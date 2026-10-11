<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Editar mantenimiento"
                       :subtitle="($mantenimiento->activo?->Etiqueta_activo ?? 'Equipo') . ' · ' . ($mantenimiento->Fecha_mantenimiento?->format('d/m/Y') ?? '')"
                       icon="wrench">
            <x-slot name="actions">
                <a href="{{ route('activos.show', $mantenimiento->fk_activo) }}" class="btn btn-ghost">
                    <x-icon name="arrow-left" :size="18" /> Volver al equipo
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="POST" action="{{ route('mantenimientos.update', $mantenimiento) }}"
          enctype="multipart/form-data" class="mx-auto max-w-3xl">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Datos del mantenimiento</h3>
            </div>
            <div class="card-body space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="Tipo" value="Tipo *" />
                        <select id="Tipo" name="Tipo" class="form-control mt-1" required>
                            @foreach (['Preventivo', 'Correctivo', 'Otro'] as $tipo)
                                <option value="{{ $tipo }}" @selected(old('Tipo', $mantenimiento->Tipo) === $tipo)>{{ $tipo }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('Tipo')" />
                    </div>

                    <div>
                        <x-input-label for="Fecha_mantenimiento" value="Fecha *" />
                        <x-text-input id="Fecha_mantenimiento" name="Fecha_mantenimiento" type="date" class="mt-1"
                                      :value="old('Fecha_mantenimiento', $mantenimiento->Fecha_mantenimiento?->format('Y-m-d'))" required />
                        <x-input-error :messages="$errors->get('Fecha_mantenimiento')" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="Descripcion" value="Descripción *" />
                        <textarea id="Descripcion" name="Descripcion" rows="4" class="form-control mt-1" required>{{ old('Descripcion', $mantenimiento->Descripcion) }}</textarea>
                        <x-input-error :messages="$errors->get('Descripcion')" />
                    </div>
                </div>

                @if ($mantenimiento->evidencias->isNotEmpty())
                    <div>
                        <x-input-label value="Evidencias actuales" />
                        <ul class="mt-1 space-y-2">
                            @foreach ($mantenimiento->evidencias as $evidencia)
                                <li class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
                                    <input type="checkbox" id="del-ev-{{ $evidencia->id }}"
                                           name="evidencias_eliminar[]" value="{{ $evidencia->id }}"
                                           class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                    <label for="del-ev-{{ $evidencia->id }}" class="flex-1 text-sm text-slate-700">
                                        {{ $evidencia->Nombre_original }}
                                        <span class="text-xs text-slate-400">· marcar para eliminar</span>
                                    </label>
                                    <a href="{{ route('mantenimientos.evidencia', $evidencia) }}" class="link text-sm">Ver</a>
                                </li>
                            @endforeach
                        </ul>
                        <x-input-error :messages="$errors->get('evidencias_eliminar')" />
                    </div>
                @endif

                <div>
                    <x-input-label for="evidencias" value="Agregar evidencias" />
                    <input type="file" id="evidencias" name="evidencias[]" multiple
                           accept=".jpg,.jpeg,.png,.pdf,.mp4" class="form-control mt-1">
                    <p class="mt-1 text-xs text-slate-500">JPG, PNG, PDF o MP4 · máximo 20 MB por archivo.</p>
                    <x-input-error :messages="$errors->get('evidencias')" />
                    @foreach ($errors->get('evidencias.*') as $mensajes)
                        @foreach ($mensajes as $mensaje)
                            <p class="mt-1 text-sm text-rose-600">{{ $mensaje }}</p>
                        @endforeach
                    @endforeach
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <a href="{{ route('activos.show', $mantenimiento->fk_activo) }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <x-icon name="check-circle" :size="18" /> Actualizar
                </button>
            </div>
        </div>
    </form>
</x-app-layout>
