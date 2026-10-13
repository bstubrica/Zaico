<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Administración" subtitle="Operaciones avanzadas sobre los datos del sistema" icon="shield" />
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-5">
        <x-alert tipo="warning">
            Esta sección contiene operaciones <strong>destructivas e irreversibles</strong>.
            Úsela solo si comprende su alcance.
        </x-alert>

        @php $total = collect($resumen)->sum('registros'); @endphp

        <div class="card">
            <div class="card-header">
                <div>
                    <p class="card-title">Datos de inventario</p>
                    <p class="mt-0.5 text-xs text-slate-500">Registros que serán eliminados.</p>
                </div>
                <span class="badge bg-slate-100 text-slate-700">{{ number_format($total) }} registros</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tabla</th>
                            <th class="text-right">Registros</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumen as $fila)
                            <tr>
                                <td>
                                    <span class="font-medium text-slate-900">{{ $fila['etiqueta'] }}</span>
                                    <span class="ml-1 font-mono text-xs text-slate-400">{{ $fila['tabla'] }}</span>
                                </td>
                                <td class="text-right tabular-nums">{{ number_format($fila['registros']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-500">
                Se conservan las cuentas de usuario, el catálogo de estados y la estructura de la base de datos.
                También se eliminan los archivos de evidencias e importaciones temporales.
            </div>
        </div>

        <div class="card border-rose-200">
            <div class="card-header border-rose-200 bg-rose-50">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-600 text-white">
                        <x-icon name="trash" :size="20" />
                    </span>
                    <div>
                        <p class="card-title text-rose-800">Zona de peligro</p>
                        <p class="mt-0.5 text-xs text-rose-700">Depuración total del inventario</p>
                    </div>
                </div>
            </div>
            <div class="card-body space-y-4">
                @if (! $configurado)
                    <x-alert tipo="error">
                        La contraseña de depuración no está configurada. Defina
                        <code class="font-mono">ZAICO_DEPURACION_PASSWORD_HASH</code> en el servidor
                        para habilitar esta operación.
                    </x-alert>
                @elseif (! $habilitado)
                    <x-alert tipo="error">
                        La depuración está <strong>deshabilitada</strong> en este entorno
                        (producción). Habilítela con <code class="font-mono">ZAICO_DEPURACION_HABILITADO=true</code>
                        de forma consciente y temporal.
                    </x-alert>
                @endif

                <p class="text-sm text-slate-600">
                    Se eliminarán <strong>{{ number_format($total) }} registros</strong> de inventario de forma
                    permanente. Confirme escribiendo la frase exacta y su contraseña de depuración.
                </p>

                <form method="POST" action="{{ route('admin.depurar') }}" class="space-y-4"
                      onsubmit="return confirm('¿Confirma el borrado definitivo de todos los datos de inventario? Esta acción no se puede deshacer.');">
                    @csrf

                    <div>
                        <label for="frase" class="form-label">Frase de confirmación</label>
                        <p class="form-hint mb-1">
                            Escriba exactamente: <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-700">{{ $frase }}</code>
                        </p>
                        <input id="frase" name="frase" type="text" required autocomplete="off" spellcheck="false"
                               class="form-control font-mono" placeholder="{{ $frase }}"
                               @disabled(! $configurado || ! $habilitado)>
                    </div>

                    <div>
                        <label for="password" class="form-label">Contraseña de depuración</label>
                        <input id="password" name="password" type="password" required autocomplete="off"
                               class="form-control" placeholder="••••••••"
                               @disabled(! $configurado || ! $habilitado)>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-danger" @disabled(! $configurado || ! $habilitado)>
                            <x-icon name="trash" :size="18" />
                            Borrar todos los datos de inventario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
