<?php

namespace App\Services;

use App\Enums\FormatoCarta;
use App\Enums\TipoDesincorporacion;
use App\Models\Activo;
use App\Models\Desincorporacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls as XlsWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Construye y renderiza la carta de desincorporación: el documento formal que
 * acredita la baja de los equipos, con el membrete de la organización y las
 * firmas. Se puede emitir por un único equipo o como acta del listado filtrado.
 */
class CartaDesincorporacionService
{
    public function __construct(private readonly DesincorporacionService $desincorporaciones) {}

    /**
     * Datos de la carta para un único registro de baja.
     *
     * @return array<string, mixed>
     */
    public function datosIndividual(Desincorporacion $desincorporacion, string $emitidaPor): array
    {
        $desincorporacion->loadMissing(['activo.estadoCatalogo', 'usuario']);

        $activo = $desincorporacion->activo;
        $baja = $this->filaBaja($desincorporacion);

        return $this->datosBase('individual', $emitidaPor) + [
            'folio' => $this->folio(config('zaico.carta.folio_prefijo'), (string) $desincorporacion->id),
            'baja' => $baja,
            'equipos' => $activo ? [$this->filaEquipo($activo, $desincorporacion)] : [],
            'filtros_resumen' => null,
        ];
    }

    /**
     * Datos de la carta como acta del listado que cumple los filtros dados.
     *
     * @param  array{termino?: string, tipo?: string, desde?: string, hasta?: string, sin_datos?: mixed}  $filtros
     * @return array<string, mixed>
     */
    public function datosActa(array $filtros, string $emitidaPor): array
    {
        $equipos = $this->desincorporaciones->consultaListado($filtros)
            ->get()
            ->map(fn (Activo $activo): array => $this->filaEquipo($activo, $activo->desincorporacion));

        return $this->datosBase('acta', $emitidaPor) + [
            'folio' => $this->folio(config('zaico.carta.folio_prefijo'), Carbon::now()->format('Ymd').'-'.$equipos->count()),
            'baja' => null,
            'equipos' => $equipos->all(),
            'filtros_resumen' => $this->resumenFiltros($filtros),
        ];
    }

