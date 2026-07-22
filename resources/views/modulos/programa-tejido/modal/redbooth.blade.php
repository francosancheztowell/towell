@php($redboothContext = $redboothContext ?? 'programa')
<style>
  #modalRedboothProgramaTejido .rb-shell { width:min(940px,96vw); height:min(900px,94vh); transition:width .18s ease,height .18s ease; }
  #modalRedboothProgramaTejido .rb-shell.rb-compact { width:min(560px,94vw); height:auto; max-height:94vh; }
  #modalRedboothProgramaTejido .rb-shell.rb-compact #redboothLoading { min-height:210px; flex:none; }
  #modalRedboothProgramaTejido .rb-shell.rb-compact #redboothEditor { flex:none; }
  #modalRedboothProgramaTejido .rb-scroll { scrollbar-color:#cbd5e1 transparent; scrollbar-width:thin; }
  #modalRedboothProgramaTejido .rb-comment:last-child { border-bottom:0; }
  #modalRedboothProgramaTejido > .select2-container,
  #modalRedboothProgramaTejido .select2-container--open,
  #modalRedboothProgramaTejido .select2-dropdown { z-index:2147483050 !important; }
  #modalRedboothProgramaTejido .rb-rich { color:#4b5563; font-size:.875rem; line-height:1.55; overflow-wrap:anywhere; }
  #modalRedboothProgramaTejido .rb-rich > * + * { margin-top:.75rem; }
  #modalRedboothProgramaTejido .rb-rich strong { color:#374151; font-weight:700; }
  #modalRedboothProgramaTejido .rb-rich hr { margin:1rem 0; border:0; border-top:1px solid #d1d5db; }
  #modalRedboothProgramaTejido .rb-rich table { width:100%; margin:1rem 0; border-collapse:collapse; table-layout:auto; background:#fff; }
  #modalRedboothProgramaTejido .rb-rich th,
  #modalRedboothProgramaTejido .rb-rich td { border:1px solid #e5e7eb; padding:.7rem .75rem; text-align:left; vertical-align:top; }
  #modalRedboothProgramaTejido .rb-rich th { background:#f3f4f6; color:#374151; font-weight:700; }
  #modalRedboothProgramaTejido .rb-rich td:first-child { width:1%; min-width:130px; font-weight:600; }
  #modalRedboothProgramaTejido .rb-rich ul { margin:.65rem 0; padding-left:1.6rem; list-style:disc; }
  #modalRedboothProgramaTejido .rb-rich ol { margin:.65rem 0; padding-left:1.6rem; list-style:decimal; }
  #modalRedboothProgramaTejido .rb-rich li + li { margin-top:.45rem; }
  #modalRedboothProgramaTejido .rb-rich code { border-radius:3px; background:#f3f4f6; padding:.15rem .35rem; color:#374151; font-family:monospace; font-size:.8rem; }
  #modalRedboothProgramaTejido .rb-rich a { color:#4f6bed; text-decoration:none; }
  #modalRedboothProgramaTejido .rb-rich img { display:block; width:auto; max-width:100%; max-height:520px; margin:.75rem 0; border:1px solid #e5e7eb; border-radius:.4rem; object-fit:contain; background:#f9fafb; cursor:zoom-in; }
  #modalRedboothProgramaTejido .rb-inline-image { display:table; max-width:100%; margin:.75rem 0; overflow:hidden; border:1px solid #e5e7eb; border-radius:.4rem; background:#f9fafb; }
  #modalRedboothProgramaTejido .rb-inline-image img { margin:0; border:0; border-radius:0; }
  #modalRedboothProgramaTejido .rb-inline-image-actions { display:flex; align-items:center; justify-content:flex-end; gap:1rem; padding:.55rem .75rem; border-top:1px solid #e5e7eb; }
  #modalRedboothProgramaTejido .rb-inline-image-actions button,
  #modalRedboothProgramaTejido .rb-inline-image-actions a { color:#2563eb; font-size:.75rem; font-weight:600; cursor:pointer; }
  @media (max-width:640px) {
    #modalRedboothProgramaTejido .rb-rich table { display:block; overflow-x:auto; white-space:normal; }
    #modalRedboothProgramaTejido .rb-rich td:first-child { min-width:105px; }
  }
</style>

