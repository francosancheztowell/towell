/**
 * Generador de sonidos usando Web Audio API
 * Crea sonidos de click sin necesidad de archivos externos
 */

// Función para generar un sonido de click
function generateClickSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const buffer = audioContext.createBuffer(1, audioContext.sampleRate * 0.08, audioContext.sampleRate);
        const data = buffer.getChannelData(0);

        for (let i = 0; i < buffer.length; i++) {
            // Generar un sonido de click suave y corto
            const t = i / audioContext.sampleRate;
            data[i] = Math.sin(2 * Math.PI * 800 * t) * Math.exp(-t * 20) * 0.15;
        }

        return { audioContext, buffer };
    } catch (error) {
        return null;
    }
}

// Función para reproducir el sonido
function playClickSound() {
    const sound = generateClickSound();
    if (!sound) return;

    try {
        const source = sound.audioContext.createBufferSource();
        const gainNode = sound.audioContext.createGain();

        source.buffer = sound.buffer;
        gainNode.gain.value = 0.1; // Volumen suave y discreto

        source.connect(gainNode);
        gainNode.connect(sound.audioContext.destination);

        source.start();
    } catch (error) {

    }
}

// Función para generar sonido de hover
function generateHoverSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const buffer = audioContext.createBuffer(1, audioContext.sampleRate * 0.04, audioContext.sampleRate);
        const data = buffer.getChannelData(0);

        for (let i = 0; i < buffer.length; i++) {
            const t = i / audioContext.sampleRate;
            data[i] = Math.sin(2 * Math.PI * 600 * t) * Math.exp(-t * 25) * 0.08;
        }

        return { audioContext, buffer };
    } catch (error) {
        return null;
    }
}

// Función para reproducir sonido de hover
function playHoverSound() {
    const sound = generateHoverSound();
    if (!sound) return;

    try {
        const source = sound.audioContext.createBufferSource();
        const gainNode = sound.audioContext.createGain();

        source.buffer = sound.buffer;
        gainNode.gain.value = 0.02; // Volumen muy suave para hover

        source.connect(gainNode);
        gainNode.connect(sound.audioContext.destination);

        source.start();
    } catch (error) {
    }
}

// Exportar funciones para uso global
window.playClickSound = playClickSound;
window.playHoverSound = playHoverSound;




































































