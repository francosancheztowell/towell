/**
 * Filtro por valores de una columna de Saldos 2026 (tipo Excel). Antes era un Swal con HTML
 * armado a mano; ahora x-ui.modal-base #modalSaldosFiltro + <template> del Blade.
 */
import { abrir, cerrarPorId } from '../../../../componentes/dialog.ts';
import type { ValorConteo } from './logica.ts';

const ID = 'modalSaldosFiltro';
const CLASE_TODOS = ['bg-green-100', 'text-green-700', 'hover:bg-green-200'];
const CLASE_ALGUNOS = ['bg-blue-100', 'text-blue-700', 'hover:bg-blue-200'];

/** seleccion null = "Limpiar filtro" de esa columna. */
export type AlAplicar = (col: number, seleccion: string[] | null, totalOpciones: number) => void;

export interface ModalFiltro {
    abrir(col: number, etiqueta: string, valores: ValorConteo[], seleccionActual: string[] | null): void;
}

export function crearModalFiltro(alAplicar: AlAplicar): ModalFiltro {
    const dialog = document.getElementById(ID);
    const titulo = document.getElementById(`${ID}-titulo`);
    const columna = dialog?.querySelector<HTMLElement>('[data-filtro-columna]');
    const lista = dialog?.querySelector<HTMLElement>('[data-filtro-lista]');
    const vacio = dialog?.querySelector<HTMLElement>('[data-filtro-vacio]');
    const botonTodos = dialog?.querySelector<HTMLButtonElement>('[data-filtro-accion="todos"]');
    const plantilla = document.getElementById('saldos-filtro-opcion') as HTMLTemplateElement | null;

    let col = 0;
    let total = 0;

    const casillas = (): HTMLInputElement[] => Array.from(lista?.querySelectorAll<HTMLInputElement>('input[type="checkbox"]') ?? []);

    const pintarTodos = (): void => {
        if (!botonTodos) return;
        const todas = casillas().length > 0 && casillas().every((c) => c.checked);
        botonTodos.textContent = todas ? 'Todos seleccionados' : 'Seleccionar todo';
        botonTodos.classList.remove(...(todas ? CLASE_ALGUNOS : CLASE_TODOS));
        botonTodos.classList.add(...(todas ? CLASE_TODOS : CLASE_ALGUNOS));
    };

    const marcar = (valor: boolean): void => {
        casillas().forEach((c) => {
            c.checked = valor;
            c.closest('label')?.classList.toggle('bg-blue-50', valor);
        });
        pintarTodos();
    };

    dialog?.addEventListener('change', (ev) => {
        const c = ev.target;
        if (!(c instanceof HTMLInputElement) || c.type !== 'checkbox') return;
        c.closest('label')?.classList.toggle('bg-blue-50', c.checked);
        pintarTodos();
    });

    dialog?.addEventListener('click', (ev) => {
        const boton = (ev.target as Element).closest<HTMLElement>('[data-filtro-accion]');
        switch (boton?.dataset.filtroAccion) {
            case 'todos':
                marcar(true);
                break;
            case 'ninguno':
                marcar(false);
                break;
            case 'aplicar':
                alAplicar(
                    col,
                    casillas()
                        .filter((c) => c.checked)
                        .map((c) => c.value),
                    total,
                );
                cerrarPorId(ID);
                break;
            case 'limpiar':
                alAplicar(col, null, total);
                cerrarPorId(ID);
                break;
        }
    });

    return {
        abrir(columnaIdx, etiqueta, valores, seleccionActual) {
            if (!lista || !plantilla) return;
            col = columnaIdx;
            total = valores.length;
            if (titulo) titulo.textContent = `Filtrar: ${etiqueta}`;
            if (columna) columna.textContent = etiqueta;
            const permitido = seleccionActual ? new Set(seleccionActual) : null;
            lista.replaceChildren(
                ...valores.map(({ valor, conteo }) => {
                    const nodo = plantilla.content.firstElementChild?.cloneNode(true) as HTMLElement;
                    const input = nodo.querySelector('input') as HTMLInputElement;
                    const marcado = permitido === null || permitido.has(valor);
                    input.value = valor;
                    input.checked = marcado;
                    nodo.classList.toggle('bg-blue-50', marcado);
                    const texto = nodo.querySelector<HTMLElement>('[data-texto]');
                    if (texto) {
                        texto.textContent = valor.length > 40 ? `${valor.slice(0, 40)}…` : valor;
                        texto.title = valor;
                    }
                    const n = nodo.querySelector<HTMLElement>('[data-conteo]');
                    if (n) n.textContent = `(${conteo})`;
                    return nodo;
                }),
            );
            if (vacio) vacio.hidden = valores.length > 0;
            pintarTodos();
            abrir(ID);
        },
    };
}
