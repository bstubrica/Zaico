<x-guest-layout>
    <h2 class="text-lg font-bold text-slate-900">Confirmar contraseña</h2>
    <p class="mt-1 text-sm text-slate-500">
        Esta es un área segura. Confirma tu contraseña antes de continuar.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <x-primary-button class="w-full">
            <x-icon name="shield" :size="18" /> Confirmar
        </x-primary-button>
    </form>
</x-guest-layout>
