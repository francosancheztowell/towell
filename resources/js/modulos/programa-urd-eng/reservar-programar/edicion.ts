/**
 * Edición en la tabla de telares: cuenta y calibre en la celda (clic derecho, mantener
 * presionado o "⋮" → Editar; Enter guarda, Esc cancela) y el select de tipo de atado.
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import type { PosicionAcciones } from '../../../utils/acciones-tactiles.ts';
import { exigirExito, mensajeError, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import { $, cfg, state } from './estado.ts';
import { esCampoEditable, fmt, normalizeTipo, payloadEdicion, s, type CampoEditable } from './logica.ts';

/* ---------- Menú de fila ("⋮") ---------- */

let filaMenu: HTMLTableRowElement | null = null;

const menuFila = (): HTMLElement | null => $('#puMenuFila');

export const cerrarMenuFila = (): void => menuFila()?.classList.add('hidden');

/** Abre el menú de la fila (Editar cuenta / Editar calibre) en `pos` (coordenadas de viewport). */
export function abrirMenuFila(fila: HTMLElement, pos: PosicionAcciones): void {
    const m = menuFila();
    if (!m || !(fila instanceof HTMLTableRowElement)) return;
    filaMenu = fila;
    m.classList.remove('hidden');
    m.style.left = `${pos.x}px`;
    m.style.top = `${pos.y}px`;
    m.querySelector<HTMLButtonElement>('button')?.focus();
}

/** Acción elegida en el menú de fila. */
export function accionMenuFila(accion: string): void {
    cerrarMenuFila();
    const campo = accion.replace('editar-', '');
    const celda = filaMenu?.querySelector<HTMLTableCellElement>(`[data-editable-field="${campo}"]`);
    if (celda) editarCelda(celda);
}

/* ---------- Edición de cuenta / calibre ---------- */

let activa: { td: HTMLTableCellElement; input: HTMLInputElement; original: string } | null = null;

function cancelar(): void {
    if (!activa) return;
    const { td, original } = activa;
    activa = null;
    td.textContent = original;
}

/** Refleja el valor guardado en los datos, el dataset de la fila y la selección. */
function reflejar(campo: CampoEditable, nuevo: string, td: HTMLTableCellElement, id: number | null, noTelar: string, tipo: string): void {
    const calibre = nuevo !== '' ? parseFloat(nuevo) : null;
    const calibreTexto = calibre === null ? '' : String(calibre);
    const tTipo = tipo.toUpperCase().trim();
    const esEste = (t: { id?: unknown; no_telar?: unknown; tipo?: unknown }): boolean =>
        id ? Number(t.id) === id : s(t.no_telar) === noTelar && s(t.tipo).toUpperCase().trim() === tTipo;

    const base = state.telaresDataOriginal.length ? state.telaresDataOriginal : state.telaresData;
    const fila = base.find(esEste);
    if (fila) fila[campo] = campo === 'cuenta' ? nuevo : calibre;

    // Dataset de la fila: lo leen la selección individual y la múltiple.
    const tr = td.closest('tr');
    if (tr) tr.dataset[campo] = campo === 'cuenta' ? nuevo : calibreTexto;

    // Programación de requerimientos recibe lo que haya en la selección.
    for (const t of state.selectedTelares) if (esEste(t)) t[campo] = campo === 'cuenta' ? nuevo : calibreTexto;
    const tel = state.selectedTelar;
    if (tel && s(tel.no_telar) === noTelar && s(tel.tipo).toUpperCase().trim() === tTipo) {
        tel[campo] = campo === 'cuenta' ? nuevo : calibreTexto;
    }
}

