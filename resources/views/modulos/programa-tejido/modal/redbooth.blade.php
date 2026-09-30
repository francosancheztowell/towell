<style>
  #modalRedboothProgramaTejido .rb-shell { width:min(940px,96vw); height:min(900px,94vh); transition:width .18s ease,height .18s ease; }
  #modalRedboothProgramaTejido .rb-shell.rb-compact { width:min(560px,94vw); height:auto; max-height:94vh; }
  #modalRedboothProgramaTejido .rb-shell.rb-compact #redboothLoading { min-height:210px; flex:none; }
  #modalRedboothProgramaTejido .rb-shell.rb-compact #redboothEditor { flex:none; }
  #modalRedboothProgramaTejido .rb-scroll { scrollbar-color:#cbd5e1 transparent; scrollbar-width:thin; }
  #modalRedboothProgramaTejido .rb-comment:last-child { border-bottom:0; }
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

{{-- La lógica vive en resources/js/modulos/redbooth/ (HANDOFF PT-05 B4); aquí solo los valores del servidor. --}}
@php
  $redboothBoot = [
    'contexto' => $redboothContext ?? 'programa',
    'rutas' => [
      'show' => route('programa-tejido.redbooth.show', ['programa' => '__ID__']),
      'destroy' => route('programa-tejido.redbooth.destroy', ['programa' => '__ID__']),
      'descargaArchivo' => route('redbooth.files.download', ['fileId' => '__FILE_ID__']),
      'proyectos' => route('programa-tejido.redbooth.proyectos'),
      'store' => route('programa-tejido.redbooth.store'),
    ],
  ];
@endphp
<script type="application/json" id="redbooth-boot">@json($redboothBoot)</script>
@vite('resources/js/modulos/redbooth/index.ts')
