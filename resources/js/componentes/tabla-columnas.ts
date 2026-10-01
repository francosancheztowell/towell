/**
 * Filtros por columna para cualquier tabla (flux:table, <table>, x-ui.table): basta poner
 * `data-filtros-columna` en el <table>. Agrega bajo el encabezado una fila con un input por
 * columna (`th[data-sin-filtro]` = sin input) y oculta las filas que no coinciden: "contiene",
 * sin mayúsculas ni acentos, AND entre columnas. Una <th> por columna (sin colspan).
 *
 * Oculta con [data-filtro-col-oculta], no con `hidden`: así convive con los filtros propios de
 * cada pantalla (que usan `hidden`) sin pisarse. .tabla-cebra (app.css) ignora ambas.
 * Las filas con td[colspan] (vacío, "sin resultados") no se filtran.
 * Si el filtro deja la tabla sin filas visibles, pinta "Sin coincidencias" (salvo que la
 * pantalla ya muestre su propio aviso). Se re-aplica sola cuando cambian las filas (Livewire,
 * JS de la pantalla) o su `hidden`. En Livewire, el <thead> lleva wire:ignore: si no, el
 * morph quita la fila de inputs.
 * La fila de filtros nace oculta; la muestra/oculta cualquier botón con
 * `data-alternar-filtros="<selector del table>"` (aria-pressed). Al ocultarla se limpian los
 * filtros: así nunca quedan filas escondidas por un filtro que no se ve.
 * Emite `tabla:filtrada` ({ visibles, total }) en el <table>.
 */
import { debounce } from '../utils/format.ts';
import { delegate } from '../utils/dom.ts';

export function normalizar(texto: string): string {
    return texto.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();
}

/** filtros[i] = texto del input de la columna i ('' = sin filtro). */
export function celdasCoinciden(celdas: string[], filtros: string[]): boolean {
    return filtros.every((f, i) => normalizar(f) === '' || normalizar(celdas[i] ?? '').includes(normalizar(f)));
}

const iniciadas = new WeakSet<HTMLTableElement>();
let alternadorListo = false;

/** Un solo listener en document para todos los botones data-alternar-filtros. */
function escucharAlternador(): void {
    if (alternadorListo) return;
    alternadorListo = true;
    delegate(document, 'click', '[data-alternar-filtros]', (_e, boton) => {
        const tabla = document.querySelector<HTMLTableElement>(boton.dataset.alternarFiltros ?? '');
        const fila = tabla?.querySelector<HTMLTableRowElement>('tr[data-fila-filtros]');
        if (!tabla || !fila) return;

        fila.hidden = !fila.hidden;
        boton.setAttribute('aria-pressed', String(!fila.hidden));
        if (fila.hidden) {
            fila.querySelectorAll('input').forEach((i) => { i.value = ''; });
            fila.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            fila.querySelector('input')?.focus();
        }
    });
}

function avisoVacio(tabla: HTMLTableElement, mostrar: boolean): void {
    const actual = tabla.querySelector('tr[data-filtro-vacio]');
    if (!mostrar) {
        actual?.remove();
        return;
    }
    if (actual) return;
    const tr = (tabla.tBodies[0] ?? tabla.createTBody()).insertRow();
    tr.dataset.filtroVacio = '';
    const td = tr.insertCell();
    td.colSpan = tabla.tHead?.rows[0]?.cells.length ?? 1;
    td.className = 'py-8 text-center text-slate-500';
    td.textContent = 'Sin coincidencias con los filtros de columna';
}

function aplicar(tabla: HTMLTableElement, inputs: (HTMLInputElement | null)[]): void {
    const filtros = inputs.map((i) => i?.value ?? '');
    const activo = filtros.some((f) => f.trim() !== '');
    let visibles = 0;
    let total = 0;
    let otroAviso = false;
    for (const cuerpo of tabla.tBodies) {
        for (const tr of cuerpo.rows) {
            if (tr.querySelector('td[colspan]')) {
                otroAviso ||= !tr.hidden && !tr.hasAttribute('data-filtro-vacio');
                continue;
            }
            total++;
            const ok = celdasCoinciden([...tr.cells].map((c) => c.textContent ?? ''), filtros);
            tr.toggleAttribute('data-filtro-col-oculta', !ok);
            if (ok && !tr.hidden) visibles++;
        }
    }
    avisoVacio(tabla, activo && visibles === 0 && !otroAviso);
    tabla.dispatchEvent(new CustomEvent('tabla:filtrada', { detail: { visibles, total } }));
}

export function iniciarFiltrosColumna(root: ParentNode = document): void {
    root.querySelectorAll<HTMLTableElement>('table[data-filtros-columna]').forEach((tabla) => {
        const encabezado = tabla.tHead?.rows[0];
        if (iniciadas.has(tabla) || !encabezado) return;
        iniciadas.add(tabla);

        escucharAlternador();
        const fila = tabla.tHead!.insertRow();
        fila.dataset.filaFiltros = '';
        fila.hidden = true;
        const inputs = [...encabezado.cells].map((th) => {
            const celda = document.createElement('th');
            fila.append(celda);
            if (th.hasAttribute('data-sin-filtro')) return null;

            const input = document.createElement('input');
            input.type = 'search';
            input.className = 'tabla-filtro-input';
            input.placeholder = 'Filtrar';
            input.setAttribute('aria-label', `Filtrar ${th.textContent?.trim() ?? ''}`);
            celda.append(input);
            return input;
        });

        // Re-aplica si otro código cambia filas o su hidden. Lo que muta aplicar() se descarta
        // con takeRecords(), si no el observer se dispararía a sí mismo.
        // Se observa el <table> y no el <tbody>: Livewire puede reemplazar el <tbody> entero.
        const observador = new MutationObserver(() => reaplicar());
        const reaplicar = debounce(() => {
            aplicar(tabla, inputs);
            observador.takeRecords();
        }, 150);

        fila.addEventListener('input', reaplicar);
        observador.observe(tabla, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'data-filtro-col-oculta'] });
    });
}