async function guardar(): Promise<void> {
    if (!activa) return;
    const { td, input, original } = activa;
    const campo = td.dataset.editableField;
    if (!esCampoEditable(campo)) return;

    const nuevo = input.value.trim();
    const id = td.dataset.id ? parseInt(td.dataset.id, 10) : null;
    const noTelar = td.dataset.telar ?? '';
    const tipo = td.dataset.tipo ?? '';
    activa = null;

    if (!noTelar) {
        notify.warning('No se puede actualizar: falta identificar el telar');
        td.textContent = original;
        return;
    }

    try {
        exigirExito(
            await http.post<RespuestaApi>(cfg.api.actualizarTelar, payloadEdicion(campo, nuevo, { id, no_telar: noTelar, tipo })),
            'No se pudo actualizar',
        );
        td.textContent = campo === 'calibre' ? (nuevo !== '' ? fmt.num(parseFloat(nuevo)) : '') : nuevo;
        reflejar(campo, nuevo, td, id, noTelar, tipo);
        notify.success('Actualizado');
    } catch (err) {
        notify.error(mensajeError(err, 'No se pudo actualizar'));
        td.textContent = original;
    }
}

/** Convierte la celda en un input (cuenta: texto; calibre: número). */
export function editarCelda(td: HTMLTableCellElement): void {
    if (!cfg.can.modificar) return;
    if (activa) cancelar();
    const campo = td.dataset.editableField;
    if (!esCampoEditable(campo)) return;

    const original = td.textContent?.trim() ?? '';
    const esCalibre = campo === 'calibre';
    const input = document.createElement('input');
    input.type = esCalibre ? 'number' : 'text';
    input.step = '0.01';
    input.className =
        'w-full px-2 py-1 text-sm text-center bg-white text-gray-900 border border-gray-400 rounded focus:ring-2 focus:ring-gray-300 focus:border-gray-500 focus:outline-none selection:bg-gray-200 selection:text-gray-900';
    input.value = esCalibre ? String(parseFloat(original) || '') : original;
    input.setAttribute('aria-label', `${esCalibre ? 'Calibre' : 'Cuenta'} del telar ${td.dataset.telar ?? ''}`);
    input.dataset.field = campo;
    activa = { td, input, original };

    td.replaceChildren(input);
    input.focus();
    input.select();

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            void guardar();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelar();
        }
    });
    input.addEventListener(
        'blur',
        () => {
            if (activa?.input === input) cancelar();
        },
        { once: true },
    );
}

/* ---------- Tipo de atado ---------- */

export async function cambiarTipoAtado(select: HTMLSelectElement): Promise<void> {
    if (!cfg.can.modificar) return;
    const row = select.closest<HTMLTableRowElement>('.selectable-row');
    const telar = row?.dataset.telar ?? '';
    if (!row || !telar) return;

    const nuevo = select.value || 'Normal';
    const previo = row.dataset.tipoAtado ?? 'Normal';
    const seleccionada = row.classList.contains('is-selected') && state.selectedTelar;
    if (seleccionada && state.selectedTelar) state.selectedTelar = { ...state.selectedTelar, tipo_atado: nuevo };
    row.dataset.tipoAtado = nuevo;

    // El id permite al backend resolver el folio para actualizar programas.
    const payload: Record<string, unknown> = { no_telar: telar, tipo: normalizeTipo(row.dataset.tipo), tipo_atado: nuevo };
    if (row.dataset.id) payload.id = parseInt(row.dataset.id, 10);

    try {
        exigirExito(await http.post<RespuestaApi>(cfg.api.actualizarTelar, payload), 'No se pudo actualizar tipo de atado');
        const base = state.telaresDataOriginal.find((t) => s(t.id) === row.dataset.id);
        if (base) base.tipo_atado = nuevo;
        notify.success('Tipo de atado actualizado');
    } catch (err) {
        notify.error(mensajeError(err, 'No se pudo actualizar tipo de atado'));
        // Revertir al valor anterior (antes se revertía al nuevo: el "previo" ya se había pisado).
        select.value = previo;
        row.dataset.tipoAtado = previo;
        if (seleccionada && state.selectedTelar) state.selectedTelar = { ...state.selectedTelar, tipo_atado: previo };
    }
}
