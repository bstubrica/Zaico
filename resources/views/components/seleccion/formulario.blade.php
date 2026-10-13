@props(['color' => 'brand', 'pintarTodos' => true])

@php
    $variantes = [
        'brand' => ['bg-brand-50 text-brand-700', 'text-brand-600'],
    ];
    [$chip, $enlace] = $variantes[$color] ?? $variantes['brand'];
@endphp

<form {{ $attributes->merge(['class' => 'sticky bottom-0 z-20 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 shadow-[0_-6px_16px_-8px_rgba(15,23,42,0.25)]']) }}
      x-show="cantidad() > 0" x-cloak x-transition.opacity.duration.150ms
      @submit="prepararEnvio($event)">
    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold {{ $chip }}">
            <x-icon name="check-circle" :size="18" />
            <span><span x-text="cantidad()"></span> seleccionado(s)</span>
        </div>

        @if ($pintarTodos)
            <template x-if="puedeSeleccionarTodos() && !todosFiltrados">
                <button type="button" class="text-sm font-medium {{ $enlace }} hover:underline"
                        @click="seleccionarTodos()">
                    Seleccionar los <span x-text="total"></span> resultados del filtro
                </button>
            </template>
            <template x-if="todosFiltrados">
                <span class="text-sm text-slate-500">
                    Se aplicará a los <strong x-text="total"></strong> resultados del filtro
                    <button type="button" class="ms-1 font-medium text-rose-600 hover:underline" @click="limpiar()">
                        Quitar
                    </button>
                </span>
            </template>
        @endif
    </div>

    <div class="flex flex-wrap items-end gap-3">
        {{ $slot }}
    </div>

    <div data-seleccion-campos class="hidden"></div>
</form>
