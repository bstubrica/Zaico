@php
    $secciones = [
        [
            'titulo' => 'General',
            'enlaces' => [
                ['ruta' => 'dashboard', 'patron' => 'dashboard', 'icono' => 'home', 'texto' => 'Dashboard'],
            ],
        ],
        [
            'titulo' => 'Inventario',
            'enlaces' => [
                ['ruta' => 'activos.index', 'patron' => ['activos.index', 'activos.show', 'activos.create', 'activos.edit'], 'icono' => 'cube', 'texto' => 'Equipos'],
                ['ruta' => 'desincorporados.index', 'patron' => 'desincorporados.*', 'icono' => 'inbox', 'texto' => 'Desincorporados'],
                ['ruta' => 'activos.buscar', 'patron' => 'activos.buscar', 'icono' => 'search', 'texto' => 'Búsqueda rápida'],
                ['ruta' => 'personal.index', 'patron' => 'personal.*', 'icono' => 'users', 'texto' => 'Personal'],
                ['ruta' => 'estados.index', 'patron' => 'estados.*', 'icono' => 'tag', 'texto' => 'Estados'],
                ['ruta' => 'ubicaciones.index', 'patron' => 'ubicaciones.*', 'icono' => 'map-pin', 'texto' => 'Ubicaciones'],
                ['ruta' => 'modelos.index', 'patron' => 'modelos.*', 'icono' => 'cpu-chip', 'texto' => 'Modelos'],
            ],
        ],
        [
            'titulo' => 'Operaciones',
            'enlaces' => [
                ['ruta' => 'mantenimientos.index', 'patron' => 'mantenimientos.*', 'icono' => 'wrench', 'texto' => 'Mantenimientos'],
                ['ruta' => 'historial.index', 'patron' => 'historial.*', 'icono' => 'clock', 'texto' => 'Historial'],
            ],
        ],
    ];
@endphp

<aside x-cloak
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 start-0 z-50 flex w-64 flex-col bg-slate-900 transition-transform duration-200 ease-out lg:translate-x-0">
    <!-- Marca -->
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm">
            <x-icon name="cube" :size="22" />
        </span>
        <div class="leading-tight">
            <p class="text-sm font-bold text-white">Zaico</p>
            <p class="text-xs text-slate-400">Inventario TI</p>
        </div>
        <button type="button" @click="sidebarOpen = false"
                class="ms-auto rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden"
                aria-label="Cerrar menú">
            <x-icon name="close" :size="20" />
        </button>
    </div>

    <!-- Navegación -->
    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        @foreach ($secciones as $seccion)
            <div>
                <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $seccion['titulo'] }}</p>
                <div class="space-y-1">
                    @foreach ($seccion['enlaces'] as $enlace)
                        <a href="{{ route($enlace['ruta']) }}"
                           class="sidebar-link {{ request()->routeIs($enlace['patron']) ? 'sidebar-link-active' : '' }}">
                            <x-icon :name="$enlace['icono']" :size="20" />
                            <span>{{ $enlace['texto'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if (auth()->user()->puedeEscribir())
            @php $noLeidas = auth()->user()->unreadNotifications->count(); @endphp
            <div>
                <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Atención</p>
                <div class="space-y-1">
                    <a href="{{ route('alertas.index') }}"
                       class="sidebar-link {{ request()->routeIs('alertas.*') ? 'sidebar-link-active' : '' }}">
                        <x-icon name="bell" :size="20" />
                        <span>Alertas</span>
                        @if ($noLeidas > 0)
                            <span class="ms-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-rose-500 px-1.5 text-xs font-semibold text-white">
                                {{ min($noLeidas, 99) }}
                            </span>
                        @endif
                    </a>
                </div>
            </div>

            <div>
                <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Datos</p>
                <div class="space-y-1">
                    <a href="{{ route('importaciones.index') }}"
                       class="sidebar-link {{ request()->routeIs('importaciones.*') ? 'sidebar-link-active' : '' }}">
                        <x-icon name="upload" :size="20" />
                        <span>Importación CSV</span>
                    </a>
                </div>
            </div>
        @endif

        @if (auth()->user()->isAdmin())
            <div>
                <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Sistema</p>
                <div class="space-y-1">
                    <a href="{{ route('admin.index') }}"
                       class="sidebar-link {{ request()->routeIs('admin.*') ? 'sidebar-link-active' : '' }}">
                        <x-icon name="shield" :size="20" />
                        <span>Administración</span>
                    </a>
                </div>
            </div>
        @endif
    </nav>

    <!-- Usuario -->
    <div class="shrink-0 border-t border-white/10 p-3">
        <div class="flex items-center gap-3 rounded-lg px-2 py-2">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
                {{ \Illuminate\Support\Str::of(Auth::user()->name)->explode(' ')->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))->take(2)->implode('') }}
            </span>
            <div class="min-w-0 flex-1 leading-tight">
                <p class="truncate text-sm font-semibold text-white">{{ Auth::user()->name }}</p>
                <p class="truncate text-xs text-slate-400">{{ Auth::user()->role }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white" title="Cerrar sesión" aria-label="Cerrar sesión">
                    <x-icon name="logout" :size="20" />
                </button>
            </form>
        </div>
    </div>
</aside>
