/**
 * Consultar Cortes de Eficiencia (19-02). Vista: modulos/cortes-eficiencia/consultar-cortes-eficiencia.blade.php.
 * Antes: clase CortesManager inline + onclick/ondblclick por fila y funciones globales.
 */
import { delegate, onReady, qs, qsa } from '../../../../utils/dom.ts';
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { alertaError, exigirExito, leerDatos, mensajeError, type RespuestaApi } from '../../comun/pagina.ts';
import { confirmarFinalizar, finalizarFolio, validarParaFinalizar } from '../comun/finalizar.ts';
import { datosEdicion, folioParaFecha, hoyLocal, type CorteDetalle } from '../comun/logica.ts';
import { abrirModal, cerrarModal } from '../comun/modal.ts';
import { folioExistente } from '../comun/folio.ts';

interface ConfigConsultar {
    ultimoFolio: string | null;
    esSupervisor: boolean;
    rutas: {
        corte: string;
        editar: string;
        finalizar: string;
        visualizarFolio: string;
        visualizar: string;
        actualizarRegistro: string;
        generarFolio: string;
    };
}

interface RespuestaCorte extends RespuestaApi {
    data?: CorteDetalle;
}

const CLASES_SELECCION = ['bg-blue-500', 'text-white', 'selected-row'];

const estado = {
    folio: null as string | null,
    status: null as string | null,
    peticion: null as AbortController | null,
};

const conFolio = (plantilla: string, folio: string): string => plantilla.replace('__FOLIO__', encodeURIComponent(folio));
const boton = (id: string): HTMLButtonElement | null => document.getElementById(id) as HTMLButtonElement | null;
const valor = (id: string): string => (document.getElementById(id) as HTMLInputElement | HTMLSelectElement | null)?.value ?? '';
const fijar = (id: string, v: string): void => {
    const el = document.getElementById(id) as HTMLInputElement | HTMLSelectElement | null;
    if (el) el.value = v;
};

