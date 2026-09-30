/**
 * Finalizar paro (19-08). Antes: <script> inline de finalizar-paro/index.blade.php.
 * El id viaja en la URL (?id=) para sobrevivir a un F5.
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { leerDatos, mensajeError, ocultarBotonParo, rutaCon, soloPlaceholder } from '../comun/pagina.ts';
import type { RespuestaApi } from '../comun/pagina.ts';
import { relojLocal } from '../comun/fechas.ts';
import { acotarCalidad, MAX_CALIDAD, payloadCierre, validarCierre } from './logica.ts';

interface ConfigFinalizar {
    rutas: { paro: string; finalizar: string; operadores: string; solicitudes: string; nuevoParo: string };
}

interface Paro {
    Depto?: string;
    MaquinaId?: string;
    TipoFallaId?: string;
    Falla?: string;
    Descripcion?: string;
    OrdenTrabajo?: string;
    NomAtendio?: string;
    TurnoAtendio?: number | string;
    Calidad?: number | string | null;
    ObsCierre?: string;
}

interface Operador {
    NomEmpl?: string;
    Turno?: number | string;
}

function iniciar(): void {
    const raiz = document.getElementById('pagina-finalizar-paro');
    const cfg = leerDatos<ConfigFinalizar>(raiz);
    const form = document.getElementById('form-finalizar-paro') as HTMLFormElement | null;
    if (!raiz || !cfg || !form) return;

    const $ = <T extends HTMLElement>(id: string): T => document.getElementById(id) as T;
    const campo = (id: string): HTMLInputElement => $<HTMLInputElement>(id);
    const selAtendio = $<HTMLSelectElement>('atendio');
    const inputTurno = campo('turno');
    const grupoCalidad = $('calidad-stars');
    const fieldsetCalidad = $('calidad-fieldset');
    const contadorCalidad = $('calidad-value');
    const btnFinalizar = $<HTMLButtonElement>('btn-aceptar');
    const radios = Array.from(form.querySelectorAll<HTMLInputElement>('input[name="calidad"]'));

    ocultarBotonParo(cfg.rutas.nuevoParo);

    const paroId = new URLSearchParams(window.location.search).get('id');
    if (!paroId) {
        window.location.href = cfg.rutas.solicitudes;
        return;
    }
    campo('paro_id').value = paroId;

    const calidadSeleccionada = (): number => acotarCalidad(radios.find((r) => r.checked)?.value);
    const actualizarContador = (): void => {
        contadorCalidad.textContent = `${calidadSeleccionada()}/${MAX_CALIDAD}`;
    };
    const marcarCalidadInvalida = (invalida: boolean): void => {
        grupoCalidad.classList.toggle('calidad-invalida', invalida);
        if (invalida) fieldsetCalidad.setAttribute('aria-invalid', 'true');
        else fieldsetCalidad.removeAttribute('aria-invalid');
    };
    radios.forEach((r) =>
        r.addEventListener('change', () => {
            actualizarContador();
            marcarCalidadInvalida(false);
        }),
    );

    selAtendio.addEventListener('change', () => {
        inputTurno.value = selAtendio.selectedOptions[0]?.dataset.turno ?? '';
    });

    async function cargarOperadores(): Promise<void> {
        try {
            const r = await http.get<RespuestaApi<Operador[]>>(cfg!.rutas.operadores);
            const previo = selAtendio.value;
            soloPlaceholder(selAtendio, 'Seleccione un operador');
            (r.data ?? []).forEach((op) => {
                const o = new Option(op.NomEmpl ?? '', op.NomEmpl ?? '');
                if (op.Turno) o.dataset.turno = String(op.Turno);
                selAtendio.add(o);
            });
            if (previo) selAtendio.value = previo;
        } catch (err) {
            notify.error(mensajeError(err, 'No se pudieron cargar los operadores.'));
        }
    }

    async function cargarParo(id: string): Promise<void> {
        try {
            const r = await http.get<RespuestaApi<Paro>>(rutaCon(cfg!.rutas.paro, { __ID__: id }));
            const paro = r.data ?? {};
            const { fecha, hora } = relojLocal();
            campo('fecha').value = fecha;
            campo('hora').value = hora;
            campo('depto').value = paro.Depto ?? '';
            campo('maquina').value = paro.MaquinaId ?? '';
            campo('tipo_falla').value = paro.TipoFallaId ?? '';
            campo('falla').value = paro.Falla ?? '';
            campo('descrip').value = paro.Descripcion ?? '';
            campo('orden_trabajo').value = paro.OrdenTrabajo ?? '';
            if (paro.NomAtendio) selAtendio.value = paro.NomAtendio;
            if (paro.TurnoAtendio) inputTurno.value = String(paro.TurnoAtendio);
            if (paro.Calidad !== null && paro.Calidad !== undefined) {
                const calidad = acotarCalidad(paro.Calidad);
                const radio = radios.find((x) => Number(x.value) === calidad);
                if (radio) radio.checked = true;
                actualizarContador();
            }
            if (paro.ObsCierre) $<HTMLTextAreaElement>('obs_cierre').value = paro.ObsCierre;
        } catch (err) {
            await notify.alert(mensajeError(err, 'Error al cargar los datos del paro.'), 'Error', 'error');
            window.location.href = cfg!.rutas.solicitudes;
        }
    }

    void cargarParo(paroId);
    void cargarOperadores();

    $('btn-cancelar').addEventListener('click', () => {
        window.location.href = cfg.rutas.solicitudes;
    });

    // Un doble toque mandaba dos PUT y dos avisos de Telegram: el botón se bloquea.
    let enviando = false;
    const textoBoton = btnFinalizar.textContent ?? 'Finalizar';
    const bloquear = (si: boolean): void => {
        enviando = si;
        btnFinalizar.disabled = si;
        btnFinalizar.textContent = si ? 'Enviando...' : textoBoton;
    };

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (enviando) return;

        const datos = {
            atendio: selAtendio.value,
            turno: inputTurno.value,
            calidad: calidadSeleccionada(),
            obsCierre: $<HTMLTextAreaElement>('obs_cierre').value,
        };
        const problema = validarCierre(datos);
        if (problema) {
            if (problema.campo === 'calidad') marcarCalidadInvalida(true);
            await notify.alert(problema.mensaje, 'Campo requerido', 'warning');
            (problema.campo === 'atendio' ? selAtendio : $('calidad-1')).focus();
            return;
        }

        bloquear(true);
        try {
            const r = await http.put<RespuestaApi>(rutaCon(cfg!.rutas.finalizar, { __ID__: paroId }), payloadCierre(datos));
            // Como antes: el aviso se va solo a los 2 s y se vuelve a Solicitudes,
            // donde quien cierra varios paros seguidos sigue con la lista.
            await Promise.race([
                notify.alert(r.message ?? 'El paro ha sido finalizado correctamente', 'Paro finalizado', 'success'),
                new Promise((listo) => window.setTimeout(listo, 2000)),
            ]);
            window.location.href = cfg!.rutas.solicitudes;
        } catch (err) {
            bloquear(false);
            void notify.alert(mensajeError(err, 'Error al finalizar el paro. Por favor, intenta nuevamente.'), 'Error', 'error');
        }
    });
}

iniciar();
