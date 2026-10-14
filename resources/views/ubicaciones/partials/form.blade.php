@php($ubicacion = $ubicacion ?? new \App\Models\Ubicacion())

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <x-input-label for="Nombre" value="Nombre *" />
        <x-text-input id="Nombre" name="Nombre" type="text" class="mt-1"
                      :value="old('Nombre', $ubicacion->Nombre)" required />
        <x-input-error :messages="$errors->get('Nombre')" />
    </div>

    <div>
        <x-input-label for="Estado" value="Estado" />
        <select id="Estado" name="Estado" class="form-control mt-1">
            @foreach (['Activo', 'Inactivo'] as $opcion)
                <option value="{{ $opcion }}" @selected(old('Estado', $ubicacion->Estado ?? 'Activo') === $opcion)>
                    {{ $opcion }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('Estado')" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="Descripcion" value="Descripción" />
        <textarea id="Descripcion" name="Descripcion" rows="3"
                  class="form-control mt-1">{{ old('Descripcion', $ubicacion->Descripcion) }}</textarea>
        <x-input-error :messages="$errors->get('Descripcion')" />
    </div>
</div>
