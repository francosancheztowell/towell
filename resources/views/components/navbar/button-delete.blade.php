{{--
    Componente: Navbar Button Delete

    Botón de eliminar para navbar-right con estilo consistente y verificación de permisos.

    Props:
        @param string $onclick - Función JavaScript a ejecutar al hacer clic
        @param string $title - Texto del tooltip (default: 'Eliminar')
        @param string $id - ID del botón (opcional, recomendado para controlar estado)
        @param bool $disabled - Si el botón está deshabilitado (default: true)
        @param string $module - Nombre del módulo para verificar permisos (opcional)
        @param int $moduleId - ID del módulo (idrol) para verificar permisos (opcional, preferido sobre $module)
        @param bool $checkPermission - Si debe verificar permisos (default: true si se proporciona $module o $moduleId)
        @param string $icon - Clase del icono FontAwesome (default: 'fa-trash')
        @param string $iconColor - Color del icono en clases Tailwind (default: 'text-white')
        @param string $hoverBg - Color de fondo al hacer hover en clases Tailwind (default: 'hover:bg-blue-600')
        @param string $bg - Color de fondo en clases Tailwind (default: 'bg-blue-500')
        @param string $text - Texto opcional para mostrar junto al ícono (opcional)
        @param string $class - Clases CSS adicionales personalizadas (opcional)

    Uso:
        <x-navbar.button-delete onclick="deleteSelected()" id="btn-delete" />
        <x-navbar.button-delete onclick="handleDelete()" moduleId="123" title="Eliminar Registro" id="btn-top-delete" :disabled="false" />
        <x-navbar.button-delete onclick="handleDelete()" module="Marcas Finales" title="Eliminar Registro" id="btn-top-delete" :disabled="false" />
        <x-navbar.button-delete onclick="eliminar()" title="Eliminar" icon="fa-trash-can" />
        <x-navbar.button-delete onclick="eliminar()" title="Eliminar" icon="fa-xmark" iconColor="text-gray-600" hoverBg="hover:bg-gray-100" />
--}}

@props([
    'onclick' => '',
    'title' => 'Eliminar',
    'id' => null,
    'disabled' => true,
    'module' => null,
    'moduleId' => null,
    'checkPermission' => null,
    'icon' => 'fa-trash',
    'iconColor' => 'text-red-500',
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
                    $rol = \App\Models\SYSRoles::where('modulo', $module)->first();
                    $moduleParam = $rol ? $rol->idrol : $module; // Fallback al nombre si no se encuentra
                } catch (\Exception $e) {
                    $moduleParam = $module; // Fallback al nombre en caso de error
                }
            } else {
                $moduleParam = null;
            }

            if ($moduleParam) {
                $hasPermission = function_exists('userCan') ? userCan('eliminar', $moduleParam) : true;
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
@endphp

@php
    // Extraer hover del class si está presente (tiene prioridad)
    $hoverFromClass = '';
    if ($class && preg_match('/hover:[^\s]+/', $class, $matches)) {
        $hoverFromClass = $matches[0];
    }

    // Lógica para hoverBg: si hay hover en class, usarlo; si no, usar la lógica normal
    $finalHoverBg = $hoverBg;
    if ($hoverFromClass) {
        // Si hay hover en class, usarlo (tiene máxima prioridad)
        $finalHoverBg = $hoverFromClass;
    } elseif ($bg) {
        // Si hay un fondo personalizado y no hay hover en class
        if ($hoverBg === 'hover:bg-red-100') {
            // Si hoverBg es el default antiguo, usar hover más oscuro automáticamente
            $finalHoverBg = 'hover:opacity-90';
        }
        // Si hoverBg es diferente al default (se proporcionó uno personalizado), se mantiene el proporcionado
    }

    // Remover el hover del class para evitar duplicados
    if ($hoverFromClass) {
        $class = preg_replace('/hover:[^\s]+/', '', $class);
        $class = preg_replace('/\s+/', ' ', trim($class));
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
        <span class="text-sm font-medium">{{ $text }}</span>
    @endif
</button>
@endif

