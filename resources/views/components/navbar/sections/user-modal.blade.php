@php
    $usuario = Auth::user();
    $fotoUrl = function_exists('getFotoUsuarioUrl') ? getFotoUsuarioUrl($usuario->foto ?? null) : null;
    $usuarioInicial = strtoupper(substr($usuario->nombre, 0, 1));

    // Información del dispositivo
    $deviceInfo = function_exists('getDeviceInfo') ? getDeviceInfo() : [
        'tipo' => [
            'nombre' => 'Dispositivo',
            'modelo' => '',
            'icono' => 'fa-desktop',
            'tipo' => 'desktop'
        ],
        'navegador' => [
            'nombre' => 'Navegador',
            'version' => '',
            'icono' => 'fa-globe'
        ],
        'sistema' => [
            'nombre' => 'Sistema',
            'version' => '',
            'icono' => 'fa-desktop'
        ],
        'ip' => function_exists('getClientIpv4') ? getClientIpv4() : request()->ip(),
        'user_agent' => request()->userAgent() ?? ''
    ];
    $deviceId = function_exists('getDeviceIdentifier') ? getDeviceIdentifier() : 'N/A';
    $deviceModel = $deviceInfo['tipo']['modelo'] ?? '';

    // Nombre del dispositivo (MON-19): vive en SYSMonDispositivo, por la cookie towell_disp.
    // Una lectura por índice único; si falla o el monitoreo está apagado, se usa el nombre detectado.
    $monitoreoActivo = \App\Services\Monitoreo\Monitoreo::activo();
    $dispUuid = \App\Services\Monitoreo\DispositivoService::uuid(request());
    $nombreDispositivo = $monitoreoActivo && $dispUuid
        ? (string) \App\Services\Monitoreo\Monitoreo::seguro('leer nombre de dispositivo', fn () => \App\Models\Sistema\Monitoreo\MonDispositivo::where('Uuid', $dispUuid)->value('Nombre'), '')
        : '';
@endphp

