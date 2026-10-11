<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Compras" subtitle="Registro de compras de equipos" icon="currency" />
    </x-slot>

    <div class="mx-auto max-w-5xl">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Resultados
                    <span class="ms-1 font-normal text-slate-400">({{ $compras->total() }})</span>
                </h3>
            </div>

            @if ($compras->isEmpty())
                <x-empty-state title="No hay compras registradas"
                               description="Cada compra se asocia a un equipo desde su ficha." icon="currency" />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Equipo</th>
                                <th>Fecha</th>
                                <th>Proveedor</th>
                                <th class="text-right">Costo</th>
                                <th class="hidden md:table-cell">Garantía</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($compras as $compra)
                                <tr>
                                    <td class="font-medium">
                                        <a class="link" href="{{ route('activos.show', $compra->fk_activo) }}">
                                            {{ $compra->activo?->Etiqueta_activo }}
                                        </a>
                                        <span class="block text-xs text-slate-500">{{ $compra->activo?->Nombre_de_activo }}</span>
                                    </td>
                                    <td>{{ $compra->Fecha_Compra?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-slate-600">{{ $compra->Proveedor ?? '—' }}</td>
                                    <td class="text-right font-medium text-slate-900">
                                        {{ $compra->Costo_compra !== null ? '$ '.number_format((float) $compra->Costo_compra, 2) : '—' }}
                                    </td>
                                    <td class="hidden text-slate-500 md:table-cell">
                                        {{ trim(($compra->Tiempo_garantia ?? '').($compra->Garantia ? ' · '.$compra->Garantia : '')) ?: '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $compras->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>