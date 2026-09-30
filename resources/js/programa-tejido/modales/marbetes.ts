// Modal "Editar marbetes" (menú contextual de la fila).
import { PT_BOOT } from '../boot.ts';
import { datosDelError } from '../respuesta.ts';

const CAMPOS = ['pesoRollo', 'repeticiones', 'mtsRollo', 'pzasRollo', 'noMarbete', 'totalRollos', 'totalPzas'] as const;
type Campo = (typeof CAMPOS)[number];
// Columna de la grilla donde se refleja cada campo al guardar.
const COLUMNAS: Record<Campo, string> = {
    pesoRollo: 'PesoRollo', repeticiones: 'Repeticiones', mtsRollo: 'MtsRollo', pzasRollo: 'PzasRollo',
    noMarbete: 'NoMarbete', totalRollos: 'TotalRollos', totalPzas: 'TotalPzas',
};
// Cadena de cálculo (misma que liberar órdenes, regla FEL incluida): al cambiar un campo se
// envían él y los de arriba, y el servidor recalcula todo lo que va debajo.
// MtsRollo y No. marbetes no arrastran nada, así que son captura libre.
const CADENA: readonly Campo[] = ['pesoRollo', 'repeticiones', 'pzasRollo', 'totalRollos'];

type Valores = Partial<Record<Campo, number | string | null>>;
interface RespuestaMarbetes {
    valores?: Valores;
    registro?: { telar?: string; producto?: string; tamano?: string; noTiras?: number | string | null };
    esFel?: boolean;
    message?: string;
}

const URL_MARBETES = PT_BOOT.routes?.marbetes ?? '';
let registroId: string | null = null;

const inp = (campo: Campo) => document.getElementById('marbetes-' + campo) as HTMLInputElement | null;
// Solo `message`: el contrato del endpoint de marbetes (antes: err.data?.message).
const mensaje = (err: unknown, porDefecto: string) =>
    datosDelError<{ message?: string }>(err)?.message || porDefecto;

function pintar(valores: Valores | null | undefined): void {
    CAMPOS.forEach((c) => {
        const el = inp(c);
        const v = valores?.[c];
        if (el) el.value = v !== null && v !== undefined ? String(v) : '';
    });
}

/** Texto de cabecera del modal. */
export function infoMarbetes(res: RespuestaMarbetes): string {
    const r = res.registro || {};
    return 'Telar ' + (r.telar || '-') + ' · ' + (r.producto || '') + ' · Tamaño ' + (r.tamano || '-') +
        ' · Tiras ' + (r.noTiras !== null && r.noTiras !== undefined ? r.noTiras : '-') +
        (res.esFel ? ' · FEL (marbetes ×2, mts/pzas ÷2)' : '');
}

function cerrarModalMarbetes(): void {
    const modal = document.getElementById('modalMarbetes');
    if (modal) { modal.classList.add('hidden'); document.body.style.overflow = ''; }
    registroId = null;
}

function abrirModalMarbetes(row: Element | null | undefined): void {
    registroId = row ? row.getAttribute('data-id') : null;
    if (!registroId) { notify.error('No hay registro seleccionado'); return; }

    const modal = document.getElementById('modalMarbetes');
    const info = document.getElementById('marbetes-info');
    if (!modal) return;
    pintar(null);
    if (info) info.textContent = 'Cargando…';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    http.get<RespuestaMarbetes>(URL_MARBETES, { params: { id: registroId } })
        .then((res) => {
            pintar(res.valores);
            if (info) info.textContent = infoMarbetes(res);
        })
        .catch((err: unknown) => {
            notify.error(mensaje(err, 'No se pudieron cargar los marbetes'));
            cerrarModalMarbetes();
        });
}

CADENA.forEach((campo, i) => {
    inp(campo)?.addEventListener('change', () => {
        if (!registroId) return;
        const params: Record<string, string | number> = { id: registroId };
        CADENA.slice(0, i + 1).forEach((c) => {
            const v = parseFloat(inp(c)?.value ?? '');
            if (v > 0) params[c] = v;
        });
        http.get<RespuestaMarbetes>(URL_MARBETES, { params })
            .then((res) => pintar(res.valores))
            .catch(() => notify.error('No se pudo recalcular'));
    });
});

function guardarMarbetesEnviar(): void {
    if (!registroId) return;
    const id = registroId;
    const btn = document.getElementById('btnGuardarMarbetes') as HTMLButtonElement | null;
    const payload: Record<string, string | number | null> = { id };
    CAMPOS.forEach((c) => {
        const v = inp(c)?.value;
        payload[c] = v === '' || v === undefined ? null : parseFloat(v);
    });

    if (btn) { btn.disabled = true; btn.textContent = 'Guardando…'; }

    http.post<RespuestaMarbetes>(PT_BOOT.routes?.marbetesGuardar ?? '', payload)
        .then((res) => {
            notify.success(res.message || 'Marbetes actualizados');
            const row = document.querySelector(`.selectable-row[data-id="${CSS.escape(id)}"]`);
            if (row) {
                CAMPOS.forEach((c) => {
                    const td = row.querySelector(`td[data-column="${COLUMNAS[c]}"]`);
                    if (!td) return;
                    const v = payload[c];
                    td.setAttribute('data-value', v === null || v === undefined ? '' : String(v));
                    td.textContent = v === null || v === undefined ? '' : String(v);
                });
            }
            cerrarModalMarbetes();
        })
        .catch((err: unknown) => notify.error(mensaje(err, 'Error al guardar marbetes')))
        .finally(() => { if (btn) { btn.disabled = false; btn.textContent = 'Guardar'; } });
}

// PUENTE PT-TS 1: index.js (menú contextual de la fila) abre el modal.
window.abrirModalMarbetes = abrirModalMarbetes;
// PUENTE PT-TS 1: x-ui.modal-base (onclose del botón × y Esc) en modal/marbetes.blade.php.
window.cerrarModalMarbetes = cerrarModalMarbetes;

document.addEventListener('click', (e) => {
    if ((e.target as Element | null)?.closest?.('#btnGuardarMarbetes')) guardarMarbetesEnviar();
});

document.addEventListener('keydown', (e) => {
    const m = document.getElementById('modalMarbetes');
    if (e.key === 'Escape' && m && !m.classList.contains('hidden')) cerrarModalMarbetes();
});
