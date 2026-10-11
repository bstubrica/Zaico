<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Editar equipo" :subtitle="$activo->Etiqueta_activo . ' · ' . $activo->Nombre_de_activo" icon="pencil">
            <x-slot name="actions">
                <a href="{{ route('activos.show', $activo) }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Volver
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="POST" action="{{ route('activos.update', $activo) }}" class="mx-auto max-w-3xl">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Datos del equipo</h3>
            </div>
            <div class="card-body">
                @include('activos.partials.form', ['grupos' => $grupos, 'estados' => $estados])
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <a href="{{ route('activos.show', $activo) }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check-circle" :size="18" /> Actualizar
                </button>
            </div>
        </div>
    </form>
</x-app-layout>
