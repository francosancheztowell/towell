/**
 * Tabla de requerimientos: filas desde <template>, tipo/hilo, botón Siguiente y
 * navegación a Creación de Órdenes.
 */
import { notify } from '../../../utils/notifications.ts';
import { urlConTelares } from '../comun/contrato-flujo.ts';
import { campo, clonar, tipoDeFila, type Dom, type Estado } from './estado.ts';
import {
    CAMPOS_REQUERIDOS,
    estiloTipo,
    fechaHoyISO,
    formatearNumeroInput,
    normalizarTipo,
    primerCampoFaltante,
    puedeContinuar,
    telaresParaCreacion,
    type FilaCapturada,
    type TelarEntrada,
} from './logica.ts';
import { guardarCampoTelar } from './servidor.ts';

const CLASES_ERROR = ['border-red-500', 'ring-2', 'ring-red-200'] as const;

function opcion(valor: string, texto = valor): HTMLOptionElement {
    return new Option(texto, valor);
}

/** Opciones del select Hilo según el tipo; conserva la selección si sigue existiendo. */
export function llenarHilos(estado: Estado, select: HTMLSelectElement, tipo: string): void {
    const actual = select.value;
    const hilos = estado.hilos[normalizarTipo(tipo).toUpperCase()] ?? [];
    select.replaceChildren(opcion('', 'Seleccione...'), ...hilos.map((h) => opcion(h)));
    select.value = hilos.includes(actual) ? actual : '';
}

function pintarTipo(select: HTMLSelectElement): void {
    const { backgroundColor, color } = estiloTipo(select.value);
    select.style.backgroundColor = backgroundColor;
    select.style.color = color;
}

function crearFila(estado: Estado, grupo: TelarEntrada[], indice: number): HTMLTableRowElement {
    const telar = grupo[0]!;
    const tipo = normalizarTipo(telar.tipo);
    const noTelar = telar.no_telar ?? '';

    const tr = clonar<HTMLTableRowElement>('tpl-fila-requerimiento');
    tr.dataset.index = String(indice);
    tr.dataset.telarId = noTelar;
    tr.dataset.inventarioId = telar.id !== null && telar.id !== undefined && telar.id !== '' ? String(telar.id) : '';
    tr.dataset.fecha = telar.fecha ?? '';
    tr.dataset.turno = telar.turno !== null && telar.turno !== undefined && telar.turno !== '' ? String(telar.turno) : '';

    campo(tr, 'telar')!.value = grupo.map((t) => t.no_telar).join(', ');
    campo(tr, 'fecha_req')!.value = telar.fecha_req || fechaHoyISO();

    const t = telar as TelarEntrada & { tamaño?: string; inventSizeId?: string };
    campo(tr, 'tamano')!.value = t.tamano || t.tamaño || t.inventSizeId || '';
    campo(tr, 'cuenta')!.value = telar.cuenta || '';
    campo(tr, 'calibre')!.value = telar.calibre !== null && telar.calibre !== undefined ? String(telar.calibre) : '';

    const selTipo = campo<HTMLSelectElement>(tr, 'tipo')!;
    selTipo.value = tipo === 'Pie' ? 'Pie' : 'Rizo';
    pintarTipo(selTipo);

    llenarHilos(estado, campo<HTMLSelectElement>(tr, 'hilo')!, tipo);

    const selUrdido = campo<HTMLSelectElement>(tr, 'urdido')!;
    selUrdido.replaceChildren(...estado.cfg.opcionesUrdido.map((u) => opcion(u)));
    if (telar.urdido && estado.cfg.opcionesUrdido.includes(telar.urdido)) selUrdido.value = telar.urdido;

    campo<HTMLSelectElement>(tr, 'tipo_atado')!.value = telar.tipo_atado === 'Especial' ? 'Especial' : 'Normal';
    campo(tr, 'metros')!.value = telar.metros ? formatearNumeroInput(telar.metros) : '';
    campo(tr, 'kilos')!.value = telar.kilos ? formatearNumeroInput(telar.kilos) : '';

    for (const input of tr.querySelectorAll<HTMLElement>('[data-field]')) {
        if (noTelar) input.dataset.telarId = noTelar;
    }
    return tr;
}

