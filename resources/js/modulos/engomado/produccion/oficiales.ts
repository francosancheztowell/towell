/**
 * Producción Engomado — oficiales: celda de la fila, modal "Oficiales" (alta, edición,
 * baja) y propagación a las filas siguientes.
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { delegate } from '../../../utils/dom.ts';
import { el, exigirExito, mensajeError, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import { cfg, cuerpoTabla, filaDe, requireCanEdit } from './contexto.ts';
import {
    clavesRepetidas,
    etiquetaOficial,
    filasAPropagar,
    mensajesDuplicados,
    metrosParaPropagar,
    oficialNumero,
    oficialesAGuardar,
    oficialesParaCelda,
    parsearOficiales,
    planPropagacion,
    sumaMetros,
    tooltipOficiales,
    turnosRepetidos,
    type CapturaOficial,
    type OficialFila,
    type OficialGuardar,
} from './logica.ts';

interface UsuarioEngomado {
    numero_empleado: string;
    nombre: string;
    turno: string | number | null;
}

interface RespuestaGuardarOficial extends RespuestaApi {
    warning?: string | null;
}

const NUMEROS = [1, 2, 3] as const;
let usuarios: UsuarioEngomado[] = [];

// ─── Celda de la fila ───────────────────────────────────────────────────

/** Pinta los oficiales en la celda (formato 2 líneas) y actualiza su JSON y tooltip. */
export function pintarOficialesEnCelda(celda: HTMLElement, oficiales: OficialFila[]): void {
    celda.dataset.oficialesJson = JSON.stringify(oficiales);
    celda.title = tooltipOficiales(oficiales);

    if (oficiales.length === 0) {
        celda.replaceChildren(el('div', { clase: 'text-xs', texto: 'Sin oficiales' }));
        celda.classList.add('text-gray-400', 'italic');
        celda.classList.remove('text-gray-900');
        return;
    }

    celda.replaceChildren(
        ...oficiales.map((of) => {
            const { clave, nombreConTurno } = etiquetaOficial(of);
            return el(
                'div',
                { clase: 'oficial-item' },
                el('div', { clase: 'font-semibold text-gray-900 text-sm leading-tight', texto: clave }),
                el('div', { clase: 'text-gray-600 text-xs leading-tight truncate', texto: nombreConTurno }),
            );
        }),
    );
    celda.classList.remove('text-gray-400', 'italic');
    celda.classList.add('text-gray-900', 'space-y-0.5');
}

/** Refleja en la fila los oficiales guardados (celda, turno oculto, metros y botón lápiz). */
export function actualizarOficialesEnTabla(
    registroId: string,
    oficiales: OficialGuardar[],
    { actualizarMetros = true }: { actualizarMetros?: boolean } = {},
): void {
    const fila = filaDe(registroId);
    const celda = fila?.querySelector<HTMLElement>('.oficial-texto');
    if (!fila || !celda) return;

    pintarOficialesEnCelda(celda, oficialesParaCelda(oficiales));

    const primerTurno = oficiales[0]?.turno;
    if (primerTurno) {
        const turno = fila.querySelector<HTMLSelectElement>('select[data-field="turno"]');
        if (turno) turno.value = primerTurno;
    }

    if (actualizarMetros) {
        const suma = sumaMetros(oficiales);
        const metros = fila.querySelector<HTMLInputElement>('input[data-field="metros"]');
        if (metros) metros.value = suma > 0 ? String(suma) : '';
    }

    const btn = fila.querySelector<HTMLButtonElement>('.btn-agregar-oficial');
    if (btn) {
        btn.dataset.cantidadOficiales = String(oficiales.length);
        btn.disabled = oficiales.length >= 3;
        const activo = ['text-blue-600', 'hover:text-blue-800', 'hover:bg-blue-50'];
        const inactivo = ['text-gray-400', 'cursor-not-allowed', 'opacity-50'];
        btn.classList.remove(...(btn.disabled ? activo : inactivo));
        btn.classList.add(...(btn.disabled ? inactivo : activo));
    }
}

