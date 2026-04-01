import './bootstrap';
import '../css/app.css';

// Programa Tejido — filter engine (expuesto para Blade)
import { checkFilterMatch, groupFiltersByColumn, rowMatchesCustomFilters, dateInRange } from './programa-tejido/filter-engine.js';
window.PTFilterEngine = { checkFilterMatch, groupFiltersByColumn, rowMatchesCustomFilters, dateInRange };

// Importar estilos CSS de librerías
import '@fortawesome/fontawesome-free/css/all.css';
import '../css/fontawesome-display.css';
import 'select2/dist/css/select2.css';
import 'toastr/build/toastr.css';
