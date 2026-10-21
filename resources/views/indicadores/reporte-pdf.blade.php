<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>{{ $titulo }} — Reportes y KPIs</title>
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #1f2937;
            background: #ffffff;
            font-size: 9.5pt;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        .hoja {
            padding: 14mm;
        }

        .encabezado {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .titulo {
            font-size: 16pt;
            font-weight: bold;
            margin: 0 0 2px 0;
        }

        .resumen {
            font-size: 9pt;
            color: #4b5563;
            margin: 0 0 2px 0;
        }

        .meta {
            font-size: 8pt;
            color: #6b7280;
            margin: 0;
        }

        .grafico {
            margin: 0 0 14px 0;
            text-align: center;
        }

        .grafico img {
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        thead th {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            font-size: 8.5pt;
            text-align: left;
        }

        tbody td {
            border: 1px solid #e2e8f0;
            padding: 4px 7px;
            font-size: 8.5pt;
        }

        tfoot td {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            font-size: 8.5pt;
            font-weight: bold;
        }

        .num {
            text-align: right;
        }

        .nota {
            font-size: 8pt;
            color: #64748b;
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="hoja">
        <div class="encabezado">
            <h1 class="titulo">{{ $titulo }}</h1>
            <p class="resumen">{{ $resumen }}</p>
            <p class="meta">
                Emitido el {{ $emitida_en->format('d/m/Y H:i') }}@if ($emitida_por) · por {{ $emitida_por }}@endif
            </p>
        </div>

        @php
            // dompdf no dibuja <svg> inline: se serializa el gráfico como SVG
            // y se embebe como data-URI en un <img> (resuelto por php-svg-lib).
            $svg = match ($grafico['tipo']) {
                'linea' => \Illuminate\Support\Facades\Blade::render(
                    '<x-charts.linea :etiquetas="$etiquetas" :series="$series" :titulo="$titulo" />',
                    [
                        'etiquetas' => $grafico['etiquetas'] ?? [],
                        'series' => $grafico['series'] ?? [],
                        'titulo' => $titulo,
                    ]
                ),
                'donut' => \Illuminate\Support\Facades\Blade::render(
                    '<x-charts.donut :datos="$datos" :titulo="$titulo" />',
                    ['datos' => $grafico['items'] ?? [], 'titulo' => $titulo]
                ),
                default => \Illuminate\Support\Facades\Blade::render(
                    '<x-charts.barras :datos="$datos" :titulo="$titulo" />',
                    ['datos' => $grafico['items'] ?? [], 'titulo' => $titulo]
                ),
            };

            $formatoCelda = function ($valor, string $tipo) {
                return match ($tipo) {
                    'numero' => number_format((float) $valor, 0, ',', '.'),
                    'dinero' => number_format((float) $valor, 2),
                    'porcentaje' => number_format((float) $valor, 1).' %',
                    'fecha' => filled($valor) ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : '—',
                    default => filled($valor) ? $valor : '—',
                };
            };
            $alineacion = fn (string $tipo) => in_array($tipo, ['numero', 'dinero', 'porcentaje'], true) ? 'num' : '';
        @endphp

        <div class="grafico">
            <img src="data:image/svg+xml;base64,{{ base64_encode($svg) }}" alt="{{ $titulo }}">
        </div>

        <table>
            <thead>
                <tr>
                    @foreach ($columnas as $columna)
                        <th class="{{ $alineacion($columna['tipo']) }}">{{ $columna['etiqueta'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($filas as $fila)
                    <tr>
                        @foreach ($columnas as $columna)
                            <td class="{{ $alineacion($columna['tipo']) }}">
                                {{ $formatoCelda($fila[$columna['clave']] ?? null, $columna['tipo']) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columnas) }}" style="text-align: center; color: #94a3b8; padding: 14px;">
                            Sin datos para este reporte con los filtros aplicados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if (! empty($totales))
                <tfoot>
                    <tr>
                        @foreach ($columnas as $columna)
                            <td class="{{ $alineacion($columna['tipo']) }}">
                                {{ $formatoCelda($totales[$columna['clave']] ?? null, $columna['tipo']) }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>

        @if ($nota)
            <p class="nota">{{ $nota }}</p>
        @endif
    </div>
</body>

</html>
