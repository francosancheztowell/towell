{{--
    Componente: Navbar Button Create

    Botón de crear para navbar-right con estilo consistente y verificación de permisos.

    Props:
        @param string $onclick - Función JavaScript a ejecutar al hacer clic
        @param string $title - Texto del tooltip (default: 'Nuevo')
        @param string $id - ID del botón (opcional)
        @param bool $disabled - Si el botón está deshabilitado (default: false)
        @param string $module - Nombre del módulo para verificar permisos (opcional)
        @param int $moduleId - ID del módulo (idrol) para verificar permisos (opcional, preferido sobre $module)
        @param bool $checkPermission - Si debe verificar permisos (default: true si se proporciona $module o $moduleId)

    Uso:
        <x-navbar.button-create onclick="openModal('createModal')" />
        <x-navbar.button-create onclick="handleCreate()" moduleId="123" title="Crear Registro" id="btn-create" />
        <x-navbar.button-create onclick="handleCreate()" module="Marcas Finales" title="Crear Registro" id="btn-create" />
--}}

@props([
    'onclick' => '',
    'title' => 'Nuevo',
    'id' => null,
    'disabled' => false,
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
                $hasPermission = function_exists('userCan') ? userCan('crear', $moduleParam) : true;
            }
        }
    }
@endphp

@if($hasPermission)
<button
    type="button"
    @if($id) id="{{ $id }}" @endif
    onclick="{{ $onclick }}"
    class="p-2 rounded-lg transition hover:bg-green-100 disabled:opacity-50 disabled:cursor-not-allowed"
    @if($disabled) disabled @endif
    title="{{ $title }}">
    <i class="fa-solid fa-plus text-green-600 text-lg"></i>
</button>
@endif

