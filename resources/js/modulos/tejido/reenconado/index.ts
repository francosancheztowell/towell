/**
 * Producción Reenconado Cabezuela (19-02). Antes: 749 líneas de <script> inline en
 * resources/views/modulos/produccion-reenconado-cabezuela.blade.php (axios crudo, Swal y
 * filas armadas con innerHTML sin escapar).
 */
import { hoyLocal } from '../cortes-eficiencia/comun/logica.ts';
import { delegate, onReady, qs, qsa } from '../../../utils/dom.ts';
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { leerDatos, mensajeError, type RespuestaApi } from '../comun/pagina.ts';
import { filaSinResultados } from '../../urdido/comun/pagina.ts';
import {
    CAMPOS,
    DATASET,
    datasetDeRegistro,
    eficiencia,
    opcionesCalibres,
    opcionesColores,
    opcionesFibras,
    pasaFiltro,
    textoCelda,
    turnoPorMinuto,
    validar,
    type CampoRegistro,
    type Filtro,
    type Opcion,
    type Registro,
} from './logica.ts';

interface ConfigReenconado {
    usuario: string;
    rutas: {
        calibres: string;
        fibras: string;
        colores: string;
        store: string;
        generarFolio: string;
        update: string;
        destroy: string;
    };
}

interface RespuestaLista<T> extends RespuestaApi {
    data?: T[];
}

interface RespuestaRegistro extends RespuestaApi {
    data?: Registro;
}

interface RespuestaFolio extends RespuestaApi {
    folio?: string;
    fecha?: string;
    turno?: string | number;
    usuario?: string;
    numero_empleado?: string;
}

interface Selecciones {
    calibre?: string;
    fibra?: string;
    codColor?: string;
    colorName?: string;
}

// Cebra, hover y selección: .tabla-cebra / .tabla-seleccionable (app.css). Celda: mismas clases que flux:table.cell.
const CLASES_FILA = 'table-row';
const CLASES_CELDA = 'py-3 px-3 text-sm text-center whitespace-nowrap text-zinc-500 border-t border-zinc-800/10';
const TODOS: readonly CampoRegistro[] = [...CAMPOS.slice(0, 3), 'numero_empleado', ...CAMPOS.slice(3)];

/** Fecha local (no UTC: de las 18:00 en adelante toISOString() daba mañana en CDMX). */
const hoyISO = (): string => hoyLocal(new Date());

