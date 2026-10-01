/**
 * Scripts principales de la aplicación
 */
(function () {
    "use strict";

    // ==============================
    // Constantes
    // ==============================
    const CLICK_DEBOUNCE = 500; // ms, por elemento
    const LOADER_DELAY = 150; // ms antes de mostrar el loader: evita parpadeo en navegaciones instantáneas

    // ==============================
    // Navegación: loader, debounce y botón atrás
    // ==============================
    // El botón atrás es un <a href> con la ruta del padre ya resuelta en el
    // servidor (ver navbar/sections/left.blade.php). Aquí solo se intercepta
    // cuando la página define un comportamiento propio.
    function initNavigation() {
        // Loader único (HANDOFF 16 A3): window.loader (componentes/loader.ts) tiene un solo
        // temporizador, así un hide() de una vista cancela también el show diferido de aquí.
        const mostrarLoader = () => window.loader?.show(LOADER_DELAY);
        const ocultarLoader = () => window.loader?.hide();

        document.addEventListener("click", (e) => {
            const link = e.target.closest("a[href]");
            if (!link || link.target === "_blank") return;
            if (link.hostname !== location.hostname) return;

            const href = link.getAttribute("href");
            if (!href || href.startsWith("#") || href.startsWith("javascript:")) {
                return;
            }

            // Comportamiento propio del botón atrás, si la página lo define
            if (link.id === "btn-back" && typeof window.volverAlIndice === "function") {
                e.preventDefault();
                window.volverAlIndice();
                return;
            }

            // Debounce por elemento: dos clicks seguidos al MISMO link no
            // disparan dos navegaciones, pero A y luego B sí funcionan.
            const now = Date.now();
            const ultimoClick = Number(link.dataset.lastClick || 0);
            if (now - ultimoClick < CLICK_DEBOUNCE) {
                e.preventDefault();
                return;
            }
            link.dataset.lastClick = String(now);

            mostrarLoader();
        });

        // Si la navegación no ocurre (descarga, target raro, vuelta por bfcache),
        // no dejar el loader colgado.
        window.addEventListener("pageshow", ocultarLoader);
        document.addEventListener("livewire:navigated", ocultarLoader);
    }

    // ==============================
    // Inicialización global
    // ==============================
    function initAppScripts() {
        initNavigation();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAppScripts);
    } else {
        initAppScripts();
    }
})();
