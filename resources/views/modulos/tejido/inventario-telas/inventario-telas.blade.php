@extends('layouts.app', ['ocultarBotones' => true])

@php
    $titulosInventario = [
        'jacquard' => 'Inventario Jacquard',
        'itema' => 'Inventario Itema',
        'karl-mayer' => 'Inventario Karl Mayer',
    ];
    $nombresSalon = [
        'jacquard' => 'Jacquard',
        'itema' => 'Itema',
        'karl-mayer' => 'Karl Mayer',
    ];
@endphp
@section('page-title', $titulosInventario[$tipoInventario] ?? 'Inventario de Telas')

@push('styles')
    @vite('resources/css/tejido/inventario-telas.css')
@endpush

@section('navbar-right')
    @if(count($telares ?? []) > 0)
    <div class="relative">
        <!-- Dropdown de Telares -->
        <button
            type="button"
            id="btnDropdownTelares"
            class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-none"
        >
            <span class="font-medium">Telares</span>
            <i class="fas fa-chevron-down text-sm transition-transform duration-200 ease-out rotate-0" id="iconDropdown"></i>
        </button>

        <!-- Menú Dropdown -->
        <div
            id="menuDropdownTelares"
            class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg border border-gray-200 max-h-96 overflow-y-auto z-50 shadow-none"
        >
            <div class="py-2">
                <button
                    type="button"
                    onclick="event.stopPropagation(); irATelar('');"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                >
                    <span class="font-medium">Todos los telares</span>
                </button>
                <div class="border-t border-gray-200 my-1"></div>
                @foreach(collect($telares)->sortBy(fn($v) => (float) $v)->values() as $t)
                    <button
                        type="button"
                        onclick="event.stopPropagation(); irATelar('{{ $t }}');"
                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                    >
                        Telar <span class="font-semibold">{{ $t }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
    @endif
@endsection

@section('content')
<div class="inventario-telas-page">
    @if(count($telares) > 0)
        <div class="inventario-telas-list">
            @foreach ($telares as $telar)
                @php
                    $telarData = $datosTelaresCompletos[$telar]['telarData'] ?? (object) [
                        'Telar' => $telar,
                        'en_proceso' => false
                    ];
                    $ordenSig = $datosTelaresCompletos[$telar]['ordenSig'] ?? null;
                @endphp

                <div id="telar-{{ $telar }}">
                    <x-telares.telar-section
                        :telar="$telarData"
                        :ordenSig="$ordenSig"
                        :tipo="$tipoInventario"
                        :showRequerimiento="true"
                        :showSiguienteOrden="true"
                    />
                </div>
            @endforeach
        </div>
    @else
        <!-- Estado vacío -->
        <div class="flex flex-col items-center justify-center py-12 px-4">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-gray-100 mb-4">
                    <i class="fas fa-industry text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    No hay telares {{ $nombresSalon[$tipoInventario] ?? '' }} en proceso
                </h3>
                <p class="text-gray-500 mb-4">
                    Actualmente no hay telares {{ $nombresSalon[$tipoInventario] ?? '' }} con producción activa.
                </p>
                <p class="text-sm text-gray-400">
                    Los telares aparecerán aquí cuando tengan órdenes con <span class="font-semibold">EnProceso = 1</span>
                </p>
            </div>
        </div>
    @endif
</div>

@if(count($telares) > 0)
<script>
/** Toggle del dropdown (sin dependencias) */
(function(){
  const btn = document.getElementById('btnDropdownTelares');
  const menu = document.getElementById('menuDropdownTelares');
  const icon = document.getElementById('iconDropdown');

  btn?.addEventListener('click', (e) => {
    e.stopPropagation();
    const hidden = menu.classList.contains('hidden');
    menu.classList.toggle('hidden', !hidden);
    icon.classList.toggle('rotate-180', hidden);
    icon.classList.toggle('rotate-0', !hidden);
  });

  document.addEventListener('click', (e) => {
    // Solo cerrar si el click fue fuera del botón y del menú
    // Y si el menú no está oculto (para evitar cerrar múltiples veces)
    if (menu && !menu.classList.contains('hidden')) {
      if (!btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.add('hidden');
        icon.classList.remove('rotate-180');
        icon.classList.add('rotate-0');
      }
    }
  });

  // Auto-enfocar por ?telar=### o #telar-###
  window.addEventListener('DOMContentLoaded', function(){
    const url = new URL(location.href);
    let t = url.searchParams.get('telar');
    if(!t && location.hash.startsWith('#telar-')) t = location.hash.replace('#telar-','');
    if (t) setTimeout(() => irATelar(t), 500);
  });
})();

/** Scroll suave al telar y actualización de URL */
(function(){
  function getScrollable(node){
    let n = node ? node.parentElement : null;
    while (n && n !== document.body) {
      const cs = getComputedStyle(n);
      if ((cs.overflowY === 'auto' || cs.overflowY === 'scroll') && n.scrollHeight > n.clientHeight) return n;
      n = n.parentElement;
    }
    return document.scrollingElement || document.documentElement;
  }

  window.irATelar = function(noTelar){
    // Cerrar dropdown después de un pequeño delay para permitir que el click se procese
    setTimeout(() => {
      const menu = document.getElementById('menuDropdownTelares');
      const icon = document.getElementById('iconDropdown');
      if (menu) menu.classList.add('hidden');
      if (icon) { icon.classList.remove('rotate-180'); icon.classList.add('rotate-0'); }
    }, 100);

    // Mostrar todo (por si había filtro previo)
    document.querySelectorAll('[id^="telar-"]').forEach(el => el.classList.remove('hidden'));

    if (!noTelar) {
      const u0 = new URL(location.href); u0.searchParams.delete('telar'); u0.hash = '';
      history.replaceState(null,'',u0.toString());
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    const el = document.getElementById('telar-'+noTelar);
    if (!el) return;

    const sticky = document.querySelector('nav.sticky, nav.fixed, .sticky.top-0');
    const stickyH = sticky ? sticky.getBoundingClientRect().height : 0;
    const extra = 50; // aire superior

    const scroller = getScrollable(el);
    const scRect = scroller.getBoundingClientRect ? scroller.getBoundingClientRect() : { top: 0 };
    const tRect = el.getBoundingClientRect();
    const current = scroller.scrollTop || window.pageYOffset || document.documentElement.scrollTop || 0;
    const targetTop = tRect.top - scRect.top + current - stickyH - extra;

    if (scroller.scrollTo) scroller.scrollTo({ top: Math.max(0, targetTop), behavior:'smooth' });
    else window.scrollTo({ top: Math.max(0, targetTop), behavior:'smooth' });

    // Actualizar URL con query y hash para compatibilidad
    const url = new URL(location.href);
    url.searchParams.set('telar', noTelar);
    url.hash = 'telar-'+noTelar;
    history.replaceState(null,'',url.toString());
  }
})();

/** Inyectar etiqueta "SIG. ORDEN" y número del telar en la columna izquierda (no podemos tocar el componente interno) */
(function(){
  function placeLabels(){
    document.querySelectorAll('.telar-section').forEach(section => {
      const col = section.querySelector(':scope > .inv-telas-rail');
      if(!col) return;

      // Buscar o crear la etiqueta "SIG. ORDEN"
      let center = col.querySelector('.col-label.center');
      if(!center){
        center = document.createElement('div');
        center.className = 'col-label center mt-4';
        center.textContent = 'SIG. ORDEN';
        col.appendChild(center);
      }

      // Buscar o crear el número del telar debajo de "SIG. ORDEN"
      let telarNumber = col.querySelector('.telar-number-label');
      if(!telarNumber){
        // Buscar el número del telar en el header (elemento con text-4xl font-bold)
        let numeroTelar = null;
        const header = section.querySelector(':scope > .inv-telas-rail');
        if(header){
          const numeroElement = header.querySelector('.text-4xl.font-bold');
          if(numeroElement && numeroElement.textContent.trim()){
            numeroTelar = numeroElement.textContent.trim();
          }
        }

        // Si no se encuentra, intentar desde el ID de la sección padre
        if(!numeroTelar){
          const sectionParent = section.closest('[id^="telar-"]');
          if(sectionParent){
            const idMatch = sectionParent.id.match(/telar-(\d+)/);
            if(idMatch){
              numeroTelar = idMatch[1];
            }
          }
        }

        if(numeroTelar){
          telarNumber = document.createElement('div');
          telarNumber.className = 'telar-number-label';
          telarNumber.textContent = numeroTelar;
          col.appendChild(telarNumber);
        }
      }
    });
  }
  window.addEventListener('load', placeLabels);
  document.addEventListener('visibilitychange', () => { if(!document.hidden) placeLabels(); });
  setTimeout(placeLabels, 300);
})();
</script>
@endif
@endsection
