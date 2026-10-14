export default function registrarVista(Alpine) {
    Alpine.data('vistaListado', ({ clave, porDefecto = 'tabla' }) => ({
        vista: porDefecto,

        init() {
            const guardada = this.leer();

            if (guardada === 'tabla' || guardada === 'tarjeta') {
                this.vista = guardada;
            }
        },

        leer() {
            try {
                return localStorage.getItem(clave);
            } catch {
                return null;
            }
        },

        setVista(vista) {
            this.vista = vista;

            try {
                localStorage.setItem(clave, vista);
            } catch {
                // Sin persistencia disponible.
            }
        },
    }));
}