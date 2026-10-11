@props(['tipo' => 'info'])

@php
    $variantes = [
        'success' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'check-circle', 'hover:bg-emerald-100'],
        'error' => ['border-rose-200 bg-rose-50 text-rose-800', 'warning', 'hover:bg-rose-100'],
        'warning' => ['border-amber-200 bg-amber-50 text-amber-800', 'warning', 'hover:bg-amber-100'],
        'info' => ['border-sky-200 bg-sky-50 text-sky-800', 'check-circle', 'hover:bg-sky-100'],
    ];
    [$clase, $icono, $hover] = $variantes[$tipo] ?? $variantes['info'];
@endphp

<div x-data="{ open: true }" x-show="open" x-cloak
     {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {$clase}"]) }}
     role="{{ $tipo === 'error' ? 'alert' : 'status' }}">
    <x-icon :name="$icono" :size="20" class="mt-0.5 shrink-0" />
    <div class="flex-1">{{ $slot }}</div>
    <button type="button" @click="open = false" class="shrink-0 rounded p-1 {{ $hover }}" aria-label="Cerrar">
        <x-icon name="close" :size="16" />
    </button>
</div>
