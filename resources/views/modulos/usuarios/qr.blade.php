@extends('layouts.app')

@php
    $soloAtras = true;
@endphp

@section('content')
    <div class="max-w-2xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-t-xl shadow-sm border-b-2 border-blue-500 p-6">
            <div class="text-center">
                <div class="mb-4">
                    @if (!empty($usuario->foto))
                        <img src="{{ asset('storage/usuarios/' . $usuario->foto) }}" alt="Foto de {{ $usuario->nombre }}"
                            class="h-24 w-24 mx-auto rounded-full object-cover border-4 border-blue-500 shadow-lg">
                    @else
                        <div class="h-24 w-24 mx-auto rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center border-4 border-blue-500 shadow-lg">
                            <span class="text-white font-bold text-2xl">
                                {{ strtoupper(substr($usuario->nombre, 0, 2)) }}
                            </span>
                        </div>
                    @endif
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-1">{{ $usuario->nombre }}</h1>
                <p class="text-gray-600">Empleado #{{ $usuario->numero_empleado }}</p>
                <p class="text-sm text-gray-500 mt-2">{{ $usuario->area ?? 'Sin área asignada' }} - {{ $usuario->puesto ?? 'Sin puesto' }}</p>
            </div>
        </div>

        <!-- QR Code Container -->
        <div class="bg-white rounded-b-xl shadow-sm p-8">
            <div class="text-center">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Código QR de Acceso</h2>
                <p class="text-sm text-gray-600 mb-6">Escanea este código para iniciar sesión rápidamente</p>

                <!-- QR Code -->
                <div class="flex justify-center mb-6">
                    <div id="qrcode" class="inline-block p-4 bg-white border-4 border-gray-200 rounded-lg shadow-md"></div>
                </div>

                <!-- Download Button -->
                <button onclick="downloadQR()"
                    class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors shadow-md mb-4">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar QR
                </button>

                <!-- Back Button -->
                <div class="mt-4">
                    <a href="{{ route('usuarios.select') }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Volver a Lista de Usuarios
                    </a>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-900 mb-2 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Instrucciones de uso
            </h3>
            <ul class="text-sm text-blue-900 space-y-1 ml-7">
                <li>1. Abre la página de inicio de sesión</li>
                <li>2. Selecciona la opción "Código QR"</li>
                <li>3. Escanea este código con la cámara</li>
                <li>4. Accederás automáticamente al sistema</li>
            </ul>
        </div>
    </div>

    <!-- QRCode.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // Generar QR Code
        const qrcode = new QRCode(document.getElementById("qrcode"), {
            text: "{{ $usuario->numero_empleado }}",
            width: 256,
            height: 256,
            colorDark: "#1e40af",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        // Función para descargar el QR
        function downloadQR() {
            const canvas = document.querySelector('#qrcode canvas');
            if (canvas) {
                const url = canvas.toDataURL('image/png');
                const link = document.createElement('a');
                link.download = 'QR_{{ $usuario->numero_empleado }}_{{ str_replace(' ', '_', $usuario->nombre) }}.png';
                link.href = url;
                link.click();
            }
        }
    </script>
@endsection



