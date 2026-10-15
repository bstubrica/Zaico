@php($persona = $persona ?? new \App\Models\Personal())

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="Nombre" value="Nombre *" />
        <x-text-input id="Nombre" name="Nombre" type="text" class="mt-1"
                      :value="old('Nombre', $persona->Nombre)" required />
        <x-input-error :messages="$errors->get('Nombre')" />
    </div>

    <div>
        <x-input-label for="Apellido" value="Apellido *" />
        <x-text-input id="Apellido" name="Apellido" type="text" class="mt-1"
                      :value="old('Apellido', $persona->Apellido)" required />
        <x-input-error :messages="$errors->get('Apellido')" />
    </div>

    <div>
        <x-input-label for="Nombre_usuario" value="Nombre de usuario *" />
        <x-text-input id="Nombre_usuario" name="Nombre_usuario" type="text" class="mt-1"
                      :value="old('Nombre_usuario', $persona->Nombre_usuario)" required />
        <x-input-error :messages="$errors->get('Nombre_usuario')" />
    </div>

    <div>
        <x-input-label for="fk_ubicacion" value="Departamento / Ubicación" />
        <select id="fk_ubicacion" name="fk_ubicacion" class="form-control mt-1">
            <option value="">Sin asignar</option>
            @foreach ($ubicaciones as $ubicacion)
                <option value="{{ $ubicacion->id }}" @selected(old('fk_ubicacion', $persona->fk_ubicacion) == $ubicacion->id)>
                    {{ $ubicacion->Nombre }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('fk_ubicacion')" />
    </div>

    <div>
        <x-input-label for="Cargo" value="Cargo" />
        <x-text-input id="Cargo" name="Cargo" type="text" class="mt-1" list="cargos-sugeridos"
                      :value="old('Cargo', $persona->Cargo)" placeholder="Ej. Analista Contable, Coordinador…" />
        @if (! empty($cargos))
            <datalist id="cargos-sugeridos">
                @foreach ($cargos as $cargoOpcion)
                    <option value="{{ $cargoOpcion }}"></option>
                @endforeach
            </datalist>
        @endif
        <x-input-error :messages="$errors->get('Cargo')" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="Observaciones" value="Observaciones" />
        <textarea id="Observaciones" name="Observaciones" rows="3" class="form-control mt-1">{{ old('Observaciones', $persona->Observaciones) }}</textarea>
        <x-input-error :messages="$errors->get('Observaciones')" />
    </div>
</div>
