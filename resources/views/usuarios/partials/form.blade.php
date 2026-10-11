@php($usuario = $usuario ?? new \App\Models\User())
@php($requierePassword = $requierePassword ?? false)
@php($roles = ['Admin', 'SoporteTecnico', 'Auditor'])

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="name" value="Nombre *" />
        <x-text-input id="name" name="name" type="text" class="mt-1"
                      :value="old('name', $usuario->name)" required maxlength="255" />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="email" value="Correo *" />
        <x-text-input id="email" name="email" type="email" class="mt-1"
                      :value="old('email', $usuario->email)" required maxlength="255" />
        <x-input-error :messages="$errors->get('email')" />
    </div>

    <div>
        <x-input-label for="role" value="Rol *" />
        <select id="role" name="role" class="form-control mt-1" required>
            @foreach ($roles as $rol)
                <option value="{{ $rol }}" @selected(old('role', $usuario->role ?? 'Auditor') === $rol)>{{ $rol }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Admin gestiona todo · SoporteTecnico escribe en inventario · Auditor solo consulta.</p>
        <x-input-error :messages="$errors->get('role')" />
    </div>

    <div>
        <x-input-label for="password" :value="$requierePassword ? 'Contraseña *' : 'Nueva contraseña'" />
        <x-text-input id="password" name="password" type="password" class="mt-1"
                      :required="$requierePassword" autocomplete="new-password" />
        @if (! $requierePassword)
            <p class="mt-1 text-xs text-slate-500">Déjela vacía para conservar la contraseña actual.</p>
        @endif
        <x-input-error :messages="$errors->get('password')" />
    </div>
</div>