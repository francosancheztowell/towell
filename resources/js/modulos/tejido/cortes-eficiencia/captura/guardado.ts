/**
 * Autoguardado del corte: un guardado a la vez y el aviso flotante "Guardado automáticamente".
 */
import { debounce } from '../../../../utils/format.ts';
import { HttpError, http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { exigirExito, mensajeError, type RespuestaApi } from '../../comun/pagina.ts';
import { folioExistente } from '../comun/folio.ts';
import { actualizarBadgeFolio, cfg, estado } from './estado.ts';
import { leerHorarios, recopilarDatosTelares } from './tabla.ts';

interface RespuestaStore extends RespuestaApi {
    folio?: string;
    folio_existente?: string;
}

/** Aviso de "Folio en proceso" y a Consultar (mismo flujo que antes). */
export async function irAConsultarPorFolioEnProceso(mensaje: string): Promise<void> {
    await notify.alert(mensaje, 'Folio en proceso', 'warning');
    window.location.href = cfg().rutas.consultar;
}

export function avisoFlotante(texto: string, esError = false): void {
    const n = document.getElementById(esError ? 'notificacion-error-guardado' : 'notificacion-guardado');
    if (!n) return;
    const span = n.querySelector('[data-texto]');
    if (span) span.textContent = texto;
    n.classList.replace('opacity-0', 'opacity-100');
    window.setTimeout(() => n.classList.replace('opacity-100', 'opacity-0'), esError ? 3000 : 2000);
}

async function guardarAhora(mostrarAviso: boolean): Promise<void> {
    // Un folio finalizado ya no se guarda: store() lo trataría como nuevo y generaría otro folio.
    if (cfg().soloLectura || estado.status === 'Finalizado') return;
    if (!estado.folio || !estado.fecha || !estado.turno) return;
    const datos = recopilarDatosTelares();
    if (!datos.length) return;
    const horarios = leerHorarios();
    const payload = {
        folio: estado.folio,
        fecha: estado.fecha,
        turno: estado.turno,
        status: estado.status,
        usuario: estado.usuario,
        noEmpleado: estado.noEmpleado,
        datos_telares: datos,
        horario1: horarios[1],
        horario2: horarios[2],
        horario3: horarios[3],
    };
    try {
        const r = exigirExito(await http.post<RespuestaStore>(cfg().rutas.store, payload), 'Error al guardar');
        // El backend puede haber generado un folio distinto al sugerido.
        if (r.folio && r.folio !== estado.folio) {
            estado.folio = r.folio;
            actualizarBadgeFolio();
        }
        if (mostrarAviso) avisoFlotante('Guardado automáticamente');
    } catch (err) {
        const existente = folioExistente(err);
        if (existente) {
            const cuerpo = ((err as HttpError).data ?? {}) as RespuestaStore;
            await irAConsultarPorFolioEnProceso(cuerpo.message || `Ya existe un folio en proceso: ${existente}`);
            return;
        }
        if (mostrarAviso) avisoFlotante(`Error: ${mensajeError(err, 'Error al guardar')}`, true);
        throw err;
    }
}

// Un guardado a la vez: si llega otro mientras uno está en vuelo, se agenda uno solo más (lee la
// tabla al salir, así lleva lo último). Encimados se bloqueaban entre sí en TejEficienciaLine.
let enVuelo: Promise<void> | null = null;
let pendiente: Promise<void> | null = null;

export function guardarEnServidor(mostrarAviso = true): Promise<void> {
    if (!enVuelo) {
        enVuelo = guardarAhora(mostrarAviso).finally(() => {
            enVuelo = null;
        });
        return enVuelo;
    }
    const actual = enVuelo;
    pendiente ??= actual
        .catch(() => {})
        .then(() => {
            pendiente = null;
            return guardarEnServidor(mostrarAviso);
        });
    return pendiente;
}

export const guardarAutomatico = debounce(() => {
    guardarEnServidor(true).catch(() => {});
}, 900);