    /**
     * Renderiza los datos en el formato solicitado.
     *
     * @param  array<string, mixed>  $datos
     */
    public function render(array $datos, FormatoCarta $formato): Response|StreamedResponse|View
    {
        return match ($formato) {
            FormatoCarta::Impresion => view('desincorporados.carta-web', $datos),
            FormatoCarta::Pdf => Pdf::loadView('desincorporados.carta-pdf', $datos)
                ->setPaper('letter')
                ->download($this->nombreArchivo($datos, $formato)),
            FormatoCarta::Xls => $this->descargarHoja($datos, FormatoCarta::Xls),
            FormatoCarta::Xlsx => $this->descargarHoja($datos, FormatoCarta::Xlsx),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function datosBase(string $modo, string $emitidaPor): array
    {
        return [
            'modo' => $modo,
            'titulo' => (string) config('zaico.carta.titulo', 'Carta de desincorporación'),
            'organizacion' => config('zaico.carta.organizacion'),
            'firmas' => config('zaico.carta.firmas'),
            'emitida_en' => Carbon::now(),
            'emitida_por' => $emitidaPor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaBaja(Desincorporacion $baja): array
    {
        return [
            'id' => $baja->id,
            'tipo' => $baja->Tipo,
            'tipo_etiqueta' => TipoDesincorporacion::etiquetaDe($baja->Tipo),
            'destinatario' => $baja->Destinatario,
            'detalle_destino' => $baja->Detalle_destino,
            'referencia' => $baja->Referencia,
            'monto' => $baja->Monto,
            'moneda' => $baja->Moneda,
            'monto_texto' => $this->montoTexto($baja->Monto, $baja->Moneda),
            'motivo' => $baja->Motivo,
            'fecha' => $baja->Fecha,
            'registrado_por' => $baja->usuario?->name,
            'estado' => $baja->Estado,
            'anulada' => $baja->Estado === 'Anulada',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaEquipo(Activo $activo, ?Desincorporacion $baja): array
    {
        return [
            'etiqueta' => $activo->Etiqueta_activo,
            'nombre' => $activo->Nombre_de_activo,
            'serial' => $activo->Serial,
            'categoria' => $activo->Categoria,
            'fabricante' => $activo->Fabricante,
            'modelo' => $activo->Modelo,
            'ubicacion' => $activo->Ubicacion,
            'estado' => $activo->estadoCatalogo?->Nombre ?? $activo->Estado,
            'tipo_etiqueta' => TipoDesincorporacion::etiquetaDe($baja?->Tipo),
            'destinatario' => $baja?->Destinatario,
            'detalle_destino' => $baja?->Detalle_destino,
            'referencia' => $baja?->Referencia,
            'monto_texto' => $this->montoTexto($baja?->Monto, $baja?->Moneda),
            'motivo' => $baja?->Motivo,
            'fecha' => $baja?->Fecha,
            'anulada' => $baja?->Estado === 'Anulada',
            'sin_datos' => $baja === null,
        ];
    }

    private function montoTexto(mixed $monto, ?string $moneda): ?string
    {
        if ($monto === null) {
            return null;
        }

        $numero = number_format((float) $monto, 2);

        return blank($moneda) ? $numero : trim($moneda.' '.$numero);
    }

    private function folio(?string $prefijo, string $sufijo): string
    {
        return Str::upper(Str::slug((string) ($prefijo ?: 'DES'))).'-'.$sufijo;
    }

    /**
     * @param  array{termino?: string, tipo?: string, desde?: string, hasta?: string, sin_datos?: mixed}  $filtros
     */
    private function resumenFiltros(array $filtros): string
    {
        $partes = [];

        if (! blank($filtros['termino'] ?? null)) {
            $partes[] = 'Búsqueda: '.$filtros['termino'];
        }

        if (! blank($filtros['tipo'] ?? null)) {
            $partes[] = 'Tipo: '.TipoDesincorporacion::etiquetaDe($filtros['tipo']);
        }

        if (! blank($filtros['desde'] ?? null)) {
            $partes[] = 'Desde: '.$filtros['desde'];
        }

        if (! blank($filtros['hasta'] ?? null)) {
            $partes[] = 'Hasta: '.$filtros['hasta'];
        }

        if (! empty($filtros['sin_datos'])) {
            $partes[] = 'Solo registros sin datos de baja';
        }

        return $partes === [] ? 'Todos los equipos desincorporados' : implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function nombreArchivo(array $datos, FormatoCarta $formato): string
    {
        $base = 'carta-desincorporacion';

        $referencia = $datos['modo'] === 'individual'
            ? (string) ($datos['equipos'][0]['etiqueta'] ?? 'equipo')
            : Carbon::now()->format('Y-m-d');

        return $base.'-'.Str::slug($referencia).'.'.$formato->extension();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function descargarHoja(array $datos, FormatoCarta $formato): StreamedResponse
    {
        $hoja = $this->hojaCalculo($datos);
        $writer = $formato === FormatoCarta::Xls ? new XlsWriter($hoja) : new XlsxWriter($hoja);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $this->nombreArchivo($datos, $formato), [
            'Content-Type' => $formato->mime(),
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function hojaCalculo(array $datos): Spreadsheet
    {
        $hoja = new Spreadsheet;
        $activa = $hoja->getActiveSheet();
        $activa->setTitle('Carta');

        $organizacion = $datos['organizacion'] ?? [];
        $fila = 1;

        $activa->setCellValue('A'.$fila, (string) ($organizacion['nombre'] ?? ''));
        $activa->mergeCells('A'.$fila.':F'.$fila);
        $activa->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(14);
        $fila++;

        $lineasOrganizacion = array_filter([
            $organizacion['rif'] ?? null,
            $organizacion['direccion'] ?? null,
            $organizacion['ciudad'] ?? null,
        ]);

        foreach ($lineasOrganizacion as $linea) {
            $activa->setCellValue('A'.$fila, (string) $linea);
            $activa->mergeCells('A'.$fila.':F'.$fila);
            $fila++;
        }

        $fila++;
        $activa->setCellValue('A'.$fila, (string) $datos['titulo']);
        $activa->mergeCells('A'.$fila.':F'.$fila);
        $activa->getStyle('A'.$fila)->getFont()->setBold(true)->setSize(12);
        $fila++;

        $activa->setCellValue('A'.$fila, 'Folio: '.$datos['folio'].'   ·   Emitida: '.$datos['emitida_en']->format('d/m/Y H:i'));
        $activa->mergeCells('A'.$fila.':F'.$fila);
        $fila += 2;

        if ($datos['modo'] === 'individual' && $datos['baja'] !== null) {
            $fila = $this->bloqueBaja($activa, $datos['baja'], $fila);
        } else {
            $activa->setCellValue('A'.$fila, 'Detalle: '.$datos['filtros_resumen']);
            $activa->mergeCells('A'.$fila.':F'.$fila);
            $fila++;
        }

        $fila = $this->tablaEquipos($activa, $datos['equipos'], $fila);

        $activa->setCellValue('A'.$fila, 'Total de equipos: '.count($datos['equipos']));
        $activa->getStyle('A'.$fila)->getFont()->setBold(true);
        $fila += 3;

        $firmas = $datos['firmas'] ?? [];
        $activa->setCellValue('A'.$fila, '_________________________');
        $activa->setCellValue('D'.$fila, '_________________________');
        $fila++;
        $activa->setCellValue('A'.$fila, (string) ($firmas['entrega'] ?? 'Entrega'));
        $activa->setCellValue('D'.$fila, (string) ($firmas['recibe'] ?? 'Recibe'));
        $fila++;

        foreach ($this->nombresFirma($datos) as $columna => $nombre) {
            $activa->setCellValue($columna.$fila, $nombre);
        }

        foreach (range('A', 'F') as $columna) {
            $activa->getColumnDimension($columna)->setWidth(20);
        }

        return $hoja;
    }

    /**
     * @param  array<string, mixed>  $baja
     */
    private function bloqueBaja(Worksheet $activa, array $baja, int $fila): int
    {
        $campos = [
            'Tipo de baja' => $baja['tipo_etiqueta'],
            'Destinatario' => $baja['destinatario'],
            'Detalle del destino' => $baja['detalle_destino'],
            'Referencia' => $baja['referencia'],
            'Monto' => $baja['monto_texto'],
            'Fecha' => $baja['fecha']?->format('d/m/Y'),
            'Registrado por' => $baja['registrado_por'],
            'Motivo' => $baja['motivo'],
            'Estado' => $baja['anulada'] ? 'ANULADA' : $baja['estado'],
        ];

        foreach ($campos as $etiqueta => $valor) {
            $activa->setCellValue('A'.$fila, $etiqueta);
            $activa->getStyle('A'.$fila)->getFont()->setBold(true);
            $activa->setCellValueExplicit('C'.$fila, (string) ($valor ?? '—'), DataType::TYPE_STRING);
            $fila++;
        }

        return $fila + 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $equipos
     */
    private function tablaEquipos(Worksheet $activa, array $equipos, int $fila): int
    {
        $cabeceras = ['Etiqueta', 'Equipo', 'Serial', 'Categoría', 'Modelo', 'Ubicación', 'Estado'];

        foreach ($cabeceras as $indice => $cabecera) {
            $columna = chr(65 + $indice);
            $activa->setCellValue($columna.$fila, $cabecera);
            $activa->getStyle($columna.$fila)->getFont()->setBold(true);
        }

        $activa->getStyle('A'.$fila.':'.chr(65 + count($cabeceras) - 1).$fila)
            ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        $fila++;

        foreach ($equipos as $equipo) {
            $valores = [
                $equipo['etiqueta'],
                $equipo['nombre'],
                $equipo['serial'],
                $equipo['categoria'],
                $equipo['modelo'],
                $equipo['ubicacion'],
                $equipo['sin_datos'] ? 'Sin datos de baja' : $equipo['estado'],
            ];

            foreach ($valores as $indice => $valor) {
                $activa->setCellValueExplicit(chr(65 + $indice).$fila, (string) ($valor ?? '—'), DataType::TYPE_STRING);
            }

            $fila++;
        }

        return $fila + 1;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, string>
     */
    private function nombresFirma(array $datos): array
    {
        if ($datos['modo'] === 'individual' && $datos['baja'] !== null) {
            return [
                'A' => (string) ($datos['baja']['registrado_por'] ?? ''),
                'D' => (string) ($datos['baja']['destinatario'] ?? ''),
            ];
        }

        return ['A' => '', 'D' => ''];
    }
}
