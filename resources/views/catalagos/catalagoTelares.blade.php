@extends('layouts.app')

@section('page-title', 'Catálogo de Telares')

@section('content')
    <div class="container">
    @if ($noResults ?? false)
        <div class="alert alert-warning text-center">No se encontraron resultados con la información proporcionada.</div>
        @endif

    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-blue-500 border-b-2 text-white z-20">
                    <tr>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Salón</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Telar</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Nombre</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Grupo</th>
                    </tr>
                </thead>
                <tbody id="telares-body" class="bg-white text-black">
                    @foreach ($telares as $t)
                        @php $uid = $t->SalonTejidoId . '_' . $t->NoTelarId; @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="selectRow(this)"
                            ondblclick="deselectRow(this)"
                            data-uid="{{ $uid }}"
                            data-salon="{{ $t->SalonTejidoId }}"
                            data-telar="{{ $t->NoTelarId }}"
                            data-nombre="{{ $t->Nombre }}"
                            data-grupo="{{ $t->Grupo ?? '' }}">
                            <td class="py-2 px-4 border-b">{{ $t->SalonTejidoId }}</td>
                            <td class="py-2 px-4 border-b">{{ $t->NoTelarId }}</td>
                            <td class="py-2 px-4 border-b">{{ $t->Nombre }}</td>
                            <td class="py-2 px-4 border-b">{{ $t->Grupo ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    </div>

{{-- SweetAlert2 --}}
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
/* ========= Constantes / Estado ========= */
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
let $sel = null; // fila seleccionada

/* ========= Utilidades ========= */
const nameFrom = (salon, telar) => {
  const up = String(salon||'').toUpperCase().trim();
  const pref = up.includes('JACQUARD') ? 'JAC' : (up.includes('SMITH') ? 'Smith' : up.slice(0,3).toUpperCase());
  return `${pref} ${telar||''}`.trim();
};
const toast = (icon, title, ms=1400) => Swal.fire({toast:true, position:'top-end', showConfirmButton:false, timer:ms, icon, title});

/* ========= Selección de filas ========= */
function selectRow(tr){
  console.log('selectRow llamado con:', tr);
  document.querySelectorAll('#telares-body tr').forEach(r=>{
    r.classList.remove('bg-blue-500','text-white'); r.classList.add('hover:bg-blue-50');
  });
  tr.classList.remove('hover:bg-blue-50'); tr.classList.add('bg-blue-500','text-white');
  $sel = tr;
  console.log('Fila seleccionada:', $sel, 'UID:', $sel?.dataset?.uid);
            enableButtons();
        }
function deselectRow(tr){
  if (!tr.classList.contains('bg-blue-500')) return;
  tr.classList.remove('bg-blue-500','text-white'); tr.classList.add('hover:bg-blue-50');
  $sel = null; disableButtons();
}

/* ========= Botones (si existen en tu header) ========= */
function enableButtons(){
  console.log('enableButtons llamado');
  const e = document.getElementById('btn-editar');
  const d = document.getElementById('btn-eliminar');
  if (e) { e.disabled=false; e.className='p-2 text-blue-600 hover:text-blue-800 rounded-md transition-colors'; e.onclick = function() { console.log('Botón editar clickeado'); editarTelar(); }; }
  if (d) { d.disabled=false; d.className='p-2 text-red-600 hover:text-red-800 rounded-md transition-colors'; d.onclick = function() { console.log('Botón eliminar clickeado'); eliminarTelar(); }; }
}
function disableButtons(){
  console.log('disableButtons llamado');
  const e = document.getElementById('btn-editar');
  const d = document.getElementById('btn-eliminar');
   if (e) { e.disabled=true; e.className='p-2 text-gray-400 rounded-md transition-colors cursor-not-allowed'; e.onclick = null; }
  if (d) { d.disabled=true; d.className='p-2 text-gray-400 rounded-md transition-colors cursor-not-allowed'; d.onclick = null; }
}

/* ========= Crear ========= */
async function agregarTelar(){
  await Swal.fire({
    title:'Crear Telar',
                html: `
      <div class="grid grid-cols-2 gap-3 text-left text-sm">
        <div>
          <label class="block text-xs font-medium mb-1">Salón *</label>
          <input id="s-salon" class="swal2-input" maxlength="20" placeholder="Jacquard / Smith">
                        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Telar *</label>
          <input id="s-telar" class="swal2-input" maxlength="10" placeholder="200, 300">
                        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium mb-1">Nombre *</label>
          <input id="s-nombre" class="swal2-input" maxlength="30" placeholder="JAC 200">
                        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium mb-1">Grupo</label>
          <input id="s-grupo" class="swal2-input" maxlength="30" placeholder="Prueba / Jacquard Smith">
                    </div>
      </div>`,
    showCancelButton:true,
    confirmButtonText:'Crear',
    confirmButtonColor:'#16a34a',
    didOpen:()=>{
      const salon = document.getElementById('s-salon');
      const telar = document.getElementById('s-telar');
      const nombre= document.getElementById('s-nombre');
      let touched = false;
      const auto = ()=>{ if(!touched && nombre) nombre.value = nameFrom(salon.value, telar.value); };
      if (salon) salon.addEventListener('input', auto);
      if (telar) telar.addEventListener('input', auto);
      if (nombre) nombre.addEventListener('input', ()=> touched = true);
      auto();
    },
    preConfirm: async ()=>{
      const SalonTejidoId = document.getElementById('s-salon').value.trim();
      const NoTelarId     = document.getElementById('s-telar').value.trim();
      let   Nombre        = document.getElementById('s-nombre').value.trim();
      const Grupo         = document.getElementById('s-grupo').value.trim();
      if(!SalonTejidoId || !NoTelarId){ Swal.showValidationMessage('Completa Salón y Telar'); return false; }
      if(!Nombre) Nombre = nameFrom(SalonTejidoId, NoTelarId);

                            Swal.showLoading();
      try{
        const r = await fetch('/planeacion/telares',{
          method:'POST',
          headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
          body: JSON.stringify({SalonTejidoId, NoTelarId, Nombre, Grupo})
        });
        const data = await r.json().catch(()=> ({}));
        if(!r.ok || !data.success){ Swal.showValidationMessage(data?.message || `HTTP ${r.status}`); return false; }
        return true;
      }catch(e){ Swal.showValidationMessage(e.message||'Error de red'); return false; }
    }
  }).then(res=>{ if(res.isConfirmed){ Swal.update({icon:'success',title:'¡Creado!'}); setTimeout(()=>location.reload(),700); }});
}

/* ========= Editar ========= */
async function editarTelar(){
  console.log('editarTelar() llamada, $sel:', $sel);
  if(!$sel){
    console.log('No hay fila seleccionada');
    Swal.fire({icon:'warning', title:'Selecciona un telar'});
                return;
            }
  const salon0 = $sel.dataset.salon, telar0=$sel.dataset.telar, nombre0=$sel.dataset.nombre, grupo0=$sel.dataset.grupo||'', uid=$sel.dataset.uid;
  console.log('Editar telar - uid:', uid, 'salon:', salon0, 'telar:', telar0);
  console.log('Llamando a Swal.fire...');

  await Swal.fire({
    title:'Editar Telar',
                html: `
      <div class="grid grid-cols-2 gap-3 text-left text-sm">
        <div>
          <label class="block text-xs font-medium mb-1">Salón *</label>
          <input id="e-salon" class="swal2-input" maxlength="20" value="${salon0}">
                        </div>
        <div>
          <label class="block text-xs font-medium mb-1">Telar *</label>
          <input id="e-telar" class="swal2-input" maxlength="10" value="${telar0}">
                        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium mb-1">Nombre *</label>
          <input id="e-nombre" class="swal2-input" maxlength="30" value="${nombre0}">
                        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium mb-1">Grupo</label>
          <input id="e-grupo" class="swal2-input" maxlength="30" value="${grupo0}">
                    </div>
      </div>`,
    showCancelButton:true,
    confirmButtonText:'Actualizar',
    confirmButtonColor:'#f59e0b',
    didOpen:()=>{
      console.log('Modal de editar abierto');
      const salon = document.getElementById('e-salon');
      const telar = document.getElementById('e-telar');
      const nombre= document.getElementById('e-nombre');
      let touched = false;
      if (nombre) nombre.addEventListener('input', ()=> touched = true);
      const auto = ()=>{ if(!touched && nombre) nombre.value = nameFrom(salon.value, telar.value); };
      if (salon) salon.addEventListener('input', auto);
      if (telar) telar.addEventListener('input', auto);
    },
    preConfirm: async ()=>{
      const SalonTejidoId = document.getElementById('e-salon').value.trim();
      const NoTelarId     = document.getElementById('e-telar').value.trim();
      let   Nombre        = document.getElementById('e-nombre').value.trim();
      const Grupo         = document.getElementById('e-grupo').value.trim();
      if(!SalonTejidoId || !NoTelarId){ Swal.showValidationMessage('Completa Salón y Telar'); return false; }
      if(!Nombre) Nombre = nameFrom(SalonTejidoId, NoTelarId);

                            Swal.showLoading();
      try{
        const r = await fetch(`/planeacion/telares/${encodeURIComponent(uid)}`,{
          method:'PUT',
          headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
          body: JSON.stringify({SalonTejidoId, NoTelarId, Nombre, Grupo})
        });
        const data = await r.json().catch(()=> ({}));
        if(!r.ok || !data.success){ Swal.showValidationMessage(data?.message || `HTTP ${r.status}`); return false; }
        return {SalonTejidoId, NoTelarId, Nombre, Grupo};
      }catch(e){ Swal.showValidationMessage(e.message||'Error de red'); return false; }
    }
  }).then(res=>{
    if(res.isConfirmed && res.value){
      // refresca la fila seleccionada en UI
      $sel.dataset.salon = res.value.SalonTejidoId;
      $sel.dataset.telar = res.value.NoTelarId;
      $sel.dataset.nombre= res.value.Nombre;
      $sel.dataset.grupo = res.value.Grupo || '';
      $sel.dataset.uid   = `${res.value.SalonTejidoId}_${res.value.NoTelarId}`;
      const tds = $sel.querySelectorAll('td');
      tds[0].textContent = res.value.SalonTejidoId;
      tds[1].textContent = res.value.NoTelarId;
      tds[2].textContent = res.value.Nombre;
      tds[3].textContent = res.value.Grupo || 'N/A';

      Swal.update({icon:'success',title:'¡Actualizado!'});
      setTimeout(()=>location.reload(),700);
                }
            });
        }

/* ========= Eliminar ========= */
async function eliminarTelar(){
  if(!$sel){ Swal.fire({icon:'warning',title:'Selecciona un telar'}); return; }
  const uid = $sel.dataset.uid;
  const salon = $sel.dataset.salon, telar=$sel.dataset.telar, nombre=$sel.dataset.nombre;
  console.log('Eliminar telar - uid:', uid, 'salon:', salon, 'telar:', telar);

  await Swal.fire({
    title:'¿Eliminar Telar?',
    html:`<div class="text-left">
                        <p><strong>Salón:</strong> ${salon}</p>
                        <p><strong>Telar:</strong> ${telar}</p>
                        <p><strong>Nombre:</strong> ${nombre}</p>
          </div>`,
    icon:'warning', showCancelButton:true,
    confirmButtonText:'Sí, eliminar', confirmButtonColor:'#dc2626'
  }).then(async (res)=>{
    if(!res.isConfirmed) return;
                            Swal.showLoading();
    try{
      const r = await fetch(`/planeacion/telares/${encodeURIComponent(uid)}`,{
        method:'DELETE',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
      });
      const data = await r.json().catch(()=> ({}));
      if(!r.ok || !data.success) return Swal.fire({icon:'error', title: data?.message || `HTTP ${r.status}`});
      Swal.fire({icon:'success', title:'¡Eliminado!', timer:900, showConfirmButton:false});
      setTimeout(()=> location.reload(), 950);
    }catch(e){ Swal.fire({icon:'error', title: e.message || 'Error de red'}); }
  });
}

/* ========= Excel ========= */
async function subirExcelTelares(){
  await Swal.fire({
    title:'Subir Excel - Telares',
    html: `<div class="text-left">
            <div class="mb-3">
              <label class="block text-sm font-medium mb-1">Archivo Excel</label>
              <input id="x-file" type="file" accept=".xlsx,.xls" class="swal2-input">
                        </div>
            <div class="text-xs text-gray-600 bg-blue-50 p-2 rounded">Formatos: .xlsx, .xls (máx 10MB)</div>
          </div>`,
    showCancelButton:true,
    confirmButtonText:'Subir', confirmButtonColor:'#16a34a',
    preConfirm: async ()=>{
      const file = document.getElementById('x-file').files[0];
      if(!file){ Swal.showValidationMessage('Selecciona un archivo'); return false; }
                            Swal.showLoading();
      const fd = new FormData(); fd.append('archivo_excel', file); CSRF && fd.append('_token', CSRF);
      try{
        const r = await fetch('/planeacion/telares/excel', { method:'POST', body: fd, headers:{'Accept':'application/json'} });
        const data = await r.json().catch(()=> ({}));
        if(!r.ok || !data.success){ Swal.showValidationMessage(data?.message || `HTTP ${r.status}`); return false; }
        return true;
      }catch(e){ Swal.showValidationMessage(e.message||'Error de red'); return false; }
    }
  }).then(res=>{ if(res.isConfirmed){ Swal.update({icon:'success',title:'¡Procesado!'}); setTimeout(()=>location.reload(),700); }});
}

/* ========= Filtros simples en memoria ========= */
async function filtrarTelares(){
  await Swal.fire({
    title:'Filtrar Telares',
                html: `
      <div class="grid grid-cols-2 gap-3 text-left text-sm">
                        <div>
          <label class="block text-xs font-medium mb-1">Salón</label>
          <input id="f-salon" class="swal2-input" placeholder="Jacquard / Smith">
                        </div>
                        <div>
          <label class="block text-xs font-medium mb-1">Telar</label>
          <input id="f-telar" class="swal2-input" placeholder="200">
                        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium mb-1">Nombre</label>
          <input id="f-nombre" class="swal2-input" placeholder="JAC 200">
                        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium mb-1">Grupo</label>
          <input id="f-grupo" class="swal2-input" placeholder="Prueba">
                        </div>
      </div>`,
    showCancelButton:true,
    confirmButtonText:'Filtrar', confirmButtonColor:'#3b82f6',
  }).then(res=>{
    if(!res.isConfirmed) return;
    const s = (id)=> document.getElementById(id).value.trim().toLowerCase();
    const salon = s('f-salon'), telar=s('f-telar'), nombre=s('f-nombre'), grupo=s('f-grupo');
    const rows = document.querySelectorAll('#telares-body tr');
    let visibles=0;
    rows.forEach(r=>{
      const tds = r.querySelectorAll('td');
      const ok =
        (!salon || tds[0].textContent.toLowerCase().includes(salon)) &&
        (!telar || tds[1].textContent.toLowerCase().includes(telar)) &&
        (!nombre|| tds[2].textContent.toLowerCase().includes(nombre)) &&
        (!grupo || (tds[3].textContent||'').toLowerCase().includes(grupo));
      r.style.display = ok ? '' : 'none';
      if (ok) visibles++;
    });
    toast('success', `Mostrando ${visibles} registro(s)`);
  });
}
function limpiarFiltrosTelares(){
  document.querySelectorAll('#telares-body tr').forEach(r=> r.style.display='');
  toast('success', 'Filtros limpiados');
}

/* ========= Exponer a navbar (si aplica) ========= */
        window.agregarTelares = agregarTelar;
window.editarTelares  = editarTelar;
window.eliminarTelares= eliminarTelar;
window.subirExcelTelares = subirExcelTelares;
window.filtrarTelares = filtrarTelares;
window.limpiarFiltrosTelares = limpiarFiltrosTelares;

/* ========= Init ========= */
document.addEventListener('DOMContentLoaded', ()=>{
  console.log('DOM cargado, inicializando botones...');
  // Verificar que los botones existen antes de deshabilitarlos
  const e = document.getElementById('btn-editar');
  const d = document.getElementById('btn-eliminar');
  if (e && d) {
                disableButtons();
  } else {
    console.log('Botones no encontrados en DOMContentLoaded');
            }
        });
    </script>
@endsection
