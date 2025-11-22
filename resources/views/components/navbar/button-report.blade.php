{{--
    Componente: Navbar Button Report

    Botón de reporte para navbar-right con estilo consistente y verificación de permisos.

    Props:
        @param string $onclick - Función JavaScript a ejecutar al hacer clic
        @param string $title - Texto del tooltip (default: 'Reporte')
        @param string $id - ID del botón (opcional)
        @param bool $disabled - Si el botón está deshabilitado (default: false)
        @param string $module - Nombre del módulo para verificar permisos (opcional)
        @param int $moduleId - ID del módulo (idrol) para verificar permisos (opcional, preferido sobre $module)
        @param bool $checkPermission - Si debe verificar permisos (default: true si se proporciona $module o $moduleId)
        @param string $icon - Clase del icono FontAwesome (default: 'fa-file')
        @param string $iconColor - Color del icono en clases Tailwind (default: 'text-purple-600')
        @param string $hoverBg - Color de fondo al hacer hover en clases Tailwind (default: 'hover:bg-purple-100')
        @param string $text - Texto opcional para mostrar junto al ícono (opcional)
        @param string $bg - Color de fondo en clases Tailwind (opcional, ej: 'bg-purple-500', 'bg-purple-600')
        @param string $class - Clases CSS adicionales personalizadas (opcional)

    Uso:
        <x-navbar.button-report onclick="generarReporte()" />
        <x-navbar.button-report onclick="handleReport()" moduleId="123" title="Generar Reporte" id="btn-reporte" />
        <x-navbar.button-report onclick="handleReport()" module="Producción" title="Generar Reporte" id="btn-reporte" />
        <x-navbar.button-report onclick="exportarReporte()" title="Exportar" icon="fa-download" />
        <x-navbar.button-report onclick="exportarReporte()" title="Exportar" icon="fa-download" iconColor="text-blue-500" hoverBg="hover:bg-blue-100" />
        <x-navbar.button-report onclick="exportarReporte()" title="Exportar" icon="fa-download" text="Exportar Reporte" />
        <x-navbar.button-report onclick="exportarReporte()" title="Exportar" icon="fa-download" text="Exportar" bg="bg-purple-500" iconColor="text-white" />
--}}

@props([
    'onclick' => '',
    'title' => 'Reporte',
    'id' => null,
    'disabled' => false,
    'module' => null,
    'moduleId' => null,
    'checkPermission' => null,
    'icon' => 'fa-file',
    'iconColor' => 'text-purple-600',
    'hoverBg' => 'hover:bg-purple-100',
    'text' => null,
    'bg' => null,
    'class' => ''
])

@php
    // Si se proporciona módulo o moduleId, verificar permisos
    // Verifica SOLO el permiso 'registrar' (independiente de otros permisos)
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
                $hasPermission = function_exists('userCan') ? userCan('registrar', $moduleParam) : true;

                // Debug temporal: log para verificar permisos
                if (function_exists('logger') && !$hasPermission) {
                    \Log::info('Button-report: Sin permiso registrar', [
                        'moduleParam' => $moduleParam,
                        'user_id' => auth()->id(),
                        'module' => $module,
                        'moduleId' => $moduleId
                    ]);
                }
            } else {
                // Si no se encontró el módulo, permitir por defecto para no bloquear
                $hasPermission = true;
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
        if ($hoverBg === 'hover:bg-purple-100') {
            // Si hoverBg es el default (no se proporcionó uno personalizado), usar hover más oscuro automáticamente
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
    class="{{ $paddingClass }} {{ $text ? 'rounded-lg' : 'rounded-full' }} transition {{ $finalHoverBg }} disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 {{ $bg ?? '' }} {{ !$text ? ($bg ? 'w-9 h-9' : 'w-9 h-9') : '' }} {{ $class }}"
    @if($disabled) disabled @endif
    title="{{ $title }}">
    <i class="fa-solid {{ $iconNormalized }} {{ $iconColor }} {{ $text ? 'text-base' : 'text-sm' }}"></i>
    @if($text)
        <span class="text-sm font-medium">{{ $text }}</span>
    @endif
</button>
@endif
