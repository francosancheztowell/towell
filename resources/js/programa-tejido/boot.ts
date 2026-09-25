/**
 * Valores que solo conoce el servidor (rutas, columnas, capacidades de la superficie).
 *
 * La vista los imprime como datos, no como código: un
 * <script type="application/json" id="pt-boot"> en scripts/main.blade.php. Este módulo
 * lo lee una sola vez y lo deja también en window.PT_BOOT para el código que lo busca ahí.
 * Va primero entre los imports de index.js: los módulos que lo usan al evaluarse
 * (marbetes, recalcular) lo encuentran ya resuelto.
 */
type PtBoot = NonNullable<Window['PT_BOOT']>;

export function leerBoot(doc: Pick<Document, 'getElementById'> = document): Partial<PtBoot> {
    const nodo = doc.getElementById('pt-boot');
    if (nodo?.textContent) {
        try {
            return JSON.parse(nodo.textContent) as PtBoot;
        } catch (e) {
            console.error('[PT] #pt-boot no es JSON válido:', e);
        }
    }

    return window.PT_BOOT ?? {};
}

export const PT_BOOT = leerBoot();
window.PT_BOOT = PT_BOOT as PtBoot;
