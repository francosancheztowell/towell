/**
 * Autocompletar de BOM (L.Mat Urdido de cada fila y L.Mat Engomado): lista flotante bajo el
 * input, con flechas/Enter/Esc. Misma conducta que el setupAutocomplete del JS viejo.
 */
import { debounce } from '../../../utils/format.ts';
import { delegate } from '../../../utils/dom.ts';
import { http } from '../../../utils/http.ts';
import { conQuery, enBlanco } from './logica.ts';

export interface SugerenciaBom {
    BOMID: string;
    NAME?: string | null;
}

interface OpcionesAutocompletar {
    raiz: HTMLElement;
    selector: string;
    ruta: () => string;
    idLista: string;
    alElegir: (input: HTMLInputElement, s: SugerenciaBom) => void;
}

function posicionar(input: HTMLElement, lista: HTMLElement): void {
    const r = input.getBoundingClientRect();
    lista.style.top = `${r.bottom + window.scrollY + 4}px`;
    lista.style.left = `${r.left + window.scrollX}px`;
    lista.style.width = `${r.width}px`;
}

export function autocompletarBom({ raiz, selector, ruta, idLista, alElegir }: OpcionesAutocompletar): void {
    let lista = document.getElementById(idLista);
    if (!lista) {
        lista = document.createElement('div');
        lista.id = idLista;
        lista.setAttribute('role', 'listbox');
        lista.className = 'fixed z-[99999] bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto';
        document.body.appendChild(lista);
    }
    const contenedor = lista;
    let activo: HTMLInputElement | null = null;
    let indice = -1;
    let abierta = false;

    const opciones = (): HTMLElement[] => [...contenedor.querySelectorAll<HTMLElement>('[role="option"]')];
    const marcar = (): void => opciones().forEach((o, i) => o.classList.toggle('bg-blue-100', i === indice));
    const ocultar = (): void => {
        contenedor.classList.add('hidden');
        contenedor.replaceChildren();
        abierta = false;
        indice = -1;
        activo = null;
    };

    const pintar = (input: HTMLInputElement, items: SugerenciaBom[]): void => {
        contenedor.replaceChildren(
            ...items.map((s, i) => {
                const op = document.createElement('div');
                op.setAttribute('role', 'option');
                op.className = 'px-3 py-2 hover:bg-blue-50 cursor-pointer text-xs border-gray-100';
                op.textContent = `${s.BOMID} - ${s.NAME || ''}`;
                op.addEventListener('click', () => {
                    if (activo) alElegir(activo, s);
                    ocultar();
                });
                op.addEventListener('mouseenter', () => {
                    indice = i;
                    marcar();
                });
                return op;
            }),
        );
        activo = input;
        posicionar(input, contenedor);
        contenedor.classList.remove('hidden');
        abierta = true;
    };

    const buscar = debounce(async (q: string, input: HTMLInputElement) => {
        if (enBlanco(q)) {
            ocultar();
            return;
        }
        try {
            const datos = await http.get<SugerenciaBom[] | { data?: SugerenciaBom[] }>(conQuery(ruta(), { q: q.trim() }));
            const items = Array.isArray(datos) ? datos : datos.data || [];
            if (!items.length) ocultar();
            else pintar(input, items);
        } catch {
            ocultar();
        }
    }, 300);

    delegate<HTMLInputElement>(raiz, 'input', selector, (_e, input) => buscar(input.value, input));
    delegate<HTMLInputElement>(raiz, 'focusin', selector, (_e, input) => {
        if (!enBlanco(input.value)) buscar(input.value, input);
    });
    delegate<HTMLInputElement, KeyboardEvent>(raiz, 'keydown', selector, (e) => {
        if (!abierta) return;
        const items = opciones();
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            indice = Math.min(indice + 1, items.length - 1);
            items[indice]?.scrollIntoView({ block: 'nearest' });
            marcar();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            indice = Math.max(indice - 1, -1);
            marcar();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const elegido = indice >= 0 ? items[indice] : undefined;
            if (elegido) elegido.click();
            else ocultar();
        } else if (e.key === 'Escape') {
            ocultar();
        }
    });

    const reubicar = (): void => {
        if (activo && abierta) posicionar(activo, contenedor);
    };
    window.addEventListener('scroll', reubicar, true);
    window.addEventListener('resize', reubicar);
    document.addEventListener(
        'click',
        (e) => {
            const t = e.target;
            if (activo && t instanceof Node && !activo.contains(t) && !contenedor.contains(t)) ocultar();
        },
        true,
    );
}
