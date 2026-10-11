@props(['label', 'value', 'icon' => 'cube', 'color' => 'brand', 'href' => null])

@php
    $chips = [
        'brand' => 'bg-brand-50 text-brand-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
    $chip = $chips[$color] ?? $chips['brand'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'card group flex items-center gap-4 p-5 transition hover:shadow-md' . ($href ? ' hover:-translate-y-0.5' : '')]) }}>
    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $chip }}">
        <x-icon :name="$icon" :size="24" />
    </span>
    <div class="min-w-0">
        <p class="truncate text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
        <p class="mt-0.5 text-2xl font-bold text-slate-900">{{ $value }}</p>
    </div>
</{{ $tag }}>
