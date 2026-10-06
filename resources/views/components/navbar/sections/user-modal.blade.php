@php
    $usuario = Auth::user();
    $fotoUrl = function_exists('getFotoUsuarioUrl') ? getFotoUsuarioUrl($usuario->foto ?? null) : null;
@endphp

{{-- Panel del flux:dropdown (navbar.blade.php): el popover lo posiciona y cierra Flux. --}}
<div id="user-modal" popover="manual"
     class="max-w-[calc(100vw-2rem)] w-80 bg-white rounded-lg shadow-lg border border-gray-200">
    <div class="p-4">
        <!-- Header del modal -->
        <div class="flex items-center gap-3 mb-3 pb-3 border-b border-gray-100">
            <flux:avatar circle size="lg" color="blue" initials:single class="shrink-0"
                         :name="$usuario->nombre" :src="$fotoUrl" :alt="'Foto de '.$usuario->nombre" />
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
    </div>
</div>
