/**
 * Ventana de reimpresión de la orden de urdido (19-01): cuando el iframe termina de
 * cargar el PDF, abre el diálogo de impresión.
 * Vista: resources/views/modulos/urdido/reimpresion-urdido-popup.blade.php
 * (la sirve ProgramarUrdidoController::reimpresionVentanaImprimir).
 */
const RETARDO_IMPRESION_MS = 600;

function abrirImpresion(): void {
    try {
        window.print();
    } catch (e) {
        console.warn('Print:', e);
    }
}

const iframe = document.getElementById('pdf-frame');
if (iframe instanceof HTMLIFrameElement) {
    iframe.addEventListener('load', () => {
        window.setTimeout(abrirImpresion, RETARDO_IMPRESION_MS);
    });
}
