@php
    $activo = $activo ?? new \App\Models\Activo();

    $ubicacionPredet = old('Ubicacion_Predeterminada', $activo->Ubicacion_Predeterminada);
    $opcionesUbicacionPredet = $ubicaciones->map(fn ($ubicacion) => [
        'value' => $ubicacion->Nombre,
        'label' => $ubicacion->Nombre,
        'meta' => $ubicacion->Descripcion ?? '',
    ])->values()->all();

    if (filled($ubicacionPredet) && ! in_array($ubicacionPredet, array_column($opcionesUbicacionPredet, 'value'), true)) {
        $opcionesUbicacionPredet[] = ['value' => $ubicacionPredet, 'label' => $ubicacionPredet, 'meta' => 'Valor actual'];
    }
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <x-input-label for="Nombre_de_activo" value="Nombre del activo *" />
        <x-text-input id="Nombre_de_activo" name="Nombre_de_activo" type="text" class="mt-1"
                      :value="old('Nombre_de_activo', $activo->Nombre_de_activo)" required />
        <x-input-error :messages="$errors->get('Nombre_de_activo')" />
    </div>

    <div>
        <x-input-label for="Etiqueta_activo" value="Etiqueta *" />
        <x-text-input id="Etiqueta_activo" name="Etiqueta_activo" type="text" class="mt-1"
                      :value="old('Etiqueta_activo', $activo->Etiqueta_activo)" required />
        <x-input-error :messages="$errors->get('Etiqueta_activo')" />
    </div>

    <div>
        <x-input-label for="Serial" value="Serial" />
        <x-text-input id="Serial" name="Serial" type="text" class="mt-1"
                      :value="old('Serial', $activo->Serial)" />
        <x-input-error :messages="$errors->get('Serial')" />
    </div>

    <div>
        <x-input-label for="Categoria" value="Categoría" />
        <x-text-input id="Categoria" name="Categoria" type="text" class="mt-1"
                      :value="old('Categoria', $activo->Categoria)" />
        <x-input-error :messages="$errors->get('Categoria')" />
    </div>

    <div>
        <x-input-label for="Fabricante" value="Fabricante" />
        <x-text-input id="Fabricante" name="Fabricante" type="text" class="mt-1"
                      :value="old('Fabricante', $activo->Fabricante)" />
        <x-input-error :messages="$errors->get('Fabricante')" />
    </div>

    <div>
        <x-input-label for="fk_modelo" value="Modelo (catálogo)" />
        <x-search-select
            name="fk_modelo"
            class="mt-1"
            placeholder="Escriba para buscar…"
            :selected="$activo->fk_modelo"
            :options="$modelos->map(fn ($modelo) => [
                'value' => $modelo->id,
                'label' => $modelo->Nombre,
                'meta' => $modelo->Fabricante ?? '',
            ])->values()->all()" />
        <x-input-error :messages="$errors->get('fk_modelo')" />
    </div>

    <div>
        <x-input-label for="Direccion_MAC" value="Dirección MAC" />
        <x-text-input id="Direccion_MAC" name="Direccion_MAC" type="text" class="mt-1"
                      :value="old('Direccion_MAC', $activo->Direccion_MAC)" placeholder="00:11:22:33:44:55" />
        <x-input-error :messages="$errors->get('Direccion_MAC')" />
    </div>

    <div>
        <x-input-label for="fk_ubicacion" value="Ubicación (catálogo)" />
        <x-search-select
            name="fk_ubicacion"
            class="mt-1"
            placeholder="Escriba para buscar…"
            :selected="$activo->fk_ubicacion"
            :options="$ubicaciones->map(fn ($ubicacion) => [
                'value' => $ubicacion->id,
                'label' => $ubicacion->Nombre,
                'meta' => $ubicacion->Descripcion ?? '',
            ])->values()->all()" />
        <x-input-error :messages="$errors->get('fk_ubicacion')" />
    </div>

    <div>
        <x-input-label for="Ubicacion_Predeterminada" value="Ubicación predeterminada" />
        <x-search-select
            name="Ubicacion_Predeterminada"
            class="mt-1"
            placeholder="Escriba para buscar…"
            :selected="$ubicacionPredet"
            :options="$opcionesUbicacionPredet" />
        <x-input-error :messages="$errors->get('Ubicacion_Predeterminada')" />
    </div>

    <div>
        <x-input-label for="Estado" value="Estado (grupo) *" />
        <select id="Estado" name="Estado" class="form-control mt-1">
            @foreach ($grupos as $grupo)
                <option value="{{ $grupo }}" @selected(old('Estado', $activo->Estado) === $grupo)>{{ $grupo }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('Estado')" />
    </div>

    <div>
        <x-input-label for="fk_estado" value="Estado de catálogo (detalle)" />
        <x-search-select
            name="fk_estado"
            class="mt-1"
            placeholder="Escriba para buscar…"
            :selected="$activo->fk_estado"
            :options="$estados->map(fn ($estado) => [
                'value' => $estado->id,
                'label' => $estado->Nombre,
                'meta' => $estado->Grupo,
            ])->values()->all()" />
        <x-input-error :messages="$errors->get('fk_estado')" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="Observaciones" value="Observaciones" />
        <textarea id="Observaciones" name="Observaciones" rows="3" class="form-control mt-1">{{ old('Observaciones', $activo->Observaciones) }}</textarea>
        <x-input-error :messages="$errors->get('Observaciones')" />
    </div>

    <div class="md:col-span-2">
        <x-input-label for="imagen" value="Imagen del activo (JPG, PNG o WEBP · máx. 10 MB)" />
        @if ($activo->Imagen_Activo)
            <img src="{{ route('activos.imagen', $activo) }}" alt="Imagen del activo"
                 class="mt-2 h-32 w-auto rounded-md border border-slate-200 object-cover">
        @endif
        <input type="file" id="imagen" name="imagen" accept=".jpg,.jpeg,.png,.webp"
               class="mt-1 block w-full text-sm text-slate-600 file:me-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200">
        <x-input-error :messages="$errors->get('imagen')" />
    </div>
</div>