function iniciar(): void {
    const raiz = qs('#pagina-reenconado');
    const cfg = leerDatos<ConfigReenconado>(raiz);
    const tbody = qs<HTMLTableSectionElement>('#rows-body');
    const modal = qs('#modalNuevo');
    if (!raiz || !cfg || !tbody || !modal) return;

    const btnEditar = qs<HTMLButtonElement>('#btn-editar');
    const btnEliminar = qs<HTMLButtonElement>('#btn-eliminar');
    const btnGuardar = qs<HTMLButtonElement>('#btn-guardar-nuevo');
    const titulo = qs('#modal-title');
    const calibreEl = qs<HTMLSelectElement>('#f_Calibre');
    const fibraEl = qs<HTMLSelectElement>('#f_FibraTrama');
    const codColorEl = qs<HTMLSelectElement>('#f_CodColor');

    let seleccionada: HTMLTableRowElement | null = null;
    let modo: 'create' | 'edit' = 'create';
    let guardando = false;
    // "Mis registros" (botón del navbar) arranca encendido, como antes el filtro de operador.
    // Calibre y demás columnas: filtros por columna de la tabla (componentes/tabla-columnas.ts).
    let misRegistros = !!cfg.usuario;
    const filtro = (): Filtro => ({ operador: misRegistros ? cfg.usuario : '', calibre: '' });

    const cache = {
        calibres: null as string[] | null,
        fibras: new Map<string, string[]>(),
        colores: new Map<string, Opcion[]>(),
    };

    /* ---------- Campos del modal ---------- */
    const campo = (id: string): HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null =>
        document.getElementById(id) as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null;
    const leer = (id: string): string | null => campo(id)?.value || null;
    const poner = (id: string, valor: unknown): void => {
        const el = campo(id);
        if (el) el.value = valor === null || valor === undefined ? '' : String(valor);
    };

    const registroDelModal = (): Registro => {
        const r = {} as Record<CampoRegistro, string | null>;
        for (const c of TODOS) r[c] = leer('f_' + c);
        return r as Registro;
    };

    const cargarFilaEnModal = (fila: HTMLTableRowElement): void => {
        for (const c of TODOS) poner('f_' + c, fila.dataset[DATASET[c]]);
    };

    const opcionesSelect = (select: HTMLSelectElement | null, opciones: ReadonlyArray<string | Opcion>, placeholder: string, valor = ''): void => {
        if (!select) return;
        const nodos = [new Option(placeholder, '')];
        for (const op of opciones) {
            const o = typeof op === 'string' ? new Option(op, op) : new Option(op.label, op.value);
            if (typeof op !== 'string' && op.name) o.dataset.name = op.name;
            nodos.push(o);
        }
        select.replaceChildren(...nodos);
        select.value = valor || '';
        select.disabled = opciones.length === 0;
    };

    const asegurarOpcion = (select: HTMLSelectElement | null, valor: string | undefined, etiqueta: string, nombre = ''): void => {
        if (!select || !valor) return;
        if (Array.from(select.options).some((o) => o.value === valor)) return;
        const o = new Option(etiqueta || valor, valor);
        if (nombre) o.dataset.name = nombre;
        select.appendChild(o);
    };

    /* ---------- Catálogos (calibre → fibras y colores) ---------- */
    const obtenerCalibres = async (): Promise<string[]> => {
        if (cache.calibres) return cache.calibres;
        opcionesSelect(calibreEl, [], 'Cargando...');
        try {
            const r = await http.get<RespuestaLista<{ ItemId?: string }>>(cfg.rutas.calibres);
            cache.calibres = opcionesCalibres(r?.data);
            return cache.calibres;
        } catch {
            notify.error('No se pudieron cargar calibres');
            return [];
        }
    };

    const obtenerFibras = async (itemId: string): Promise<string[]> => {
        const guardadas = cache.fibras.get(itemId);
        if (guardadas) return guardadas;
        try {
            const r = await http.get<RespuestaLista<{ ConfigId?: string }>>(cfg.rutas.fibras, { params: { itemId } });
            const items = opcionesFibras(r?.data);
            cache.fibras.set(itemId, items);
            return items;
        } catch {
            notify.error('No se pudieron cargar fibras');
            return [];
        }
    };

    const obtenerColores = async (itemId: string): Promise<Opcion[]> => {
        const guardados = cache.colores.get(itemId);
        if (guardados) return guardados;
        try {
            const r = await http.get<RespuestaLista<{ InventColorId?: string; Name?: string }>>(cfg.rutas.colores, { params: { itemId } });
            const items = opcionesColores(r?.data);
            cache.colores.set(itemId, items);
            return items;
        } catch {
            notify.error('No se pudieron cargar colores');
            return [];
        }
    };

    const limpiarDependientes = (): void => {
        opcionesSelect(fibraEl, [], 'Selecciona calibre');
        opcionesSelect(codColorEl, [], 'Selecciona calibre');
        poner('f_Color', '');
    };

    const cargarDependientes = async (itemId: string | undefined, sel: Selecciones = {}): Promise<void> => {
        if (!itemId) {
            limpiarDependientes();
            return;
        }
        opcionesSelect(fibraEl, [], 'Cargando...');
        opcionesSelect(codColorEl, [], 'Cargando...');
        const [fibras, colores] = await Promise.all([obtenerFibras(itemId), obtenerColores(itemId)]);
        opcionesSelect(fibraEl, fibras, 'Selecciona fibra', sel.fibra ?? '');
        opcionesSelect(codColorEl, colores, 'Selecciona color', sel.codColor ?? '');
        if (sel.fibra && fibraEl) {
            asegurarOpcion(fibraEl, sel.fibra, sel.fibra);
            fibraEl.value = sel.fibra;
        }
        if (sel.codColor && codColorEl) {
            asegurarOpcion(codColorEl, sel.codColor, sel.codColor, sel.colorName);
            codColorEl.value = sel.codColor;
        }
        poner('f_Color', codColorEl?.selectedOptions[0]?.dataset.name || sel.colorName || '');
    };

    const iniciarSelectores = async (sel: Selecciones = {}): Promise<void> => {
        const calibres = await obtenerCalibres();
        opcionesSelect(calibreEl, calibres, 'Selecciona calibre', sel.calibre ?? '');
        if (sel.calibre && calibreEl) {
            asegurarOpcion(calibreEl, sel.calibre, sel.calibre);
            calibreEl.value = sel.calibre;
        }
        await cargarDependientes(sel.calibre, sel);
    };

    /* ---------- Modal ---------- */
    const abrirModal = (): void => {
        qsa<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, textarea, select', modal).forEach((el) => (el.value = ''));
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        window.setTimeout(() => calibreEl?.focus(), 100);
    };
    const cerrarModal = (): void => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };
    const modalAbierto = (): boolean => !modal.classList.contains('hidden');

    const ponerTitulo = (texto: string, icono?: string): void => {
        if (!titulo) return;
        if (!icono) {
            titulo.textContent = texto;
            return;
        }
        const i = document.createElement('i');
        i.className = icono;
        i.setAttribute('aria-hidden', 'true');
        titulo.replaceChildren(i, texto);
    };

    const estadoGuardando = (activo: boolean): void => {
        guardando = activo;
        if (!btnGuardar) return;
        btnGuardar.disabled = activo;
        const i = document.createElement('i');
        i.className = activo ? 'fa fa-spinner fa-spin mr-2' : 'fa fa-save mr-2';
        i.setAttribute('aria-hidden', 'true');
        btnGuardar.replaceChildren(i, activo ? 'Guardando...' : 'Guardar');
    };

    /* ---------- Tabla ---------- */
    const filas = (): HTMLTableRowElement[] => qsa<HTMLTableRowElement>('tr.table-row', tbody);

    const pintarFila = (fila: HTMLTableRowElement, r: Partial<Registro>): void => {
        const celdas = CAMPOS.map((c) => {
            const td = document.createElement('td');
            td.className = CLASES_CELDA;
            td.textContent = textoCelda(c, r[c]);
            return td;
        });
        fila.replaceChildren(...celdas);
        Object.assign(fila.dataset, datasetDeRegistro(r));
    };

    const filaNueva = (r: Partial<Registro>): HTMLTableRowElement => {
        const fila = document.createElement('tr');
        fila.className = CLASES_FILA;
        pintarFila(fila, r);
        return fila;
    };

    const actualizarBotones = (): void => {
        const hay = seleccionada !== null;
        if (btnEditar) btnEditar.disabled = !hay;
        if (btnEliminar) btnEliminar.disabled = !hay;
    };

    const seleccionar = (fila: HTMLTableRowElement | null): void => {
        seleccionada?.setAttribute('aria-selected', 'false');
        seleccionada = fila;
        fila?.setAttribute('aria-selected', 'true');
        actualizarBotones();
    };

    delegate<HTMLTableRowElement>(tbody, 'click', 'tr.table-row', (_ev, fila) => seleccionar(fila));

    /* ---------- Guardar / nuevo / editar / eliminar ---------- */
    async function guardar(): Promise<void> {
        if (guardando) return;
        const registro = registroDelModal();
        const falta = validar(registro);
        if (falta) {
            notify.warning(falta);
            return;
        }
        estadoGuardando(true);
        try {
            if (modo === 'edit' && registro.Folio) {
                const url = cfg!.rutas.update.replace('__F__', encodeURIComponent(registro.Folio));
                const r = await http.put<RespuestaRegistro>(url, { record: registro });
                if (r?.success && r.data) {
                    if (seleccionada) pintarFila(seleccionada, r.data);
                    cerrarModal();
                    notify.success('Registro actualizado');
                    aplicarFiltros();
                } else {
                    notify.error('No se pudo actualizar');
                }
            } else {
                const r = await http.post<RespuestaRegistro>(cfg!.rutas.store, { modal: 1, record: registro });
                if (r?.success && r.data) {
                    tbody!.prepend(filaNueva(r.data));
                    cerrarModal();
                    notify.success('Registro guardado exitosamente');
                    aplicarFiltros();
                } else {
                    notify.error('No se pudo guardar el registro');
                }
            }
        } catch (err) {
            // 422: el primer error de campo; si no, el mensaje (ya sin detalle interno) del servidor.
            notify.error(mensajeError(err, 'Error al guardar el registro'));
        } finally {
            estadoGuardando(false);
        }
    }

    async function nuevo(): Promise<void> {
        modo = 'create';
        seleccionar(null);
        abrirModal();
        ponerTitulo('Nuevo Registro de Producción');
        limpiarDependientes();
        await iniciarSelectores();

        const respaldo = (): void => {
            const ahora = new Date();
            poner('f_Folio', `TEMP-${Date.now()}`);
            poner('f_Date', hoyISO());
            poner('f_Turno', turnoPorMinuto(ahora.getHours() * 60 + ahora.getMinutes()));
        };
        try {
            const r = await http.post<RespuestaFolio>(cfg!.rutas.generarFolio);
            if (r?.success) {
                poner('f_Folio', r.folio ?? '');
                poner('f_Date', r.fecha || hoyISO());
                poner('f_Turno', r.turno || '1');
                poner('f_nombreEmpl', r.usuario);
                poner('f_numero_empleado', r.numero_empleado);
            } else {
                respaldo();
            }
        } catch {
            respaldo();
        }
    }

    async function editar(): Promise<void> {
        const fila = seleccionada;
        if (!fila) {
            notify.info('Selecciona un registro');
            return;
        }
        modo = 'edit';
        abrirModal();
        cargarFilaEnModal(fila);
        await iniciarSelectores({
            calibre: fila.dataset.calibre || '',
            fibra: fila.dataset.fibratrama || '',
            codColor: fila.dataset.codcolor || '',
            colorName: fila.dataset.color || '',
        });
        ponerTitulo(`Editar Registro · Folio ${fila.dataset.folio || ''}`, 'fa fa-edit mr-2');
    }

    async function eliminar(): Promise<void> {
        const fila = seleccionada;
        if (!fila) {
            notify.info('Selecciona un registro');
            return;
        }
        const folio = fila.dataset.folio;
        if (!folio) {
            notify.error('Folio inválido');
            return;
        }
        const ok = await notify.confirm({
            title: `¿Eliminar folio ${folio}?`,
            text: 'Esta acción no se puede deshacer',
            confirmText: 'Sí, eliminar',
            confirmColor: '#dc2626',
        });
        if (!ok) return;
        try {
            const r = await http.delete<RespuestaApi>(cfg!.rutas.destroy.replace('__F__', encodeURIComponent(folio)));
            if (r?.success) {
                fila.remove();
                seleccionar(null);
                modo = 'create';
                notify.success('Registro eliminado');
                aplicarFiltros();
            } else {
                void notify.alert('No se pudo eliminar', 'Error', 'error');
            }
        } catch (err) {
            void notify.alert(mensajeError(err, 'Error al eliminar el registro'), 'Error', 'error');
        }
    }

    /* ---------- Filtros ---------- */
    function aplicarFiltros(): void {
        let visibles = 0;
        for (const fila of filas()) {
            const ver = pasaFiltro({ operador: fila.dataset.nombreempl, calibre: fila.dataset.calibre }, filtro());
            fila.hidden = !ver; // hidden, no display: la cebra cuenta solo las visibles
            if (ver) visibles++;
        }
        qs('[data-accion="mis-registros"]')?.setAttribute('aria-pressed', String(misRegistros));
        filaSinResultados(tbody, 14, visibles === 0 ? 'Sin resultados con los filtros aplicados' : null);
    }

    /* ---------- Eventos ---------- */
    const acciones: Record<string, () => void> = {
        'cerrar-modal': cerrarModal,
        guardar: () => void guardar(),
        'mis-registros': () => {
            misRegistros = !misRegistros;
            aplicarFiltros();
        },
    };
    delegate(document, 'click', '[data-accion]', (_ev, el) => acciones[el.dataset.accion ?? '']?.());

    // Botones del navbar (componentes x-navbar.*: solo existen con permiso).
    qs('#btn-nuevo')?.addEventListener('click', () => void nuevo());
    btnEditar?.addEventListener('click', () => void editar());
    btnEliminar?.addEventListener('click', () => void eliminar());

    calibreEl?.addEventListener('change', () => {
        limpiarDependientes();
        void cargarDependientes(calibreEl.value);
    });
    codColorEl?.addEventListener('change', () => poner('f_Color', codColorEl.selectedOptions[0]?.dataset.name || ''));

    // Enter en observaciones no mete salto de línea (la columna es de 60 caracteres).
    campo('f_Obs')?.addEventListener('keydown', (ev) => {
        if ((ev as KeyboardEvent).key === 'Enter') {
            ev.preventDefault();
            (ev.target as HTMLElement).blur();
        }
    });

    // Eficiencia automática con la misma fórmula que el servidor.
    const recalcular = (): void => poner('f_Eficiencia', eficiencia(leer('f_Cantidad'), leer('f_Horas')));
    for (const id of ['f_Cantidad', 'f_Horas']) {
        campo(id)?.addEventListener('input', recalcular);
        campo(id)?.addEventListener('blur', recalcular);
    }

    document.addEventListener('keydown', (ev) => {
        if (ev.key !== 'Escape') return;
        if (modalAbierto()) cerrarModal();
    });

    actualizarBotones();
    aplicarFiltros();
}

onReady(iniciar);
