/**
 * PWA bootstrap: SW, instalación y fullscreen sin hacks de scroll
 */

// ------------------------------
// Service Worker Registration
// ------------------------------
if ('serviceWorker' in navigator) {
    // Diagnóstico de PWA
    const pwaDiagnostics = () => {
        const diagnostics = {
            protocol: window.location.protocol,
            hostname: window.location.hostname,
            origin: window.location.origin,
            pathname: window.location.pathname,
            isSecure: window.isSecureContext,
            swSupported: 'serviceWorker' in navigator,
            manifestLink: document.querySelector('link[rel="manifest"]')?.href || 'No encontrado'
        };

        console.log('[PWA] Diagnóstico:', diagnostics);

        // Verificar manifest
        if (diagnostics.manifestLink !== 'No encontrado') {
            fetch(diagnostics.manifestLink)
                .then(r => r.json())
                .then(manifest => console.log('[PWA] Manifest cargado:', manifest))
                .catch(e => console.error('[PWA] Error al cargar manifest:', e));
        }

        return diagnostics;
    };

    window.addEventListener('load', () => {
        const diagnostics = pwaDiagnostics();

        // Detectar la ruta base correcta
        const swPath = '/sw.js';
        const baseUrl = new URL(swPath, window.location.origin).href;

        // Verificar si estamos en un contexto seguro
        // Chrome/Edge permiten IPs privadas en desarrollo
        const isSecureContext = window.isSecureContext ||
                                 window.location.hostname === 'localhost' ||
                                 window.location.hostname === '127.0.0.1' ||
                                 window.location.hostname.match(/^192\.168\.|^10\.|^172\.(1[6-9]|2[0-9]|3[01])\./);

        if (!isSecureContext && window.location.protocol !== 'https:') {
            console.warn('[PWA] Advertencia: SW puede no funcionar en HTTP con IPs públicas. Usa HTTPS o localhost.');
        }

        // Intentar registrar el SW
        navigator.serviceWorker.register(swPath, { scope: '/' })
            .then(reg => {
                console.log('[PWA] ✅ SW registrado exitosamente:', {
                    scope: reg.scope,
                    active: reg.active?.scriptURL,
                    installing: reg.installing?.scriptURL,
                    waiting: reg.waiting?.scriptURL
                });

                // Verificar actualizaciones
                reg.addEventListener('updatefound', () => {
                    console.log('[PWA] Nueva versión del SW encontrada');
                });

                // Verificar estado periódicamente
                setInterval(() => {
                    reg.update().catch(() => {});
                }, 60000); // Cada minuto
            })
            .catch(err => {
                console.error('[PWA]  Error al registrar SW:', {
                    error: err.message,
                    name: err.name,
                    stack: err.stack,
                    url: baseUrl,
                    protocol: window.location.protocol,
                    hostname: window.location.hostname,
                    diagnostics: diagnostics
                });

                // Sugerencias de solución
                if (err.message.includes('Failed to register')) {
                    console.warn('[PWA] Soluciones posibles:');
                    console.warn('1. Verifica que /sw.js sea accesible:', baseUrl);
                    console.warn('2. Usa HTTPS o localhost/127.0.0.1');
                    console.warn('3. Limpia el caché del navegador');
                    console.warn('4. Verifica la consola de DevTools > Application > Service Workers');
                }
            });
    });

    // Exponer diagnóstico globalmente
    window.pwaDiagnostics = pwaDiagnostics;
}

// ------------------------------
// Helpers de modo standalone
// ------------------------------
function isStandaloneMode() {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.matchMedia('(display-mode: fullscreen)').matches ||
        window.navigator.standalone === true // iOS
    );
}

// ------------------------------
// Fullscreen helpers
// ------------------------------
async function enableFullscreen() {
    try {
        // Verificar si ya está en fullscreen (múltiples formas de verificar)
        const isAlreadyFullscreen = document.fullscreenElement ||
                                     document.webkitFullscreenElement ||
                                     document.mozFullScreenElement ||
                                     document.msFullscreenElement;

        if (isAlreadyFullscreen) {
            return; // Ya está en fullscreen, no hacer nada
        }

        const el = document.documentElement;
        const request = el.requestFullscreen ||
                       el.webkitRequestFullscreen ||
                       el.mozRequestFullScreen ||
                       el.msRequestFullscreen;

        if (request) {
            await request.call(el);
            console.log('[PWA] Fullscreen activado');
        }
    } catch (e) {
        // Silenciar errores si el usuario canceló o no está permitido
        if (e.name !== 'NotAllowedError' && e.name !== 'TypeError') {
            console.warn('[PWA] No se pudo entrar a fullscreen:', e);
        }
    }
}

