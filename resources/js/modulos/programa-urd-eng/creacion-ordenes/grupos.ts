/** Tabla 1: telares agrupados, destino por grupo, BOM de urdido y selección de fila. */
import { el } from '../../urdido/comun/pagina.ts';
import { cache, estado } from './estado.ts';
import { cargarAnchosBalona, actualizarMetrajeTelas } from './engomado.ts';
import {
    DESTINOS,
    aNum,
    agruparTelares,
    clasesTipo,
    destinoInicial,
    enBlanco,
    fechaCorta,
    idFila,
    normalizarDestino,
    numero,
    requiereDestinoManual,
} from './logica.ts';
import type { Grupo, Telar } from './logica.ts';
import { cargarMaterialesUrdido, filaVacia, pintarMaterialesEngomado, pintarMaterialesUrdido } from './materiales.ts';

const CELDA = 'px-2 py-3 text-sm text-center';
const CLASES_SELECCION = ['bg-blue-100', 'border-l-4', 'border-blue-500'];

function selectDestino(filaId: string, inicial: string): HTMLSelectElement {
    const actual = normalizarDestino(inicial);
    const select = el(
        'select',
        {
            clase: 'w-full px-2 py-1.5 border border-gray-300 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500',
            attrs: { 'data-destino-select': 'true', 'data-fila-id': filaId, 'aria-label': 'Destino del grupo' },
        },
        el('option', { texto: 'Seleccione...', attrs: { value: '' } }),
        ...DESTINOS.map((d) => el('option', { texto: d, attrs: { value: d } })),
    );
    select.value = actual;
    marcarDestinoPendiente(select);
    return select;
}

/** Borde ámbar mientras el destino del grupo esté vacío. */
export function marcarDestinoPendiente(select: HTMLSelectElement | null | undefined): void {
    if (!select) return;
    const vacio = enBlanco(select.value);
    select.classList.toggle('border-amber-400', vacio);
    select.classList.toggle('bg-amber-50', vacio);
}

function filaGrupo(g: Grupo, indice: number): HTMLTableRowElement {
    const filaId = idFila(indice, g);
    const destino = destinoInicial(g);
    const manual = requiereDestinoManual(g);
    estado.filas[filaId] = {
        grupo: g,
        bomId: '',
        kilos: g.kilos || 0,
        materialesUrdido: null,
        destinoSeleccionado: destino,
        requiereDestinoManual: manual,
    };

    const inputBom = el('input', {
        clase: 'w-full px-2 py-1.5 border border-gray-500 rounded-md text-xs focus:outline-none focus:ring-2 focus:ring-blue-500',
        attrs: {
            type: 'text',
            placeholder: 'Buscar BOM...',
            'aria-label': `L.Mat Urdido de ${g.telaresStr || 'la fila'}`,
            autocomplete: 'off',
            'data-bom-input': 'true',
            'data-grupo': String(g.telaresStr),
            'data-fila-id': filaId,
            'data-kilos': String(g.kilos || 0),
            'data-bom-id': '',
        },
    });

    const tr = el(
        'tr',
        { clase: 'hover:bg-gray-50 cursor-pointer transition-colors', attrs: { id: filaId, 'data-fila-id': filaId } },
        el('td', { clase: CELDA, texto: g.telaresStr || '-' }),
        el('td', { clase: CELDA, texto: fechaCorta(g.fechaReq) }),
        el('td', { clase: CELDA, texto: g.cuenta || '-' }),
        el('td', { clase: CELDA, texto: g.calibre || '-' }),
        el('td', { clase: CELDA, texto: g.hilo || '-' }),
        el('td', { clase: CELDA, texto: g.tamano || '-' }),
        el('td', { clase: CELDA, texto: g.urdido || '-' }),
        el(
            'td',
            { clase: 'px-2 py-3 text-center' },
            el('span', { clase: `px-2 py-1 inline-block text-sm font-medium rounded-md ${clasesTipo(g.tipo || '')}`, texto: g.tipo || 'Rizo' }),
        ),
        el(
            'td',
            { clase: CELDA },
            manual
                ? selectDestino(filaId, destino)
                : el('span', { clase: 'text-xs font-medium text-gray-700', texto: destino || '-', attrs: { 'data-destino-text': 'true' } }),
        ),
        el('td', { clase: CELDA, texto: numero(g.metros) }),
        el('td', { clase: CELDA, texto: numero(g.kilos) }),
        el('td', { clase: 'px-2 py-3 text-center' }, inputBom),
    );
    return tr;
}

