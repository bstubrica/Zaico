{{-- Gráfico de barras SVG (vertical u horizontal). Sin CSS ni transforms:
     solo atributos de presentación, para que dompdf lo rasterice en el PDF. --}}
@props(['datos' => [], 'horizontal' => false, 'color' => '#0ea5e9', 'titulo' => 'Gráfico de barras'])

@php
    $datos = collect($datos)->values();
    $n = $datos->count();
    $valores = $datos->map(fn ($d) => (float) ($d['valor'] ?? 0))->all();

    // Techo «bonito» del eje Y (1, 2, 2.5, 5 x 10^k).
    $maximo = $n > 0 ? max($valores) : 0;
    if ($maximo <= 0) {
        $maxEje = 1;
    } else {
        $exponente = pow(10, floor(log10($maximo)));
        $fraccion = $maximo / $exponente;
        $bonito = $fraccion <= 1 ? 1 : ($fraccion <= 2 ? 2 : ($fraccion <= 2.5 ? 2.5 : ($fraccion <= 5 ? 5 : 10)));
        $maxEje = $bonito * $exponente;
    }

    $formatoEje = function (float $valor) use ($maxEje): string {
        if ($maxEje >= 1000) {
            return $valor >= 1000 ? rtrim(rtrim(number_format($valor / 1000, 1, ',', '.'), '0'), ',').'k' : number_format($valor, 0, ',', '.');
        }

        return number_format($valor, $maxEje < 10 ? 1 : 0, ',', '.');
    };

    $ancho = 700;
@endphp

@if ($n === 0)
    <svg width="{{ $ancho }}" height="120" viewBox="0 0 {{ $ancho }} 120" font-family="sans-serif" class="w-full h-auto"
        xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $titulo }} — sin datos">
        <rect x="0" y="0" width="{{ $ancho }}" height="120" fill="#f8fafc"></rect>
        <text x="350" y="65" text-anchor="middle" font-size="13" fill="#94a3b8">Sin datos en el periodo</text>
    </svg>
@elseif ($horizontal)
    @php
        $fila = 34;
        $alto = max(80, $n * $fila + 10);
        $etiquetaAncho = 210;
        $barraOrigen = $etiquetaAncho + 12;
        $barraAncho = $ancho - $barraOrigen - 80;
        $maxBarra = $maximo > 0 ? $maximo : 1;
    @endphp
    <svg width="{{ $ancho }}" height="{{ $alto }}" viewBox="0 0 {{ $ancho }} {{ $alto }}" font-family="sans-serif" class="w-full h-auto"
        xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $titulo }}">
        @foreach ($datos as $i => $dato)
            @php
                $y = 5 + $i * $fila;
                $anchoBarra = max(1, ((float) ($dato['valor'] ?? 0)) / $maxBarra * $barraAncho);
                $etiqueta = \Illuminate\Support\Str::limit((string) ($dato['etiqueta'] ?? ''), 28, '…');
                $valorTexto = number_format((float) ($dato['valor'] ?? 0), (float) ($dato['valor'] ?? 0) == floor((float) ($dato['valor'] ?? 0)) ? 0 : 2, ',', '.');
            @endphp
            <text x="0" y="{{ $y + 20 }}" font-size="12" fill="#334155">{{ $etiqueta }}</text>
            <rect x="{{ $barraOrigen }}" y="{{ $y + 6 }}" width="{{ $anchoBarra }}" height="18" rx="3"
                fill="{{ $color }}" opacity="0.9"></rect>
            <text x="{{ $ancho }}" y="{{ $y + 20 }}" text-anchor="end" font-size="12" fill="#475569">{{ $valorTexto }}</text>
        @endforeach
    </svg>
@else
    @php
        $izq = 56;
        $der = 14;
        $arriba = 24;
        $abajo = 46;
        $plotW = $ancho - $izq - $der;
        $alto = 300;
        $plotH = $alto - $arriba - $abajo;
        $ranura = $n > 0 ? $plotW / $n : $plotW;
        $barraAncho = min($ranura * 0.6, 64);
        $pasoEtiqueta = max(1, (int) ceil($n / 12));
    @endphp
    <svg width="{{ $ancho }}" height="{{ $alto }}" viewBox="0 0 {{ $ancho }} {{ $alto }}" font-family="sans-serif" class="w-full h-auto"
        xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $titulo }}">
        @for ($p = 0; $p <= 4; $p++)
            @php
                $valorGrid = $maxEje * $p / 4;
                $yGrid = $arriba + $plotH - ($valorGrid / $maxEje * $plotH);
            @endphp
            <line x1="{{ $izq }}" y1="{{ $yGrid }}" x2="{{ $ancho - $der }}" y2="{{ $yGrid }}"
                stroke="#e2e8f0" stroke-width="1"></line>
            <text x="{{ $izq - 8 }}" y="{{ $yGrid + 4 }}" text-anchor="end" font-size="10" fill="#94a3b8">{{ $formatoEje($valorGrid) }}</text>
        @endfor

        @foreach ($datos as $i => $dato)
            @php
                $valor = (float) ($dato['valor'] ?? 0);
                $altura = $maxEje > 0 ? max(1, $valor / $maxEje * $plotH) : 1;
                $x = $izq + $i * $ranura + ($ranura - $barraAncho) / 2;
                $y = $arriba + $plotH - $altura;
            @endphp
            <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barraAncho }}" height="{{ $altura }}" rx="3"
                fill="{{ $color }}" opacity="0.9"></rect>
            @if ($n <= 14)
                <text x="{{ $x + $barraAncho / 2 }}" y="{{ $y - 5 }}" text-anchor="middle" font-size="10"
                    fill="#475569">{{ number_format($valor, 0, ',', '.') }}</text>
            @endif
            @if ($i % $pasoEtiqueta === 0)
                <text x="{{ $x + $barraAncho / 2 }}" y="{{ $arriba + $plotH + 18 }}" text-anchor="middle"
                    font-size="11" fill="#64748b">{{ $dato['etiqueta'] ?? '' }}</text>
            @endif
        @endforeach
    </svg>
@endif
