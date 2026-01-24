import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Configurar CSRF token para Axios
const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

// Configurar jQuery global
import $ from 'jquery';
window.$ = window.jQuery = $;

// Configurar SweetAlert2 global
import Swal from 'sweetalert2';
window.Swal = Swal;

// Configurar Select2 global
import 'select2';

// Configurar Toastr global
import toastr from 'toastr';
window.toastr = toastr;

// Configurar Chart.js global
import { Chart, registerables } from 'chart.js/auto';
Chart.register(...registerables);
window.Chart = Chart;

// Configurar SortableJS global
import Sortable from 'sortablejs';
window.Sortable = Sortable;
