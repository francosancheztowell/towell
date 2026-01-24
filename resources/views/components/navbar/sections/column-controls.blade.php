@props([
    'resetId' => 'btnResetColumns',
    'resetIconId' => 'iconResetColumns'
])

{{-- Componente reutilizable para controles de columnas --}}

<button type="button" 
        onclick="openPinColumnsModal()"
        class="w-9 h-9 flex items-center justify-center rounded-full bg-yellow-600 text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 transition-colors"
        title="Fijar columnas" 
        aria-label="Fijar columnas">
    <i class="fa-solid fa-thumbtack text-sm"></i>
</button>

<button type="button" 
        onclick="openHideColumnsModal()"
        class="w-9 h-9 flex items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 transition-colors"
        title="Ocultar columnas" 
        aria-label="Ocultar columnas">
    <i class="fa-solid fa-eye-slash text-sm"></i>
</button>

<button type="button" 
        id="{{ $resetId }}"
        class="w-9 h-9 flex items-center justify-center rounded-full bg-gray-500 text-white hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400 transition-colors"
        title="Restablecer columnas" 
        aria-label="Restablecer columnas">
    <i id="{{ $resetIconId }}" class="fa-solid fa-rotate text-sm"></i>
</button>
