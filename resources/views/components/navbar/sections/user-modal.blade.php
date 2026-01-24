@php
    $usuario = Auth::user();
    $fotoUrl = getFotoUsuarioUrl($usuario->foto ?? null);
    $usuarioInicial = strtoupper(substr($usuario->nombre, 0, 1));

    // Información del dispositivo
    $deviceInfo = getDeviceInfo();
    $deviceId = getDeviceIdentifier();
    $deviceModel = $deviceInfo['tipo']['modelo'] ?? '';
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

            @if(isset($usuario->correo) && $usuario->correo)
                <div class="flex items-center gap-2">
                    <i class="fas fa-envelope text-gray-400 flex-shrink-0 w-4 text-center"></i>
                    <span class="text-gray-600 truncate">{{ $usuario->correo }}</span>
                </div>
            @endif
        </div>

        <!-- Información del dispositivo -->
        <div class="mt-3 pt-3 border-t border-gray-100">
            <!-- Header con nombre editable -->
            <div class="flex items-center gap-2 mb-2">
                <i class="fas {{ $deviceInfo['tipo']['icono'] }} text-blue-500"></i>
                <div class="flex-1 min-w-0">
                    <!-- Nombre del dispositivo (editable) -->
                    <div class="flex items-center gap-1 group">
                        <span id="device-name" class="text-xs font-semibold text-gray-700 truncate cursor-pointer hover:text-blue-600"
                              title="Clic para editar nombre">
                            {{ $deviceInfo['tipo']['nombre'] }}
                        </span>
                        <button id="edit-device-name"
                                class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-blue-500 transition-opacity"
                                title="Editar nombre">
                            <i class="fas fa-pencil text-[10px]"></i>
                        </button>
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

                <!-- Navegador -->
                <div class="flex items-center gap-1.5 text-gray-600">
                    <i class="fab {{ $deviceInfo['navegador']['icono'] }} text-gray-400 w-3.5 text-center"></i>
                    <span class="truncate">{{ $deviceInfo['navegador']['nombre'] }}@if($deviceInfo['navegador']['version']) {{ $deviceInfo['navegador']['version'] }}@endif</span>
                </div>

                <!-- Resolución de pantalla -->
                <div class="flex items-center gap-1.5 text-gray-600">
                    <i class="fas fa-expand text-gray-400 w-3.5 text-center"></i>
                    <span id="screen-resolution">-</span>
                </div>

                <!-- IP -->
                <div class="flex items-center gap-1.5 text-gray-600">
                    <i class="fas fa-network-wired text-gray-400 w-3.5 text-center"></i>
                    <span class="truncate" title="{{ $deviceInfo['ip'] }}">{{ $deviceInfo['ip'] }}</span>
                </div>
            </div>

            <!-- Botón para ver User Agent (debug) -->
            <button id="show-user-agent"
                    class="mt-2 w-full text-[10px] text-gray-400 hover:text-blue-500 flex items-center justify-center gap-1 py-1 hover:bg-gray-50 rounded transition-colors"
                    title="Ver información técnica">
                <i class="fas fa-code"></i>
                <span>Ver info técnica</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal para ver User Agent -->
