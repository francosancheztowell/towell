import './bootstrap';
import '../css/app.css';

// Importar estilos CSS de librerías
import '@fortawesome/fontawesome-free/css/all.css';
import '../css/fontawesome-display.css';
import 'select2/dist/css/select2.css';
import 'toastr/build/toastr.css';

// SweetAlert2 — expuesto globalmente para vistas Blade
import Swal from 'sweetalert2';
window.Swal = Swal;
