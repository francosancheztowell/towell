@extends('layouts.app')

@section('content')
<div class="container">



    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <table class="table table-bordered table-sm w-full">
                <thead class="sticky top-0 bg-blue-500 text-white z-10">
                    <tr>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Clave</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Nombre</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Salón</th>
                        <th class="py-1 px-2 font-bold uppercase tracking-wider text-center">Telar</th>
                    </tr>
                </thead>
                <tbody id="aplicaciones-body" class="bg-white text-black">
                    @foreach ($aplicaciones as $item)
                        @php
                            $uniqueId = $item->AplicacionId;
                            $recordId = $item->Id ?? $item->id ?? null;
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="selectRow(this, '{{ $uniqueId }}', '{{ $uniqueId }}')"
                            ondblclick="deselectRow(this)"
                            data-aplicacion="{{ $uniqueId }}"
                            data-aplicacion-id="{{ $uniqueId }}"
                            data-clave="{{ $item->AplicacionId }}"
                            data-nombre="{{ $item->Nombre }}"
                            data-salon="{{ $item->SalonTejidoId }}"
                            data-telar="{{ $item->NoTelarId }}"
                        >
                            <td class="py-1 px-4 border-b">{{ $item->AplicacionId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->Nombre }}</td>
                            <td class="py-1 px-4 border-b font-semibold">{{ $item->SalonTejidoId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->NoTelarId }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .scrollbar-thin { scrollbar-width: thin; }
    .scrollbar-thin::-webkit-scrollbar { width: 8px; }
    .scrollbar-thumb-gray-400::-webkit-scrollbar-thumb { background-color: #9ca3af; border-radius: 4px; }
    .scrollbar-track-gray-100::-webkit-scrollbar-track { background-color: #f3f4f6; }
    .scrollbar-thin::-webkit-scrollbar-thumb:hover { background-color: #6b7280; }
    .swal2-input { width: 100% !important; }
</style>

<script>
/* ===========================
   Estado
=========================== */
let selectedRow = null;
let selectedKey = null;
let selectedId  = null;

let filtrosActuales = { clave:'', nombre:'', salon:'', telar:'' };
let datosOriginales = @json($aplicaciones);
let datosActuales   = datosOriginales;
const cacheFiltros  = new Map();
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

/* ===========================
   Helpers UI
=========================== */
function crearToast(icon, msg, ms = 1500) {
  const Toast = Swal.mixin({
    toast:true, position:'top-end', showConfirmButton:false, timer:ms, timerProgressBar:true,
    didOpen:(t)=>{ t.addEventListener('mouseenter', Swal.stopTimer); t.addEventListener('mouseleave', Swal.resumeTimer); }
  });
  Toast.fire({icon, title:msg});
}

function enableButtons() {
  const e = document.getElementById('btn-editar');
  const d = document.getElementById('btn-eliminar');
  if (e) { e.disabled=false; e.className='inline-flex items-center px-3 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-sm'; }
  if (d) { d.disabled=false; d.className='inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm'; }
}
function disableButtons() {
  const e = document.getElementById('btn-editar');
  const d = document.getElementById('btn-eliminar');
  if (e) { e.disabled=true; e.className='inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded cursor-not-allowed text-sm'; }
  if (d) { d.disabled=true; d.className='inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded cursor-not-allowed text-sm'; }
}

/* ===========================
   Selección
=========================== */
function selectRow(row, uniqueId, id) {
  document.querySelectorAll('#aplicaciones-body tr').forEach(r=>{
    r.classList.remove('bg-blue-500','text-white');
    r.classList.add('hover:bg-blue-50');
  });
  row.classList.remove('hover:bg-blue-50');
  row.classList.add('bg-blue-500','text-white');

  selectedRow = row;
  selectedKey = uniqueId;
  selectedId  = (id && id !== 'null' && id !== '') ? id : uniqueId; // fallback a clave
  enableButtons();
}
function deselectRow(row) {
  if (!row.classList.contains('bg-blue-500')) return;
  row.classList.remove('bg-blue-500','text-white');
  row.classList.add('hover:bg-blue-50');
  selectedRow = null; selectedKey = null; selectedId = null;
  disableButtons();
}

/* ===========================
   Crear (con preConfirm async)
=========================== */
function agregarAplicacion() {
  Swal.fire({
    title:'Agregar Nueva Aplicación',
    html: `
      <div class="grid grid-cols-2 gap-3 text-sm text-left">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Clave *</label>
          <input id="swal-clave" type="text" class="swal2-input" placeholder="APP001" maxlength="50" required>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Nombre *</label>
          <input id="swal-nombre" type="text" class="swal2-input" placeholder="Sistema" maxlength="100" required>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Salón *</label>
          <input id="swal-salon" type="text" class="swal2-input" placeholder="JACQUARD / SMITH" maxlength="50" required>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Telar *</label>
          <input id="swal-telar" type="text" class="swal2-input" placeholder="201, 300, ..." maxlength="50" required>
        </div>
      </div>
    `,
    width:'520px',
    showCancelButton:true,
    confirmButtonText:'<i class="fas fa-plus mr-2"></i>Agregar',
    cancelButtonText:'<i class="fas fa-times mr-2"></i>Cancelar',
    confirmButtonColor:'#10b981',
    cancelButtonColor:'#6b7280',
    didOpen:()=>{ Swal.getConfirmButton().focus(); },
    preConfirm: async () => {
      const AplicacionId  = document.getElementById('swal-clave').value.trim();
      const Nombre        = document.getElementById('swal-nombre').value.trim();
      const SalonTejidoId = document.getElementById('swal-salon').value.trim();
      const NoTelarId     = document.getElementById('swal-telar').value.trim();

      if (!AplicacionId || !Nombre || !SalonTejidoId || !NoTelarId) {
        Swal.showValidationMessage('Por favor completa los campos requeridos'); return false;
      }

      Swal.showLoading();

      const form = new FormData();
      CSRF_TOKEN && form.append('_token', CSRF_TOKEN);
      form.append('AplicacionId', AplicacionId);
      form.append('Nombre', Nombre);
      form.append('SalonTejidoId', SalonTejidoId);
      form.append('NoTelarId', NoTelarId);

      try {
        const r = await fetch('/planeacion/aplicaciones', { method:'POST', body: form, headers:{ 'Accept':'application/json' } });
        if (r.status === 422) {
          const data = await r.json();
          const errs = data?.errors || {};
          let msg = data?.message || 'Error en la validación';
          if (errs.AplicacionId?.length) msg = errs.AplicacionId[0];
          if (errs.Nombre?.length) msg = errs.Nombre[0];
          if (errs.SalonTejidoId?.length) msg = errs.SalonTejidoId[0];
          if (errs.NoTelarId?.length) msg = errs.NoTelarId[0];
          Swal.showValidationMessage(msg);
          return false;
        }
        if (!r.ok) {
          const t = await r.text();
          Swal.showValidationMessage(t || `HTTP ${r.status}`);
          return false;
        }
        const data = await r.json();
        if (!data.success) {
          Swal.showValidationMessage(data.message || 'No se pudo crear.');
          return false;
        }
        return data;
      } catch (e) {
        Swal.showValidationMessage(e.message || 'Error de red');
        return false;
      }
    }
  }).then(res=>{
    if (res.isConfirmed) {
      // Modal sigue siendo el mismo: mostramos éxito con update
      Swal.update({ icon:'success', title:'¡Aplicación creada!', html:'', showConfirmButton:false });
      setTimeout(()=> location.reload(), 800);
    }
  });
}

/* ===========================
   Editar (con preConfirm async) — maneja 422 sin cerrar modal
=========================== */
function editarAplicacion() {
  if (!selectedRow || !selectedId) {
    Swal.fire({ icon:'warning', title:'Atención', text:'Selecciona una aplicación para editar' });
    return;
  }

  const claveActual  = selectedRow.getAttribute('data-clave');
  const nombreActual = selectedRow.getAttribute('data-nombre');
  const salonActual  = selectedRow.getAttribute('data-salon');
  const telarActual  = selectedRow.getAttribute('data-telar');

  Swal.fire({
    title:'Editar Aplicación',
    html: `
      <div class="grid grid-cols-2 gap-3 text-sm text-left">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Clave *</label>
          <input id="swal-clave-edit" type="text" class="swal2-input" maxlength="50" required value="${claveActual}">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Nombre *</label>
          <input id="swal-nombre-edit" type="text" class="swal2-input" maxlength="100" required value="${nombreActual}">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Salón *</label>
          <input id="swal-salon-edit" type="text" class="swal2-input" maxlength="50" required value="${salonActual}">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Telar *</label>
          <input id="swal-telar-edit" type="text" class="swal2-input" maxlength="50" required value="${telarActual}">
        </div>
      </div>
    `,
    width:'520px',
    showCancelButton:true,
    confirmButtonText:'<i class="fas fa-save mr-2"></i>Actualizar',
    cancelButtonText:'<i class="fas fa-times mr-2"></i>Cancelar',
    confirmButtonColor:'#ffc107',
    cancelButtonColor:'#6b7280',
    didOpen:()=>{ Swal.getConfirmButton().focus(); },
    preConfirm: async () => {
      const AplicacionId  = document.getElementById('swal-clave-edit').value.trim();
      const Nombre        = document.getElementById('swal-nombre-edit').value.trim();
      const SalonTejidoId = document.getElementById('swal-salon-edit').value.trim();
      const NoTelarId     = document.getElementById('swal-telar-edit').value.trim();

      if (!AplicacionId || !Nombre || !SalonTejidoId || !NoTelarId) {
        Swal.showValidationMessage('Por favor completa los campos requeridos'); return false;
      }

      Swal.showLoading();

      const form = new FormData();
      CSRF_TOKEN && form.append('_token', CSRF_TOKEN);
      form.append('_method', 'PUT');
      form.append('AplicacionId', AplicacionId);
      form.append('Nombre', Nombre);
      form.append('SalonTejidoId', SalonTejidoId);
      form.append('NoTelarId', NoTelarId);

      try {
        const r = await fetch(`/planeacion/aplicaciones/${encodeURIComponent(selectedId)}`, {
          method:'POST', body: form, headers:{ 'Accept':'application/json' }
        });

        if (r.status === 422) {
          const data = await r.json();
          const errs = data?.errors || {};
          // Muestra el error de validación más relevante dentro del mismo modal
          let msg = data?.message || 'Error en la validación';
          if (errs.AplicacionId?.length) msg = errs.AplicacionId[0]; // <-- "has already been taken"
          else if (errs.Nombre?.length) msg = errs.Nombre[0];
          else if (errs.SalonTejidoId?.length) msg = errs.SalonTejidoId[0];
          else if (errs.NoTelarId?.length) msg = errs.NoTelarId[0];
          Swal.showValidationMessage(msg);
          return false;
        }

        if (!r.ok) {
          const t = await r.text();
          Swal.showValidationMessage(t || `HTTP ${r.status}`);
          return false;
        }

        const data = await r.json();
        if (!data.success) {
          Swal.showValidationMessage(data.message || 'No se pudo actualizar.');
          return false;
        }
        return data;
      } catch (e) {
        Swal.showValidationMessage(e.message || 'Error de red');
        return false;
      }
    }
  }).then(res=>{
    if (res.isConfirmed) {
      Swal.update({ icon:'success', title:'¡Aplicación actualizada!', html:'', showConfirmButton:false });
      setTimeout(()=> location.reload(), 800);
    }
  });
}

/* ===========================
   Eliminar — simple, sin cadenas de modales
=========================== */
function eliminarAplicacion() {
  if (!selectedRow || !selectedId) {
    Swal.fire({ icon:'warning', title:'Atención', text:'Selecciona una aplicación para eliminar' });
    return;
  }

  const clave  = selectedRow.getAttribute('data-clave');
  const nombre = selectedRow.getAttribute('data-nombre');

  Swal.fire({
    title:'¿Eliminar Aplicación?',
    html: `
      <div class="text-left">
        <p><strong>Clave:</strong> ${clave}</p>
        <p><strong>Nombre:</strong> ${nombre}</p>
        <hr>
        <p class="text-red-600 font-semibold">Esta acción no se puede deshacer.</p>
      </div>
    `,
    icon:'warning',
    showCancelButton:true,
    confirmButtonColor:'#dc2626',
    cancelButtonColor:'#6b7280',
    confirmButtonText:'<i class="fas fa-trash mr-2"></i>Sí, eliminar',
    cancelButtonText:'<i class="fas fa-times mr-2"></i>Cancelar',
    preConfirm: async () => {
      Swal.showLoading();
      try {
        const r = await fetch(`/planeacion/aplicaciones/${encodeURIComponent(selectedId)}`, {
          method:'DELETE',
          headers:{ 'Content-Type':'application/json', ...(CSRF_TOKEN ? {'X-CSRF-TOKEN':CSRF_TOKEN} : {}), 'Accept':'application/json' }
        });
        if (!r.ok) {
          const t = await r.text();
          Swal.showValidationMessage(t || `HTTP ${r.status}`);
          return false;
        }
        const data = await r.json();
        if (!data.success) {
          Swal.showValidationMessage(data.message || 'No se pudo eliminar.');
          return false;
        }
        return true;
      } catch(e) {
        Swal.showValidationMessage(e.message || 'Error de red');
        return false;
      }
    }
  }).then(res=>{
    if (res.isConfirmed) {
      Swal.update({ icon:'success', title:'¡Aplicación eliminada!', html:'', showConfirmButton:false });
      setTimeout(()=> location.reload(), 800);
    }
  });
}

/* ===========================
   Excel
=========================== */
function subirExcelAplicaciones() {
  Swal.fire({
    title:'Subir Excel - Aplicaciones',
    html: `
      <div class="text-left">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Seleccionar archivo Excel</label>
          <input id="excel-file" type="file" accept=".xlsx,.xls" class="swal2-input">
        </div>
        <div class="text-sm text-gray-600 bg-blue-50 p-3 rounded">
          <i class="fas fa-info-circle mr-1"></i> Formatos soportados: .xlsx, .xls (máximo 10MB)
        </div>
      </div>
    `,
    width:'500px',
    showCancelButton:true,
    confirmButtonText:'<i class="fas fa-upload mr-2"></i>Subir',
    cancelButtonText:'<i class="fas fa-times mr-2"></i>Cancelar',
    confirmButtonColor:'#198754',
    cancelButtonColor:'#6c757d',
    preConfirm: async () => {
      const file = document.getElementById('excel-file').files[0];
      if (!file) { Swal.showValidationMessage('Por favor selecciona un archivo Excel'); return false; }
      Swal.showLoading();

      const form = new FormData();
      form.append('archivo_excel', file);
      CSRF_TOKEN && form.append('_token', CSRF_TOKEN);

      try {
        const r = await fetch('/planeacion/aplicaciones/excel', { method:'POST', body: form, headers:{ 'Accept':'application/json' } });
        if (!r.ok) {
          const t = await r.text();
          Swal.showValidationMessage(t || `HTTP ${r.status}`);
          return false;
        }
        const data = await r.json();
        if (!data.success) {
          Swal.showValidationMessage(data.message || 'No se pudo procesar el Excel.');
          return false;
        }
        return true;
      } catch(e) {
        Swal.showValidationMessage(e.message || 'Error de red');
        return false;
      }
    }
  }).then(res=>{
    if (res.isConfirmed) {
      Swal.update({ icon:'success', title:'¡Excel procesado!', html:'', showConfirmButton:false });
      setTimeout(()=> location.reload(), 800);
    }
  });
}

/* ===========================
   Filtros
=========================== */
function mostrarFiltros() {
  Swal.fire({
    title:'Filtrar Aplicaciones',
    html: `
      <div class="grid grid-cols-2 gap-3 text-sm text-left">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Clave</label>
          <input id="swal-clave-filter" type="text" class="swal2-input" placeholder="APP001" value="${filtrosActuales.clave || ''}">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Nombre</label>
          <input id="swal-nombre-filter" type="text" class="swal2-input" placeholder="Sistema" value="${filtrosActuales.nombre || ''}">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Salón</label>
          <input id="swal-salon-filter" type="text" class="swal2-input" placeholder="JACQUARD / SMITH" value="${filtrosActuales.salon || ''}">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Telar</label>
          <input id="swal-telar-filter" type="text" class="swal2-input" placeholder="201, 300, ..." value="${filtrosActuales.telar || ''}">
        </div>
      </div>
      <div class="mt-3 text-xs text-gray-500 bg-blue-50 p-2 rounded">
        <i class="fas fa-info-circle mr-1"></i> Deja campos vacíos para no aplicar filtro.
      </div>
    `,
    width:'520px',
    showCancelButton:true,
    confirmButtonText:'<i class="fas fa-filter mr-2"></i>Filtrar',
    cancelButtonText:'<i class="fas fa-times mr-2"></i>Cancelar',
    confirmButtonColor:'#3b82f6',
    cancelButtonColor:'#6b7280',
    preConfirm: () => {
      const clave  = document.getElementById('swal-clave-filter').value.trim();
      const nombre = document.getElementById('swal-nombre-filter').value.trim();
      const salon  = document.getElementById('swal-salon-filter').value.trim();
      const telar  = document.getElementById('swal-telar-filter').value.trim();
      return { clave, nombre, salon, telar };
    }
  }).then(res=>{
    if (res.isConfirmed && res.value) aplicarFiltros(res.value);
  });
}

function aplicarFiltros(f) {
  filtrosActuales = { ...f };

  const cacheKey = JSON.stringify(filtrosActuales);
  if (cacheFiltros.has(cacheKey)) {
    const cached = cacheFiltros.get(cacheKey);
    datosActuales = cached; actualizarTablaOptimizada(cached); actualizarContador();
    crearToast('success', `${cached.length} de ${datosOriginales.length} registros mostrados`);
    return;
  }

  const filtrados = (datosOriginales || []).filter(it=>{
    const clave  = String(it.AplicacionId  || '').toLowerCase();
    const nombre = String(it.Nombre        || '').toLowerCase();
    const salon  = String(it.SalonTejidoId || '').toLowerCase();
    const telar  = String(it.NoTelarId     || '').toLowerCase();
    if (f.clave && !clave.includes(f.clave.toLowerCase())) return false;
    if (f.nombre && !nombre.includes(f.nombre.toLowerCase())) return false;
    if (f.salon && !salon.includes(f.salon.toLowerCase())) return false;
    if (f.telar && !telar.includes(f.telar.toLowerCase())) return false;
    return true;
  });

  if (cacheFiltros.size >= 10) cacheFiltros.delete(cacheFiltros.keys().next().value);
  cacheFiltros.set(cacheKey, filtrados);

  datosActuales = filtrados;
  actualizarTablaOptimizada(filtrados);
  actualizarContador();
  crearToast('success', `${filtrados.length} de ${datosOriginales.length} registros mostrados`);
}

/* ===========================
   Render
=========================== */
function actualizarTablaOptimizada(datos) {
  const tbody = document.getElementById('aplicaciones-body');
  if (!datos || datos.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" class="text-center py-8 text-gray-500">
          <i class="fas fa-search text-4xl mb-2"></i><br>No se encontraron resultados
        </td>
      </tr>`;
    return;
  }
   const frag = document.createDocumentFragment();
   datos.forEach(item=>{
     const tr = document.createElement('tr');
     const uniqueId = item.AplicacionId;

     tr.className = 'text-center hover:bg-blue-50 transition cursor-pointer';
     tr.onclick = () => selectRow(tr, uniqueId, uniqueId);
     tr.ondblclick = () => deselectRow(tr);

     tr.setAttribute('data-aplicacion', uniqueId);
     tr.setAttribute('data-aplicacion-id', uniqueId);
     tr.setAttribute('data-clave', item.AplicacionId);
     tr.setAttribute('data-nombre', item.Nombre);
     tr.setAttribute('data-salon', item.SalonTejidoId);
     tr.setAttribute('data-telar', item.NoTelarId);

    tr.innerHTML = `
      <td class="py-1 px-4 border-b">${item.AplicacionId}</td>
      <td class="py-1 px-4 border-b">${item.Nombre}</td>
      <td class="py-1 px-4 border-b font-semibold">${item.SalonTejidoId}</td>
      <td class="py-1 px-4 border-b">${item.NoTelarId}</td>`;
    frag.appendChild(tr);
  });
  tbody.innerHTML = '';
  tbody.appendChild(frag);
}

function actualizarContador() {
  const el = document.getElementById('filter-count');
  if (!el) return;
  const n = Object.values(filtrosActuales).filter(v => v !== '' && v !== null && v !== undefined).length;
  if (n > 0) { el.textContent = n; el.classList.remove('hidden'); }
  else { el.classList.add('hidden'); }
}

/* ===========================
   Bootstrap seguro
=========================== */
document.addEventListener('DOMContentLoaded', ()=>{
  disableButtons();
  const byId = id => document.getElementById(id);
  [
    ['btn-agregar',  agregarAplicacion],
    ['btn-editar',   editarAplicacion],
    ['btn-eliminar', eliminarAplicacion],
    ['btn-filtrar',  mostrarFiltros],
    ['btn-limpiar',  limpiarFiltros],
    ['btn-excel',    subirExcelAplicaciones],
  ].forEach(([id,fn])=>{ const el=byId(id); el && el.addEventListener('click', fn); });
});

/* ===========================
   Aliases globales para navbar
=========================== */
window.agregarAplicacion  = agregarAplicacion;
window.editarAplicacion   = editarAplicacion;
window.eliminarAplicacion = eliminarAplicacion;
window.mostrarFiltros     = mostrarFiltros;
window.limpiarFiltros     = limpiarFiltros;
window.subirExcelAplicaciones = subirExcelAplicaciones;

// En caso de que tus botones llamen en plural
window.agregarAplicaciones        = () => agregarAplicacion();
window.editarAplicaciones         = () => editarAplicacion();
window.eliminarAplicaciones       = () => eliminarAplicacion();
window.filtrarAplicaciones        = () => mostrarFiltros();
window.limpiarFiltrosAplicaciones = () => limpiarFiltros();

/* ===========================
   Limpiar filtros
=========================== */
function limpiarFiltros() {
  filtrosActuales = { clave:'', nombre:'', salon:'', telar:'' };
  cacheFiltros.clear();
  datosActuales = datosOriginales;
  actualizarTablaOptimizada(datosOriginales);
  actualizarContador();
  crearToast('success', `Filtros limpiados - Mostrando ${datosOriginales.length} registros`, 1600);
}
</script>
@endsection