<div id="user-modal"
     class="fixed top-16 right-4 max-w-[calc(100vw-2rem)] w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50 opacity-0 invisible scale-95 transition-all duration-200 origin-top-right">
    <div class="p-4">
        <!-- Header del modal -->
        <div class="flex items-center gap-3 mb-3 pb-3 border-b border-gray-100">
            <div class="w-12 h-12 rounded-full overflow-hidden flex-shrink-0">
                @if($fotoUrl)
                    <img src="{{ $fotoUrl }}"
                         alt="Foto de {{ $usuario->nombre }}"
                         width="48"
                         height="48"
                         decoding="async"
                         class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-base">
                        {{ $usuarioInicial }}
                    </div>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="font-bold text-gray-900 text-sm truncate">{{ $usuario->nombre }}</h4>
                <p class="text-xs text-gray-500">{{ $usuario->puesto ?? 'Usuario' }}</p>
            </div>
        </div>

        <!-- Información del usuario -->
        <div class="space-y-2 text-sm">
            @if(isset($usuario->area) && $usuario->area)
                <div class="flex items-center gap-2">
                    <i class="fas fa-building text-gray-400 flex-shrink-0 w-4 text-center"></i>
                    <span class="text-gray-600 truncate">{{ $usuario->area }}</span>
                </div>
            @endif

            @if(isset($usuario->turno) && $usuario->turno)
                <div class="flex items-center gap-2">
                    <i class="fas fa-clock text-gray-400 flex-shrink-0 w-4 text-center"></i>
                    <span class="text-gray-600">Turno {{ $usuario->turno }}</span>
                </div>
            @endif

            @if(isset($usuario->numero_empleado) && $usuario->numero_empleado)
                <div class="flex items-center gap-2">
                    <i class="fas fa-id-badge text-gray-400 flex-shrink-0 w-4 text-center"></i>
                    <span class="text-gray-600">No. empleado {{ $usuario->numero_empleado }}</span>
                </div>
            @endif
        </div>

        @can('admin')
            <a href="{{ route('admin.index') }}"
               class="mt-3 flex items-center gap-2 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                <i class="fas fa-shield-halved w-4 text-center"></i>
                <span>Admin</span>
            </a>
        @endcan

        <!-- Información del dispositivo -->
        <div class="mt-3 pt-3 border-t border-gray-100">
            <!-- Header con nombre editable -->
            <div class="flex items-center gap-2 mb-2">
                <i class="fas {{ $deviceInfo['tipo']['icono'] }} text-blue-500"></i>
                <div class="flex-1 min-w-0">
                    <!-- Nombre del dispositivo (editable) -->
                    <div class="flex items-center gap-1 group">
                        {{-- resources/js/monitoreo/dispositivo.ts: edición y migración del nombre viejo de localStorage. --}}
                        <span id="device-name"
                              @class(['text-xs font-semibold text-gray-700 truncate', 'cursor-pointer hover:text-blue-600' => $monitoreoActivo])
                              data-default="{{ $deviceInfo['tipo']['nombre'] }}"
                              data-nombre="{{ $nombreDispositivo }}"
                              data-llave-local="device_name_{{ $deviceId }}"
                              @if($monitoreoActivo) title="Clic para editar nombre" @endif>
                            {{ $nombreDispositivo !== '' ? $nombreDispositivo : $deviceInfo['tipo']['nombre'] }}
                        </span>
                        @if($monitoreoActivo)
                            <button id="edit-device-name"
                                    type="button"
                                    class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-blue-500 transition-opacity"
                                    title="Editar nombre">
                                <i class="fas fa-pencil text-[10px]"></i>
                            </button>
                        @endif
                    </div>
                    <!-- Modelo detectado -->
                    @if($deviceModel)
                        <span class="text-[10px] text-gray-500">{{ $deviceModel }}</span>
                    @endif
                </div>
                <span class="px-2 py-0.5 bg-gray-100 rounded text-xs font-mono text-gray-600" title="ID de dispositivo">
                    {{ $deviceId }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs">
                <!-- Sistema operativo -->
                <div class="flex items-center gap-1.5 text-gray-600">
                    <i class="fab {{ $deviceInfo['sistema']['icono'] }} text-gray-400 w-3.5 text-center"></i>
                    <span class="truncate">{{ $deviceInfo['sistema']['nombre'] }}@if($deviceInfo['sistema']['version']) {{ $deviceInfo['sistema']['version'] }}@endif</span>
                </div>

                <!-- IPv4 del dispositivo (IP pública desde el navegador si el servidor devuelve localhost) -->
                <div class="flex items-center gap-1.5 text-gray-600">
                    <i class="fas fa-network-wired text-gray-400 w-3.5 text-center"></i>
                    <span id="device-ip-display" class="truncate" title="Dirección IPv4" data-server-ip="{{ $deviceInfo['ip'] }}">{{ $deviceInfo['ip'] }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@if($monitoreoActivo)
<!-- Input oculto para editar nombre -->
<div id="device-name-editor" class="hidden fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-4 w-72 mx-4">
        <h3 class="text-sm font-bold text-gray-800 mb-3">Nombre del dispositivo</h3>
        <input type="text"
               id="device-name-input"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="Ej: Tablet Producción 1"
               maxlength="80">
        <p class="text-[10px] text-gray-500 mt-1">Este nombre identifica a este equipo en el monitoreo</p>
        <div class="flex gap-2 mt-3">
            <button id="cancel-device-name"
                    class="flex-1 px-3 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                Cancelar
            </button>
            <button id="save-device-name"
                    class="flex-1 px-3 py-2 text-sm text-white bg-blue-500 rounded-lg hover:bg-blue-600 transition-colors">
                Guardar
            </button>
        </div>
    </div>
</div>
@endif
