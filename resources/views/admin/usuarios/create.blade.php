<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Nuevo usuario" subtitle="Alta de una cuenta de acceso al sistema" icon="user-plus">
            <x-slot name="actions">
                <a href="{{ route('admin.usuarios.index') }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Volver
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="POST" action="{{ route('admin.usuarios.store') }}" class="mx-auto max-w-3xl">
        @csrf

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Datos del usuario</h3>
            </div>
            <div class="card-body">
                @include('admin.usuarios.partials.form')
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <a href="{{ route('admin.usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check-circle" :size="18" /> Crear usuario
                </button>
            </div>
        </div>
    </form>
</x-app-layout>
