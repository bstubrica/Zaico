<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Nueva ubicación" subtitle="Alta de una ubicación para el inventario" icon="plus">
            <x-slot name="actions">
                <a href="{{ route('ubicaciones.index') }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Volver
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="POST" action="{{ route('ubicaciones.store') }}" class="mx-auto max-w-3xl">
        @csrf

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Datos de la ubicación</h3>
            </div>
            <div class="card-body">
                @include('ubicaciones.partials.form')
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <a href="{{ route('ubicaciones.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check-circle" :size="18" /> Guardar ubicación
                </button>
            </div>
        </div>
    </form>
</x-app-layout>
