@php
    $usuario = $usuario ?? null;
    $esEdicion = $usuario !== null;
    $etiquetasRol = [
        'Admin' => 'Administrador',
        'SoporteTecnico' => 'Soporte Técnico',
        'Auditor' => 'Auditor',
    ];
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="name" value="Nombre *" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      :value="old('name', $usuario?->name)" required autofocus autocomplete="name" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="Correo electrónico *" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                      :value="old('email', $usuario?->email)" required autocomplete="email" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="role" value="Rol *" />
        <select id="role" name="role" class="form-control mt-1" required>
            @foreach ($roles as $rol)
                <option value="{{ $rol }}" @selected(old('role', $usuario?->role ?? 'Auditor') === $rol)>
                    {{ $etiquetasRol[$rol] ?? $rol }}
                </option>
            @endforeach
        </select>
        <p class="form-hint">Administrador: control total. Soporte Técnico: escritura de inventario. Auditor: solo lectura.</p>
        <x-input-error :messages="$errors->get('role')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password" :value="$esEdicion ? 'Nueva contraseña (opcional)' : 'Contraseña *'" />
        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full"
                      :required="! $esEdicion" autocomplete="new-password" />
        @if ($esEdicion)
            <p class="form-hint">Deje en blanco para conservar la contraseña actual.</p>
        @endif
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password_confirmation" :value="$esEdicion ? 'Confirmar nueva contraseña' : 'Confirmar contraseña *'" />
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full"
                      :required="! $esEdicion" autocomplete="new-password" />
        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
    </div>
</div>
