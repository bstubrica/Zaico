@props(['valor', 'etiqueta' => null])

<input type="checkbox" value="{{ $valor }}"
       {{ $attributes->merge(['class' => 'h-4 w-4 cursor-pointer rounded border-slate-300 text-brand-600 focus:ring-brand-500']) }}
       :checked="estaSeleccionado({{ $valor }})"
       @change="alternar({{ $valor }}, $event.target.checked)"
       aria-label="Seleccionar {{ $etiqueta ?? $valor }}">
