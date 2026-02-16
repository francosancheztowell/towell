/**
 * PWA bootstrap: SW, instalaciÃ³n y fullscreen sin hacks de scroll
 */

// ------------------------------
// Service Worker Registration
// ------------------------------
if ('serviceWorker' in navigator) {
    // DiagnÃ³stico de PWA
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


        // Verificar manifest
        if (diagnostics.manifestLink !== 'No encontrado') {
            fetch(diagnostics.manifestLink)
                .then(r => r.json())
                .catch(e => console.error(e));
        }

        return diagnostics;
    };

    window.addEventListener('load', () => {
        const diagnostics = pwaDiagnostics();

        // Resolver ruta del SW con base en el manifest para soportar subdirectorios.
        const manifestHref = document.querySelector('link[rel="manifest"]')?.getAttribute('href');
        const manifestUrl = manifestHref ? new URL(manifestHref, window.location.href) : null;
        const swUrl = manifestUrl ? new URL('sw.js', manifestUrl) : new URL('/sw.js', window.location.origin);
        const swPath = swUrl.pathname;
        const swScope = manifestUrl ? new URL('.', manifestUrl).pathname : '/';

        // Verificar si estamos en un contexto seguro
        // Chrome/Edge permiten IPs privadas en desarrollo
        const isSecureContext = window.isSecureContext ||
                                 window.location.hostname === 'localhost' ||
                                 window.location.hostname === '127.0.0.1' ||
                                 window.location.hostname.match(/^192\.168\.|^10\.|^172\.(1[6-9]|2[0-9]|3[01])\./);

        if (!isSecureContext && window.location.protocol !== 'https:') {
            console.warn('[PWA] Advertencia: SW puede no funcionar en HTTP con IPs pÃºblicas. Usa HTTPS o localhost.');
        }

        // Intentar registrar el SW
        navigator.serviceWorker.register(swPath, { scope: swScope })
            .then(reg => {


                // Verificar actualizaciones
                reg.addEventListener('updatefound', () => {
                });

                // Verificar estado periÃ³dicamente
                setInterval(() => {
                    reg.update().catch(() => {});
                }, 60000); // Cada minuto
            })
            .catch(err => {
                console.error(err);
            });
    });

    // Exponer diagnÃ³stico globalmente
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
        // Verificar si ya estÃ¡ en fullscreen (mÃºltiples formas de verificar)
        const isAlreadyFullscreen = document.fullscreenElement ||
                                     document.webkitFullscreenElement ||
                                     document.mozFullScreenElement ||
                                     document.msFullscreenElement;

        if (isAlreadyFullscreen) {
            return; // Ya estÃ¡ en fullscreen, no hacer nada
        }

        const el = document.documentElement;
        const request = el.requestFullscreen ||
                       el.webkitRequestFullscreen ||
                       el.mozRequestFullScreen ||
                       el.msRequestFullscreen;

        if (request) {
            await request.call(el);
        }
    } catch (e) {
        // Silenciar errores si el usuario cancelÃ³ o no estÃ¡ permitido
        if (e.name !== 'NotAllowedError' && e.name !== 'TypeError') {
            console.warn(e);
        }
    }
}

async function exitFullscreen() {
    try {
        const exit = document.exitFullscreen || document.webkitExitFullscreen;
        if (document.fullscreenElement && exit) {
            await exit.call(document);
        }
    } catch (e) {
        console.warn(e);
    }
}

// Intento automÃ¡tico de fullscreen SOLO UNA VEZ al iniciar la PWA
// Solo cuando la app estÃ¡ instalada/standalone y aÃºn no estÃ¡ en fullscreen
(function initFullscreenOnce() {
    // Verificar si ya estÃ¡ en fullscreen o si ya se intentÃ³ activar en esta sesiÃ³n
    const FULLSCREEN_ATTEMPTED_KEY = 'pwa_fullscreen_attempted';
    const sessionAttempted = sessionStorage.getItem(FULLSCREEN_ATTEMPTED_KEY);

    // Si ya estÃ¡ en fullscreen, no hacer nada
    if (document.fullscreenElement || document.webkitFullscreenElement) {
        return;
    }

    // Si ya se intentÃ³ en esta sesiÃ³n, no volver a intentar
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

    // Esperar a que la pÃ¡gina estÃ© completamente cargada
    const attemptFullscreen = () => {
        // Verificar nuevamente que no estÃ© ya en fullscreen
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            return;
        }

        // Marcar que se intentÃ³ en esta sesiÃ³n
        sessionStorage.setItem(FULLSCREEN_ATTEMPTED_KEY, 'true');

        // Intentar activar fullscreen una sola vez
        enableFullscreen().catch(() => {
            // Si falla, no volver a intentar
        });
    };

    // Intentar despuÃ©s de un pequeÃ±o delay para asegurar que la pÃ¡gina estÃ¡ lista
    if (document.readyState === 'complete') {
        setTimeout(attemptFullscreen, 500);
    } else {
        window.addEventListener('load', () => {
            setTimeout(attemptFullscreen, 500);
        }, { once: true });
    }
})();

