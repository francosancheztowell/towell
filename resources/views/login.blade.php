<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Towell</title>

  <!-- Tailwind CSS compilado a través de Vite -->
  @vite(['resources/css/app.css'])
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    .font-inter{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
    html, body { margin: 0; padding: 0; }
    @media (min-width: 1024px) {
      .login-panel-right {
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center !important;
        padding-top: 0 !important;
      }
    }
  </style>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="images-base" content="{{ asset('images') }}">
</head>

<body class="bg-white h-screen overflow-hidden font-inter  p-0">
  <div class="h-screen flex overflow-hidden">
    <!-- Panel izquierdo - Branding -->
    <div class="hidden lg:flex lg:w-2/5 relative overflow-hidden bg-blue-600">
      <div class="relative z-10 p-12 mt-16 h-full flex flex-col justify-center text-white">
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
          <p>© {{ date('Y') }} Towell. Todos los derechos reservados.</p>
        </div>
      </div>
    </div>

    <!-- Panel derecho - Formulario -->
    <div class="login-panel-right w-full lg:w-3/5 flex flex-col items-center justify-start pt-16 lg:pt-0 p-8 lg:p-12 overflow-y-auto lg:overflow-hidden h-screen">
      <div class="w-full max-w-2xl flex flex-col items-center flex-shrink-0">
        <div class="text-center w-full flex-shrink-0">
          <img src="{{ asset('images/fondosTowell/logo.png') }}" class="h-20 mx-auto" alt="Logo_Towell">
        </div>

        <div class="w-full bg-white  p-10 ">
          <x-auth.login-form
            :errors="$errors ?? []"
          />
        </div>
      </div>
    </div>
  </div>

  <!-- Script para recarga de página -->
  <script>
    // Detecta si esta página fue accedida desde el historial (adelante o atrás)
    window.addEventListener('pageshow', function (event) {
      if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
        window.location.reload(); // Fuerza recarga completa
      }
    });
  </script>
  <!-- En tablet/móvil: al enfocar un input, bajar el scroll para que se vea el botón Iniciar sesión -->
  <script>
    (function() {
      var form = document.getElementById('loginForm');
      if (!form) return;
      var submitBtn = form.querySelector('button[type="submit"]');
      if (!submitBtn) return;

      function scrollButtonIntoView() {
        setTimeout(function() {
          submitBtn.scrollIntoView({ behavior: 'smooth', block: 'end', inline: 'nearest' });
        }, 400);
      }

      form.querySelectorAll('input').forEach(function(input) {
        input.addEventListener('focus', scrollButtonIntoView);
      });
    })();
  </script>

</body>
</html>
