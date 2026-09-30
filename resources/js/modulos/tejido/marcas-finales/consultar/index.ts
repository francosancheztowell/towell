/**
 * Consultar Marcas Finales (19-02): selección de folio, editar, visualizar, finalizar,
 * edición de supervisor y reporte por fecha.
 * Vista: resources/views/modulos/marcas-finales/marcasFinales.blade.php (config en data-pagina).
 */
import { delegate, onReady, qs, qsa } from '../../../../utils/dom.ts';
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { alertaError, exigirExito, leerDatos, type RespuestaApi } from '../../comun/pagina.ts';
import {
    botonesDeshabilitados,
    EN_PROCESO,
    fechaIso,
    lineasIncompletas,
    resumenIncompletas,
    validarRegistro,
    type DatosRegistro,
} from './logica.ts';

interface ConfigConsultar {
    ultimoFolio: string | null;
    esSupervisor: boolean;
    rutas: {
        nuevo: string;
        show: string;
        visualizar: string;
        finalizar: string;
        actualizarRegistro: string;
        reporte: string;
    };
}

interface Marca {
    Folio?: string;
    Date?: string | null;
    Turno?: number | string | null;
    Status?: string | null;
    numero_empleado?: string | null;
    nombreEmpl?: string | null;
    turno_capturista?: number | string | null;
}

interface RespuestaShow extends RespuestaApi {
    marca?: Marca;
    lineas?: Record<string, unknown>[];
}

const CLASES_SELECCION = ['bg-blue-100', 'border-l-4', 'border-blue-600'];