export function pintarGrupos(telares: Telar[]): void {
    const tbody = document.getElementById('tbodyOrdenes');
    if (!tbody) return;
    if (!telares.length) {
        tbody.replaceChildren(filaVacia(12, 'No hay telares seleccionados.'));
        return;
    }
    const grupos = agruparTelares(telares);
    tbody.replaceChildren(...grupos.map(filaGrupo));
    const primero = grupos[0];
    if (primero) seleccionarFila(idFila(0, primero));
}

export function seleccionarFila(filaId: string): void {
    if (estado.filaSeleccionadaId) {
        const previa = document.getElementById(estado.filaSeleccionadaId);
        previa?.classList.remove(...CLASES_SELECCION);
        previa?.classList.add('hover:bg-gray-50');
        previa?.removeAttribute('aria-selected');
    }
    estado.filaSeleccionadaId = filaId;
    const fila = document.getElementById(filaId);
    fila?.classList.add(...CLASES_SELECCION);
    fila?.classList.remove('hover:bg-gray-50');
    fila?.setAttribute('aria-selected', 'true');

    const datos = estado.filas[filaId];
    if (!datos) {
        pintarMaterialesUrdido([], 0, null, false);
        pintarMaterialesEngomado([], null);
        return;
    }

    const inputBom = fila?.querySelector<HTMLInputElement>('[data-bom-input="true"]');
    const bomId = (datos.bomId || inputBom?.dataset.bomId || inputBom?.value || '').trim();
    if (datos.bomId && inputBom && inputBom.value !== datos.bomId) {
        inputBom.value = datos.bomId;
        inputBom.dataset.bomId = datos.bomId;
    }

    if (!enBlanco(bomId)) {
        void cargarMaterialesUrdido(bomId, datos.kilos || 0, false);
        datos.bomId = bomId;
    } else {
        pintarMaterialesUrdido([], datos.kilos, null, false);
        pintarMaterialesEngomado([], null);
    }

    void cargarAnchosBalona(datos.grupo.cuenta || '', datos.grupo.tipo || '');
    actualizarMetrajeTelas();
}

export function cambiarDestino(select: HTMLSelectElement): void {
    const datos = estado.filas[select.dataset.filaId ?? ''];
    if (datos) datos.destinoSeleccionado = normalizarDestino(select.value);
    marcarDestinoPendiente(select);
}

/** BOM elegido de la lista de sugerencias. */
export function elegirBomUrdido(input: HTMLInputElement, bomId: string): void {
    input.value = bomId;
    input.dataset.bomId = bomId;
    const filaId = input.dataset.filaId ?? '';
    const datos = estado.filas[filaId];
    if (datos) {
        const anterior = datos.bomId;
        datos.bomId = bomId;
        if (anterior && anterior !== bomId) cache.borrarMateriales(anterior);
    }
    if (filaId === estado.filaSeleccionadaId) void cargarMaterialesUrdido(bomId, aNum(input.dataset.kilos, 0), true);
}

/** BOM escrito a mano (al salir del input de la fila seleccionada). */
export function salirDeBomUrdido(input: HTMLInputElement): void {
    const filaId = input.dataset.filaId ?? '';
    const bomId = (input.value || '').trim();
    const datos = estado.filas[filaId];
    if (filaId !== estado.filaSeleccionadaId || !bomId || !datos) return;
    const kilos = aNum(input.dataset.kilos, 0);
    const anterior = datos.bomId;
    datos.bomId = bomId;
    input.dataset.bomId = bomId;
    if (anterior && anterior !== bomId) {
        cache.borrarMateriales(anterior);
        void cargarMaterialesUrdido(bomId, kilos, true);
    } else {
        void cargarMaterialesUrdido(bomId, kilos, false);
    }
}
