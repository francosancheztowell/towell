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
 * Cálculos del modal en el navegador, con las mismas reglas que FilasDestino (PHP). En los dos
 * modos se captura el pedido y el saldo es texto: saldo = pedido × (1 + %seg/100) − producción.
 * El saldo y el cuadre se ven al escribir, sin ir al servidor; al guardar el servidor vuelve a
 * calcular el saldo desde el pedido y valida el cuadre de Dividir.
 */

/** Lo que el modal usa de $wire. */
export interface WireFilas {
    $get(ruta: string): unknown;
    $set(ruta: string, valor: unknown, live?: boolean): unknown;
}

interface FilaCalculo {
    pedido?: unknown;
    produccion?: unknown;
    porcSeg?: unknown;
    registroId?: unknown;
}

const TOLERANCIA_SALDO = 0.5;

const aNumero = (v: unknown): number => Number.parseFloat(String(v ?? '').replace(/,/g, '')) || 0;
// ponytail: en medios centavos exactos (p. ej. 1.005) puede diferir 0.01 del round() de PHP; el
// cuadre tolera ±0.5. Si importa, redondear sobre el texto decimal.
const redondear = (n: number): number => Math.round(n * 100) / 100;
const factor = (porcSeg: unknown): number => 1 + Math.max(0, aNumero(porcSeg)) / 100;
/** FilasDestino::saldo: sin recortar a 0 (negativo = pedido menor a lo ya producido). */
const saldoDe = (f: FilaCalculo): number => redondear(aNumero(f.pedido) * factor(f.porcSeg) - aNumero(f.produccion));

/** El saldo de la fila como lo muestra la columna Saldo: sin decimales (son piezas). */
export function saldo(fila: FilaCalculo): string {
    return formatNumber(saldoDe(fila), 0);
}

/** Σ saldos contra el disponible: cuadra con ±0.5 y sin saldos negativos. */
export function cuadre(filas: FilaCalculo[], disponible: number): { asignado: string; estado: string; cuadra: boolean } {
    const saldos = filas.map(saldoDe);
    const asignado = redondear(saldos.reduce((a, b) => a + b, 0));
    const diferencia = redondear(asignado - disponible);
    const cuadra = Math.abs(diferencia) <= TOLERANCIA_SALDO && saldos.every((s) => s >= 0);
    const estado = cuadra ? 'Cuadra' : `${diferencia < 0 ? 'Faltan' : 'Sobran'} ${formatNumber(Math.abs(diferencia), 0)}`;
    return { asignado: formatNumber(asignado, 0), estado, cuadra };
}

/** x-data del indicador de Dividir: `c` se recalcula cada vez que cambia un pedido o un %seg. */
export function cuadreVivo(disponible: number): { $wire?: Pick<WireFilas, '$get'>; readonly c: ReturnType<typeof cuadre> } {
    return {
        get c() {
            return cuadre((this.$wire?.$get('filas.dividir') as FilaCalculo[] | undefined) ?? [], disponible);
        },
    };
}

/**
 * Dividir: al teclear el pedido o el %seg de una fila, el registro abierto (el original) toma la
 * diferencia para que Σ saldos siga igual al disponible. Su propio pedido se corrige a mano.
 */
export function absorber(wire: WireFilas, i: number, campo: 'pedido' | 'porcSeg', valor: string): void {
    if (wire.$get('modo') !== 'dividir') return;
    const origen = (wire.$get('origen') ?? {}) as { id?: unknown; disponible?: unknown };
    const filas = ((wire.$get('filas.dividir') as FilaCalculo[] | undefined) ?? []).map((f, j) => (j === i ? { ...f, [campo]: valor } : f));
    const k = filas.findIndex((f) => f.registroId === origen.id);
    const original = filas[k];
    if (!original || k === i) return;
    const otros = filas.reduce((suma, f, j) => (j === k ? suma : suma + saldoDe(f)), 0);
    const pedido = (aNumero(origen.disponible) - otros + aNumero(original.produccion)) / factor(original.porcSeg);
    wire.$set(`filas.dividir.${k}.pedido`, String(redondear(pedido)), false);
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
