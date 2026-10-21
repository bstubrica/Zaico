{{-- Gráfica de líneas SVG multi-serie con leyenda. Solo atributos de
     presentación para que dompdf la dibuje en el PDF. --}}
@props(['etiquetas' => [], 'series' => [], 'titulo' => 'Gráfica de líneas'])

@php
    $etiquetas = collect($etiquetas)->values();
    $series = collect($series)->values();
    $n = $etiquetas->count();
    $ancho = 700;
    $alto = 310;
    $izq = 56;
    $der = 16;
    $arriba = 46; // deja la franja superior para la leyenda
    $abajo = 46;
    $plotW = $ancho - $izq - $der;
    $plotH = $alto - $arriba - $abajo;

    $maximo = 0;
    foreach ($series as $serie) {
        foreach ($serie['valores'] ?? [] as $valor) {
            $maximo = max($maximo, (float) $valor);
        }
    }

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

    $sinDatos = $n === 0 || $series->isEmpty()
        || $series->every(fn ($serie) => collect($serie['valores'] ?? [])->sum() == 0);
    $pasoEtiqueta = max(1, (int) ceil($n / 12));
@endphp

@if ($sinDatos)
    <svg width="{{ $ancho }}" height="160" viewBox="0 0 {{ $ancho }} 160" font-family="sans-serif" class="w-full h-auto"
        xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $titulo }} — sin datos">
        <rect x="0" y="0" width="{{ $ancho }}" height="160" fill="#f8fafc"></rect>
        <text x="350" y="85" text-anchor="middle" font-size="13" fill="#94a3b8">Sin movimientos en el periodo</text>
    </svg>
@else
    <svg width="{{ $ancho }}" height="{{ $alto }}" viewBox="0 0 {{ $ancho }} {{ $alto }}" font-family="sans-serif" class="w-full h-auto"
        xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $titulo }}">
        {{-- Leyenda --}}
        @php
            $leyendaX = $izq;
        @endphp
        @foreach ($series as $serie)
            @php
                $etiquetaSerie = (string) ($serie['nombre'] ?? 'Serie');
                $colorSerie = $serie['color'] ?? '#0ea5e9';
                $anchoTexto = mb_strlen($etiquetaSerie) * 7 + 42;
            @endphp
            <rect x="{{ $leyendaX }}" y="14" width="14" height="14" rx="3" fill="{{ $colorSerie }}"></rect>
            <text x="{{ $leyendaX + 20 }}" y="26" font-size="12" fill="#334155">{{ $etiquetaSerie }}</text>
            @php
                $leyendaX += $anchoTexto;
            @endphp
        @endforeach

        {{-- Rejilla y eje Y --}}
        @for ($p = 0; $p <= 4; $p++)
            @php
                $valorGrid = $maxEje * $p / 4;
                $yGrid = $arriba + $plotH - ($valorGrid / $maxEje * $plotH);
            @endphp
            <line x1="{{ $izq }}" y1="{{ $yGrid }}" x2="{{ $ancho - $der }}" y2="{{ $yGrid }}"
                stroke="#e2e8f0" stroke-width="1"></line>
            <text x="{{ $izq - 8 }}" y="{{ $yGrid + 4 }}" text-anchor="end" font-size="10" fill="#94a3b8">{{ $formatoEje($valorGrid) }}</text>
        @endfor

        @php
            $pasoX = $n > 1 ? $plotW / ($n - 1) : 0;
        @endphp

        {{-- Series --}}
        @foreach ($series as $serie)
            @php
                $colorSerie = $serie['color'] ?? '#0ea5e9';
                $puntos = [];
                foreach (($serie['valores'] ?? []) as $i => $valor) {
                    if ($i >= $n) {
                        break;
                    }
                    $x = $n > 1 ? $izq + $i * $pasoX : $izq + $plotW / 2;
                    $y = $arriba + $plotH - (max(0, (float) $valor) / $maxEje * $plotH);
                    $puntos[] = [round($x, 1), round($y, 1), (float) $valor];
                }
            @endphp
            @if (count($puntos) > 1)
                <polyline fill="none" stroke="{{ $colorSerie }}" stroke-width="2.5" stroke-linejoin="round"
                    stroke-linecap="round"
                    points="{{ collect($puntos)->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}"></polyline>
            @endif
            @foreach ($puntos as $punto)
                <circle cx="{{ $punto[0] }}" cy="{{ $punto[1] }}" r="3" fill="#ffffff" stroke="{{ $colorSerie }}"
                    stroke-width="2"></circle>
            @endforeach
        @endforeach

        {{-- Eje X --}}
        @foreach ($etiquetas as $i => $etiqueta)
            @if ($i % $pasoEtiqueta === 0)
                @php
                    $x = $n > 1 ? $izq + $i * $pasoX : $izq + $plotW / 2;
                @endphp
                <text x="{{ $x }}" y="{{ $arriba + $plotH + 20 }}" text-anchor="middle" font-size="11"
                    fill="#64748b">{{ $etiqueta }}</text>
            @endif
        @endforeach
    </svg>
@endif
