/**
 * Runtime de los componentes Blade (fase 16) y del UX global (fase 17-02). Se importa una vez
 * desde app.js. Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md
 */
import { abrir, cerrarPorId, escucharDocumento, iniciarModales } from './dialog.ts';
import { iniciarAutocierre } from './dismiss.ts';
import { iniciarFiltros, refrescarFiltros } from './filter-bar.ts';
import { loader } from './loader.ts';
import { iniciarConexion, pintarConexion } from './conexion.ts';
import { mostrarModalDiasLiberar } from './dias-liberar.ts';
import { onReady } from '../utils/dom.ts';
import { escucharSesionLivewire } from '../utils/sesion.ts';
import { accionesTactilesApi, type AccionesTactiles } from '../utils/acciones-tactiles.ts';

declare global {
    interface Window {
        loader: typeof loader;
        uiModal: { abrir: typeof abrir; cerrar: typeof cerrarPorId };
        uiFiltros: { refrescar: typeof refrescarFiltros };
        accionesTactiles: AccionesTactiles;
        mostrarModalDiasLiberar: typeof mostrarModalDiasLiberar;
    }
}

window.loader = loader;
window.uiModal = { abrir, cerrar: cerrarPorId };
window.uiFiltros = { refrescar: refrescarFiltros };
// UX-06: helper de acciones sin clic derecho, para que cada módulo lo adopte en su 19-xx.
window.accionesTactiles = accionesTactilesApi;
// Puente: lo llama el onclick del botón Liberar de navbar/sections/programa-tejido.blade.php.
window.mostrarModalDiasLiberar = mostrarModalDiasLiberar;

function iniciar(): void {
    iniciarModales();
    iniciarAutocierre();
    iniciarFiltros();
    pintarConexion();
}

escucharDocumento();
iniciarConexion();

// UX-11: 419/401 de Livewire → mismo aviso y recarga que window.http (sin el confirm en inglés).
if (window.Livewire) {
    escucharSesionLivewire(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => window.Livewire && escucharSesionLivewire(window.Livewire), { once: true });
}

onReady(iniciar);
document.addEventListener('livewire:navigated', iniciar);
