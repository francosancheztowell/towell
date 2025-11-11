@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Consultar Marcas Finales')

@section('navbar-right')
@php
    $permisosMarcas = userPermissions('Marcas Finales') ?? userPermissions('Nuevas Marcas Finales');
    $puedeCrear      = (bool)($permisosMarcas->crear     ?? false);
    $puedeModificar  = (bool)($permisosMarcas->modificar ?? false);
    $puedeEliminar   = (bool)($permisosMarcas->eliminar  ?? false);
@endphp

<div class="flex items-center gap-2">
    @if($puedeCrear)
        <a href="{{ route('marcas.nuevo') }}" id="btn-nuevo" class="p-2 rounded-lg transition hover:bg-blue-100" title="Nuevo">
            <i class="fas fa-plus text-blue-600 text-lg"></i>
        </a>
    @else
        <button id="btn-nuevo" disabled class="p-2 rounded-lg cursor-not-allowed opacity-50" title="Nuevo (Sin permisos)">
            <i class="fas fa-lock text-gray-400 text-lg"></i>
        </button>
    @endif

    <button id="btn-editar-global"
            onclick="editarMarcaSeleccionada()"
            class="p-2 rounded-lg transition hover:bg-yellow-100 disabled:opacity-50 disabled:cursor-not-allowed"
            {{ $puedeModificar ? '' : 'disabled' }}
            title="Editar">
        <i class="fas fa-edit text-yellow-500 text-lg"></i>
    </button>

    <button id="btn-finalizar-global"
            onclick="finalizarMarcaSeleccionada()"
            class="p-2 rounded-lg transition hover:bg-green-100 disabled:opacity-50 disabled:cursor-not-allowed"
            {{ ($puedeModificar || $puedeEliminar) ? '' : 'disabled' }}
            title="Finalizar">
        <i class="fas fa-check text-green-500 text-lg"></i>
    </button>
</div>
@endsection

@section('content')
<!-- CONTENEDOR A TODO LO ANCHO Y ALTO (OPTIMIZADO PARA TABLET) -->
<div class="w-full ">
  <!-- Dos paneles en columna, cada uno con altura fija para que quepan en pantalla -->
  <div class="flex flex-col gap-3 w-full"
       style="max-height: calc(100vh - 140px);">

    @if(isset($marcas) && $marcas->count() > 0)
      <!-- PANEL SUPERIOR: LISTA DE FOLIOS -->
      <div class="bg-white rounded-md shadow-sm overflow-hidden w-full flex-shrink-0">
        <div class="overflow-auto" style="max-height: calc((100vh - 200px) / 2);">
          <table class="w-full table-fixed text-xs">
            <thead class="bg-blue-600 text-white sticky top-0 z-10">
              <tr>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-24">Folio</th>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-28">Fecha</th>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-24">Turno</th>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-32">Empleado</th>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-28">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              @foreach($marcas as $marca)
              <tr class="hover:bg-blue-50 cursor-pointer {{ isset($ultimoFolio) && $ultimoFolio->Folio == $marca->Folio ? 'fila-seleccionada' : '' }}"
                  data-folio="{{ $marca->Folio }}"
                  onclick="seleccionarMarca('{{ $marca->Folio }}', this)">
                <td class="px-2 py-2 font-semibold text-gray-900 truncate">{{ $marca->Folio }}</td>
                <td class="px-2 py-2 text-gray-900 truncate">{{ \Carbon\Carbon::parse($marca->Date)->format('d/m/Y') }}</td>
                <td class="px-2 py-2 text-gray-900 truncate">T{{ $marca->Turno }}</td>
                <td class="px-2 py-2 text-gray-900 truncate">{{ $marca->numero_empleado ?? 'N/A' }}</td>
                <td class="px-2 py-2">
                  @if($marca->Status === 'Finalizado')
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-700">Finalizado</span>
                  @elseif($marca->Status === 'En Proceso')
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-700">En Proceso</span>
                  @else
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-yellow-100 text-yellow-700">{{ $marca->Status }}</span>
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      <!-- PANEL INFERIOR: PREVIEW / DETALLE -->
      <div id="preview-panel" class="bg-white rounded-md shadow-sm overflow-hidden w-full hidden flex-shrink-0">
        <!-- Header compacto -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-3 py-1.5 border-b border-blue-700 flex-shrink-0">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0 flex-1">
              <div class="flex items-center gap-2 min-w-0">
                <i class="fas fa-file-alt text-white text-xs"></i>
                <span id="preview-folio" class="text-xs font-bold text-white truncate">-</span>
              </div>
              <span class="text-white/80 text-[10px] hidden sm:inline">·</span>
              <span id="preview-meta" class="text-[10px] text-white/90 truncate hidden sm:inline">-</span>
            </div>
            <span id="preview-status" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-white/20 text-white border border-white/30 whitespace-nowrap">-</span>
          </div>
        </div>

        <div class="overflow-auto" style="max-height: calc((100vh - 200px) / 2);">
          <table class="w-full table-fixed text-xs">
            <thead class="bg-blue-600 text-white sticky top-0 z-10">
              <tr>
                <th class="px-2 py-2 text-left uppercase text-[11px] w-20">Telar</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-24">Efic. STD</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-20">Marcas</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-20">Trama</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-20">Pie</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-20">Rizo</th>
                <th class="px-2 py-2 text-center uppercase text-[11px] w-20">Otros</th>
              </tr>
            </thead>
            <tbody id="preview-lineas" class="divide-y divide-gray-100"></tbody>
          </table>
        </div>
      </div>

    @else
      <!-- SIN REGISTROS -->
      <div class="bg-white rounded-md shadow-sm p-8 text-center w-full">
        <h3 class="text-lg font-semibold text-gray-700 mb-2">No hay marcas registradas</h3>
        <p class="text-gray-500">Toca “Nueva Marca” para crear el primer registro.</p>
        @if($puedeCrear)
        <a href="{{ route('marcas.nuevo') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          <i class="fas fa-plus mr-2"></i> Nueva Marca
        </a>
        @endif
      </div>
    @endif

  </div>
