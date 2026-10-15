@props([])

<input type="checkbox" aria-label="Seleccionar todos los resultados de esta página"
       {{ $attributes->merge(['class' => 'h-4 w-4 cursor-pointer rounded border-slate-300 text-brand-600 focus:ring-brand-500']) }}
       :checked="todosVisibles()"
       @change="alternarVisibles($event.target.checked)">
