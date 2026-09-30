/**
 * Resumen por semana (5 semanas de programación de los telares del grupo) y prellenado
 * de Metros/Kilos de la tabla de requerimientos con los totales por telar.
 */
import { el, mensajeError } from '../../urdido/comun/pagina.ts';
import { campo, clonar, type Dom, type Estado } from './estado.ts';
import {
    etiquetaSemana,
    formatearCantidad,
    formatearNumeroInput,
    itemTieneDatos,
    itemsResumen,
    kilosProgramados,
    mensajeResumenVacio,
    normalizarEntrada,
    totalesPorTelar,
    totalesResumen,
    type DatosResumen,
    type ItemResumen,
    type Semana,
    type Validacion,
} from './logica.ts';
import { pedirResumen } from './servidor.ts';

const CELDA = 'px-2 py-1.5 whitespace-nowrap';
const CELDA_TEXTO = `${CELDA} text-caption text-gray-700`;
const CELDA_NUM = `${CELDA} text-md text-right`;
const CELDA_TOTAL = 'px-2 py-2 whitespace-nowrap text-md font-semibold text-right';

export function pintarMensajeResumen(dom: Dom, mensaje: string, conReintentar = false): void {
    const fila = clonar('tpl-resumen-mensaje');
    const slot = fila.querySelector('[data-slot="mensaje"]');
    if (slot) slot.textContent = mensaje;
    const boton = fila.querySelector<HTMLButtonElement>('[data-accion="reintentar-resumen"]');
    if (boton) boton.hidden = !conReintentar;
    dom.resumen.replaceChildren(fila);
}

function pintarEncabezados(dom: Dom, semanas: readonly Semana[]): void {
    semanas.forEach((semana, idx) => {
        const rango = etiquetaSemana(semana);
        if (!rango) return;
        dom.encabezadoResumen.querySelectorAll(`.semana-header[data-semana="${idx}"]`).forEach((th) => {
            const envoltura = th.querySelector<HTMLElement>('[data-rango-semana]');
            const texto = th.querySelector<HTMLElement>('[data-rango-texto]');
            // Solo la primera vez (como antes): no se reescriben fechas ya puestas.
            if (!envoltura || !texto || !envoltura.classList.contains('hidden')) return;
            texto.textContent = rango;
            envoltura.classList.remove('hidden');
        });
    });
}

function filaItem(r: ItemResumen): HTMLTableRowElement {
    const calibre = r.calibre !== null && r.calibre !== undefined && r.calibre !== '' ? String(r.calibre) : '-';
    return el(
        'tr',
        { clase: 'hover:bg-gray-50' },
        el('td', { clase: CELDA_TEXTO, texto: r.telar }),
        el('td', { clase: CELDA_TEXTO, texto: r.cuenta || '-' }),
        el('td', { clase: CELDA_TEXTO, texto: r.hilo || '-' }),
        el('td', { clase: CELDA_TEXTO, texto: calibre }),
        el('td', { clase: CELDA_TEXTO, texto: r.modelo || '-' }),
        ...r.metros.map((m) => el('td', { clase: CELDA_NUM, texto: formatearCantidad(m) })),
        el('td', { clase: `${CELDA} text-md font-semibold text-right text-blue-600 bg-blue-50`, texto: formatearCantidad(r.total) }),
        ...r.kilos.map((k) => el('td', { clase: CELDA_NUM, texto: formatearCantidad(k) })),
        el('td', { clase: `${CELDA} text-md font-semibold text-right text-green-600 bg-green-50`, texto: formatearCantidad(r.totalKilos) }),
    );
}

function filaTotales(items: readonly ItemResumen[]): HTMLTableRowElement {
    const t = totalesResumen(items);
    const azul = `${CELDA_TOTAL} text-blue-700 bg-blue-100`;
    const verde = `${CELDA_TOTAL} text-green-700 bg-green-100`;
    return el(
        'tr',
        { clase: 'bg-gray-100 font-bold', attrs: { id: 'filaTotal' } },
        el('td', { clase: 'px-2 py-2 whitespace-nowrap text-caption font-bold text-gray-800 bg-gray-100', texto: 'TOTAL', attrs: { colspan: '5' } }),
        ...t.metros.map((m) => el('td', { clase: azul, texto: formatearCantidad(m) })),
        el('td', { clase: azul }, el('span', { clase: 'block', texto: formatearCantidad(t.totalMetros) })),
        ...t.kilos.map((k) => el('td', { clase: verde, texto: formatearCantidad(k) })),
        el('td', { clase: verde }, el('span', { clase: 'block', texto: formatearCantidad(t.totalKilos) })),
    );
}

/** Metros = total programado del telar; Kilos = proporcional (kg/m del resumen). */
function prellenarMetrosYKilos(estado: Estado, dom: Dom): void {
    for (const fila of dom.cuerpo.querySelectorAll<HTMLElement>('tr[data-index]')) {
        const totales = estado.porTelar.get(fila.dataset.telarId ?? '');
        const metros = campo(fila, 'metros');
        const kilos = campo(fila, 'kilos');
        if (!totales || totales.totalMetros <= 0 || !metros || !kilos) continue;
        metros.value = formatearNumeroInput(totales.totalMetros);
        kilos.value = formatearNumeroInput(kilosProgramados(totales, totales.totalMetros));
    }
}

function pintarResumen(estado: Estado, dom: Dom, data: DatosResumen, v: Validacion & { valido: true }, semanas: Semana[]): void {
    pintarEncabezados(dom, semanas);

    const items = itemsResumen(data, v);
    if (!items.length) {
        estado.porTelar = new Map();
        pintarMensajeResumen(dom, mensajeResumenVacio(data, v, semanas));
        return;
    }

    dom.resumen.replaceChildren(...items.filter(itemTieneDatos).map(filaItem), filaTotales(items));
    estado.porTelar = totalesPorTelar(items);
    prellenarMetrosYKilos(estado, dom);
    dom.cuerpo.dispatchEvent(new Event('change'));
}

export async function cargarResumen(estado: Estado, dom: Dom): Promise<void> {
    const v = estado.validacion;
    if (!v) return;
    dom.resumen.replaceChildren(clonar('tpl-resumen-cargando'));
    try {
        const { data, semanas } = await pedirResumen(estado, normalizarEntrada(estado.telares));
        pintarResumen(estado, dom, data, v, semanas);
    } catch (err) {
        console.error('Error al cargar resumen:', err);
        pintarMensajeResumen(dom, `Error al cargar datos: ${mensajeError(err, 'Error desconocido')}`, true);
    }
}
