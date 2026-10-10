<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Registrar equipo" subtitle="Alta de un nuevo activo en el inventario" icon="plus">
            <x-slot name="actions">
                <a href="{{ route('activos.index') }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Volver
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="POST" action="{{ route('activos.store') }}" class="mx-auto max-w-3xl">
        @csrf

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Datos del equipo</h3>
            </div>
            <div class="card-body">
                @include('activos.partials.form', ['grupos' => $grupos, 'estados' => $estados])
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <a href="{{ route('activos.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check-circle" :size="18" /> Guardar equipo
                </button>
            </div>
        </div>
    </form>
</x-app-layout>