async function exitFullscreen() {
    try {
        const exit = document.exitFullscreen || document.webkitExitFullscreen;
        if (document.fullscreenElement && exit) {
            await exit.call(document);
            console.log('[PWA] Fullscreen desactivado');
        }
    } catch (e) {
        console.warn('[PWA] No se pudo salir de fullscreen:', e);
    }
}

// Intento automático de fullscreen SOLO UNA VEZ al iniciar la PWA
// Solo cuando la app está instalada/standalone y aún no está en fullscreen
(function initFullscreenOnce() {
    // Verificar si ya está en fullscreen o si ya se intentó activar en esta sesión
    const FULLSCREEN_ATTEMPTED_KEY = 'pwa_fullscreen_attempted';
    const sessionAttempted = sessionStorage.getItem(FULLSCREEN_ATTEMPTED_KEY);

    // Si ya está en fullscreen, no hacer nada
    if (document.fullscreenElement || document.webkitFullscreenElement) {
        return;
    }

    // Si ya se intentó en esta sesión, no volver a intentar
    if (sessionAttempted === 'true') {
        return;
    }

    // Solo activar si estamos en modo standalone/PWA
    if (!isStandaloneMode()) {
        return;
    }

    const el = document.documentElement;
    const requestFullscreen = el.requestFullscreen || el.webkitRequestFullscreen;
    if (!requestFullscreen) {
        return; // navegador no soportado
    }

    // Esperar a que la página esté completamente cargada
    const attemptFullscreen = () => {
        // Verificar nuevamente que no esté ya en fullscreen
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            return;
        }

        // Marcar que se intentó en esta sesión
        sessionStorage.setItem(FULLSCREEN_ATTEMPTED_KEY, 'true');

        // Intentar activar fullscreen una sola vez
        enableFullscreen().catch(() => {
            // Si falla, no volver a intentar
            console.log('[PWA] Fullscreen no disponible o rechazado por el usuario');
        });
    };

    // Intentar después de un pequeño delay para asegurar que la página está lista
    if (document.readyState === 'complete') {
        setTimeout(attemptFullscreen, 500);
    } else {
        window.addEventListener('load', () => {
            setTimeout(attemptFullscreen, 500);
        }, { once: true });
    }
})();

// ------------------------------
// Botón de instalar (opcional)
// ------------------------------
let deferredPrompt = null;

function toggleInstallButton(show) {
    const btn = document.getElementById('btn-install');
    if (!btn) return;
    btn.classList.toggle('hidden', !show);
    btn.toggleAttribute('disabled', !show);
}

window.addEventListener('beforeinstallprompt', (e) => {
    // Chrome dispara esto si cumples los criterios; guardamos el evento
    e.preventDefault();
    deferredPrompt = e;
    toggleInstallButton(true);
});

async function promptInstall() {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    console.log('[PWA] Install outcome:', outcome);
    deferredPrompt = null;
    toggleInstallButton(false);
}

// Conecta botones opcionales si existen en tu UI:
//  - #btn-install para instalar
//  - #btn-fullscreen para solicitar fullscreen manual
document.addEventListener('DOMContentLoaded', () => {
    const btnInstall = document.getElementById('btn-install');
    toggleInstallButton(false);
    if (btnInstall) btnInstall.addEventListener('click', promptInstall);

    const btnFullscreen = document.getElementById('btn-fullscreen');
    if (btnFullscreen) btnFullscreen.addEventListener('click', enableFullscreen);

    setupBodyScrollGuards();
});

window.addEventListener('appinstalled', () => {
    console.log('[PWA] App instalada');
    toggleInstallButton(false);
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
function setupBodyScrollGuards() {
    const body = document.body;
    if (!body) return;

    const pointerGuard = (e) => {
        const target = e.target;
        const scrollable = target && target.closest('.overflow-y-auto, .overflow-auto, [style*="overflow-y"], [style*="overflow: auto"]');
        if (!scrollable) {
            e.preventDefault();
        }
    };

    body.addEventListener('touchmove', pointerGuard, { passive: false });
    body.addEventListener('wheel', pointerGuard, { passive: false });

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

    const applyFixedLayout = () => {
        document.documentElement.style.position = 'fixed';
        document.documentElement.style.width = '100%';
        document.documentElement.style.height = '100%';
        body.style.position = 'fixed';
        body.style.width = '100%';
        body.style.height = '100%';
        body.style.top = '0';
        body.style.left = '0';
    };

    if (isStandaloneMode()) {
        if (document.readyState === 'complete') {
            applyFixedLayout();
        } else {
            window.addEventListener('load', applyFixedLayout, { once: true });
        }
    }
}

// Exponer funciones globalmente si se necesitan
window.enableFullscreen = enableFullscreen;
window.exitFullscreen = exitFullscreen;
window.promptInstall = promptInstall;
window.isStandaloneMode = isStandaloneMode;
