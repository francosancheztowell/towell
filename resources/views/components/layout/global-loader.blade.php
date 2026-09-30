{{--
    Loader global único (DS-07). Lo muestran app-core.js al navegar y las vistas, siempre con
    window.loader.show()/hide() (resources/js/componentes/loader.ts).
    El giro usa `animate-loader` (keyframes `loader-giro` en app.css): el `@keyframes spin`
    que vivía aquí pisaba el de Tailwind y desplazaba todo `animate-spin` de la app.
--}}
<div id="globalLoader" class="fixed inset-0 z-50 bg-black/40 hidden" role="status" aria-live="polite">
    <div class="absolute inset-0 flex items-center justify-center">
        <div class="size-[50px] rounded-full border-4 border-white border-t-blue-600 animate-loader"></div>
        <span class="sr-only">Cargando…</span>
    </div>
</div>
