// Modal "Crear repaso". Vivía inline en modulos/programa-tejido/modal/repaso.blade.php.
import { datosDelError } from '../respuesta.ts';
import { rutaSuperficie } from '../rutas.ts';

interface OpcionCatalogo {
    value?: string;
    label?: string;
    Hilo?: string;
    id?: string;
    nombre?: string;
}

interface RespuestaRepaso {
    ok?: boolean;
    id?: number | string;
    message?: string;
    registro?: Record<string, unknown> | null;
    errors?: Record<string, string[] | string>;
}

let repasoRowId: string | null = null;

const porId = <T extends HTMLElement>(id: string) => document.getElementById(id) as T | null;

async function cargarCatalogo(ruta: string): Promise<unknown[]> {
    try {
        const data = await http.get<unknown>(rutaSuperficie(ruta));
        return Array.isArray(data) ? data : [];
    } catch {
        // Sin catálogo el select queda con la opción vacía, como antes.
        return [];
    }
}

async function cargarHilos(): Promise<void> {
    const arr = await cargarCatalogo('/programa-tejido/hilos-options');
    const sel = porId<HTMLSelectElement>('repaso-hilo');
    if (!sel) return;
    sel.replaceChildren(new Option('Seleccione hilo...', ''));
    arr.forEach((h) => {
        const o = (typeof h === 'object' && h ? h : null) as OpcionCatalogo | null;
        const v = o ? o.value || o.Hilo || o.id || '' : String(h);
        const l = o ? o.label || o.Hilo || o.nombre || v : v;
        if (v) sel.appendChild(new Option(l, v));
    });
}

async function cargarTelares(): Promise<void> {
    const arr = (await cargarCatalogo('/programa-tejido/telares-all')) as (OpcionCatalogo | null)[];
    const sel = porId<HTMLSelectElement>('repaso-telar');
    if (!sel) return;
    sel.replaceChildren(new Option('Seleccione telar...', ''));
    arr.forEach((item) => {
        if (item && item.value) sel.appendChild(new Option(item.label || item.value, item.value));
    });
}

function abrirModalRepaso(row: Element | null | undefined): void {
    repasoRowId = row ? row.getAttribute('data-id') : null;
    const modal = porId('modalRepaso');
    if (!modal) return;

    const telarSel = porId<HTMLSelectElement>('repaso-telar');
    const anchoInp = porId<HTMLInputElement>('repaso-ancho');
    const hiloSel = porId<HTMLSelectElement>('repaso-hilo');
    const calibreInp = porId<HTMLInputElement>('repaso-calibre');
    if (telarSel) telarSel.selectedIndex = 0;
    if (anchoInp) anchoInp.value = '';
    if (hiloSel) hiloSel.selectedIndex = 0;
    if (calibreInp) calibreInp.value = '';

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    void Promise.all([cargarHilos(), cargarTelares()]).then(() => {
        const first = porId('repaso-telar');
        if (first) setTimeout(() => first.focus(), 100);
    });
}

function cerrarModalRepaso(): void {
    const modal = porId('modalRepaso');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
    repasoRowId = null;
}

/** Primer mensaje de validación (422) o el mensaje general. */
export function mensajeRepaso(res: RespuestaRepaso | null | undefined): string {
    let errMsg = res?.message || 'Error al crear repaso';
    const errors = res?.errors;
    if (errors && typeof errors === 'object') {
        const keys = Object.keys(errors);
        const first = keys.length ? errors[keys[0] as string] : undefined;
        if (Array.isArray(first) && first[0]) errMsg = String(first[0]);
        else if (typeof first === 'string') errMsg = first;
    }
    return errMsg;
}

function exito(res: RespuestaRepaso): void {
    cerrarModalRepaso();
    const mensaje = res.message || 'Repaso creado';
    // agregarRegistroSinRecargar vive en el scope de index.js y hoy no se publica en window,
    // así que esta rama no corre (igual que antes de PT-TS 1: ver HANDOFF para PT-TS 2).
    const agregar = window.agregarRegistroSinRecargar;
    if (typeof agregar !== 'function' || !res.id) {
        notify.success(mensaje);
        return;
    }
    const reg = res.registro && typeof res.registro === 'object' ? res.registro : null;
    const payload = {
        registro_id: res.id,
        message: mensaje,
        registro: reg,
        registros_datos: reg ? { [String(res.id)]: reg } : null,
    };
    setTimeout(() => {
        agregar(payload)
            .then(() => notify.success(mensaje))
            .catch(() => notify.info('Repaso creado. Si no aparece, recargue la página.'));
    }, 200);
}

async function crearRepasoEnviar(): Promise<void> {
    const btn = porId<HTMLButtonElement>('btnCrearRepaso');
    const telarSel = porId<HTMLSelectElement>('repaso-telar');
    const ancho = porId<HTMLInputElement>('repaso-ancho');
    const hiloSel = porId<HTMLSelectElement>('repaso-hilo');
    const calibre = porId<HTMLInputElement>('repaso-calibre');

    const telarVal = telarSel ? telarSel.options[telarSel.selectedIndex]?.value : '';
    const data = {
        id: repasoRowId,
        telar: (telarVal || '').trim(),
        ancho: ancho ? (ancho.value === '' ? '' : parseFloat(ancho.value)) : '',
        hilo: hiloSel ? hiloSel.options[hiloSel.selectedIndex]?.value || '' : '',
        calibre: calibre ? (calibre.value === '' ? '' : parseFloat(calibre.value)) : '',
    };

    const labelOriginal = btn ? btn.textContent : '';
    if (btn) {
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.classList.add('opacity-70', 'cursor-wait', 'pointer-events-none');
        btn.textContent = 'Creando…';
    }

    try {
        const res = (await http.post<RespuestaRepaso>(rutaSuperficie('/planeacion/programa-tejido/crear-repaso'), data)) ?? {};
        if (res.ok === true) exito(res);
        else notify.error(mensajeRepaso(res));
    } catch (err) {
        // 419/401 los atiende http (aviso y recarga); el resto trae el JSON del servidor.
        const datos = datosDelError<RespuestaRepaso>(err);
        notify.error(datos ? mensajeRepaso(datos) : 'Error de conexión al crear repaso');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
            btn.classList.remove('opacity-70', 'cursor-wait', 'pointer-events-none');
            btn.textContent = labelOriginal || 'Crear';
        }
    }
}

// PUENTE PT-TS 1: index.js (menú contextual de la fila) abre el modal.
window.abrirModalRepaso = abrirModalRepaso;
// PUENTE PT-TS 1: x-ui.modal-base (onclose del botón × y Esc) en modal/repaso.blade.php.
window.cerrarModalRepaso = cerrarModalRepaso;

document.addEventListener('click', (e) => {
    if ((e.target as Element | null)?.closest?.('#btnCrearRepaso')) void crearRepasoEnviar();
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const m = porId('modalRepaso');
    if (m && !m.classList.contains('hidden')) cerrarModalRepaso();
});