// ------------------------------
// BotÃ³n de instalar (PWA)
// ------------------------------
let deferredPrompt = null;
let installButtonVisible = false;

// Detectar si es tablet o mÃ³vil
function isTabletOrMobile() {
    const ua = navigator.userAgent || '';
    const isIPadOS = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    const isIOS = /iPad|iPhone|iPod/i.test(ua) || isIPadOS;
    const isAndroid = /Android/i.test(ua);
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    return isTouchDevice && (isIOS || isAndroid || window.innerWidth <= 1366);
}

// Detectar si ya estÃ¡ instalada
function isPWAInstalled() {
    return isStandaloneMode() ||
           window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true;
}

function toggleInstallButton(show) {
    const btn = document.getElementById('btn-install');
    if (!btn) return;

    // Si ya estÃ¡ instalada, ocultar el botÃ³n
    if (isPWAInstalled()) {
        btn.style.display = 'none';
        btn.classList.add('hidden');
        installButtonVisible = false;
        return;
    }

    // Mostrar u ocultar el botÃ³n
    if (show) {
        btn.style.display = 'flex';
        btn.classList.remove('hidden');
        btn.removeAttribute('disabled');
    } else {
        btn.style.display = 'none';
        btn.classList.add('hidden');
        btn.setAttribute('disabled', 'disabled');
    }

    installButtonVisible = show;

    // Agregar indicador visual si estÃ¡ disponible
    if (show && isTabletOrMobile()) {
        btn.title = 'Instalar aplicacion Towell';
        btn.setAttribute('aria-label', 'Instalar aplicacion Towell');
    }
}

// Manejar el evento beforeinstallprompt
window.addEventListener('beforeinstallprompt', (e) => {
    // En touch devices (tablets/moviles) dejar que el navegador maneje el banner nativo.
    const isTabletMobile = isTabletOrMobile();
    const hasInstallButton = !!document.getElementById('btn-install');

    if (isTabletMobile) {
        deferredPrompt = e;

        // En algunos casos el banner automatico no aparece; mostrar boton manual como alternativa.
        setTimeout(() => {
            if (deferredPrompt && !isPWAInstalled()) {
                toggleInstallButton(true);
            }
        }, 2000);
    } else if (hasInstallButton) {
        // En desktop con boton manual, usar flujo controlado.
        e.preventDefault();
        deferredPrompt = e;
        toggleInstallButton(true);
    } else {
        // Sin boton manual, no bloquear el prompt nativo del navegador.
        deferredPrompt = e;
    }
});

async function promptInstall() {
    if (!deferredPrompt) {
        console.warn('[PWA] No hay prompt de instalacion disponible');
        // Intentar mostrar instrucciones alternativas
        showInstallInstructions();
        return;
    }

    try {
        // Para tablets/mÃ³viles, si el evento todavÃ­a no se ha prevenido, hacerlo ahora
        // Esto asegura que podemos mostrar el prompt manualmente
        if (deferredPrompt && typeof deferredPrompt.preventDefault === 'function') {
            // El evento ya fue capturado, podemos usar prompt() directamente
        }

        // Mostrar el prompt de instalaciÃ³n
        await deferredPrompt.prompt();

        // Esperar a que el usuario responda
        const { outcome } = await deferredPrompt.userChoice;

        if (outcome === 'accepted') {
            // El evento 'appinstalled' se dispararÃ¡ automÃ¡ticamente
        } else {
        }

        // Limpiar el prompt
        deferredPrompt = null;
        toggleInstallButton(false);
    } catch (error) {
        console.error('[PWA] Error al mostrar prompt de instalacion:', error);
        // Si falla, mostrar instrucciones manuales
        showInstallInstructions();
        deferredPrompt = null;
    }
}

// Mostrar instrucciones de instalaciÃ³n manual
function showInstallInstructions() {
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const isAndroid = /Android/.test(navigator.userAgent);
    const isTablet = isTabletOrMobile();

    let message = '';

    if (isIOS) {
        message = 'Para instalar: Toca el boton de compartir y selecciona "Anadir a pantalla de inicio"';
    } else if (isAndroid || isTablet) {
        message = 'Para instalar: Toca el menu del navegador y selecciona "Instalar aplicacion" o "Anadir a pantalla de inicio"';
    } else {
        message = 'Para instalar: Haz clic en el icono de instalacion en la barra de direcciones o usa el menu del navegador';
    }

    // Mostrar notificaciÃ³n o alerta (puedes personalizar esto)
    if (window.alert) {
        alert(message);
    } else {
    }
}

