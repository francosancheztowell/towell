/**
 * Captura de Cortes de Eficiencia (19-02). Vista: modulos/cortes-eficiencia/cortes-eficiencia.blade.php
 * (también en solo lectura desde visualizar-folio). Cablea eventos; la lógica vive en los módulos
 * hermanos y en ../comun/logica.ts.
 */
import { delegate, onReady, qs } from '../../../../utils/dom.ts';
import { HttpError, http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { exigirExito, leerDatos, type RespuestaApi } from '../../comun/pagina.ts';
import { HORARIOS, recortarHora, type CorteDetalle } from '../comun/logica.ts';
import { capturarImagen, finalizar, notificarTelegram } from './acciones.ts';
import {
    actualizarBadgeFolio,
    actualizarEstadoBotonesHeader,
    botones,
    cfg,
    cuerpoTabla,
    estado,
    fijarConfig,
    horaHorario,
    rutaFolio,
    type ConfigCaptura,
} from './estado.ts';
import { folioExistente } from '../comun/folio.ts';
import { guardarAutomatico, irAConsultarPorFolioEnProceso } from './guardado.ts';
import { cargarTurnoActual, tomarHora } from './horarios.ts';
import { abrirObservaciones, instalarObservaciones, precargarFallas } from './observaciones.ts';
import {
    aplicarModoSoloLectura,
    completarStd,
    horarioTomado,
    inputsStd,
    limitarInput,
    marcarErrorStd,
    pintarLineas,
    pintarProgramaStd,
    siguienteInput,
    valorSugerido,
} from './tabla.ts';

interface RespuestaTelares extends RespuestaApi {
    telares?: Record<string, number | string | null>[];
}

interface RespuestaFolio extends RespuestaApi {
    folio?: string;
    turno?: string;
    usuario?: { nombre?: string; numero_empleado?: string };
}

async function cargarDatosTelaresStd(): Promise<void> {
    try {
        const data = await http.get<RespuestaTelares>(cfg().rutas.datosPrograma);
        if (!(data.success && Array.isArray(data.telares))) throw new Error('Respuesta inválida');
        pintarProgramaStd(data.telares);
    } catch {
        marcarErrorStd();
        notify.error('No se pudo cargar Programa Tejido');
    } finally {
        try {
            const historial = await http.get<RespuestaTelares>(cfg().rutas.datosTelares);
            if (historial.success && Array.isArray(historial.telares)) completarStd(historial.telares);
        } catch {
            // Sin historial se quedan los estándares del programa.
        }
    }
}

async function generarNuevoFolio(): Promise<void> {
    try {
        const d = exigirExito(await http.get<RespuestaFolio>(cfg().rutas.generarFolio), 'No se pudo generar folio');
        estado.folio = d.folio ?? null;
        estado.usuario = d.usuario?.nombre ?? '';
        estado.noEmpleado = d.usuario?.numero_empleado ?? '';
        estado.turno = d.turno || estado.turno;
        estado.status = 'En Proceso';
        actualizarBadgeFolio();
        // Con el folio en la URL, recargar sigue editando el mismo.
        if (d.folio) {
            const url = new URL(window.location.href);
            url.searchParams.set('folio', d.folio);
            window.history.replaceState({}, '', url.toString());
        }
    } catch (err) {
        const existente = folioExistente(err);
        if (existente) {
            await irAConsultarPorFolioEnProceso(`Ya existe un folio en proceso: ${existente}. Debe finalizarlo antes de crear uno nuevo.`);
            return;
        }
        await notify.alert('No se pudo generar el folio. Por favor, intente nuevamente.', 'Error', 'error');
        window.location.href = cfg().rutas.consultar;
    }
}

async function cargarCorteExistente(folio: string): Promise<void> {
    const r = exigirExito(await http.get<RespuestaApi & { data?: CorteDetalle }>(rutaFolio(cfg().rutas.corte, folio)), 'No se pudo cargar el corte');
    const info = r.data ?? {};
    estado.folio = info.folio ?? folio;
    estado.fecha = info.fecha || estado.fecha;
    estado.turno = info.turno ? String(info.turno) : estado.turno;
    estado.status = info.status || 'En Proceso';
    estado.usuario = info.usuario || '';
    estado.noEmpleado = info.noEmpleado || '';
    actualizarBadgeFolio();
    for (const h of HORARIOS) {
        const hora = info[`horario_${h}`];
        const span = horaHorario(h);
        if (hora && span) span.textContent = recortarHora(hora);
    }
    if (Array.isArray(info.datos_telares)) pintarLineas(info.datos_telares);
}

function instalarEventos(raiz: HTMLElement): void {
    const soloLectura = cfg().soloLectura;
    const cuerpo = cuerpoTabla();

    delegate<HTMLButtonElement>(raiz, 'click', '[data-accion="tomar-hora"]', (_ev, btn) => {
        void tomarHora(parseInt(btn.dataset.horario ?? '', 10));
    });
    botones.imagen()?.addEventListener('click', () => void capturarImagen());
    botones.telegram()?.addEventListener('click', () => void notificarTelegram());
    botones.finalizar()?.addEventListener('click', () => void finalizar());

    delegate<HTMLInputElement>(cuerpo, 'click', '.obs-checkbox', (_ev, cb) => void abrirObservaciones(cb));
    instalarObservaciones();

    // Inputs de RPM y % EF.
    delegate<HTMLInputElement>(cuerpo, 'focusin', 'input.valor-input', (_ev, input) => {
        if (soloLectura) return;
        const horario = parseInt(input.dataset.horario ?? '', 10);
        if (!horarioTomado(horario)) {
            notify.warning(`Toma primero la hora del horario ${horario}`);
            input.blur();
            return;
        }
        // En 0: sugerir el valor del horario anterior (o el estándar) y seleccionarlo.
        if ((parseInt(input.value, 10) || 0) === 0) {
            const tipo = input.dataset.type === 'rpm' ? 'rpm' : 'eficiencia';
            const sugerido = valorSugerido(input.dataset.telar ?? '', horario, tipo);
            if (sugerido > 0) {
                input.value = String(sugerido);
                input.select();
            }
        } else {
            input.select();
        }
    });
    delegate<HTMLInputElement>(cuerpo, 'input', 'input.valor-input', (_ev, input) => {
        if (!soloLectura) limitarInput(input);
    });
    delegate<HTMLInputElement>(cuerpo, 'focusout', 'input.valor-input', () => {
        if (!soloLectura) guardarAutomatico();
    });
    delegate<HTMLInputElement, KeyboardEvent>(cuerpo, 'keydown', 'input.valor-input', (ev, input) => {
        if (soloLectura || ev.key !== 'Enter') return;
        ev.preventDefault();
        ev.stopPropagation();
        const siguiente = siguienteInput(input);
        guardarAutomatico();
        if (!siguiente) {
            input.blur();
            return;
        }
        siguiente.focus();
        siguiente.select();
    });
    delegate<HTMLInputElement>(cuerpo, 'change', 'input.valor-input', (_ev, input) => {
        if (soloLectura) return;
        input.classList.add('bg-green-100');
        window.setTimeout(() => input.classList.remove('bg-green-100'), 300);
    });

    // Cambios en STD => autoguardado.
    for (const input of inputsStd()) input.addEventListener('input', () => guardarAutomatico());
}

onReady(async () => {
    const raiz = qs('#pagina-cortes');
    const config = leerDatos<ConfigCaptura>(raiz);
    if (!raiz || !config) return;
    fijarConfig(config);

    if (config.aviso) {
        await irAConsultarPorFolioEnProceso(config.aviso);
        return;
    }

    instalarEventos(raiz);
    if (config.soloLectura) aplicarModoSoloLectura();
    actualizarEstadoBotonesHeader();
    await Promise.all([cargarTurnoActual(), cargarDatosTelaresStd()]);

    const folio = config.folioInicial || new URLSearchParams(window.location.search).get('folio');
    try {
        if (folio) {
            await cargarCorteExistente(folio);
        } else if (!config.soloLectura) {
            await generarNuevoFolio();
        } else {
            await notify.alert('No se encontró el folio solicitado.', 'Aviso', 'warning');
            window.location.href = config.rutas.consultar;
        }
    } catch (err) {
        if (!folio && !config.soloLectura) await generarNuevoFolio();
        else if (!(err instanceof HttpError)) console.error('Cortes: no se pudo cargar el corte', err);
    }

    // Catálogo de fallas para abrir rápido el modal de observaciones.
    if (!config.soloLectura) void precargarFallas();
});
