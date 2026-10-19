import { normalizar } from './busqueda';

export default function registrarServidor(Alpine) {
    Alpine.data('panelServidor', ({ categorias, esServidor, maximo, total, plantilla, filas }) => ({
        categorias: (categorias ?? []).map(normalizar),
        esServidor: Boolean(esServidor),
        maximo: Number(maximo),
        total: Number(total),
        plantilla,
        filas,

        init() {
            const campo = document.getElementById('Categoria');

            if (! campo) {
                return;
            }

            const sincronizar = () => {
                this.esServidor = this.categorias.includes(normalizar(campo.value));
            };

            campo.addEventListener('input', sincronizar);
            campo.addEventListener('change', sincronizar);
            sincronizar();
        },

        get puedeAgregar() {
            return this.total < this.maximo;
        },

        agregar() {
            if (! this.puedeAgregar) {
                return;
            }

            const molde = document.querySelector(this.plantilla);
            const contenedor = document.querySelector(this.filas);

            if (! molde || ! contenedor) {
                return;
            }

            const fila = molde.content.firstElementChild.cloneNode(true);

            this.renombrar(fila, this.total);
            contenedor.appendChild(fila);
            this.total += 1;

            this.$nextTick(() => Alpine.initTree(fila));
        },

        quitar(evento) {
            const fila = evento.target.closest('[data-fila-tarjeta]');

            if (! fila) {
                return;
            }

            fila.remove();
            this.total = Math.max(0, this.total - 1);
            this.renumerar();
        },

        renumerar() {
            const contenedor = document.querySelector(this.filas);

            if (! contenedor) {
                return;
            }

            Array.from(contenedor.children).forEach((fila, indice) => this.renombrar(fila, indice));
        },

        renombrar(fila, indice) {
            fila.querySelectorAll('[name], [id], [for], [aria-controls]').forEach((elemento) => {
                ['name', 'id', 'for', 'aria-controls'].forEach((atributo) => {
                    const valor = elemento.getAttribute(atributo);

                    if (valor && valor.includes('tarjetas[')) {
                        elemento.setAttribute(atributo, valor.replace(/tarjetas\[[^\]]+\]/, `tarjetas[${indice}]`));
                    }
                });
            });
        },
    }));
}
