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


        // Verificar manifest
        if (diagnostics.manifestLink !== 'No encontrado') {
            fetch(diagnostics.manifestLink)
                .then(r => r.json())
                .then(manifest => console.log(manifest))
                .catch(e => console.error(e));
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


                // Verificar actualizaciones
                reg.addEventListener('updatefound', () => {
                });

                // Verificar estado periódicamente
                setInterval(() => {
                    reg.update().catch(() => {});
                }, 60000); // Cada minuto
            })
            .catch(err => {
                console.error(err);
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
        }
    } catch (e) {
        // Silenciar errores si el usuario canceló o no está permitido
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
// Botón de instalar (PWA)
// ------------------------------
let deferredPrompt = null;
let installButtonVisible = false;

// Detectar si es tablet o móvil
function isTabletOrMobile() {
    const width = window.innerWidth;
    const isTablet = width >= 768 && width <= 1024;
    const isMobile = width < 768;
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    return (isTablet || isMobile) && isTouchDevice;
}

// Detectar si ya está instalada
function isPWAInstalled() {
    return isStandaloneMode() || 
           window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true;
}

function toggleInstallButton(show) {
    const btn = document.getElementById('btn-install');
    if (!btn) return;
    
    // Si ya está instalada, ocultar el botón
    if (isPWAInstalled()) {
        btn.style.display = 'none';
        btn.classList.add('hidden');
        installButtonVisible = false;
        return;
    }
    
    // Mostrar u ocultar el botón
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
    
    // Agregar indicador visual si está disponible
    if (show && isTabletOrMobile()) {
        btn.title = 'Instalar aplicación Towell';
        btn.setAttribute('aria-label', 'Instalar aplicación Towell');
    }
}

// Manejar el evento beforeinstallprompt
window.addEventListener('beforeinstallprompt', (e) => {
    console.log('[PWA] beforeinstallprompt event fired');
    
    // Para tablets y móviles, permitir que el navegador muestre el banner automáticamente
    // pero también guardar el evento por si el usuario quiere instalar manualmente
    const isTabletMobile = isTabletOrMobile();
    
    if (isTabletMobile) {
        // En tablets/móviles, NO prevenir el comportamiento por defecto inmediatamente
        // Esto permite que el navegador muestre el banner automático si está disponible
        deferredPrompt = e;
        
        console.log('[PWA] Banner automático disponible en tablet/móvil. El navegador puede mostrarlo automáticamente.');
        
        // Sin embargo, en algunos casos el banner automático puede no aparecer
        // Por lo tanto, también ofrecemos un botón manual como alternativa
        // Esperar un poco antes de mostrar el botón para no interferir con el banner
        setTimeout(() => {
            if (deferredPrompt && !isPWAInstalled()) {
                // Verificar si el usuario ya interactuó con algún banner
                // Si no, mostrar nuestro botón manual como alternativa
                console.log('[PWA] Mostrando botón de instalación manual como alternativa');
                // NO prevenir el comportamiento por defecto aquí, solo mostrar el botón
                toggleInstallButton(true);
            }
        }, 2000); // Esperar 2 segundos
    } else {
        // En desktop, prevenir el comportamiento por defecto y usar botón manual
        e.preventDefault();
        deferredPrompt = e;
        toggleInstallButton(true);
        console.log('[PWA] Instalación manual disponible (desktop)');
    }
});

async function promptInstall() {
    if (!deferredPrompt) {
        console.warn('[PWA] No hay prompt de instalación disponible');
        // Intentar mostrar instrucciones alternativas
        showInstallInstructions();
        return;
    }
    
    try {
        // Para tablets/móviles, si el evento todavía no se ha prevenido, hacerlo ahora
        // Esto asegura que podemos mostrar el prompt manualmente
        if (deferredPrompt && typeof deferredPrompt.preventDefault === 'function') {
            // El evento ya fue capturado, podemos usar prompt() directamente
        }
        
        // Mostrar el prompt de instalación
        await deferredPrompt.prompt();
        
        // Esperar a que el usuario responda
        const { outcome } = await deferredPrompt.userChoice;
        
        console.log('[PWA] Install outcome:', outcome);
        
        if (outcome === 'accepted') {
            console.log('[PWA] Usuario aceptó instalar la aplicación');
            // El evento 'appinstalled' se disparará automáticamente
        } else {
            console.log('[PWA] Usuario rechazó instalar la aplicación');
        }
        
        // Limpiar el prompt
        deferredPrompt = null;
        toggleInstallButton(false);
    } catch (error) {
        console.error('[PWA] Error al mostrar prompt de instalación:', error);
        // Si falla, mostrar instrucciones manuales
        showInstallInstructions();
        deferredPrompt = null;
    }
}

// Mostrar instrucciones de instalación manual
function showInstallInstructions() {
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const isAndroid = /Android/.test(navigator.userAgent);
    const isTablet = isTabletOrMobile();
    
    let message = '';
    
    if (isIOS) {
        message = 'Para instalar: Toca el botón de compartir y selecciona "Añadir a pantalla de inicio"';
    } else if (isAndroid || isTablet) {
        message = 'Para instalar: Toca el menú del navegador (⋮) y selecciona "Instalar aplicación" o "Añadir a pantalla de inicio"';
    } else {
        message = 'Para instalar: Haz clic en el ícono de instalación en la barra de direcciones o usa el menú del navegador';
    }
    
    // Mostrar notificación o alerta (puedes personalizar esto)
    if (window.alert) {
        alert(message);
    } else {
        console.log('[PWA] Instrucciones de instalación:', message);
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
    
    // Verificar si ya está instalada al cargar
    if (isPWAInstalled()) {
        console.log('[PWA] La aplicación ya está instalada');
        toggleInstallButton(false);
    }
});

// Cuando la app se instala, ocultar el botón
window.addEventListener('appinstalled', (evt) => {
    console.log('[PWA] Aplicación instalada exitosamente');
    deferredPrompt = null;
    toggleInstallButton(false);
    
    // Opcional: Mostrar mensaje de confirmación
    // Puedes personalizar esto según tu UI
});

// ------------------------------
// Señales útiles de modo/visibilidad
// ------------------------------
window.matchMedia('(display-mode: standalone)').addEventListener('change', (e) => {
});

document.addEventListener('visibilitychange', () => {
    // Solo informativo; podrías pausar timers, etc.
    // console.log('[PWA] visibility:', document.visibilityState);
});

// ------------------------------
// Optimización para tablets y móviles
// ------------------------------
function setupBodyScrollGuards() {
    const body = document.body;
    if (!body) return;

    // Detectar dispositivo (tablet o móvil)
    const isTablet = window.innerWidth >= 768 && window.innerWidth <= 1024;
    const isMobile = window.innerWidth < 768;
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    // Prevenir zoom accidental con doble toque (solo en móviles)
    let lastTouchEnd = 0;
    if (isMobile || isTouchDevice) {
        document.addEventListener('touchend', (e) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
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
            if (!isInput) {
                e.preventDefault();
            }
        }
    };

    // Aplicar guards solo en modo standalone
    if (isStandaloneMode()) {
        body.addEventListener('touchmove', pointerGuard, { passive: false });
        // En tablets, ser más permisivo con el scroll
        if (!isTablet) {
            body.addEventListener('wheel', pointerGuard, { passive: false });
        }
    }

    // Prevenir navegación con teclado en modo standalone
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

    // Manejar cambios de orientación
    window.addEventListener('orientationchange', () => {
        setTimeout(() => {
            applyFixedLayout();
            // Forzar redibujado en algunos navegadores
            window.dispatchEvent(new Event('resize'));
        }, 100);
    });

    // Manejar cambios de tamaño de ventana (útil en tablets)
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