// ─── Modal ──────────────────────────────────────────────────────────────

const modal = (): HTMLElement | null => document.getElementById('modal-oficial');
const contenedor = (): HTMLElement | null => document.getElementById('oficiales-existentes');
const registroModal = (): HTMLInputElement | null => document.getElementById('modal-registro-id') as HTMLInputElement | null;

interface ControlesOficial {
    fila: HTMLTableRowElement;
    select: HTMLSelectElement;
    clave: HTMLInputElement;
    nombre: HTMLInputElement;
    turno: HTMLSelectElement;
    metros: HTMLInputElement;
    display: HTMLElement;
    editar: HTMLButtonElement;
    eliminar: HTMLButtonElement;
}

function controles(numero: number | string): ControlesOficial | null {
    const fila = contenedor()?.querySelector<HTMLTableRowElement>(`tr[data-numero="${numero}"]`);
    if (!fila) return null;
    return {
        fila,
        select: fila.querySelector<HTMLSelectElement>('.select-oficial-nombre')!,
        clave: fila.querySelector<HTMLInputElement>('.input-oficial-clave')!,
        nombre: fila.querySelector<HTMLInputElement>('.input-oficial-nombre')!,
        turno: fila.querySelector<HTMLSelectElement>('.input-oficial-turno')!,
        metros: fila.querySelector<HTMLInputElement>('.input-oficial-metros')!,
        display: fila.querySelector<HTMLElement>('.oficial-display')!,
        editar: fila.querySelector<HTMLButtonElement>('.btn-editar-oficial')!,
        eliminar: fila.querySelector<HTMLButtonElement>('.btn-eliminar-oficial')!,
    };
}

function capturaModal(): CapturaOficial[] {
    return NUMEROS.flatMap((n) => {
        const c = controles(n);
        return c ? [{ numero: n, clave: c.clave.value, nombre: c.nombre.value, turno: c.turno.value, metros: c.metros.value }] : [];
    });
}

function habilitarEliminar(c: ControlesOficial, habilitado: boolean): void {
    c.eliminar.disabled = !habilitado;
    c.eliminar.classList.toggle('opacity-50', !habilitado);
    c.eliminar.classList.toggle('cursor-not-allowed', !habilitado);
}

function limpiarOficial(c: ControlesOficial): void {
    c.select.value = '';
    c.clave.value = '';
    c.nombre.value = '';
    c.turno.value = '';
    c.metros.value = '';
    habilitarEliminar(c, false);
}

/** Marca en rojo claves/turnos repetidos; con `avisar` muestra el aviso. Devuelve true si no hay duplicados. */
function validarDuplicados(avisar: boolean): boolean {
    const captura = capturaModal();
    const claves = clavesRepetidas(captura);
    const turnos = turnosRepetidos(captura);
    const ok = ['border-gray-300', 'focus:ring-blue-500', 'focus:border-blue-500'];
    const mal = ['border-red-500', 'focus:ring-red-500', 'focus:border-red-500'];

    for (const n of NUMEROS) {
        const c = controles(n);
        if (!c) continue;
        const clave = c.clave.value.trim();
        const turno = c.turno.value.trim();
        const dupClave = clave !== '' && (claves.get(clave)?.includes(n) ?? false);
        const dupTurno = turno !== '' && (turnos.get(turno)?.includes(n) ?? false);
        c.select.classList.remove(...(dupClave ? ok : mal));
        c.select.classList.add(...(dupClave ? mal : ok));
        c.turno.classList.remove(...(dupTurno ? ok : mal));
        c.turno.classList.add(...(dupTurno ? mal : ok));
    }

    const mensajes = mensajesDuplicados(claves, turnos);
    if (mensajes.length && avisar) void notify.alert(mensajes.join(' '), 'Acción no permitida', 'warning');
    return mensajes.length === 0;
}

