/**
 * Modal de observaciones por telar y horario (antes un Swal con HTML armado a mano):
 * x-ui.modal-base #modal-observaciones con el catálogo de fallas.
 */
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { MAX_OBSERVACION } from '../comun/logica.ts';
import { abrirModal, cerrarModal } from '../comun/modal.ts';
import { cfg, estado, type Falla } from './estado.ts';
import { guardarAutomatico } from './guardado.ts';
import { horarioTomado, sincronizarTituloObs } from './tabla.ts';

const MODAL = 'modal-observaciones';

let fallas: Falla[] | null = null;
let cargando: Promise<Falla[]> | null = null;

export function precargarFallas(): Promise<Falla[]> {
    if (fallas) return Promise.resolve(fallas);
    cargando ??= http
        .get<{ success?: boolean; data?: Falla[] }>(cfg().rutas.fallas)
        .then((r) => (r.success && Array.isArray(r.data) ? r.data : []))
        .catch(() => [] as Falla[])
        .then((lista) => {
            fallas = lista;
            cargando = null;
            return lista;
        });
    return cargando;
}

interface Abierta {
    checkbox: HTMLInputElement;
    telar: string;
    horario: string;
}

let abierta: Abierta | null = null;

const el = <T extends HTMLElement>(id: string): T | null => document.getElementById(id) as T | null;

function contar(): void {
    const ta = el<HTMLTextAreaElement>('obs-texto');
    const contador = el('obs-contador');
    if (ta && contador) contador.textContent = `${ta.value.length}/${MAX_OBSERVACION}`;
}

function pintarFallas(lista: Falla[]): void {
    const select = el<HTMLSelectElement>('obs-falla');
    if (!select) return;
    const primera = document.createElement('option');
    primera.value = '';
    primera.textContent = '-- Seleccione una clave --';
    const opciones = lista.map((f) => {
        const o = document.createElement('option');
        o.value = String(f.Clave);
        o.textContent = String(f.Clave);
        o.dataset.desc = String(f.Descripcion ?? '');
        return o;
    });
    select.replaceChildren(primera, ...opciones);
}

function prepararModal(telar: string, horario: string, texto: string, soloLectura: boolean): void {
    const t = el('obs-telar');
    const h = el('obs-horario');
    if (t) t.textContent = telar;
    if (h) h.textContent = horario;
    el('obs-falla-campo')?.classList.toggle('hidden', soloLectura);
    el('obs-guardar')?.classList.toggle('hidden', soloLectura);
    el('obs-contador')?.classList.toggle('hidden', soloLectura);
    const cancelar = el('obs-cancelar');
    if (cancelar) cancelar.textContent = soloLectura ? 'Cerrar' : 'Cancelar';
    const ta = el<HTMLTextAreaElement>('obs-texto');
    if (ta) {
        ta.value = texto;
        ta.readOnly = soloLectura;
        ta.classList.toggle('bg-gray-100', soloLectura);
        ta.classList.toggle('text-gray-700', soloLectura);
    }
    contar();
}

export async function abrirObservaciones(checkbox: HTMLInputElement): Promise<void> {
    const telar = checkbox.dataset.telar ?? '';
    const horario = checkbox.dataset.horario ?? '';
    const clave = `${telar}-${horario}`;
    const actual = estado.observaciones[clave] ?? '';
    const soloLectura = cfg().soloLectura || checkbox.dataset.readonly === '1';
    // El clic ya cambió la marca: en solo lectura se regresa; al editar, hasta que se guarde.
    if (soloLectura) checkbox.checked = !!actual.trim();

    const h = parseInt(horario, 10);
    if (!horarioTomado(h)) {
        notify.warning(`Toma primero la hora del horario ${h}`);
        checkbox.checked = !!actual;
        return;
    }

    if (soloLectura) {
        if (!actual.trim()) {
            notify.info(`Sin observaciones: Telar ${telar} - Horario ${horario}`);
            return;
        }
        abierta = null;
        prepararModal(telar, horario, actual, true);
        abrirModal(MODAL);
        return;
    }

    checkbox.checked = !!actual;
    pintarFallas(await precargarFallas());
    abierta = { checkbox, telar, horario };
    prepararModal(telar, horario, actual, false);
    abrirModal(MODAL);
    el('obs-texto')?.focus();
}

function guardar(): void {
    if (!abierta) return;
    const { checkbox, telar, horario } = abierta;
    const valor = (el<HTMLTextAreaElement>('obs-texto')?.value ?? '').slice(0, MAX_OBSERVACION);
    estado.observaciones[`${telar}-${horario}`] = valor;
    checkbox.checked = valor.trim() !== '';
    sincronizarTituloObs(telar, Number(horario), valor);
    abierta = null;
    cerrarModal(MODAL);
    guardarAutomatico();
    notify.success(`Observación guardada: Telar ${telar} - Horario ${horario}`);
}

export function instalarObservaciones(): void {
    el('obs-texto')?.addEventListener('input', contar);
    el<HTMLSelectElement>('obs-falla')?.addEventListener('change', (ev) => {
        const select = ev.currentTarget as HTMLSelectElement;
        const desc = select.options[select.selectedIndex]?.dataset.desc ?? '';
        const ta = el<HTMLTextAreaElement>('obs-texto');
        if (desc && ta) {
            ta.value = desc.slice(0, MAX_OBSERVACION);
            contar();
        }
    });
    el('obs-guardar')?.addEventListener('click', guardar);
}