function iniciar(): void {
    const raiz = qs('#pagina-consultar');
    const cfg = leerDatos<ConfigConsultar>(raiz);
    if (!raiz || !cfg) return;
    const cuerpo = qs('#cortes-body');

    const exigirSeleccion = (accion: string): string | null => {
        if (!estado.folio) void notify.alert(`Selecciona un folio para ${accion}`, 'Sin selección', 'warning');
        return estado.folio;
    };

    function actualizarBotones(): void {
        const finalizado = estado.status === 'Finalizado';
        const hay = estado.folio !== null;
        const b = (id: string, deshabilitado: boolean): void => {
            const el = boton(id);
            if (el) el.disabled = deshabilitado;
        };
        b('btn-nuevo', false);
        b('btn-editar', !hay || finalizado);
        b('btn-finalizar', !hay || finalizado);
        b('btn-visualizar', !hay);
        b('btn-editar-supervisor', !hay);
    }

    async function cargarDetalles(folio: string): Promise<void> {
        estado.peticion?.abort();
        const peticion = new AbortController();
        estado.peticion = peticion;
        try {
            const r = exigirExito(await http.get<RespuestaCorte>(conFolio(cfg!.rutas.corte, folio), { signal: peticion.signal }), 'Error al cargar los detalles');
            estado.status = r.data?.status ?? null;
            actualizarBotones();
        } catch (err) {
            if (!peticion.signal.aborted) console.error('Cortes: error al cargar detalles', err);
        }
    }

    function seleccionar(fila: HTMLTableRowElement): void {
        const folio = fila.dataset.folio ?? '';
        if (!folio || estado.folio === folio) return;
        estado.folio = folio;
        for (const tr of qsa<HTMLTableRowElement>('tbody tr')) {
            tr.classList.remove(...CLASES_SELECCION);
            tr.removeAttribute('aria-selected');
        }
        fila.classList.add(...CLASES_SELECCION);
        fila.setAttribute('aria-selected', 'true');
        void cargarDetalles(folio);
    }

    function visualizar(): void {
        const folio = exigirSeleccion('visualizar');
        if (folio) window.location.href = conFolio(cfg!.rutas.visualizarFolio, folio);
    }

    function editar(): void {
        const folio = exigirSeleccion('editar');
        if (folio) window.location.href = cfg!.rutas.editar + encodeURIComponent(folio);
    }

    function marcarFilaFinalizada(folio: string): void {
        const fila = qs(`tr[data-folio="${CSS.escape(folio)}"]`);
        const celda = fila?.querySelector('td:last-child');
        if (!celda) return;
        const badge = document.createElement('span');
        badge.className = 'status-badge-finalizado px-3 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-700';
        badge.textContent = 'Finalizado';
        celda.replaceChildren(badge);
    }

    async function finalizar(): Promise<void> {
        const folio = exigirSeleccion('finalizar');
        if (!folio) return;
        try {
            if (!(await validarParaFinalizar(conFolio(cfg!.rutas.corte, folio)))) return;
        } catch (err) {
            alertaError(err, 'No se pudo validar el folio');
            return;
        }
        if (!(await confirmarFinalizar(folio))) return;
        if (await finalizarFolio(conFolio(cfg!.rutas.finalizar, folio), 'El registro se ha cerrado correctamente.')) {
            estado.status = 'Finalizado';
            actualizarBotones();
            marcarFilaFinalizada(folio);
        }
    }

    /* ---------- Nuevo corte (fecha + turno) ---------- */
    function abrirNuevo(): void {
        fijar('input-fecha-nuevo-corte', hoyLocal(new Date()));
        fijar('select-turno-nuevo-corte', '');
        abrirModal('modal-nuevo-corte');
    }

    async function confirmarNuevo(): Promise<void> {
        const fecha = valor('input-fecha-nuevo-corte');
        const turno = valor('select-turno-nuevo-corte');
        if (!fecha) {
            void notify.alert('Por favor seleccione una fecha.', 'Fecha requerida', 'warning');
            return;
        }
        if (!turno) {
            void notify.alert('Por favor seleccione un turno.', 'Turno requerido', 'warning');
            return;
        }
        cerrarModal('modal-nuevo-corte');
        void notify.loading('Generando folio...');
        try {
            const d = exigirExito(await http.get<RespuestaApi & { folio?: string }>(cfg!.rutas.generarFolio, { params: { fecha, turno } }), 'No se pudo generar el folio');
            window.location.href = cfg!.rutas.editar + encodeURIComponent(d.folio ?? '');
        } catch (err) {
            notify.close();
            const existente = folioExistente(err);
            if (existente) {
                const editarlo = await notify.confirm({
                    title: 'Folio en proceso',
                    text: `Ya existe un folio en proceso: ${existente}. ¿Desea continuar editándolo?`,
                    confirmText: 'Sí, editar',
                });
                if (editarlo) window.location.href = cfg!.rutas.editar + encodeURIComponent(existente);
                return;
            }
            void notify.alert(`No se pudo generar el folio: ${mensajeError(err, 'error desconocido')}`, 'Error', 'error');
        }
    }

    /* ---------- Visualizar por fecha ---------- */
    function visualizarPorFecha(): void {
        const fecha = valor('input-fecha-inicio');
        if (!fecha) {
            void notify.alert('Selecciona una fecha de inicio.', 'Fecha requerida', 'warning');
            return;
        }
        const filas = qsa<HTMLTableRowElement>('tbody tr[data-folio]').map((tr) => ({ folio: tr.dataset.folio ?? '', fecha: tr.dataset.fecha ?? '' }));
        const folio = folioParaFecha(filas, fecha);
        if (!folio) {
            void notify.alert('No se encontraron folios para la fecha seleccionada.', 'Sin folios', 'info');
            return;
        }
        cerrarModal('modal-fechas');
        window.location.href = conFolio(cfg!.rutas.visualizar, folio);
    }

    /* ---------- Editar registro (supervisor) ---------- */
    async function editarSupervisor(): Promise<void> {
        const folio = exigirSeleccion('editar');
        if (!folio) return;
        void notify.loading('Cargando datos...');
        try {
            const r = exigirExito(await http.get<RespuestaCorte>(conFolio(cfg!.rutas.corte, folio)), 'No se pudo obtener los datos');
            notify.close();
            const d = datosEdicion(r.data ?? {});
            const titulo = document.getElementById('modal-editar-registro-titulo');
            if (titulo) titulo.textContent = `Editar Registro - ${r.data?.folio || folio}`;
            fijar('edit-fecha', d.fecha);
            fijar('edit-turno', d.turno);
            fijar('edit-empleado', d.empleado);
            fijar('edit-nombre', d.nombre);
            fijar('edit-status', d.status);
            abrirModal('modal-editar-registro');
        } catch (err) {
            notify.close();
            alertaError(err, 'No se pudieron cargar los datos del folio');
        }
    }

    async function guardarRegistro(): Promise<void> {
        const folio = estado.folio;
        if (!folio) return;
        const datos = {
            Date: valor('edit-fecha'),
            Turno: valor('edit-turno'),
            numero_empleado: valor('edit-empleado'),
            nombreEmpl: valor('edit-nombre'),
            Status: valor('edit-status'),
        };
        if (!datos.Date || !datos.Turno) {
            void notify.alert('Fecha y Turno son obligatorios.', 'Campos requeridos', 'warning');
            return;
        }
        void notify.loading('Guardando cambios...');
        try {
            const r = exigirExito(await http.put<RespuestaApi>(conFolio(cfg!.rutas.actualizarRegistro, folio), datos), 'No se pudo actualizar el registro');
            notify.close();
            cerrarModal('modal-editar-registro');
            await notify.alert(r.message || 'Registro actualizado correctamente.', '¡Actualizado!', 'success');
            window.location.reload();
        } catch (err) {
            notify.close();
            alertaError(err, 'Error al guardar los cambios');
        }
    }

    /* ---------- Eventos ---------- */
    if (cuerpo) {
        delegate<HTMLTableRowElement>(cuerpo, 'click', 'tr.corte-row', (_ev, fila) => seleccionar(fila));
        delegate<HTMLTableRowElement>(cuerpo, 'dblclick', 'tr.corte-row', () => visualizar());
    }
    const clic: Record<string, () => void> = {
        'btn-nuevo': abrirNuevo,
        'btn-nuevo-empty': abrirNuevo,
        'btn-editar': editar,
        'btn-finalizar': () => void finalizar(),
        'btn-visualizar': visualizar,
        'btn-fechas': () => abrirModal('modal-fechas'),
        'btn-editar-supervisor': () => void editarSupervisor(),
        'modal-fechas-ok': visualizarPorFecha,
        'modal-editar-save': () => void guardarRegistro(),
        'btn-confirmar-nuevo-corte': () => void confirmarNuevo(),
    };
    for (const [id, fn] of Object.entries(clic)) boton(id)?.addEventListener('click', fn);

    if (cfg.ultimoFolio) {
        const fila = qs<HTMLTableRowElement>(`tr[data-folio="${CSS.escape(cfg.ultimoFolio)}"]`);
        if (fila) seleccionar(fila);
    }
}

onReady(iniciar);
