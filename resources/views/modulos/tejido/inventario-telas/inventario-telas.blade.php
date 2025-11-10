@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', $tipoInventario === 'jacquard' ? 'Inventario Jacquard' : 'Inventario Itema')

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
                    onclick="irATelar('')"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                >
                    <span class="font-medium">Todos los telares</span>
                </button>
                <div class="border-t border-gray-200 my-1"></div>
                @foreach($telares as $t)
                    <button
                        type="button"
                        onclick="irATelar('{{ $t }}')"
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
<div class="container mx-auto">
    <style>
      /* Franja vertical izquierda (requiere ::before) */
      .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700::before,
      .telar-section > .bg-gray-100::before {
        content: "";
        position: absolute; left: 0; top: 0; bottom: 0; width: 8px; display: block;
      }
      .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700::before {
        background: linear-gradient(to bottom, #2563eb, #1d4ed8);
      }
      .telar-section > .bg-gray-100::before {
        background: linear-gradient(to bottom, #9ca3af, #6b7280);
      }

      /* Columna izquierda fija (transforma el header del componente) */
      .telar-section { position: relative; }
      .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700,
      .telar-section > .bg-gray-100 {
        display:flex !important; justify-content:center;
        position:absolute; left:0; top:0; bottom:0; width:110px; padding:0 !important;
        border-right:1px solid #e5e7eb; background: transparent;
        align-items:flex-start !important; padding-top: 8px !important;
      }
      .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 { background: linear-gradient(to bottom, #2563eb, #1d4ed8) !important; }
      .telar-section > .bg-gray-100 { background: linear-gradient(to bottom, #9ca3af, #6b7280) !important; }

      /* Ocultar partes del header original y subtítulos */
      .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .flex.items-center > .flex.items-center,
      .telar-section > .bg-gray-100 .flex.items-center > .flex.items-center,
      .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 > .absolute,
      .telar-section > .bg-gray-100 > .absolute,
      .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .flex.items-center:first-child,
      .telar-section > .bg-gray-100 .flex.items-center:first-child,
      .telar-section > div > .bg-gray-200 h2,
      .telar-section .col-label.bottom { display: none !important; }

      /* Número del telar: grande, blanco y sin tarjeta */
      .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .bg-red-500,
      .telar-section > .bg-gray-100 .bg-gray-400 {
        background: transparent !important; padding: 0 !important; box-shadow: none !important;
      }
      .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .bg-red-500 .text-xs,
      .telar-section > .bg-gray-100 .bg-gray-400 .text-xs { display: none; }
      .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .bg-red-500 .text-3xl,
      .telar-section > .bg-gray-100 .bg-gray-400 .text-3xl {
        font-weight: 800 !important; color: #ffffff !important; font-size: 2.5rem !important;
        padding: 12px !important; line-height: 1 !important;
      }

      /* Línea separadora en “Siguiente Orden” y “Requerimiento” */
      .telar-section > div > .bg-gray-200 {
        padding: 0 !important; height: 1px; background: #e5e7eb !important;
      }

      /* Calendario ligeramente más grande */
      .telar-section .flex.gap-1.overflow-x-auto.pb-2 {
        transform: scale(1.08); transform-origin: top left;
      }

      /* Etiqueta central “SIG. ORDEN” */
      .telar-section .col-label {
        position: absolute; left: 0; right: 0; color: #fff; font-size: 14px;
        letter-spacing: .08em; text-align: center; pointer-events: none; opacity: .95; font-weight: 700;
      }
      .telar-section .col-label.center { top: 50%; transform: translateY(-50%); }

      /* Checkboxes más grandes y labels alineados */
      .telar-section input[type="checkbox"] { width: 22px; height: 22px; accent-color: #2563eb; }
      .telar-section label { display:inline-flex; align-items:center; gap: 6px; }

      /* Alinear “Cuentas” con el calendario */
      .telar-section .grid.grid-cols-1.md\:grid-cols-2.gap-4 { align-items: stretch !important; }
      .telar-section .grid.grid-cols-1.md\:grid-cols-2.gap-4 > div { height: 100% !important; }
      .telar-section .space-y-2 { display:flex !important; flex-direction:column !important; height:100% !important; justify-content:space-evenly !important; gap:0 !important; }
      .telar-section .space-y-2 > div { display:flex !important; align-items:center !important; gap:8px !important; min-height:32px !important; padding:4px 0 !important; }
      .telar-section .rounded-lg.p-3.border.border-gray-200 { margin-top: 32px !important; }

      /* Dejar espacio para la columna izquierda */
      .telar-section > .p-3,
      .telar-section > div:not([class*='bg-gradient-to-r']):not([class*='bg-gray-100']) { margin-left: 112px; }

      /* Responsive: columna izquierda más angosta en móvil */
      @media (max-width: 640px){
        .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700,
        .telar-section > .bg-gray-100 { width:92px; }
        .telar-section > .p-3,
        .telar-section > div:not([class*='bg-gradient-to-r']):not([class*='bg-gray-100']) { margin-left: 96px; }
      }
    </style>

    @if(count($telares) > 0)
        <x-telares.telar-navbar :telares="$telares" />
    @endif

    @if(count($telares) > 0)
        <div class="space-y-6">
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
                    No hay telares {{ $tipoInventario === 'jacquard' ? 'Jacquard' : 'Itema' }} en proceso
                </h3>
                <p class="text-gray-500 mb-4">
                    Actualmente no hay telares {{ $tipoInventario === 'jacquard' ? 'Jacquard' : 'Itema' }} con producción activa.
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
    if (!btn.contains(e.target) && !menu.contains(e.target)) {
      menu.classList.add('hidden');
      icon.classList.remove('rotate-180');
      icon.classList.add('rotate-0');
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
    // Cerrar dropdown
    const menu = document.getElementById('menuDropdownTelares');
    const icon = document.getElementById('iconDropdown');
    if (menu) menu.classList.add('hidden');
    if (icon) { icon.classList.remove('rotate-180'); icon.classList.add('rotate-0'); }

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

/** Inyectar etiqueta “SIG. ORDEN” en la columna izquierda (no podemos tocar el componente interno) */
(function(){
  function placeLabels(){
    document.querySelectorAll('.telar-section').forEach(section => {
      const col = section.querySelector('.bg-gradient-to-r.from-blue-600.to-blue-700, .bg-gray-100');
      if(!col) return;
      let center = col.querySelector('.col-label.center');
      if(!center){
        center = document.createElement('div');
        center.className = 'col-label center';
        center.textContent = 'SIG. ORDEN';
        col.appendChild(center);
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
