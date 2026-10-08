<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Estados de catálogo</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                @if ($estados->isEmpty())
                    <div class="p-8 text-center text-sm text-gray-500">
                        El catálogo de estados está vacío. Ejecute <code class="text-xs bg-gray-100 px-1 rounded">php artisan db:seed --class=EstadoSeeder</code>.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">Nombre</th>
                                    <th class="px-6 py-3">Grupo</th>
                                    <th class="px-6 py-3 text-center">Deployed</th>
                                    <th class="px-6 py-3 text-center">Deployable</th>
                                    <th class="px-6 py-3 text-center">Activos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($estados as $estado)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 font-medium text-gray-900">{{ $estado->Nombre }}</td>
                                        <td class="px-6 py-3">
                                            <x-status-badge :grupo="$estado->Grupo" />
                                        </td>
                                        <td class="px-6 py-3 text-center">{{ $estado->Deployed ? 'Sí' : 'No' }}</td>
                                        <td class="px-6 py-3 text-center">{{ $estado->Deployable ? 'Sí' : 'No' }}</td>
                                        <td class="px-6 py-3 text-center text-gray-500">{{ $estado->activos_count ?? $estado->activos()->count() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
