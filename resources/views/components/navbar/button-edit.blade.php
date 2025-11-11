{{--
    Componente: Navbar Button Edit

    Botón de editar para navbar-right con estilo consistente y verificación de permisos.

    Props:
        @param string $onclick - Función JavaScript a ejecutar al hacer clic
        @param string $title - Texto del tooltip (default: 'Editar')
        @param string $id - ID del botón (opcional, recomendado para controlar estado)
        @param bool $disabled - Si el botón está deshabilitado (default: true)
        @param string $module - Nombre del módulo para verificar permisos (opcional)
        @param int $moduleId - ID del módulo (idrol) para verificar permisos (opcional, preferido sobre $module)
        @param bool $checkPermission - Si debe verificar permisos (default: true si se proporciona $module o $moduleId)

    Uso:
        <x-navbar.button-edit onclick="editSelected()" id="btn-edit" />
        <x-navbar.button-edit onclick="handleEdit()" moduleId="123" title="Editar Registro" id="btn-top-edit" :disabled="false" />
        <x-navbar.button-edit onclick="handleEdit()" module="Marcas Finales" title="Editar Registro" id="btn-top-edit" :disabled="false" />
--}}

@props([
    'onclick' => '',
    'title' => 'Editar',
    'id' => null,
    'disabled' => true,
    'module' => null,
    'moduleId' => null,
    'checkPermission' => null
])

@php
    // Si se proporciona módulo o moduleId, verificar permisos
    $hasPermission = true;
    if ($moduleId || $module) {
        $checkPermission = $checkPermission ?? true;
        if ($checkPermission) {
            // Si se proporciona moduleId, usarlo directamente
            // Si solo se proporciona module (nombre), buscar el ID automáticamente
            if ($moduleId) {
                $moduleParam = $moduleId;
            } elseif ($module) {
                try {
                    $rol = \App\Models\SYSRoles::where('modulo', $module)->first();
                    $moduleParam = $rol ? $rol->idrol : $module; // Fallback al nombre si no se encuentra
                } catch (\Exception $e) {
                    $moduleParam = $module; // Fallback al nombre en caso de error
                }
            } else {
                $moduleParam = null;
            }

            if ($moduleParam) {
                $hasPermission = function_exists('userCan') ? userCan('modificar', $moduleParam) : true;
            }
        }
    }
@endphp

@if($hasPermission)
<button
    type="button"
    @if($id) id="{{ $id }}" @endif
    onclick="{{ $onclick }}"
    class="p-2 rounded-lg transition hover:bg-yellow-100 disabled:opacity-50 disabled:cursor-not-allowed"
    @if($disabled) disabled @endif
    title="{{ $title }}">
    <i class="fa-solid fa-pen-to-square text-yellow-500 text-lg"></i>
</button>
@endif

