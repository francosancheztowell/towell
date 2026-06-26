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

// --- Shim de compatibilidad Select2 ↔ jQuery 4 ---
// Select2 4.x usa varias APIs que jQuery 4 ELIMINÓ. Sin esto, al invocar select2()
// el plugin no se registra y aparece "$(...).select2 is not a function".
// Restaurarlas ANTES de cargar/inicializar Select2.
if (typeof $.fn.bind !== 'function') {
    $.fn.bind = function (types, data, fn) { return this.on(types, null, data, fn); };
    $.fn.unbind = function (types, fn) { return this.off(types, null, fn); };
    $.fn.delegate = function (selector, types, data, fn) { return this.on(types, selector, data, fn); };
    $.fn.undelegate = function (selector, types, fn) {
        return arguments.length === 1
            ? this.off(selector, '**')
            : this.off(types, selector || '**', fn);
    };
}
if (typeof $.trim !== 'function') {
    $.trim = (text) => (text == null ? '' : String(text).trim());
}
if (typeof $.isFunction !== 'function') {
    $.isFunction = (obj) => typeof obj === 'function';
}
if (typeof $.isArray !== 'function') {
    $.isArray = Array.isArray;
}
if (typeof $.camelCase !== 'function') {
    $.camelCase = (str) => str.replace(/-([a-z])/g, (_, l) => l.toUpperCase());
}
if (typeof $.type !== 'function') {
    const class2type = {};
    'Boolean Number String Function Array Date RegExp Object Error Symbol'
        .split(' ').forEach((name) => { class2type['[object ' + name + ']'] = name.toLowerCase(); });
    $.type = (obj) => {
        if (obj == null) return obj + '';
        return (typeof obj === 'object' || typeof obj === 'function')
            ? (class2type[Object.prototype.toString.call(obj)] || 'object')
            : typeof obj;
    };
}

// Configurar SweetAlert2 global
import Swal from 'sweetalert2';
window.Swal = Swal;

// Configurar Select2 global.
// El paquete exporta una función factory (module.exports = function(root, jQuery){...})
// que DEBE invocarse para registrar $.fn.select2; un `import 'select2'` solo no basta.
import select2 from 'select2';
select2();

// Configurar Toastr global
import toastr from 'toastr';
window.toastr = toastr;

// Chart.js (reportes con canvas en Blade, p. ej. engomado / promedio paros)
import Chart from 'chart.js/auto';
window.Chart = Chart;

// Cliente HTTP unificado y notificaciones (expuestos para scripts inline de Blade).
// Ver resources/js/utils/. Reemplazan los fetch() crudos y los showToast() duplicados.
import http from './utils/http';
import notify, { showToast } from './utils/notifications';
window.http = http;
window.notify = notify;
// showToast global unificado (firma estándar message, type) → toastr vía notify.
window.showToast = showToast;
