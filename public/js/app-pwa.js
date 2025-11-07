/**
 * PWA bootstrap: SW, instalación y fullscreen sin hacks de scroll
 */

// ------------------------------
// Service Worker Registration
// ------------------------------
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(reg => console.log('[PWA] SW ok:', reg.scope))
            .catch(err => console.warn('[PWA] SW error:', err));
    });
}

// ------------------------------
// Helpers de modo standalone
// ------------------------------
function isStandaloneMode() {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true // iOS
    );
}

// ------------------------------
// Fullscreen helpers
// ------------------------------
async function enableFullscreen() {
    try {
        // iOS Safari no soporta Fullscreen API en PWA
        const el = document.documentElement;
        if (!document.fullscreenElement && el.requestFullscreen) {
            await el.requestFullscreen();
            console.log('[PWA] Fullscreen activado');
        }
    } catch (e) {
        console.warn('[PWA] No se pudo entrar a fullscreen:', e);
    }
}

async function exitFullscreen() {
    try {
        if (document.fullscreenElement && document.exitFullscreen) {
            await document.exitFullscreen();
            console.log('[PWA] Fullscreen desactivado');
        }
    } catch (e) {
        console.warn('[PWA] No se pudo salir de fullscreen:', e);
    }
}

// Intento automático de fullscreen en el PRIMER gesto del usuario (solo modo PWA)
// No fuerza nada solo cuando la app está instalada/standalone.
(function attachOneShotGestureToEnterFullscreen() {
    if (!isStandaloneMode()) return;
    if (!document.documentElement.requestFullscreen) return; // navegador no soportado

    let used = false;
    const handler = () => {
        if (used) return;
        used = true;
        enableFullscreen().finally(() => {
            document.removeEventListener('click', handler, true);
            document.removeEventListener('touchend', handler, true);
        });
    };

    document.addEventListener('click', handler, true);
    document.addEventListener('touchend', handler, true);
})();

// ------------------------------
// Botón de instalar (opcional)
// ------------------------------
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
    // Chrome dispara esto si cumples los criterios; guardamos el evento
    e.preventDefault();
    deferredPrompt = e;

    // Si tienes un botón con id #btn-install, muéstralo
    const btn = document.getElementById('btn-install');
    if (btn) btn.classList.remove('hidden');
});

async function promptInstall() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    console.log('[PWA] Install outcome:', outcome);
    deferredPrompt = null;

    const btn = document.getElementById('btn-install');
    if (btn) btn.classList.add('hidden');
}

// Conecta botones opcionales si existen en tu UI:
//  - #btn-install para instalar
//  - #btn-fullscreen para solicitar fullscreen manual
document.addEventListener('DOMContentLoaded', () => {
    const btnInstall = document.getElementById('btn-install');
    if (btnInstall) btnInstall.addEventListener('click', promptInstall);

    const btnFullscreen = document.getElementById('btn-fullscreen');
    if (btnFullscreen) btnFullscreen.addEventListener('click', enableFullscreen);
});

// ------------------------------
// Señales útiles de modo/visibilidad
// ------------------------------
window.matchMedia('(display-mode: standalone)').addEventListener('change', (e) => {
    console.log('[PWA] display-mode:', e.matches ? 'standalone' : 'browser');
});

document.addEventListener('visibilitychange', () => {
    // Solo informativo; podrías pausar timers, etc.
    // console.log('[PWA] visibility:', document.visibilityState);
});

// ------------------------------
// Prevenir scroll en body/html - especialmente en tablets
// ------------------------------
(function preventBodyScroll() {
    // Prevenir scroll en el body
    document.body.addEventListener('touchmove', function(e) {
        const target = e.target;
        const scrollable = target.closest('.overflow-y-auto, .overflow-auto, [style*="overflow-y"], [style*="overflow: auto"]');
        if (!scrollable) {
            e.preventDefault();
        }
    }, { passive: false });

    // Prevenir scroll con rueda del mouse en body
    document.body.addEventListener('wheel', function(e) {
        const target = e.target;
        const scrollable = target.closest('.overflow-y-auto, .overflow-auto, [style*="overflow-y"], [style*="overflow: auto"]');
        if (!scrollable) {
            e.preventDefault();
        }
    }, { passive: false });

    // Prevenir scroll con teclado (flechas, espacio, etc.)
    document.addEventListener('keydown', function(e) {
        const activeElement = document.activeElement;
        const scrollable = activeElement && (
            activeElement.classList.contains('overflow-y-auto') ||
            activeElement.classList.contains('overflow-auto') ||
            activeElement.tagName === 'TEXTAREA' ||
            activeElement.tagName === 'SELECT' ||
            activeElement.isContentEditable
        );

        if (!scrollable && (
            e.key === 'ArrowDown' || e.key === 'ArrowUp' ||
            e.key === 'PageDown' || e.key === 'PageUp' ||
            (e.key === ' ' && !activeElement.tagName.match(/INPUT|TEXTAREA|BUTTON/))
        )) {
            e.preventDefault();
        }
    });

    // Forzar posición fija al cargar (solo en modo standalone)
    if (isStandaloneMode()) {
        window.addEventListener('load', function() {
            document.documentElement.style.position = 'fixed';
            document.documentElement.style.width = '100%';
            document.documentElement.style.height = '100%';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
            document.body.style.height = '100%';
            document.body.style.top = '0';
            document.body.style.left = '0';
        });
    }
})();

// Exponer funciones globalmente si se necesitan
window.enableFullscreen = enableFullscreen;
window.exitFullscreen = exitFullscreen;
window.promptInstall = promptInstall;
window.isStandaloneMode = isStandaloneMode;
