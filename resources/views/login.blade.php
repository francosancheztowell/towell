<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Towell</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/jsqr/dist/jsQR.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    .font-inter{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
  </style>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="images-base" content="{{ asset('images') }}">
</head>

<body class="bg-white h-screen overflow-hidden font-inter">
  <div class="h-screen flex overflow-hidden">
    <!-- Panel izquierdo - Branding -->
    <div class="hidden lg:flex lg:w-2/5 relative overflow-hidden bg-blue-600">
      <div class="relative z-10 p-12 h-full flex flex-col justify-center text-white">
        <div class="text-center mb-8">
          <div class="flex justify-center mb-10">
            <img src="images/fotos_usuarios/TOWELLIN.png" alt="Logo" class="w-40 h-40">
          </div>
          <div class="mt-20">
            <h1 class="text-white text-5xl font-bold leading-tight mb-6">Bienvenido</h1>
            <p class="text-xl text-white/90 leading-relaxed">
              Accede a tu cuenta de forma rápida y segura
            </p>
          </div>
        </div>
        <div class="mt-auto text-sm text-center text-white/70">
          <p>© 2025 Towell. Todos los derechos reservados.</p>
        </div>
      </div>
    </div>

    <!-- Panel derecho - Formulario -->
    <div class="w-full lg:w-3/5 flex flex-col items-center justify-center p-8 lg:p-12 overflow-hidden h-screen">
      <div class="text-center w-full flex-shrink-0 order-first">
        <img src="{{ asset('images/fondosTowell/logo.png') }}" class="h-20 mx-auto" alt="Logo_Towell">
      </div>

      <div class="w-full max-w-2xl bg-white rounded-2xl p-10 shadow-sm">
        <!-- Tabs de autenticación -->
        <div class="flex bg-slate-100 rounded-lg p-1 mb-8 gap-0">
          <button type="button" id="qr-tab"
                  class="flex-1 px-4 py-3 text-sm font-medium rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 text-slate-600 bg-transparent">
                  <svg viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor" aria-hidden="true">
                    <path d="M3 3h8v8H3V3zm2 2v4h4V5H5zM13 3h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zM13 13h3v3h-3v-3zm5 0h3v3h-3v-3zm-5 5h3v3h-3v-3zm5 3v-3h3v3h-3z"/>
                  </svg>

            Código QR
          </button>
          <button type="button" id="user-tab"
                  class="flex-1 px-4 py-3 text-sm font-medium rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 text-white bg-blue-600 shadow-sm">
            <svg fill="currentColor" viewBox="0 0 20 20" class="w-4 h-4">
              <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
            </svg>
            ID Usuario
          </button>
        </div>

        <!-- Formulario de ID Usuario -->
        <div id="user-form" class="block">
          <x-auth.login-form
            :errors="$errors ?? []"
          />
        </div>

        <!-- Formulario de QR -->
        <div id="qr-form" class="hidden">

          <button type="button" onclick="openQRScanner()"
                  class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-4 px-6 rounded-lg transition-colors duration-200 flex items-center justify-center text-lg">
                  <svg viewBox="0 0 24 24" class="w-6 h-6" fill="currentColor" aria-hidden="true">
                    <path d="M3 3h8v8H3V3zm2 2v4h4V5H5zM13 3h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zM13 13h3v3h-3v-3zm5 0h3v3h-3v-3zm-5 5h3v3h-3v-3zm5 3v-3h3v3h-3z"/>
                  </svg>

            Escanear Código QR
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de QR -->
  <div id="qr-modal" class="fixed inset-0 bg-black/90 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
      <div class="text-center">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Escáner QR</h3>
        <!-- Video container con efecto espejo -->
        <div class="relative mb-4">
          <video id="qr-video" autoplay class="w-full h-64 bg-gray-100 rounded-lg" style="transform: scaleX(-1);"></video>
          <canvas id="qr-canvas" class="hidden"></canvas>
        </div>
        <!-- Status message -->
        <div id="qr-status" class="text-sm text-gray-600 mb-4">Preparando cámara...</div>
        <!-- Buttons -->
        <div class="flex gap-3">
          <button onclick="closeQRScanner()"
                  class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.min.js"></script>

  <script>
    // Variables globales
    let currentAuthMode = 'user';
    let stream = null;
    let interval = null;

    // Inicialización
    document.addEventListener('DOMContentLoaded', function () {
      initializeTabs();
    });

    // Manejo de tabs
    function initializeTabs() {
      const qrTab = document.getElementById('qr-tab');
      const userTab = document.getElementById('user-tab');

      qrTab.addEventListener('click', () => switchToTab('qr'));
      userTab.addEventListener('click', () => switchToTab('user'));
    }

    function switchToTab(mode) {
      const qrTab  = document.getElementById('qr-tab');
      const userTab = document.getElementById('user-tab');
      const qrForm = document.getElementById('qr-form');
      const userForm = document.getElementById('user-form');

      currentAuthMode = mode;

      if (mode === 'qr') {
        qrTab.className  = 'flex-1 px-4 py-3 text-sm font-medium rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 text-white bg-blue-600 shadow-sm';
        userTab.className = 'flex-1 px-4 py-3 text-sm font-medium rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 text-slate-600 bg-transparent';
        qrForm.classList.remove('hidden');
        userForm.classList.add('hidden');

        // Abrir automáticamente el modal QR cuando se cambia a la pestaña de QR
        setTimeout(() => { openQRScanner(); }, 100);
      } else {
        userTab.className = 'flex-1 px-4 py-3 text-sm font-medium rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 text-white bg-blue-600 shadow-sm';
        qrTab.className   = 'flex-1 px-4 py-3 text-sm font-medium rounded-md cursor-pointer transition-all duration-200 flex items-center justify-center gap-2 text-slate-600 bg-transparent';
        userForm.classList.remove('hidden');
        qrForm.classList.add('hidden');

        // Cerrar el modal QR si está abierto cuando cambias a la pestaña de usuario
        closeQRScanner();
      }
    }

    // Función para abrir el escáner QR
    async function openQRScanner() {
      const modal = document.getElementById('qr-modal');
      const video = document.getElementById('qr-video');
      const status = document.getElementById('qr-status');

      // Si ya hay un stream activo, no hacer nada (evita duplicados)
      if (stream) return;

      modal.classList.remove('hidden');
      status.textContent = 'Preparando cámara...';

      try {
        // (Petición directa de cámara; se eliminó la verificación de HTTPS/localhost)
        // Verificar soporte de getUserMedia
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          status.innerHTML = 'Tu navegador no soporta la cámara<br><small>Usa Chrome, Firefox o Safari</small>';
          return;
        }

        // Solicitar acceso a la cámara frontal
        stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: 'user', // Cámara frontal
            width: { ideal: 640 },
            height: { ideal: 480 }
          }
        });

        video.srcObject = stream;
        status.textContent = 'Preparando cámara...';

        // Iniciar escaneo con mejor manejo de eventos
        video.onloadedmetadata = () => {
          video.play().then(() => {
            status.textContent = 'Apunta la cámara al código QR';
            // Esperar un poco para que la cámara se estabilice
            setTimeout(() => { startQRScanning(); }, 500);
          }).catch(error => {
            console.error('Error al reproducir video:', error);
            status.textContent = 'Error al iniciar la cámara';
          });
        };

        // Manejar errores del video
        video.onerror = (error) => {
          console.error('Error en el video:', error);
          status.textContent = 'Error en la cámara';
        };

      } catch (error) {
        console.error('Error al acceder a la cámara:', error);

        let errorMsg = 'No se pudo acceder a la cámara\n\n';
        if (error.name === 'NotAllowedError') {
          errorMsg += 'Permisos denegados. Por favor, permite el acceso a la cámara.';
        } else if (error.name === 'NotFoundError') {
          errorMsg += 'No se encontró ninguna cámara.';
        } else if (error.name === 'NotReadableError') {
          errorMsg += 'La cámara está siendo usada por otra aplicación.';
        } else {
          errorMsg += `Error: ${error.message}`;
        }
        status.innerHTML = errorMsg.replace(/\n/g, '<br>');
      }
    }

    // Función para iniciar el escaneo QR
    function startQRScanning() {
      const video = document.getElementById('qr-video');
      const canvas = document.getElementById('qr-canvas');
      const status = document.getElementById('qr-status');
      let isProcessing = false; // Evitar múltiples procesamientos simultáneos

      interval = setInterval(() => {
        if (video.readyState === video.HAVE_ENOUGH_DATA && !isProcessing) {
          isProcessing = true;

          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;

          const ctx = canvas.getContext('2d');

          // Voltear horizontalmente para cámara frontal (efecto espejo)
          ctx.translate(canvas.width, 0);
          ctx.scale(-1, 1);
          ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

          // Resetear transformación
          ctx.setTransform(1, 0, 0, 1, 0, 0);

          const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

          // Usar jsQR para detectar el código con mejores opciones
          if (typeof jsQR === 'function') {
            const code = jsQR(imageData.data, canvas.width, canvas.height, {
              inversionAttempts: "attemptBoth",
              greyScaleWeights: { red: 0.2126, green: 0.7152, blue: 0.0722 }
            });

            if (code && code.data && code.data.trim() !== '') {
              clearInterval(interval);
              authenticateWithQR(code.data.trim());
              return;
            }
          }

          isProcessing = false;
        }
      }, 150); // Intervalo para rendimiento
    }

    // Función para autenticar con QR detectado
    async function authenticateWithQR(qrData) {
      const status = document.getElementById('qr-status');

      // Validar que el QR no esté vacío
      if (!qrData || qrData.trim() === '') {
        status.textContent = 'Código QR vacío. Intenta de nuevo.';
        setTimeout(() => { status.textContent = 'Apunta la cámara al código QR'; }, 2000);
        return;
      }

      status.textContent = 'Verificando código...';

      try {
        const response = await fetch('/login-qr', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ numero_empleado: qrData.trim() })
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);

        const data = await response.json();

        if (data.success) {
          status.textContent = `¡Acceso exitoso! Bienvenido ${data.user || ''}`;
          // Cerrar el modal y redirigir después de un breve delay
          setTimeout(() => {
            closeQRScanner();
            window.location.href = '/produccionProceso';
          }, 1000);
        } else {
          status.textContent = `${data.message || 'Código QR inválido'}`;
          setTimeout(() => {
            status.textContent = 'Apunta la cámara al código QR';
            // Reiniciar el escaneo después del error
            startQRScanning();
          }, 3000);
        }
      } catch (error) {
        console.error('Error en la autenticación QR:', error);
        status.textContent = 'Error de conexión. Reintentando...';
        setTimeout(() => {
          status.textContent = 'Apunta la cámara al código QR';
          // Reiniciar el escaneo después del error
          startQRScanning();
        }, 3000);
      }
    }

    // Función para cerrar el escáner QR
    function closeQRScanner() {
      const modal = document.getElementById('qr-modal');
      const video = document.getElementById('qr-video');
      const status = document.getElementById('qr-status');

      modal.classList.add('hidden');

      // Detener stream de cámara
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
      }

      // Limpiar interval
      if (interval) {
        clearInterval(interval);
        interval = null;
      }

      // Limpiar video
      if (video) {
        video.srcObject = null;
        video.pause();
      }

      // Resetear status
      status.textContent = 'Preparando cámara...';
    }
  </script>

  <!-- Script para recarga de página -->
  <script>
    // Detecta si esta página fue accedida desde el historial (adelante o atrás)
    window.addEventListener('pageshow', function (event) {
      if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
        window.location.reload(); // Fuerza recarga completa
      }
    });
  </script>

</body>
</html>
