import './bootstrap';
import './monitoreo/telemetria';

// Importar estilos CSS de librerías
// Solo el core + la familia `solid`. `all.css` arrastraba tambien regular, brands
// y los shims de v4: ~25 KB de CSS y 129 KB de webfont para 4 iconos en todo el repo.
import '@fortawesome/fontawesome-free/css/fontawesome.css';
import '@fortawesome/fontawesome-free/css/solid.css';
import '../css/fontawesome-display.css';
