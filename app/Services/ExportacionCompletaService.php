<?php

namespace App\Services;

use App\Enums\EstatusServidor;
use App\Models\Activo;
use App\Models\Asignacion;
use App\Models\Desincorporacion;
use App\Models\EstadoActivo;
use App\Models\HistorialEvento;
use App\Models\Mantenimiento;
use App\Models\ModeloActivo;
use App\Models\Personal;
use App\Models\TarjetaRed;
use App\Models\Ubicacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Genera la exportación completa del sistema en formato CSV (RFC 4180, BOM UTF-8).
 *
 * La entidad `activos` es compatible bidireccionalmente con ImportacionService;
 * el resto son volcados por entidad para respaldo/analítica.
 */
class ExportacionCompletaService
{
    private const DELIMITADOR = ',';

    /**
     * Entidades exportables y su etiqueta visible en la UI.
     *
     * @var array<string, string>
     */
    public const ETIQUETAS = [
        'activos' => 'Activos (inventario completo)',
        'personal' => 'Personal (directorio completo)',
        'asignaciones' => 'Asignaciones (historial)',
        'mantenimientos' => 'Mantenimientos y evidencias',
        'desincorporaciones' => 'Desincorporaciones (vigentes y anuladas)',
        'catalogos' => 'Catálogos (estados, ubicaciones, modelos)',
        'historial' => 'Auditoría (historial de eventos)',
        'usuarios' => 'Usuarios del sistema',
    ];

    /**
     * Entidades cuya exportación está reservada al rol Admin.
     *
     * @var array<int, string>
     */
    public const SOLO_ADMIN = ['historial', 'usuarios'];

    /**
     * Cabeceras canónicas exportadas.
     * Mapeadas de forma 1:1 con las columnas interpretadas por ImportacionService.
     *
     * @var array<int, string>
     */
    public const CABECERAS = [
        'Compañía',
        'Nombre de Activo',
        'Etiqueta de Activo',
        'Modelo',
        'Modelo n.',
        'Categoría',
        'Fabricante',
        'Serial',
        'Comprado',
        'Costo',
        'Número de Orden',
        'Proveedor',
        'Factura',
        'Garantía (meses)',
        'Localización',
        'Ubicación Predeterminada',
        'Asignado',
        'Tipo',
        'Username',
        'Responsable',
        'Departamento',
        'Estado',
        'Notas',
        'Dirección MAC',
        'IP',
        'Sistema Operativo',
        'Memoria RAM',
        'Capacidad Disco',
        'Estatus Servidor',
        'Tarjetas de Red',
        'Desincorporado',
        'Fecha Desincorporación',
        'Tipo Desincorporación',
        'Destinatario Desincorporación',
        'Motivo Desincorporación',
    ];

    /**
     * Exporta el total de la data de activos (alias de `activos`).
     */
    public function exportar(?string $nombreArchivo = null): StreamedResponse
    {
        return $this->exportarEntidad('activos', $nombreArchivo);
    }

    /**
     * Exporta la entidad indicada (whitelist) en streaming CSV con BOM UTF-8.
     *
     * @throws \InvalidArgumentException si la entidad no está en la whitelist
     */
    public function exportarEntidad(string $entidad, ?string $nombreArchivo = null): StreamedResponse
    {
        if (! array_key_exists($entidad, self::ETIQUETAS)) {
            throw new \InvalidArgumentException("Entidad de exportación no válida: {$entidad}");
        }

        // Los catálogos unen tablas heterogéneas, tienen volcado propio.
        if ($entidad === 'catalogos') {
            return $this->exportarCatalogos();
        }

        [$cabeceras, $consulta, $mapear] = match ($entidad) {
            'activos' => $this->fuenteActivos(),
            'personal' => $this->fuentePersonal(),
            'asignaciones' => $this->fuenteAsignaciones(),
            'mantenimientos' => $this->fuenteMantenimientos(),
            'desincorporaciones' => $this->fuenteDesincorporaciones(),
            'historial' => $this->fuenteHistorial(),
            'usuarios' => $this->fuenteUsuarios(),
        };

        $nombre = $nombreArchivo ?? $entidad.'-'.now()->format('Y-m-d-His').'.csv';

        return $this->generarCsv($nombre, $cabeceras, $consulta, $mapear);
    }

