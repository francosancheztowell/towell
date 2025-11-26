/**
 * Scripts principales de la aplicación
 */

// Logout modal
(function () {
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
            cancelButtonText: "Cancelar",
        }).then((res) => {
            if (res.isConfirmed)
                document.getElementById("logout-form").submit();
        });
    });
})();

// Sistema de navegación mejorado
(function () {
    const NAV_STACK_KEY = "nav_stack";
    const MAX_STACK_SIZE = 10;
    const homePath = "/produccionProceso";

    // Obtener stack de navegación
    function getNavStack() {
        try {
            const stack = sessionStorage.getItem(NAV_STACK_KEY);
            return stack ? JSON.parse(stack) : [];
        } catch (e) {
            return [];
        }
    }

    // Guardar stack de navegación
    function saveNavStack(stack) {
        try {
            sessionStorage.setItem(NAV_STACK_KEY, JSON.stringify(stack));
        } catch (e) {
            console.warn("No se pudo guardar el stack de navegación");
        }
    }

    // Agregar página al stack
    function pushToStack(url) {
        let stack = getNavStack();
        // Evitar duplicados consecutivos
        if (stack.length > 0 && stack[stack.length - 1] === url) {
            return;
        }
        stack.push(url);
        // Limitar tamaño del stack
        if (stack.length > MAX_STACK_SIZE) {
            stack.shift();
        }
        saveNavStack(stack);
    }

    // Obtener página anterior del stack
    function popFromStack() {
        let stack = getNavStack();
        if (stack.length <= 1) {
            return homePath;
        }
        // Remover página actual
        stack.pop();
        // Obtener página anterior
        const prevUrl = stack.pop() || homePath;
        saveNavStack(stack);
        return prevUrl;
    }

    // Registrar página actual al cargar
    const currentUrl = window.location.pathname + window.location.search;
    pushToStack(currentUrl);

    // Botón atrás
    document.addEventListener("DOMContentLoaded", function () {
        const btnBack = document.getElementById("btn-back");
        if (btnBack && !btnBack.disabled) {
            btnBack.addEventListener("click", function (e) {
                e.preventDefault();

                // Check if page has custom back behavior
                if (typeof window.volverAlIndice === "function") {
                    window.volverAlIndice();
                    return;
                }

                // Usar stack de navegación para volver
                const prevUrl = popFromStack();
                window.location.href = prevUrl;
            });
        }
    });

    // Interceptar navegación de links para actualizar stack
    document.addEventListener("click", function (e) {
        const link = e.target.closest("a[href]");
        if (!link || link.target === "_blank") return;
        if (link.hostname !== location.hostname) return;

        const href = link.getAttribute("href");
        if (href && !href.startsWith("#") && !href.startsWith("javascript:")) {
            pushToStack(href);
        }
    });
})();

// Menú usuario compacto
(function () {
    const btn = document.getElementById("btn-user-avatar");
    const modal = document.getElementById("user-modal");
    let open = false;

    function show(e) {
        e && e.stopPropagation();
        modal.classList.remove("opacity-0", "invisible", "scale-95");
        modal.classList.add("opacity-100", "visible", "scale-100");
        open = true;
    }

    function hide() {
        modal.classList.remove("opacity-100", "visible", "scale-100");
        modal.classList.add("opacity-0", "invisible", "scale-95");
        open = false;
    }

    if (btn && modal) {
        btn.addEventListener("click", (e) => (open ? hide() : show(e)));
        document.addEventListener("click", (e) => {
            if (open && !modal.contains(e.target) && !btn.contains(e.target))
                hide();
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && open) hide();
        });
    }
})();

// Persistencia UI simple y limpieza de caché
window.addEventListener("pageshow", function (event) {
    // Detectar navegación con botón atrás del navegador
    if (event.persisted) {
        // Página cargada desde caché - recargar para evitar estado inconsistente
        location.reload();
    }

    if (sessionStorage.getItem("forceReload")) {
        sessionStorage.removeItem("forceReload");
        location.reload();
    }
});

// Toastr base
if (typeof toastr !== "undefined") {
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
        hideMethod: "fadeOut",
    };
}

// Optimización navegación y prevención de duplicados
(function () {
    const path = location.pathname;
    const prev = sessionStorage.getItem("lastNavbarPath");

    document.documentElement.setAttribute(
        "data-navbar-loaded",
        prev === path ? "true" : "false"
    );
    sessionStorage.setItem("lastNavbarPath", path);

    // Prevenir navegación duplicada
    let lastClickTime = 0;
    const CLICK_DEBOUNCE = 500; // ms

    document.addEventListener("click", (e) => {
        const link = e.target.closest("a[href]");
        if (!link || link.target === "_blank") return;
        if (link.hostname !== location.hostname) return;

        const now = Date.now();
        if (now - lastClickTime < CLICK_DEBOUNCE) {
            e.preventDefault();
            return false;
        }
        lastClickTime = now;
    });

    // Limpiar stack de navegación al ir a página principal
    if (path === "/produccionProceso" || path === "/produccion") {
        sessionStorage.removeItem("nav_stack");
    }
})();
