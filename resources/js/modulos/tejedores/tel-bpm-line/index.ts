/**
 * Checklist BPM Tejedores. Vista: resources/views/modulos/bpm-tejedores/tel-bpm-line/index.blade.php
 * (config en data-tel-bpm-line de #tel-bpm-line-pagina). Antes era un <script> en la vista.
 * Los avisos de sesión los pinta x-ui.flash del layout.
 */
import { notify } from '../../../utils/notifications.ts';
import { delegate, onReady, qsa } from '../../../utils/dom.ts';
import { debounce } from '../../../utils/format.ts';
import { icono, leerDatos } from '../../urdido/comun/pagina.ts';
import { CLASES_CELDA, TEXTO_CELDA, TODAS_LAS_CLASES_CELDA, contarIncompletas, normalizarValor } from './logica.ts';

interface ConfigLinea {
    editable: boolean;
    turnoRecibe: string;
    comentarios: string;
    rutas: { toggle: string; comentarios: string; indice: string };
}

interface Respuesta {
    ok: boolean;
    msg?: string;
    valor?: string;
}

const MAX_COMENTARIOS = 150;

declare global {
    interface Window {
        volverAlIndice?: () => void;
    }
}

/** Nunca lanza: sin respuesta del servidor devuelve ok:false con aviso de conexión. */
async function postJson(url: string, datos: Record<string, unknown>): Promise<Respuesta> {
    try {
        return await window.http.post<Respuesta>(url, datos);
    } catch (err) {
        const e = err as { status?: number; data?: { msg?: string } };
        return { ok: false, msg: e.data?.msg ?? (e.status ? 'No se pudo guardar' : 'Sin conexión con el servidor. Intente de nuevo.') };
    }
}

function pintarCelda(boton: HTMLButtonElement, valor: string): void {
    const v = normalizarValor(valor);
    boton.dataset.valor = v;
    boton.classList.remove(...TODAS_LAS_CLASES_CELDA);
    boton.classList.add(...CLASES_CELDA[v]);
    const texto = TEXTO_CELDA[v];
    boton.querySelector('.cell-icon')?.replaceChildren(texto ?? icono('fas fa-wrench'));
}

async function confirmarYEnviar(formId: string, title: string, text?: string, confirmText = 'Aceptar'): Promise<void> {
    const form = document.getElementById(formId) as HTMLFormElement | null;
    if (form && (await notify.confirm({ title, confirmText, ...(text ? { text } : {}) }))) form.submit();
}

function iniciar(raiz: HTMLElement, cfg: ConfigLinea): void {
    window.volverAlIndice = () => {
        window.location.href = cfg.rutas.indice;
    };

    delegate<HTMLButtonElement>(raiz, 'click', '.cell-btn', async (_e, boton) => {
        if (!cfg.editable) {
            notify.info('Edición no permitida');
            return;
        }
        const r = await postJson(cfg.rutas.toggle, {
            Orden: Number.parseInt(boton.dataset.orden ?? '', 10),
            NoTelarId: boton.dataset.telar,
            SalonTejidoId: boton.dataset.salon || null,
            TurnoRecibe: cfg.turnoRecibe,
            Actividad: boton.dataset.actividad,
        });
        if (!r.ok) {
            notify.error(r.msg ?? 'No se pudo guardar');
            return;
        }
        pintarCelda(boton, r.valor ?? '');
    });

    document.getElementById('btn-finish')?.addEventListener('click', () => {
        const incompletas = contarIncompletas(qsa<HTMLButtonElement>('.cell-btn', raiz).map((b) => b.dataset.valor ?? ''));
        void (incompletas > 0
            ? confirmarYEnviar('form-finish', 'No se han completado todas las actividades', '¿Desea continuar de todos modos?', 'Sí, Finalizar')
            : confirmarYEnviar('form-finish', '¿Marcar como Terminado?'));
    });
    document.getElementById('btn-authorize')?.addEventListener('click', () => void confirmarYEnviar('form-authorize', '¿Autorizar folio?'));
    document.getElementById('btn-reject')?.addEventListener('click', () => void confirmarYEnviar('form-reject', '¿Rechazar y regresar a Creado?'));

    iniciarComentarios(cfg);
}

/** Comentarios: guardado automático 3 s después de dejar de escribir. */
function iniciarComentarios(cfg: ConfigLinea): void {
    const area = document.getElementById('comentarios-textarea') as HTMLTextAreaElement | null;
    const contador = document.getElementById('char-count');
    const estado = document.getElementById('comentarios-status');
    let guardado = cfg.comentarios;

    const pintarEstado = (texto: string, error = false): void => {
        if (!estado) return;
        estado.textContent = texto;
        estado.className = `text-xs ${error ? 'text-red-500' : 'text-slate-400'}`;
    };

    const guardar = debounce(async () => {
        if (!area || area.value.trim() === guardado.trim()) return;
        if (area.value.length > MAX_COMENTARIOS) {
            notify.error('Los comentarios no pueden superar 150 caracteres.');
            return;
        }
        pintarEstado('Guardando...');
        const r = await postJson(cfg.rutas.comentarios, { Comentarios: area.value });
        if (!r.ok) {
            pintarEstado('Error', true);
            notify.error(r.msg ?? 'No se pudo guardar el comentario.');
            return;
        }
        guardado = area.value;
        pintarEstado('Guardado');
        setTimeout(() => pintarEstado(''), 2000);
        notify.success(r.msg ?? 'Comentario guardado correctamente.');
    }, 3000);

    area?.addEventListener('input', () => {
        const largo = area.value.length;
        if (contador) contador.textContent = String(largo);
        area.classList.toggle('border-red-500', largo > MAX_COMENTARIOS);
        if (cfg.editable) guardar();
    });
}

onReady(() => {
    const raiz = document.getElementById('tel-bpm-line-pagina');
    const cfg = leerDatos<ConfigLinea>(raiz, 'telBpmLine');
    if (raiz && cfg) iniciar(raiz, cfg);
});
