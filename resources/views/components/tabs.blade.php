{{--
    Componente: Tabs

    Descripción:
        Sistema de pestañas reutilizable con navegación entre contenido.
        Ideal para formularios con múltiples opciones o vistas alternativas.

    Props:
        @param array $tabs - Array de pestañas en formato ['id' => 'label', ...]
        @param string $active - ID de la pestaña activa por defecto

    Uso:
        <x-tabs :tabs="['qr' => 'Código QR', 'user' => 'ID Usuario']" active="user">
            <x-slot name="tab-user">
                <!-- Contenido de la pestaña Usuario -->
            </x-slot>

            <x-slot name="tab-qr">
                <!-- Contenido de la pestaña QR -->
            </x-slot>
        </x-tabs>
--}}

@props([
    'tabs' => [],
    'active' => null
])

@php
    $tabsArray = is_array($tabs) ? $tabs : [];
    $activeTab = $active ?? array_key_first($tabsArray);
@endphp

<div class="tabs-container" data-active="{{ $activeTab }}">
    <!-- Navegación de pestañas -->
    <div class="flex bg-slate-100 rounded-lg p-1 mb-8 gap-0">
        @foreach($tabsArray as $tabId => $tabLabel)
            <button
                type="button"
                class="tab-button flex-1 px-4 py-3 text-sm font-medium border-0 rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 m-0 {{ $tabId === $activeTab ? 'text-white bg-blue-600 shadow-sm' : 'text-slate-600 bg-transparent' }}"
                data-tab="{{ $tabId }}"
                onclick="switchTab('{{ $tabId }}')"
            >
                @if($tabId === 'qr')
                    <svg fill="currentColor" viewBox="0 0 20 20" class="w-4 h-4">
                        <path d="M3 3h7v7H3V3zm9 0h7v7h-7V3zm-9 9h7v7H3v-7zm15 0h3v3h-3v-3zm-3-9h3v3h-3V3zm3 6h3v3h-3V9zm-9 6h3v3h-3v-3zm6 0h3v3h-3v-3zm-3 0h3v3h-3v-3z"/>
                    </svg>
                @elseif($tabId === 'user')
                    <svg fill="currentColor" viewBox="0 0 20 20" class="w-4 h-4">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                    </svg>
                @endif
                {{ $tabLabel }}
            </button>
        @endforeach
    </div>

    <!-- Contenido de las pestañas -->
    <div class="tab-content">
        @foreach($tabsArray as $tabId => $tabLabel)
            <div
                id="tab-{{ $tabId }}"
                class="tab-pane {{ $tabId === $activeTab ? 'block' : 'hidden' }}"
            >
                @if($tabId === 'user')
                    {{ $tab_user ?? '' }}
                @elseif($tabId === 'qr')
                    {{ $tab_qr ?? '' }}
                @endif
            </div>
        @endforeach
    </div>
</div>

<script>
function switchTab(tabId) {
    const container = document.querySelector('.tabs-container');
    if (!container) return;

    // Actualizar pestañas
    container.querySelectorAll('.tab-button').forEach(btn => {
        const isActive = btn.dataset.tab === tabId;
        btn.className = btn.className.replace(
            isActive ? 'text-slate-600 bg-transparent' : 'text-white bg-blue-600 shadow-sm',
            isActive ? 'text-white bg-blue-600 shadow-sm' : 'text-slate-600 bg-transparent'
        );
    });

    // Mostrar/ocultar contenido
    container.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.toggle('block', pane.id === `tab-${tabId}`);
        pane.classList.toggle('hidden', pane.id !== `tab-${tabId}`);
    });

    // Actualizar atributo activo
    container.dataset.active = tabId;
}
</script>
