{{-- Gráfico de donut SVG con leyenda. Solo atributos de presentación
     (dompdf lo dibuja en el PDF sin CSS ni transforms). --}}
@props(['datos' => [], 'titulo' => 'Gráfico de donut', 'total_etiqueta' => 'Total'])

@php
    $datos = collect($datos)->values()->filter(fn ($d) => (float) ($d['valor'] ?? 0) > 0)->values();
    $n = $datos->count();
    $total = (float) $datos->sum(fn ($d) => (float) ($d['valor'] ?? 0));
    $paleta = ['#0ea5e9', '#10b981', '#f59e0b', '#f43f5e', '#8b5cf6', '#64748b', '#f97316', '#14b8a6'];
    $ancho = 700;
    $alto = max(250, $n * 30 + 40);
    $cx = 150;
    $cy = $alto / 2;
    $radio = min(95, $alto / 2 - 14);
    $interno = $radio * 0.58;

    $punto = function (float $cx, float $cy, float $r, float $angulo): array {
        $rad = deg2rad($angulo);

        return [$cx + $r * cos($rad), $cy + $r * sin($rad)];
    };
@endphp

@if ($n === 0 || $total <= 0)
    <svg width="{{ $ancho }}" height="160" viewBox="0 0 {{ $ancho }} 160" font-family="sans-serif" class="w-full h-auto"
        xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $titulo }} — sin datos">
        <rect x="0" y="0" width="{{ $ancho }}" height="160" fill="#f8fafc"></rect>
        <text x="350" y="85" text-anchor="middle" font-size="13" fill="#94a3b8">Sin datos para mostrar</text>
    </svg>
@else
    <svg width="{{ $ancho }}" height="{{ $alto }}" viewBox="0 0 {{ $ancho }} {{ $alto }}" font-family="sans-serif" class="w-full h-auto"
        xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $titulo }}">
        @php
            $acumulado = -90.0;
        @endphp
        @foreach ($datos as $i => $dato)
            @php
                $fraccion = (float) ($dato['valor'] ?? 0) / $total * 360;
                $inicio = $acumulado;
                $fin = $acumulado + $fraccion;
                $acumulado = $fin;

                // Un único dato ocuparía el círculo completo y el arco degenera.
                if ($fin - $inicio >= 359.99) {
                    $fin = $inicio + 359.99;
                }

                [$x1, $y1] = $punto($cx, $cy, $radio, $inicio);
                [$x2, $y2] = $punto($cx, $cy, $radio, $fin);
                [$x3, $y3] = $punto($cx, $cy, $interno, $fin);
                [$x4, $y4] = $punto($cx, $cy, $interno, $inicio);
                $grande = ($fin - $inicio) > 180 ? 1 : 0;
                $color = $dato['color'] ?? $paleta[$i % count($paleta)];
                $ruta = sprintf(
                    'M %.2F %.2F A %.2F %.2F 0 %d 1 %.2F %.2F L %.2F %.2F A %.2F %.2F 0 %d 0 %.2F %.2F Z',
                    $x1, $y1, $radio, $radio, $grande, $x2, $y2,
                    $x3, $y3, $interno, $interno, $grande, $x4, $y4
                );
            @endphp
            <path d="{{ $ruta }}" fill="{{ $color }}"></path>
        @endforeach

        <text x="{{ $cx }}" y="{{ $cy - 2 }}" text-anchor="middle" font-size="24" font-weight="bold" fill="#0f172a">{{ number_format($total, 0, ',', '.') }}</text>
        <text x="{{ $cx }}" y="{{ $cy + 18 }}" text-anchor="middle" font-size="11" fill="#64748b">{{ $total_etiqueta }}</text>

        @php
            $leyendaY = ($alto - $n * 30) / 2 + 8;
            $leyendaX = max($cx + $radio + 40, 320);
        @endphp
        @foreach ($datos as $i => $dato)
            @php
                $y = $leyendaY + $i * 30;
                $color = $dato['color'] ?? $paleta[$i % count($paleta)];
                $valor = (float) ($dato['valor'] ?? 0);
                $porcentaje = number_format($valor / $total * 100, 1, ',', '.');
                $etiqueta = \Illuminate\Support\Str::limit((string) ($dato['label'] ?? $dato['etiqueta'] ?? ''), 30, '…');
            @endphp
            <rect x="{{ $leyendaX }}" y="{{ $y }}" width="14" height="14" rx="3" fill="{{ $color }}"></rect>
            <text x="{{ $leyendaX + 24 }}" y="{{ $y + 12 }}" font-size="13" fill="#334155">{{ $etiqueta }}</text>
            <text x="{{ $ancho - 10 }}" y="{{ $y + 12 }}" text-anchor="end" font-size="13" fill="#0f172a">{{ number_format($valor, 0, ',', '.') }} ({{ $porcentaje }}%)</text>
        @endforeach
    </svg>
@endif
