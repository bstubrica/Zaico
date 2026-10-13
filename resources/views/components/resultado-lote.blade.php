@php
    $lote = session('lote_omitidos');
@endphp

@if (is_array($lote) && $lote !== [])
    <div x-data="{ abierto: false }"
         class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <x-icon name="warning" :size="20" class="mt-0.5 shrink-0" />
        <div class="flex-1">
            <p class="font-medium">
                {{ count($lote) }} registro(s) no se procesaron.
                <button type="button" class="ms-2 text-xs font-semibold underline" @click="abierto = !abierto">
                    <span x-show="!abierto">Ver detalle</span>
                    <span x-show="abierto" x-cloak>Ocultar detalle</span>
                </button>
            </p>
            <ul x-show="abierto" x-cloak
                class="mt-2 max-h-48 list-disc space-y-0.5 overflow-y-auto ps-5">
                @foreach ($lote as $omitido)
                    <li><strong>{{ $omitido['label'] }}</strong>: {{ $omitido['motivo'] }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
