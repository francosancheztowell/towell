/**
 * Autocompletado de Tamaño (tipeo libre + validación contra el catálogo del tipo de la fila).
 * Al elegir, guarda el tamaño y deriva Cuenta y Calibre ("4112-12/1" → 4112 / 12).
 */
import { delegate } from '../../../utils/dom.ts';
import { notify } from '../../../utils/notifications.ts';
import { el } from '../../urdido/comun/pagina.ts';
import { campo, tipoDeFila, type Dom, type Estado } from './estado.ts';
import { cuentaYCalibreDeTamano, filtrarTamanos, normalizarTipo } from './logica.ts';
import { guardarCampoTelar } from './servidor.ts';

const RESALTADO = 'bg-blue-100';
const SELECTOR_INPUT = 'input[data-field="tamano"]';

function tamanosDeFila(estado: Estado, fila: Element | null): string[] {
    return estado.tamanos[normalizarTipo(tipoDeFila(fila)).toUpperCase()] ?? [];
}

function desplegable(input: HTMLInputElement): HTMLElement | null {
    return input.closest('.tamano-wrapper')?.querySelector<HTMLElement>('.tamano-dropdown') ?? null;
}

function cerrar(input: HTMLInputElement): void {
    desplegable(input)?.classList.add('hidden');
    input.setAttribute('aria-expanded', 'false');
}

function mostrar(input: HTMLInputElement, lista: string[]): void {
    const drop = desplegable(input);
    if (!drop) return;
    drop.replaceChildren(
        ...(lista.length
            ? lista.map((t) =>
                  el('div', {
                      clase: 'px-2 py-1 cursor-pointer hover:bg-blue-50 border-b border-gray-100 last:border-0 leading-tight',
                      texto: t,
                      attrs: { 'data-value': t, role: 'option' },
                  }),
              )
            : [el('div', { clase: 'px-2 py-2 text-gray-400 text-xs text-center', texto: 'Sin resultados' })]),
    );
    // El desplegable es position:fixed → coordenadas de viewport (sin sumar el scroll).
    const rect = input.getBoundingClientRect();
    drop.style.top = `${rect.bottom}px`;
    drop.style.left = `${rect.left}px`;
    drop.style.width = `${rect.width}px`;
    drop.classList.remove('hidden');
    input.setAttribute('aria-expanded', 'true');
}

/**
 * Deriva Cuenta y Calibre del tamaño de la fila. Con `guardar` persiste los que cambiaron
 * (en silencio). Tamaño vacío limpia ambos; formato ajeno no toca nada.
 */
export async function rellenarCuentaYCalibre(estado: Estado, fila: HTMLElement, guardar = true): Promise<void> {
    const cuenta = campo(fila, 'cuenta');
    const calibre = campo(fila, 'calibre');
    const tamano = campo(fila, 'tamano');
    if (!cuenta || !calibre || !tamano) return;

    const derivado = cuentaYCalibreDeTamano(tamano.value);
    if (!derivado) return;

    const cambios: [string, string][] = [];
    if (cuenta.value.trim() !== derivado.cuenta) {
        cuenta.value = derivado.cuenta;
        cambios.push(['cuenta', derivado.cuenta]);
    }
    if (calibre.value.trim() !== derivado.calibre) {
        calibre.value = derivado.calibre;
        cambios.push(['calibre', derivado.calibre]);
    }
    if (!guardar || !fila.dataset.telarId) return;
    for (const [nombre, valor] of cambios) {
        await guardarCampoTelar(estado, nombre, valor, fila, tipoDeFila(fila), { silencioso: true });
    }
}

async function seleccionar(estado: Estado, dom: Dom, input: HTMLInputElement, valor: string): Promise<void> {
    input.value = valor;
    cerrar(input);
    const fila = input.closest('tr');
    if (!fila) return;
    await guardarCampoTelar(estado, 'tamano', valor, fila, tipoDeFila(fila));
    await rellenarCuentaYCalibre(estado, fila);
    // Cuenta/calibre cambian por código: el botón no se entera por 'input'.
    dom.cuerpo.dispatchEvent(new Event('change'));
}

function mover(input: HTMLInputElement, abajo: boolean): void {
    const items = [...(desplegable(input)?.querySelectorAll<HTMLElement>('[data-value]') ?? [])];
    if (!items.length) return;
    const actual = items.findIndex((i) => i.classList.contains(RESALTADO));
    items.forEach((i) => i.classList.remove(RESALTADO));
    let siguiente = abajo ? actual + 1 : actual - 1;
    if (siguiente < 0) siguiente = items.length - 1;
    if (siguiente >= items.length) siguiente = 0;
    const item = items[siguiente]!;
    item.classList.add(RESALTADO);
    item.scrollIntoView({ block: 'nearest' });
}

export function instalarTamano(estado: Estado, dom: Dom): void {
    const cuerpo = dom.cuerpo;
    const filtrar = (input: HTMLInputElement) => mostrar(input, filtrarTamanos(tamanosDeFila(estado, input.closest('tr')), input.value));

    delegate<HTMLInputElement>(cuerpo, 'input', SELECTOR_INPUT, (_e, input) => filtrar(input));
    delegate<HTMLInputElement>(cuerpo, 'focusin', SELECTOR_INPUT, (_e, input) => filtrar(input));

    delegate<HTMLInputElement, KeyboardEvent>(cuerpo, 'keydown', SELECTOR_INPUT, (e, input) => {
        if (e.key === 'Escape') {
            cerrar(input);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const resaltado = desplegable(input)?.querySelector<HTMLElement>(`.${RESALTADO}`);
            if (resaltado?.dataset.value) void seleccionar(estado, dom, input, resaltado.dataset.value);
            else cerrar(input);
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            mover(input, e.key === 'ArrowDown');
        }
    });

    // Al salir: si lo tecleado no está en el catálogo del tipo, se avisa y se limpian tamaño, cuenta y calibre.
    delegate<HTMLInputElement>(cuerpo, 'focusout', SELECTOR_INPUT, (_e, input) => {
        window.setTimeout(() => {
            cerrar(input);
            const fila = input.closest('tr');
            const valor = input.value.trim();
            if (!valor || tamanosDeFila(estado, fila).includes(valor)) return;
            void notify.alert(
                `"${valor}" no coincide con ningún tamaño disponible. Seleccione una opción de la lista.`,
                'Tamaño no válido',
                'warning',
            );
            input.value = '';
            const cuenta = campo(fila, 'cuenta');
            const calibre = campo(fila, 'calibre');
            if (cuenta) cuenta.value = '';
            if (calibre) calibre.value = '';
            cuerpo.dispatchEvent(new Event('change'));
        }, 180);
    });

    // mousedown (no click) para ganarle al blur del input.
    delegate<HTMLElement, MouseEvent>(cuerpo, 'mousedown', '.tamano-dropdown', (e, drop) => {
        e.preventDefault();
        const item = (e.target as Element | null)?.closest<HTMLElement>('[data-value]');
        const input = drop.closest('.tamano-wrapper')?.querySelector<HTMLInputElement>(SELECTOR_INPUT);
        if (item?.dataset.value && input) void seleccionar(estado, dom, input, item.dataset.value);
    });

    document.addEventListener('click', (e) => {
        if ((e.target as Element | null)?.closest?.('.tamano-wrapper')) return;
        cuerpo.querySelectorAll('.tamano-dropdown').forEach((d) => d.classList.add('hidden'));
        cuerpo.querySelectorAll(SELECTOR_INPUT).forEach((i) => i.setAttribute('aria-expanded', 'false'));
    });
}
