/**
 * Finalizar un folio: la revisión de campos vacíos y el POST, compartidos por la captura y
 * por Consultar (antes duplicados en las dos vistas).
 */
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { alertaError, exigirExito, type RespuestaApi } from '../../comun/pagina.ts';
import { revisarLineas, type CorteDetalle } from './logica.ts';

interface RespuestaCorte extends RespuestaApi {
    data?: CorteDetalle;
}

/** Revisa las líneas guardadas del folio. true = se puede seguir. Lanza si no pudo leerlas. */
export async function validarParaFinalizar(urlCorte: string): Promise<boolean> {
    void notify.loading('Validando...');
    let lineas: CorteDetalle['datos_telares'];
    try {
        const r = exigirExito(await http.get<RespuestaCorte>(urlCorte), 'No se pudo obtener el detalle');
        lineas = Array.isArray(r.data?.datos_telares) ? r.data.datos_telares : [];
    } finally {
        notify.close();
    }

    if (!lineas || lineas.length === 0) {
        await notify.alert('No puedes finalizar un folio sin líneas capturadas.', 'No hay líneas', 'warning');
        return false;
    }

    const revision = revisarLineas(lineas);
    if (revision.telares === 0) return true;
    return notify.confirm({
        icon: 'warning',
        title: 'Hay campos sin llenar',
        text: `Hay ${revision.campos} campo(s) vacío(s) o en cero en ${revision.telares} telar(es). ¿Deseas continuar?`,
        confirmText: 'Sí, continuar',
        cancelText: 'No, revisar',
    });
}

export function confirmarFinalizar(folio: string): Promise<boolean> {
    return notify.confirm({
        title: '¿Finalizar Corte de Eficiencia?',
        text: `El folio ${folio} quedará cerrado y no podrá editarse.`,
        confirmText: 'Sí, finalizar',
        confirmColor: '#ea580c',
    });
}

/** POST de finalizar. true si quedó finalizado (ya avisó al usuario en ambos casos). */
export async function finalizarFolio(url: string, mensajeExito: string): Promise<boolean> {
    void notify.loading('Finalizando...');
    try {
        exigirExito(await http.post<RespuestaApi>(url), 'No se pudo finalizar el folio.');
        notify.close();
        await notify.alert(mensajeExito, '¡Finalizado!', 'success');
        return true;
    } catch (err) {
        notify.close();
        alertaError(err, 'No se pudo finalizar el folio.');
        return false;
    }
}
