/**
 * Modal de Redbooth (vincular/ver tarea). Lo incluyen Programa Tejido, Trazabilidad y
 * CatCodificación (modal/redbooth.blade.php), así que vive en su propia entrada (index.ts)
 * que carga el propio modal. Los valores del servidor llegan en data-redbooth-boot.
 *
 * El HTML de Redbooth (descripción y comentarios) no es de confianza: se parsea en un
 * documento inerte (DOMParser), se limpia con limpiarNodos() y se inserta como nodos.
 */
import {
    fmtDate,
    iniciales,
    limpiarNodos,
    mensajeRespuesta,
    safeAvatarUrl,
    textoEstado,
    type RedboothBoot,
    type UsuarioRedbooth,
} from './logica.ts';
import { el, icono } from '../urdido/comun/pagina.ts';
import { http, HttpError } from '../../utils/http.ts';
import { notify } from '../../utils/notifications.ts';
import type { Combobox } from '../../utils/combobox.ts';

interface ArchivoComentario {
    download_url?: string;
    name?: string;
    is_image?: boolean;
}

interface Comentario {
    user?: UsuarioRedbooth;
    user_name?: string;
    author?: { name?: string };
    user_id?: number | string;
    body_html?: string;
    body?: string;
    comment?: string;
    created_at?: string | number;
    files?: ArchivoComentario[];
}

interface DetalleRedbooth {
    linked?: boolean;
    idRedbooth?: number | string;
    nombreRedbooth?: string;
    programaId?: number | null;
    task?: {
        name?: string;
        project_id?: number;
        task_list_id?: number;
        status?: string;
        start_on?: string;
        created_at?: string | number;
        due_on?: string;
        description_html?: string;
        description?: string;
    };
    comments?: Comentario[];
}

interface OpcionesApertura {
    registroId?: number | string | null;
    source?: string;
    flogAsignacion?: string;
    totalOrdenes?: number | string;
}

type Modo = 'loading' | 'editor' | 'viewer' | 'delete';

/** Error con un mensaje ya pensado para el usuario. */
class ErrorRedbooth extends Error {}

/** el() de modulos/urdido/comun/pagina.ts con la firma corta (clase, texto). */
function elemento<K extends keyof HTMLElementTagNameMap>(tag: K, clase = '', texto?: string): HTMLElementTagNameMap[K] {
    return el(tag, { clase, texto: texto ?? null });
}

