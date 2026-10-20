<x-app-layout ancho="max-w-screen-2xl">
    <x-slot name="header">
        <x-page-header title="Importación y Exportación CSV" subtitle="Gestión e intercambio de inventario en CSV (Snipec IT)" icon="upload">
            <x-slot name="actions">
                <form method="GET" action="{{ route('importaciones.exportar') }}" class="flex items-end gap-2">
                    <div>
                        <label for="entidad" class="mb-1 block text-xs font-medium text-slate-500">Entidad a exportar</label>
                        <select id="entidad" name="entidad" class="form-control">
                            @foreach ($entidades as $clave => $etiqueta)
                                <option value="{{ $clave }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <x-icon name="download" :size="18" /> Exportar
                    </button>
                </form>
                <x-badge color="slate">{{ $historial->total() }} importaciones</x-badge>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Formulario de carga --}}
        <div class="card lg:col-span-1">
            <div class="card-header">
                <h3 class="card-title">Subir archivo</h3>
            </div>
            <div class="card-body">
                <p class="mb-4 text-sm text-slate-500">
                    CSV en UTF-8, máx. 20 MB. Primero previsualice: <strong>no se modifica nada</strong> hasta confirmar.
                </p>

                <form method="POST" action="{{ route('importaciones.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <input type="file" name="archivo" accept=".csv" required
                           class="block w-full text-sm text-slate-600 file:me-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">

                    <div class="flex flex-col gap-2">
                        <button type="submit" name="modo" value="dryrun" class="btn btn-neutral">
                            <x-icon name="eye" :size="18" /> Previsualizar (sin guardar)
                        </button>
                        <button type="submit" name="modo" value="commit"
                                onclick="return confirm('¿Importar directamente en la base de datos?')"
                                class="btn btn-primary">
                            <x-icon name="upload" :size="18" /> Importar directamente
                        </button>
                    </div>
                </form>

                @if ($pendiente)
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-medium text-amber-800">
                            Previsualización pendiente: <span class="font-mono">{{ $pendiente['nombre'] }}</span>
                        </p>
                        <form method="POST" action="{{ route('importaciones.confirmar') }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn btn-warning w-full">
                                <x-icon name="check-circle" :size="18" /> Confirmar importación
                            </button>
                        </form>
                    </div>
                @endif

                <x-input-error :messages="$errors->all()" class="mt-3" />
            </div>
        </div>

        {{-- Historial --}}
        <div class="card lg:col-span-2"
             x-data="seleccionLote({ clave: 'sel:importaciones', total: {{ $historial->total() }}, pagina: @js($historial->pluck('id')->all()), mensaje: 'Se eliminarán {n} registros de importación junto con su detalle.', mensajeGrande: 'Son {n} registros de importación. Se eliminarán de forma permanente. ¿Continuar?' })">
            <div class="card-header">
                <h3 class="card-title">Historial de importaciones</h3>
            </div>

            @if ($historial->isEmpty())
                <x-empty-state title="Sin importaciones" description="Todavía no se ha importado ningún archivo." icon="upload" />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="table-col-chk">
                                    <x-sel-todos />
                                </th>
                                <th class="table-col-fija-2">Fecha</th>
                                <th>Archivo</th>
                                <th>Modo</th>
                                <th class="text-center">Filas</th>
                                <th class="hidden text-center xl:table-cell">Nuevas</th>
                                <th class="hidden text-center xl:table-cell">Actualizadas</th>
                                <th class="text-center">Errores</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($historial as $log)
                                <tr>
                                    <td class="table-col-chk">
                                        <x-sel-fila :valor="$log->id" :etiqueta="$log->Nombre_archivo" />
                                    </td>
                                    <td class="table-col-fija-2 whitespace-nowrap text-slate-500">{{ $log->Fecha?->format('d/m/Y H:i') }}</td>
                                    <td class="font-medium text-slate-900">{{ \Illuminate\Support\Str::limit($log->Nombre_archivo, 32) }}</td>
                                    <td>
                                        <x-badge :color="$log->Modo === 'DryRun' ? 'slate' : 'brand'">{{ $log->Modo }}</x-badge>
                                    </td>
                                    <td class="text-center">{{ $log->Total_filas }}</td>
                                    <td class="hidden text-center text-emerald-700 xl:table-cell">{{ $log->Insertadas }}</td>
                                    <td class="hidden text-center text-sky-700 xl:table-cell">{{ $log->Actualizadas }}</td>
                                    <td class="text-center {{ $log->Errores > 0 ? 'font-semibold text-rose-700' : 'text-slate-400' }}">{{ $log->Errores }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-0.5">
                                            <a href="{{ route('importaciones.show', $log) }}" class="icon-btn"
                                               title="Ver detalle" aria-label="Ver detalle de la importación de {{ $log->Nombre_archivo }}">
                                                <x-icon name="eye" :size="18" />
                                            </a>
                                            <form method="POST" action="{{ route('importaciones.destroy', $log) }}"
                                                  onsubmit="return confirm('¿Eliminar este registro de importación y su detalle?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="icon-btn icon-btn-danger"
                                                        title="Eliminar" aria-label="Eliminar la importación de {{ $log->Nombre_archivo }}">
                                                    <x-icon name="trash" :size="18" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-seleccion.formulario id="bulk-importaciones" method="POST"
                                        action="{{ route('importaciones.eliminar-masivo') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger" :disabled="cantidad() === 0">
                        <x-icon name="trash" :size="18" /> Eliminar seleccionados
                    </button>
                </x-seleccion.formulario>

                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $historial->links() }}
                </div>
            @endif
        </div>
    </div>

    <x-resultado-lote />
</x-app-layout>