function actualizarDisplay(numero: number | string): void {
    const c = controles(numero);
    if (!c) return;
    const clave = c.clave.value.trim();
    const nombre = c.nombre.value.trim();
    const tiene = clave !== '' || nombre !== '';
    if (tiene) {
        const { clave: l1, nombreConTurno } = etiquetaOficial({ clave, nombre, turno: c.turno.value });
        c.display.querySelector('[data-parte="clave"]')!.textContent = l1;
        const l2 = c.display.querySelector<HTMLElement>('[data-parte="nombre"]')!;
        l2.textContent = nombreConTurno;
        l2.title = nombreConTurno;
    }
    c.display.classList.toggle('hidden', !tiene);
    c.select.classList.toggle('hidden', tiene);
    c.editar.classList.toggle('hidden', !tiene);
    habilitarEliminar(c, tiene);
}

async function cargarUsuarios(): Promise<void> {
    try {
        const r = exigirExito(await http.get<RespuestaApi & { data?: UsuarioEngomado[] }>(cfg().rutas.usuarios), 'Error al cargar usuarios');
        usuarios = r.data ?? [];
    } catch (err) {
        console.error('Error al cargar usuarios de Engomado:', mensajeError(err, 'Error al cargar usuarios'));
        usuarios = [];
    }
}

