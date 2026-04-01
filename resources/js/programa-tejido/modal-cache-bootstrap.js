/**
 * Programa tejido — modal duplicar/dividir: flag de depuración.
 * En consola: window.__PT_DEBUG = true antes de abrir el modal para ver ptDebugLog en Blade.
 */
if (typeof window !== 'undefined' && window.__PT_DEBUG !== true) {
	window.__PT_DEBUG = false;
}