<div id="modalRedboothProgramaTejido"
  class="fixed inset-0 z-[10000] hidden items-center justify-center p-3"
  style="position:fixed;inset:0;z-index:2147483000;background-color:rgba(0,0,0,.82);backdrop-filter:blur(2px)"
  role="dialog" aria-modal="true" aria-labelledby="modalRedboothProgramaTejidoTitulo">
  <div class="rb-shell rb-compact relative flex flex-col overflow-hidden rounded-lg bg-white shadow-2xl" style="z-index:1">
    <header class="flex shrink-0 items-center gap-3 border-b border-gray-200 px-4 py-3">
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <span id="redboothTaskId" class="text-sm font-semibold text-gray-500">Redbooth</span>
          <h2 id="modalRedboothProgramaTejidoTitulo" class="truncate text-lg font-semibold text-gray-800">
            Vincular tarea
          </h2>
        </div>
        <p id="redboothTaskContext" class="mt-0.5 truncate text-xs text-gray-500">Programa de tejido</p>
      </div>
      <button type="button" id="editarModalRedboothProgramaTejido"
        class="hidden items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
        <i class="fas fa-pen"></i> Editar vínculo
      </button>
      <button type="button" id="eliminarModalRedboothProgramaTejido"
        class="hidden items-center gap-2 rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50">
        <i class="fas fa-unlink"></i> Eliminar vínculo
      </button>
      <button type="button" id="cerrarModalRedboothProgramaTejido"
        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100"
        aria-label="Cerrar"><i class="fas fa-times"></i></button>
    </header>

    <section id="redboothLoading" class="flex flex-1 items-center justify-center text-gray-500">
      <div class="text-center"><i class="fas fa-circle-notch fa-spin text-2xl text-blue-600"></i><p class="mt-3 text-sm">Consultando Redbooth…</p></div>
    </section>

    <section id="redboothEditor" class="hidden flex-1 overflow-y-auto p-6">
      <div class="mx-auto max-w-xl rounded-lg border border-gray-200 bg-gray-50 p-5">
        <label for="redboothProgramaTejidoProyecto" class="mb-2 block text-sm font-semibold text-gray-700">Proyecto</label>
        <select id="redboothProgramaTejidoProyecto" name="redbooth_task_id" class="w-full"><option value=""></option></select>
        <p class="mt-2 text-xs text-gray-500">Busca por ID o nombre de la tarea de Redbooth.</p>
        <div class="mt-5 flex justify-end gap-2">
          <button type="button" id="cancelarEdicionRedbooth" class="hidden rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white">Cancelar</button>
          <button type="button" id="guardarModalRedboothProgramaTejido" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Guardar
          </button>
        </div>
      </div>
    </section>

    <section id="redboothDeleteConfirm" class="hidden flex-1 p-6">
      <div class="mx-auto max-w-xl rounded-lg border border-red-200 bg-red-50 p-5">
        <div class="flex gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600"><i class="fas fa-unlink"></i></div>
          <div>
            <h3 class="text-base font-semibold text-gray-900">Eliminar vínculo de Redbooth</h3>
            <p class="mt-1 text-sm leading-6 text-gray-600">Se limpiarán el ID y el nombre de Redbooth en Programa Tejido y en todos los registros de CatCodificados ligados por la orden.</p>
          </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button type="button" id="cancelarEliminarRedbooth" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
          <button type="button" id="confirmarEliminarRedbooth" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"><i class="fas fa-trash-alt mr-1"></i> Eliminar vínculo</button>
        </div>
      </div>
    </section>

    <section id="redboothViewer" class="rb-scroll hidden flex-1 overflow-y-auto">
      <div class="mx-auto max-w-3xl px-5 py-6">
        <div class="border-b border-gray-200 pb-5">
          <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-md bg-gray-50 p-3"><span class="block text-xs font-semibold uppercase text-gray-400">Estado</span><span id="rbEstado" class="mt-1 inline-flex rounded-full px-2 py-0.5 text-sm font-semibold text-gray-700">—</span></div>
            <div class="rounded-md bg-gray-50 p-3"><span class="block text-xs font-semibold uppercase text-gray-400">Fecha inicio</span><span id="rbInicio" class="mt-1 block text-sm text-gray-700">—</span></div>
            <div class="rounded-md bg-gray-50 p-3"><span class="block text-xs font-semibold uppercase text-gray-400">Fecha límite</span><span id="rbLimite" class="mt-1 block text-sm text-gray-700">—</span></div>
          </div>
          <div class="mt-4">
            <span class="text-xs font-semibold uppercase text-gray-400">Descripción</span>
            <div id="rbDescripcion" class="rb-rich mt-2">Sin descripción.</div>
          </div>
        </div>

        <h3 class="mt-5 border-b-2 border-blue-600 px-1 pb-3 text-sm font-semibold text-blue-600">Comentarios</h3>
        <div id="rbComentarios" class="py-2"></div>
      </div>
    </section>
  </div>

  <div id="redboothImageViewer" class="fixed inset-0 hidden items-center justify-center p-4"
    style="z-index:2147483100;background:rgba(0,0,0,.94)">
    <button type="button" id="cerrarRedboothImageViewer" class="absolute right-5 top-5 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-xl text-white hover:bg-white/20" aria-label="Cerrar imagen">
      <i class="fas fa-times"></i>
    </button>
    <img id="redboothImageViewerImg" src="" alt="Imagen de Redbooth" class="max-h-[88vh] max-w-[94vw] object-contain">
    <a id="descargarRedboothImageViewer" href="#" target="_blank" rel="noopener" class="absolute bottom-5 right-5 inline-flex items-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-lg hover:bg-gray-100">
      <i class="fas fa-download"></i> Descargar
    </a>
  </div>