function iniciar(): void {
    const raiz = qs('#pagina-marcas-consultar');
    const cfg = leerDatos<ConfigConsultar>(raiz);
    if (!raiz || !cfg) return;
    const rutas = cfg.rutas;
    const conFolio = (plantilla: string, folio: string): string => plantilla.replace('__FOLIO__', encodeURIComponent(folio));

    let folio: string | null = null;
    let status: string | null = null;

    const boton = (id: string): HTMLButtonElement | null => qs<HTMLButtonElement>(`#${id}`);
    const actualizarBotones = (): void => {
        const d = botonesDeshabilitados(folio, status);
        const poner = (id: string, deshabilitado: boolean): void => {
            const b = boton(id);
            if (b) b.disabled = deshabilitado;
        };
        poner('btn-nuevo', false);
        poner('btn-editar', d.editar);
        poner('btn-finalizar', d.finalizar);
        poner('btn-visualizar', d.visualizar);
        poner('btn-editar-supervisor', d.supervisor);
    };

    /* ---------- Selección ---------- */
    const seleccionar = (fila: HTMLTableRowElement): void => {
        const f = fila.dataset.folio ?? null;
        if (f === folio) return;
        folio = f;
        status = fila.dataset.status ?? null;
        qsa<HTMLTableRowElement>('tr.marca-row', raiz).forEach((tr) => {
            tr.classList.remove(...CLASES_SELECCION);
            tr.setAttribute('aria-selected', 'false');
        });
        fila.classList.add(...CLASES_SELECCION);
        fila.setAttribute('aria-selected', 'true');
        actualizarBotones();
    };
    delegate<HTMLTableRowElement>(raiz, 'click', 'tr.marca-row', (_ev, fila) => seleccionar(fila));
    delegate<HTMLTableRowElement, KeyboardEvent>(raiz, 'keydown', 'tr.marca-row', (ev, fila) => {
        if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            seleccionar(fila);
        }
    });

    const sinSeleccion = (texto: string): boolean => {
        if (folio) return false;
        void notify.alert(texto, 'Sin selección', 'warning');
        return true;
    };

    const cargarDetalle = async (f: string): Promise<RespuestaShow> =>
        exigirExito(await http.get<RespuestaShow>(conFolio(rutas.show, f)), 'No se pudo obtener el detalle');

    /* ---------- Editar / visualizar ---------- */
    function editar(): void {
        if (sinSeleccion('Selecciona un folio para editar')) return;
        if (status !== EN_PROCESO) {
            void notify.alert('Solo puedes editar folios con estado "En Proceso".', 'Edición no disponible', 'info');
            return;
        }
        window.location.href = `${rutas.nuevo}?folio=${encodeURIComponent(folio!)}`;
    }

    function visualizar(): void {
        if (sinSeleccion('Selecciona un folio para visualizar')) return;
        window.location.href = conFolio(rutas.visualizar, folio!);
    }

    /* ---------- Finalizar ---------- */
    async function validarParaFinalizar(f: string): Promise<boolean> {
        void notify.loading('Validando...');
        let lineas: Record<string, unknown>[];
        try {
            const d = await cargarDetalle(f);
            lineas = Array.isArray(d.lineas) ? d.lineas : [];
        } finally {
            notify.close();
        }
        if (lineas.length === 0) {
            await notify.alert('No puedes finalizar un folio sin líneas capturadas.', 'No hay líneas', 'warning');
            return false;
        }
        const incompletas = lineasIncompletas(lineas);
        if (incompletas.length === 0) return true;
        return notify.confirm({
            title: 'Hay campos sin valor',
            text: resumenIncompletas(incompletas),
            confirmText: 'Sí, continuar',
            cancelText: 'No, revisar',
        });
    }

    async function finalizar(): Promise<void> {
        if (sinSeleccion('Selecciona un folio para finalizar')) return;
        if (status !== EN_PROCESO) {
            void notify.alert('Solo puedes finalizar folios con estado "En Proceso".', 'No se puede finalizar', 'info');
            return;
        }
        const f = folio!;
        try {
            if (!(await validarParaFinalizar(f))) return;
        } catch (err) {
            alertaError(err, 'No se pudo validar el folio');
            return;
        }
        const ok = await notify.confirm({
            title: '¿Finalizar Marca?',
            text: `El folio ${f} quedará cerrado y no podrá editarse.`,
            confirmText: 'Sí, finalizar',
            confirmColor: '#ea580c',
        });
        if (!ok) return;
        void notify.loading('Finalizando...');
        try {
            exigirExito(await http.post<RespuestaApi>(conFolio(rutas.finalizar, f)), 'No se pudo finalizar');
            notify.close();
            await notify.alert('El registro se ha cerrado correctamente.', '¡Finalizado!', 'success');
            window.location.reload();
        } catch (err) {
            notify.close();
            alertaError(err, 'No se pudo finalizar');
        }
    }

    /* ---------- Modal de fechas (reporte) ---------- */
    const modalFechas = qs('#modal-fechas');
    const inputFecha = qs<HTMLInputElement>('#input-fechas');
    const abrirFechas = (): void => {
        modalFechas?.classList.remove('hidden');
        inputFecha?.focus();
    };
    const cerrarFechas = (): void => modalFechas?.classList.add('hidden');
    function generarReporte(): void {
        const fecha = inputFecha?.value ?? '';
        cerrarFechas();
        if (!fecha) {
            void notify.alert('Selecciona una fecha para generar el reporte.', 'Fecha requerida', 'warning');
            return;
        }
        window.location.href = `${rutas.reporte}?fecha=${encodeURIComponent(fecha)}`;
    }

    /* ---------- Modal de supervisor ---------- */
    const modalEditar = qs('#modal-editar-registro');
    const campo = <T extends HTMLInputElement | HTMLSelectElement>(id: string): T | null => qs<T>(`#${id}`);
    const cerrarEdicion = (): void => modalEditar?.classList.add('hidden');

    function abrirEdicion(marca: Marca): void {
        if (!modalEditar) return;
        const titulo = qs('#edit-folio-title');
        if (titulo) titulo.textContent = ` - ${marca.Folio ?? ''}`;
        const poner = (id: string, valor: string): void => {
            const el = campo(id);
            if (el) el.value = valor;
        };
        poner('edit-fecha', fechaIso(marca.Date));
        poner('edit-turno', String(marca.Turno || '1'));
        poner('edit-empleado', marca.numero_empleado || '');
        poner('edit-nombre', marca.nombreEmpl || '');
        poner('edit-status', marca.Status || EN_PROCESO);
        // El Turno del folio es la ventana de reloj; el capturista puede ser turno 4.
        qs('#edit-turno-capturista')?.classList.toggle('hidden', Number(marca.turno_capturista) !== 4);
        modalEditar.classList.remove('hidden');
        campo('edit-fecha')?.focus();
    }

    async function editarSupervisor(): Promise<void> {
        if (sinSeleccion('Selecciona un folio para editar')) return;
        void notify.loading('Cargando datos...');
        try {
            const d = await cargarDetalle(folio!);
            notify.close();
            if (d.marca) abrirEdicion(d.marca);
        } catch (err) {
            notify.close();
            alertaError(err, 'No se pudieron cargar los datos del folio');
        }
    }

    async function guardarRegistro(): Promise<void> {
        const f = folio;
        if (!f) return;
        const valor = (id: string): string => campo(id)?.value ?? '';
        const datos: DatosRegistro = {
            Date: valor('edit-fecha'),
            Turno: valor('edit-turno'),
            numero_empleado: valor('edit-empleado'),
            nombreEmpl: valor('edit-nombre'),
            Status: valor('edit-status'),
        };
        const error = validarRegistro(datos);
        if (error) {
            void notify.alert(error, 'Campos requeridos', 'warning');
            return;
        }
        void notify.loading('Guardando cambios...');
        try {
            const d = exigirExito(await http.put<RespuestaApi>(conFolio(rutas.actualizarRegistro, f), datos), 'No se pudo actualizar el registro');
            notify.close();
            cerrarEdicion();
            await notify.alert(d.message || 'Registro actualizado correctamente.', '¡Actualizado!', 'success');
            window.location.reload();
        } catch (err) {
            notify.close();
            alertaError(err, 'Error al guardar los cambios');
        }
    }

    /* ---------- Acciones ---------- */
    const acciones: Record<string, () => void> = {
        nuevo: () => {
            window.location.href = rutas.nuevo;
        },
        editar,
        visualizar,
        finalizar: () => void finalizar(),
        fechas: abrirFechas,
        'cerrar-fechas': cerrarFechas,
        'generar-reporte': generarReporte,
        'editar-supervisor': () => void editarSupervisor(),
        'cerrar-edicion': cerrarEdicion,
        'guardar-registro': () => void guardarRegistro(),
    };
    delegate(document, 'click', '[data-accion]', (ev, el) => {
        const accion = acciones[el.dataset.accion ?? ''];
        if (!accion || (el as HTMLButtonElement).disabled) return;
        ev.preventDefault();
        accion();
    });
    document.addEventListener('keydown', (ev) => {
        if (ev.key !== 'Escape') return;
        cerrarFechas();
        cerrarEdicion();
    });

    const inicial = cfg.ultimoFolio ? qsa<HTMLTableRowElement>('tr.marca-row', raiz).find((tr) => tr.dataset.folio === cfg.ultimoFolio) : undefined;
    if (inicial) seleccionar(inicial);
    else actualizarBotones();
}

onReady(iniciar);
