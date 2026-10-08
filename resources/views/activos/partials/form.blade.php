@php($activo = $activo ?? new \App\Models\Activo())

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <x-input-label for="Nombre_de_activo" value="Nombre del activo *" />
        <x-text-input id="Nombre_de_activo" name="Nombre_de_activo" type="text" class="mt-1 block w-full"
                      :value="old('Nombre_de_activo', $activo->Nombre_de_activo)" required />
        <x-input-error :messages="$errors->get('Nombre_de_activo')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Etiqueta_activo" value="Etiqueta *" />
        <x-text-input id="Etiqueta_activo" name="Etiqueta_activo" type="text" class="mt-1 block w-full"
                      :value="old('Etiqueta_activo', $activo->Etiqueta_activo)" required />
        <x-input-error :messages="$errors->get('Etiqueta_activo')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Serial" value="Serial" />
        <x-text-input id="Serial" name="Serial" type="text" class="mt-1 block w-full"
                      :value="old('Serial', $activo->Serial)" />
        <x-input-error :messages="$errors->get('Serial')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Categoria" value="Categoría" />
        <x-text-input id="Categoria" name="Categoria" type="text" class="mt-1 block w-full"
                      :value="old('Categoria', $activo->Categoria)" />
        <x-input-error :messages="$errors->get('Categoria')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Fabricante" value="Fabricante" />
        <x-text-input id="Fabricante" name="Fabricante" type="text" class="mt-1 block w-full"
                      :value="old('Fabricante', $activo->Fabricante)" />
        <x-input-error :messages="$errors->get('Fabricante')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Modelo" value="Modelo" />
        <x-text-input id="Modelo" name="Modelo" type="text" class="mt-1 block w-full"
                      :value="old('Modelo', $activo->Modelo)" />
        <x-input-error :messages="$errors->get('Modelo')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Direccion_MAC" value="Dirección MAC" />
        <x-text-input id="Direccion_MAC" name="Direccion_MAC" type="text" class="mt-1 block w-full"
                      :value="old('Direccion_MAC', $activo->Direccion_MAC)" placeholder="00:11:22:33:44:55" />
        <x-input-error :messages="$errors->get('Direccion_MAC')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Ubicacion" value="Ubicación" />
        <x-text-input id="Ubicacion" name="Ubicacion" type="text" class="mt-1 block w-full"
                      :value="old('Ubicacion', $activo->Ubicacion)" />
        <x-input-error :messages="$errors->get('Ubicacion')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Ubicacion_Predeterminada" value="Ubicación predeterminada" />
        <x-text-input id="Ubicacion_Predeterminada" name="Ubicacion_Predeterminada" type="text" class="mt-1 block w-full"
                      :value="old('Ubicacion_Predeterminada', $activo->Ubicacion_Predeterminada)" />
        <x-input-error :messages="$errors->get('Ubicacion_Predeterminada')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Estado" value="Estado (grupo) *" />
        <select id="Estado" name="Estado"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($grupos as $grupo)
                <option value="{{ $grupo }}" @selected(old('Estado', $activo->Estado) === $grupo)>{{ $grupo }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('Estado')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="fk_estado" value="Estado de catálogo (detalle)" />
        <select id="fk_estado" name="fk_estado"
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">— Sin detalle —</option>
            @foreach ($estados as $estado)
                <option value="{{ $estado->id }}" @selected((int) old('fk_estado', $activo->fk_estado) === $estado->id)>
                    {{ $estado->Grupo }} · {{ $estado->Nombre }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('fk_estado')" class="mt-2" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="Observaciones" value="Observaciones" />
        <textarea id="Observaciones" name="Observaciones" rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('Observaciones', $activo->Observaciones) }}</textarea>
        <x-input-error :messages="$errors->get('Observaciones')" class="mt-2" />
    </div>
</div>
