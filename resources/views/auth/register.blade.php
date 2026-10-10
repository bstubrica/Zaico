<x-guest-layout>
    <h2 class="text-lg font-bold text-slate-900">Crear cuenta</h2>
    <p class="mt-1 text-sm text-slate-500">Regístrate para acceder al sistema.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="name" value="Nombre" />
            <x-text-input id="name" class="mt-1" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmar contraseña" />
            <x-text-input id="password_confirmation" class="mt-1" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="flex items-center justify-between">
            <a class="link text-sm" href="{{ route('login') }}">¿Ya tienes cuenta?</a>
            <x-primary-button>
                <x-icon name="user-circle" :size="18" /> Registrarse
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
