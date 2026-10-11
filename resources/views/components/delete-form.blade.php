@props([
    'action' => '',
    'title' => 'Eliminar registro',
    'message' => '¿Está seguro de eliminar este registro? Esta acción no se puede deshacer.',
    'confirm' => 'Eliminar',
    'buttonClass' => 'btn btn-ghost btn-sm text-rose-600',
])

<div x-data="{ open: false }"
     x-effect="document.body.classList.toggle('overflow-hidden', open)"
     class="inline-flex">
    <button type="button" @click="open = true" title="{{ $title }}"
            {{ $attributes->class($buttonClass) }}>
        {{ $slot }}
    </button>

    <div x-show="open" x-cloak role="dialog" aria-modal="true"
         class="fixed inset-0 z-50 overflow-y-auto px-4 py-8 sm:px-0">
        <div x-show="open" x-transition.opacity
             class="fixed inset-0 bg-slate-900/50" @click="open = false"></div>

        <div x-show="open" x-transition
             class="relative card mx-auto w-full max-w-sm">
            <div class="card-header">
                <h3 class="card-title flex items-center gap-2 text-rose-700">
                    <x-icon name="warning" :size="20" /> {{ $title }}
                </h3>
            </div>

            <div class="card-body">
                <p class="text-sm text-slate-600">{{ $message }}</p>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <button type="button" class="btn btn-secondary" @click="open = false">Cancelar</button>

                <form method="POST" action="{{ $action }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <x-icon name="trash" :size="18" /> {{ $confirm }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>