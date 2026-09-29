/**
 * Producción Engomado (/engomado/modulo-produccion-engomado) — entrada Vite (19-01, unidad E).
 * Vista: resources/views/modulos/engomado/modulo-produccion-engomado.blade.php
 *
 *  - contexto.ts    configuración (data-pagina), guardas y Kg. Neto de la fila
 *  - filas.ts       julios, "Listo" y eventos de la tabla
 *  - guardado.ts    guardado campo por campo (fila y merma de la orden)
 *  - temperaturas.ts selector de Canoa 1/2 y Tambor
 *  - oficiales.ts   celda de oficiales, modal y propagación
 *  - validacion.ts  campos faltantes (Listo / Finalizar)
 *  - formulacion.ts modal "Nueva Formulación"
 *  - finalizar.ts   imprimir parcial y finalizar
 *  - logica.ts      reglas puras (tests node)
 */
import { delegate, onReady } from '../../../utils/dom.ts';
import { iniciarConfig } from './contexto.ts';
import { iniciarFilas } from './filas.ts';
import { toggleQuantityEdit } from './temperaturas.ts';
import { iniciarOficiales } from './oficiales.ts';
import { abrirModalFormulacion, cargarDatosPrograma, cerrarModalFormulacion } from './formulacion.ts';
import { finalizar, imprimirProduccionParcial } from './finalizar.ts';

const acciones: Record<string, (el: HTMLElement, ev: Event) => void> = {
    finalizar: () => void finalizar(),
    'imprimir-parcial': () => void imprimirProduccionParcial(),
    ir: (el) => {
        if (el.dataset.url) window.location.assign(el.dataset.url);
    },
    'editar-cantidad': (el) => toggleQuantityEdit(el),
    'abrir-formulacion': () => abrirModalFormulacion(),
    'cerrar-formulacion': () => cerrarModalFormulacion(),
};

onReady(() => {
    const raiz = document.getElementById('produccion-engomado');
    if (!raiz || !iniciarConfig(raiz)) return;

    // Los botones de la barra superior (navbar-right) están fuera de la raíz: se delega en document.
    delegate<HTMLElement>(document, 'click', '[data-accion]', (ev, el) => acciones[el.dataset.accion ?? '']?.(el, ev));
    delegate<HTMLSelectElement>(document, 'change', '[data-accion-cambio="folio-formulacion"]', (_ev, sel) => cargarDatosPrograma(sel));

    iniciarFilas();
    iniciarOficiales();
});
