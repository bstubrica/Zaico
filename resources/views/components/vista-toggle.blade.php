<div class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-100 p-0.5" role="group" aria-label="Formato de vista">
    <button type="button" @click="setVista('tabla')"
            :class="vista === 'tabla' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            :aria-pressed="vista === 'tabla' ? 'true' : 'false'"
            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold transition"
            title="Ver como tabla">
        <x-icon name="table-cells" :size="16" />
        <span class="hidden sm:inline">Tabla</span>
    </button>
    <button type="button" @click="setVista('tarjeta')"
            :class="vista === 'tarjeta' ? 'bg-white text-brand-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            :aria-pressed="vista === 'tarjeta' ? 'true' : 'false'"
            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-semibold transition"
            title="Ver como tarjetas">
        <x-icon name="squares-2x2" :size="16" />
        <span class="hidden sm:inline">Tarjeta</span>
    </button>
</div>