    /**
     * Escribe el CSV en streaming (RFC 4180, BOM UTF-8) procesando la consulta en bloques.
     *
     * @param  array<int, string>  $cabeceras
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $consulta
     * @param  callable(Model): array<int, mixed>  $mapear
     */
    private function generarCsv(string $nombre, array $cabeceras, Builder $consulta, callable $mapear): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabeceras, $consulta, $mapear) {
            $salida = fopen('php://output', 'w');

            // BOM UTF-8 para compatibilidad universal (Excel, editores)
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, array_map($this->sanearCelda(...), $cabeceras), self::DELIMITADOR);

            foreach ($consulta->lazy(500) as $registro) {
                fputcsv($salida, array_map($this->sanearCelda(...), $mapear($registro)), self::DELIMITADOR);
            }

            fclose($salida);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Fuente de la entidad `activos` (paridad 1:1 con ImportacionService).
     *
     * @return array{0: array<int, string>, 1: Builder<Activo>, 2: callable(Activo): array<int, mixed>}
     */
    private function fuenteActivos(): array
    {
        $consulta = Activo::query()
            ->with([
                'compra',
                'asignacionActiva.personal.ubicacion',
                'ubicacionCatalogo',
                'modeloCatalogo',
                'estadoCatalogo',
                'tarjetasRed.switchCatalogo',
                'desincorporacion',
            ])
            ->orderBy('id');

        return [self::CABECERAS, $consulta, $this->mapearFila(...)];
    }

    /**
     * Fuente de la entidad `personal` (directorio completo, incluye inactivos).
     *
     * @return array{0: array<int, string>, 1: Builder<Personal>, 2: callable(Personal): array<int, mixed>}
     */
    private function fuentePersonal(): array
    {
        $cabeceras = ['Nombre', 'Apellido', 'Usuario', 'Cargo', 'Departamento / Ubicación', 'Estado', 'Observaciones'];

        $consulta = Personal::query()->with('ubicacion')->orderBy('id');

        return [
            $cabeceras,
            $consulta,
            fn (Personal $persona): array => [
                $persona->Nombre,
                $persona->Apellido,
                $persona->Nombre_usuario,
                $persona->Cargo,
                $persona->ubicacion?->Nombre ?? '',
                $persona->Estado,
                $persona->Observaciones,
            ],
        ];
    }

    /**
     * Fuente de la entidad `asignaciones` (historial completo, activas y cerradas).
     *
     * @return array{0: array<int, string>, 1: Builder<Asignacion>, 2: callable(Asignacion): array<int, mixed>}
     */
    private function fuenteAsignaciones(): array
    {
        $cabeceras = ['Fecha Asignación', 'Etiqueta', 'Equipo', 'Persona', 'Fecha Devolución', 'Estado', 'Observaciones', 'Registrado por'];

        $consulta = Asignacion::query()->with(['activo', 'personal', 'usuario'])->orderBy('id');

        return [
            $cabeceras,
            $consulta,
            fn (Asignacion $asignacion): array => [
                $asignacion->Fecha_asignacion?->format('Y-m-d') ?? '',
                $asignacion->activo?->Etiqueta_activo ?? '',
                $asignacion->activo?->Nombre_de_activo ?? '',
                $asignacion->personal?->NombreCompleto ?? '',
                $asignacion->Fecha_devolucion?->format('Y-m-d') ?? '',
                $asignacion->Estado,
                $asignacion->Observaciones,
                $asignacion->usuario?->name ?? '',
            ],
        ];
    }

    /**
     * Fuente de la entidad `mantenimientos` (con metadata de evidencias, nunca binarios).
     *
     * @return array{0: array<int, string>, 1: Builder<Mantenimiento>, 2: callable(Mantenimiento): array<int, mixed>}
     */
    private function fuenteMantenimientos(): array
    {
        $cabeceras = ['Fecha', 'Etiqueta', 'Equipo', 'Tipo', 'Descripción', 'Evidencias', 'Registrado por'];

        $consulta = Mantenimiento::query()->with(['activo', 'evidencias', 'usuario'])->orderBy('id');

        return [
            $cabeceras,
            $consulta,
            fn (Mantenimiento $mantenimiento): array => [
                $mantenimiento->Fecha_mantenimiento?->format('Y-m-d') ?? '',
                $mantenimiento->activo?->Etiqueta_activo ?? '',
                $mantenimiento->activo?->Nombre_de_activo ?? '',
                $mantenimiento->Tipo,
                $mantenimiento->Descripcion,
                $mantenimiento->evidencias->count(),
                $mantenimiento->usuario?->name ?? '',
            ],
        ];
    }

    /**
     * Fuente de la entidad `desincorporaciones` (vigentes y anuladas).
     *
     * @return array{0: array<int, string>, 1: Builder<Desincorporacion>, 2: callable(Desincorporacion): array<int, mixed>}
     */
    private function fuenteDesincorporaciones(): array
    {
        $cabeceras = ['Fecha', 'Etiqueta', 'Equipo', 'Tipo de Baja', 'Destinatario', 'Detalle Destino', 'Referencia', 'Monto', 'Moneda', 'Motivo', 'Estado', 'Registrado por'];

        $consulta = Desincorporacion::query()->with(['activo', 'usuario'])->orderBy('id');

        return [
            $cabeceras,
            $consulta,
            fn (Desincorporacion $desincorporacion): array => [
                $desincorporacion->Fecha?->format('Y-m-d') ?? '',
                $desincorporacion->activo?->Etiqueta_activo ?? '',
                $desincorporacion->activo?->Nombre_de_activo ?? '',
                $desincorporacion->Tipo,
                $desincorporacion->Destinatario,
                $desincorporacion->Detalle_destino,
                $desincorporacion->Referencia,
                $desincorporacion->Monto,
                $desincorporacion->Moneda,
                $desincorporacion->Motivo,
                $desincorporacion->Estado,
                $desincorporacion->usuario?->name ?? '',
            ],
        ];
    }

    /**
     * Volcado de los tres catálogos (estados + ubicaciones + modelos) en un solo CSV.
     */
    public function exportarCatalogos(): StreamedResponse
    {
        $nombre = 'catalogos-'.now()->format('Y-m-d-His').'.csv';
        $cabeceras = ['Catálogo', 'Nombre', 'Grupo / Detalle', 'Extra', 'Estado'];

        return response()->streamDownload(function () use ($cabeceras) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, array_map($this->sanearCelda(...), $cabeceras), self::DELIMITADOR);

            foreach (EstadoActivo::query()->orderBy('id')->lazy(500) as $estado) {
                fputcsv($salida, array_map($this->sanearCelda(...), [
                    'Estados',
                    $estado->Nombre,
                    $estado->Grupo,
                    'Deployed: '.($estado->Deployed ? 'Sí' : 'No').'; Deployable: '.($estado->Deployable ? 'Sí' : 'No'),
                    (string) $estado->Estado,
                ]), self::DELIMITADOR);
            }

            foreach (Ubicacion::query()->orderBy('id')->lazy(500) as $ubicacion) {
                fputcsv($salida, array_map($this->sanearCelda(...), [
                    'Ubicaciones',
                    $ubicacion->Nombre,
                    $ubicacion->Descripcion,
                    '',
                    $ubicacion->Estado,
                ]), self::DELIMITADOR);
            }

            foreach (ModeloActivo::query()->orderBy('id')->lazy(500) as $modelo) {
                fputcsv($salida, array_map($this->sanearCelda(...), [
                    'Modelos',
                    $modelo->Nombre,
                    $modelo->Fabricante,
                    $modelo->Categoria,
                    $modelo->Estado,
                ]), self::DELIMITADOR);
            }

            fclose($salida);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Fuente de la entidad `historial` (auditoría completa; reservada a Admin).
     *
     * @return array{0: array<int, string>, 1: Builder<HistorialEvento>, 2: callable(HistorialEvento): array<int, mixed>}
     */
    private function fuenteHistorial(): array
    {
        $cabeceras = ['Fecha', 'Tipo de Evento', 'Activo', 'Descripción', 'Valor Anterior', 'Valor Nuevo', 'Registrado por'];

        $consulta = HistorialEvento::query()->with(['activo', 'usuario'])->orderBy('id');

        return [
            $cabeceras,
            $consulta,
            fn (HistorialEvento $evento): array => [
                $evento->Fecha?->format('Y-m-d H:i:s') ?? '',
                $evento->Tipo_evento,
                $evento->activo?->Etiqueta_activo ?? '',
                $evento->Descripcion,
                $evento->Valor_anterior,
                $evento->Valor_nuevo,
                $evento->usuario?->name ?? '',
            ],
        ];
    }

    /**
     * Fuente de la entidad `usuarios` (operadores del sistema; reservada a Admin).
     * Nunca incluye password ni remember_token.
     *
     * @return array{0: array<int, string>, 1: Builder<User>, 2: callable(User): array<int, mixed>}
     */
    private function fuenteUsuarios(): array
    {
        $cabeceras = ['Nombre', 'Usuario', 'Correo', 'Rol', 'Activo', 'Creado'];

        $consulta = User::query()
            ->select(['id', 'name', 'username', 'email', 'role', 'activo', 'created_at'])
            ->orderBy('id');

        return [
            $cabeceras,
            $consulta,
            fn (User $usuario): array => [
                $usuario->name,
                $usuario->username,
                $usuario->email,
                $usuario->role,
                $usuario->activo ? 'Sí' : 'No',
                $usuario->created_at?->format('Y-m-d H:i:s') ?? '',
            ],
        ];
    }

    /**
     * Transforma una entidad Activo en la fila CSV correspondiente.
     *
     * @return array<int, mixed>
     */
    public function mapearFila(Activo $activo): array
    {
        $asignacion = $activo->asignacionActiva;
        $personal = $asignacion?->personal;
        $compra = $activo->compra;
        $desinc = $activo->desincorporacion;

        // Tarjetas de red resumidas en un formato serializado interpretable
        $tarjetasStr = $activo->tarjetasRed->map(function (TarjetaRed $nic): string {
            $partes = ["#{$nic->Numero}"];
            if ($nic->Nombre) {
                $partes[] = $nic->Nombre;
            }
            if ($nic->Direccion_MAC) {
                $partes[] = "MAC: {$nic->Direccion_MAC}";
            }
            if ($nic->IP) {
                $partes[] = "IP: {$nic->IP}";
            }
            $sw = $nic->switchCatalogo?->Etiqueta_activo ?? $nic->Switch;
            if ($sw) {
                $partes[] = "SW: {$sw}".($nic->Puerto ? ":{$nic->Puerto}" : '');
            }

            return implode(' | ', $partes);
        })->implode(' ;; ');

        $esUser = $personal !== null;
        $asignado = $esUser
            ? $personal->NombreCompleto
            : ($activo->Ubicacion ?: '');

        $tipo = $esUser ? 'user' : ($activo->Ubicacion ? 'location' : '');

        return [
            'Tubrica', // Compañía
            $activo->Nombre_de_activo ?? '',
            $activo->Etiqueta_activo ?? '',
            $activo->modeloCatalogo?->Nombre ?? $activo->Modelo ?? '',
            $activo->modeloCatalogo?->Numero ?? '',
            $activo->Categoria ?? '',
            $activo->Fabricante ?? '',
            $activo->Serial ?? '',
            $compra?->Fecha_Compra?->format('Y-m-d') ?? '',
            $compra?->Costo_compra !== null ? (string) $compra->Costo_compra : '',
            $compra?->Numero_Requisicion ?? '',
            $compra?->Proveedor ?? '',
            $compra?->Numero_factura ?? '',
            $compra?->Tiempo_garantia !== null ? (string) $compra->Tiempo_garantia : '',
            $activo->ubicacionCatalogo?->Nombre ?? $activo->Ubicacion ?? '',
            $activo->Ubicacion_Predeterminada ?? '',
            $asignado,
            $tipo,
            $personal?->Nombre_usuario ?? '',
            $personal?->NombreCompleto ?? '',
            $personal?->ubicacion?->Nombre ?? '',
            $activo->estadoCatalogo?->Nombre ?? $activo->Estado ?? '',
            $activo->Observaciones ?? '',
            $activo->Direccion_MAC ?? '',
            $activo->IP ?? '',
            $activo->Sistema_Operativo ?? '',
            $activo->Memoria_RAM ?? '',
            $activo->Capacidad_Disco ?? '',
            $activo->Estatus_Servidor ? EstatusServidor::etiquetaDe($activo->Estatus_Servidor) : '',
            $tarjetasStr,
            $desinc ? 'Sí' : 'No',
            $desinc?->Fecha?->format('Y-m-d') ?? '',
            $desinc?->Tipo ?? '',
            $desinc?->Destinatario ?? '',
            $desinc?->Motivo ?? '',
        ];
    }

    /**
     * Neutraliza la inyección de fórmulas (CSV injection).
     */
    private function sanearCelda(mixed $valor): mixed
    {
        if ($valor === null || is_int($valor) || is_float($valor)) {
            return $valor;
        }

        $texto = (string) $valor;

        if ($texto !== '' && ! is_numeric($texto) && preg_match('/^[=+\-@\t\r]/', $texto) === 1) {
            return "'".$texto;
        }

        return $texto;
    }
}