export function iniciarRedbooth(boot: RedboothBoot): void {
    const byId = <T extends HTMLElement = HTMLElement>(id: string) => document.getElementById(id) as T | null;
    const modal = byId('modalRedboothProgramaTejido');
    const loading = byId('redboothLoading');
    const editor = byId('redboothEditor');
    const viewer = byId('redboothViewer');
    const deleteConfirm = byId('redboothDeleteConfirm');
    const proyecto = byId<HTMLSelectElement>('redboothProgramaTejidoProyecto');
    const guardar = byId<HTMLButtonElement>('guardarModalRedboothProgramaTejido');
    const editar = byId('editarModalRedboothProgramaTejido');
    const eliminar = byId('eliminarModalRedboothProgramaTejido');
    const cancelar = byId('cancelarEdicionRedbooth');
    const imageViewer = byId('redboothImageViewer');
    const imageViewerImg = byId<HTMLImageElement>('redboothImageViewerImg');
    const imageViewerDownload = byId<HTMLAnchorElement>('descargarRedboothImageViewer');
    const comentarios = byId('rbComentarios');
    if (!modal || !proyecto || !guardar || !loading || !editor || !viewer || !deleteConfirm || !editar || !eliminar ||
        !cancelar || !imageViewer || !imageViewerImg || !imageViewerDownload || !comentarios) return;
    if (modal.parentElement !== document.body) document.body.appendChild(modal);

    const texto = (id: string, valor: string) => { const n = byId(id); if (n) n.textContent = valor; };
    const urlArchivo = (fileId: string) => boot.rutas.descargaArchivo.replace('__FILE_ID__', fileId);

    let recordSource: RedboothBoot['contexto'] = boot.contexto;
    let programaId: number | null = null;
    let currentData: DetalleRedbooth | null = null;
    let comboboxProyecto: Promise<Combobox> | null = null;
    let flogAsignacion = '';
    let cantidadOrdenesAsignacion = 0;

    /** HTML de Redbooth limpio como fragmento; con un párrafo de respaldo si queda vacío. */
    const htmlRedbooth = (html: string | undefined, fallbackText = ''): DocumentFragment => {
        const doc = new DOMParser().parseFromString(`<body>${String(html || '')}</body>`, 'text/html');
        const imagenes = limpiarNodos(doc.body, urlArchivo);
        imagenes.forEach(({ img, url, nombre }) => {
            const wrapper = elemento('div', 'rb-inline-image');
            const actions = elemento('div', 'rb-inline-image-actions');
            const expand = elemento('button');
            expand.type = 'button';
            expand.dataset.rbImage = url;
            expand.dataset.rbImageName = nombre;
            expand.append(icono('fas fa-expand'), ' Ver grande');
            const download = elemento('a');
            download.href = url;
            download.target = '_blank';
            download.rel = 'noopener';
            download.append(icono('fas fa-download'), ' Descargar');
            img.setAttribute('data-rb-image', url);
            img.setAttribute('data-rb-image-name', nombre);
            img.replaceWith(wrapper);
            wrapper.appendChild(img);
            actions.append(expand, download);
            wrapper.appendChild(actions);
        });
        if (!doc.body.textContent?.trim() && fallbackText) doc.body.appendChild(elemento('p', '', fallbackText));

        const fragmento = document.createDocumentFragment();
        fragmento.append(...Array.from(doc.body.childNodes, (n) => document.importNode(n, true)));
        return fragmento;
    };

    const setMode = (mode: Modo) => {
        modal.querySelector('.rb-shell')?.classList.toggle('rb-compact', mode !== 'viewer');
        loading.classList.toggle('hidden', mode !== 'loading');
        editor.classList.toggle('hidden', mode !== 'editor');
        viewer.classList.toggle('hidden', mode !== 'viewer');
        deleteConfirm.classList.toggle('hidden', mode !== 'delete');
        editar.classList.toggle('hidden', mode !== 'viewer');
        editar.classList.toggle('inline-flex', mode === 'viewer');
        eliminar.classList.toggle('hidden', mode !== 'viewer');
        eliminar.classList.toggle('inline-flex', mode === 'viewer');
        cancelar.classList.toggle('hidden', mode !== 'editor' || !currentData?.linked);
    };

    const updateRow = (id: string | number | undefined, name: string | undefined) => {
        const idTxt = id ? String(id) : '';
        const row = recordSource === 'catcodificados'
            ? document.querySelector<HTMLElement>(`tr[data-cat-id="${programaId}"]`)
            : document.querySelector<HTMLElement>(`tr.selectable-row[data-id="${programaId}"]`);
        if (!row) return;
        row.dataset.idRedbooth = idTxt;
        row.dataset.nombreRedbooth = name || '';
        const idCell = row.querySelector('td[data-column="IdRedbooth"]');
        const nameCell = row.querySelector('td[data-column="NombreRedbooth"]');
        if (idCell) idCell.textContent = idTxt;
        if (nameCell) nameCell.textContent = name || '';
        window.dispatchEvent(new CustomEvent('redbooth:updated', {
            detail: { source: recordSource, registroId: programaId, idRedbooth: idTxt, nombreRedbooth: name || '' },
        }));
    };

    const inicializarSelect = () => {
        comboboxProyecto ??= window.combobox(proyecto, {
            placeholder: 'Selecciona una tarea', permitirVacio: true,
            remoto: { url: boot.rutas.proyectos },
            textos: { sinResultados: 'No se encontraron tareas', buscando: 'Buscando…', errorCarga: 'No se pudieron cargar las tareas' },
        }).catch((error: unknown) => { comboboxProyecto = null; throw error; });
    };
    const selectValue = (id: string | number | null | undefined, name: string | null | undefined) => {
        comboboxProyecto?.then((ts) => {
            ts.clear();
            ts.clearOptions();
            if (id) { ts.addOption({ value: String(id), text: `${id} — ${name}` }); ts.setValue(String(id), true); }
        }).catch(() => {});
    };

    const archivoComentario = (file: ArchivoComentario): HTMLElement => {
        const url = file.download_url || '';
        const name = file.name || 'Archivo adjunto';
        if (!file.is_image) {
            const a = elemento('a', 'flex max-w-xl items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 hover:bg-gray-100');
            a.href = url;
            a.target = '_blank';
            a.rel = 'noopener';
            a.append(icono('fas fa-file-download text-blue-600'), elemento('span', 'min-w-0 flex-1 truncate text-sm text-gray-700', name));
            return a;
        }
        const ver = (clase: string) => {
            const b = elemento('button', clase);
            b.type = 'button';
            b.dataset.rbImage = url;
            b.dataset.rbImageName = name;
            return b;
        };
        const img = elemento('img', 'max-h-[420px] max-w-full object-contain');
        img.src = url;
        img.alt = name;
        img.loading = 'lazy';
        const miniatura = ver('block cursor-zoom-in');
        miniatura.appendChild(img);
        const grande = ver('text-xs font-semibold text-blue-600 hover:text-blue-800');
        grande.append(icono('fas fa-expand mr-1'), 'Ver grande');
        const descargar = elemento('a', 'text-xs font-semibold text-gray-600 hover:text-gray-900');
        descargar.href = url;
        descargar.target = '_blank';
        descargar.rel = 'noopener';
        descargar.append(icono('fas fa-download mr-1'), 'Descargar');
        const pie = elemento('div', 'flex items-center gap-3 border-t border-gray-200 px-3 py-2');
        pie.append(elemento('span', 'min-w-0 flex-1 truncate text-xs text-gray-600', name), grande, descargar);
        const caja = elemento('div', 'w-fit max-w-full overflow-hidden rounded-md border border-gray-200 bg-gray-50');
        caja.append(miniatura, pie);
        return caja;
    };

    const comentario = (comment: Comentario): HTMLElement => {
        const author = comment.user?.name || comment.user_name || comment.author?.name || `Usuario ${comment.user_id || ''}`.trim();
        const avatar = elemento('div', 'relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-600 text-xs font-bold text-white', iniciales(author));
        const avatarUrl = safeAvatarUrl(comment.user, window.location.origin);
        if (avatarUrl) {
            const img = elemento('img', 'absolute inset-0 h-full w-full object-cover');
            img.src = avatarUrl;
            img.alt = author;
            img.loading = 'lazy';
            img.addEventListener('error', () => img.remove());
            avatar.appendChild(img);
        }

        const cabecera = elemento('div', 'flex flex-wrap items-baseline gap-2');
        cabecera.append(elemento('strong', 'text-sm text-gray-800', author), elemento('time', 'text-xs text-gray-400', fmtDate(comment.created_at)));
        const cuerpo = elemento('div', 'rb-rich mt-2');
        cuerpo.append(htmlRedbooth(comment.body_html, comment.body || comment.comment || ''));
        if (!cuerpo.childNodes.length) cuerpo.appendChild(elemento('p', '', 'Sin texto'));

        const contenido = elemento('div', 'min-w-0 flex-1');
        contenido.append(cabecera, cuerpo);
        if (Array.isArray(comment.files) && comment.files.length) {
            const archivos = elemento('div', 'mt-3 space-y-2');
            archivos.append(...comment.files.map(archivoComentario));
            contenido.appendChild(archivos);
        }

        const article = elemento('article', 'rb-comment flex gap-3 border-b border-gray-100 py-5');
        article.append(avatar, contenido);
        return article;
    };

    const renderComments = (comments: Comentario[]) => {
        if (!Array.isArray(comments) || comments.length === 0) {
            comentarios.replaceChildren(elemento('p', 'py-8 text-center text-sm text-gray-400', 'Esta tarea no tiene comentarios.'));
            return;
        }
        comentarios.replaceChildren(...comments.map(comentario));
    };

    const renderViewer = (data: DetalleRedbooth) => {
        const task = data.task || {};
        texto('redboothTaskId', `#${data.idRedbooth}`);
        texto('modalRedboothProgramaTejidoTitulo', data.nombreRedbooth || task.name || 'Tarea Redbooth');
        texto('redboothTaskContext', `Proyecto ${task.project_id || 2113514} · Lista ${task.task_list_id || 6863455}`);
        const abierto = String(task.status || '').toLowerCase() === 'open';
        const statusElement = byId('rbEstado');
        if (statusElement) {
            statusElement.textContent = textoEstado(task.status);
            statusElement.classList.toggle('bg-green-100', abierto);
            statusElement.classList.toggle('text-green-700', abierto);
            statusElement.classList.toggle('bg-gray-200', !abierto);
            statusElement.classList.toggle('text-gray-700', !abierto);
        }
        texto('rbInicio', fmtDate(task.start_on || task.created_at));
        texto('rbLimite', fmtDate(task.due_on));
        const descripcion = byId('rbDescripcion');
        if (descripcion) {
            descripcion.replaceChildren(htmlRedbooth(task.description_html, task.description || ''));
            if (!descripcion.childNodes.length) descripcion.textContent = 'Sin descripción.';
        }
        renderComments(data.comments || []);
        setMode('viewer');
    };

    const conFuente = (plantilla: string) => {
        const url = new URL(plantilla.replace('__ID__', String(programaId)), window.location.origin);
        if (recordSource === 'catcodificados') url.searchParams.set('source', 'catcodificados');
        return url.toString();
    };

    /** Mensaje del error: el del servidor si respondió, o el genérico. */
    const mensajeDe = (error: unknown, porDefecto: string) => {
        if (error instanceof ErrorRedbooth) return error.message;
        if (error instanceof HttpError) return mensajeRespuesta(error.data, porDefecto);
        return error instanceof Error && error.message ? error.message : porDefecto;
    };

    const loadDetail = async () => {
        setMode('loading');
        try {
            const data = await http.get<DetalleRedbooth>(conFuente(boot.rutas.show));
            currentData = data ?? {};
            if (currentData.linked) {
                renderViewer(currentData);
                return;
            }
            texto('redboothTaskId', 'Redbooth');
            texto('modalRedboothProgramaTejidoTitulo', 'Vincular tarea');
            texto('redboothTaskContext', flogAsignacion
                ? 'La tarea se asignará a ' + cantidadOrdenesAsignacion + ' orden(es) del Flog ' + flogAsignacion
                : (recordSource === 'catcodificados' ? 'CatCodificados' : 'Programa de tejido'));
            selectValue(null, null);
            setMode('editor');
        } catch (error) {
            setMode('editor');
            void notify.alert(mensajeDe(error, 'No se pudo consultar Redbooth.'), 'No se pudo cargar Redbooth', 'error');
        }
    };

    const abrirModalRedboothProgramaTejido = ({ registroId, source, flogAsignacion: flogContexto, totalOrdenes }: OpcionesApertura = {}) => {
        recordSource = String(source || boot.contexto) === 'catcodificados' ? 'catcodificados' : 'programa';
        flogAsignacion = String(flogContexto || '').trim();
        cantidadOrdenesAsignacion = Number(totalOrdenes) || 0;
        programaId = Number(registroId) || null;
        currentData = null;
        if (!programaId) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        inicializarSelect();
        void loadDetail();
    };
    // PUENTE PT-TS 1: index.js (grilla de PT), catcodificacion/partials/redbooth.blade.php y
    // resources/js/trazabilidad/redbooth.ts abren el modal.
    window.abrirModalRedboothProgramaTejido = abrirModalRedboothProgramaTejido;

    const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    byId('cerrarModalRedboothProgramaTejido')?.addEventListener('click', close);
    editar.addEventListener('click', () => { selectValue(currentData?.idRedbooth, currentData?.nombreRedbooth); setMode('editor'); });
    cancelar.addEventListener('click', () => (currentData?.linked ? renderViewer(currentData) : close()));

    guardar.addEventListener('click', async () => {
        const taskId = Number(proyecto.value || 0);
        if (!taskId) { void notify.alert('', 'Selecciona una tarea', 'warning'); return; }
        guardar.disabled = true;
        try {
            const payload: Record<string, string | number> = recordSource === 'catcodificados'
                ? { source: 'catcodificados', cat_codificados_id: programaId ?? 0, redbooth_task_id: taskId }
                : { source: 'programa', req_programa_tejido_id: programaId ?? 0, redbooth_task_id: taskId };
            if (flogAsignacion) payload.flog_asignacion = flogAsignacion;
            const data = await http.post<{ success?: boolean; idRedbooth?: string; nombreRedbooth?: string; asignacionFlog?: boolean; ordenesActualizadas?: number }>(
                boot.rutas.store, payload);
            if (data?.success !== true) throw new ErrorRedbooth(mensajeRespuesta(data, 'No se pudo guardar.'));
            updateRow(data.idRedbooth, data.nombreRedbooth);
            if (data.asignacionFlog) {
                notify.success('Redbooth asignado a ' + data.ordenesActualizadas + ' orden(es) del Flog.');
                flogAsignacion = '';
                cantidadOrdenesAsignacion = 0;
            }
            await loadDetail();
        } catch (error) {
            void notify.alert(mensajeDe(error, 'No se pudo guardar.'), 'No se pudo guardar', 'error');
        } finally {
            guardar.disabled = false;
        }
    });

    eliminar.addEventListener('click', () => {
        texto('redboothTaskId', `#${currentData?.idRedbooth || ''}`);
        texto('modalRedboothProgramaTejidoTitulo', 'Eliminar vínculo');
        setMode('delete');
    });
    byId('cancelarEliminarRedbooth')?.addEventListener('click', () => { if (currentData) renderViewer(currentData); });
    byId<HTMLButtonElement>('confirmarEliminarRedbooth')?.addEventListener('click', async (event) => {
        const button = event.currentTarget as HTMLButtonElement;
        button.disabled = true;
        try {
            const data = await http.delete<{ success?: boolean; message?: string }>(conFuente(boot.rutas.destroy));
            if (data?.success !== true) throw new ErrorRedbooth(data?.message || 'No se pudo eliminar el vínculo.');
            updateRow('', '');
            currentData = { linked: false, programaId };
            selectValue(null, null);
            close();
            notify.success('El ID y nombre de Redbooth fueron eliminados de Programa Tejido y CatCodificados.');
        } catch (error) {
            const porDefecto = 'No se pudo eliminar el vínculo.';
            const msg = error instanceof HttpError ? ((error.data as { message?: string } | null)?.message || porDefecto) : mensajeDe(error, porDefecto);
            void notify.alert(msg, 'No se pudo eliminar', 'error');
        } finally {
            button.disabled = false;
        }
    });

    const closeImageViewer = () => {
        imageViewer.classList.add('hidden');
        imageViewer.classList.remove('flex');
        imageViewerImg.src = '';
    };
    comentarios.addEventListener('click', (event) => {
        const trigger = (event.target as Element).closest<HTMLElement>('[data-rb-image]');
        if (!trigger) return;
        imageViewerImg.src = trigger.dataset.rbImage ?? '';
        imageViewerImg.alt = trigger.dataset.rbImageName || 'Imagen de Redbooth';
        imageViewerDownload.href = trigger.dataset.rbImage ?? '';
        imageViewer.classList.remove('hidden');
        imageViewer.classList.add('flex');
    });
    byId('cerrarRedboothImageViewer')?.addEventListener('click', closeImageViewer);
    imageViewer.addEventListener('click', (event) => { if (event.target === imageViewer) closeImageViewer(); });
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (!imageViewer.classList.contains('hidden')) { closeImageViewer(); return; }
        if (!modal.classList.contains('hidden')) close();
    });
}
