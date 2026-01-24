@props(['simple' => false])

<!-- Todas las librerías se cargan a través de Vite en app.js -->
@vite(['resources/js/app.js'])


@if(!$simple)
    <!-- Service Worker Cleanup -->
    <script>
        (function() {
            if (!('serviceWorker' in navigator)) return;
            async function cleanup() {
                try {
                    const regs = await navigator.serviceWorker.getRegistrations();
                    for (const r of regs) await r.unregister();
                    if ('caches' in window) {
                        for (const n of await caches.keys()) await caches.delete(n);
                    }
                    if (navigator.serviceWorker.controller) {
                        navigator.serviceWorker.controller.postMessage({ action: 'skipWaiting' });
                    }
                } catch (e) {}
            }
            cleanup();
            window.addEventListener('load', cleanup);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) cleanup();
            });
        })();
    </script>
@endif

@stack('styles')


















