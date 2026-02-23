@extends('layouts.app')

@php
    $soloAtras = true;

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

@section('content')
    <div class="max-w-2xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-t-xl shadow-sm border-b-2 border-blue-500 p-6">
            <div class="text-center">
                <div class="mb-4">
                    @if (!empty($usuario->foto))
        @php
            $fotoUrl = getFotoUsuarioUrl($usuario->foto ?? null);
        @endphp
        @if($fotoUrl)
            <img src="{{ $fotoUrl }}" alt="Foto de {{ $usuario->nombre }}"
                class="h-24 w-24 mx-auto rounded-full object-cover border-4 border-blue-500 shadow-lg">
        @else
            <div class="h-24 w-24 mx-auto rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center border-4 border-blue-500 shadow-lg">
                <span class="text-white font-bold text-2xl">{{ iniciales($usuario->nombre ?? 'U') }}</span>
            </div>
        @endif
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
                    <div class="relative inline-block">
                        <div id="qrcode" class="p-4 bg-white border-4 border-gray-200 rounded-lg shadow-md"></div>
                        <!-- Imagen TOWELLIN en el centro del QR -->
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div class="bg-white p-2 rounded-lg shadow-lg">
                                <img src="{{ asset('images/fotos_usuarios/TOWELLIN.png') }}"
                                     alt="TOWELLIN Logo"
                                     class="w-20 h-20 object-contain">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Download Button -->
                <button onclick="downloadQR()"
                    class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors shadow-md mb-4">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar QR
                </button>

            </div>
        </div>

    </div>

    <!-- QRCode.js Library -->
    <!--<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>-->
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
            const qrContainer = document.querySelector('#qrcode');
            const canvas = qrContainer.querySelector('canvas');

            if (canvas) {
                // Crear un canvas más grande para incluir la imagen
                const downloadCanvas = document.createElement('canvas');
                const ctx = downloadCanvas.getContext('2d');

                // Dimensiones del canvas final
                const size = 300;
                downloadCanvas.width = size;
                downloadCanvas.height = size;

                // Dibujar el QR en el canvas
                ctx.drawImage(canvas, 0, 0, size, size);

                // Cargar y dibujar la imagen TOWELLIN en el centro
                const img = new Image();
                img.onload = function() {
                    const logoSize = 100;
                    const logoX = (size - logoSize) / 2;
                    const logoY = (size - logoSize) / 2;

                    // Fondo blanco para la imagen
                    ctx.fillStyle = 'white';
                    ctx.fillRect(logoX - 5, logoY - 5, logoSize + 10, logoSize + 10);

                    // Dibujar la imagen
                    ctx.drawImage(img, logoX, logoY, logoSize, logoSize);

                    // Descargar el resultado
                    const url = downloadCanvas.toDataURL('image/png');
                    const link = document.createElement('a');
                    link.download = 'QR_{{ $usuario->numero_empleado }}_{{ str_replace(' ', '_', $usuario->nombre) }}.png';
                    link.href = url;
                    link.click();
                };

                img.src = '{{ asset('images/fotos_usuarios/TOWELLIN.png') }}';
            }
        }
    </script>
@endsection




