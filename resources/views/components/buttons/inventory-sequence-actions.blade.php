{{--
    Componente: Inventory Sequence Actions (Acciones de Secuencia de Inventario)

    Descripción:
        Botones de acción para módulos de secuencia de inventario (crear, editar, eliminar).
        Incluye verificación de permisos basada en el módulo.

    Props:
        @param string $modulo - Nombre del módulo para permisos (ej: 'Secuencia Inv Telas', 'Secuencia Inv Trama')
        @param string $onCreate - Función JavaScript para crear (ej: 'agregarSecuenciaInvTelas')
        @param string $onEdit - Función JavaScript para editar (ej: 'editarSecuenciaInvTelas')
        @param string $onDelete - Función JavaScript para eliminar (ej: 'eliminarSecuenciaInvTelas')

    Uso:
        <x-buttons.inventory-sequence-actions
            modulo="Secuencia Inv Telas"
            onCreate="agregarSecuenciaInvTelas"
            onEdit="editarSecuenciaInvTelas"
            onDelete="eliminarSecuenciaInvTelas"
        />
--}}

@props([
    'modulo' => '',
    'onCreate' => '',
    'onEdit' => '',
    'onDelete' => '',
])

@php
    // Verificar permisos usando userCan()
    $puedeCrear = $modulo ? userCan('crear', $modulo) : true;
    $puedeEditar = $modulo ? userCan('modificar', $modulo) : true;
    $puedeEliminar = $modulo ? userCan('eliminar', $modulo) : true;
@endphp

<div class="flex items-center gap-1">
    {{-- Botón Crear --}}
    <button type="button" id="btn-agregar"
        @if($puedeCrear)
            onclick="{{ $onCreate }}()"
            class="p-2 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded-md transition-colors"
            title="Crear" aria-label="Crear"
        @else
            disabled
            class="p-2 text-gray-300 hover:text-gray-400 rounded-md transition-colors cursor-not-allowed"
            title="No tiene permiso para crear" aria-label="Crear (sin permiso)"
        @endif>
        <i class="fas fa-plus text-lg"></i>
    </button>

    {{-- Botón Editar --}}
    <button type="button" id="btn-editar"
        @if($puedeEditar)
            onclick="{{ $onEdit }}()"
        @endif
        disabled
        @if($puedeEditar)
            class="p-2 text-gray-400 hover:text-gray-600 rounded-md transition-colors cursor-not-allowed"
            title="Editar" aria-label="Editar"
        @else
            class="p-2 text-gray-300 hover:text-gray-400 rounded-md transition-colors cursor-not-allowed"
            title="No tiene permiso para editar" aria-label="Editar (sin permiso)"
        @endif>
        <i class="fas fa-edit text-lg"></i>
    </button>

    {{-- Botón Eliminar --}}
    <button type="button" id="btn-eliminar"
        @if($puedeEliminar)
            onclick="{{ $onDelete }}()"
        @endif
        disabled
        @if($puedeEliminar)
            class="p-2 text-red-400 hover:text-red-600 rounded-md transition-colors cursor-not-allowed"
            title="Eliminar" aria-label="Eliminar"
        @else
            class="p-2 text-gray-300 hover:text-gray-400 rounded-md transition-colors cursor-not-allowed"
            title="No tiene permiso para eliminar" aria-label="Eliminar (sin permiso)"
        @endif>
        <i class="fas fa-trash text-lg"></i>
    </button>
</div>
