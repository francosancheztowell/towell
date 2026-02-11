@props(['simple' => false])

<!-- Todas las librerías se cargan a través de Vite en app.js -->
@vite(['resources/js/app.js'])


@if(!$simple && config('app.service_worker_cleanup', false))
    <!-- Service Worker Cleanup -->
    <script>
        (function() {
            if (!('serviceWorker' in navigator)) return;
            const markerKey = 'towell_sw_cleanup_done';

            if (sessionStorage.getItem(markerKey) === '1') return;
            sessionStorage.setItem(markerKey, '1');

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
        })();
    </script>
@endif

@stack('styles')

















