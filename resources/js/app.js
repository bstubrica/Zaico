import Alpine from 'alpinejs';

import registrarBusqueda from './busqueda';
import registrarSeleccion from './seleccion';
import registrarServidor from './servidor';
import registrarVista from './vista';

window.Alpine = Alpine;

registrarSeleccion(Alpine);
registrarBusqueda(Alpine);
registrarServidor(Alpine);
registrarVista(Alpine);

Alpine.start();
