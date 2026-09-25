/**
 * Runtime de los componentes Blade (fase 16). Se importa una vez desde app.js.
 * Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md
 */
import { abrir, cerrarPorId, escucharDocumento, iniciarModales } from './dialog.ts';
import { iniciarAutocierre } from './dismiss.ts';
import { iniciarFiltros, refrescarFiltros } from './filter-bar.ts';
import { loader } from './loader.ts';

declare global {
    interface Window {
        loader: typeof loader;
        uiModal: { abrir: typeof abrir; cerrar: typeof cerrarPorId };
        uiFiltros: { refrescar: typeof refrescarFiltros };
    }
}

window.loader = loader;
window.uiModal = { abrir, cerrar: cerrarPorId };
window.uiFiltros = { refrescar: refrescarFiltros };

function iniciar(): void {
    iniciarModales();
    iniciarAutocierre();
    iniciarFiltros();
}

escucharDocumento();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar, { once: true });
} else {
    iniciar();
}
document.addEventListener('livewire:navigated', iniciar);
