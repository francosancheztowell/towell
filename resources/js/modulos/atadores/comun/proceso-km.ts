/**
 * Tarjetas Montado / Enhebrado de Karl Mayer (vista calificar-atadores/_proceso-km.blade.php).
 * Las usan calificar (barra KM) y la pantalla propia /atadores/calificar/{montado|enhebrado}.
 * Cada cambio se guarda solo (action km_montado / km_enhebrado de /atadores/save).
 */
import { delegate } from '../../../utils/dom.ts';
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { exigirOk, mensajeError, type RespuestaAtadores } from './pagina.ts';
import {
    cveDeEmpleado,
    empleadoRepetido,
    fechaHoraLocal,
    nombreDeEmpleado,
    nombreTarjeta,
    textoEmpleado,
    type EmpleadoApi,
} from './proceso-km-logica.ts';

export interface ConfigProcesoKm {
    guardar: string;
    empleados: string;
    noJulio: string;
    noOrden: string;
    yo: { cve: string; nombre: string; area: string } | null;
}

const FILAS = [1, 2, 3] as const;

function select(prefijo: string, n: number): HTMLSelectElement | null {
    return document.getElementById(`${prefijo}_cve${n}`) as HTMLSelectElement | null;
}

function opcion(cve: string, nombre: string): HTMLOptionElement {
    const op = new Option(textoEmpleado(cve, nombre), cve);
    if (nombre) op.dataset.nombre = nombre;
    return op;
}

function sincronizarNombre(sel: HTMLSelectElement): void {
    const destino = document.getElementById(sel.dataset.nombreDestino ?? '') as HTMLInputElement | null;
    if (destino) destino.value = sel.selectedOptions[0]?.dataset.nombre ?? '';
}

function valoresTarjeta(prefijo: string): string[] {
    return FILAS.map((n) => select(prefijo, n)?.value ?? '');
}

function ponerInicioSiVacio(prefijo: string): void {
    const inicio = document.getElementById(`${prefijo}_inicio`) as HTMLInputElement | null;
    if (inicio && !inicio.disabled && !inicio.value) inicio.value = fechaHoraLocal(new Date());
}

function avisar(titulo: string, texto: string): void {
    void notify.alert(texto, titulo, 'warning');
}

export function iniciarProcesoKm(raiz: Element | Document, cfg: ConfigProcesoKm): void {
    const guardar = async (prefijo: string): Promise<void> => {
        const valor = (id: string): string => (document.getElementById(`${prefijo}_${id}`) as HTMLInputElement | null)?.value ?? '';
        try {
            const res = await http.post<RespuestaAtadores>(cfg.guardar, {
                action: `km_${prefijo}`,
                no_julio: cfg.noJulio,
                no_orden: cfg.noOrden,
                cve1: valor('cve1'), nombre1: valor('nombre1'),
                cve2: valor('cve2'), nombre2: valor('nombre2'),
                cve3: valor('cve3'), nombre3: valor('nombre3'),
                fecha_inicio: valor('inicio'),
                fecha_fin: valor('fin'),
            });
            exigirOk(res, 'No se pudo guardar');
            notify.success('Guardado');
        } catch (err) {
            void notify.alert(mensajeError(err, 'No se pudo guardar'), 'Error', 'error');
        }
    };

    delegate<HTMLSelectElement>(raiz, 'focusin', 'select[data-km-empleado]', (_ev, sel) => {
        sel.dataset.prev = sel.value;
    });

    delegate<HTMLSelectElement>(raiz, 'change', 'select[data-km-empleado]', (_ev, sel) => {
        const prefijo = sel.dataset.kmEmpleado ?? '';
        const fila = Number(sel.dataset.fila);
        if (empleadoRepetido(valoresTarjeta(prefijo), fila, sel.value)) {
            avisar('Empleado repetido', `Ese empleado ya está en otra fila de ${nombreTarjeta(prefijo)}. Cada persona solo puede estar en una.`);
            sel.value = sel.dataset.prev ?? '';
            sincronizarNombre(sel);
            return;
        }
        sel.dataset.prev = sel.value;
        sincronizarNombre(sel);
        if (sel.value && fila === 1) ponerInicioSiVacio(prefijo);
        void guardar(prefijo);
    });

    delegate<HTMLInputElement>(raiz, 'change', 'input[data-km-fecha]', (_ev, input) => {
        void guardar(input.dataset.kmFecha ?? '');
    });

    delegate<HTMLElement>(raiz, 'click', '[data-accion="km-asignarme"]', (_ev, boton) => {
        const prefijo = boton.dataset.prefijo ?? '';
        const fila = Number(boton.dataset.fila);
        const yo = cfg.yo;
        if (!yo?.cve) {
            avisar('Sin sesión', 'No se pudo identificar al usuario actual.');
            return;
        }
        const sel = select(prefijo, fila);
        if (!sel || sel.disabled) return;
        if (empleadoRepetido(valoresTarjeta(prefijo), fila, yo.cve)) {
            avisar('Ya estás registrado', `Ya estás en otra fila de ${nombreTarjeta(prefijo)}. Cada persona solo puede estar en una.`);
            return;
        }
        if (![...sel.options].some((o) => o.value === yo.cve)) sel.add(opcion(yo.cve, yo.nombre));
        sel.value = yo.cve;
        sincronizarNombre(sel);
        if (fila === 1) ponerInicioSiVacio(prefijo);
        void guardar(prefijo);
    });

    void llenarEmpleados(raiz, cfg);
}

/** Opciones de los selects con los empleados del área del usuario (una consulta por página). */
async function llenarEmpleados(raiz: Element | Document, cfg: ConfigProcesoKm): Promise<void> {
    const selects = [...raiz.querySelectorAll<HTMLSelectElement>('select[data-km-empleado]')];
    if (!selects.length || !cfg.yo?.area) return;

    let empleados: EmpleadoApi[] = [];
    try {
        const datos = await http.get<unknown>(`${cfg.empleados}/${encodeURIComponent(cfg.yo.area)}`);
        empleados = Array.isArray(datos) ? (datos as EmpleadoApi[]) : [];
    } catch {
        return; // Sin lista: quedan la opción guardada y "Asignarme".
    }

    for (const sel of selects) {
        const actual = sel.dataset.valorActual || sel.value;
        for (const e of empleados) {
            const cve = cveDeEmpleado(e);
            if (!cve) continue;
            const nombre = nombreDeEmpleado(e);
            const existente = [...sel.options].find((o) => o.value === cve);
            if (!existente) {
                sel.add(opcion(cve, nombre));
            } else if (nombre && !existente.dataset.nombre) {
                existente.dataset.nombre = nombre;
                existente.textContent = textoEmpleado(cve, nombre);
            }
        }
        if (actual) sel.value = actual;
        sincronizarNombre(sel);
    }
}
