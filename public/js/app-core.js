/**
 * Scripts principales de la aplicación
 */
(function () {
    "use strict";

    // ==============================
    // Constantes
    // ==============================
    const NAV_STACK_KEY = "nav_stack";
    const MAX_STACK_SIZE = 10;
    const HOME_PATH = "/produccionProceso";
    const HOME_PATHS = ["/produccionProceso"];
    const CLICK_DEBOUNCE = 500; // ms

    // ==============================
    // Utilidades de navegación (stack)
    // ==============================

    // Normaliza URL para evitar duplicados por trailing slashes o variaciones
    function normalizeUrl(url) {
        if (!url || url === "/") return url;
        // Eliminar trailing slashes (excepto root)
        return url.replace(/\/+$/, "");
    }

    // Obtiene solo el pathname (sin query strings) para comparar páginas
    function getBasePath(url) {
        if (!url) return url;
        return normalizeUrl(url.split("?")[0]);
    }

    const NavStack = {
        get() {
            try {
                const stack = sessionStorage.getItem(NAV_STACK_KEY);
                return stack ? JSON.parse(stack) : [];
            } catch (e) {
                return [];
            }
        },

        save(stack) {
            try {
                sessionStorage.setItem(NAV_STACK_KEY, JSON.stringify(stack));
            } catch (e) {
                console.warn("No se pudo guardar el stack de navegación");
            }
        },

        push(url) {
            const normalized = normalizeUrl(url);
            const basePath = getBasePath(normalized);
            const stack = this.get();

            // Evitar duplicados de la misma página (comparando solo pathname, ignorando query strings)
            // Esto evita que refreshes o cambios de estado agreguen la misma página múltiples veces
            if (stack.length > 0 && getBasePath(stack[stack.length - 1]) === basePath) {
                return;
            }

            stack.push(normalized);

            // Limitar tamaño del stack
            if (stack.length > MAX_STACK_SIZE) {
                stack.shift();
            }

            this.save(stack);
        },

        pop() {
            const stack = this.get();
            const currentBasePath = getBasePath(window.location.pathname);

            // Si no hay historial suficiente, regresar al home
            if (stack.length === 0) {
                return HOME_PATH;
            }

            // Buscar la primera página DIFERENTE a la actual (por pathname)
            // Esto evita volver a estados anteriores de la misma página
            let prevUrl = null;
            while (stack.length > 0) {
                const candidate = stack.pop();
                const candidateBasePath = getBasePath(candidate);

                // Si encontramos una página diferente, usarla
                if (candidateBasePath !== currentBasePath) {
                    prevUrl = candidate;
                    break;
                }
            }

            this.save(stack);
            return prevUrl || HOME_PATH;
        },

        clear() {
            sessionStorage.removeItem(NAV_STACK_KEY);
        }
    };

    // ==============================
    // Logout modal
    // ==============================
    function initLogout() {
        const btn = document.getElementById("logout-btn");
        if (!btn) return;

        btn.addEventListener("click", function (e) {
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
                if (res.isConfirmed) {
                    const form = document.getElementById("logout-form");
                    if (form) form.submit();
                }
            });
        });
    }

    // ==============================
    // Menú usuario compacto
    // ==============================
    function initUserMenu() {
        const btn = document.getElementById("btn-user-avatar");
        const modal = document.getElementById("user-modal");

        if (!btn || !modal) return;

        let open = false;

        const show = (e) => {
            if (e) e.stopPropagation();
            modal.classList.remove("opacity-0", "invisible", "scale-95");
            modal.classList.add("opacity-100", "visible", "scale-100");
            open = true;
        };

        const hide = () => {
            modal.classList.remove("opacity-100", "visible", "scale-100");
            modal.classList.add("opacity-0", "invisible", "scale-95");
            open = false;
        };

        btn.addEventListener("click", (e) => (open ? hide() : show(e)));

        document.addEventListener("click", (e) => {
            if (!open) return;
            const target = e.target;
            if (!modal.contains(target) && !btn.contains(target)) {
                hide();
            }
        });

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && open) {
                hide();
            }
        });
    }

    // ==============================
    // Navegación mejorada / botón atrás / debounce y stack
    // ==============================
    function initNavigation() {
        const path = window.location.pathname;
        const currentUrl = window.location.pathname + window.location.search;

        // Usar replaceState para evitar que refreshes creen entradas en el historial del navegador
        // Esto previene el problema de "volver a un estado anterior de la misma página"
        if (window.history && window.history.replaceState) {
            window.history.replaceState({ page: path }, "", currentUrl);
        }

        // Registrar página actual en el stack
        NavStack.push(currentUrl);

        // Marcador de navbar cargado (optimización)
        const prevNavbarPath = sessionStorage.getItem("lastNavbarPath");
        document.documentElement.setAttribute(
            "data-navbar-loaded",
            prevNavbarPath === path ? "true" : "false"
        );
        sessionStorage.setItem("lastNavbarPath", path);

        // Limpiar stack de navegación al ir a páginas principales
        if (HOME_PATHS.includes(path)) {
            NavStack.clear();
        }

        // Botón atrás - Lógica jerárquica basada en módulos
        const btnBack = document.getElementById("btn-back");
        if (btnBack && !btnBack.disabled) {
            btnBack.addEventListener("click", async (e) => {
                e.preventDefault();

                // Comportamiento custom si existe
                if (typeof window.volverAlIndice === "function") {
                    window.volverAlIndice();
                    return;
                }

                // Obtener ruta del módulo padre desde la API
                try {
                    const currentPath = window.location.pathname;
                    const response = await fetch(`/api/modulo-padre?ruta=${encodeURIComponent(currentPath)}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.success && data.rutaPadre) {
                            window.location.replace(data.rutaPadre);
                            return;
                        }
                    }
                } catch (error) {
                    console.warn('Error al obtener módulo padre, usando fallback:', error);
                }

                // Fallback: usar stack de navegación
                const prevUrl = NavStack.pop();
                window.location.replace(prevUrl);
            });
        }

        // Interceptar links para prevenir doble click
        let lastClickTime = 0;

        document.addEventListener("click", (e) => {
            const link = e.target.closest("a[href]");
            if (!link || link.target === "_blank") return;
            if (link.hostname !== location.hostname) return;

            const href = link.getAttribute("href");
            if (!href || href.startsWith("#") || href.startsWith("javascript:")) {
                return;
            }

            const now = Date.now();
            if (now - lastClickTime < CLICK_DEBOUNCE) {
                e.preventDefault();
                return;
            }

            lastClickTime = now;
            // La página se registra al cargar (en initNavigation), no aquí
        });
    }

    // ==============================
    // Persistencia UI simple y limpieza de caché
    // ==============================
    function initPageShowHandler() {
        window.addEventListener("pageshow", (event) => {
            // Si viene del back/forward cache del navegador, recargar
            if (event.persisted) {
                location.reload();
                return;
            }

            // Recarga forzada manual
            if (sessionStorage.getItem("forceReload")) {
                sessionStorage.removeItem("forceReload");
                location.reload();
            }
        });
    }

    // ==============================
    // Configuración base de Toastr
    // ==============================
    function initToastr() {
        if (typeof toastr === "undefined") return;

        toastr.options = {
            closeButton: true,
            debug: false,
            newestOnTop: true,
            progressBar: true,
            positionClass: "toast-top-right",
            preventDuplicates: false,
            showDuration: "300",
            hideDuration: "1000",
            timeOut: "5000",
            extendedTimeOut: "1000",
            showEasing: "swing",
            hideEasing: "linear",
            showMethod: "fadeIn",
            hideMethod: "fadeOut"
        };
    }

    // ==============================
    // Inicialización global
    // ==============================
    function initAppScripts() {
        initLogout();
        initUserMenu();
        initNavigation();
        initPageShowHandler();
        initToastr();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAppScripts);
    } else {
        initAppScripts();
    }
})();
