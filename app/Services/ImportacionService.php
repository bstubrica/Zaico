<?php

namespace App\Services;

use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Compra;
use App\Models\EstadoActivo;
use App\Models\ImportacionDetalle;
use App\Models\ImportacionLog;
use App\Models\Personal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Importa el CSV de Snipec IT (custom-assets-report) con upsert por Etiqueta_activo.
 *
 * Fase 1: planificación en seco (solo lectura) por fila.
 * Fase 2: aplicación en lotes de 500 (solo si no es dry-run).
 * Fase 3: persistencia de IMPORTACIONES_LOG + IMPORTACIONES_DETALLE (siempre).
 */
class ImportacionService
{
    private const LOTE = 500;

    /** @var array<int, string> Columnas críticas que deben existir en la cabecera. */
    private const COLUMNAS_REQUERIDAS = ['nombre de activo', 'etiqueta de activo', 'estado'];

    public function __construct(private readonly HistorialService $historial) {}

    /**
     * @return array{0: array<string, int>, 1: array<int, array<string, string>>} [mapa cabecera=>indice, filas normalizadas]
     */
    private function leer(string $rutaArchivo): array
    {
        $handle = @fopen($rutaArchivo, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('No se pudo abrir el archivo CSV.');
        }

        try {
            $cabeceraCruda = fgetcsv($handle, 0, ',', '"', '\\');

            if ($cabeceraCruda === false) {
                throw new InvalidArgumentException('El archivo CSV está vacío.');
            }

            $mapa = [];
            foreach ($cabeceraCruda as $indice => $titulo) {
                $mapa[$this->normalizarTitulo((string) $titulo)] = $indice;
            }

            $faltantes = array_diff(self::COLUMNAS_REQUERIDAS, array_keys($mapa));
            if ($faltantes !== []) {
                throw new InvalidArgumentException(
                    'El CSV no tiene las columnas esperadas. Faltan: '.implode(', ', $faltantes).'.'
                );
            }

            $filas = [];
            $numero = 0;
            while (($registro = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $numero++;
                if (count($registro) < 3) {
                    continue;
                }
                $fila = [];
                foreach ($mapa as $titulo => $indice) {
                    $fila[$titulo] = isset($registro[$indice]) ? (string) $registro[$indice] : '';
                }
                $filas[$numero] = $fila;
            }

            if ($filas === []) {
                throw new InvalidArgumentException('El archivo CSV no contiene filas de datos.');
            }

            return [$mapa, $filas];
        } finally {
            fclose($handle);
        }
    }

    public function importarCsv(string $rutaArchivo, string $nombreOriginal, bool $dryRun, int $usuarioId): ImportacionLog
    {
        [, $filas] = $this->leer($rutaArchivo);

        $log = ImportacionLog::create([
            'Nombre_archivo' => Str::limit($nombreOriginal, 255),
            'fk_usuario' => $usuarioId,
            'Modo' => $dryRun ? 'DryRun' : 'Commit',
            'Estado' => 'EnProceso',
            'Total_filas' => count($filas),
        ]);

        $planes = $this->planear($filas);

        if (! $dryRun) {
            $planes = $this->aplicar($planes, $usuarioId);
        }

        $conteos = [
            'Insertadas' => 0,
            'Actualizadas' => 0,
            'Errores' => 0,
        ];

        foreach ($planes as $plan) {
            if ($plan['accion'] === 'Insertado') {
                $conteos['Insertadas']++;
            } elseif ($plan['accion'] === 'Actualizado') {
                $conteos['Actualizadas']++;
            } elseif ($plan['accion'] === 'Error') {
                $conteos['Errores']++;
            }
        }

        DB::transaction(function () use ($log, $planes, $conteos) {
            foreach ($planes as $plan) {
                ImportacionDetalle::create([
                    'fk_importacion' => $log->id,
                    'Fila_numero' => $plan['fila'],
                    'Etiqueta' => $plan['etiqueta'],
                    'Accion' => $plan['accion'],
                    'Mensaje' => $plan['mensaje'],
                    'Datos_previos' => $plan['datos_previos'],
                ]);
            }

            $log->update([...$conteos, 'Estado' => 'Completado']);
        });

        return $log->load('detalles');
    }

    /**
     * Fase 1: por cada fila decide qué haría, sin escribir en la BD.
     *
     * @param  array<int, array<string, string>>  $filas
     * @return array<int, array<string, mixed>>
     */
    private function planear(array $filas): array
    {
        $estados = EstadoActivo::query()->get()->keyBy('Nombre');
        $personasPorUsuario = Personal::query()->pluck('id', 'Nombre_usuario');
        $planes = [];
        $etiquetasVistas = [];
        $serialesVistos = [];

        foreach ($filas as $numero => $fila) {
            $planes[] = $this->planearFila($numero, $fila, $estados, $personasPorUsuario, $etiquetasVistas, $serialesVistos);
        }

        return $planes;
    }

    /**
     * @param  Collection<string, EstadoActivo>  $estados
     * @param  Collection<string, int>  $personasPorUsuario
     * @param  array<string, true>  $etiquetasVistas
     * @param  array<string, true>  $serialesVistos
     * @return array<string, mixed>
     */
    private function planearFila(int $numero, array $fila, Collection $estados, Collection $personasPorUsuario, array &$etiquetasVistas, array &$serialesVistos): array
    {
        $plan = [
            'fila' => $numero,
            'etiqueta' => null,
            'accion' => 'SinCambios',
            'mensaje' => null,
            'datos_previos' => null,
            'modo' => null,
        ];

        $etiqueta = trim($fila['etiqueta de activo'] ?? '');
        $nombre = trim($fila['nombre de activo'] ?? '');
        $estadoNombre = trim($fila['estado'] ?? '');
        $plan['etiqueta'] = $etiqueta !== '' ? $etiqueta : null;

        if ($etiqueta === '') {
            return $this->error($plan, 'Etiqueta de activo vacía.');
        }

        if (isset($etiquetasVistas[$etiqueta])) {
            $plan['mensaje'] = 'Etiqueta repetida en el archivo; se conserva el registro ya importado.';

            return $plan;
        }

        if ($nombre === '') {
            return $this->error($plan, 'Nombre de activo vacío.');
        }

        if ($estadoNombre === '') {
            return $this->error($plan, 'Estado vacío.');
        }

        $estado = $estados->get($estadoNombre);
        $estadoNuevo = $estado === null;

        $grupo = $estado?->Grupo ?? $this->grupoDeEstado($estadoNombre);

        $serialBruto = trim($fila['serial'] ?? '');
        $serial = ($serialBruto === '' || strcasecmp($serialBruto, 'N/A') === 0) ? null : $serialBruto;

        $modelo = trim($fila['modelo'] ?? '');
        $modeloN = trim($fila['modelo n.'] ?? '');
        $notas = trim($fila['notas'] ?? '');

        $observaciones = [];
        if ($modeloN !== '' && $modeloN !== $modelo) {
            $observaciones[] = 'Modelo n.: '.$modeloN;
        }
        if ($notas !== '') {
            $observaciones[] = $notas;
        }

        $attrs = array_filter([
            'Nombre_de_activo' => $nombre,
            'Etiqueta_activo' => $etiqueta,
            'Serial' => $serial,
            'Modelo' => $modelo !== '' ? $modelo : null,
            'Categoria' => ($c = trim($fila['categoria'] ?? '')) !== '' ? $c : null,
            'Fabricante' => ($f = trim($fila['fabricante'] ?? '')) !== '' ? $f : null,
            'Ubicacion' => ($u = trim($fila['localizacion'] ?? '')) !== '' ? $u : null,
            'Ubicacion_Predeterminada' => ($up = trim($fila['ubicacion predeterminada'] ?? '')) !== '' ? $up : null,
            'Observaciones' => $observaciones !== [] ? implode("\n", $observaciones) : null,
        ], fn ($valor) => $valor !== null);

        $attrs['Estado'] = $grupo;
        $attrs['fk_estado_nombre'] = $estadoNombre;

        $avisoSerial = null;
        if ($serial !== null) {
            $conflicto = Activo::where('Serial', $serial)
                ->when($plan['etiqueta'] !== null, fn ($query) => $query->where('Etiqueta_activo', '!=', $plan['etiqueta']))
                ->exists();

            if ($conflicto) {
                unset($attrs['Serial']);
                $avisoSerial = 'El serial ya existe en otro activo; se conserva el existente.';
            } elseif (isset($serialesVistos[$serial])) {
                unset($attrs['Serial']);
                $avisoSerial = 'Serial repetido en el archivo; se deja vacío y se conserva el primer registro.';
            } else {
                $serialesVistos[$serial] = true;
            }
        }

        $existente = Activo::where('Etiqueta_activo', $etiqueta)->first();

        $cambios = [];
        if ($existente === null) {
            $modo = 'crear';
            $accion = 'Insertado';
            $mensaje = 'Activo nuevo.';
        } else {
            $modo = 'actualizar';
            $previos = [];

            foreach ($attrs as $campo => $valor) {
                if ($campo === 'fk_estado_nombre') {
                    if ($estadoNuevo || (int) $existente->fk_estado !== (int) $estado?->id) {
                        $cambios['Estado'] = $grupo;
                        $cambios['fk_estado_nombre'] = $estadoNombre;
                    }

                    continue;
                }

                $actual = $existente->{$campo};
                $igual = $campo === 'Observaciones'
                    ? trim((string) $actual) === trim((string) $valor)
                    : (string) $actual === (string) $valor;

                if (! $igual) {
                    $cambios[$campo] = $valor;
                    $previos[$campo] = $actual;
                }
            }

            $accion = $cambios === [] ? 'SinCambios' : 'Actualizado';
            $mensaje = $accion === 'SinCambios' ? 'Sin cambios respecto a la BD.' : 'Campos actualizados: '.implode(', ', array_keys($cambios)).'.';
            $plan['datos_previos'] = $previos !== [] ? $previos : null;
        }

        if ($avisoSerial !== null) {
            $mensaje = trim(($mensaje ?? '').' '.$avisoSerial);
        }

        // PERSONAL + ASIGNACIÓN (solo Tipo = user)
        $tipo = trim($fila['tipo'] ?? '');
        $username = trim($fila['username'] ?? '');
        $asignado = trim($fila['asignado'] ?? '');
        $personaId = null;
        $crearPersona = null;
        $crearAsignacion = false;

        if ($tipo === 'user' && $username !== '') {
            $personaId = $personasPorUsuario->get($username);

            if ($personaId === null) {
                [$personaNombre, $personaApellido] = $this->separarNombre($asignado !== '' ? $asignado : $username);
                $crearPersona = [
                    'Nombre_usuario' => $username,
                    'Nombre' => $personaNombre,
                    'Apellido' => $personaApellido,
                    'Estado' => 'Activo',
                ];
            }

            if (str_starts_with($estadoNombre, 'Asignado')) {
                $yaAsignado = $existente?->asignacionActiva()->exists() ?? false;

                if (! $yaAsignado) {
                    $crearAsignacion = true;
                }
            }
        }

        $plan['persona_id'] = $personaId;

        // COMPRAS
        $compraAttrs = array_filter([
            'Fecha_Compra' => ($comprado = trim($fila['comprado'] ?? '')) !== '' ? substr($comprado, 0, 10) : null,
            'Costo_compra' => ($costo = trim($fila['costo'] ?? '')) !== '' ? $costo : null,
            'Numero_Requisicion' => ($orden = trim($fila['numero de orden'] ?? '')) !== '' ? $orden : null,
            'Proveedor' => ($proveedor = trim($fila['proveedor'] ?? '')) !== '' ? $proveedor : null,
        ], fn ($valor) => $valor !== null);

        $crearCompra = false;
        $compraCambios = [];

        if ($compraAttrs !== []) {
            $compraActual = $existente?->compra()->first();

            if ($compraActual === null) {
                $crearCompra = true;
            } else {
                foreach ($compraAttrs as $campo => $valor) {
                    $actual = $campo === 'Fecha_Compra'
                        ? $compraActual->{$campo}?->format('Y-m-d')
                        : (string) $compraActual->{$campo};

                    if ($actual !== (string) $valor) {
                        $compraCambios[$campo] = $valor;
                    }
                }
            }
        }

        if ($crearPersona !== null || $crearAsignacion || $crearCompra || $compraCambios !== []) {
            if ($accion === 'SinCambios') {
                $accion = 'Actualizado';
                $mensaje = 'Cambios secundarios: '.implode(', ', array_filter([
                    $crearPersona !== null ? "persona {$username}" : null,
                    $crearAsignacion ? 'asignación' : null,
                    ($crearCompra || $compraCambios !== []) ? 'compra' : null,
                ])).'.';
            } elseif ($accion === 'Insertado') {
                $mensaje = trim(($mensaje ?? '').' Se creará personal/asignación/compra según aplique.');
            }
        }

        $plan['accion'] = $accion;
        $plan['mensaje'] = $mensaje;
        $plan['modo'] = $modo;
        $plan['attrs'] = $attrs;
        $plan['cambios'] = $cambios;
        $plan['estado_nuevo'] = $estadoNuevo;
        $plan['crear_persona'] = $crearPersona;
        $plan['crear_asignacion'] = $crearAsignacion;
        $plan['asignado_a'] = $asignado;
        $plan['crear_compra'] = $crearCompra;
        $plan['compra_cambios'] = $compraCambios;
        $plan['compra_attrs'] = $compraAttrs;

        if ($plan['accion'] !== 'Error') {
            $etiquetasVistas[$etiqueta] = true;
        }

        return $plan;
    }

    /**
     * Fase 2: aplica los planes por lotes. Devuelve los planes con acciones finales.
     *
     * @param  array<int, array<string, mixed>>  $planes
     * @return array<int, array<string, mixed>>
     */
    private function aplicar(array $planes, int $usuarioId): array
    {
        $planes = $this->crearEstadosPendientes($planes);

        $planPorFila = [];

        foreach (array_chunk($planes, self::LOTE) as $lote) {
            try {
                DB::transaction(function () use ($lote, $usuarioId, &$planPorFila) {
                    foreach ($lote as $plan) {
                        $planPorFila[$plan['fila']] = $this->aplicarFila($plan, $usuarioId);
                    }
                });
            } catch (Throwable $errorLote) {
                // Reintenta fila por fila para aislar el fallo.
                foreach ($lote as $plan) {
                    if ($plan['accion'] === 'Error') {
                        continue;
                    }

                    try {
                        DB::transaction(function () use (&$planPorFila, $plan, $usuarioId): void {
                            $planPorFila[$plan['fila']] = $this->aplicarFila($plan, $usuarioId);
                        });
                    } catch (Throwable $errorFila) {
                        $plan['accion'] = 'Error';
                        $plan['mensaje'] = 'Error al persistir: '.$errorFila->getMessage();
                        $planPorFila[$plan['fila']] = $plan;
                    }
                }
            }
        }

        foreach ($planes as $indice => $plan) {
            $planes[$indice] = $planPorFila[$plan['fila']] ?? $plan;
        }

        return $planes;
    }

    /**
     * Crea en el catálogo los estados nuevos detectados en la fase de planificación.
     *
     * @param  array<int, array<string, mixed>>  $planes
     * @return array<int, array<string, mixed>>
     */
    private function crearEstadosPendientes(array $planes): array
    {
        $pendientes = [];

        foreach ($planes as $plan) {
            $nombre = $plan['attrs']['fk_estado_nombre'] ?? null;

            if ($nombre !== null && ! isset($pendientes[$nombre])) {
                $pendientes[$nombre] = $plan['attrs']['Estado'];
            }
        }

        DB::transaction(function () use ($pendientes) {
            foreach ($pendientes as $nombre => $grupo) {
                EstadoActivo::firstOrCreate(
                    ['Nombre' => $nombre],
                    ['Grupo' => $grupo, 'Deployed' => false, 'Deployable' => false, 'Estado' => 1]
                );
            }
        });

        $ids = EstadoActivo::pluck('id', 'Nombre');

        foreach ($planes as $indice => $plan) {
            $nombre = $plan['attrs']['fk_estado_nombre'] ?? null;

            if ($nombre !== null) {
                $planes[$indice]['attrs']['fk_estado'] = $ids[$nombre] ?? null;
            }

            if ($plan['modo'] === 'actualizar' && isset($plan['cambios']['fk_estado_nombre'])) {
                $planes[$indice]['cambios']['fk_estado'] = $ids[$plan['cambios']['fk_estado_nombre']] ?? null;
                unset($planes[$indice]['cambios']['fk_estado_nombre']);
            }

            unset($planes[$indice]['attrs']['fk_estado_nombre']);
        }

        return $planes;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function aplicarFila(array $plan, int $usuarioId): array
    {
        if ($plan['accion'] === 'Error' || $plan['modo'] === null) {
            return $plan;
        }

        $personaId = $plan['persona_id'] ?? null;

        if ($plan['crear_persona'] !== null) {
            $personaId = Personal::updateOrCreate(
                ['Nombre_usuario' => $plan['crear_persona']['Nombre_usuario']],
                $plan['crear_persona']
            )->id;
        }

        if ($plan['modo'] === 'crear') {
            // Defensa: entre la planificación y la aplicación la etiqueta pudo aparecer.
            if (Activo::where('Etiqueta_activo', $plan['etiqueta'])->exists()) {
                $plan['accion'] = 'SinCambios';
                $plan['mensaje'] = 'La etiqueta ya existe en la BD; se conserva el registro existente.';

                return $plan;
            }

            if (isset($plan['attrs']['Serial']) && Activo::where('Serial', $plan['attrs']['Serial'])->exists()) {
                unset($plan['attrs']['Serial']);
                $plan['mensaje'] = trim(($plan['mensaje'] ?? '').' El serial ya existe en la BD; se deja vacío.');
            }

            $activo = Activo::create($plan['attrs']);
        } else {
            $activo = Activo::where('Etiqueta_activo', $plan['etiqueta'])->first();

            if ($activo === null) {
                return $this->error($plan, 'El activo desapareció entre la planificación y la aplicación.');
            }

            if ($plan['cambios'] !== []) {
                $activo->update($plan['cambios']);
            }
        }

        if ($plan['crear_asignacion'] && $personaId !== null && ! $activo->asignacionActiva()->exists()) {
            Asignacion::create([
                'fk_Activo' => $activo->id,
                'fk_Personal' => $personaId,
                'fk_usuario' => $usuarioId,
                'Fecha_asignacion' => now()->toDateString(),
                'Observaciones' => 'Importado desde Snipec IT',
                'Estado' => 'Activa',
            ]);
        }

        if ($plan['crear_compra']) {
            Compra::create([...$plan['compra_attrs'], 'fk_activo' => $activo->id]);
        } elseif ($plan['compra_cambios'] !== []) {
            $activo->compra()->update($plan['compra_cambios']);
        }

        if ($plan['accion'] === 'Insertado' || $plan['accion'] === 'Actualizado') {
            $this->historial->registrar(
                $activo->id,
                'Importacion',
                "Importación Snipec IT ({$plan['accion']})",
                usuarioId: $usuarioId,
            );
        }

        return $plan;
    }

    public function historialImportaciones(): LengthAwarePaginator
    {
        return ImportacionLog::with('usuario:id,name')
            ->orderByDesc('Fecha')
            ->paginate(25);
    }

    private function error(array $plan, string $mensaje): array
    {
        $plan['accion'] = 'Error';
        $plan['mensaje'] = $mensaje;

        return $plan;
    }

    private function normalizarTitulo(string $titulo): string
    {
        $sinAcentos = strtr($titulo, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n', 'Ü' => 'u',
        ]);

        return mb_strtolower(trim($sinAcentos));
    }

    private function grupoDeEstado(string $nombre): string
    {
        $segmento = explode('>', $nombre, 2)[0];
        $segmento = explode('(', $segmento, 2)[0];

        return Str::limit(trim($segmento) ?: 'Otro', 30, '');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function separarNombre(string $nombreCompleto): array
    {
        $partes = preg_split('/\s+/', trim($nombreCompleto)) ?: [];

        if (count($partes) === 1) {
            return [$partes[0], $partes[0]];
        }

        $apellido = array_pop($partes);

        return [implode(' ', $partes), $apellido];
    }
}
