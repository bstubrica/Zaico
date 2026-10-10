@props(['grupo' => null, 'label' => null])

@php
    $mapa = [
        'Asignado' => ['bg-sky-50', 'text-sky-700', 'ring-sky-600/20', 'bg-sky-500'],
        'Disponible' => ['bg-emerald-50', 'text-emerald-700', 'ring-emerald-600/20', 'bg-emerald-500'],
        'Reparacion' => ['bg-amber-50', 'text-amber-700', 'ring-amber-600/20', 'bg-amber-500'],
        'Defectuoso' => ['bg-orange-50', 'text-orange-700', 'ring-orange-600/20', 'bg-orange-500'],
        'Desincorporado' => ['bg-rose-50', 'text-rose-700', 'ring-rose-600/20', 'bg-rose-500'],
    ];

    [$bg, $texto, $ring, $punto] = $mapa[$grupo] ?? ['bg-slate-100', 'text-slate-600', 'ring-slate-500/20', 'bg-slate-400'];
@endphp

<span {{ $attributes->merge(['class' => "badge ring-1 ring-inset {$bg} {$texto} {$ring}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $punto }}"></span>
    {{ $label ?? $grupo ?? 'Sin estado' }}
</span>
