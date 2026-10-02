/**
 * Duplicar / Dividir desde la grilla: el modal es el componente Livewire
 * planeacion.programa-tejido.duplicar-dividir (montado en partials/complementos.blade.php).
 * Aquí solo se abre con el Id de la fila y, al guardar, se pinta el resultado sin recargar.
 */
import { notify } from '../utils/notifications.ts';

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
    /** redirectToRegistro: agrega/actualiza las filas devueltas. */
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

export function escucharGuardado(p: Posproceso, destino: EventTarget = window): void {
    destino.addEventListener('pt-duplicar-guardado', (e) => {
        const data = (e as CustomEvent<RespuestaDuplicar>).detail ?? {};
        procesarGuardado(data, p).catch((err: unknown) => {
            console.error('[PT] post-proceso de Duplicar/Dividir:', err);
            notify.warning('Se guardó, pero la tabla no se pudo actualizar. Recarga la página.');
        });
    });
}
