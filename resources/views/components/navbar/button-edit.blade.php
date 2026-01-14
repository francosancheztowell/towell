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
        @param string $icon - Clase del icono FontAwesome (default: 'fa-pen-to-square')
        @param string $iconColor - Color del icono en clases Tailwind (default: 'text-white')
        @param string $hoverBg - Color de fondo al hacer hover en clases Tailwind (default: 'hover:bg-blue-600')
        @param string $bg - Color de fondo en clases Tailwind (default: 'bg-blue-500')
        @param string $text - Texto opcional para mostrar junto al ícono (opcional)
        @param string $class - Clases CSS adicionales personalizadas (opcional)

    Uso:
        <x-navbar.button-edit onclick="editSelected()" id="btn-edit" />
        <x-navbar.button-edit onclick="handleEdit()" moduleId="123" title="Editar Registro" id="btn-top-edit" :disabled="false" />
        <x-navbar.button-edit onclick="handleEdit()" module="Marcas Finales" title="Editar Registro" id="btn-top-edit" :disabled="false" />
        <x-navbar.button-edit onclick="subir()" title="Subir Prioridad" icon="fa-arrow-up" />
        <x-navbar.button-edit onclick="bajar()" title="Bajar Prioridad" icon="fa-arrow-down" iconColor="text-blue-500" hoverBg="hover:bg-blue-100" />
--}}

@props([
    'onclick' => '',
    'title' => 'Editar',
    'id' => null,
    'disabled' => true,
    'module' => null,
    'moduleId' => null,
    'checkPermission' => null,
    'icon' => 'fa-pen-to-square',
    'iconColor' => 'text-blue-600',
    'hoverBg' => '',
    'bg' => '',
    'text' => null,
    'class' => ''
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
                    $rol = \App\Models\Sistema\SYSRoles::where('modulo', $module)->first();
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

    // Si tiene permisos y se proporcionó module/moduleId, habilitar el botón automáticamente
    // El disabled solo se aplica si el usuario no tiene permisos o si se pasa explícitamente
    if ($hasPermission && ($moduleId || $module)) {
        $disabled = false;
    }
@endphp

@php
    // Normalizar el icono: remover "fa-solid " si viene incluido, ya que siempre lo agregamos
    $iconNormalized = str_replace('fa-solid ', '', $icon);
    // Asegurar que tenga el prefijo "fa-"
    if (!str_starts_with($iconNormalized, 'fa-')) {
        $iconNormalized = 'fa-' . $iconNormalized;
    }

    // Si hay texto, ajustar el padding
    $paddingClass = $text ? 'px-3 py-2' : 'p-2';
@endphp

@php
    // Si hay fondo personalizado, ajustar hoverBg si es necesario
    $finalHoverBg = $hoverBg;
    if ($bg && $hoverBg === '') {
        // Si hay bg pero hoverBg es el default antiguo, usar hover más oscuro
        $finalHoverBg = 'hover:opacity-90';
    }
@endphp

@if($hasPermission)
<button
    type="button"
    @if($id) id="{{ $id }}" @endif
    onclick="{{ $onclick }}"
    class="{{ $paddingClass }} {{ $text ? 'rounded-lg' : 'rounded-full' }} transition {{ $finalHoverBg }} disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 {{ $bg }} {{ !$text ? 'w-9 h-9' : '' }} {{ $class }}"
    @if($disabled) disabled @endif
    title="{{ $title }}">
    <i class="fa-solid {{ $iconNormalized }} {{ $iconColor }} {{ $text ? 'text-base' : 'text-sm' }}"></i>
    @if($text)
        <span class="text-sm font-medium {{ $iconColor }}">{{ $text }}</span>
    @endif
</button>
@endif

