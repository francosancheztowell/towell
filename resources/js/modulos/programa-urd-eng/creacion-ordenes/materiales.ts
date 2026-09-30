/**
 * Tablas 2 y 3: materiales de urdido (BOM) y de engomado (inventario seleccionable),
 * con caché en localStorage, orden por columna, selección y totales.
 */
import { qs, qsa } from '../../../utils/dom.ts';
import { http } from '../../../utils/http.ts';
import { el, icono } from '../../urdido/comun/pagina.ts';
import { claveMaterial, ordenarMateriales, siguienteDireccion } from '../comun/inventario-materiales.ts';
import { cache, estado, filaActual, rutas } from './estado.ts';
import type { Seleccion } from './estado.ts';
import {
    celdasMaterialEngomado,
    conQuery,
    enBlanco,
    filasMaterialesUrdido,
    queryMaterialesEngomado,
    totalesSeleccion,
} from './logica.ts';
import type { MaterialEngomado, MaterialMarcado, MaterialUrdido } from './logica.ts';

const CELDA = 'px-2 py-3 text-sm text-center';

/** Material de cada fila pintada (antes iba serializado en data-material-data). */
const materialDeFila = new WeakMap<HTMLTableRowElement, MaterialEngomado>();

const tbodyUrdido = (): HTMLTableSectionElement | null => qs<HTMLTableSectionElement>('#tbodyMaterialesUrdido');
const tbodyEngomado = (): HTMLTableSectionElement | null => qs<HTMLTableSectionElement>('#tbodyMaterialesEngomado');
const checkboxes = (): HTMLInputElement[] => qsa<HTMLInputElement>('#tbodyMaterialesEngomado .checkbox-material');

/** Fila "sin datos" con el mismo aspecto que antes. */
export function filaVacia(colspan: number, texto: string): HTMLTableRowElement {
    return el(
        'tr',
        {},
        el(
            'td',
            { clase: 'px-4 py-8 text-center text-gray-500', attrs: { colspan: String(colspan) } },
            icono('fa-solid fa-circle-info text-gray-400 mb-2'),
            el('p', { texto }),
        ),
    );
}

/* =================== Urdido =================== */

export async function cargarMaterialesUrdido(bomId: string, kilosProgramados: number, forzar: boolean): Promise<void> {
    const id = (bomId || '').trim();
    if (enBlanco(id)) {
        pintarMaterialesUrdido([], kilosProgramados, null, false);
        pintarMaterialesEngomado([], null);
        return;
    }

    if (!forzar) {
        const guardado = cache.materiales(id);
        if (guardado?.materialesUrdido?.length && guardado.materialesEngomado?.length) {
            pintarFilasUrdido(guardado.materialesUrdido, kilosProgramados);
            pintarMaterialesEngomado(guardado.materialesEngomado, id);
            return;
        }
    }

    try {
        const datos = await http.get<MaterialUrdido[]>(conQuery(rutas().materialesUrdido, { bomId: id }));
        pintarMaterialesUrdido(Array.isArray(datos) ? datos : [], kilosProgramados, id, true);
    } catch {
        pintarMaterialesUrdido([], kilosProgramados, id, false);
    }
}

function pintarFilasUrdido(materiales: MaterialUrdido[], kilosProgramados: number): void {
    const tbody = tbodyUrdido();
    if (!tbody) return;
    if (!materiales.length) {
        tbody.replaceChildren(filaVacia(4, 'No hay materiales de urdido disponibles.'));
        return;
    }
    tbody.replaceChildren(
        ...filasMaterialesUrdido(materiales, kilosProgramados).map((f) =>
            el(
                'tr',
                { clase: 'hover:bg-gray-50' },
                el('td', { clase: CELDA, texto: f.articulo }),
                el('td', { clase: CELDA, texto: f.config }),
                el('td', { clase: CELDA, texto: f.consumo }),
                el('td', { clase: CELDA, texto: f.kilos }),
            ),
        ),
    );
}

export function pintarMaterialesUrdido(
    materiales: MaterialUrdido[],
    kilosProgramados: number,
    bomId: string | null,
    forzarEngomado: boolean,
): void {
    pintarFilasUrdido(materiales, kilosProgramados);
    if (!materiales.length) {
        pintarMaterialesEngomado([], bomId);
        return;
    }

    if (bomId) {
        const actual = filaActual();
        if (actual) {
            actual.materialesUrdido = materiales;
            actual.bomId = bomId;
        } else {
            for (const f of Object.values(estado.filas)) if (f.bomId === bomId) f.materialesUrdido = materiales;
        }
    }

    const query = queryMaterialesEngomado(materiales);
    if (query) void cargarMaterialesEngomado(query, bomId, forzarEngomado);
    else pintarMaterialesEngomado([], bomId);
}

/* =================== Engomado =================== */

async function cargarMaterialesEngomado(query: string, bomId: string | null, forzar: boolean): Promise<void> {
    if (bomId && !forzar) {
        const guardado = cache.materiales(bomId);
        if (guardado?.materialesEngomado?.length) {
            pintarMaterialesEngomado(guardado.materialesEngomado, bomId);
            return;
        }
    }

    try {
        const respuesta = await http.get<MaterialEngomado[]>(conQuery(rutas().materialesEngomado, query));
        const datos = Array.isArray(respuesta) ? respuesta : [];
        if (bomId) cache.guardarMateriales(bomId, materialesUrdidoDe(bomId), datos);
        pintarMaterialesEngomado(datos, bomId);
    } catch {
        pintarMaterialesEngomado([], bomId);
    }
}

