@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Inventario Jacquard')

@section('navbar-right')
    @if(count($telaresJacquard ?? []) > 0)
    <div class="relative">
        <!-- Dropdown de Telares -->
        <button type="button" id="btnDropdownTelares" 
                class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
            <span class="font-medium">Telares</span>
            <svg class="w-4 h-4 transition-transform" id="iconDropdown" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>

        <!-- Menu Dropdown -->
        <div id="menuDropdownTelares" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 max-h-96 overflow-y-auto z-50">
            <div class="py-2">
                <button type="button" onclick="irATelar('')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">
                    <span class="font-medium">Todos los telares</span>
                </button>
                <div class="border-t border-gray-200 my-1"></div>
                @foreach($telaresJacquard as $t)
                    <button type="button" onclick="irATelar('{{ $t }}')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors">
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
          /* Encabezado compacto con franja vertical y solo No. de Telar */
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700,
          .telar-section > .bg-gray-100 {
            position: relative;
            background: #fff !important;
          }
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700::before,
          .telar-section > .bg-gray-100::before {
            content: "";
            position: absolute; left: 0; top: 0; bottom: 0; width: 8px;
            display: block;
          }
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700::before {
            background: linear-gradient(to bottom, #2563eb, #1d4ed8);
          }
          .telar-section > .bg-gray-100::before {
            background: linear-gradient(to bottom, #9ca3af, #6b7280);
          }
          /* Ocultar título JACQUARD SULZER | TEJIDO */
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .flex.items-center > .flex.items-center,
          .telar-section > .bg-gray-100 .flex.items-center > .flex.items-center {
            display: none;
          }
          /* Convertir badge en número simple */
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .bg-red-500,
          .telar-section > .bg-gray-100 .bg-gray-400 {
            background: transparent !important;
            padding: 0 !important;
            box-shadow: none !important;
          }
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .bg-red-500 .text-xs,
          .telar-section > .bg-gray-100 .bg-gray-400 .text-xs {
            display: none;
          }
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .bg-red-500 .text-3xl,
          .telar-section > .bg-gray-100 .bg-gray-400 .text-3xl {
            font-weight: 800 !important;
          }
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .bg-red-500 .text-3xl { color: #1d4ed8; }
          .telar-section > .bg-gray-100 .bg-gray-400 .text-3xl { color: #374151; }
        </style>
        <!-- Ajustes adicionales: etiquetas en columna y calendario más grande -->
        <style>
          /* Columna izquierda: alinear arriba y pequeño padding superior */
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700,
          .telar-section > .bg-gray-100 {
            align-items: flex-start !important;
            padding-top: 8px !important;
          }

          /* Reemplazar título de Siguiente Orden por una línea fina */
          .telar-section > div > .bg-gray-200 h2 { display: none !important; }
          .telar-section > div > .bg-gray-200 { padding: 0 !important; height: 1px; background: #e5e7eb !important; }

          /* Ampliar ligeramente los calendarios */
          .telar-section .flex.gap-1.overflow-x-auto.pb-2 { transform: scale(1.08); transform-origin: top left; }

          /* Etiquetas de columna (posicionadas con JS) */
          .telar-section .col-label {
            position: absolute;
            left: 0; right: 0; /* ocupar ancho de la columna */
            color: #fff;
            font-size: 14px; /* Texto más grande para SIG. ORDEN */
            letter-spacing: .08em;
            text-align: center;
            pointer-events: none;
            opacity: .95;
            font-weight: 700; /* Más bold */
          }
          .telar-section .col-label.center { top: 50%; transform: translateY(-50%); }
          .telar-section .col-label.bottom { display: none !important; } /* Ocultar REQUERIMIENTO */
        </style>
        <!-- Checkboxes más grandes (solo Jacquard) -->
        <style>
          .telar-section input[type="checkbox"]{
            width: 22px;
            height: 22px;
            accent-color: #2563eb; /* color del check */
          }
          /* Mejora de accesibilidad del click target si hay labels adyacentes */
          .telar-section label{ display:inline-flex; align-items:center; gap: 6px; }
          
          /* Alinear sección de cuentas (Rizo, Pie, etc.) con el calendario */
          .telar-section .grid.grid-cols-1.md\\:grid-cols-2.gap-4 {
            align-items: stretch !important; /* mismo alto para ambas columnas */
          }
          
          /* Hacer que la sección de cuentas tenga el mismo alto que los calendarios */
          .telar-section .grid.grid-cols-1.md\\:grid-cols-2.gap-4 > div {
            height: 100% !important;
          }
          
          /* Contenedor de cuentas con altura completa y distribución uniforme */
          .telar-section .space-y-2 {
            display: flex !important;
            flex-direction: column !important;
            height: 100% !important;
            justify-content: space-evenly !important; /* distribución uniforme */
            gap: 0 !important; /* eliminar gap para usar justify-content */
          }
          
          /* Elementos de Rizo y Pie alineados como los checkboxes del calendario */
          .telar-section .space-y-2 > div {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            min-height: 32px !important; /* altura mínima similar a las filas del calendario */
            padding: 4px 0 !important; /* padding vertical similar */
          }
          
          /* Margin-top para la caja de "Cuentas: RIZO / PIE" que está al lado de los calendarios */
          .telar-section .rounded-lg.p-3.border.border-gray-200 {
            margin-top: 32px !important; /* Alinear con la altura del calendario */
          }
        </style>
        <!-- Overrides para colocar número arriba, etiquetas en columna y línea separadora -->
        <style>
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700,
          .telar-section > .bg-gray-100{
            align-items:flex-start !important; padding:8px 0 0 0 !important;
          }
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .text-center,
          .telar-section > .bg-gray-100 .text-center{ text-align:center; }
          /* ELIMINAR el texto pegado al número */
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .text-center::after,
          .telar-section > .bg-gray-100 .text-center::after{
            display: none !important;
          }
          /* Línea fina en lugar del subtítulo de Siguiente Orden */
          .telar-section > div > .bg-gray-200 h2{ display:none !important; }
          .telar-section > div > .bg-gray-200{ padding:0 !important; height:1px; background:#e5e7eb !important; }
        </style>
        <!-- Forzar color blanco para el número de telar -->
        <style>
          /* Número dentro del header del componente (activo e inactivo) - MÁS GRANDE Y CON PADDING */
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .bg-red-500 .text-3xl,
          .telar-section > .bg-gray-100 .bg-gray-400 .text-3xl { 
            color: #ffffff !important; 
            font-size: 2.5rem !important; /* más grande que text-3xl */
            padding: 12px !important; /* padding alrededor del número */
            line-height: 1 !important;
          }
          /* Número en la columna izquierda (si se usa telar-aside) */
          .telar-aside .num { color: #ffffff !important; }
        </style>
        <style>
          /* Transformar el header del componente en una columna izquierda fija */
          .telar-section{ position: relative; }
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700,
          .telar-section > .bg-gray-100{
            display:flex !important; align-items:center; justify-content:center;
            position:absolute; left:0; top:0; bottom:0; width:110px; padding:0 !important;
            border-right:1px solid #e5e7eb;
            background: transparent; /* será cubierto por gradientes abajo */
          }
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700{ background: linear-gradient(to bottom, #2563eb, #1d4ed8) !important; }
          .telar-section > .bg-gray-100{ background: linear-gradient(to bottom, #9ca3af, #6b7280) !important; }
          /* Ocultar barra separadora superior del header original */
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 > .absolute,
          .telar-section > .bg-gray-100 > .absolute{ display:none !important; }
          /* Ocultar grupo de títulos (JACQUARD SULZER | TEJIDO) */
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .flex.items-center:first-child,
          .telar-section > .bg-gray-100 .flex.items-center:first-child{ display:none !important; }
          /* Restyle del contenedor con el número del telar dentro del header */
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .text-center,
          .telar-section > .bg-gray-100 .text-center{
            color:#fff; text-shadow: 0 1px 1px rgba(0,0,0,.2);
          }
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .text-center .text-xs,
          .telar-section > .bg-gray-100 .text-center .text-xs{ opacity:.9; letter-spacing:.08em; }
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .text-center .text-3xl,
          .telar-section > .bg-gray-100 .text-center .text-3xl{ font-weight:800; }
          /* Desactivar “tarjeta” del badge original */
          .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700 .bg-red-500,
          .telar-section > .bg-gray-100 .bg-gray-400{ background:transparent !important; box-shadow:none !important; padding:0 !important; }
          /* Desplazar el contenido para dejar espacio a la columna izquierda */
          .telar-section > .p-3,
          .telar-section > div:not([class*='bg-gradient-to-r']):not([class*='bg-gray-100']){ margin-left: 112px; }
          @media (max-width: 640px){
            .telar-section > .bg-gradient-to-r.from-blue-600.to-blue-700,
            .telar-section > .bg-gray-100{ width:92px; }
            .telar-section > .p-3,
            .telar-section > div:not([class*='bg-gradient-to-r']):not([class*='bg-gray-100']){ margin-left: 96px; }
          }
        </style>
        <!-- Navbar de telares usando componente (ordenados por secuencia) -->
        @if(count($telaresJacquard) > 0)
            <x-telar-navbar :telares="$telaresJacquard" />
        @endif

        <!-- Vista completa de todos los telares Jacquard con datos reales -->
        @if(count($telaresJacquard) > 0)
            <div class="space-y-6">
                @foreach ($telaresJacquard as $telar)
                    @php
                        // Obtener datos reales desde el controlador
                        $telarData = $datosTelaresCompletos[$telar]['telarData'] ?? (object) [
                            'Telar' => $telar,
                            'en_proceso' => false
                        ];

                        $ordenSig = $datosTelaresCompletos[$telar]['ordenSig'] ?? null;
                    @endphp

                    <!-- Usar componente de sección de telar con datos reales -->
                    <div id="telar-{{ $telar }}">
                        <x-telar-section
                            :telar="$telarData"
                            :ordenSig="$ordenSig"
                            tipo="jacquard"
                            :showRequerimiento="true"
                            :showSiguienteOrden="true"
                        />
                    </div>
                @endforeach
            </div>
        @else
            <!-- Mensaje cuando no hay datos -->
            <div class="flex flex-col items-center justify-center py-12 px-4">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-gray-100 mb-4">
                        <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709M15 6.291A7.962 7.962 0 0012 5c-2.34 0-4.29 1.009-5.824 2.709M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No hay telares Jacquard en proceso</h3>
                    <p class="text-gray-500 mb-4">
                        Actualmente no hay telares Jacquard con producción activa.
                    </p>
                    <p class="text-sm text-gray-400">
                        Los telares aparecerán aquí cuando tengan órdenes con <span class="font-semibold">EnProceso = 1</span>
                    </p>
                </div>
            </div>
        @endif
    </div>

    <!-- JavaScript para Dropdown y Navegación -->
    @if(count($telaresJacquard) > 0)
    <script>
    // Función global para ir a un telar
    function irATelar(noTelar) {
        console.log('Navegando al telar:', noTelar);
        
        // Cerrar dropdown
        const menu = document.getElementById('menuDropdownTelares');
        const icon = document.getElementById('iconDropdown');
        if (menu) menu.classList.add('hidden');
        if (icon) icon.style.transform = 'rotate(0deg)';

        // Mostrar todos los telares
        document.querySelectorAll('[id^="telar-"]').forEach(el => {
            el.classList.remove('hidden');
        });

        // Si se seleccionó un telar específico, ir a él
        if (noTelar && noTelar !== '') {
            // Usar setTimeout para asegurar que el DOM esté listo
            setTimeout(() => {
                const el = document.getElementById('telar-' + noTelar);
                console.log('Elemento encontrado:', el);
                
                if (el) {
                    // Scroll con offset para que aparezca completo (150px arriba del elemento)
                    const offset = 150;
                    const elementPosition = el.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - offset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    // Actualizar URL
                    const url = new URL(location.href);
                    url.searchParams.set('telar', noTelar);
                    history.replaceState(null, '', url.toString());
                } else {
                    console.error('No se encontró el elemento telar-' + noTelar);
                }
            }, 100);
        } else {
            // Si no hay telar, ir al inicio
            window.scrollTo({ top: 0, behavior: 'smooth' });
            const url = new URL(location.href);
            url.searchParams.delete('telar');
            history.replaceState(null, '', url.toString());
        }
    }

    // Toggle del dropdown
    (function(){
        const btnDropdown = document.getElementById('btnDropdownTelares');
        const menuDropdown = document.getElementById('menuDropdownTelares');
        const iconDropdown = document.getElementById('iconDropdown');

        btnDropdown?.addEventListener('click', function(e) {
            e.stopPropagation();
            const isHidden = menuDropdown.classList.contains('hidden');
            
            if (isHidden) {
                menuDropdown.classList.remove('hidden');
                iconDropdown.style.transform = 'rotate(180deg)';
            } else {
                menuDropdown.classList.add('hidden');
                iconDropdown.style.transform = 'rotate(0deg)';
            }
        });

        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!btnDropdown.contains(e.target) && !menuDropdown.contains(e.target)) {
                menuDropdown.classList.add('hidden');
                iconDropdown.style.transform = 'rotate(0deg)';
            }
        });

        // Auto-enfocar si viene ?telar=### o hash #telar-###
        window.addEventListener('DOMContentLoaded', function(){
            const url = new URL(location.href);
            let t = url.searchParams.get('telar');
            if(!t && location.hash.startsWith('#telar-')){
                t = location.hash.replace('#telar-', '');
            }
            if(t){
                setTimeout(() => irATelar(t), 500);
            }
        });
    })();
    </script>
    <script>
    // Override navegación para scroll suave robusto (sin refrescar)
    (function(){
      function getScrollable(node){
        let n = node ? node.parentElement : null;
        while (n && n !== document.body) {
          const cs = getComputedStyle(n);
          const oy = cs.overflowY;
          if ((oy === 'auto' || oy === 'scroll') && n.scrollHeight > n.clientHeight) return n;
          n = n.parentElement;
        }
        return document.scrollingElement || document.documentElement;
      }
      window.irATelar = function(noTelar){
        // Cerrar dropdown si existe
        const menu = document.getElementById('menuDropdownTelares');
        const icon = document.getElementById('iconDropdown');
        if (menu) menu.classList.add('hidden');
        if (icon) icon.style.transform = 'rotate(0deg)';

        // Mostrar todos por si hay filtro
        document.querySelectorAll('[id^="telar-"]').forEach(el => el.classList.remove('hidden'));
        if (!noTelar) {
          const u0 = new URL(location.href); u0.search = ''; u0.hash = ''; history.replaceState(null,'',u0.toString());
          window.scrollTo({ top: 0, behavior: 'smooth' });
          return;
        }

        const el = document.getElementById('telar-'+noTelar);
        if (!el) return;

        const sticky = document.querySelector('nav.sticky, nav.fixed, .sticky.top-0');
        const stickyH = sticky ? sticky.getBoundingClientRect().height : 0;
        const extra = -50 // Más espacio para ver el telar completo y calendarios

        const scroller = getScrollable(el);
        const scRect = scroller.getBoundingClientRect ? scroller.getBoundingClientRect() : { top: 0 };
        const tRect = el.getBoundingClientRect();
        const current = scroller.scrollTop || window.pageYOffset || document.documentElement.scrollTop || 0;
        const targetTop = tRect.top - scRect.top + current - stickyH - extra;

        if (scroller.scrollTo) scroller.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });
        else window.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });

        const url = new URL(location.href); url.search = ''; url.hash = 'telar-'+noTelar; history.replaceState(null,'',url.toString());
      }
    })();
    </script>
    <script>
    // Insertar etiqueta "SIG. ORDEN" en la columna lateral
    (function(){
      function placeLabels(){
        document.querySelectorAll('.telar-section').forEach(section => {
          const col = section.querySelector('.bg-gradient-to-r.from-blue-600.to-blue-700, .bg-gray-100');
          if(!col) return;

          // Crear/obtener solo la etiqueta del centro (SIG. ORDEN)
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
