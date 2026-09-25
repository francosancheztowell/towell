import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Configurar CSRF token para Axios
const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

// Configurar SweetAlert2 global
import Swal from 'sweetalert2';
window.Swal = Swal;

// Cliente HTTP unificado y notificaciones (expuestos para scripts inline de Blade).
// Ver resources/js/utils/ (tipos en resources/js/types/global.d.ts). Reemplazan los
// fetch() crudos y los showToast() duplicados.
import http from './utils/http.ts';
import notify, { showToast } from './utils/notifications.ts';
import librerias from './utils/librerias.ts';
window.http = http;
window.notify = notify;
// showToast global unificado (firma estándar message, type) → toasts nativos vía notify.
window.showToast = showToast;

// Adaptador temporal: Toastr ya no existe; las vistas que aún lo llaman
// (Programa Tejido: balancear, repaso) caen en notify. Se retira en la fase 21.
const toastrANotify = (tipo) => (message) => notify[tipo](message);
window.toastr = {
    success: toastrANotify('success'),
    error: toastrANotify('error'),
    warning: toastrANotify('warning'),
    info: toastrANotify('info'),
    clear() {},
    options: {},
};

// Combobox (Tom Select) para scripts inline: el módulo y su CSS bajan solo cuando
// una vista lo usa. Los módulos de Vite importan utils/combobox.ts directo.
window.combobox = (select, opciones) =>
    import('./utils/combobox.ts').then(({ combobox }) => combobox(select, opciones));

// html2canvas y pdf.js bajo demanda (antes, <script> de cdnjs).
window.librerias = librerias;

// Puente Livewire → toast. Cualquier componente puede avisar sin JS propio:
//   $this->dispatch('aviso', tipo: 'success', texto: 'Guardado.');
document.addEventListener('livewire:init', () => {
    window.Livewire?.on('aviso', ({ tipo, texto }) => showToast(texto, tipo));
});