/** Llena el select de operadores; si la clave existe la preselecciona (y copia nombre/turno). */
function poblarSelectUsuarios(c: ControlesOficial, claveSeleccionada: string): void {
    if (!usuarios.length) return;
    c.select.replaceChildren(el('option', { texto: 'Seleccionar...', attrs: { value: '' } }));

    let elegido: UsuarioEngomado | null = null;
    for (const u of usuarios) {
        const opcion = el('option', {
            texto: u.nombre,
            attrs: { value: u.numero_empleado, 'data-nombre': u.nombre, 'data-turno': String(u.turno ?? '') },
        });
        if (claveSeleccionada && u.numero_empleado === claveSeleccionada) {
            opcion.selected = true;
            elegido = u;
        }
        c.select.append(opcion);
    }

    if (elegido) {
        c.nombre.value = elegido.nombre;
        c.clave.value = elegido.numero_empleado;
        if (elegido.turno) c.turno.value = String(elegido.turno);
        c.select.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

function renderizarOficiales(registroId: string): void {
    const cont = contenedor();
    const plantilla = document.getElementById('tpl-fila-oficial') as HTMLTemplateElement | null;
    const celda = filaDe(registroId)?.querySelector<HTMLElement>('.oficial-texto');
    if (!cont || !plantilla) return;

    const oficiales = parsearOficiales(celda?.dataset.oficialesJson);
    cont.replaceChildren();

    for (const n of NUMEROS) {
        const of = oficialNumero(oficiales, n);
        const fila = (plantilla.content.firstElementChild as HTMLTableRowElement).cloneNode(true) as HTMLTableRowElement;
        fila.dataset.numero = String(n);
        fila.querySelectorAll<HTMLElement>('select, input, button').forEach((nodo) => {
            nodo.dataset.numero = String(n);
        });
        cont.append(fila);

        const c = controles(n)!;
        c.clave.value = String(of.clave ?? '');
        c.nombre.value = String(of.nombre ?? '');
        c.turno.value = String(of.turno ?? '');
        c.metros.value = of.metros ? String(of.metros) : '';
        actualizarDisplay(n);
        poblarSelectUsuarios(c, String(of.clave ?? ''));
    }

    validarDuplicados(false);
}

async function abrirModal(registroId: string): Promise<void> {
    if (!filaDe(registroId)) return;
    if (usuarios.length === 0) await cargarUsuarios();

    renderizarOficiales(registroId);
    const input = registroModal();
    if (input) input.value = registroId;

    const m = modal();
    if (!m) return;
    m.classList.remove('hidden');
    m.style.display = 'flex';
}

function cerrarModal(): void {
    const m = modal();
    if (m) {
        m.classList.add('hidden');
        m.style.display = 'none';
    }
    contenedor()?.replaceChildren();
}

function alCambiarOperador(select: HTMLSelectElement): void {
    const numero = select.dataset.numero ?? '';
    const c = controles(numero);
    if (!c) return;
    const opcion = select.options[select.selectedIndex];

    if (opcion && opcion.value) {
        c.clave.value = opcion.value;
        c.nombre.value = opcion.dataset.nombre || opcion.textContent || '';
        if (opcion.dataset.turno) c.turno.value = opcion.dataset.turno;
        habilitarEliminar(c, true);

        if (!validarDuplicados(true)) {
            limpiarOficial(c);
            validarDuplicados(false);
            return;
        }
    } else {
        limpiarOficial(c);
    }
    actualizarDisplay(numero);
    validarDuplicados(false);
}

async function eliminarOficial(boton: HTMLButtonElement): Promise<void> {
    if (boton.disabled || !requireCanEdit()) return;
    const numero = parseInt(boton.dataset.numero ?? '', 10);
    const registroId = registroModal()?.value;
    if (!registroId || !numero) return;

    const confirmado = await notify.confirm({
        title: '¿Eliminar oficial?',
        text: 'Se eliminará este oficial del registro',
        confirmText: 'Sí, eliminar',
        confirmColor: '#dc2626',
    });
    if (!confirmado) return;

    try {
        exigirExito(
            await http.post<RespuestaApi>(cfg().rutas.eliminarOficial, { registro_id: registroId, numero_oficial: numero }),
            'Error al eliminar oficial',
        );
        const c = controles(numero);
        if (c) {
            limpiarOficial(c);
            actualizarDisplay(numero);
        }
        const restantes = oficialesAGuardar(capturaModal().filter((f) => f.numero !== numero && f.clave.trim() !== ''));
        actualizarOficialesEnTabla(registroId, restantes);
        notify.success('Oficial eliminado');
    } catch (err) {
        void notify.alert(mensajeError(err, 'Error al eliminar el oficial'), 'Acción no permitida', 'warning');
    }
}

async function guardarOficiales(): Promise<void> {
    if (!requireCanEdit()) return;
    const registroId = registroModal()?.value;
    if (!registroId) {
        void notify.alert('No se encontró el registro', 'Error', 'error');
        return;
    }

    const oficiales = oficialesAGuardar(capturaModal());
    if (!validarDuplicados(true)) return;

    const guardados: OficialGuardar[] = [];
    const avisosTurno: string[] = [];
    let primerError: string | null = null;
    for (const oficial of oficiales) {
        try {
            const r = exigirExito(
                await http.post<RespuestaGuardarOficial>(cfg().rutas.guardarOficial, { registro_id: registroId, ...oficial }),
                'Error al guardar oficial',
            );
            guardados.push(oficial);
            if (r.warning) avisosTurno.push(r.warning);
        } catch (err) {
            primerError ??= mensajeError(err, 'Error al guardar oficial');
        }
    }

    if (guardados.length === 0) {
        void notify.alert(
            primerError ?? 'No se guardaron oficiales. Asegúrate de llenar al menos la clave o nombre.',
            'Oficiales',
            'warning',
        );
        return;
    }

    actualizarOficialesEnTabla(registroId, guardados);
    cerrarModal();
    notify.success('Los oficiales han sido guardados correctamente');
    if (avisosTurno.length) notify.warning([...new Set(avisosTurno)].join(' '));
    window.setTimeout(() => void propagarOficialesHaciaAbajo(registroId, guardados), 500);
}

/**
 * Copia los oficiales a las filas siguientes hasta la primera con H. Inicio
 * (reglas en logica.ts → planPropagacion).
 */
async function propagarOficialesHaciaAbajo(registroId: string, oficiales: OficialGuardar[]): Promise<void> {
    const filas = Array.from(cuerpoTabla()?.querySelectorAll<HTMLTableRowElement>('tr[data-registro-id]') ?? []);
    const actual = filas.findIndex((f) => f.dataset.registroId === registroId);
    const horas = filas.map((f) => f.querySelector<HTMLInputElement>('input[data-field="h_inicio"]')?.value ?? '');
    const plan = planPropagacion(oficiales);
    const rutas = cfg().rutas;

    for (const i of filasAPropagar(horas, actual)) {
        const fila = filas[i];
        const id = fila?.dataset.registroId;
        if (!fila || !id) continue;

        // guardar-oficial exige metros > 0: se calculan ANTES de borrar nada; si falta alguno
        // la fila se deja como está (antes se borraba el Oficial 1 y el guardado fallaba con 422).
        const actuales = parsearOficiales(fila.querySelector<HTMLElement>('.oficial-texto')?.dataset.oficialesJson);
        const envios = plan.oficiales.map((of) => ({
            of,
            metros: metrosParaPropagar(oficialNumero(actuales, of.numero_oficial).metros, of.metros),
        }));
        if (envios.some((e) => e.metros === null)) continue;

        if (plan.reemplazarPrimero) {
            try {
                exigirExito(await http.post<RespuestaApi>(rutas.eliminarOficial, { registro_id: id, numero_oficial: 1 }), 'Error');
            } catch (err) {
                // Igual que antes: solo "Registro no encontrado" deja seguir con esta fila.
                if (!mensajeError(err, '').includes('Registro no encontrado')) continue;
            }
        }
        let todos = true;
        for (const { of, metros } of envios) {
            try {
                const r = await http.post<RespuestaApi>(rutas.guardarOficial, {
                    registro_id: id,
                    numero_oficial: of.numero_oficial,
                    cve_empl: of.cve_empl,
                    nom_empl: of.nom_empl,
                    turno: of.turno,
                    metros,
                });
                if (!r.success) todos = false;
            } catch (err) {
                console.error('Error propagando oficiales:', mensajeError(err, 'error'));
                todos = false;
            }
        }
        if (todos) {
            const guardados = envios.map(({ of, metros }) => ({ ...of, metros }));
            actualizarOficialesEnTabla(id, guardados, { actualizarMetros: false });
        }
    }
}

/** Instala los eventos del modal y del lápiz de cada fila. */
export function iniciarOficiales(): void {
    void cargarUsuarios();

    delegate<HTMLButtonElement, MouseEvent>(document, 'click', '.btn-agregar-oficial', (e, btn) => {
        if (!requireCanEdit()) return;
        e.preventDefault();
        if (btn.disabled) return;
        const id = btn.dataset.registroId;
        if (id) void abrirModal(id);
    });

    document.getElementById('btn-cerrar-modal')?.addEventListener('click', cerrarModal);
    document.getElementById('btn-cancelar-modal')?.addEventListener('click', cerrarModal);
    document.getElementById('btn-guardar-oficiales')?.addEventListener('click', () => void guardarOficiales());
    const m = modal();
    m?.addEventListener('click', (e) => {
        if (e.target === m) cerrarModal();
    });

    const cont = contenedor();
    if (!cont) return;
    delegate<HTMLButtonElement, MouseEvent>(cont, 'click', '.btn-editar-oficial', (e, btn) => {
        e.preventDefault();
        const c = controles(btn.dataset.numero ?? '');
        if (!c) return;
        c.display.classList.add('hidden');
        c.select.classList.remove('hidden');
        btn.classList.add('hidden');
        c.select.focus();
    });
    delegate<HTMLButtonElement, MouseEvent>(cont, 'click', '.btn-eliminar-oficial', (e, btn) => {
        e.preventDefault();
        void eliminarOficial(btn);
    });
    delegate<HTMLSelectElement>(cont, 'change', '.input-oficial-turno', (_e, sel) => actualizarDisplay(sel.dataset.numero ?? ''));
    delegate<HTMLSelectElement>(cont, 'change', '.select-oficial-nombre', (_e, sel) => alCambiarOperador(sel));
}
