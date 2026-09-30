{{-- Modal líneas --}}
@include('components.programa-tejido.req-programa-tejido-line-table')

{{-- Modal Actualizar Calendarios --}}
@include('modulos.programa-tejido.modal.act-calendarios')

{{-- Modal Crear Repaso --}}
@include('modulos.programa-tejido.modal.repaso')

{{-- Modal Editar Marbetes --}}
@include('modulos.programa-tejido.modal.marbetes')

@unless($isMuestras ?? false)
  {{-- Modal Redbooth: primer corte exclusivamente visual --}}
  @include('modulos.programa-tejido.modal.redbooth')
@endunless

{{-- Permisos del módulo de la superficie para el menú contextual (Muestras = idrol 5). --}}
@php
  $moduloPT = ($superficie ?? \App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface::actual())->moduloPermiso();
  $canCrear = function_exists('userCan') ? userCan('crear', $moduloPT) : true;
  $canModificar = function_exists('userCan') ? userCan('modificar', $moduloPT) : true;
  $canEliminar = function_exists('userCan') ? userCan('eliminar', $moduloPT) : true;
@endphp

{{-- Menú contextual --}}
<div id="contextMenu" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg z-50 py-1 min-w-[180px]">
  @if($canCrear)
  <button id="contextMenuCrear" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-plus-circle text-blue-500"></i>
    <span>Crear</span>
  </button>
  <button id="contextMenuRepaso" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-redo text-blue-500"></i>
    <span>Crear Repaso</span>
  </button>
  @endif
  @if($canModificar)
  <button id="contextMenuEditar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-pen text-yellow-500"></i>
    <span>Editar fila</span>
  </button>
  <button id="contextMenuMarbetes" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-tags text-orange-500"></i>
    <span>Editar marbetes</span>
  </button>
  @endif
  @if($canEliminar)
  <button id="contextMenuEliminar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 flex items-center gap-2">
    <i class="fas fa-trash text-red-500"></i>
    <span>Eliminar</span>
  </button>
  <button id="contextMenuEliminarEnProceso" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 flex items-center gap-2">
    <i class="fas fa-stop-circle text-red-700"></i>
    <span>Eliminar en proceso</span>
  </button>
  @endif
  @if($canModificar)
  <button id="contextMenuDesvincular" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700 flex items-center gap-2">
    <i class="fas fa-unlink text-purple-500"></i>
    <span>Desvincular</span>
  </button>
  @endif
  {{-- redirigir a catalogo de codificacion --}}
  <button id="contextMenuCodificacion" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-code text-green-500"></i>
    <span>Codificación</span>
  </button>
  {{-- redirigir a catalogo de codificacion de modelos --}}
  <button id="contextMenuModelos" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-code text-green-500"></i>
    <span>Modelos</span>
  </button>
  @unless($isMuestras ?? false)
  <button id="contextMenuRedbooth" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-comments text-red-500"></i>
    <span>Redbooth</span>
  </button>
  @endunless

</div>

{{-- Menú contextual para encabezados de columnas --}}
<div id="contextMenuHeader" class="hidden fixed bg-white border border-gray-300 rounded-lg shadow-lg z-50 py-1 min-w-[180px]">
  <button id="contextMenuHeaderFiltrar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 flex items-center gap-2">
    <i class="fas fa-filter text-yellow-500"></i>
    <span>Filtrar</span>
  </button>
  <button id="contextMenuHeaderFijar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-700 flex items-center gap-2">
    <i class="fas fa-thumbtack text-blue-500"></i>
    <span>Fijar</span>
  </button>
  <button id="contextMenuHeaderOcultar" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 flex items-center gap-2">
    <i class="fas fa-eye-slash text-red-500"></i>
    <span>Ocultar</span>
  </button>
</div>

{{-- OJO: EL JS de duplicar/dividir NO VA AQUÍ (si lo incluyes aquí se imprime) --}}

{{-- ?v=filemtime obligatorio: .htaccess le pone un ano de expiracion al CSS y
     estos dos no pasan por Vite, asi que sin esto el navegador sigue sirviendo el
     de antes. Al mover las utilidades de la celda (px-3 py-2 text-sm) del HTML a
     #mainTable tbody td, un main.css cacheado deja la tabla sin tamano ni padding
     y la pagina se ve "con zoom". --}}
<link rel="stylesheet" href="{{ asset('css/programa-tejido/main.css') }}?v={{ filemtime(public_path('css/programa-tejido/main.css')) }}">
<link rel="stylesheet" href="{{ asset('css/programa-tejido/modals.css') }}?v={{ filemtime(public_path('css/programa-tejido/modals.css')) }}">
