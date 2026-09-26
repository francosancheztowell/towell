/**
 * Captura de Fórmula: Calidad por formulación (✓/✗ de Tiempo, Sólidos y Viscosidad + observación).
 * Guarda con PUT eng-formulacion.update (JSON). Antes era un Swal con html + preConfirm.
 */
import { http, HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { delegate } from '../../../utils/dom.ts';
import { exigirExito, mensajeError, rutaCon, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import { abrirModalBase, cerrarModalBase, estado } from './estado.ts';
import { clasesStatusPrograma, okDesdeDato, okInicial, okSiguiente } from './logica.ts';

const MODAL = 'modalCalidad';
const CAMPOS = ['oktiempo', 'oksolidos', 'okviscocidad'] as const;
type CampoOk = (typeof CAMPOS)[number];

let botonActual: HTMLElement | null = null;

function modal(): HTMLElement | null {
    return document.getElementById(MODAL);
}

function ciclo(campo: CampoOk): HTMLButtonElement | null {
    return modal()?.querySelector<HTMLButtonElement>(`[data-calidad-ciclo="${campo}"]`) ?? null;
}

function pintarCiclo(boton: HTMLButtonElement, valor: '0' | '1'): void {
    boton.dataset.value = valor;
    boton.textContent = valor === '1' ? '✓' : '✗';
    boton.classList.toggle('text-green-600', valor === '1');
    boton.classList.toggle('text-red-600', valor === '0');
    boton.setAttribute('aria-label', `${boton.dataset.concepto ?? ''}: ${valor === '1' ? 'correcto' : 'incorrecto'}`);
}

/** Abre el modal con los datos del botón de la fila (data-*). */
export function abrirCalidad(boton: HTMLElement): void {
    const m = modal();
    if (!m) return;
    botonActual = boton;
    const d = boton.dataset;
    const texto = (clave: string, valor: string): void => {
        const nodo = m.querySelector(`[data-calidad="${clave}"]`);
        if (nodo) nodo.textContent = valor;
    };
    texto('folio', d.folio ?? '');
    texto('formula', d.formula ?? '');
    texto('litros', d.litros ?? '');
    texto('tiempo', d.tiempo ?? '');
    texto('solidos', d.solidos ?? '');
    texto('viscocidad', d.viscocidad ?? '');

    const status = d.programaStatus ?? '';
    const chip = m.querySelector<HTMLElement>('[data-calidad="status"]');
    if (chip) {
        chip.className = `px-2 py-0.5 rounded-full text-xs font-semibold ${clasesStatusPrograma(status)}`;
        chip.textContent = status || 'Sin status';
    }
    // Sin capturar arranca en ✓: 1 toque = ✓, 2 toques = ✗.
    for (const campo of CAMPOS) {
        const b = ciclo(campo);
        if (b) pintarCiclo(b, okInicial(d[campo]));
    }
    const obs = m.querySelector<HTMLInputElement>('#calidad-obs');
    if (obs) obs.value = d.obs ?? '';

    abrirModalBase(MODAL);
    obs?.focus();
}

/** Refleja lo guardado en el botón de la fila (ícono, tooltip y data-ok*). */
function actualizarBoton(boton: HTMLElement, obs: string, oks: Record<CampoOk, 0 | 1 | null>): void {
    for (const campo of CAMPOS) {
        const v = oks[campo];
        boton.dataset[campo] = v === null ? '' : String(v);
    }
    const tieneObs = obs.trim() !== '';
    boton.dataset.obs = obs;
    boton.title = tieneObs ? obs : 'Calidad (sin observaciones)';
    boton.classList.toggle('text-blue-700', tieneObs);
    boton.classList.toggle('text-blue-500', !tieneObs);
    const i = boton.querySelector('i');
    if (i) i.className = `fa-solid ${tieneObs ? 'fa-clipboard-check' : 'fa-clipboard-list'} text-sm ${tieneObs ? 'text-blue-700' : 'text-blue-500'}`;
}

async function guardarCalidad(): Promise<void> {
    const boton = botonActual;
    const m = modal();
    if (!boton || !m) return;
    const folio = boton.dataset.folio ?? '';
    const formulacionId = boton.dataset.id ?? '';
    const obs = m.querySelector<HTMLInputElement>('#calidad-obs')?.value ?? '';
    const oks = Object.fromEntries(CAMPOS.map((c) => [c, okDesdeDato(ciclo(c)?.dataset.value)])) as Record<CampoOk, 0 | 1 | null>;

    const cuerpo: Record<string, unknown> = {
        obs_calidad: obs,
        ok_tiempo: oks.oktiempo,
        ok_viscocidad: oks.okviscocidad,
        ok_solidos: oks.oksolidos,
    };
    if (formulacionId) cuerpo.formulacion_id = formulacionId;

    try {
        exigirExito(await http.put<RespuestaApi>(rutaCon(estado.cfg.rutas.formulacion, { folio }), cuerpo), 'Error al guardar');
        actualizarBoton(boton, obs, oks);
        cerrarModalBase(MODAL);
        notify.success(formulacionId ? 'Calidad actualizada' : 'Calidad creada');
    } catch (err) {
        if (err instanceof HttpError && err.status === 422 && err.errors) {
            void notify.validation(err.errors);
            return;
        }
        console.error('Error al guardar calidad:', err);
        void notify.alert(mensajeError(err, 'No se pudieron guardar las observaciones'), 'Error', 'error');
    }
}

export function iniciarCalidad(): void {
    const m = modal();
    if (!m) return; // sin permiso 'registrar' no se pinta el modal ni los botones
    delegate<HTMLButtonElement>(m, 'click', '[data-calidad-ciclo]', (_e, b) => pintarCiclo(b, okSiguiente(b.dataset.value)));
    delegate(m, 'click', '[data-calidad-accion="guardar"]', () => void guardarCalidad());
    m.querySelector<HTMLInputElement>('#calidad-obs')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            void guardarCalidad();
        }
    });
}