</div>

<script>
(() => {
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

  const showUrl = @json(route('programa-tejido.redbooth.show', ['programa' => '__ID__']));
  const deleteUrl = @json(route('programa-tejido.redbooth.destroy', ['programa' => '__ID__']));
  const fileDownloadUrl = @json(route('redbooth.files.download', ['fileId' => '__FILE_ID__']));
  const defaultRecordSource = @json($redboothContext);
  let recordSource = defaultRecordSource;
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  let programaId = null;
  let currentData = null;
  let selectInicializado = false;

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
    const jq = window.jQuery || window.$;
    if (selectInicializado || !jq?.fn?.select2) return;
    jq(proyecto).select2({
      width:'100%', dropdownParent:jq(modal), placeholder:'Selecciona una tarea', allowClear:true,
      ajax:{url:@json(route('programa-tejido.redbooth.proyectos')),dataType:'json',delay:250,data:(p)=>({q:p.term||''}),processResults:(d)=>({results:Array.isArray(d?.results)?d.results:[]}),cache:true},
      language:{noResults:()=> 'No se encontraron tareas',searching:()=> 'Buscando…',loadingError:()=> 'No se pudieron cargar las tareas'},
    });
    selectInicializado = true;
  };
  const selectValue = (id, name) => {
    const jq = window.jQuery || window.$;
    if (!jq || !selectInicializado) return;
    jq(proyecto).empty();
    if (id) jq(proyecto).append(new Option(`${id} — ${name}`, id, true, true));
    else jq(proyecto).append(new Option('', '', true, true));
    jq(proyecto).trigger('change');
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
      else { byId('redboothTaskId').textContent='Redbooth'; byId('modalRedboothProgramaTejidoTitulo').textContent='Vincular tarea'; selectValue(null, null); setMode('editor'); }
    } catch (error) { setMode('editor'); window.Swal?.fire({icon:'error',title:'No se pudo cargar Redbooth',text:error.message}); }
  };

  window.abrirModalRedboothProgramaTejido = ({registroId, source}={}) => {
    recordSource = String(source || defaultRecordSource) === 'catcodificados' ? 'catcodificados' : 'programa';
    programaId = Number(registroId)||null; currentData=null;
    if (!programaId) return;
    modal.classList.remove('hidden'); modal.classList.add('flex'); inicializarSelect(); loadDetail();
  };
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
  byId('cerrarModalRedboothProgramaTejido').addEventListener('click', close);
  editar.addEventListener('click', () => { selectValue(currentData?.idRedbooth, currentData?.nombreRedbooth); setMode('editor'); });
  cancelar.addEventListener('click', () => currentData?.linked ? renderViewer(currentData) : close());
  guardar.addEventListener('click', async () => {
    const jq = window.jQuery || window.$; const taskId = Number(jq?.(proyecto).val()||0);
    if (!taskId) { window.Swal?.fire({icon:'warning',title:'Selecciona una tarea'}); return; }
    guardar.disabled=true;
    try {
      const payload = recordSource === 'catcodificados'
        ? {source:'catcodificados',cat_codificados_id:programaId,redbooth_task_id:taskId}
        : {source:'programa',req_programa_tejido_id:programaId,redbooth_task_id:taskId};
      const response = await fetch(@json(route('programa-tejido.redbooth.store')),{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify(payload)});
      const data=await response.json().catch(()=>({}));
      if(!response.ok||data.success!==true) throw new Error(Object.values(data.errors||{}).flat().find(Boolean)||data.message||'No se pudo guardar.');
      updateRow(data.idRedbooth,data.nombreRedbooth); await loadDetail();
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
})();
</script>
