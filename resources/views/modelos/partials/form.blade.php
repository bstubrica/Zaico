@php($modelo = $modelo ?? new \App\Models\ModeloActivo())

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="Nombre" value="Nombre *" />
        <x-text-input id="Nombre" name="Nombre" type="text" class="mt-1"
                      :value="old('Nombre', $modelo->Nombre)" required />
        <x-input-error :messages="$errors->get('Nombre')" />
    </div>

    <div>
        <x-input-label for="Fabricante" value="Fabricante" />
        <x-text-input id="Fabricante" name="Fabricante" type="text" class="mt-1"
                      :value="old('Fabricante', $modelo->Fabricante)" />
        <x-input-error :messages="$errors->get('Fabricante')" />
    </div>

    <div>
        <x-input-label for="Categoria" value="Categoría" />
        <x-text-input id="Categoria" name="Categoria" type="text" class="mt-1"
                      :value="old('Categoria', $modelo->Categoria)" />
        <x-input-error :messages="$errors->get('Categoria')" />
    </div>

    <div>
        <x-input-label for="Estado" value="Estado" />
        <select id="Estado" name="Estado" class="form-control mt-1">
            @foreach (['Activo', 'Inactivo'] as $opcion)
                <option value="{{ $opcion }}" @selected(old('Estado', $modelo->Estado ?? 'Activo') === $opcion)>
                    {{ $opcion }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('Estado')" />
    </div>
</div>
