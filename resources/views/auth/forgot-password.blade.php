<x-guest-layout>
    <h2 class="text-lg font-bold text-slate-900">Recuperar contraseña</h2>
    <p class="mt-1 text-sm text-slate-500">
        Indica tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
    </p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="flex items-center justify-between">
            <a class="link text-sm" href="{{ route('login') }}">Volver a iniciar sesión</a>
            <x-primary-button>
                <x-icon name="key" :size="18" /> Enviar enlace
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
