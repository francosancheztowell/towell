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
        @param string $icon - Clase del icono FontAwesome (default: 'fa-plus')
        @param string $iconColor - Color del icono en clases Tailwind (default: 'text-green-600')
        @param string $hoverBg - Color de fondo al hacer hover en clases Tailwind (default: 'hover:bg-green-100')
        @param string $text - Texto opcional para mostrar junto al ícono (opcional)
        @param string $bg - Color de fondo en clases Tailwind (opcional, ej: 'bg-blue-500', 'bg-green-600')

    Uso:
        <x-navbar.button-create onclick="openModal('createModal')" />
        <x-navbar.button-create onclick="handleCreate()" moduleId="123" title="Crear Registro" id="btn-create" />
        <x-navbar.button-create onclick="handleCreate()" module="Marcas Finales" title="Crear Registro" id="btn-create" />
        <x-navbar.button-create onclick="cargarInfo()" title="Cargar" icon="fa-download" />
        <x-navbar.button-create onclick="cargarInfo()" title="Cargar" icon="fa-download" iconColor="text-blue-500" hoverBg="hover:bg-blue-100" />
        <x-navbar.button-create onclick="cargarInfo()" title="Cargar" icon="fa-download" text="Cargar Información" />
        <x-navbar.button-create onclick="cargarInfo()" title="Cargar" icon="fa-download" text="Cargar" bg="bg-blue-500" iconColor="text-white" />
--}}

@props([
    'onclick' => '',
    'title' => 'Nuevo',
    'id' => null,
    'disabled' => false,
    'module' => null,
    'moduleId' => null,
    'checkPermission' => null,
    'icon' => 'fa-plus',
    'iconColor' => 'text-green-600',
    'hoverBg' => 'hover:bg-green-100',
    'text' => null,
    'bg' => null
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
                $hasPermission = function_exists('userCan') ? userCan('crear', $moduleParam) : true;
            }
        }
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

    // Si hay fondo, ajustar el hoverBg si no se especifica
    $finalHoverBg = $hoverBg;
    if ($bg && $hoverBg === 'hover:bg-green-100') {
        // Si hay un fondo personalizado, usar el hoverBg proporcionado o un hover más oscuro por defecto
        $finalHoverBg = $hoverBg !== 'hover:bg-green-100' ? $hoverBg : 'hover:opacity-90';
    }
@endphp

@if($hasPermission)
<button
    type="button"
    @if($id && !$attributes->has('id')) id="{{ $id }}" @endif
    onclick="{{ $onclick }}"
    {{ $attributes->merge(['class' => $paddingClass.' '.($text ? 'rounded-lg' : 'rounded-full').' transition '.$finalHoverBg.' disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 '.($bg ?? '').' '.(!$text ? 'w-9 h-9' : '')]) }}
    @if($disabled) disabled @endif
    title="{{ $title }}">
    <i class="fa-solid {{ $iconNormalized }} {{ $iconColor }} {{ $text ? 'text-base' : 'text-sm' }}"></i>
    @if($text)
        <span class="text-sm font-medium {{ $iconColor }}">{{ $text }}</span>
    @endif
</button>
@endif

