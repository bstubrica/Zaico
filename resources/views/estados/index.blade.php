<x-app-layout ancho="max-w-screen-2xl">
    <x-slot name="header">
        <x-page-header title="Estados de catálogo" subtitle="Catálogo de estados de los activos" icon="tag">
            <x-slot name="actions">
                @if (auth()->user()->puedeEscribir())
                    <a href="{{ route('estados.create') }}" class="btn btn-primary">
                        <x-icon name="plus" :size="18" /> Registrar estado
                    </a>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-5xl">
        <div class="card">
            @if ($estados->isEmpty())
                <x-empty-state title="El catálogo de estados está vacío"
                               description="Registre estados para describir la situación de los activos." icon="tag">
                    <x-slot name="action">
                        @if (auth()->user()->puedeEscribir())
                            <a href="{{ route('estados.create') }}" class="btn btn-primary">
                                <x-icon name="plus" :size="18" /> Registrar estado
                            </a>
                        @endif
                    </x-slot>
                </x-empty-state>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="table-col-fija">Nombre</th>
                                <th>Grupo</th>
                                <th class="text-center">Deployed</th>
                                <th class="text-center">Deployable</th>
                                <th class="text-center">Activos</th>
                                @if (auth()->user()->puedeEscribir())
                                    <th class="text-right">Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($estados as $estado)
                                <tr>
                                    <td class="table-col-fija font-medium text-slate-900">{{ $estado->Nombre }}</td>
                                    <td><x-status-badge :grupo="$estado->Grupo" /></td>
                                    <td class="text-center">
                                        @if ($estado->Deployed)
                                            <x-icon name="check-circle" :size="18" class="mx-auto text-emerald-500" />
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($estado->Deployable)
                                            <x-icon name="check-circle" :size="18" class="mx-auto text-emerald-500" />
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-slate-500">{{ $estado->activos_count ?? $estado->activos()->count() }}</td>
                                    @if (auth()->user()->puedeEscribir())
                                        <td class="text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-0.5">
                                                <a href="{{ route('estados.edit', $estado) }}" class="icon-btn"
                                                   title="Editar" aria-label="Editar {{ $estado->Nombre }}">
                                                    <x-icon name="pencil" :size="18" />
                                                </a>
                                                <form method="POST" action="{{ route('estados.destroy', $estado) }}"
                                                      onsubmit="return confirm({{ \Illuminate\Support\Js::from('¿Eliminar el estado '.$estado->Nombre.'?') }})">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="icon-btn icon-btn-danger"
                                                            title="Eliminar" aria-label="Eliminar el estado {{ $estado->Nombre }}">
                                                        <x-icon name="trash" :size="18" />
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