/** Materiales de urdido del BOM para la caché: fila actual, otra fila con el BOM o lo guardado. */
function materialesUrdidoDe(bomId: string): MaterialUrdido[] {
    const actual = filaActual();
    if (actual?.materialesUrdido) return actual.materialesUrdido;
    for (const f of Object.values(estado.filas)) {
        if (f.bomId === bomId && f.materialesUrdido?.length) return f.materialesUrdido;
    }
    return cache.materiales(bomId)?.materialesUrdido ?? [];
}

function filaEngomado(m: MaterialEngomado, bomId: string | null): HTMLTableRowElement {
    const check = el('input', {
        clase: 'w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 checkbox-material',
        attrs: {
            type: 'checkbox',
            'aria-label': `Seleccionar ${m.ItemId || ''} ${m.InventSerialId || ''}`.trim(),
            'data-material-id': m.ItemId || '',
            'data-serial-id': m.InventSerialId || '',
            'data-checkbox-key': claveMaterial(m),
            'data-bom-id': bomId || '',
        },
    });
    const tr = el(
        'tr',
        { clase: 'hover:bg-gray-50' },
        ...celdasMaterialEngomado(m).map((texto) => el('td', { clase: CELDA, texto })),
        el('td', { clase: 'px-2 py-3 text-center' }, check),
    );
    materialDeFila.set(tr, m);
    return tr;
}

/**
 * Pinta la Tabla 3. `reordenando`: viene del clic en un encabezado, no se vuelve a guardar
 * la lista en la fila (se guarda sin ordenar, como antes).
 */
export function pintarMaterialesEngomado(materiales: MaterialEngomado[], bomId: string | null, reordenando = false): void {
    const tbody = tbodyEngomado();
    if (!tbody) return;
    const actual = filaActual();

    if (!materiales.length) {
        tbody.replaceChildren(filaVacia(14, 'No hay materiales de engomado disponibles.'));
        if (actual) actual.materialesEngomado = [];
        actualizarTotales();
        return;
    }

    if (!reordenando) {
        if (actual) actual.materialesEngomado = materiales;
        else for (const f of Object.values(estado.filas)) if (f.bomId === bomId) f.materialesEngomado = materiales;
    }

    const { columna, direccion } = estado.orden;
    tbody.replaceChildren(...ordenarMateriales(materiales, columna, direccion).map((m) => filaEngomado(m, bomId)));
    actualizarIconosOrden();

    if (bomId) restaurarSelecciones(cache.selecciones(bomId));
    actualizarTotales();
    actualizarBotonCrear();
}

/* =================== Orden por columna =================== */

function actualizarIconosOrden(): void {
    const { columna, direccion } = estado.orden;
    for (const th of qsa<HTMLElement>('#tablaMaterialesEngomado th.sortable')) {
        th.classList.remove('sort-asc', 'sort-desc');
        const activa = Boolean(columna) && th.dataset.sort === columna;
        if (activa) th.classList.add(direccion === 'asc' ? 'sort-asc' : 'sort-desc');
        th.setAttribute('aria-sort', activa ? (direccion === 'asc' ? 'ascending' : 'descending') : 'none');
    }
}

export function ordenarPor(columna: string): void {
    estado.orden = { columna, direccion: siguienteDireccion(estado.orden, columna) };
    actualizarIconosOrden();
    const actual = filaActual();
    if (!actual) return;
    const bomId = actual.bomId || null;
    guardarSelecciones(bomId);
    const selecciones = bomId ? cache.selecciones(bomId) : [];
    pintarMaterialesEngomado(actual.materialesEngomado || [], bomId, true);
    restaurarSelecciones(selecciones);
    actualizarTotales();
}

/* =================== Selección =================== */

export function guardarSelecciones(bomId: string | null): void {
    let id = bomId ?? '';
    if (enBlanco(id)) {
        id = checkboxes()[0]?.dataset.bomId || filaActual()?.bomId || '';
        if (enBlanco(id)) return;
    }
    const selecciones: Seleccion[] = checkboxes()
        .filter((c) => c.checked)
        .map((c) => ({
            materialId: c.dataset.materialId || '',
            serialId: c.dataset.serialId || '',
            checkboxKey: c.dataset.checkboxKey || '',
        }));
    cache.guardarSelecciones(id, selecciones);
}

function restaurarSelecciones(selecciones: Seleccion[]): void {
    if (!selecciones.length) return;
    const claves = new Set(selecciones.map((s) => s.checkboxKey || `${s.materialId}_${s.serialId}`));
    for (const c of checkboxes()) if (claves.has(c.dataset.checkboxKey || '')) c.checked = true;
}

/** Al marcar/desmarcar un material. */
export function alCambiarSeleccion(check: HTMLInputElement): void {
    guardarSelecciones(check.dataset.bomId || null);
    actualizarTotales();
    actualizarBotonCrear();
}

/** Materiales marcados con su objeto de fila (para el payload). */
export function marcados(): MaterialMarcado[] {
    return checkboxes()
        .filter((c) => c.checked)
        .map((c) => {
            const tr = c.closest('tr');
            return {
                materialId: c.dataset.materialId || '',
                serialId: c.dataset.serialId || '',
                material: (tr && materialDeFila.get(tr)) || null,
            };
        });
}

export function actualizarTotales(): void {
    const t = totalesSeleccion(marcados().flatMap((m) => (m.material ? [m.material] : [])));
    const conos = qs('#totalConos');
    const kilos = qs('#totalKilos');
    const registros = qs('#totalRegistros');
    if (conos) conos.textContent = t.conos;
    if (kilos) kilos.textContent = t.kilos;
    if (registros) registros.textContent = String(t.registros);
}

export function actualizarBotonCrear(): void {
    const btn = document.getElementById('btn-crear-ordenes');
    if (btn instanceof HTMLButtonElement) btn.disabled = !checkboxes().some((c) => c.checked);
}
