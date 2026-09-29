/**
 * Producción Engomado — selector de temperatura (Canoa 1/2, Tambor): tira de números 0–100.
 */
import { avisarOficialRequerido, tieneOficial } from './contexto.ts';
import { actualizarCampoProduccion } from './guardado.ts';
import { CAMPO_PRODUCCION, valorInicialSelector } from './logica.ts';

export function cerrarSelectoresCantidad(): void {
    document.querySelectorAll<HTMLElement>('.quantity-edit-container:not(.hidden)').forEach((cont) => {
        const celda = cont.closest('td');
        const display = celda?.querySelector('.quantity-display');
        if (display && display.textContent?.trim() === '') display.textContent = '0';
        cont.classList.add('hidden');
        const btn = celda?.querySelector<HTMLElement>('.edit-quantity-btn');
        if (btn) {
            btn.classList.remove('hidden');
            btn.style.display = '';
        }
    });
}

export function toggleQuantityEdit(boton: HTMLElement): void {
    const celda = boton.closest('td');
    const editor = celda?.querySelector<HTMLElement>('.quantity-edit-container');
    const display = celda?.querySelector<HTMLElement>('.quantity-display');
    const estabaOculto = editor?.classList.contains('hidden') ?? false;

    cerrarSelectoresCantidad();
    if (!editor || !display) return;

    let actual = display.textContent?.trim() ?? '';
    if (actual === '') {
        display.textContent = '0';
        actual = '0';
    }

    // Tras cerrar todos, "estabaOculto" decide si se abre este.
    if (!estabaOculto) return;
    editor.classList.remove('hidden');
    boton.classList.add('hidden');

    const resaltar = valorInicialSelector(display.dataset.field ?? null, actual);
    let elegido: HTMLElement | null = null;
    editor.querySelectorAll<HTMLElement>('.number-option').forEach((op) => {
        const es = op.dataset.value === resaltar;
        if (es) elegido = op;
        op.classList.remove('bg-blue-500', 'text-white', 'bg-gray-100', 'text-gray-700');
        op.classList.add(...(es ? ['bg-blue-500', 'text-white'] : ['bg-gray-100', 'text-gray-700']));
    });
    (elegido as HTMLElement | null)?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
}

export async function elegirNumero(opcion: HTMLElement): Promise<void> {
    const celda = opcion.closest('td');
    const fila = celda?.closest('tr');
    if (!celda) return;
    const valor = opcion.dataset.value ?? '0';

    opcion.closest('.number-scroll-container')?.querySelectorAll<HTMLElement>('.number-option').forEach((o) => {
        o.classList.remove('bg-blue-500', 'text-white');
        o.classList.add('bg-gray-100', 'text-gray-700');
    });
    opcion.classList.remove('bg-gray-100', 'text-gray-700');
    opcion.classList.add('bg-blue-500', 'text-white');

    const editor = celda.querySelector<HTMLElement>('.quantity-edit-container');
    const btn = celda.querySelector<HTMLElement>('.edit-quantity-btn');
    editor?.classList.add('hidden');
    if (btn) {
        btn.classList.remove('hidden');
        btn.style.display = '';
    }

    const display = celda.querySelector<HTMLElement>('.quantity-display');
    if (!display) return;
    const anterior = display.textContent?.trim() || '0';
    display.textContent = valor;

    const nombre = display.dataset.field ?? '';
    const registroId = fila?.dataset.registroId;
    const columna = CAMPO_PRODUCCION[nombre];
    if (!registroId || !columna) return;
    if (!tieneOficial(registroId)) {
        display.textContent = anterior;
        avisarOficialRequerido();
        return;
    }

    btn?.classList.remove('border-red-500', 'border-2', 'ring-2', 'ring-red-300');
    if (btn) btn.style.border = '';

    if (!(await actualizarCampoProduccion(registroId, columna, valor))) display.textContent = anterior;
}
