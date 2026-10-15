<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <title>{{ $titulo }} — {{ $folio }}</title>
        <style>
            body {
                font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
                color: #1f2937;
                background: #ffffff;
                font-size: 10pt;
                line-height: 1.4;
                margin: 0;
                padding: 0;
            }

            .hoja {
                padding: 16mm 14mm;
            }

            .encabezado {
                border-bottom: 1px solid #e5e7eb;
                padding-bottom: 10px;
                margin-bottom: 16px;
            }

            .titulo-principal {
                font-size: 16pt;
                font-weight: bold;
                margin: 0 0 4px 0;
            }

            .subtitulo {
                font-size: 9pt;
                color: #4b5563;
                margin: 0;
            }

            .folio {
                float: right;
                text-align: right;
                font-size: 9pt;
                color: #374151;
            }

            .bloque {
                border: 1px solid #e5e7eb;
                border-radius: 4px;
                margin-bottom: 12px;
            }

            .bloque table {
                width: 100%;
                border-collapse: collapse;
            }

            .bloque td {
                padding: 4px 8px;
                font-size: 9pt;
                border-bottom: 1px solid #f1f5f9;
                vertical-align: top;
            }

            .bloque td.etiqueta {
                width: 30%;
                color: #4b5563;
                font-weight: bold;
            }

            .motivo {
                border: 1px solid #e5e7eb;
                border-radius: 4px;
                padding: 8px;
                font-size: 9pt;
                margin-bottom: 12px;
            }

            .motivo-titulo {
                font-weight: bold;
                margin-bottom: 4px;
            }

            table.equipos {
                width: 100%;
                border-collapse: collapse;
                font-size: 8pt;
                margin-top: 8px;
                margin-bottom: 10px;
            }

            table.equipos th {
                background: #f1f5f9;
                text-align: left;
                padding: 4px 6px;
                font-weight: bold;
                border-bottom: 1px solid #e5e7eb;
            }

            table.equipos td {
                padding: 4px 6px;
                border-bottom: 1px solid #f1f5f9;
            }

            .total {
                font-size: 9pt;
                font-weight: bold;
                margin-bottom: 20px;
            }

            .firmas {
                width: 100%;
                margin-top: 40px;
            }

            .firma {
                width: 48%;
                float: left;
                text-align: center;
            }

            .firma.derecha {
                float: right;
            }

            .linea-firma {
                border-bottom: 1px solid #4b5563;
                height: 40px;
                margin-bottom: 4px;
            }

            .texto-firma {
                font-size: 9pt;
                font-weight: bold;
                margin: 2px 0 0 0;
            }

            .texto-firma-secundario {
                font-size: 8pt;
                color: #4b5563;
                margin: 2px 0 0 0;
            }

            .clearfix {
                clear: both;
            }
        </style>
    </head>
    <body>
        @php
            $org = $organizacion ?? [];
        @endphp
        <div class="hoja">
            <div class="folio">
                <p>Folio: <strong>{{ $folio }}</strong></p>
                <p>Emitida el {{ $emitida_en->format('d/m/Y') }} a las {{ $emitida_en->format('H:i') }}</p>
                @if ($emitida_por)
                    <p>Por: {{ $emitida_por }}</p>
                @endif
            </div>

            <div class="encabezado">
                <h1 class="titulo-principal">{{ $org['nombre'] ?? config('app.name') }}</h1>
                @foreach (array_filter([$org['rif'] ?? null, $org['direccion'] ?? null, $org['ciudad'] ?? null]) as $linea)
                    <p class="subtitulo">{{ $linea }}</p>
                @endforeach
                <h2 class="titulo-principal" style="margin-top: 6px; font-size: 12pt;">{{ $titulo }}</h2>
            </div>

            @if ($modo === 'individual' && $baja !== null)
                <div class="bloque">
                    <table>
                        <tr>
                            <td class="etiqueta">Tipo de baja</td>
                            <td>{{ $baja['tipo_etiqueta'] }}</td>
                            <td class="etiqueta">Fecha</td>
                            <td>{{ $baja['fecha']?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="etiqueta">Destinatario</td>
                            <td>{{ $baja['destinatario'] ?? '—' }}</td>
                            <td class="etiqueta">Monto</td>
                            <td>{{ $baja['monto_texto'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="etiqueta">Detalle del destino</td>
                            <td>{{ $baja['detalle_destino'] ?? '—' }}</td>
                            <td class="etiqueta">Referencia</td>
                            <td>{{ $baja['referencia'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="etiqueta">Registrado por</td>
                            <td>{{ $baja['registrado_por'] ?? '—' }}</td>
                            <td class="etiqueta">Estado</td>
                            <td>
                                {{ $baja['estado'] }}
                                @if ($baja['anulada'])
                                    · ANULADA
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>

                @if ($baja['motivo'])
                    <div class="motivo">
                        <div class="motivo-titulo">Motivo</div>
                        {{ $baja['motivo'] }}
                    </div>
                @endif
            @else
                <p class="subtitulo" style="margin-bottom: 12px;"><strong>Detalle del acta:</strong> {{ $filtros_resumen ?? 'Todos los equipos desincorporados.' }}</p>
            @endif

            <h3 style="font-size: 11pt; margin: 0 0 4px 0;">Equipos desincorporados</h3>
            <table class="equipos">
                <thead>
                    <tr>
                        <th>Etiqueta</th>
                        <th>Equipo</th>
                        <th>Serial</th>
                        <th>Categoría</th>
                        <th>Modelo</th>
                        <th>Ubicación</th>
                        <th>Fecha</th>
                        <th>Destinatario</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipos as $equipo)
                        <tr>
                            <td>{{ $equipo['etiqueta'] }}</td>
                            <td>{{ $equipo['nombre'] }}</td>
                            <td>{{ $equipo['serial'] ?? '—' }}</td>
                            <td>{{ $equipo['categoria'] ?? '—' }}</td>
                            <td>{{ $equipo['modelo'] ?? '—' }}</td>
                            <td>{{ $equipo['ubicacion'] ?? '—' }}</td>
                            <td>{{ $equipo['fecha']?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $equipo['destinatario'] ?? '—' }}</td>
                            <td>
                                @if ($equipo['sin_datos'])
                                    Sin datos de baja
                                @else
                                    {{ $equipo['estado'] }}
                                    @if ($equipo['anulada'])
                                        · ANULADA
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center;">No hay equipos en esta carta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <p class="total">Total de equipos: {{ count($equipos) }}</p>

            @php
                $firmasConfig = $firmas ?? [];
            @endphp
            <div class="firmas">
                <div class="firma">
                    <div class="linea-firma"></div>
                    <p class="texto-firma">{{ $firmasConfig['entrega'] ?? 'Entrega' }}</p>
                    @if ($modo === 'individual' && $baja !== null)
                        <p class="texto-firma-secundario">{{ $baja['registrado_por'] ?? '' }}</p>
                    @elseif ($emitida_por)
                        <p class="texto-firma-secundario">{{ $emitida_por }}</p>
                    @endif
                </div>
                <div class="firma derecha">
                    <div class="linea-firma"></div>
                    <p class="texto-firma">{{ $firmasConfig['recibe'] ?? 'Recibe' }}</p>
                    @if ($modo === 'individual' && $baja !== null)
                        <p class="texto-firma-secundario">{{ $baja['destinatario'] ?? '' }}</p>
                    @endif
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
    </body>
</html>
