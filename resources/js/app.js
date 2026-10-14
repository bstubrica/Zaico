import Alpine from 'alpinejs';

import registrarBusqueda from './busqueda';
import registrarSeleccion from './seleccion';

window.Alpine = Alpine;

registrarSeleccion(Alpine);
registrarBusqueda(Alpine);

Alpine.start();
