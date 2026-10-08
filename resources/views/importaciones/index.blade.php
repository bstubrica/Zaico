<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Importación CSV (Snipec IT)</h2>
            <span class="text-sm text-gray-500">{{ $historial->total() }} importaciones</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Formulario de carga --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-1">Subir archivo</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Reporte <code class="text-xs bg-gray-100 px-1 rounded">custom-assets-report</code> de Snipec IT (CSV, UTF-8, máx. 20 MB).
                    Primero previsualice: <strong>no se modifica nada</strong> hasta confirmar.
                </p>

                <form method="POST" action="{{ route('importaciones.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <input type="file" name="archivo" accept=".csv" required
                           class="block w-full text-sm text-gray-600 file:me-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" name="modo" value="dryrun"
                                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Previsualizar (sin guardar)
                        </button>
                        <button type="submit" name="modo" value="commit"
                                onclick="return confirm('¿Importar directamente en la base de datos?')"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            Importar directamente
                        </button>
                    </div>
                </form>

                @if ($pendiente)
                    <div class="mt-5 rounded-md border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-medium text-amber-800">
                            Hay una previsualización pendiente de confirmación: <span class="font-mono">{{ $pendiente['nombre'] }}</span>
                        </p>
                        <form method="POST" action="{{ route('importaciones.confirmar') }}" class="mt-3">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                Confirmar importación
                            </button>
                        </form>
                    </div>
                @endif

                <x-input-error :messages="$errors->all()" class="mt-3" />
            </div>

            {{-- Historial --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">Historial de importaciones</h3>
                </div>

                @if ($historial->isEmpty())
                    <div class="p-6 text-sm text-gray-500 text-center">Todavía no se ha importado ningún archivo.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">Fecha</th>
                                    <th class="px-6 py-3">Archivo</th>
                                    <th class="px-6 py-3">Modo</th>
                                    <th class="px-6 py-3 text-center">Filas</th>
                                    <th class="px-6 py-3 text-center">Nuevas</th>
                                    <th class="px-6 py-3 text-center">Actualizadas</th>
                                    <th class="px-6 py-3 text-center">Errores</th>
                                    <th class="px-6 py-3">Estado</th>
                                    <th class="px-6 py-3 text-right">Detalle</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($historial as $log)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 text-gray-700">{{ $log->Fecha?->format('d/m/Y H:i') }}</td>
                                        <td class="px-6 py-3 font-medium text-gray-900">{{ \Illuminate\Support\Str::limit($log->Nombre_archivo, 40) }}</td>
                                        <td class="px-6 py-3">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $log->Modo === 'DryRun' ? 'bg-gray-100 text-gray-700' : 'bg-indigo-100 text-indigo-800' }}">
                                                {{ $log->Modo }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-center text-gray-700">{{ $log->Total_filas }}</td>
                                        <td class="px-6 py-3 text-center text-green-700">{{ $log->Insertadas }}</td>
                                        <td class="px-6 py-3 text-center text-blue-700">{{ $log->Actualizadas }}</td>
                                        <td class="px-6 py-3 text-center {{ $log->Errores > 0 ? 'text-red-700 font-semibold' : 'text-gray-500' }}">{{ $log->Errores }}</td>
                                        <td class="px-6 py-3 text-gray-700">{{ $log->Estado }}</td>
                                        <td class="px-6 py-3 text-right">
                                            <a href="{{ route('importaciones.show', $log) }}"
                                               class="text-indigo-600 hover:underline text-xs font-medium">Ver detalle</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-3 border-t border-gray-100">
                        {{ $historial->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