export function pintarFilas(estado: Estado, dom: Dom, grupos: TelarEntrada[][]): void {
    estado.grupos = grupos;
    dom.cuerpo.replaceChildren(...grupos.map((g, i) => crearFila(estado, g, i)));
}

export function pintarVacio(dom: Dom): void {
    dom.cuerpo.replaceChildren(clonar('tpl-requerimientos-vacio'));
}

export function pintarErrorValidacion(dom: Dom, mensaje: string): void {
    const fila = clonar('tpl-requerimientos-error');
    const slot = fila.querySelector('[data-slot="mensaje"]');
    if (slot) slot.textContent = mensaje;
    dom.cuerpo.replaceChildren(fila);
}

export function filas(dom: Dom): HTMLTableRowElement[] {
    return [...dom.cuerpo.querySelectorAll<HTMLTableRowElement>('tr[data-index]')];
}

function leerFila(estado: Estado, tr: HTMLTableRowElement): FilaCapturada {
    const valor = (nombre: string) => campo<HTMLInputElement | HTMLSelectElement>(tr, nombre)?.value ?? '';
    const grupo = estado.grupos[Number(tr.dataset.index)] ?? [];
    return {
        telar: valor('telar'),
        fecha_req: valor('fecha_req'),
        cuenta: valor('cuenta'),
        calibre: valor('calibre'),
        tamano: valor('tamano'),
        hilo: valor('hilo'),
        urdido: valor('urdido'),
        tipo: valor('tipo') || 'Rizo',
        tipo_atado: valor('tipo_atado'),
        metros: valor('metros'),
        kilos: valor('kilos'),
        grupo: grupo.map((t) => ({ no_telar: t.no_telar ?? null })),
    };
}

export function actualizarBotonSiguiente(estado: Estado, dom: Dom): void {
    if (dom.siguiente) dom.siguiente.disabled = !puedeContinuar(filas(dom).map((f) => leerFila(estado, f)));
}

/** Cambio de Tipo: color, guardado y hilos del nuevo tipo (Rizo y Pie no comparten catálogo). */
export async function cambiarTipo(estado: Estado, select: HTMLSelectElement): Promise<void> {
    const fila = select.closest('tr');
    if (!fila) return;
    pintarTipo(select);
    const hilo = campo<HTMLSelectElement>(fila, 'hilo');
    if (hilo) llenarHilos(estado, hilo, select.value);
    await guardarCampoTelar(estado, 'tipo', select.value, fila, select.value);
}

export async function cambiarHilo(estado: Estado, select: HTMLSelectElement): Promise<void> {
    const fila = select.closest('tr');
    if (fila) await guardarCampoTelar(estado, 'hilo', select.value.trim(), fila, tipoDeFila(fila));
}

/** Valida, arma el contrato y navega a Creación de Órdenes con ?telares=. */
export function continuar(estado: Estado, dom: Dom): void {
    const trs = filas(dom);
    for (const tr of trs) {
        for (const { campo: nombre } of CAMPOS_REQUERIDOS) campo(tr, nombre)?.classList.remove(...CLASES_ERROR);
    }

    const capturadas = trs.map((tr) => leerFila(estado, tr));
    const faltante = primerCampoFaltante(capturadas);
    if (faltante) {
        const input = campo(trs[faltante.indice], faltante.campo);
        input?.classList.add(...CLASES_ERROR);
        input?.focus();
        input?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        void notify.alert(faltante.mensaje, 'Campos requeridos', 'warning');
        return;
    }

    const telares = telaresParaCreacion(capturadas);
    if (!telares.length) {
        notify.warning('No hay telares para procesar');
        return;
    }
    window.location.href = urlConTelares(estado.cfg.rutas.creacionOrdenes, telares);
}
