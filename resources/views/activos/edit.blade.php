<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Editar equipo · {{ $activo->Etiqueta_activo }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('activos.update', $activo) }}" class="bg-white shadow-sm sm:rounded-lg p-6">
                @csrf
                @method('PUT')

                @include('activos.partials.form', ['grupos' => $grupos, 'estados' => $estados])

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('activos.show', $activo) }}"
                       class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Cancelar
                    </a>
                    <button type="submit"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
