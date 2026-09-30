/**
 * Karl Mayer: tablas de resumen (BOM) e inventario seleccionable, con orden por columna y totales.
 */
import { delegate } from '../../../utils/dom.ts';
import { el } from '../../urdido/comun/pagina.ts';
import {
    ordenarMateriales,
    siguienteDireccion,
    type Direccion,
    type MaterialInventario,
} from '../comun/inventario-materiales.ts';
import { filaDetalle, totalesSeleccion } from './logica.ts';

export interface FilaResumen {
    articulo?: unknown;
    config?: unknown;
    consumo?: unknown;
    kilos?: unknown;
}

const COLS_RESUMEN = 4;
const COLS_DETALLE = 14;
const SIN_DATOS = 'Sin datos';
const CLASE_CELDA = 'px-1.5 py-1 text-center';
const CLASE_CHECK = 'chk-detalle-lmat w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500';

/** Fondo alterno por columna (igual que los encabezados). */
const fondo = (i: number): string => (i % 2 === 0 ? 'bg-blue-50' : 'bg-white');

function filaMensaje(colspan: number, mensaje: string): HTMLTableRowElement {
    return el('tr', {}, el('td', { clase: 'px-2 py-3 text-center text-gray-500 text-sm', texto: mensaje, attrs: { colspan: String(colspan) } }));
}

export class TablasInventario {
    private readonly resumen: HTMLTableSectionElement;
    private readonly detalle: HTMLTableSectionElement;
    private readonly encabezados: HTMLTableSectionElement | null;
    private readonly totalRegistros: HTMLElement | null;
    private readonly totalConos: HTMLElement | null;
    private readonly totalKilos: HTMLElement | null;
    private readonly loteProveedor: HTMLInputElement | null;
    private materiales: MaterialInventario[] = [];
    private readonly materialDeFila = new WeakMap<HTMLTableRowElement, MaterialInventario>();
    private orden: { columna: string | null; direccion: Direccion | null } = { columna: null, direccion: null };
    /** Se llama cuando cambia la selección (para re-evaluar el botón Crear). */
    onSeleccion: () => void = () => {};

    constructor(resumen: HTMLTableSectionElement, detalle: HTMLTableSectionElement) {
        this.resumen = resumen;
        this.detalle = detalle;
        this.encabezados = detalle.closest('table')?.tHead ?? null;
        this.totalRegistros = document.getElementById('txt-total-registros');
        this.totalConos = document.getElementById('txt-total-conos');
        this.totalKilos = document.getElementById('txt-total-kilos');
        this.loteProveedor = document.getElementById('input-lote-proveedor') as HTMLInputElement | null;

        delegate<HTMLInputElement>(detalle, 'change', '.chk-detalle-lmat', () => {
            this.actualizarTotales();
            this.onSeleccion();
        });
        if (this.encabezados) {
            delegate<HTMLElement>(this.encabezados, 'click', 'th.sortable', (_e, th) => this.ordenarPor(th.dataset.sort ?? ''));
        }
    }

    pintarResumen(filas: FilaResumen[]): void {
        if (!Array.isArray(filas) || filas.length === 0) {
            this.resumen.replaceChildren(filaMensaje(COLS_RESUMEN, SIN_DATOS));
            return;
        }
        this.resumen.replaceChildren(
            ...filas.map((r) =>
                el(
                    'tr',
                    { clase: 'hover:bg-gray-50' },
                    ...[r.articulo, r.config, r.consumo, r.kilos].map((v) => el('td', { clase: 'px-2 py-1.5 text-center', texto: String(v ?? '') })),
                ),
            ),
        );
    }