// Conecta botones si existen en tu UI
document.addEventListener('DOMContentLoaded', () => {
    const btnInstall = document.getElementById('btn-install');
    if (btnInstall) {
        // Inicialmente oculto hasta que haya un prompt disponible
        toggleInstallButton(false);
        btnInstall.addEventListener('click', promptInstall);
    }

    const btnFullscreen = document.getElementById('btn-fullscreen');
    if (btnFullscreen) {
        btnFullscreen.addEventListener('click', enableFullscreen);
    }

    setupBodyScrollGuards();

    // Verificar si ya estÃ¡ instalada al cargar
    if (isPWAInstalled()) {
        toggleInstallButton(false);
    }
});

// Cuando la app se instala, ocultar el botÃ³n
window.addEventListener('appinstalled', (evt) => {
    deferredPrompt = null;
    toggleInstallButton(false);

    // Opcional: Mostrar mensaje de confirmaciÃ³n
    // Puedes personalizar esto segÃºn tu UI
});

// ------------------------------
// SeÃ±ales Ãºtiles de modo/visibilidad
// ------------------------------
window.matchMedia('(display-mode: standalone)').addEventListener('change', (e) => {
});

document.addEventListener('visibilitychange', () => {
    // Solo informativo; podrÃ­as pausar timers, etc.
    // console.log('[PWA] visibility:', document.visibilityState);
});

// ------------------------------
// OptimizaciÃ³n para tablets y mÃ³viles
// ------------------------------
function setupBodyScrollGuards() {
    const body = document.body;
    if (!body) return;

    // Detectar dispositivo (tablet o mÃ³vil)
    const isTablet = window.innerWidth >= 768 && window.innerWidth <= 1024;
    const isMobile = window.innerWidth < 768;
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    // Prevenir zoom accidental con doble toque (solo en mÃ³viles)
    let lastTouchEnd = 0;
    if (isMobile || isTouchDevice) {
        document.addEventListener('touchend', (e) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300 && e.cancelable) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    }

    // Guard para scroll: solo prevenir en elementos no scrollables
    const pointerGuard = (e) => {
        const target = e.target;
        const scrollable = target && target.closest('.overflow-y-auto, .overflow-auto, [style*="overflow-y"], [style*="overflow: auto"], textarea, select, [contenteditable="true"]');
        if (!scrollable && isStandaloneMode()) {
            // Solo prevenir en modo standalone para evitar scroll accidental
            const isInput = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT';
            if (!isInput && e.cancelable) {
                e.preventDefault();
            }
        }
    };

    // Aplicar guards solo en modo standalone
    if (isStandaloneMode()) {
        body.addEventListener('touchmove', pointerGuard, { passive: false });
        // En tablets, ser mÃ¡s permisivo con el scroll
        if (!isTablet) {
            body.addEventListener('wheel', pointerGuard, { passive: false });
        }
    }

    // Prevenir navegaciÃ³n con teclado en modo standalone
    document.addEventListener('keydown', function(e) {
        const activeElement = document.activeElement;
        const scrollable = activeElement && (
            activeElement.classList.contains('overflow-y-auto') ||
            activeElement.classList.contains('overflow-auto') ||
            activeElement.tagName === 'TEXTAREA' ||
            activeElement.tagName === 'SELECT' ||
            activeElement.isContentEditable
        );

        // Solo prevenir en modo standalone y cuando no hay elemento scrollable activo
        if (isStandaloneMode() && !scrollable && (
            e.key === 'ArrowDown' || e.key === 'ArrowUp' ||
            e.key === 'PageDown' || e.key === 'PageUp' ||
            (e.key === ' ' && !activeElement.tagName.match(/INPUT|TEXTAREA|BUTTON/))
        )) {
            e.preventDefault();
        }
    });

    // Layout fijo solo en modo standalone (para evitar problemas de viewport)
    const applyFixedLayout = () => {
        if (isStandaloneMode()) {
            // En tablets, no aplicar layout fijo para permitir mejor uso del espacio
            if (!isTablet) {
                document.documentElement.style.height = '100vh';
                document.documentElement.style.overflow = 'hidden';
                body.style.height = '100vh';
                body.style.overflow = 'hidden';
            } else {
                // En tablets, usar viewport completo pero permitir scroll interno
                document.documentElement.style.height = '100%';
                body.style.height = '100%';
            }
        }
    };

    if (document.readyState === 'complete') {
        applyFixedLayout();
    } else {
        window.addEventListener('load', applyFixedLayout, { once: true });
    }

    // Manejar cambios de orientaciÃ³n
    window.addEventListener('orientationchange', () => {
        setTimeout(() => {
            applyFixedLayout();
            // Forzar redibujado en algunos navegadores
            window.dispatchEvent(new Event('resize'));
        }, 100);
    });

    // Manejar cambios de tamaÃ±o de ventana (Ãºtil en tablets)
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            applyFixedLayout();
        }, 250);
    });
}

// Exponer funciones globalmente si se necesitan
window.enableFullscreen = enableFullscreen;
window.exitFullscreen = exitFullscreen;
window.promptInstall = promptInstall;
window.isStandaloneMode = isStandaloneMode;
