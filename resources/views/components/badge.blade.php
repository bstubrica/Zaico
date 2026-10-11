@props(['color' => 'slate', 'dot' => false])

@php
    $colores = [
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    ];

    $dotColores = [
        'slate' => 'bg-slate-400',
        'brand' => 'bg-brand-500',
        'sky' => 'bg-sky-500',
        'emerald' => 'bg-emerald-500',
        'amber' => 'bg-amber-500',
        'orange' => 'bg-orange-500',
        'rose' => 'bg-rose-500',
    ];

    $clase = $colores[$color] ?? $colores['slate'];
    $punto = $dotColores[$color] ?? $dotColores['slate'];
@endphp

<span {{ $attributes->merge(['class' => "badge ring-1 ring-inset {$clase}"]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $punto }}"></span>
    @endif
    {{ $slot }}
</span>
