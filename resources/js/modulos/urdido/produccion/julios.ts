/**
 * Catálogo de julios y selects "No. Julio": un julio elegido en una fila desaparece de las
 * demás. Una sola implementación de "ocupados" / "rellenar select" (logica.ts).
 */
import { http } from '../../../utils/http.ts';
import type { RespuestaApi } from '../comun/pagina.ts';
import { ctx, campo, marcarCampoError } from './contexto.ts';
import { juliosOcupados, netoDe, netoFueraDeRango, opcionesJulio, peso, type JulioCatalogo } from './logica.ts';

let catalogo: JulioCatalogo[] = [];

function selects(): HTMLSelectElement[] {
    return Array.from(ctx.tabla?.querySelectorAll<HTMLSelectElement>('select.select-julio') ?? []);
}

/** Rehace las opciones del select sin los `ocupados` y deja seleccionado `valor` si sigue disponible. */
function rellenar(select: HTMLSelectElement, ocupados: Set<string>, valor: string): void {
    while (select.options.length > 1) select.remove(1);
    for (const op of opcionesJulio(catalogo, ocupados)) {
        const opcion = new Option(op.valor, op.valor);
        opcion.dataset.tara = op.tara;
        select.add(opcion);
    }
    select.value = valor;
    if (select.value !== valor) select.value = '';
}

/** Recalcula todas las filas con los valores que tienen ahora. */
export function actualizarTodosLosSelectsJulios(): void {
    const todos = selects();
    const valores = todos.map((s) => s.value);
    todos.forEach((s, i) => rellenar(s, juliosOcupados(valores, i), valores[i] ?? ''));
}

/** Tara (número o null) de la opción elegida del select. */
export function taraSeleccionada(select: HTMLSelectElement): number | null {
    const t = select.options[select.selectedIndex]?.dataset.tara;
    return t !== undefined && t !== '' ? parseFloat(peso(t)) : null;
}

/** Carga el catálogo y pinta los selects con el julio guardado de cada fila (data-valor-inicial). */
export async function cargarCatalogosJulios(): Promise<void> {
    try {
        const r = await http.get<RespuestaApi & { data?: JulioCatalogo[] }>(ctx.cfg.rutas.catalogoJulios);
        if (!(r.success && r.data)) {
            console.error('Error al cargar catálogo de julios:', r.error || 'Error desconocido');
            return;
        }
        catalogo = r.data;
    } catch (error) {
        console.error('Error al cargar catálogo de julios:', error);
        return;
    }

    const todos = selects();
    const iniciales = todos.map((s) => s.dataset.valorInicial ?? '');
    todos.forEach((select, i) => {
        const inicial = iniciales[i] ?? '';
        rellenar(select, juliosOcupados(iniciales, i), inicial);
        select.dataset.valorAnterior = select.value;
        if (!inicial || !select.value) return;

        const fila = select.closest('tr');
        const tara = fila ? campo(fila, 'tara') : null;
        if (!fila || !tara) return;
        tara.value = peso(select.options[select.selectedIndex]?.dataset.tara ?? '0');
        const bruto = campo(fila, 'kg_bruto');
        const neto = campo(fila, 'kg_neto');
        if (bruto && neto) {
            const n = netoDe(bruto.value, tara.value);
            neto.value = n.toFixed(2);
            marcarCampoError(neto, netoFueraDeRango(n, ctx.cfg.maxKgNeto));
        }
    });
}
