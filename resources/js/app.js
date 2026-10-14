import Alpine from 'alpinejs';

import registrarBusqueda from './busqueda';
import registrarSeleccion from './seleccion';
import registrarVista from './vista';

window.Alpine = Alpine;

registrarSeleccion(Alpine);
registrarBusqueda(Alpine);
registrarVista(Alpine);

Alpine.start();
