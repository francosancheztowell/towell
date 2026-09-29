/**
 * Producción Engomado — modal "Nueva Formulación de Engomado" (#createModal).
 *
 * Nota 19-01: ningún botón de la pantalla ni otro archivo abre este modal (el antiguo
 * window.abrirModalFormulacion no tenía llamadores; "Agregar fórmula" navega a captura
 * de fórmulas). Se conserva tal cual; `abrir` queda disponible como acción
 * data-accion="abrir-formulacion" por si se vuelve a enlazar.
 */
import { cfg } from './contexto.ts';

const modal = (): HTMLElement | null => document.getElementById('createModal');

function ponerValor(id: string, valor: string): void {
    const input = document.getElementById(id) as HTMLInputElement | null;
    if (input) input.value = valor;
}

/** Copia los datos del folio elegido (data-* de la opción) a los campos ocultos del formulario. */
export function cargarDatosPrograma(select: HTMLSelectElement | null): void {
    if (!select) return;
    const opcion = select.options[select.selectedIndex];

    if (!opcion || !opcion.value) {
        for (const id of ['create_cuenta', 'create_calibre', 'create_tipo', 'create_formula']) ponerValor(id, '');
        return;
    }

    ponerValor('create_cuenta', opcion.dataset.cuenta ?? '');
    ponerValor('create_calibre', opcion.dataset.calibre ?? '');
    ponerValor('create_tipo', opcion.dataset.tipo ?? '');
    ponerValor('create_formula', opcion.dataset.formula ?? '');
    ponerValor('create_nom_empl', cfg().usuario.nombre);
    ponerValor('create_cve_empl', cfg().usuario.numero);
}

export function abrirModalFormulacion(): void {
    const m = modal();
    if (!m) return;
    m.classList.remove('hidden');
    m.style.display = 'flex';
    const select = document.getElementById('create_folio_prog') as HTMLSelectElement | null;
    if (select?.value) cargarDatosPrograma(select);
}

export function cerrarModalFormulacion(): void {
    const m = modal();
    if (!m) return;
    m.classList.add('hidden');
    m.style.display = 'none';
}
