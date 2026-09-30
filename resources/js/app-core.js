/**
 * Scripts principales de la aplicación
 */
import "./programa-tejido/modal-cache-bootstrap.js";

(function () {
    "use strict";

    // ==============================
    // Constantes
    // ==============================
    const CLICK_DEBOUNCE = 500; // ms, por elemento
    const LOADER_DELAY = 150; // ms antes de mostrar el loader: evita parpadeo en navegaciones instantáneas

    // ==============================
    // Logout modal
    // ==============================
    // Delegado en document: wire:navigate reemplaza el <body>, asi que un
    // listener atado al boton se pierde en la primera transicion.
    function initLogout() {
        document.addEventListener("click", function (e) {
            if (!e.target.closest("#logout-btn")) return;
            e.preventDefault();

            Swal.fire({
                title: "¿Confirma cerrar sesión?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, salir",
                cancelButtonText: "Cancelar"
            }).then((res) => {
                if (!res.isConfirmed) return;
                const logoutForm = document.getElementById("logout-form");
                if (!logoutForm) return;
                if (typeof logoutForm.requestSubmit === "function") {
                    logoutForm.requestSubmit();
                } else {
                    logoutForm.submit();
                }
            });
        });
    }

    // ==============================
    // Menú usuario compacto
    // ==============================
    function initUserMenu() {
        const estaAbierto = (modal) => modal.classList.contains("opacity-100");

        const mostrar = (modal) => {
            modal.classList.remove("opacity-0", "invisible", "scale-95");
            modal.classList.add("opacity-100", "visible", "scale-100");
        };

        const ocultar = (modal) => {
            modal.classList.remove("opacity-100", "visible", "scale-100");
            modal.classList.add("opacity-0", "invisible", "scale-95");
        };

        document.addEventListener("click", (e) => {
            const modal = document.getElementById("user-modal");
            if (!modal) return;

            const btn = e.target.closest("#btn-user-avatar");
            if (btn) {
                e.stopPropagation();
                estaAbierto(modal) ? ocultar(modal) : mostrar(modal);
                return;
            }

            if (estaAbierto(modal) && !modal.contains(e.target)) {
                ocultar(modal);
            }
        });

        document.addEventListener("keydown", (e) => {
            const modal = document.getElementById("user-modal");
            if (e.key === "Escape" && modal && estaAbierto(modal)) {
                ocultar(modal);
            }
        });
    }

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
        initLogout();
        initUserMenu();
        initNavigation();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAppScripts);
    } else {
        initAppScripts();
    }
})();
