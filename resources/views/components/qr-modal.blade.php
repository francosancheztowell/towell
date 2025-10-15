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
    class="fixed inset-0 w-screen h-screen bg-black bg-opacity-80 items-center justify-center z-50 hidden"
>
    <video id="qr-video" autoplay class="w-4/5 max-w-md h-auto border-4 border-white rounded-lg bg-black"></video>

    <div class="absolute inset-0 bg-black bg-opacity-50 flex flex-col items-center justify-center text-white text-2xl z-10">
        <div id="qr-message" class="mb-5">{{ $title }}</div>
        <div class="mt-5 border-3 border-white w-50 h-50 rounded-lg"></div>

        <button
            id="cerrar-qr"
            class="absolute top-3 right-3 z-20 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors"
            onclick="closeQRModal('{{ $id }}')"
        >
            Cerrar QR
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
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });

            if (this.video) {
                this.video.srcObject = this.stream;
                this.modal.classList.remove('hidden');
                this.modal.classList.add('flex');
                this.video.play();

                this.interval = setInterval(() => {
                    if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
                        this.scanQR();
                    }
                }, 100);
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
        context.drawImage(this.video, 0, 0, canvas.width, canvas.height);

        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);

        // Verificar si jsQR está disponible
        if (typeof jsQR === 'function') {
            const qrCode = jsQR(imageData.data, canvas.width, canvas.height);

            if (qrCode) {
                this.stop();
                this.authenticateWithQR(qrCode.data);
            }
        }
    }

    async authenticateWithQR(qrData) {
        try {
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
                window.location.href = '/produccionProceso';
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            console.error('Error en la autenticación QR:', error);
            alert('Error en la autenticación. Inténtalo de nuevo.');
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
    if (!window.qrScanners[modalId]) {
        window.qrScanners[modalId] = new QRScanner(modalId);
    }
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
