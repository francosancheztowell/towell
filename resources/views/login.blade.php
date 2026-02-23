<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Accede al sistema de gestion de produccion y planeacion empresarial Towell.">
  <title>Login - Towell</title>

  <!-- Tailwind CSS compilado a través de Vite -->
  @vite(['resources/css/app.css'])

  <style>
    html, body { margin: 0; padding: 0; }
    @media (min-width: 1024px) {
      .login-panel-right {
        display: flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center !important;
        padding-top: 0 !important;
      }
      .login-panel-left-content {
        padding-top: 8rem !important;
      }
    }
  </style>
</head>

<body class="bg-white h-screen overflow-hidden p-0">
  <div class="h-screen flex overflow-hidden">
    <!-- Panel izquierdo - Branding -->
    <div class="hidden lg:flex lg:w-2/5 relative overflow-hidden bg-blue-600">
      <div class="relative z-10 p-12 h-full flex flex-col justify-between text-white">
        <div class="login-panel-left-content text-center flex-shrink-0">
          <div class="flex justify-center">
            <picture>
              <source srcset="{{ asset('images/fotos_usuarios/TOWELLIN.webp') }}" type="image/webp">
              <img src="{{ asset('images/fotos_usuarios/TOWELLIN.png') }}" alt="Logo" width="307" height="391" decoding="async" class="h-40 w-auto">
            </picture>
          </div>
          <div>
            <h1 class="text-white text-5xl font-bold leading-tight mb-6 mt-6">Bienvenido</h1>
            <p class="text-xl text-white/90 leading-relaxed">
              Accede a tu cuenta de forma rápida y segura
            </p>
          </div>
        </div>
        <div class="text-sm text-center text-white/70 flex-shrink-0">
          <p>© {{ date('Y') }} Towell. Todos los derechos reservados.</p>
        </div>
      </div>
    </div>

    <!-- Panel derecho - Formulario -->
    <div class="login-panel-right w-full lg:w-3/5 flex flex-col items-center justify-start pt-24 lg:pt-0 p-8 lg:p-12 overflow-y-auto lg:overflow-hidden h-screen">
      <div class="w-full max-w-2xl flex flex-col items-center flex-shrink-0">
        <div class="text-center w-full flex-shrink-0">
          <picture>
            <source srcset="{{ asset('images/fondosTowell/logo-sm.webp') }}" type="image/webp">
            <img src="{{ asset('images/fondosTowell/logo.png') }}" width="792" height="227" class="h-20 w-auto mx-auto" decoding="async" alt="Logo_Towell">
          </picture>
        </div>

        <div class="w-full bg-white  p-10 ">
          <x-auth.login-form />
        </div>
      </div>
    </div>
  </div>

  <!-- Script para recarga de página -->
  <script>
    // Detecta si esta página fue accedida desde el historial (adelante o atrás)
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) {
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
