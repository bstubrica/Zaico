@php($persona = $persona ?? new \App\Models\Personal())

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="Nombre" value="Nombre *" />
        <x-text-input id="Nombre" name="Nombre" type="text" class="mt-1 block w-full"
                      :value="old('Nombre', $persona->Nombre)" required />
        <x-input-error :messages="$errors->get('Nombre')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Apellido" value="Apellido *" />
        <x-text-input id="Apellido" name="Apellido" type="text" class="mt-1 block w-full"
                      :value="old('Apellido', $persona->Apellido)" required />
        <x-input-error :messages="$errors->get('Apellido')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Nombre_usuario" value="Nombre de usuario *" />
        <x-text-input id="Nombre_usuario" name="Nombre_usuario" type="text" class="mt-1 block w-full"
                      :value="old('Nombre_usuario', $persona->Nombre_usuario)" required />
        <x-input-error :messages="$errors->get('Nombre_usuario')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="Cargo" value="Cargo" />
        <x-text-input id="Cargo" name="Cargo" type="text" class="mt-1 block w-full"
                      :value="old('Cargo', $persona->Cargo)" />
        <x-input-error :messages="$errors->get('Cargo')" class="mt-2" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="Observaciones" value="Observaciones" />
        <textarea id="Observaciones" name="Observaciones" rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('Observaciones', $persona->Observaciones) }}</textarea>
        <x-input-error :messages="$errors->get('Observaciones')" class="mt-2" />
    </div>
</div>
