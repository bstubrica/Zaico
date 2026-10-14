<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Editar modelo" :subtitle="$modelo->Nombre" icon="pencil">
            <x-slot name="actions">
                <a href="{{ route('modelos.index') }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Volver
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="POST" action="{{ route('modelos.update', $modelo) }}" class="mx-auto max-w-3xl">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Datos del modelo</h3>
            </div>
            <div class="card-body">
                @include('modelos.partials.form')
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <a href="{{ route('modelos.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check-circle" :size="18" /> Actualizar
                </button>
            </div>
        </div>
    </form>
</x-app-layout>