    /** Pinta el inventario; conserva lo seleccionado (por ItemId_Serie) salvo que se pida lo contrario. */
    pintarDetalle(materiales: MaterialInventario[], conservarSeleccion = true): void {
        const seleccion = conservarSeleccion ? this.clavesSeleccionadas() : new Set<string>();

        if (!Array.isArray(materiales) || materiales.length === 0) {
            this.materiales = [];
            this.detalle.replaceChildren(filaMensaje(COLS_DETALLE, SIN_DATOS));
            this.actualizarTotales();
            return;
        }

        this.materiales = materiales;
        this.detalle.replaceChildren(
            ...ordenarMateriales(materiales, this.orden.columna, this.orden.direccion).map((m) => this.fila(m, seleccion)),
        );
        this.pintarIconosOrden();
        this.actualizarTotales();
    }

    limpiar(): void {
        this.pintarResumen([]);
        this.pintarDetalle([]);
        if (this.loteProveedor) this.loteProveedor.value = '';
    }

    /** Materiales marcados, en el orden en que se ven. */
    seleccionados(): MaterialInventario[] {
        return this.filasMarcadas()
            .map((tr) => this.materialDeFila.get(tr))
            .filter((m): m is MaterialInventario => m !== undefined);
    }

    hayFilas(): boolean {
        return this.detalle.querySelector('tr[data-id]') !== null;
    }

    private fila(m: MaterialInventario, seleccion: Set<string>): HTMLTableRowElement {
        const d = filaDetalle(m);
        const check = el('input', {
            clase: CLASE_CHECK,
            attrs: { type: 'checkbox', 'data-id': d.clave, 'aria-label': `Seleccionar ${d.celdas[0] ?? ''} serie ${d.celdas[7] ?? ''}`.trim() },
        });
        check.checked = seleccion.has(d.clave);

        const tr = el(
            'tr',
            { clase: 'hover:bg-blue-50' },
            ...d.celdas.map((texto, i) => el('td', { clase: `${CLASE_CELDA} ${fondo(i)}`, texto })),
            el('td', { clase: `${CLASE_CELDA} ${fondo(d.celdas.length)}` }, check),
        );
        tr.dataset.id = d.clave;
        tr.dataset.conos = String(d.conos);
        tr.dataset.kilos = String(d.kilos);
        tr.dataset.lote = d.lote;
        this.materialDeFila.set(tr, m);
        return tr;
    }

    private filasMarcadas(): HTMLTableRowElement[] {
        return Array.from(this.detalle.querySelectorAll<HTMLInputElement>('.chk-detalle-lmat:checked'))
            .map((chk) => chk.closest('tr'))
            .filter((tr): tr is HTMLTableRowElement => tr !== null);
    }

    private clavesSeleccionadas(): Set<string> {
        return new Set(this.filasMarcadas().map((tr) => tr.dataset.id ?? '').filter(Boolean));
    }

    private ordenarPor(columna: string): void {
        if (!columna) return;
        this.orden = { columna, direccion: siguienteDireccion(this.orden, columna) };
        if (this.materiales.length) {
            this.pintarDetalle(this.materiales);
        } else {
            this.pintarIconosOrden();
        }
    }

    private pintarIconosOrden(): void {
        this.encabezados?.querySelectorAll<HTMLElement>('th.sortable').forEach((th) => {
            th.classList.remove('sort-asc', 'sort-desc');
            const activo = th.dataset.sort === this.orden.columna && this.orden.direccion;
            if (activo) th.classList.add(this.orden.direccion === 'asc' ? 'sort-asc' : 'sort-desc');
            th.setAttribute('aria-sort', activo ? (this.orden.direccion === 'asc' ? 'ascending' : 'descending') : 'none');
        });
    }

    private actualizarTotales(): void {
        const t = totalesSeleccion(
            this.filasMarcadas().map((tr) => ({
                conos: parseInt(tr.dataset.conos ?? '', 10) || 0,
                kilos: parseFloat(tr.dataset.kilos ?? '') || 0,
                lote: (tr.dataset.lote ?? '').trim(),
            })),
        );
        if (this.totalRegistros) this.totalRegistros.textContent = t.registros;
        if (this.totalConos) this.totalConos.textContent = t.conos;
        if (this.totalKilos) this.totalKilos.textContent = t.kilos;
        if (this.loteProveedor) this.loteProveedor.value = t.lote;
    }
}
