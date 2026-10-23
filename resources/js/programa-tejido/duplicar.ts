/**
 * Duplicar / Dividir desde la grilla: el modal es el componente Livewire
 * planeacion.programa-tejido.duplicar-dividir (montado en partials/complementos.blade.php).
 * Aquí solo se abre con el Id de la fila y, al guardar, se pinta el resultado sin recargar.
 */
import { notify } from '../utils/notifications.ts';
import { formatNumber } from '../utils/format.ts';

export interface RespuestaDuplicar {
    success?: boolean;
    message?: string;
    modo?: string;
    registro_id_original?: number | string | null;
    registros_ids?: Array<number | string>;
    advertencias?: { tipo?: string; [k: string]: unknown } | null;
    [k: string]: unknown;
}

export interface Posproceso {
    /** insertarFilasNuevas: agrega las filas nuevas que trae registros_datos. */
    insertar(data: RespuestaDuplicar): Promise<void>;
    /** actualizarRegistrosPorIds: refresca fechas/balanceo de las filas tocadas. */
    refrescar(ids: string[]): Promise<void>;
    advertenciaHtml(message: string | undefined, advertencias: unknown): string;
}

export function abrirDuplicar(fila: Element | null, livewire: Pick<NonNullable<typeof window.Livewire>, 'dispatch'> | null = window.Livewire ?? null): boolean {
    const id = Number(fila?.closest('tr.selectable-row')?.getAttribute('data-id') ?? fila?.getAttribute('data-id'));
    if (!livewire || !Number.isInteger(id) || id <= 0) {
        notify.error('No se pudo abrir Duplicar/Dividir para esta fila.');
        return false;
    }
    livewire.dispatch('pt-duplicar-abrir', { id });
    return true;
}

/** Dividir: el original y las filas nuevas (las únicas cuyas fechas cambiaron). */
export function idsTocados(data: RespuestaDuplicar): string[] {
    if (data.modo !== 'dividir') return [];
    const ids = [data.registro_id_original, ...(data.registros_ids ?? [])];
    return [...new Set(ids.filter((id) => id != null && id !== '').map(String))];
}

function mensajeExito(modo: string | undefined): string {
    if (modo === 'dividir') return 'Registro dividido correctamente';
    return modo === 'vincular' ? 'Registro vinculado correctamente' : 'Telar duplicado correctamente';
}

export async function procesarGuardado(data: RespuestaDuplicar, p: Posproceso): Promise<void> {
    if (data.advertencias?.tipo === 'calendario_sin_fechas') {
        await notify.html(p.advertenciaHtml(data.message, data.advertencias), 'Duplicación completada con advertencias', 'warning');
    } else {
        notify.success(data.message || mensajeExito(data.modo));
    }
    await p.insertar(data);
    const ids = idsTocados(data);
    if (ids.length > 0) await p.refrescar(ids);
}

/*
 * Cálculos del modal en el navegador, con las mismas reglas que FilasDestino (PHP): el saldo,
 * el pedido derivado y el cuadre se ven al escribir, sin ir al servidor. El servidor vuelve a
 * calcular el saldo al recibir pedido/%seg (updatedFilas) y valida el cuadre al guardar.
 */

/** Lo que el modal usa de $wire. */
export interface WireFilas {
    $get(ruta: string): unknown;
    $set(ruta: string, valor: unknown, live?: boolean): unknown;
}

interface FilaCuadre {
    saldo?: unknown;
    produccion?: unknown;
    porcSeg?: unknown;
}

const TOLERANCIA_SALDO = 0.5;

const aNumero = (v: unknown): number => Number.parseFloat(String(v ?? '').replace(/,/g, '')) || 0;
// ponytail: en medios centavos exactos (p. ej. 1.005) puede diferir 0.01 del round() de PHP; se
// guarda el saldo que se ve, así que no descuadra. Si importa, redondear sobre el texto decimal.
const redondear = (n: number): number => Math.round(n * 100) / 100;

/** Regla 3, sin producción: saldo = pedido × (1 + %seg/100). "1050", "1050.5" como FilasDestino::numero. */
export function saldoDuplicar(pedido: unknown, porcSeg: unknown): string {
    return String(redondear(aNumero(pedido) * (1 + aNumero(porcSeg) / 100)));
}

/** Regla 4: pedido = (saldo + producción) / (1 + %seg/100), con el formato de la vista. */
export function pedidoDerivado(fila: FilaCuadre): string {
    const porcSeg = Math.max(0, aNumero(fila.porcSeg));
    return formatNumber(redondear((aNumero(fila.saldo) + aNumero(fila.produccion)) / (1 + porcSeg / 100)), 2);
}

/** Σ saldos contra el disponible: cuadra con ±0.5 y sin saldos negativos. */
export function cuadre(filas: FilaCuadre[], disponible: number): { asignado: string; estado: string; cuadra: boolean } {
    const saldos = filas.map((f) => aNumero(f.saldo));
    const asignado = redondear(saldos.reduce((a, b) => a + b, 0));
    const diferencia = redondear(asignado - disponible);
    const cuadra = Math.abs(diferencia) <= TOLERANCIA_SALDO && saldos.every((s) => s >= 0);
    const estado = cuadra ? 'Cuadra' : `${diferencia < 0 ? 'Faltan' : 'Sobran'} ${formatNumber(Math.abs(diferencia), 2)}`;
    return { asignado: formatNumber(asignado, 2), estado, cuadra };
}

/** Al teclear pedido o %seg de una fila de Duplicar: su saldo se recalcula solo en el cliente. */
export function alEscribir(wire: WireFilas, base: string, campo: 'pedido' | 'porcSeg', valor: string): void {
    // En Dividir el saldo lo captura el planeador y el pedido se deriva: no se toca.
    if (base.startsWith('filas.dividir.')) return;
    const fila = { pedido: wire.$get(`${base}.pedido`), porcSeg: wire.$get(`${base}.porcSeg`), [campo]: valor };
    wire.$set(`${base}.saldo`, saldoDuplicar(fila.pedido, fila.porcSeg), false);
}

/** x-data del indicador de Dividir: `c` se recalcula cada vez que cambia un saldo. */
export function cuadreVivo(disponible: number): { $wire?: WireFilas; readonly c: ReturnType<typeof cuadre> } {
    return {
        get c() {
            return cuadre((this.$wire?.$get('filas.dividir') as FilaCuadre[] | undefined) ?? [], disponible);
        },
    };
}

export function escucharGuardado(p: Posproceso, destino: EventTarget = window): void {
    destino.addEventListener('pt-duplicar-guardado', (e) => {
        const data = (e as CustomEvent<RespuestaDuplicar>).detail ?? {};
        procesarGuardado(data, p).catch((err: unknown) => {
            console.error('[PT] post-proceso de Duplicar/Dividir:', err);
            notify.warning('Se guardó, pero la tabla no se pudo actualizar. Recarga la página.');
        });
    });
}
