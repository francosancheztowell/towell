/**
 * Sistema de sonidos para clicks en módulos y submódulos
 * Mejora la experiencia de usuario con feedback auditivo
 */

class ClickSoundManager {
    constructor() {
        this.sounds = {
            click: null,
            hover: null,
            success: null
        };
        this.isEnabled = this.getSoundPreference();
        this.volume = 0.3; // Volumen por defecto
        this.init();
    }

    init() {
        // Cargar sonidos
        this.loadSounds();

        // Agregar listener para todos los módulos y submódulos
        this.addClickListeners();

        // Crear toggle de sonido
        this.createSoundToggle();
    }

    loadSounds() {
        // Sonido principal de click (generado con Web Audio API)
        this.sounds.click = this.generateClickSound();

        // Sonido de hover (opcional)
        this.sounds.hover = this.generateHoverSound();

        // Sonido de éxito (opcional)
        this.sounds.success = this.generateSuccessSound();
    }

    generateClickSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const buffer = audioContext.createBuffer(1, audioContext.sampleRate * 0.1, audioContext.sampleRate);
            const data = buffer.getChannelData(0);

            for (let i = 0; i < buffer.length; i++) {
                // Generar un sonido de click suave
                data[i] = Math.sin(2 * Math.PI * 800 * i / audioContext.sampleRate) *
                         Math.exp(-i / audioContext.sampleRate * 10) * 0.3;
            }

