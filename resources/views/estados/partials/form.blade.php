@php($estado = $estado ?? new \App\Models\EstadoActivo())
@php($grupos = \App\Models\EstadoActivo::query()->orderBy('Grupo')->distinct()->pluck('Grupo'))

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="Nombre" value="Nombre *" />
        <x-text-input id="Nombre" name="Nombre" type="text" class="mt-1"
                      :value="old('Nombre', $estado->Nombre)" placeholder="Ej.: En préstamo" required maxlength="60" />
        <x-input-error :messages="$errors->get('Nombre')" />
    </div>

    <div>
        <x-input-label for="Grupo" value="Grupo *" />
        <x-text-input id="Grupo" name="Grupo" type="text" class="mt-1"
                      :value="old('Grupo', $estado->Grupo)" placeholder="Ej.: Asignado" required maxlength="30"
                      list="grupos-estado" />
        <datalist id="grupos-estado">
            @foreach ($grupos as $grupo)
                <option value="{{ $grupo }}"></option>
            @endforeach
        </datalist>
        <x-input-error :messages="$errors->get('Grupo')" />
    </div>

    <div class="md:col-span-2">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <label class="form-control flex cursor-pointer items-center gap-2">
                <input type="checkbox" name="Deployed" value="1"
                       @checked(old('Deployed', $estado->Deployed)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-700">Deployed</span>
            </label>
            <label class="form-control flex cursor-pointer items-center gap-2">
                <input type="checkbox" name="Deployable" value="1"
                       @checked(old('Deployable', $estado->Deployable)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-700">Deployable</span>
            </label>
            <label class="form-control flex cursor-pointer items-center gap-2">
                <input type="checkbox" name="Estado" value="1"
                       @checked(old('Estado', $estado->Estado ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-700">Activo</span>
            </label>
        </div>
        <p class="mt-1 text-xs text-slate-500">Un estado inactivo deja de mostrarse en el catálogo y en los formularios.</p>
        <x-input-error :messages="$errors->get('Deployed')" />
        <x-input-error :messages="$errors->get('Deployable')" />
        <x-input-error :messages="$errors->get('Estado')" />
    </div>
</div>