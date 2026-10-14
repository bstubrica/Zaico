@php($estado = $estado ?? new \App\Models\EstadoActivo())

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <x-input-label for="Nombre" value="Nombre *" />
        <x-text-input id="Nombre" name="Nombre" type="text" class="mt-1"
                      :value="old('Nombre', $estado->Nombre)" required />
        <p class="mt-1 text-xs text-slate-500">Estado detallado, por ejemplo «Disponible &gt; Nuevo (deployable)».</p>
        <x-input-error :messages="$errors->get('Nombre')" />
    </div>

    <div>
        <x-input-label for="Grupo" value="Grupo *" />
        <select id="Grupo" name="Grupo" class="form-control mt-1" required>
            <option value="">— Seleccione un grupo —</option>
            @foreach (\App\Models\EstadoActivo::GRUPOS as $grupo)
                <option value="{{ $grupo }}" @selected(old('Grupo', $estado->Grupo) === $grupo)>
                    {{ $grupo }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Es el valor que se guarda en el activo para filtros y reportes.</p>
        <x-input-error :messages="$errors->get('Grupo')" />
    </div>

    <div class="flex flex-col justify-center gap-3 rounded-lg border border-slate-200 px-4 py-3">
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="Deployed" value="0">
            <input type="checkbox" name="Deployed" value="1"
                   @checked(old('Deployed', $estado->Deployed ?? false))
                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            Deployed (equipo desplegado)
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="Deployable" value="0">
            <input type="checkbox" name="Deployable" value="1"
                   @checked(old('Deployable', $estado->Deployable ?? false))
                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            Deployable (equipo desplegable)
        </label>
    </div>
</div>
