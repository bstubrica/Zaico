<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Zaiko') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">
            <!-- Overlay móvil -->
            <div x-cloak x-show="sidebarOpen" x-transition.opacity
                 @click="sidebarOpen = false"
                 class="fixed inset-0 z-40 bg-slate-900/60 lg:hidden"></div>

            <!-- Barra lateral -->
            @include('layouts.navigation')

            <!-- Contenido -->
            <div class="lg:pl-64">
                <!-- Barra superior -->
                <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
                    <button type="button" @click="sidebarOpen = true"
                            class="-ms-2 inline-flex items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 lg:hidden"
                            aria-label="Abrir menú">
                        <x-icon name="menu" :size="22" />
                    </button>

                    <!-- Búsqueda global -->
                    <form method="GET" action="{{ route('activos.index') }}" class="hidden flex-1 sm:block">
                        <div class="relative max-w-md">
                            <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
                                <x-icon name="search" :size="18" />
                            </span>
                            <input type="search" name="termino"
                                   placeholder="Buscar por etiqueta, serial, nombre…"
                                   class="form-control ps-10"
                                   aria-label="Buscar equipos">
                        </div>
                    </form>

                    <a href="{{ route('activos.buscar') }}" title="Búsqueda rápida por etiqueta o serial"
                       class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                       aria-label="Búsqueda rápida por etiqueta o serial">
                        <x-icon name="camera" :size="20" />
                    </a>

                    <div class="ms-auto"></div>

                    <!-- Notificaciones -->
                    @if (Auth::user()->puedeEscribir())
                        @php $noLeidas = Auth::user()->unreadNotifications; @endphp
                        <x-dropdown align="right" width="w-80">
                            <x-slot name="trigger">
                                <button class="relative inline-flex items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus:outline-none"
                                        aria-label="Notificaciones">
                                    <x-icon name="bell" :size="20" />
                                    @if ($noLeidas->count() > 0)
                                        <span class="absolute -end-0.5 -top-0.5 inline-flex min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">
                                            {{ min($noLeidas->count(), 99) }}
                                        </span>
                                    @endif
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                    <p class="text-sm font-semibold text-slate-800">Notificaciones</p>
                                    @if ($noLeidas->count() > 0)
                                        <form method="POST" action="{{ route('notificaciones.leidas') }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-brand-600 hover:text-brand-700">
                                                Marcar leídas
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                <div class="max-h-80 overflow-y-auto">
                                    @forelse ($noLeidas->take(8) as $notificacion)
                                        <a href="{{ isset($notificacion->data['fk_activo']) ? route('activos.show', $notificacion->data['fk_activo']) : route('alertas.index') }}"
                                           class="block border-b border-slate-50 px-4 py-3 hover:bg-slate-50">
                                            <p class="text-sm font-medium text-slate-800">{{ $notificacion->data['titulo'] ?? 'Alerta' }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $notificacion->data['mensaje'] ?? '' }}</p>
                                            <p class="mt-1 text-[11px] text-slate-400">{{ $notificacion->created_at->diffForHumans() }}</p>
                                        </a>
                                    @empty
                                        <p class="px-4 py-6 text-center text-sm text-slate-500">No tienes notificaciones nuevas.</p>
                                    @endforelse
                                </div>

                                <a href="{{ route('alertas.index') }}"
                                   class="block border-t border-slate-100 px-4 py-3 text-center text-sm font-medium text-brand-600 hover:bg-slate-50">
                                    Ver todas las alertas
                                </a>
                            </x-slot>
                        </x-dropdown>
                    @endif

                    <!-- Usuario -->
                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button class="flex items-center gap-2 rounded-lg p-1.5 text-sm hover:bg-slate-100 focus:outline-none">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
                                    {{ \Illuminate\Support\Str::of(Auth::user()->name)->explode(' ')->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))->take(2)->implode('') }}
                                </span>
                                <span class="hidden text-left md:block">
                                    <span class="block text-sm font-semibold leading-tight text-slate-800">{{ Auth::user()->name }}</span>
                                    <span class="block text-xs leading-tight text-slate-500">{{ Auth::user()->role }}</span>
                                </span>
                                <x-icon name="chevron-down" :size="16" class="hidden text-slate-400 md:block" />
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-800">{{ Auth::user()->name }}</p>
                                <p class="truncate text-xs text-slate-500" title="{{ Auth::user()->email }}">
                                    {{ '@'.Auth::user()->username }}
                                </p>
                                <x-badge color="brand" class="mt-2">{{ Auth::user()->role }}</x-badge>
                            </div>
                            <x-dropdown-link :href="route('profile.edit')" class="flex items-center gap-2">
                                <x-icon name="user-circle" :size="18" /> Perfil
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();"
                                        class="flex items-center gap-2 text-rose-600 hover:text-rose-700">
                                    <x-icon name="logout" :size="18" /> Cerrar sesión
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </header>

                <!-- Encabezado de página -->
                @isset($header)
                    <div class="border-b border-slate-200 bg-white px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                @endisset

                <!-- Contenido principal -->
                <main class="px-4 py-6 sm:px-6 lg:px-8">
                    <div class="mx-auto w-full {{ $ancho }} space-y-5">
                        @if (session('success'))
                            <x-alert tipo="success">{{ session('success') }}</x-alert>
                        @endif

                        @if (session('error'))
                            <x-alert tipo="error">{{ session('error') }}</x-alert>
                        @endif

                        @if ($errors->any())
                            <div x-data="{ open: true }" x-show="open" x-cloak
                                 class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
                                 role="alert">
                                <x-icon name="warning" :size="20" class="mt-0.5 shrink-0" />
                                <div class="flex-1">
                                    <p class="font-semibold">Corrija los siguientes errores:</p>
                                    <ul class="mt-1 list-disc space-y-0.5 ps-4">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <button type="button" @click="open = false" class="shrink-0 rounded p-1 hover:bg-rose-100" aria-label="Cerrar">
                                    <x-icon name="close" :size="16" />
                                </button>
                            </div>
                        @endif

                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
