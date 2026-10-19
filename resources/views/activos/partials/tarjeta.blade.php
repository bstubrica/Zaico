@php
    $clave = $clave ?? '__tarjeta__';
    $tarjeta = $tarjeta ?? [];
    $opcionesSwitch = $opcionesSwitch ?? [];

    $valores = array_merge([
        'Numero' => '',
        'Nombre' => '',
        'Direccion_MAC' => '',
        'IP' => '',
        'fk_switch' => null,
        'Puerto' => '',
    ], $tarjeta);

    $campo = fn (string $nombre): string => "tarjetas[{$clave}][{$nombre}]";
    $valor = fn (string $nombre) => old("tarjetas.{$clave}.{$nombre}", $valores[$nombre]);
@endphp

<div class="rounded-md border border-slate-200 bg-white p-3" data-fila-tarjeta>
    <div class="mb-2 flex items-center justify-between gap-2">
        <h4 class="text-sm font-semibold text-slate-700">Tarjeta de red</h4>
        <button type="button" @click="quitar($event)"
                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50">
            <x-icon name="trash" :size="16" /> Quitar
        </button>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <x-input-label :for="$campo('Numero')" value="N.º de tarjeta *" />
            <input type="number" min="1" max="999" id="{{ $campo('Numero') }}" name="{{ $campo('Numero') }}"
                   value="{{ $valor('Numero') }}" class="form-control mt-1">
        </div>

        <div>
            <x-input-label :for="$campo('Nombre')" value="Nombre de la interfaz" />
            <input type="text" id="{{ $campo('Nombre') }}" name="{{ $campo('Nombre') }}" maxlength="60"
                   value="{{ $valor('Nombre') }}" placeholder="Ej.: Ethernet 1" class="form-control mt-1">
        </div>

        <div>
            <x-input-label :for="$campo('Direccion_MAC')" value="Dirección MAC" />
            <input type="text" id="{{ $campo('Direccion_MAC') }}" name="{{ $campo('Direccion_MAC') }}" maxlength="17"
                   value="{{ $valor('Direccion_MAC') }}" placeholder="00:11:22:33:44:55" class="form-control mt-1">
        </div>

        <div>
            <x-input-label :for="$campo('IP')" value="IP" />
            <input type="text" id="{{ $campo('IP') }}" name="{{ $campo('IP') }}" maxlength="45"
                   value="{{ $valor('IP') }}" placeholder="192.168.0.10" class="form-control mt-1">
        </div>

        <div>
            <x-input-label :for="$campo('fk_switch')" value="Switch (catálogo)" />
            <x-search-select
                :name="$campo('fk_switch')"
                class="mt-1"
                placeholder="Escriba el switch…"
                :selected="$valores['fk_switch']"
                :options="$opcionesSwitch" />
        </div>

        <div>
            <x-input-label :for="$campo('Puerto')" value="Puerto" />
            <input type="text" id="{{ $campo('Puerto') }}" name="{{ $campo('Puerto') }}" maxlength="30"
                   value="{{ $valor('Puerto') }}" placeholder="Ej.: Gi0/12" class="form-control mt-1">
        </div>
    </div>
</div>
