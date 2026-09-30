/**
 * Captura de Fórmula (Engomado), entrada Vite. Vista: resources/views/modulos/engomado/captura-formula/index.blade.php
 * Solo cablea: config de data-pagina, delegación de data-accion y arranque de cada parte.
 *  - modal-formula.ts  selección de fila, Crear / Editar / Ver, eliminar
 *  - componentes.ts    tabla de componentes del modal
 *  - filtros.ts        filtro por columna (clic derecho / pulsación larga) y orden por Fecha
 *  - calidad.ts        modal de Calidad (PUT JSON)
 *  - logica.ts         funciones puras (tests/Js/urdeng-formula.test.mjs)
 */
import { delegate, onReady } from '../../../utils/dom.ts';
import { leerDatos } from '../../urdido/comun/pagina.ts';
import { estado, type ConfigFormula } from './estado.ts';
import { agregarFila, iniciarTablaComponentes } from './componentes.ts';
import { abrirCalidad, iniciarCalidad } from './calidad.ts';
import { iniciarFiltros } from './filtros.ts';
import {
    abrirExistente,
    abrirNueva,
    actualizarBotonesAccion,
    cerrarModal,
    confirmarEliminar,
    iniciarModalFormula,
    seleccionarFila,
} from './modal-formula.ts';

const acciones: Record<string, (boton: HTMLElement) => void> = {
    nueva: () => abrirNueva(),
    editar: () => void abrirExistente('editar'),
    ver: () => void abrirExistente('ver'),
    eliminar: () => void confirmarEliminar(),
    'cerrar-modal': () => cerrarModal(),
    'agregar-fila': () => agregarFila(),
    calidad: (boton) => abrirCalidad(boton),
};

onReady(() => {
    const raiz = document.getElementById('captura-formula');
    const cfg = leerDatos<ConfigFormula>(raiz);
    if (!raiz || !cfg) return;
    estado.cfg = cfg;

    iniciarModalFormula();
    iniciarTablaComponentes();
    iniciarFiltros();
    iniciarCalidad();
    actualizarBotonesAccion();

    // Los botones del navbar (Crear/Editar/Ver/Eliminar) viven fuera de la raíz.
    delegate(document, 'click', '[data-accion]', (_e, boton) => {
        const accion = acciones[boton.dataset.accion ?? ''];
        if (accion && !(boton as HTMLButtonElement).disabled) accion(boton);
    });
    delegate<HTMLTableRowElement>(porTabla(), 'click', 'tr.formula-row', (e, fila) => {
        // El botón de Calidad de la fila no selecciona (antes: event.stopPropagation()).
        if ((e.target as Element).closest('[data-accion]')) return;
        seleccionarFila(fila);
    });

    // Volver con el botón Atrás desde bfcache: recargar para no mostrar datos viejos.
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) window.location.reload();
    });
});

function porTabla(): HTMLElement {
    return document.getElementById('formulaTableBody') ?? document.body;
}
