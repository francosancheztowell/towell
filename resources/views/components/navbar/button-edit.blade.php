{{--
    Componente: Navbar Button Edit

    Botón de editar para navbar-right con estilo consistente y verificación de permisos.

    Props:
        @param string $onclick - Función JavaScript a ejecutar al hacer clic (no se aplica si type="submit")
        @param string $title - Texto del tooltip (default: 'Editar')
        @param string $id - ID del botón (opcional, recomendado para controlar estado)
        @param bool $disabled - Si el botón está deshabilitado (default: true)
        @param string $type - Tipo de botón: 'button' (default) o 'submit' para enviar formularios
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
        <x-navbar.button-edit type="submit" title="Guardar" text="Guardar" module="Usuarios" />
--}}

@props([
    'onclick' => '',
    'title' => 'Editar',
    'id' => null,
    'disabled' => true,
    'type' => 'button',
    'module' => null,
    'moduleId' => null,
    'checkPermission' => null,
    'icon' => 'fa-pen-to-square',
    'iconColor' => null,
    'hoverBg' => null,
    'bg' => null,
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
                // Verificar SOLO 'modificar' (no usar 'crear' como fallback)
                if (function_exists('userCan')) {
                    $tienePermisoModificar = userCan('modificar', $moduleParam);
                    
                    // Además, verificar que el módulo esté activo (acceso = 1)
                    $tieneAcceso = false;
                    try {
                        $userId = auth()->id();
                        if ($userId) {
                            $rolId = is_numeric($moduleParam) ? $moduleParam : null;
                            if (!$rolId && $module) {
                                $rol = \App\Models\Sistema\SYSRoles::where('modulo', $module)->first();
                                $rolId = $rol ? $rol->idrol : null;
                            }
                            
                            if ($rolId) {
                                $permission = \App\Models\Sistema\SYSUsuariosRoles::where('idusuario', $userId)
                                    ->where('idrol', $rolId)
                                    ->first();
                                
                                $tieneAcceso = $permission && isset($permission->acceso) && $permission->acceso == 1;
                            }
                        }
                    } catch (\Exception $e) {
                        // Si hay error, asumir que no tiene acceso
                        $tieneAcceso = false;
                    }
                    
                    // El usuario debe tener permiso de modificar Y acceso activo
                    $hasPermission = $tienePermisoModificar && $tieneAcceso;
                } else {
                    $hasPermission = true;
                }
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
    // Establecer valores por defecto si no se proporcionan
    // Por defecto: texto "Editar", fondo morado, texto blanco
    // Si $text es null (no proporcionado), usar "Editar". Si es cadena vacía, mantener vacío.
    $finalText = ($text === null) ? 'Editar' : $text;
    $finalBg = $bg ?? 'bg-purple-500';
    $finalIconColor = $iconColor ?? 'text-white';
    
    // Si no se proporciona hoverBg, usar uno apropiado según el fondo
    if ($hoverBg === null) {
        if ($finalBg === 'bg-purple-500') {
            $finalHoverBg = 'hover:bg-purple-600';
        } elseif (str_contains($finalBg, 'bg-')) {
            // Si hay otro fondo, usar hover:opacity-90
            $finalHoverBg = 'hover:opacity-90';
        } else {
            // Si no hay fondo, usar el hover azul por defecto (comportamiento anterior)
            $finalHoverBg = 'hover:bg-blue-600';
        }
    } else {
        $finalHoverBg = $hoverBg;
    }

    // Normalizar el icono: remover "fa-solid " si viene incluido, ya que siempre lo agregamos
    $iconNormalized = str_replace('fa-solid ', '', $icon);
    // Asegurar que tenga el prefijo "fa-"
    if (!str_starts_with($iconNormalized, 'fa-')) {
        $iconNormalized = 'fa-' . $iconNormalized;
    }

    // Si hay texto, ajustar el padding
    $paddingClass = $finalText ? 'px-3 py-2' : 'p-2';
@endphp

@if($hasPermission)
<button
    type="{{ $type }}"
    @if($id) id="{{ $id }}" @endif
    @if($onclick && $type !== 'submit') onclick="{{ $onclick }}" @endif
    @if($module) data-module="{{ $module }}" @endif
    @if($moduleId) data-module-id="{{ $moduleId }}" @endif
    class="{{ $paddingClass }} {{ $finalText ? 'rounded-lg' : 'rounded-full' }} transition {{ $finalHoverBg }} disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 {{ $finalBg }} {{ !$finalText ? 'w-9 h-9' : '' }} {{ $class }}"
    @if($disabled) disabled @endif
    title="{{ $title }}">
    <i class="fa-solid {{ $iconNormalized }} {{ $finalIconColor }} {{ $finalText ? 'text-base' : 'text-sm' }}"></i>
    @if($finalText)
        <span class="text-sm font-medium {{ $finalIconColor }}">{{ $finalText }}</span>
    @endif
</button>
@endif

