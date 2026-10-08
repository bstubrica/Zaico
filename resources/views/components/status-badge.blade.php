@props(['grupo' => null])

@php
    $colores = [
        'Asignado' => 'bg-blue-100 text-blue-800',
        'Disponible' => 'bg-green-100 text-green-800',
        'Reparacion' => 'bg-amber-100 text-amber-800',
        'Defectuoso' => 'bg-amber-100 text-amber-800',
        'Desincorporado' => 'bg-red-100 text-red-800',
    ];
    $clase = $colores[$grupo] ?? 'bg-gray-100 text-gray-600';
    $etiqueta = $grupo ?? 'Sin estado';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium $clase"]) }}>
    {{ $etiqueta }}
</span>
