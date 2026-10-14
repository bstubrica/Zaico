@props([
    'name',
    'options' => [],
    'selected' => null,
    'placeholder' => 'Escriba para buscar…',
    'required' => false,
])

@php
    $opciones = collect($options)->map(fn ($opcion) => [
        'value' => (string) $opcion['value'],
        'label' => (string) $opcion['label'],
        'meta' => isset($opcion['meta']) ? (string) $opcion['meta'] : '',
    ])->values()->all();

    $seleccionado = (string) (old($name, $selected) ?? '');
    $tieneError = $errors->has($name);
@endphp

<div {{ $attributes->merge(['class' => 'relative']) }}
     x-data="selectorBuscable(@js([
         'opciones' => $opciones,
         'seleccionado' => $seleccionado,
         'placeholder' => $placeholder,
         'requerido' => $required,
     ]))"
     @click.outside="cerrar()"
     @keydown.escape.prevent="cerrar()">

    <input type="hidden" name="{{ $name }}" :value="seleccionadoId">

    <input x-ref="buscador"
           type="text"
           id="{{ $name }}"
           x-model="texto"
           @focus="abrir()"
           @input="alEscribir()"
           @keydown.arrow-down.prevent="moverAbajo()"
           @keydown.arrow-up.prevent="moverArriba()"
           @keydown.enter="confirmar($event)"
           @keydown.tab="cerrar()"
           :placeholder="placeholder"
           :required="requerido && ! seleccionadoId"
           autocomplete="off" spellcheck="false"
           role="combobox" aria-autocomplete="list"
           :aria-expanded="abierto" aria-controls="{{ $name }}-opciones"
           class="form-control pe-16 {{ $tieneError ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500' : '' }}">

    <button type="button" x-show="seleccionadoId" x-cloak @click="limpiar()"
            class="absolute inset-y-0 end-8 my-auto flex h-5 w-5 items-center justify-center rounded text-slate-400 hover:text-slate-600"
            aria-label="Quitar selección">
        <x-icon name="close" :size="16" />
    </button>

    <span class="pointer-events-none absolute inset-y-0 end-2.5 my-auto flex h-5 w-5 items-center text-slate-400">
        <x-icon name="chevron-down" :size="18" />
    </span>

    <div x-show="abierto" x-cloak x-ref="lista" id="{{ $name }}-opciones"
         class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white py-1 text-sm shadow-lg">
        <template x-if="filtradas.length === 0">
            <p class="px-3 py-2 text-slate-500">Sin coincidencias</p>
        </template>

        <ul role="listbox">
            <template x-for="(opcion, indice) in filtradas" :key="opcion.value">
                <li role="option"
                    :aria-selected="opcion.value === seleccionadoId"
                    :data-activo="indice === activo"
                    @click="elegir(opcion)"
                    @mouseenter="activo = indice"
                    :class="indice === activo ? 'bg-brand-50' : ''"
                    class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2">
                    <span class="flex min-w-0 items-baseline gap-2">
                        <span class="truncate text-slate-800" x-text="opcion.label"></span>
                        <span x-show="opcion.meta" class="truncate text-xs text-slate-400" x-text="opcion.meta"></span>
                    </span>
                    <x-icon x-show="opcion.value === seleccionadoId" name="check-circle" :size="16" class="shrink-0 text-brand-600" />
                </li>
            </template>
        </ul>
    </div>
</div>
