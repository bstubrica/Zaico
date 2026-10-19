<x-guest-layout>
    <h2 class="text-lg font-bold text-slate-900">Iniciar sesión</h2>
    <p class="mt-1 text-sm text-slate-500">Accede con tu usuario y contraseña.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="username" value="Usuario" />
            <x-text-input id="username" class="mt-1" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" placeholder="admin" />
            <x-input-error :messages="$errors->get('username')" />
        </div>

        <div>
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500" name="remember">
                <span class="ms-2 text-sm text-slate-600">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a class="link text-sm" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <x-primary-button class="w-full">
            <x-icon name="key" :size="18" /> Iniciar sesión
        </x-primary-button>
    </form>
</x-guest-layout>
