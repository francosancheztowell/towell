/**
 * Modal de oficiales (hasta 3 por registro): alta/edición/eliminación, validación de No.
 * Operador y Turno repetidos, texto de la celda y propagación a las filas siguientes.
 * Vista: resources/views/modulos/urdido/produccion/_modal-oficial.blade.php
 */
import { HttpError, http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { delegate } from '../../../utils/dom.ts';
import { el, icono, mensajeError, type RespuestaApi } from '../comun/pagina.ts';
import { alerta, ctx, enviar, filaDe, marcarCampoError, requireCanEdit } from './contexto.ts';
import {
    aOficialesFila,
    leerOficiales,
    metrosDe,
    modoPropagacion,
    motivoRechazo,
    oficialEn,
    oficialesSinMetros,
    repetidos,
    resumenOficiales,
    sumaMetros,
    type OficialPayload,
} from './logica.ts';

interface UsuarioUrdido {
    numero_empleado: string;
    nombre: string;
    turno?: string | number | null;
}

const NUMEROS = [1, 2, 3] as const;
const CLASES_OK = ['border-gray-300', 'focus:ring-blue-500', 'focus:border-blue-500'];
const CLASES_ERROR = ['border-red-500', 'focus:ring-red-500', 'focus:border-red-500'];

let usuarios: UsuarioUrdido[] = [];
let modal: HTMLElement | null = null;
let cuerpo: HTMLElement | null = null;
let inputRegistro: HTMLInputElement | null = null;

// ─── Usuarios ───────────────────────────────────────────────────────

export async function cargarUsuariosUrdido(): Promise<void> {
    try {
        const r = await http.get<RespuestaApi & { data?: UsuarioUrdido[] }>(ctx.cfg.rutas.usuarios);
        usuarios = r.success && r.data ? r.data : [];
        if (!r.success) console.error('Error al cargar usuarios:', r.error ?? r.message);
    } catch (error) {
        console.error('Error al cargar usuarios de Urdido:', error);
        usuarios = [];
    }
}

// ─── Controles del modal ────────────────────────────────────────────

interface Controles {
    select: HTMLSelectElement | null;
    clave: HTMLInputElement | null;
    nombre: HTMLInputElement | null;
    turno: HTMLSelectElement | null;
    metros: HTMLInputElement | null;
    eliminar: HTMLButtonElement | null;
}

function controles(numero: number | string): Controles {
    const q = <T extends Element>(sel: string): T | null => cuerpo?.querySelector<T>(`${sel}[data-numero="${numero}"]`) ?? null;
    return {
        select: q('select.select-oficial-nombre'),
        clave: q('input.input-oficial-clave'),
        nombre: q('input.input-oficial-nombre'),
        turno: q('select.input-oficial-turno'),
        metros: q('input.input-oficial-metros'),
        eliminar: q('button.btn-eliminar-oficial'),
    };
}

function habilitarEliminar(boton: HTMLButtonElement | null, habilitado: boolean): void {
    if (!boton) return;
    boton.disabled = !habilitado;
    boton.classList.toggle('opacity-50', !habilitado);
    boton.classList.toggle('cursor-not-allowed', !habilitado);
}

function limpiarPosicion(c: Controles): void {
    if (c.select) c.select.value = '';
    if (c.clave) c.clave.value = '';
    if (c.nombre) c.nombre.value = '';
    if (c.turno) c.turno.value = '';
    if (c.metros) c.metros.value = '';
    habilitarEliminar(c.eliminar, false);
}

// ─── Repetidos ──────────────────────────────────────────────────────

function repetidosEnModal(): { claves: Map<string, number[]>; turnos: Map<string, number[]> } {
    const claves: { numero: number; valor: string }[] = [];
    const turnos: { numero: number; valor: string }[] = [];
    for (const n of NUMEROS) {
        const c = controles(n);
        const clave = (c.clave?.value ?? '').trim();
        const turno = (c.turno?.value ?? '').trim();
        claves.push({ numero: n, valor: clave });
        if (clave && turno) turnos.push({ numero: n, valor: turno });
    }
    return { claves: repetidos(claves), turnos: repetidos(turnos) };
}

function marcar(control: Element | null, error: boolean): void {
    if (!control) return;
    control.classList.remove(...(error ? CLASES_OK : CLASES_ERROR));
    control.classList.add(...(error ? CLASES_ERROR : CLASES_OK));
}

/** Marca en rojo claves/turnos repetidos; con `avisar` muestra el primero. true = sin repetidos. */
function validarRepetidos(avisar: boolean): boolean {
    const { claves, turnos } = repetidosEnModal();
    for (const n of NUMEROS) {
        const c = controles(n);
        const clave = (c.clave?.value ?? '').trim();
        const turno = (c.turno?.value ?? '').trim();
        marcar(c.select, !!clave && (claves.get(clave)?.includes(n) ?? false));
        marcar(c.turno, !!turno && (turnos.get(turno)?.includes(n) ?? false));
    }
    const claveRepetida = claves.entries().next().value;
    if (avisar && claveRepetida) {
        errorModal(`El No. Operador ${claveRepetida[0]} está repetido entre oficiales (${claveRepetida[1].join(', ')}).`);
    }
    const turnoRepetido = turnos.entries().next().value;
    if (avisar && turnoRepetido) {
        errorModal(
            `El Turno ${turnoRepetido[0]} está repetido entre oficiales (${turnoRepetido[1].join(', ')}). No puede haber dos oficiales con el mismo turno.`,
        );
    }
    return claves.size === 0 && turnos.size === 0;
}

function errorModal(mensaje: string): void {
    alerta('warning', 'Acción no permitida', mensaje);
}

// ─── Render de las 3 filas ──────────────────────────────────────────

const CLASE_CONTROL = 'w-full border border-gray-300 rounded px-2 py-1 text-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500';

function filaOficial(numero: number, oficial: ReturnType<typeof oficialEn>): HTMLTableRowElement {
    const n = String(numero);
    const clave = String(oficial.clave ?? '');
    const nombre = String(oficial.nombre ?? '');
    const turno = oficial.turno === null || oficial.turno === undefined ? '' : String(oficial.turno);

    const select = el('select', { clase: `${CLASE_CONTROL} select-oficial-nombre`, attrs: { 'data-numero': n, 'aria-label': `Oficial ${n}` } });
    select.add(new Option('Seleccionar empleado...', ''));
    for (const u of usuarios) {
        const opcion = new Option(u.nombre, u.numero_empleado);
        opcion.dataset.numeroEmpleado = u.numero_empleado;
        opcion.dataset.nombre = u.nombre;
        opcion.dataset.turno = u.turno === null || u.turno === undefined ? '' : String(u.turno);
        select.add(opcion);
    }
    select.value = clave;
    if (select.value !== clave) select.value = '';

    const inputClave = el('input', { clase: 'input-oficial-clave', attrs: { type: 'hidden', 'data-numero': n } });
    inputClave.value = clave;

    const inputNombre = el('input', {
        clase: 'w-full border border-gray-300 rounded px-2 py-1 text-md bg-gray-50 cursor-not-allowed input-oficial-nombre',
        attrs: { type: 'text', 'data-numero': n, placeholder: 'Se selecciona automáticamente', readonly: '' },
    });
    inputNombre.value = select.value ? (select.selectedOptions[0]?.dataset.nombre ?? nombre) : nombre;

    const selectTurno = el('select', { clase: `${CLASE_CONTROL} input-oficial-turno`, attrs: { 'data-numero': n, 'aria-label': `Turno del oficial ${n}` } });
    selectTurno.add(new Option('Seleccionar...', ''));
    for (const t of ['1', '2', '3', '4']) selectTurno.add(new Option(t, t));
    selectTurno.value = turno;

    const inputMetros = el('input', {
        clase: `${CLASE_CONTROL} input-oficial-metros`,
        attrs: { type: 'number', step: '0.01', min: '0', 'data-numero': n, placeholder: '0.00', 'aria-label': `Metros del oficial ${n}` },
    });
    inputMetros.value = oficial.metros === null || oficial.metros === undefined ? '' : String(oficial.metros);

    const tieneOficial = nombre !== '' || select.value !== '';
    const eliminar = el(
        'button',
        {
            clase: 'btn-eliminar-oficial px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded transition-colors',
            attrs: { type: 'button', 'data-numero': n, title: 'Eliminar oficial', 'aria-label': `Eliminar oficial ${n}` },
        },
        icono('fa-solid fa-trash text-sm'),
    );
    habilitarEliminar(eliminar, tieneOficial);

    const td = (clase = ''): HTMLTableCellElement => el('td', { clase: `px-3 py-2 border border-gray-300 ${clase}`.trim() });
    const tdOficial = td();
    tdOficial.append(select, inputClave);
    const tdNombre = td('hidden');
    tdNombre.append(inputNombre);
    const tdTurno = td();
    tdTurno.append(selectTurno);
    const tdMetros = td();
    tdMetros.append(inputMetros);
    const tdEliminar = td('text-center');
    tdEliminar.append(eliminar);

    return el('tr', { clase: 'hover:bg-gray-50' }, tdOficial, tdNombre, tdTurno, tdMetros, tdEliminar);
}

function renderizarOficiales(registroId: string): void {
    const fila = filaDe(registroId);
    if (!fila || !cuerpo) return;
    const oficiales = leerOficiales(fila.querySelector<HTMLElement>('.oficial-texto')?.dataset.oficialesJson);
    cuerpo.replaceChildren(...NUMEROS.map((n) => filaOficial(n, oficialEn(oficiales, n))));
    validarRepetidos(false);
}

async function abrirModalOficial(registroId: string): Promise<void> {
    if (!filaDe(registroId) || !modal) return;
    if (usuarios.length === 0) await cargarUsuariosUrdido();
    renderizarOficiales(registroId);
    if (inputRegistro) inputRegistro.value = registroId;
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function cerrarModalOficial(): void {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.style.display = 'none';
    cuerpo?.replaceChildren();
}

export function agregarOficial(boton: HTMLElement, ev: Event): void {
    if (!requireCanEdit()) return;
    ev.preventDefault();
    if ((boton as HTMLButtonElement).disabled) return;
    const registroId = boton.dataset.registroId;
    if (registroId) void abrirModalOficial(registroId);
}

// ─── Selección de empleado ──────────────────────────────────────────

function alElegirEmpleado(select: HTMLSelectElement): void {
    const numero = select.dataset.numero ?? '';
    const c = controles(numero);
    const opcion = select.selectedOptions[0];

    if (opcion && opcion.value) {
        if (c.clave) c.clave.value = opcion.value;
        if (c.nombre) c.nombre.value = opcion.dataset.nombre || opcion.textContent || '';
        const turno = opcion.dataset.turno || '';
        if (c.turno && turno) c.turno.value = turno;
        habilitarEliminar(c.eliminar, true);

        if (!validarRepetidos(true)) {
            limpiarPosicion(c);
            validarRepetidos(false);
            return;
        }
    } else {
        if (c.clave) c.clave.value = '';
        if (c.nombre) c.nombre.value = '';
        if (c.turno) c.turno.value = '';
        habilitarEliminar(c.eliminar, false);
    }
    validarRepetidos(false);
}

// ─── Celda de oficiales en la tabla ─────────────────────────────────

function actualizarOficialesEnTabla(registroId: string, oficiales: OficialPayload[], actualizarMetros = true): void {
    const fila = filaDe(registroId);
    const celda = fila?.querySelector<HTMLElement>('.oficial-texto');
    if (!fila || !celda) return;

    const paraJson = aOficialesFila(oficiales);
    celda.dataset.oficialesJson = JSON.stringify(paraJson);

    if (paraJson.length === 0) {
        celda.replaceChildren(el('div', { clase: 'text-gray-400 italic', texto: 'Sin oficiales' }));
    } else {
        const { codigos, nombres } = resumenOficiales(paraJson);
        celda.replaceChildren(
            el('div', { clase: 'text-gray-800 font-semibold', texto: codigos }),
            ...nombres.map((o) =>
                el('div', { clase: 'text-xs text-gray-600' }, `${o.nombre} `, el('span', { clase: 'text-amber-600', texto: `(T${o.turno})` })),
            ),
        );
    }

    // Al propagar hacia abajo no se tocan los metros de la fila.
    if (actualizarMetros) {
        const metros = fila.querySelector<HTMLInputElement>('input[data-field="metros"]');
        if (metros) metros.value = String(sumaMetros(oficiales));
    }

    const agregar = fila.querySelector<HTMLButtonElement>('.btn-agregar-oficial');
    if (agregar) {
        agregar.dataset.cantidadOficiales = String(oficiales.length);
        agregar.disabled = oficiales.length >= 3;
        agregar.classList.toggle('text-gray-400', agregar.disabled);
        agregar.classList.toggle('cursor-not-allowed', agregar.disabled);
        agregar.classList.toggle('opacity-50', agregar.disabled);
        agregar.classList.toggle('text-blue-600', !agregar.disabled);
        agregar.classList.toggle('hover:text-blue-800', !agregar.disabled);
        agregar.classList.toggle('hover:bg-blue-50', !agregar.disabled);
    }
}

function oficialesDelModal(excluir?: number): OficialPayload[] {
    const lista: OficialPayload[] = [];
    for (const n of NUMEROS) {
        if (n === excluir) continue;
        const c = controles(n);
        const clave = (c.clave?.value ?? '').trim();
        const nombre = (c.nombre?.value ?? '').trim();
        const metros = (c.metros?.value ?? '').trim();
        if (!clave && !nombre) continue;
        lista.push({
            numero_oficial: n,
            cve_empl: clave || null,
            nom_empl: nombre || null,
            turno: c.turno?.value || null,
            metros: metros ? parseFloat(metros) : null,
        });
    }
    return lista;
}

// ─── Eliminar ───────────────────────────────────────────────────────

async function eliminarOficial(boton: HTMLButtonElement): Promise<void> {
    if (boton.disabled || !requireCanEdit()) return;
    const numero = Number(boton.dataset.numero);
    const registroId = inputRegistro?.value;
    if (!registroId) return;

    const ok = await notify.confirm({
        title: '¿Eliminar oficial?',
        text: 'Se eliminará este oficial del registro',
        confirmText: 'Sí, eliminar',
        confirmColor: '#dc2626',
    });
    if (!ok) return;

    try {
        await enviar(ctx.cfg.rutas.eliminarOficial, { registro_id: registroId, numero_oficial: numero }, 'Error al eliminar oficial');
    } catch (err) {
        console.error(err);
        errorModal(mensajeError(err, 'Error al eliminar el oficial'));
        return;
    }
    limpiarPosicion(controles(numero));
    actualizarOficialesEnTabla(registroId, oficialesDelModal(numero).filter((o) => o.cve_empl));
    notify.success('Oficial eliminado');
}

// ─── Guardar ────────────────────────────────────────────────────────

async function guardarOficiales(): Promise<void> {
    if (!requireCanEdit()) return;
    const registroId = inputRegistro?.value;
    if (!registroId) {
        alerta('error', 'Error', 'No se encontró el registro');
        return;
    }

    const oficiales = oficialesDelModal();
    // Metros obligatorio y > 0: mismo criterio que el servidor.
    const sinMetros = oficialesSinMetros(oficiales);
    for (const o of oficiales) marcarCampoError(controles(o.numero_oficial).metros, sinMetros.includes(o.numero_oficial));
    if (sinMetros.length) {
        errorModal(`Captura los Metros (mayores a cero) del Oficial ${sinMetros.join(', ')}.`);
        return;
    }
    if (!validarRepetidos(true)) return;

    const guardados: OficialPayload[] = [];
    const avisosTurno: string[] = [];
    const rechazos: string[] = [];
    try {
        for (const oficial of oficiales) {
            try {
                const r = await http.post<RespuestaApi & { warning?: string | null }>(ctx.cfg.rutas.guardarOficial, { registro_id: registroId, ...oficial });
                if (r.success) {
                    guardados.push(oficial);
                    if (r.warning) avisosTurno.push(r.warning);
                } else {
                    rechazos.push(motivoRechazo(r));
                }
            } catch (err) {
                // Rechazo del servidor (4xx/5xx): se conserva el motivo. Sin respuesta: error general.
                if (err instanceof HttpError && err.status > 0) rechazos.push(motivoRechazo(err.data));
                else throw err;
            }
        }
    } catch (error) {
        console.error('Error al guardar oficiales:', error);
        alerta('error', 'Error', 'Error al guardar oficiales. Por favor, intenta nuevamente.');
        return;
    }

    if (guardados.length === 0) {
        alerta(
            'error',
            'No se guardó ningún oficial',
            rechazos.length ? [...new Set(rechazos)].join(' ') : 'Asegúrate de llenar al menos la clave o el nombre.',
        );
        return;
    }

    actualizarOficialesEnTabla(registroId, guardados);
    cerrarModalOficial();
    notify.success('Los oficiales han sido guardados correctamente');
    // Avisos de turno duplicado (no bloquean, solo informan).
    if (avisosTurno.length) notify.warning([...new Set(avisosTurno)].join(' '));
    // Propagación hacia abajo (excepto filas con H. Inicio), después de que se vea el aviso.
    setTimeout(() => void propagarOficialesHaciaAbajo(registroId, guardados), 500);
}

// ─── Propagación a las filas siguientes ─────────────────────────────

async function guardarEnFila(registroId: string, o: OficialPayload, metros: number): Promise<boolean> {
    try {
        const r = await http.post<RespuestaApi>(ctx.cfg.rutas.guardarOficial, {
            registro_id: registroId,
            numero_oficial: o.numero_oficial,
            cve_empl: o.cve_empl,
            nom_empl: o.nom_empl,
            turno: o.turno,
            metros,
        });
        return r.success === true;
    } catch {
        return false;
    }
}

/**
 * Propaga a las filas siguientes hasta la primera con H. Inicio:
 *  - con Oficial 2: el 2 pasa a ser el Oficial 1 (el 1 anterior se quita).
 *  - sin Oficial 2: se copian todos con su mismo número.
 * guardar-oficial exige metros > 0: se mandan los metros que YA tiene esa fila en esa posición
 * (el esqueleto trae Metros1 de la orden). Sin metros no se toca la fila: antes se quitaba el
 * Oficial 1 y el alta del nuevo fallaba con 422, dejando la fila sin oficial.
 */
async function propagarOficialesHaciaAbajo(registroIdActual: string, oficiales: OficialPayload[]): Promise<void> {
    const filas = Array.from(ctx.tabla?.querySelectorAll<HTMLTableRowElement>('tr[data-registro-id]') ?? []);
    const indice = filas.findIndex((f) => f.dataset.registroId === registroIdActual);
    if (indice === -1) return;
    const plan = modoPropagacion(oficiales);

    for (const fila of filas.slice(indice + 1)) {
        const registroId = fila.dataset.registroId;
        if (!registroId) continue;
        if ((fila.querySelector<HTMLInputElement>('input[data-field="h_inicio"]')?.value ?? '').trim() !== '') break;

        const actuales = leerOficiales(fila.querySelector<HTMLElement>('.oficial-texto')?.dataset.oficialesJson);
        try {
            if (plan.modo === 'segundo') {
                const metros = metrosDe(actuales, 1);
                if (metros === null) continue;
                try {
                    await http.post(ctx.cfg.rutas.eliminarOficial, { registro_id: registroId, numero_oficial: 1 });
                } catch (err) {
                    const motivo = err instanceof HttpError ? motivoRechazo(err.data) : '';
                    if (!motivo.includes('Registro no encontrado')) continue;
                }
                const nuevo = { ...plan.segundo, numero_oficial: 1, metros };
                if (await guardarEnFila(registroId, nuevo, metros)) actualizarOficialesEnTabla(registroId, [nuevo], false);
            } else {
                let todos = true;
                const copiados: OficialPayload[] = [];
                for (const o of oficiales) {
                    const metros = metrosDe(actuales, o.numero_oficial);
                    if (metros === null || !(await guardarEnFila(registroId, o, metros))) {
                        todos = false;
                        continue;
                    }
                    copiados.push({ ...o, metros });
                }
                if (todos) actualizarOficialesEnTabla(registroId, copiados, false);
            }
        } catch (error) {
            console.error(`Error al propagar oficiales a registro ${registroId}:`, error);
        }
    }
}

// ─── Cableado ───────────────────────────────────────────────────────

export function iniciarModalOficial(): void {
    modal = document.getElementById('modal-oficial');
    cuerpo = document.getElementById('oficiales-existentes');
    inputRegistro = document.getElementById('modal-registro-id') as HTMLInputElement | null;
    if (!modal) return;

    delegate(modal, 'click', '[data-accion-oficial="cerrar"]', cerrarModalOficial);
    delegate(modal, 'click', '[data-accion-oficial="guardar"]', () => void guardarOficiales());
    delegate<HTMLButtonElement>(modal, 'click', '.btn-eliminar-oficial', (ev, boton) => {
        ev.preventDefault();
        void eliminarOficial(boton);
    });
    delegate<HTMLSelectElement>(modal, 'change', 'select.select-oficial-nombre', (_ev, select) => alElegirEmpleado(select));
    modal.addEventListener('click', (ev) => {
        if (ev.target === modal) cerrarModalOficial();
    });
}