</div>

<style>
  /* Ocupa todo el ancho del layout */
  section.content{ width:100%!important; max-width:100%!important }

  /* Selección */
  .fila-seleccionada{ background:#e6f0ff!important; border-left:4px solid #3b82f6 }

  /* Scrollbar discreto (opcional) */
  table{ border-collapse:separate; border-spacing:0 }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let marcaSeleccionada = null;
let statusSeleccionado = null;

function seleccionarMarca(folio, row){
  // Resalta fila
  document.querySelectorAll('tbody tr').forEach(tr => tr.classList.remove('fila-seleccionada'));
  row.classList.add('fila-seleccionada');

  marcaSeleccionada = folio;

  const controller = new AbortController();
  const t = setTimeout(()=>controller.abort(), 10000);

  fetch(`/modulo-marcas/${folio}`, { headers:{'Accept':'application/json'}, signal:controller.signal })
    .then(r => { clearTimeout(t); if(!r.ok) throw new Error(r.statusText); return r.json(); })
    .then(data => {
      if(!data.success) return;

      statusSeleccionado = data.marca.Status;
      configurarBotonesSegunStatus(statusSeleccionado);
      mostrarDetalles(data.marca, data.lineas || data.marca.marcas_line || []);
    })
    .catch(err => {
      Swal.fire({ icon:'warning', title:'Error', text:'No se pudieron cargar los detalles.' });
      console.error(err);
    });
}

function configurarBotonesSegunStatus(status){
  const btnNuevo = document.getElementById('btn-nuevo');
  const btnEdit  = document.getElementById('btn-editar-global');
  const btnEnd   = document.getElementById('btn-finalizar-global');

  if(status === 'Finalizado'){
    if(btnNuevo && btnNuevo.tagName==='BUTTON'){ btnNuevo.disabled = true; btnNuevo.classList.add('opacity-50','cursor-not-allowed'); }
    btnEdit.disabled = true; btnEnd.disabled = true;
  }else if(status === 'En Proceso'){
    if(btnNuevo && btnNuevo.tagName==='BUTTON'){ btnNuevo.disabled = true; btnNuevo.classList.add('opacity-50','cursor-not-allowed'); }
    btnEdit.disabled = false; btnEnd.disabled = false;
  }else{
    if(btnNuevo && btnNuevo.tagName==='BUTTON'){ btnNuevo.disabled = false; btnNuevo.classList.remove('opacity-50','cursor-not-allowed'); }
    btnEdit.disabled = false; btnEnd.disabled = false;
  }
}

 function mostrarDetalles(marca, lineas){
   const panel = document.getElementById('preview-panel');
   panel.classList.remove('hidden');

   document.getElementById('preview-folio').textContent = marca.Folio ?? '-';
   const fecha = marca.Date ? new Date(marca.Date).toLocaleDateString('es-MX', {day:'2-digit', month:'2-digit'}) : '-';
   document.getElementById('preview-meta').textContent = `${fecha} · T${marca.Turno ?? '-'} · ${marca.numero_empleado ?? 'N/A'}`;

   const statusEl = document.getElementById('preview-status');
   const status = marca.Status ?? '-';
   statusEl.textContent = status;
   // Actualizar colores del badge según status
   statusEl.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-white/30 whitespace-nowrap';
   if(status === 'Finalizado') {
     statusEl.classList.add('bg-green-500/30', 'text-white');
   } else if(status === 'En Proceso') {
     statusEl.classList.add('bg-yellow-500/30', 'text-white');
   } else {
     statusEl.classList.add('bg-white/20', 'text-white');
   }

  const tbody = document.getElementById('preview-lineas');
  tbody.innerHTML = '';

  if(!lineas || !lineas.length){
    tbody.innerHTML = `<tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Sin líneas capturadas</td></tr>`;
    return;
  }

  lineas.forEach((l, i) => {
    const tr = document.createElement('tr');
    tr.className = i%2===0 ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 hover:bg-gray-100';

    const eVal = l.Eficiencia ?? l.EficienciaSTD ?? l.EficienciaStd ?? null;
    const ef   = eVal !== null ? (isNaN(eVal) ? eVal : (Number(eVal)*100).toFixed(0)+'%') : '-';

    tr.innerHTML = `
      <td class="px-2 py-2 font-semibold text-gray-900 truncate">${l.NoTelarId ?? '-'}</td>
      <td class="px-2 py-2 text-center">${ef}</td>
      <td class="px-2 py-2 text-center">${l.Marcas ?? '-'}</td>
      <td class="px-2 py-2 text-center">${l.Trama ?? '-'}</td>
      <td class="px-2 py-2 text-center">${l.Pie ?? '-'}</td>
      <td class="px-2 py-2 text-center">${l.Rizo ?? '-'}</td>
      <td class="px-2 py-2 text-center">${l.Otros ?? '-'}</td>`;
    tbody.appendChild(tr);
  });
}

function editarMarcaSeleccionada(){
  if(!marcaSeleccionada) return Swal.fire('Sin selección','Selecciona un folio para editar','warning');
  window.location.href = `{{ url('/modulo-marcas') }}?folio=${marcaSeleccionada}`;
}

function finalizarMarcaSeleccionada(){
  if(!marcaSeleccionada) return Swal.fire('Sin selección','Selecciona un folio para finalizar','warning');

  Swal.fire({
    title:'Finalizar Marca', text:`¿Deseas finalizar el folio ${marcaSeleccionada}?`,
    icon:'warning', showCancelButton:true, confirmButtonText:'Sí, finalizar', cancelButtonText:'Cancelar'
  }).then(r=>{
    if(!r.isConfirmed) return;
    Swal.fire({ title:'Finalizando...', didOpen:()=>Swal.showLoading(), allowOutsideClick:false });
    fetch(`/modulo-marcas/${marcaSeleccionada}/finalizar`,{
      method:'POST',
      headers:{
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Accept':'application/json','Content-Type':'application/json'
      }
    })
    .then(r=>r.json())
    .then(d=>{
      if(d.success){ Swal.fire('Finalizado', d.message || 'Marca finalizada','success').then(()=>location.reload()); }
      else         { Swal.fire('Error', d.message || 'No se pudo finalizar','error'); }
    })
    .catch(err=> Swal.fire('Error', err.message || 'Error de conexión','error'));
  });
}

document.addEventListener('DOMContentLoaded', ()=>{
  // Estado inicial (sin selección)
  const btnEdit = document.getElementById('btn-editar-global');
  const btnEnd  = document.getElementById('btn-finalizar-global');
  if(btnEdit) btnEdit.disabled = true;
  if(btnEnd)  btnEnd.disabled  = true;

  // Autoseleccionar último folio si existe
  @if(isset($ultimoFolio))
    window.addEventListener('load', ()=>{
      const folio = '{{ $ultimoFolio->Folio }}';
      const tr = document.querySelector(`tr[data-folio="${folio}"]`);
      if(tr){ try{ seleccionarMarca(folio, tr); }catch(e){ console.error(e); } }
    });
  @endif
});
</script>
@endsection
