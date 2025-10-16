@extends('layouts.app')

@php
    $soloAtras = true;
@endphp

@section('content')
    @php
        // Helper simple para iniciales
        function iniciales($nombre)
        {
            $partes = preg_split('/\s+/', trim($nombre));
            $ini = '';
            foreach ($partes as $p) {
                if ($p !== '') {
                    $ini .= mb_strtoupper(mb_substr($p, 0, 1));
                }
                if (mb_strlen($ini) >= 2) {
                    break;
                }
            }
            return mb_substr($ini, 0, 2);
        }
    @endphp

    <!-- Header con componente reutilizable -->
    <x-page-header
        title="Lista de Usuarios"
        badge="{{ count($usuarios) }} usuarios"
        containerClass="max-w-full mx-auto px-2 mt-8"
        headerClass="bg-white rounded-t-xl shadow-sm border-gray-200"
    >
        <x-slot name="actions">
            <a href="{{ route('usuarios.create') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Nuevo Usuario
            </a>
        </x-slot>
    </x-page-header>

        {{-- Alertas SweetAlert si las usas en tu layout --}}
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

    <!-- Lista de usuarios -->
    <div class="bg-white rounded-b-xl shadow-sm border-t-0">
            <div class="overflow-hidden">
                @forelse ($usuarios as $u)
                    <div class="px-3 sm:px-4 py-4 border-b border-gray-100 hover:bg-gray-50 transition-colors {{ $loop->last ? 'rounded-b-xl' : '' }}">
                        <div class="flex items-start gap-3 lg:gap-4">
                            <!-- Avatar -->
                            <div class="flex-shrink-0">
                                @if (!empty($u->foto))
                                    <img src="{{ $u->foto }}" alt="Foto de {{ $u->nombre }}"
                                        class="h-12 w-12 rounded-full object-cover border-2 border-gray-200">
                                @else
                                    <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center border-2 border-gray-200">
                                        <span class="text-white font-semibold text-sm">
                                            {{ iniciales($u->nombre) }}
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <!-- Información del usuario -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <h3 class="text-base font-semibold text-gray-900">{{ $u->nombre }}</h3>
                                            <span class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full">
                                            #{{ $u->numero_empleado }}
                                        </span>
                                    </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 gap-3 text-sm text-gray-600">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                </svg>
                                                <span class="font-medium">Área:</span>
                                                <span>{{ $u->area ?? 'No asignada' }}</span>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="font-medium">Turno:</span>
                                                <span>{{ $u->turno ?? 'No asignado' }}</span>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                </svg>
                                                <span class="font-medium">Teléfono:</span>
                                                <span>{{ $u->telefono ?? 'No registrado' }}</span>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                </svg>
                                                <span class="font-medium">Mensajes:</span>
                                                @if ($u->enviarMensaje)
                                                    <span class="px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full">
                                                        Activo
                                                    </span>
                                                @else
                                                    <span class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-full">
                                                        Inactivo
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Botones de acción -->
                                    <div class="flex items-center gap-2 ml-2 lg:ml-4 flex-shrink-0">
                                        <a href="{{ route('usuarios.qr', $u->idusuario) }}"
                                           class="inline-flex items-center px-3 py-2 text-sm font-medium text-purple-700 bg-purple-100 hover:bg-purple-200 rounded-lg transition-colors"
                                           title="Ver código QR">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M3 3h7v7H3V3zm9 0h7v7h-7V3zm-9 9h7v7H3v-7zm15 0h3v3h-3v-3zm-3-9h3v3h-3V3zm3 6h3v3h-3V9zm-9 6h3v3h-3v-3zm6 0h3v3h-3v-3zm-3 0h3v3h-3v-3z"/>
                                            </svg>
                                            QR
                                        </a>

                                        <a href="{{ route('usuarios.edit', $u->idusuario) }}"
                                           class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-700 bg-blue-100 hover:bg-blue-200 rounded-lg transition-colors">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Editar
                                        </a>

                                        <form action="{{ route('usuarios.destroy', $u->idusuario) }}" method="POST"
                                              onsubmit="return confirmarEliminacion(event)" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-lg transition-colors">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-3 sm:px-4 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No hay usuarios</h3>
                        <p class="mt-1 text-sm text-gray-500">Comienza creando un nuevo usuario.</p>
                        <div class="mt-6">
                            <a href="{{ route('usuarios.create') }}"
                               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
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
                    text: 'Esta acción no se puede deshacer.',
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
                return confirm('¿Eliminar usuario? Esta acción no se puede deshacer.');
            }
        }
    </script>
@endsection
