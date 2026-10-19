export function normalizar(texto) {
    return String(texto ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

export default function registrarBusqueda(Alpine) {
    Alpine.data('selectorBuscable', ({ opciones, seleccionado, placeholder, requerido }) => ({
        todas: (opciones ?? []).map((opcion) => ({
            value: String(opcion.value),
            label: opcion.label ?? '',
            meta: opcion.meta ?? '',
            buscar: normalizar(`${opcion.label ?? ''} ${opcion.meta ?? ''}`),
        })),
        placeholder: placeholder ?? 'Escriba para buscar…',
        requerido: Boolean(requerido),
        seleccionadoId: seleccionado ? String(seleccionado) : '',
        texto: '',
        abierto: false,
        activo: 0,

        init() {
            this.texto = this.etiquetaSeleccionada();
        },

        get filtradas() {
            const termino = normalizar(this.texto);

            if (termino === '') {
                return this.todas;
            }

            return this.todas.filter((opcion) => opcion.buscar.includes(termino));
        },

        etiquetaSeleccionada() {
            const opcion = this.todas.find((item) => item.value === this.seleccionadoId);

            return opcion ? opcion.label : '';
        },

        abrir() {
            if (this.abierto) {
                return;
            }

            this.abierto = true;
            this.texto = '';

            const indice = this.todas.findIndex((item) => item.value === this.seleccionadoId);
            this.activo = indice >= 0 ? indice : 0;

            this.acomodar();
        },

        cerrar() {
            if (! this.abierto) {
                return;
            }

            this.abierto = false;
            this.texto = this.etiquetaSeleccionada();
        },

        alEscribir() {
            this.seleccionadoId = '';
            this.abierto = true;
            this.activo = 0;
        },

        moverAbajo() {
            if (! this.abierto) {
                this.abrir();

                return;
            }

            this.activo = Math.min(this.filtradas.length - 1, this.activo + 1);
            this.acomodar();
        },

        moverArriba() {
            if (! this.abierto) {
                this.abrir();

                return;
            }

            this.activo = Math.max(0, this.activo - 1);
            this.acomodar();
        },

        confirmar(evento) {
            if (! this.abierto) {
                return;
            }

            evento.preventDefault();

            const opcion = this.filtradas[this.activo];

            if (opcion) {
                this.elegir(opcion);
            }
        },

        elegir(opcion) {
            this.seleccionadoId = opcion.value;
            this.texto = opcion.label;
            this.abierto = false;
        },

        limpiar() {
            this.seleccionadoId = '';
            this.texto = '';
            this.abierto = false;
        },

        acomodar() {
            this.$nextTick(() => {
                const contenedor = this.$refs.lista;

                if (! contenedor) {
                    return;
                }

                const activa = contenedor.querySelector('[data-activo="true"]');

                if (activa) {
                    activa.scrollIntoView({ block: 'nearest' });
                }
            });
        },
    }));
}
