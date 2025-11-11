@props(['simple' => false])

@if(!$simple)
    <!-- Estilos PWA y optimización -->
    <style>
        img[loading="lazy"] {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .module-grid img {
            will-change: transform;
            backface-visibility: hidden;
        }

        /* PWA: Safe area insets para notches en iOS y ocultar barra Chrome */
        html {
            height: 100%;
            height: -webkit-fill-available;
            overflow: hidden;
            overflow-x: hidden;
            overflow-y: hidden;
            position: fixed;
            width: 100%;
        }

        body {
            min-height: 100%;
            min-height: -webkit-fill-available;
            max-height: 100vh;
            max-height: -webkit-fill-available;
            overflow: hidden;
            overflow-x: hidden;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
        }

        :root {
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
        }

        @media all and (display-mode: fullscreen),
               all and (display-mode: standalone) {
            html, body {
                height: 100vh;
                height: -webkit-fill-available;
                overflow: hidden;
                overflow-x: hidden;
                overflow-y: hidden;
                position: fixed;
                width: 100%;
            }
        }

        @media (max-width: 1024px) {
            html, body {
                overflow: hidden !important;
                overflow-x: hidden !important;
                overflow-y: hidden !important;
                position: fixed;
                width: 100%;
                height: 100vh;
                height: -webkit-fill-available;
            }
        }
    </style>
@else
    <!-- Estilos para layout simple -->
    <style>
        body {
            background: linear-gradient(135deg, #099ff6, #c2e7ff, #0857be);
            background-size: 300% 300%;
            animation: gradientAnimation 5s ease infinite;
            position: relative;
        }

        @keyframes gradientAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
    </style>
@endif

<!-- Estilos comunes de animaciones -->
<style>
    @keyframes ripple {
        0% { width: 0; height: 0; }
        100% { width: 300px; height: 300px; }
    }
    .ripple-effect:active::before {
        animation: ripple .6s ease-out;
    }
    @keyframes spin360 {
        to { transform: rotate(360deg); }
    }
    .spin-1s {
        animation: spin360 .9s linear 1;
        transform-origin: 50% 50%;
    }
    .animate-fade-in {
        animation: fadeIn .18s ease-out both;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>









