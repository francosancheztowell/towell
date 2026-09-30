/**
 * Modal de Redbooth (vincular/ver tarea). Movido tal cual del <script> inline de
 * resources/views/modulos/programa-tejido/modal/redbooth.blade.php (HANDOFF PT-05 B4): lo
 * incluyen Programa Tejido, Trazabilidad y CatCodificación, así que vive en su propia entrada
 * (index.ts) que carga el propio modal. Los valores del servidor llegan en #redbooth-boot.
 */
export function iniciarRedbooth(boot) {
  const byId = (id) => document.getElementById(id);
  const modal = byId('modalRedboothProgramaTejido');
  const loading = byId('redboothLoading');
  const editor = byId('redboothEditor');
  const viewer = byId('redboothViewer');
  const deleteConfirm = byId('redboothDeleteConfirm');
  const proyecto = byId('redboothProgramaTejidoProyecto');
  const guardar = byId('guardarModalRedboothProgramaTejido');
  const editar = byId('editarModalRedboothProgramaTejido');
  const eliminar = byId('eliminarModalRedboothProgramaTejido');
  const cancelar = byId('cancelarEdicionRedbooth');
  const imageViewer = byId('redboothImageViewer');
  const imageViewerImg = byId('redboothImageViewerImg');
  const imageViewerDownload = byId('descargarRedboothImageViewer');
  if (!modal || !proyecto || !guardar) return;
  if (modal.parentElement !== document.body) document.body.appendChild(modal);

  const showUrl = boot.rutas.show;
  const deleteUrl = boot.rutas.destroy;
  const fileDownloadUrl = boot.rutas.descargaArchivo;
  const defaultRecordSource = boot.contexto;
  let recordSource = defaultRecordSource;
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  let programaId = null;
  let currentData = null;
  let comboboxProyecto = null;
  let flogAsignacion = '';
  let cantidadOrdenesAsignacion = 0;

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const sanitizeRedboothHtml = (html, fallbackText = '') => {
    const allowed = new Set(['A','BR','CODE','DIV','EM','HR','IMG','LI','OL','P','SPAN','STRONG','TABLE','TBODY','TD','TH','THEAD','TR','UL']);
    const template = document.createElement('template');
    template.innerHTML = String(html || '');
    const clean = (parent) => {
      Array.from(parent.childNodes).forEach((node) => {
        if (node.nodeType === Node.COMMENT_NODE) { node.remove(); return; }
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        if (!allowed.has(node.tagName)) {
          const fragment = document.createDocumentFragment();
          while (node.firstChild) fragment.appendChild(node.firstChild);
          node.replaceWith(fragment);
          clean(parent);
          return;
        }
        if (node.tagName === 'IMG') {
          const source = node.getAttribute('src') || '';
          const fileId = source.match(/\/files\/(\d+)\//)?.[1] || '';
          const alt = node.getAttribute('alt') || 'Imagen adjunta';
          Array.from(node.attributes).forEach((attribute) => node.removeAttribute(attribute.name));
          if (!fileId) { node.remove(); return; }
          node.setAttribute('src', fileDownloadUrl.replace('__FILE_ID__', fileId));
          node.setAttribute('alt', alt);
          node.setAttribute('loading', 'lazy');
          node.setAttribute('data-rb-image', fileDownloadUrl.replace('__FILE_ID__', fileId));
          node.setAttribute('data-rb-image-name', alt);
        } else {
          Array.from(node.attributes).forEach((attribute) => node.removeAttribute(attribute.name));
        }
        clean(node);
      });
    };
    clean(template.content);
    template.content.querySelectorAll('img[data-rb-image]').forEach((image) => {
      const url = image.getAttribute('data-rb-image') || '';
      const name = image.getAttribute('data-rb-image-name') || 'Imagen adjunta';
      const wrapper = document.createElement('div');
      wrapper.className = 'rb-inline-image';
      const actions = document.createElement('div');
      actions.className = 'rb-inline-image-actions';
      const expand = document.createElement('button');
      expand.type = 'button';
      expand.setAttribute('data-rb-image', url);
      expand.setAttribute('data-rb-image-name', name);
      expand.innerHTML = '<i class="fas fa-expand"></i> Ver grande';
      const download = document.createElement('a');
      download.href = url;
      download.target = '_blank';
      download.rel = 'noopener';
      download.innerHTML = '<i class="fas fa-download"></i> Descargar';
      image.replaceWith(wrapper);
      wrapper.appendChild(image);
      actions.append(expand, download);
      wrapper.appendChild(actions);
    });
    if (!template.content.textContent?.trim() && fallbackText) {
      const paragraph = document.createElement('p');
      paragraph.textContent = fallbackText;
      template.content.appendChild(paragraph);
    }
    return template.innerHTML;
  };
  const fmtDate = (value) => {
    if (!value) return '—';
    if (/^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
      const [year, month, day] = String(value).split('-').map(Number);
      return new Date(year, month - 1, day).toLocaleDateString('es-MX', {dateStyle:'medium'});
    }
    const numericValue = Number(value);
    const date = Number.isFinite(numericValue) && numericValue > 100000000
      ? new Date(numericValue * 1000)
      : new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('es-MX', {dateStyle:'medium', timeStyle:'short'});
  };
  const safeAvatarUrl = (user) => {
    const candidate = user?.profile_avatar_url || user?.avatar_url || user?.micro_avatar_url || '';
    try {
      const url = new URL(candidate, window.location.origin);
      return url.protocol === 'https:' ? url.href : '';
    } catch (_) { return ''; }
  };
  const setMode = (mode) => {
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
  const updateRow = (id, name) => {
    const row = recordSource === 'catcodificados'
      ? document.querySelector(`tr[data-cat-id="${programaId}"]`)
      : document.querySelector(`tr.selectable-row[data-id="${programaId}"]`);
    if (!row) return;
    row.dataset.idRedbooth = id || '';
    row.dataset.nombreRedbooth = name || '';
    const idCell = row.querySelector('td[data-column="IdRedbooth"]');
    const nameCell = row.querySelector('td[data-column="NombreRedbooth"]');
    if (idCell) idCell.textContent = id || '';
    if (nameCell) nameCell.textContent = name || '';
    window.dispatchEvent(new CustomEvent('redbooth:updated', {
      detail: { source: recordSource, registroId: programaId, idRedbooth: id || '', nombreRedbooth: name || '' },
    }));
  };

  const inicializarSelect = () => {
    comboboxProyecto ??= window.combobox(proyecto, {
      placeholder:'Selecciona una tarea', permitirVacio:true,
      remoto:{url:boot.rutas.proyectos},
      textos:{sinResultados:'No se encontraron tareas',buscando:'Buscando…',errorCarga:'No se pudieron cargar las tareas'},
    }).catch((error) => { comboboxProyecto = null; throw error; });
  };
  const selectValue = (id, name) => {
    comboboxProyecto?.then((ts) => {
      ts.clear(); ts.clearOptions();
      if (id) { ts.addOption({value:String(id),text:`${id} — ${name}`}); ts.setValue(String(id), true); }
    }).catch(() => {});
  };

  const renderCommentFiles = (files) => {
    if (!Array.isArray(files) || files.length === 0) return '';
    return `<div class="mt-3 space-y-2">${files.map((file) => {
      const url = escapeHtml(file.download_url || '');
      const name = escapeHtml(file.name || 'Archivo adjunto');
      if (file.is_image) {
        return `<div class="w-fit max-w-full overflow-hidden rounded-md border border-gray-200 bg-gray-50">
          <button type="button" data-rb-image="${url}" data-rb-image-name="${name}" class="block cursor-zoom-in">
          <img src="${url}" alt="${name}" class="max-h-[420px] max-w-full object-contain" loading="lazy">
          </button>
          <div class="flex items-center gap-3 border-t border-gray-200 px-3 py-2">
            <span class="min-w-0 flex-1 truncate text-xs text-gray-600">${name}</span>
            <button type="button" data-rb-image="${url}" data-rb-image-name="${name}" class="text-xs font-semibold text-blue-600 hover:text-blue-800"><i class="fas fa-expand mr-1"></i>Ver grande</button>
            <a href="${url}" target="_blank" rel="noopener" class="text-xs font-semibold text-gray-600 hover:text-gray-900"><i class="fas fa-download mr-1"></i>Descargar</a>
          </div>
        </div>`;
      }
      return `<a href="${url}" target="_blank" rel="noopener" class="flex max-w-xl items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 hover:bg-gray-100">
        <i class="fas fa-file-download text-blue-600"></i><span class="min-w-0 flex-1 truncate text-sm text-gray-700">${name}</span>
      </a>`;
    }).join('')}</div>`;
  };

  const renderComments = (comments) => {
    const root = byId('rbComentarios');
    if (!Array.isArray(comments) || comments.length === 0) {
      root.innerHTML = '<p class="py-8 text-center text-sm text-gray-400">Esta tarea no tiene comentarios.</p>'; return;
    }
    root.innerHTML = comments.map((comment) => {
      const author = comment.user?.name || comment.user_name || comment.author?.name || `Usuario ${comment.user_id || ''}`.trim();
      const initials = author.split(/\s+/).slice(0,2).map((p)=>p.charAt(0)).join('').toUpperCase() || 'RB';
      const avatarUrl = safeAvatarUrl(comment.user);
      const bodyHtml = sanitizeRedboothHtml(comment.body_html, comment.body || comment.comment || '');
      return `<article class="rb-comment flex gap-3 border-b border-gray-100 py-5">
        <div class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-600 text-xs font-bold text-white">${escapeHtml(initials)}
          ${avatarUrl ? `<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(author)}" class="absolute inset-0 h-full w-full object-cover" loading="lazy" onerror="this.remove()">` : ''}
        </div>
        <div class="min-w-0 flex-1"><div class="flex flex-wrap items-baseline gap-2"><strong class="text-sm text-gray-800">${escapeHtml(author)}</strong><time class="text-xs text-gray-400">${escapeHtml(fmtDate(comment.created_at))}</time></div>
        <div class="rb-rich mt-2">${bodyHtml || '<p>Sin texto</p>'}</div>
        ${renderCommentFiles(comment.files)}</div></article>`;
    }).join('');
  };
  const renderViewer = (data) => {
    const task = data.task || {};
    byId('redboothTaskId').textContent = `#${data.idRedbooth}`;
    byId('modalRedboothProgramaTejidoTitulo').textContent = data.nombreRedbooth || task.name || 'Tarea Redbooth';
    byId('redboothTaskContext').textContent = `Proyecto ${task.project_id || 2113514} · Lista ${task.task_list_id || 6863455}`;
    const status = String(task.status || '').toLowerCase();
    const statusElement = byId('rbEstado');
    statusElement.textContent = status === 'open' ? 'Abierto' : (status === 'resolved' ? 'Finalizado' : (task.status || 'Sin estado'));
    statusElement.classList.toggle('bg-green-100', status === 'open');
    statusElement.classList.toggle('text-green-700', status === 'open');
    statusElement.classList.toggle('bg-gray-200', status !== 'open');
    statusElement.classList.toggle('text-gray-700', status !== 'open');
    byId('rbInicio').textContent = fmtDate(task.start_on || task.created_at);
    byId('rbLimite').textContent = fmtDate(task.due_on);
    byId('rbDescripcion').innerHTML = sanitizeRedboothHtml(task.description_html, task.description || '') || 'Sin descripción.';
    renderComments(data.comments || []); setMode('viewer');
  };
  const loadDetail = async () => {
    setMode('loading');
    try {
      const detailUrl = new URL(showUrl.replace('__ID__', programaId), window.location.origin);
      if (recordSource === 'catcodificados') detailUrl.searchParams.set('source', 'catcodificados');
      const response = await fetch(detailUrl, {headers:{Accept:'application/json'}});
      const data = await response.json().catch(()=>({}));
      if (!response.ok) throw new Error(Object.values(data.errors||{}).flat().find(Boolean) || data.message || 'No se pudo consultar Redbooth.');
      currentData = data;
      if (data.linked) renderViewer(data);
      else {
        byId('redboothTaskId').textContent='Redbooth';
        byId('modalRedboothProgramaTejidoTitulo').textContent='Vincular tarea';
        byId('redboothTaskContext').textContent = flogAsignacion
          ? 'La tarea se asignará a '+cantidadOrdenesAsignacion+' orden(es) del Flog '+flogAsignacion
          : (recordSource === 'catcodificados' ? 'CatCodificados' : 'Programa de tejido');
        selectValue(null, null);
        setMode('editor');
      }
    } catch (error) { setMode('editor'); window.Swal?.fire({icon:'error',title:'No se pudo cargar Redbooth',text:error.message}); }
  };

  window.abrirModalRedboothProgramaTejido = ({registroId, source, flogAsignacion: flogContexto, totalOrdenes}={}) => {
    recordSource = String(source || defaultRecordSource) === 'catcodificados' ? 'catcodificados' : 'programa';
    flogAsignacion = String(flogContexto || '').trim();
    cantidadOrdenesAsignacion = Number(totalOrdenes) || 0;
    programaId = Number(registroId)||null; currentData=null;
    if (!programaId) return;
    modal.classList.remove('hidden'); modal.classList.add('flex'); inicializarSelect(); loadDetail();
  };
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
  byId('cerrarModalRedboothProgramaTejido').addEventListener('click', close);
  editar.addEventListener('click', () => { selectValue(currentData?.idRedbooth, currentData?.nombreRedbooth); setMode('editor'); });
  cancelar.addEventListener('click', () => currentData?.linked ? renderViewer(currentData) : close());
  guardar.addEventListener('click', async () => {
    const taskId = Number(proyecto.value||0);
    if (!taskId) { window.Swal?.fire({icon:'warning',title:'Selecciona una tarea'}); return; }
    guardar.disabled=true;
    try {
      const payload = recordSource === 'catcodificados'
        ? {source:'catcodificados',cat_codificados_id:programaId,redbooth_task_id:taskId}
        : {source:'programa',req_programa_tejido_id:programaId,redbooth_task_id:taskId};
      if (flogAsignacion) payload.flog_asignacion = flogAsignacion;
      const response = await fetch(boot.rutas.store,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify(payload)});
      const data=await response.json().catch(()=>({}));
      if(!response.ok||data.success!==true) throw new Error(Object.values(data.errors||{}).flat().find(Boolean)||data.message||'No se pudo guardar.');
      updateRow(data.idRedbooth,data.nombreRedbooth);
      if (data.asignacionFlog) {
        window.notify?.success('Redbooth asignado a '+data.ordenesActualizadas+' orden(es) del Flog.');
        flogAsignacion = '';
        cantidadOrdenesAsignacion = 0;
      }
      await loadDetail();
    } catch(error) { window.Swal?.fire({icon:'error',title:'No se pudo guardar',text:error.message}); }
    finally { guardar.disabled=false; }
  });
  eliminar.addEventListener('click', () => {
    byId('redboothTaskId').textContent = `#${currentData?.idRedbooth || ''}`;
    byId('modalRedboothProgramaTejidoTitulo').textContent = 'Eliminar vínculo';
    setMode('delete');
  });
  byId('cancelarEliminarRedbooth').addEventListener('click', () => renderViewer(currentData));
  byId('confirmarEliminarRedbooth').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
      const unlinkUrl = new URL(deleteUrl.replace('__ID__',programaId), window.location.origin);
      if (recordSource === 'catcodificados') unlinkUrl.searchParams.set('source', 'catcodificados');
      const response=await fetch(unlinkUrl,{method:'DELETE',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf()}});
      const data=await response.json().catch(()=>({})); if(!response.ok||data.success!==true) throw new Error(data.message||'No se pudo eliminar el vínculo.');
      updateRow('',''); currentData={linked:false,programaId}; selectValue(null,null); close();
      window.Swal?.fire({
        icon:'success',
        title:'Vínculo eliminado',
        text:'El ID y nombre de Redbooth fueron eliminados de Programa Tejido y CatCodificados.',
        confirmButtonText:'Aceptar',
      });
    } catch(error) { window.Swal?.fire({icon:'error',title:'No se pudo eliminar',text:error.message}); }
    finally { button.disabled = false; }
  });
  const closeImageViewer = () => {
    imageViewer.classList.add('hidden'); imageViewer.classList.remove('flex'); imageViewerImg.src = '';
  };
  byId('rbComentarios').addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-rb-image]');
    if (!trigger) return;
    imageViewerImg.src = trigger.dataset.rbImage;
    imageViewerImg.alt = trigger.dataset.rbImageName || 'Imagen de Redbooth';
    imageViewerDownload.href = trigger.dataset.rbImage;
    imageViewer.classList.remove('hidden'); imageViewer.classList.add('flex');
  });
  byId('cerrarRedboothImageViewer').addEventListener('click', closeImageViewer);
  imageViewer.addEventListener('click', (event) => { if (event.target === imageViewer) closeImageViewer(); });
  modal.addEventListener('click',(event)=>{if(event.target===modal)close();});
  document.addEventListener('keydown',(event)=>{
    if (event.key !== 'Escape') return;
    if (!imageViewer.classList.contains('hidden')) { closeImageViewer(); return; }
    if (!modal.classList.contains('hidden')) close();
  });
}