<div id="user-agent-modal" class="hidden fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-4 w-96 max-w-[calc(100vw-2rem)] mx-4">
        <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fas fa-info-circle text-blue-500"></i>
            Información técnica del dispositivo
        </h3>

        <div class="space-y-3 text-xs">
            <div>
                <label class="block text-gray-500 mb-1">Modelo detectado:</label>
                <div class="bg-gray-100 p-2 rounded font-mono text-gray-700 break-words">
                    {{ $deviceInfo['tipo']['nombre'] }}@if($deviceModel) - {{ $deviceModel }}@endif
                </div>
            </div>

            <div>
                <label class="block text-gray-500 mb-1">User Agent completo:</label>
                <div class="bg-gray-100 p-2 rounded font-mono text-gray-700 break-all text-[10px] max-h-32 overflow-y-auto">
                    {{ $deviceInfo['user_agent'] }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-gray-500 mb-1">ID Dispositivo:</label>
                    <div class="bg-gray-100 p-2 rounded font-mono text-gray-700">{{ $deviceId }}</div>
                </div>
                <div>
                    <label class="block text-gray-500 mb-1">IP:</label>
                    <div class="bg-gray-100 p-2 rounded font-mono text-gray-700">{{ $deviceInfo['ip'] }}</div>
                </div>
            </div>
        </div>

        <button id="close-user-agent"
                class="mt-4 w-full px-3 py-2 text-sm text-white bg-blue-500 rounded-lg hover:bg-blue-600 transition-colors">
            Cerrar
        </button>
    </div>
</div>

<!-- Input oculto para editar nombre -->
<div id="device-name-editor" class="hidden fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-4 w-72 mx-4">
        <h3 class="text-sm font-bold text-gray-800 mb-3">Nombre del dispositivo</h3>
        <input type="text"
               id="device-name-input"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="Ej: Tablet Producción 1"
               maxlength="30">
        <p class="text-[10px] text-gray-500 mt-1">Este nombre se guardará en este navegador</p>
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

@push('scripts')
<script>
(function() {
    const DEVICE_ID = '{{ $deviceId }}';
    const STORAGE_KEY = 'device_name_' + DEVICE_ID;
    const DEFAULT_NAME = '{{ $deviceInfo['tipo']['nombre'] }}';

    // Elementos
    const deviceNameEl = document.getElementById('device-name');
    const editBtn = document.getElementById('edit-device-name');
    const editor = document.getElementById('device-name-editor');
    const input = document.getElementById('device-name-input');
    const saveBtn = document.getElementById('save-device-name');
    const cancelBtn = document.getElementById('cancel-device-name');
    const resolutionEl = document.getElementById('screen-resolution');

    // Cargar nombre guardado
    function loadDeviceName() {
        const savedName = localStorage.getItem(STORAGE_KEY);
        if (savedName && deviceNameEl) {
            deviceNameEl.textContent = savedName;
        }
    }

    // Guardar nombre
    function saveDeviceName() {
        const name = input.value.trim();
        if (name) {
            localStorage.setItem(STORAGE_KEY, name);
            if (deviceNameEl) deviceNameEl.textContent = name;
        } else {
            localStorage.removeItem(STORAGE_KEY);
            if (deviceNameEl) deviceNameEl.textContent = DEFAULT_NAME;
        }
        closeEditor();
    }

    // Abrir editor
    function openEditor() {
        if (editor && input) {
            input.value = localStorage.getItem(STORAGE_KEY) || '';
            editor.classList.remove('hidden');
            input.focus();
            input.select();
        }
    }

    // Cerrar editor
    function closeEditor() {
        if (editor) editor.classList.add('hidden');
    }

    // Event listeners
    if (deviceNameEl) deviceNameEl.addEventListener('click', openEditor);
    if (editBtn) editBtn.addEventListener('click', openEditor);
    if (saveBtn) saveBtn.addEventListener('click', saveDeviceName);
    if (cancelBtn) cancelBtn.addEventListener('click', closeEditor);

    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') saveDeviceName();
            if (e.key === 'Escape') closeEditor();
        });
    }

    if (editor) {
        editor.addEventListener('click', function(e) {
            if (e.target === editor) closeEditor();
        });
    }

    // Modal User Agent
    const showUABtn = document.getElementById('show-user-agent');
    const uaModal = document.getElementById('user-agent-modal');
    const closeUABtn = document.getElementById('close-user-agent');

    if (showUABtn && uaModal) {
        showUABtn.addEventListener('click', function() {
            uaModal.classList.remove('hidden');
        });
    }

    if (closeUABtn && uaModal) {
        closeUABtn.addEventListener('click', function() {
            uaModal.classList.add('hidden');
        });
    }

    if (uaModal) {
        uaModal.addEventListener('click', function(e) {
            if (e.target === uaModal) uaModal.classList.add('hidden');
        });
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        loadDeviceName();
        if (resolutionEl) {
            resolutionEl.textContent = window.screen.width + ' × ' + window.screen.height;
        }
    });
})();
</script>
@endpush
