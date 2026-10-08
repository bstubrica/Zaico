<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detalle de importación</h2>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $importacion->Modo === 'DryRun' ? 'bg-gray-100 text-gray-700' : 'bg-indigo-100 text-indigo-800' }}">
                    {{ $importacion->Modo }}
                </span>
            </div>
            <a href="{{ route('importaciones.index') }}"
               class="text-sm font-medium text-gray-500 hover:text-gray-700">← Volver</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm font-medium text-gray-500">Archivo</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900 break-all">{{ $importacion->Nombre_archivo }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm font-medium text-gray-500">Total filas</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ $importacion->Total_filas }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm font-medium text-gray-500">Nuevas</div>
                    <div class="mt-1 text-2xl font-bold text-green-700">{{ $importacion->Insertadas }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm font-medium text-gray-500">Actualizadas</div>
                    <div class="mt-1 text-2xl font-bold text-blue-700">{{ $importacion->Actualizadas }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-sm font-medium text-gray-500">Errores</div>
                    <div class="mt-1 text-2xl font-bold {{ $importacion->Errores > 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $importacion->Errores }}</div>
                </div>
            </div>

            @if ($importacion->Modo === 'DryRun')
                <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800" role="status">
                    Este fue una <strong>previsualización</strong>: no se modificó la base de datos.
                    Vuelva a la pantalla anterior para confirmar la importación.
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-800">Filas</h3>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" id="solo-errores" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Solo errores
                    </label>
                </div>

                @if ($importacion->detalles->isEmpty())
                    <div class="p-6 text-sm text-gray-500 text-center">Sin detalle de filas.</div>
                @else
                    <div class="overflow-x-auto max-h-[32rem] overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm" id="tabla-detalles">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3">Fila</th>
                                    <th class="px-4 py-3">Etiqueta</th>
                                    <th class="px-4 py-3">Acción</th>
                                    <th class="px-4 py-3">Mensaje</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($importacion->detalles as $detalle)
                                    <tr class="hover:bg-gray-50 {{ $detalle->Accion === 'Error' ? 'bg-red-50' : '' }}" data-accion="{{ $detalle->Accion }}">
                                        <td class="px-4 py-2 text-gray-500">{{ $detalle->Fila_numero }}</td>
                                        <td class="px-4 py-2 font-medium text-gray-900">{{ $detalle->Etiqueta ?? '—' }}</td>
                                        <td class="px-4 py-2">
                                            @php
                                                $colores = [
                                                    'Insertado' => 'bg-green-100 text-green-800',
                                                    'Actualizado' => 'bg-blue-100 text-blue-800',
                                                    'SinCambios' => 'bg-gray-100 text-gray-600',
                                                    'Error' => 'bg-red-100 text-red-800',
                                                ];
                                            @endphp
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $colores[$detalle->Accion] ?? 'bg-gray-100 text-gray-600' }}">
                                                {{ $detalle->Accion }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ $detalle->Mensaje ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('solo-errores')?.addEventListener('change', function () {
                const soloErrores = this.checked;
                document.querySelectorAll('#tabla-detalles tbody tr').forEach(fila => {
                    fila.style.display = (soloErrores && fila.dataset.accion !== 'Error') ? 'none' : '';
                });
            });
        </script>
    @endpush
</x-app-layout>
