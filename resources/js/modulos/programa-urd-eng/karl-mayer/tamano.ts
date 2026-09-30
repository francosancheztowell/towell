/**
 * Karl Mayer: autocompletar de Tamaño (tipeo libre, flechas/Enter/Esc y validación contra el catálogo).
 */
import { notify } from '../../../utils/notifications.ts';
import { el } from '../../urdido/comun/pagina.ts';
import { filtrarTamanos, tamanoInvalido } from './logica.ts';

const CLASE_OPCION = 'px-2 py-1 cursor-pointer hover:bg-blue-50 border-b border-gray-100 last:border-0 leading-tight';
const RESALTADO = 'bg-blue-100';

export interface OpcionesTamano {
    input: HTMLInputElement;
    lista: HTMLElement;
    /** Tras elegir o invalidar un tamaño (rellenar Cuenta/Calibre y re-evaluar el botón). */
    alCambiar: () => void;
    /** Al descartar un tamaño inválido (limpiar Cuenta/Calibre). */
    alInvalidar: () => void;
}

export function autocompletarTamano({ input, lista, alCambiar, alInvalidar }: OpcionesTamano): { opciones: (valores: string[]) => void } {
    let opciones: string[] = [];

    const posicionar = (): void => {
        const r = input.getBoundingClientRect();
        lista.style.top = `${r.bottom + window.scrollY}px`;
        lista.style.left = `${r.left + window.scrollX}px`;
        lista.style.width = `${r.width}px`;
    };

    const cerrar = (): void => lista.classList.add('hidden');

    const pintar = (valores: string[]): void => {
        if (!valores.length) {
            lista.replaceChildren(el('div', { clase: 'px-2 py-2 text-gray-400 text-xs text-center', texto: 'Sin resultados' }));
        } else {
            lista.replaceChildren(
                ...valores.map((t) => el('div', { clase: CLASE_OPCION, texto: t, attrs: { 'data-value': t, role: 'option' } })),
            );
        }
        posicionar();
        lista.classList.remove('hidden');
    };

    const elegir = (valor: string): void => {
        input.value = valor;
        cerrar();
        alCambiar();
    };

    const mostrarFiltradas = (): void => pintar(filtrarTamanos(opciones, input.value));

    input.addEventListener('input', mostrarFiltradas);
    input.addEventListener('focus', mostrarFiltradas);

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            cerrar();
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            const resaltada = lista.querySelector<HTMLElement>(`.${RESALTADO}`);
            if (resaltada?.dataset.value) elegir(resaltada.dataset.value);
            else cerrar();
            return;
        }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const items = Array.from(lista.querySelectorAll<HTMLElement>('[data-value]'));
            if (!items.length) return;
            const actual = items.findIndex((i) => i.classList.contains(RESALTADO));
            items.forEach((i) => i.classList.remove(RESALTADO));
            let siguiente = e.key === 'ArrowDown' ? actual + 1 : actual - 1;
            if (siguiente < 0) siguiente = items.length - 1;
            if (siguiente >= items.length) siguiente = 0;
            items[siguiente]?.classList.add(RESALTADO);
            items[siguiente]?.scrollIntoView({ block: 'nearest' });
        }
    });

    input.addEventListener('blur', () => {
        // Deja pasar el mousedown de la lista antes de validar.
        window.setTimeout(() => {
            cerrar();
            if (!tamanoInvalido(input.value, opciones)) return;
            const valor = input.value.trim();
            void notify.alert(
                `"${valor}" no coincide con ningún tamaño disponible. Seleccione una opción de la lista.`,
                'Tamaño no válido',
                'warning',
            );
            input.value = '';
            alInvalidar();
        }, 180);
    });

    lista.addEventListener('mousedown', (e) => {
        e.preventDefault();
        const item = (e.target as Element | null)?.closest<HTMLElement>('[data-value]');
        if (item?.dataset.value !== undefined) elegir(item.dataset.value);
    });

    document.addEventListener('click', (e) => {
        const t = e.target as Element | null;
        if (!t?.closest?.(`#${input.id}`) && !t?.closest?.(`#${lista.id}`)) cerrar();
    });

    return {
        opciones: (valores: string[]) => {
            opciones = valores;
        },
    };
}
