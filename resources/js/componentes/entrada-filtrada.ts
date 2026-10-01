/**
 * Campos que solo admiten lo que les corresponde, mientras se escribe o se pega (la validación
 * de verdad sigue en el servidor; esto evita capturar basura y enterarse hasta guardar).
 *
 *   <input data-solo="decimal" data-decimales="4" data-enteros="14">  dígitos y un punto, N decimales
 *   <input data-solo="entero" maxlength="4">                          solo dígitos
 *   <input data-solo="texto">                                          letras, números, espacio . -
 *
 * Escucha en fase de captura sobre document: corre antes que wire:model, así Livewire recibe
 * el valor ya limpio. Sirve para cualquier input, también los que llegan después (delegación).
 */

export interface OpcionesFiltro {
    decimales?: number | undefined;
    enteros?: number | undefined;
}

/** Deja solo lo permitido por el tipo. */
export function filtrarEntrada(valor: string, tipo: string, opciones: OpcionesFiltro = {}): string {
    if (tipo === 'entero') return valor.replace(/\D+/g, '');
    if (tipo === 'texto') return valor.replace(/[^\p{L}\p{N} .-]+/gu, '').replace(/ {2,}/g, ' ');
    if (tipo !== 'decimal') return valor;

    const limpio = valor.replace(',', '.').replace(/[^\d.]+/g, '');
    const punto = limpio.indexOf('.');
    let entera = (punto === -1 ? limpio : limpio.slice(0, punto)).replace(/\./g, '');
    let fraccion = punto === -1 ? null : limpio.slice(punto + 1).replace(/\./g, '');

    if (opciones.enteros !== undefined) entera = entera.slice(0, opciones.enteros);
    if (fraccion !== null && opciones.decimales !== undefined) {
        fraccion = opciones.decimales === 0 ? null : fraccion.slice(0, opciones.decimales);
    }

    return fraccion === null ? entera : `${entera}.${fraccion}`;
}

function alEscribir(e: Event): void {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || !input.dataset.solo) return;

    const numero = (v: string | undefined): number | undefined => (v === undefined || v === '' ? undefined : Number(v));
    let limpio = filtrarEntrada(input.value, input.dataset.solo, {
        decimales: numero(input.dataset.decimales),
        enteros: numero(input.dataset.enteros),
    });
    // maxlength solo frena el tecleo; un valor pegado por script o autocompletado puede pasarlo.
    if (input.maxLength > 0) limpio = limpio.slice(0, input.maxLength);
    if (limpio === input.value) return;

    // Conserva el cursor: se mueve tantas posiciones como caracteres se quitaron antes de él.
    const cursor = input.selectionStart ?? limpio.length;
    const quitados = input.value.length - limpio.length;
    input.value = limpio;
    const pos = Math.max(0, cursor - quitados);
    input.setSelectionRange(pos, pos);
}

/** Una vez por documento. */
export function escucharEntradasFiltradas(): void {
    document.addEventListener('input', alEscribir, true);
}
