@props(['activo'])

@php
    $grupo = $activo->estadoCatalogo?->Grupo;
    $detalle = $activo->estadoCatalogo?->Nombre;
    $asignadoA = $activo->AsignadoA;
    $puedeEscribir = auth()->user()?->puedeEscribir() ?? false;

    $datos = [
        'Serial' => $activo->Serial,
        'Categoría' => $activo->Categoria,
        'Fabricante' => $activo->Fabricante,
        'Modelo' => $activo->Modelo,
        'Ubicación' => $activo->Ubicacion,
    ];
@endphp

<article class="group flex flex-col rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md">
    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3">
        <div class="min-w-0">
            <a href="{{ route('activos.show', $activo) }}"
               class="block truncate font-semibold text-slate-900 group-hover:text-brand-700"
               title="{{ $activo->Nombre_de_activo }}">
                {{ $activo->Nombre_de_activo }}
            </a>
            <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500">
                <x-icon name="tag" :size="14" class="shrink-0 text-slate-400" />
                <span class="truncate font-mono">{{ $activo->Etiqueta_activo }}</span>
            </p>
        </div>
        <x-status-badge :grupo="$grupo" :title="$detalle" class="shrink-0" />
    </div>

    <div class="flex-1 space-y-3 px-4 py-3">
        <p class="flex items-center gap-2 text-sm">
            <x-icon name="user-circle" :size="16" class="shrink-0 text-slate-400" />
            @if ($asignadoA)
                <span class="truncate font-medium text-slate-800">{{ $asignadoA }}</span>
            @else
                <span class="text-slate-400">Sin asignar</span>
            @endif
        </p>

        <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
            @foreach ($datos as $etiqueta => $valor)
                <div class="min-w-0">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ $etiqueta }}</dt>
                    <dd class="mt-0.5 truncate {{ $valor ? 'text-slate-700' : 'text-slate-400' }}"
                        title="{{ $valor }}">
                        {{ $valor ?? '—' }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="flex items-center justify-between border-t border-slate-100 px-4 py-2.5">
        <a href="{{ route('activos.show', $activo) }}" class="link text-sm">Ver ficha</a>
        @if ($puedeEscribir)
            <div class="flex items-center gap-1">
                <a href="{{ route('activos.edit', $activo) }}" class="btn btn-ghost btn-sm">
                    <x-icon name="pencil" :size="16" /> Editar
                </a>
                <x-delete-form :action="route('activos.destroy', $activo)"
                               title="Eliminar equipo"
                               :message="'¿Eliminar «' . $activo->Nombre_de_activo . '» (' . $activo->Etiqueta_activo . ')? Se borrarán también su compra, mantenimientos y auditoría. Esta acción no se puede deshacer.'"
                               aria-label="Eliminar equipo">
                    <x-icon name="trash" :size="16" />
                </x-delete-form>
            </div>
        @endif
    </div>
</article>
