/**
 * Sistema simple de sonidos para clicks
 * Mejora la experiencia de usuario con feedback auditivo
 */

(function() {
    'use strict';

    // Configuración - Sonidos siempre activados
    const CONFIG = {
        enabled: true, // Siempre activado
        volume: 0.25, // Un poco más alto para tablets
        clickDuration: 0.1, // Un poco más largo para mejor feedback en tablets
        hoverDuration: 0.05
    };

    // Generar sonidos usando Web Audio API
    function createAudioContext() {
        try {
            return new (window.AudioContext || window.webkitAudioContext)();
        } catch (error) {
            console.log('Web Audio API no disponible');
            return null;
        }
    }

    function generateTone(frequency, duration, volume = 0.2) {
        const audioContext = createAudioContext();
        if (!audioContext) return;

        const buffer = audioContext.createBuffer(1, audioContext.sampleRate * duration, audioContext.sampleRate);
        const data = buffer.getChannelData(0);

        for (let i = 0; i < buffer.length; i++) {
            const t = i / audioContext.sampleRate;
            data[i] = Math.sin(2 * Math.PI * frequency * t) * Math.exp(-t * 15) * volume;
        }

        return { audioContext, buffer };
    }

    function playSound(frequency, duration, volume = CONFIG.volume) {
        if (!CONFIG.enabled) return;

        const sound = generateTone(frequency, duration, volume);
        if (!sound) return;

        try {
            const source = sound.audioContext.createBufferSource();
            const gainNode = sound.audioContext.createGain();

            source.buffer = sound.buffer;
            gainNode.gain.value = 1;

            source.connect(gainNode);
            gainNode.connect(sound.audioContext.destination);

            source.start();
        } catch (error) {
            console.log('Error reproduciendo sonido:', error);
        }
    }

    // Sonidos específicos - solo click, más realista
    const sounds = {
        click: () => playClickSound(),
        success: () => playSound(523, 0.15, 0.25), // Nota C5
        error: () => playSound(400, 0.2, 0.3)
    };

    // Función para generar sonido de click más realista
    function playClickSound() {
        if (!CONFIG.enabled) return;

        const audioContext = createAudioContext();
        if (!audioContext) return;

        try {
            // Crear un sonido de click más realista con múltiples frecuencias
            const buffer = audioContext.createBuffer(1, audioContext.sampleRate * 0.06, audioContext.sampleRate);
            const data = buffer.getChannelData(0);

            for (let i = 0; i < buffer.length; i++) {
                const t = i / audioContext.sampleRate;
                // Combinar múltiples frecuencias para un sonido más natural
                const click = Math.sin(2 * Math.PI * 1200 * t) * Math.exp(-t * 30) * 0.4 +
                             Math.sin(2 * Math.PI * 800 * t) * Math.exp(-t * 25) * 0.3 +
                             Math.sin(2 * Math.PI * 400 * t) * Math.exp(-t * 20) * 0.2;
                data[i] = click;
            }

            const source = audioContext.createBufferSource();
            const gainNode = audioContext.createGain();

            source.buffer = buffer;
            gainNode.gain.value = CONFIG.volume;

            source.connect(gainNode);
            gainNode.connect(audioContext.destination);

            source.start();
        } catch (error) {
            console.log('Error reproduciendo sonido de click:', error);
        }
    }

    // Detectar clicks en módulos y submódulos - optimizado para tablets
    function isModuleOrSubmodule(element) {
        const href = element.getAttribute('href');
        const classes = element.className;
        const id = element.getAttribute('id');

        // Verificar URLs de módulos (prioridad alta)
        if (href && (
            href.includes('/modulos/') ||
            href.includes('/submodulos/') ||
            href.includes('/tejido/') ||
            href.includes('/urdido/') ||
            href.includes('/engomado/') ||
            href.includes('/tejedores/') ||
            href.includes('/atadores/') ||
            href.includes('/inventario-telas/') ||
            href.includes('/produccionProceso') ||
            href.includes('/planeacion/') ||
            href.includes('/requerimientos/')
        )) {
            return true;
        }

        // Verificar clases de elementos interactivos
        if (classes && (
            classes.includes('module-card') ||
            classes.includes('module-link') ||
            classes.includes('telar-nav-btn') ||
            classes.includes('bg-blue-600') ||
            classes.includes('hover:bg-blue-700') ||
            classes.includes('bg-gradient-to-r') ||
            classes.includes('bg-white') ||
            classes.includes('rounded-lg') ||
            classes.includes('shadow-md') ||
            classes.includes('transition-colors') ||
            classes.includes('tablet-optimized') ||
            classes.includes('ripple-effect') ||
            classes.includes('group') // Para elementos con hover effects
        )) {
            return true;
        }

        // Verificar IDs específicos de botones de navegación
        if (id && (
            id.includes('nav-telar-') ||
            id.includes('sound-toggle') ||
            id.includes('logout-btn')
        )) {
            return true;
        }

        // Si es un enlace (a) o botón, probablemente es interactivo
        if (element.tagName === 'A' || element.tagName === 'BUTTON') {
            return true;
        }

        return false;
    }

    // Agregar efectos visuales - mejorados para tablets
    function addClickEffect(element) {
        // Efecto más notorio para tablets
        element.style.transform = 'scale(0.92)';
        element.style.transition = 'transform 0.15s ease';

        // Agregar efecto de brillo temporal
        const originalBoxShadow = element.style.boxShadow;
        element.style.boxShadow = '0 0 20px rgba(59, 130, 246, 0.5)';

        setTimeout(() => {
            element.style.transform = 'scale(1)';
            element.style.boxShadow = originalBoxShadow;
        }, 150);
    }

    // Event listener - solo para clicks
    document.addEventListener('click', function(e) {
        const target = e.target.closest('a, button');
        if (!target || !isModuleOrSubmodule(target)) return;

        sounds.click();
        addClickEffect(target);
    });

    // Inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        // Sonidos siempre activados - no necesita inicialización adicional
    });

    // Exportar para uso global
    window.TowellSounds = {
        play: sounds.click,
        playSuccess: sounds.success,
        playError: sounds.error
    };

})();
