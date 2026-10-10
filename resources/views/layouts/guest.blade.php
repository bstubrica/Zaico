<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Zaico') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans">
        <div class="grid min-h-screen lg:grid-cols-2">
            {{-- Panel de marca --}}
            <div class="relative hidden overflow-hidden bg-slate-900 lg:flex lg:flex-col lg:justify-between lg:p-12">
                <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-600/30 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-brand-500/20 blur-3xl"></div>

                <div class="relative flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-white shadow-lg">
                        <x-icon name="cube" :size="26" />
                    </span>
                    <div class="leading-tight">
                        <p class="text-lg font-bold text-white">Zaico</p>
                        <p class="text-sm text-slate-400">Inventario TI</p>
                    </div>
                </div>

                <div class="relative">
                    <h1 class="text-3xl font-bold leading-tight text-white">
                        Gestiona el inventario de equipos de forma simple y trazable.
                    </h1>
                    <p class="mt-4 max-w-md text-slate-300">
                        Controla activos, asignaciones, mantenimientos y auditoría desde una sola plataforma.
                    </p>

                    <ul class="mt-8 space-y-3 text-sm text-slate-300">
                        <li class="flex items-center gap-3">
                            <x-icon name="check-circle" :size="20" class="text-emerald-400" /> Registro y trazabilidad de equipos
                        </li>
                        <li class="flex items-center gap-3">
                            <x-icon name="check-circle" :size="20" class="text-emerald-400" /> Asignación y devolución con historial
                        </li>
                        <li class="flex items-center gap-3">
                            <x-icon name="check-circle" :size="20" class="text-emerald-400" /> Mantenimientos con evidencias
                        </li>
                    </ul>
                </div>

                <p class="relative text-xs text-slate-500">© {{ date('Y') }} Zaico · Inventario de activos de TI</p>
            </div>

            {{-- Formulario --}}
            <div class="flex flex-col justify-center px-4 py-10 sm:px-8">
                <div class="mx-auto w-full max-w-md">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm">
                            <x-icon name="cube" :size="22" />
                        </span>
                        <div class="leading-tight">
                            <p class="text-base font-bold text-slate-900">Zaico</p>
                            <p class="text-xs text-slate-500">Inventario TI</p>
                        </div>
                    </div>

                    <div class="card card-body">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
