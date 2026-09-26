/**
 * Producción Engomado — guardado campo por campo de la fila y de la orden (merma).
 * Todos respetan las guardas de contexto.ts (permiso, fila "Listo", oficial requerido).
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { exigirExito, mensajeError, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import { calcularNetoFila, campo, cfg, filaDe, puedeEditarFila } from './contexto.ts';
import { pintarOficialesEnCelda } from './oficiales.ts';
import { parsearOficiales, valorParaCampo } from './logica.ts';

interface RespuestaPesos extends RespuestaApi {
    data?: { kg_bruto?: number | null; kg_neto?: number | null };
}

export const aviso = (err: unknown, porDefecto: string): void => void notify.alert(mensajeError(err, porDefecto), 'Error', 'error');
export const decimales = (v: unknown, d: number): string => (v === null || v === undefined ? '' : parseFloat(String(v)).toFixed(d));

// ─── Llamadas al backend ─────────────────────────────────────────────────

export async function actualizarFecha(registroId: string, fecha: string): Promise<void> {
    if (!puedeEditarFila(registroId)) return;
    try {
        exigirExito(await http.post<RespuestaApi>(cfg().rutas.actualizarFecha, { registro_id: registroId, fecha }), 'Error al actualizar la fecha');
        notify.success('La fecha ha sido actualizada correctamente');
    } catch (err) {
        aviso(err, 'Error al actualizar la fecha. Por favor, intenta nuevamente.');
    }
}

export async function actualizarTurnoOficial(registroId: string, numeroOficial: number | string, turno: string): Promise<void> {
    if (!puedeEditarFila(registroId, false)) return;
    try {
        exigirExito(
            await http.post<RespuestaApi>(cfg().rutas.actualizarTurnoOficial, { registro_id: registroId, numero_oficial: numeroOficial, turno }),
            'Error al actualizar el turno',
        );
        notify.success('El turno ha sido actualizado correctamente');
        const fila = filaDe(registroId);
        const celda = fila?.querySelector<HTMLElement>('.oficial-texto');
        if (celda) {
            const oficiales = parsearOficiales(celda.dataset.oficialesJson);
            const oficial = oficiales.find((o) => parseInt(String(o.numero), 10) === parseInt(String(numeroOficial), 10));
            if (oficial) {
                oficial.turno = turno;
                pintarOficialesEnCelda(celda, oficiales);
            }
        }
        const select = fila?.querySelector<HTMLSelectElement>('select[data-field="turno"]');
        if (select) select.value = turno;
    } catch (err) {
        aviso(err, 'Error al actualizar el turno. Por favor, intenta nuevamente.');
    }
}

/** true si el servidor guardó el valor. */
export async function actualizarKgBruto(registroId: string, kgBruto: string): Promise<boolean> {
    if (!puedeEditarFila(registroId)) return false;
    const fila = filaDe(registroId);
    try {
        const r = exigirExito(
            await http.post<RespuestaPesos>(cfg().rutas.actualizarKgBruto, {
                registro_id: registroId,
                kg_bruto: kgBruto !== '' ? parseFloat(kgBruto).toFixed(2) : null,
            }),
            'No se pudo actualizar Kg. Bruto',
        );
        notify.success('Kg. Bruto actualizado correctamente');
        if (fila && r.data) {
            const bruto = campo(fila, 'kg_bruto');
            const neto = campo(fila, 'kg_neto');
            if (bruto && r.data.kg_bruto !== undefined && r.data.kg_bruto !== null) bruto.value = decimales(r.data.kg_bruto, 2);
            if (neto) neto.value = decimales(r.data.kg_neto, 2);
        }
        return true;
    } catch (err) {
        aviso(err, 'Error al actualizar Kg. Bruto. Por favor, intenta nuevamente.');
        if (fila) calcularNetoFila(fila);
        return false;
    }
}

export async function actualizarJulioTara(registroId: string, noJulio: string, tara: number | null, netoCalculado: number | null): Promise<void> {
    if (!puedeEditarFila(registroId)) return;
    try {
        const r = exigirExito(
            await http.post<RespuestaPesos>(cfg().rutas.actualizarJulioTara, { registro_id: registroId, no_julio: noJulio || null, tara }),
            'Error al actualizar No. Julio y Tara',
        );
        notify.success('No. Julio y Tara actualizados correctamente');
        const neto = filaDe(registroId) ? campo(filaDe(registroId)!, 'kg_neto') : null;
        if (neto) {
            const valor = r.data && r.data.kg_neto !== undefined ? r.data.kg_neto : netoCalculado;
            neto.value = decimales(valor, 2);
        }
    } catch (err) {
        aviso(err, 'Error al actualizar No. Julio y Tara. Por favor, intenta nuevamente.');
    }
}

export async function actualizarHora(registroId: string, columna: 'HoraInicial' | 'HoraFinal', valor: string | null): Promise<void> {
    if (!puedeEditarFila(registroId)) return;
    try {
        const r = exigirExito(
            await http.post<RespuestaApi>(cfg().rutas.actualizarHoras, { registro_id: registroId, campo: columna, valor }),
            'Error al actualizar la hora',
        );
        notify.success(r.message || 'La hora ha sido actualizada correctamente');
    } catch (err) {
        aviso(err, 'Error al actualizar la hora. Por favor, intenta nuevamente.');
    }
}

/** Solidos, Canoa1/2, Humedad, Ubicacion, Roturas. Devuelve true si se guardó. */
export async function actualizarCampoProduccion(registroId: string, columna: string, valor: string | null): Promise<boolean> {
    if (!puedeEditarFila(registroId)) return false;
    try {
        const r = exigirExito(
            await http.post<RespuestaApi>(cfg().rutas.actualizarCamposProduccion, {
                registro_id: registroId,
                campo: columna,
                valor: valorParaCampo(columna, valor),
            }),
            'Error al actualizar campo',
        );
        notify.success(r.message || 'Campo actualizado correctamente');
        return true;
    } catch (err) {
        aviso(err, 'Error al actualizar campo. Por favor, intenta nuevamente.');
        return false;
    }
}

/** Merma con/sin goma (campos de la orden, no de la fila). */
export async function actualizarCampoOrden(nombre: string, valor: number | null): Promise<void> {
    const orden = cfg().orden;
    if (!orden) return;
    try {
        const r = exigirExito(
            await http.post<RespuestaApi>(cfg().rutas.actualizarCampoOrden, { orden_id: orden.id, campo: nombre, valor }),
            'Error al actualizar campo',
        );
        notify.success(r.message || 'Campo actualizado correctamente');
    } catch (err) {
        aviso(err, 'Error al actualizar campo. Por favor, intenta nuevamente.');
    }
}
