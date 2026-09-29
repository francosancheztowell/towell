/** Botón de reloj de cada horario: toma la hora actual y la guarda en TejEficiencia. */
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { exigirExito, mensajeError, type RespuestaApi } from '../../comun/pagina.ts';
import { horaActual } from '../comun/logica.ts';
import { cfg, estado, horaHorario } from './estado.ts';

interface RespuestaTurno extends RespuestaApi {
    turno?: string | number;
}

export async function cargarTurnoActual(): Promise<void> {
    try {
        const d = await http.get<RespuestaTurno>(cfg().rutas.turnoInfo);
        if (d.success && d.turno) estado.turno = String(d.turno);
    } catch {
        // Sin turno no se guarda la hora: el botón avisa.
    }
}

async function asegurarTurno(): Promise<string> {
    if (!estado.turno) await cargarTurnoActual();
    return estado.turno;
}

export async function tomarHora(h: number): Promise<void> {
    if (cfg().soloLectura) return;
    const turnoVal = await asegurarTurno();
    if (!estado.folio || !turnoVal) {
        notify.warning('Faltan datos internos para guardar hora');
        return;
    }
    const n = parseInt(turnoVal, 10);
    const turno = Number.isFinite(n) ? n : turnoVal;
    const hora = horaActual(new Date());
    try {
        exigirExito(
            await http.post<RespuestaApi>(cfg().rutas.guardarHora, { folio: estado.folio, turno, horario: h, hora, fecha: estado.fecha }),
            'Error al guardar hora',
        );
        const span = horaHorario(h);
        if (span) span.textContent = hora;
        notify.success(`Hora guardada: Horario ${h} - ${hora}`);
    } catch (err) {
        notify.warning(`Hora no guardada (Horario ${h} - ${hora}): ${mensajeError(err, 'No guardado en BD')}`);
    }
}
