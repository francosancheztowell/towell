@extends('layouts.app')
@section('navbar-right')
<button href="{{ route('configuracion.usuarios.create') }}"
class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
 <i class="fas fa-plus mr-2"></i>
 Nuevo Usuario
</button>
@endsection
@section('page-title', 'Lista de Usuarios')

@section('content')
    @php
        function iniciales($nombre)
        {
            $partes = preg_split('/\s+/', trim($nombre));
            $ini = '';
            foreach ($partes as $p) {
                if ($p !== '') {
                    $ini .= mb_strtoupper(mb_substr($p, 0, 1));
                }
                if (mb_strlen($ini) >= 2) break;
            }
            return mb_substr($ini, 0, 2);
        }
    @endphp

    <div class="container mx-auto">
        @if (session('success'))
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: @json(session('success')),
                            confirmButtonColor: '#2563eb'
                        });
                    }
                });
            </script>
        @endif

        <div class="bg-white ">
            @forelse ($usuarios as $u)
                <div class="px-4 py-4 border-b border-gray-100 hover:bg-gray-50 transition-colors {{ $loop->last ? 'rounded-b-lg' : '' }}">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0">
                            @php
                                $fotoUrl = getFotoUsuarioUrl($u->foto ?? null);
                            @endphp
                            @if ($fotoUrl)
                                <img src="{{ $fotoUrl }}" alt="Foto de {{ $u->nombre }}"
                                    class="h-12 w-12 rounded-full object-cover border-2 border-gray-200">
                            @else
                                <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center border-2 border-gray-200">
                                    <span class="text-white font-semibold text-sm">{{ iniciales($u->nombre) }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <h3 class="text-base font-semibold text-gray-900">{{ $u->nombre }}</h3>
                                        <span class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full">
                                            #{{ $u->numero_empleado }}
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm text-gray-600">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-building text-gray-400"></i>
                                            <span class="font-medium">Área:</span>
                                            <span>{{ $u->area ?? 'No asignada' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-clock text-gray-400"></i>
                                            <span class="font-medium">Turno:</span>
                                            <span>{{ $u->turno ?? 'No asignado' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-phone text-gray-400"></i>
                                            <span class="font-medium">Teléfono:</span>
                                            <span>{{ $u->telefono ?? 'No registrado' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-comment text-gray-400"></i>
                                            <span class="font-medium">Mensajes:</span>
                                            @if ($u->enviarMensaje)
                                                <span class="px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full">Activo</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-full">Inactivo</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 ml-4 flex-shrink-0">
                                    <a href="{{ route('configuracion.usuarios.qr', $u->idusuario) }}"
                                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-purple-700 bg-purple-100 hover:bg-purple-200 rounded-lg transition-colors"
                                       title="Ver código QR">
                                        <i class="fas fa-qrcode mr-1"></i>
                                        QR
                                    </a>
                                    <a href="{{ route('configuracion.usuarios.edit', $u->idusuario) }}"
                                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-lg transition-colors">
                                        <i class="fas fa-edit mr-1"></i>
                                        Editar
                                    </a>
                                    <form action="{{ route('configuracion.usuarios.destroy', $u->idusuario) }}" method="POST"
                                          onsubmit="return confirmarEliminacion(event)" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-lg transition-colors">
                                            <i class="fas fa-trash mr-1"></i>
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-12 text-center">
                    <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay usuarios</h3>
                    <p class="mt-1 text-sm text-gray-500">Comienza creando un nuevo usuario.</p>
                    <div class="mt-6">
                        <a href="{{ route('configuracion.usuarios.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                            <i class="fas fa-plus mr-2"></i>
                            Crear primer usuario
                        </a>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Confirmación de eliminación (SweetAlert si está disponible; fallback a confirm) --}}
    <script>
        function confirmarEliminacion(e) {
            if (window.Swal) {
                e.preventDefault();
                const form = e.target;
                Swal.fire({
                    title: '¿Eliminar usuario?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#2563eb',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((r) => {
                    if (r.isConfirmed) form.submit();
                });
                return false;
            } else {
                return confirm('¿Eliminar usuario? ');
            }
        }
    </script>
@endsection