            return { audioContext, buffer };
        } catch (error) {
            console.log('Web Audio API no disponible, usando sonido alternativo');
            return null;
        }
    }

    generateHoverSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const buffer = audioContext.createBuffer(1, audioContext.sampleRate * 0.05, audioContext.sampleRate);
            const data = buffer.getChannelData(0);

            for (let i = 0; i < buffer.length; i++) {
                // Sonido más suave para hover
                data[i] = Math.sin(2 * Math.PI * 600 * i / audioContext.sampleRate) *
                         Math.exp(-i / audioContext.sampleRate * 15) * 0.15;
            }

            return { audioContext, buffer };
        } catch (error) {
            return null;
        }
    }

    generateSuccessSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const buffer = audioContext.createBuffer(1, audioContext.sampleRate * 0.2, audioContext.sampleRate);
            const data = buffer.getChannelData(0);

            for (let i = 0; i < buffer.length; i++) {
                // Sonido de éxito más musical
                data[i] = Math.sin(2 * Math.PI * 523 * i / audioContext.sampleRate) *
                         Math.exp(-i / audioContext.sampleRate * 8) * 0.2; // Nota C5
            }

            return { audioContext, buffer };
        } catch (error) {
            return null;
        }
    }

    playSound(soundType = 'click') {
        if (!this.isEnabled) return;

        try {
            const sound = this.sounds[soundType];
            if (!sound) return;

            const source = sound.audioContext.createBufferSource();
            const gainNode = sound.audioContext.createGain();

            source.buffer = sound.buffer;
            gainNode.gain.value = this.volume;

            source.connect(gainNode);
            gainNode.connect(sound.audioContext.destination);

            source.start();
        } catch (error) {
            console.log('Error reproduciendo sonido:', error);
        }
    }

    addClickListeners() {
        // Agregar listeners a todos los módulos
        document.addEventListener('click', (e) => {
            const target = e.target.closest('a, button');
            if (!target) return;

            // Verificar si es un módulo o submódulo
            if (this.isModuleOrSubmodule(target)) {
                this.playSound('click');

                // Agregar efecto visual temporal
                this.addClickEffect(target);
            }
        });

        // Agregar hover effects (opcional)
        document.addEventListener('mouseenter', (e) => {
            const target = e.target.closest('a, button');
            if (!target || !this.isModuleOrSubmodule(target)) return;

            // Sonido de hover más suave
            this.playSound('hover');
        }, true);
    }

    isModuleOrSubmodule(element) {
        // Verificar si el elemento es un módulo o submódulo
        const href = element.getAttribute('href');
        const classes = element.className;

        // Módulos principales
        if (href && (
            href.includes('/modulos/') ||
            href.includes('/submodulos/') ||
            href.includes('/tejido/') ||
            href.includes('/urdido/') ||
            href.includes('/engomado/') ||
            href.includes('/tejedores/') ||
            href.includes('/atadores/')
        )) {
            return true;
        }

        // Botones de navegación
        if (classes && (
            classes.includes('module-card') ||
            classes.includes('telar-nav-btn') ||
            classes.includes('bg-blue-600') ||
            classes.includes('hover:bg-blue-700')
        )) {
            return true;
        }

        return false;
    }

    addClickEffect(element) {
        // Efecto visual de click
        element.style.transform = 'scale(0.95)';
        element.style.transition = 'transform 0.1s ease';

        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 100);
    }

    createSoundToggle() {
        // Crear toggle para activar/desactivar sonidos
        const toggle = document.createElement('div');
        toggle.innerHTML = `
            <button id="sound-toggle" class="fixed bottom-4 right-4 bg-gray-800 text-white p-3 rounded-full shadow-lg hover:bg-gray-700 transition-colors z-50" title="Activar/Desactivar sonidos">
                <svg id="sound-icon" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.617.816L4.383 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.383l4-3.816zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.983 5.983 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.984 3.984 0 00-1.172-2.828 1 1 0 010-1.415z" clip-rule="evenodd"></path>
                </svg>
            </button>
        `;

        document.body.appendChild(toggle);

        // Agregar funcionalidad al toggle
        toggle.querySelector('#sound-toggle').addEventListener('click', () => {
            this.toggleSound();
        });

        this.updateToggleIcon();
    }

    toggleSound() {
        this.isEnabled = !this.isEnabled;
        this.saveSoundPreference();
        this.updateToggleIcon();

        // Sonido de confirmación
        if (this.isEnabled) {
            this.playSound('success');
        }
    }

    updateToggleIcon() {
        const icon = document.querySelector('#sound-icon');
        if (!icon) return;

        if (this.isEnabled) {
            icon.innerHTML = `
                <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.617.816L4.383 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.383l4-3.816zM14.657 2.929a1 1 0 011.414 0A9.972 9.972 0 0119 10a9.972 9.972 0 01-2.929 7.071 1 1 0 01-1.414-1.414A7.971 7.971 0 0017 10c0-2.21-.894-4.208-2.343-5.657a1 1 0 010-1.414zm-2.829 2.828a1 1 0 011.415 0A5.983 5.983 0 0115 10a5.983 5.983 0 01-1.757 4.243 1 1 0 01-1.415-1.415A3.984 3.984 0 0013 10a3.984 3.984 0 00-1.172-2.828 1 1 0 010-1.415z" clip-rule="evenodd"></path>
            `;
        } else {
            icon.innerHTML = `
                <path fill-rule="evenodd" d="M9.383 3.076A1 1 0 0110 4v12a1 1 0 01-1.617.816L4.383 13H2a1 1 0 01-1-1V8a1 1 0 011-1h2.383l4-3.816zM5.707 7.293a1 1 0 010 1.414L3.414 11l2.293 2.293a1 1 0 11-1.414 1.414L2 12.414l-2.293 2.293a1 1 0 01-1.414-1.414L.586 11l-2.293-2.293a1 1 0 011.414-1.414L2 9.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
            `;
        }
    }

    getSoundPreference() {
        const saved = localStorage.getItem('towell-sound-enabled');
        return saved !== null ? saved === 'true' : true; // Por defecto activado
    }

    saveSoundPreference() {
        localStorage.setItem('towell-sound-enabled', this.isEnabled.toString());
    }

    // Método público para cambiar volumen
    setVolume(volume) {
        this.volume = Math.max(0, Math.min(1, volume));
    }

    // Método público para activar/desactivar
    enable() {
        this.isEnabled = true;
        this.saveSoundPreference();
        this.updateToggleIcon();
    }

    disable() {
        this.isEnabled = false;
        this.saveSoundPreference();
        this.updateToggleIcon();
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.clickSoundManager = new ClickSoundManager();
});

// Exportar para uso global
window.ClickSoundManager = ClickSoundManager;

















