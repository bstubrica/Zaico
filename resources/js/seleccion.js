export default function registrarSeleccion(Alpine) {
    Alpine.data('seleccionLote', ({ clave, total, pagina, mensaje, mensajeGrande }) => ({
        clave,
        total: Number(total),
        pagina: (Array.isArray(pagina) ? pagina : []).map(String),
        mensaje,
        mensajeGrande: mensajeGrande ?? null,
        umbralAviso: 100,
        seleccionados: [],
        todosFiltrados: false,

        init() {
            this.cargar();
            this.$watch('seleccionados', () => this.guardar());
            this.$watch('todosFiltrados', () => this.guardar());
        },

        cargar() {
            try {
                const datos = JSON.parse(sessionStorage.getItem(this.clave) ?? 'null');

                if (datos && Array.isArray(datos.ids)) {
                    this.seleccionados = datos.ids.map(String);
                    this.todosFiltrados = Boolean(datos.todos);
                }
            } catch (e) {
                this.seleccionados = [];
                this.todosFiltrados = false;
            }
        },

        guardar() {
            sessionStorage.setItem(this.clave, JSON.stringify({
                ids: this.seleccionados,
                todos: this.todosFiltrados,
            }));
        },

        descartar() {
            sessionStorage.removeItem(this.clave);
        },

        estaSeleccionado(id) {
            return this.todosFiltrados || this.seleccionados.includes(String(id));
        },

        alternar(id, marcado) {
            const texto = String(id);

            if (marcado) {
                if (! this.seleccionados.includes(texto)) {
                    this.seleccionados = [...this.seleccionados, texto];
                }

                return;
            }

            if (this.todosFiltrados) {
                this.todosFiltrados = false;
                this.seleccionados = this.pagina.filter((valor) => valor !== texto);

                return;
            }

            this.seleccionados = this.seleccionados.filter((valor) => valor !== texto);
        },

        todosVisibles() {
            return this.pagina.length > 0 && this.pagina.every((id) => this.estaSeleccionado(id));
        },

        alternarVisibles(marcado) {
            if (marcado) {
                const conjunto = new Set(this.seleccionados);
                this.pagina.forEach((id) => conjunto.add(id));
                this.seleccionados = [...conjunto];

                return;
            }

            const enPagina = new Set(this.pagina);
            this.seleccionados = this.seleccionados.filter((id) => ! enPagina.has(id));
            this.todosFiltrados = false;
        },

        seleccionarTodos() {
            this.todosFiltrados = true;
            this.seleccionados = [];
        },

        limpiar() {
            this.seleccionados = [];
            this.todosFiltrados = false;
            this.descartar();
        },

        cantidad() {
            return this.todosFiltrados ? this.total : this.seleccionados.length;
        },

        puedeSeleccionarTodos() {
            return this.total > this.pagina.length;
        },

        prepararEnvio(event) {
            const cantidad = this.cantidad();

            if (cantidad === 0) {
                event.preventDefault();

                return;
            }

            const aviso = cantidad >= this.umbralAviso && this.mensajeGrande
                ? this.mensajeGrande
                : this.mensaje;

            if (! window.confirm(aviso.replace('{n}', String(cantidad)))) {
                event.preventDefault();

                return;
            }

            this.sincronizar(event.target);
            this.descartar();
            this.seleccionados = [];
            this.todosFiltrados = false;
        },

        sincronizar(form) {
            const contenedor = form.querySelector('[data-seleccion-campos]');

            if (! contenedor) {
                return;
            }

            contenedor.innerHTML = '';

            if (this.todosFiltrados) {
                contenedor.appendChild(this.campoOculto('todos', '1'));

                return;
            }

            const vistos = new Set();

            this.seleccionados.forEach((id) => {
                if (vistos.has(id)) {
                    return;
                }

                vistos.add(id);
                contenedor.appendChild(this.campoOculto('ids[]', id));
            });
        },

        campoOculto(nombre, valor) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = nombre;
            input.value = valor;

            return input;
        },
    }));
}
