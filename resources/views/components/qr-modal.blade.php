{{--
    Componente: QR Modal

    Descripción:
        Modal para escaneo de códigos QR con cámara web.
        Incluye funcionalidades de escaneo, detección automática y autenticación.

    Props:
        @param string $id - ID único del modal (default: 'qr-video-container')
        @param string $title - Título del modal (default: 'Escanea tu código...')
        @param bool $autoStart - Si debe iniciar automáticamente en móviles (default: true)

    Uso:
        <x-qr-modal id="my-qr-modal" title="Escanear QR" />

        <!-- Botón para activar -->
        <button onclick="openQRModal('my-qr-modal')">Escanear QR</button>
--}}

@props([
    'id' => 'qr-video-container',
    'title' => 'Escanea tu código...',
    'autoStart' => true
])

<!-- Modal de QR -->
<div
    id="{{ $id }}"
    class="fixed inset-0 w-screen h-screen bg-black bg-opacity-90 items-center justify-center z-50 hidden"
>
    <!-- Contenedor principal -->
    <div class="relative w-full max-w-md mx-4">
        <!-- Video de la cámara con efecto espejo -->
        <video id="qr-video" autoplay class="w-full h-auto border-4 border-white rounded-lg bg-black shadow-2xl" style="transform: scaleX(-1);"></video>

        <!-- Overlay con guías de escaneo -->
        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
            <!-- Marco de escaneo -->
            <div class="relative">
                <!-- Marco exterior -->
                <div class="w-64 h-64 border-2 border-white rounded-lg relative">
                    <!-- Esquinas decorativas -->
                    <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-blue-400 rounded-tl-lg"></div>
                    <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-blue-400 rounded-tr-lg"></div>
                    <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-blue-400 rounded-bl-lg"></div>
                    <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-blue-400 rounded-br-lg"></div>
                </div>
            </div>
        </div>

        <!-- Información y controles -->
        <div class="absolute inset-0 flex flex-col items-center justify-end pb-8 pointer-events-none">
            <!-- Mensaje de estado -->
            <div id="qr-message" class="text-white text-lg font-medium mb-4 text-center bg-black bg-opacity-60 px-4 py-2 rounded-lg">
                {{ $title }}
            </div>

            <!-- Instrucciones -->
            <div class="text-white text-sm text-center bg-black bg-opacity-60 px-4 py-2 rounded-lg mb-4">
                Apunta la cámara al código QR
            </div>
        </div>

        <!-- Botón de cerrar -->
        <button
            id="cerrar-qr"
            class="absolute top-4 right-4 z-20 bg-red-600 hover:bg-red-700 text-white p-3 rounded-full shadow-lg transition-colors pointer-events-auto"
            onclick="closeQRModal('{{ $id }}')"
            title="Cerrar escáner"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>

<script>
class QRScanner {
    constructor(modalId) {
        this.modalId = modalId;
        this.stream = null;
        this.interval = null;
        this.modal = document.getElementById(modalId);
        this.video = this.modal?.querySelector('#qr-video');
        this.message = this.modal?.querySelector('#qr-message');
    }

    async start() {
        console.log('QRScanner.start() llamado');
        console.log('Modal:', this.modal);
        console.log('Video:', this.video);

        try {
            console.log('Solicitando acceso a la cámara...');

            // Forzar SOLO cámara frontal
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user', // Cámara frontal obligatoria
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                }
            });
            console.log('Cámara frontal obtenida');

            if (this.video) {
                console.log('Configurando video...');
                this.video.srcObject = this.stream;
                this.modal.classList.remove('hidden');
                this.modal.classList.add('flex');
                await this.video.play();
                console.log('Video iniciado');

                this.interval = setInterval(() => {
                    if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
                        this.scanQR();
                    }
                }, 100);
                console.log('Interval de escaneo iniciado');
            } else {
                console.error('Elemento video no encontrado');
            }
        } catch (error) {
            console.error('Error al acceder a la cámara:', error);
            alert('No se pudo acceder a la cámara. Por favor, verifica los permisos.');
        }
    }

    scanQR() {
        if (!this.video) return;

        const canvas = document.createElement('canvas');
        canvas.width = this.video.videoWidth;
        canvas.height = this.video.videoHeight;
        const context = canvas.getContext('2d');

        // Voltear horizontalmente para cámara frontal (efecto espejo)
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
        context.drawImage(this.video, 0, 0, canvas.width, canvas.height);

        // Resetear transformación
        context.setTransform(1, 0, 0, 1, 0, 0);

        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);

        // Verificar si jsQR está disponible
        if (typeof jsQR === 'function') {
            const qrCode = jsQR(imageData.data, canvas.width, canvas.height, {
                inversionAttempts: "dontInvert",
            });

            if (qrCode) {
                this.stop();
                this.authenticateWithQR(qrCode.data);
            }
        }
    }

    async authenticateWithQR(qrData) {
        try {
            // Actualizar mensaje
            if (this.message) {
                this.message.textContent = 'Verificando código...';
            }

            const response = await fetch('/login-qr', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ numero_empleado: qrData })
            });

            const data = await response.json();

            if (data.success) {
                if (this.message) {
                    this.message.textContent = '¡Acceso exitoso! Redirigiendo...';
                }
                setTimeout(() => {
                    window.location.href = '/produccionProceso';
                }, 1000);
            } else {
                if (this.message) {
                    this.message.textContent = 'Error: ' + (data.message || 'Código QR inválido');
                }
                // Reiniciar escaneo después de 3 segundos
                setTimeout(() => {
                    this.start();
                }, 3000);
            }
        } catch (error) {
            console.error('Error en la autenticación QR:', error);
            if (this.message) {
                this.message.textContent = 'Error de conexión. Reintentando...';
            }
            // Reiniciar escaneo después de 3 segundos
            setTimeout(() => {
                this.start();
            }, 3000);
        }
    }

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }

        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }

        if (this.modal) {
            this.modal.classList.add('hidden');
            this.modal.classList.remove('flex');
        }
    }
}

// Instancias globales de escáneres QR
window.qrScanners = window.qrScanners || {};

function openQRModal(modalId) {
    console.log('openQRModal llamado con:', modalId);
    console.log('jsQR disponible:', typeof jsQR);

    if (!window.qrScanners[modalId]) {
        console.log('Creando nuevo QRScanner...');
        window.qrScanners[modalId] = new QRScanner(modalId);
    }

    console.log('Iniciando scanner...');
    window.qrScanners[modalId].start();
}

function closeQRModal(modalId) {
    if (window.qrScanners[modalId]) {
        window.qrScanners[modalId].stop();
    }
}

// Auto-iniciar QR en dispositivos móviles
@if($autoStart)
document.addEventListener('DOMContentLoaded', function() {
    const isMobile = /Android|iPhone|iPad|iPod|Windows Phone|webOS/i.test(navigator.userAgent);

    if (isMobile) {
        setTimeout(() => {
            const activeTab = document.querySelector('.tabs-container')?.dataset.active;
            if (activeTab === 'qr') {
                openQRModal('{{ $id }}');
            }
        }, 1000);
    }
});
@endif
</script>
