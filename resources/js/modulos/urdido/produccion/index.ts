/**
 * Producción Urdido (19-01): entrada Vite de /urdido/modulo-produccion-urdido.
 * Vistas: resources/views/modulos/urdido/modulo-produccion-urdido.blade.php + produccion/_*.blade.php
 * Config: data-produccion-urdido de produccion/_scripts.blade.php.
 *
 * Aquí solo se cablea; la lógica vive en filas.ts, julios.ts, oficiales.ts, finalizar.ts
 * y la parte pura en logica.ts.
 */
import { delegate, onReady } from '../../../utils/dom.ts';
import { leerDatos } from '../comun/pagina.ts';
import { ctx, type ConfigProduccion } from './contexto.ts';
import {
    abrirSelectorFecha,
    alCambiarEnTabla,
    alSalirDeBruto,
    alTeclearPeso,
    cerrarEditoresCantidad,
    elegirCantidad,
    hayCapturaPendiente,
    iniciarFilas,
    interceptarFilaBloqueada,
    ponerHoraActual,
    toggleQuantityEdit,
} from './filas.ts';
import { finalizar } from './finalizar.ts';
import { cargarCatalogosJulios } from './julios.ts';
import { agregarOficial, cargarUsuariosUrdido, iniciarModalOficial } from './oficiales.ts';

const acciones: Record<string, (boton: HTMLElement, ev: Event) => void> = {
    'editar-cantidad': (boton) => toggleQuantityEdit(boton),
    'elegir-fecha': abrirSelectorFecha,
    'hora-actual': ponerHoraActual,
    'agregar-oficial': agregarOficial,
};

onReady(() => {
    const cfg = leerDatos<ConfigProduccion>(document.getElementById('produccion-urdido-config'), 'produccionUrdido');
    if (!cfg) return;
    ctx.cfg = cfg;

    // Botón del navbar (fuera de la tabla).
    delegate(document, 'click', '[data-accion="finalizar"]', () => void finalizar());
    iniciarModalOficial();

    const tbody = document.getElementById('tabla-produccion-body');
    ctx.tabla = tbody;
    if (tbody) {
        delegate(tbody, 'click', '[data-accion]', (ev, boton) => acciones[boton.dataset.accion ?? '']?.(boton, ev));
        delegate(tbody, 'click', '.number-option', (ev, opcion) => elegirCantidad(opcion, ev));
        delegate<HTMLInputElement>(tbody, 'input', 'input[data-field="kg_bruto"], input[data-field="tara"]', (_ev, input) => alTeclearPeso(input));
        delegate<HTMLInputElement>(tbody, 'focusout', 'input[data-field="kg_bruto"]', (_ev, input) => alSalirDeBruto(input));
        tbody.addEventListener('change', alCambiarEnTabla);
        // Fase de captura: el aviso de fila parcial va antes que cualquier otro manejador.
        tbody.addEventListener('mousedown', interceptarFilaBloqueada, true);
        iniciarFilas(tbody);
    }

    // Cerrar los editores de roturas al hacer clic fuera.
    document.addEventListener('click', (ev) => {
        const t = ev.target as Element | null;
        if (!t?.closest?.('.quantity-edit-container, .edit-quantity-btn, .number-option')) cerrarEditoresCantidad();
    });

    // Última red: avisar si se navega con un Kg. Bruto sin guardar.
    window.addEventListener('beforeunload', (ev) => {
        if (!hayCapturaPendiente()) return;
        ev.preventDefault();
        ev.returnValue = '';
    });

    void cargarCatalogosJulios();
    void cargarUsuariosUrdido();
});
