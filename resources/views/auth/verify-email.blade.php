<x-guest-layout>
    <h2 class="text-lg font-bold text-slate-900">Verifica tu correo</h2>
    <p class="mt-1 text-sm text-slate-500">
        Gracias por registrarte. Antes de comenzar, verifica tu dirección de correo haciendo clic en el enlace que te enviamos.
        Si no lo recibiste, podemos enviarte otro.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
            Se envió un nuevo enlace de verificación al correo que registraste.
        </div>
    @endif

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>
                <x-icon name="refresh" :size="18" /> Reenviar correo
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="link text-sm">Cerrar sesión</button>
        </form>
    </div>
</x-guest-layout>
