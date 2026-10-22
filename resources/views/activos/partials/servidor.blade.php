@php
    $activo = $activo ?? new \App\Models\Activo();
    $estatuses = $estatuses ?? \App\Enums\EstatusServidor::opciones();
    $switches = $switches ?? collect();

    $maximoTarjetas = (int) config('zaico.servidores.maximo_tarjetas', 8);

    $opcionesSwitch = $switches->map(fn ($switch) => [
        'value' => $switch->id,
        'label' => $switch->Nombre_de_activo,
        'meta' => $switch->Etiqueta_activo ?? '',
    ])->values()->all();

    $tarjetasIniciales = old('tarjetas', $activo->tarjetasRed->map(fn ($tarjeta) => [
        'Numero' => $tarjeta->Numero,
        'Nombre' => $tarjeta->Nombre,
        'Direccion_MAC' => $tarjeta->Direccion_MAC,
        'IP' => $tarjeta->IP,
        'fk_switch' => $tarjeta->fk_switch,
        'Puerto' => $tarjeta->Puerto,
    ])->all());

    $erroresTarjetas = collect($errors->getBag('default')->getMessages())
        ->filter(fn ($mensajes, $campo) => str_starts_with((string) $campo, 'tarjetas'))
        ->flatten();
@endphp

<div class="md:col-span-2 rounded-lg border border-slate-200 bg-slate-50/70 p-4"
     x-data="panelServidor(@js([
         'categorias' => config('zaico.servidores.categorias', []),
         'esServidor' => $activo->esServidor() || \App\Models\Activo::categoriaEsServidor(old('Categoria')),
         'maximo' => $maximoTarjetas,
         'total' => count($tarjetasIniciales),
         'plantilla' => '#servidor-plantilla-tarjeta',
         'filas' => '#servidor-filas-tarjetas',
     ]))"
     x-show="esServidor" x-cloak>

    <input type="hidden" name="tarjetas_presente" value="1">

    <h3 class="text-sm font-semibold text-slate-800">Datos del servidor</h3>
    <p class="mt-1 text-xs text-slate-500">
        Se muestran cuando la categoría corresponde a un servidor ({{ implode(', ', config('zaico.servidores.categorias', [])) }}).
        Todos los campos son opcionales. La dirección IP se registra en el bloque general del equipo.
    </p>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <x-input-label for="Sistema_Operativo" value="Sistema operativo" />
            <x-text-input id="Sistema_Operativo" name="Sistema_Operativo" type="text" class="mt-1"
                          :value="old('Sistema_Operativo', $activo->Sistema_Operativo)"
                          placeholder="Ej.: Windows Server 2019" />
            <x-input-error :messages="$errors->get('Sistema_Operativo')" />
        </div>

        <div>
            <x-input-label for="Memoria_RAM" value="Memoria RAM" />
            <x-text-input id="Memoria_RAM" name="Memoria_RAM" type="text" class="mt-1"
                          :value="old('Memoria_RAM', $activo->Memoria_RAM)" placeholder="Ej.: 32 GB" />
            <x-input-error :messages="$errors->get('Memoria_RAM')" />
        </div>

        <div>
            <x-input-label for="Capacidad_Disco" value="Capacidad de disco" />
            <x-text-input id="Capacidad_Disco" name="Capacidad_Disco" type="text" class="mt-1"
                          :value="old('Capacidad_Disco', $activo->Capacidad_Disco)" placeholder="Ej.: 2 x 1 TB SSD" />
            <x-input-error :messages="$errors->get('Capacidad_Disco')" />
        </div>

        <div>
            <x-input-label for="Estatus_Servidor" value="Estatus" />
            <select id="Estatus_Servidor" name="Estatus_Servidor" class="form-control mt-1">
                <option value="">Sin definir</option>
                @foreach ($estatuses as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('Estatus_Servidor', $activo->Estatus_Servidor) === $valor)>
                        {{ $etiqueta }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('Estatus_Servidor')" />
        </div>
    </div>

    <div class="mt-5 border-t border-slate-200 pt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h4 class="text-sm font-semibold text-slate-700">Tarjetas de red</h4>
                <p class="text-xs text-slate-500">
                    Máximo {{ $maximoTarjetas }} tarjetas. El switch se elige del inventario de equipos.
                </p>
            </div>
            <button type="button" @click="agregar()" :disabled="! puedeAgregar"
                    class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50">
                <x-icon name="plus" :size="16" /> Agregar tarjeta
                <span class="ms-1 text-xs font-normal text-slate-500">
                    (<span x-text="total"></span>/{{ $maximoTarjetas }})
                </span>
            </button>
        </div>

        <x-input-error :messages="$erroresTarjetas->all()" class="mt-3" />

        <div x-ref="filas" id="servidor-filas-tarjetas" class="mt-3 space-y-3">
            @foreach ($tarjetasIniciales as $indice => $tarjeta)
                @include('activos.partials.tarjeta', [
                    'clave' => $indice,
                    'tarjeta' => $tarjeta,
                    'opcionesSwitch' => $opcionesSwitch,
                ])
            @endforeach
        </div>

        <p x-show="total === 0" x-cloak class="mt-3 text-sm text-slate-500">
            Este servidor no tiene tarjetas de red registradas.
        </p>

        <template x-ref="plantilla" id="servidor-plantilla-tarjeta">
            @include('activos.partials.tarjeta', [
                'clave' => '__tarjeta__',
                'tarjeta' => [],
                'opcionesSwitch' => $opcionesSwitch,
            ])
        </template>
    </div>
</div>
