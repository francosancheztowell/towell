/**
 * Panel "Devolución" de calificar atadores: Jacquard/SMIT (un julio) y Karl Mayer (una fila por julio
 * del atado anterior de la barra). Marcar el check crea la devolución; cada cambio se autoguarda;
 * desmarcarlo la elimina (con confirmación).
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { exigirOk, mensajeError, ponerValor, valorDe, type RespuestaAtadores } from '../comun/pagina.ts';
import { julioASeleccionar, payloadDevolucion } from './logica.ts';
import type { ConfigCalificar } from './tipos.ts';

interface RegistroJulio {
    julio?: unknown;
    lote?: unknown;
    cuenta?: unknown;
    calibre?: unknown;
    hilo?: unknown;
    tipo?: unknown;
}

interface DatosJulios {
    julios: unknown[];
    sugerido: string | null;
    registros: RegistroJulio[];
}

const CAMPOS_AUTOGUARDADO = ['dev_telar', 'dev_ubicacion', 'dev_cuenta', 'dev_no_julio', 'dev_metros', 'dev_calibre', 'dev_tipo', 'dev_kilos', 'dev_fecha', 'dev_hilo', 'dev_obs'];
const CAMPOS_FORMULARIO = ['dev_ubicacion', 'dev_cuenta', 'dev_lote', 'dev_no_julio', 'dev_metros', 'dev_calibre', 'dev_kilos', 'dev_fecha', 'dev_hilo', 'dev_obs'];

const $ = <T extends HTMLElement = HTMLElement>(id: string): T | null => document.getElementById(id) as T | null;
const texto = (v: unknown): string => String(v ?? '').trim();

function asegurarOpcion(sel: HTMLSelectElement, valor: string): void {
    if (![...sel.options].some((o) => o.value === valor)) sel.add(new Option(valor, valor));
}

export function iniciarDevolucion(cfg: ConfigCalificar): void {
    const atado = cfg.atado;
    if (!atado) return;
    const devGuardada = cfg.devolucion;
    const bloqueadaPorAx = Boolean(devGuardada?.bloqueada_por_ax);
    let registrada = devGuardada !== null;
    let enCurso = false;
    let pendiente = false;
    let eliminando = false;
    let temporizador: ReturnType<typeof setTimeout> | undefined;
    let ubicacionesCargadas = false;
    const juliosPorTelar: Record<string, DatosJulios> = {};

    const estado = (mensaje: string, clase = 'text-gray-500'): void => {
        const el = $('estadoAutoguardadoDevolucion');
        if (!el) return;
        el.textContent = mensaje;
        el.className = `mt-4 text-right text-xs ${clase}`;
    };

    // Telar y Tipo van deshabilitados; Lote solo readonly para que se siga enviando al cambiar de Julio.
    const bloquearCamposFijos = (): void => {
        for (const id of ['dev_telar', 'dev_tipo']) {
            const campo = $<HTMLSelectElement>(id);
            if (!campo) continue;
            campo.disabled = true;
            campo.classList.add('bg-gray-100', 'text-gray-600', 'cursor-not-allowed');
            campo.classList.remove('focus:ring-2', 'focus:ring-blue-500');
        }
        const lote = $<HTMLInputElement>('dev_lote');
        if (lote) {
            lote.readOnly = true;
            lote.disabled = false;
            lote.classList.add('bg-gray-100', 'text-gray-600', 'cursor-not-allowed');
        }
    };

    const payload = (): Record<string, unknown> => {
        if (cfg.esKm) {
            const filas = [...document.querySelectorAll<HTMLTableRowElement>('#devKmBody tr[data-julio]')].map((tr) => {
                const campo = (c: string): string => tr.querySelector<HTMLInputElement>(`[data-campo="${c}"]`)?.value ?? '';
                return {
                    no_julio: tr.dataset.julio ?? '',
                    no_produccion: tr.dataset.orden ?? '',
                    cuenta: campo('cuenta'), calibre: campo('calibre'), hilo: campo('hilo'),
                    metros: campo('metros'), kilos: campo('kilos'), obs: campo('obs'),
                };
            });
            return {
                ref_id: atado.refId,
                fecha_devol: valorDe('dev_fecha_km') || null,
                anterior_id: valorDe('dev_anterior_km') || null,
                filas,
            };
        }
        const valores: Record<string, string> = {};
        for (const id of [...CAMPOS_AUTOGUARDADO, 'dev_lote']) valores[id] = valorDe(id);
        return payloadDevolucion(atado.refId, valores);
    };

    const sincronizar = async ({ mostrarError = true, permitirCrear = false } = {}): Promise<boolean> => {
        if (bloqueadaPorAx || eliminando || !atado.refId || (!registrada && !permitirCrear)) return false;
        if (enCurso) {
            pendiente = true;
            return false;
        }
        enCurso = true;
        estado('Guardando cambios...', 'text-blue-600');
        try {
            exigirOk(await http.post<RespuestaAtadores>(cfg.rutas.devolucionGuardar, payload()), 'No se pudo guardar la devolución.');
            registrada = true;
            const check = $<HTMLInputElement>('chkDevolucion');
            if (check) check.checked = true;
            estado('Cambios guardados.', 'text-green-600');
            return true;
        } catch (err) {
            estado('No se guardaron los cambios.', 'text-red-600');
            if (mostrarError) void notify.alert(mensajeError(err, 'No se pudo guardar la devolución.'), 'Error', 'error');
            return false;
        } finally {
            enCurso = false;
            if (pendiente) {
                pendiente = false;
                programar(0);
            }
        }
    };

    const programar = (retraso = 600): void => {
        if (!registrada || bloqueadaPorAx || eliminando) return;
        clearTimeout(temporizador);
        temporizador = setTimeout(() => {
            if (registrada && !eliminando) void sincronizar();
        }, retraso);
    };

    const aplicarDatosJulio = (r: RegistroJulio): void => {
        ponerValor('dev_cuenta', r.cuenta);
        ponerValor('dev_calibre', r.calibre);
        ponerValor('dev_hilo', r.hilo);
        // Lote = DEV + NoProduccion del julio elegido (no del atado en calificación).
        ponerValor('dev_lote', r.lote ?? '');
        const tipo = texto(r.tipo);
        const sel = $<HTMLSelectElement>('dev_tipo');
        if (sel && tipo) {
            asegurarOpcion(sel, tipo);
            sel.value = tipo;
        }
        bloquearCamposFijos();
        programar();
    };

    const limpiarDatosJulio = (): void => {
        for (const id of ['dev_cuenta', 'dev_calibre', 'dev_hilo', 'dev_lote']) ponerValor(id, '');
        ponerValor('dev_tipo', '');
        bloquearCamposFijos();
    };

    // La validación de disponible está pausada (el panel dev_disponibilidad está comentado en la vista):
    // solo se quitan los topes de kilos/metros que ponía, como antes.
    const limpiarDisponibilidad = (): void => {
        $('dev_kilos')?.removeAttribute('max');
        $('dev_metros')?.removeAttribute('max');
    };

    const alCambiarJulio = (julio: string): void => {
        const valor = texto(julio);
        if (!valor) {
            // Al deseleccionar no se deja Lote/Cuenta viejos ni se guarda un Julio NULL con ellos.
            limpiarDatosJulio();
            const sel = $<HTMLSelectElement>('dev_tipo');
            if (sel && atado.tipo) {
                asegurarOpcion(sel, atado.tipo);
                sel.value = atado.tipo;
            }
            bloquearCamposFijos();
            programar();
            return;
        }
        const op = [...($<HTMLSelectElement>('dev_no_julio')?.options ?? [])].find((o) => texto(o.value) === valor);
        // Preferir data-* de la opción (no depende del caché por telar).
        if (op?.dataset.lote) {
            aplicarDatosJulio({ ...op.dataset });
            return;
        }
        const telar = valorDe('dev_telar') || texto(atado.telar);
        // El select de telar va deshabilitado y en algunos WebViews .value sale vacío: cualquier caché sirve.
        const datos = juliosPorTelar[telar] ?? Object.values(juliosPorTelar)[0];
        const registro = datos?.registros.find((r) => texto(r.julio) === valor);
        if (registro) aplicarDatosJulio(registro);
    };

    const pintarJulios = (sel: HTMLSelectElement, datos: DatosJulios): void => {
        const aSeleccionar = julioASeleccionar(sel.value, devGuardada?.no_julio, datos.sugerido);
        const porJulio = new Map(datos.registros.map((r) => [texto(r.julio), r]));
        sel.replaceChildren(new Option('Seleccione un Julio', ''));
        for (const j of datos.julios) {
            const clave = texto(j);
            const op = new Option(clave, clave);
            const r = porJulio.get(clave);
            for (const campo of ['lote', 'cuenta', 'calibre', 'hilo', 'tipo'] as const) {
                if (r?.[campo]) op.dataset[campo] = String(r[campo]);
            }
            sel.add(op);
        }
        if (aSeleccionar) {
            // El julio guardado puede no venir en la lista: se agrega para poder mostrarlo.
            asegurarOpcion(sel, aSeleccionar);
            sel.value = aSeleccionar;
            alCambiarJulio(aSeleccionar);
        }
        bloquearCamposFijos();
    };

    const cargarJulios = async (telar: string): Promise<void> => {
        const sel = $<HTMLSelectElement>('dev_no_julio');
        if (!sel) return;
        if (!telar) {
            sel.replaceChildren(new Option('Seleccione un telar primero', ''));
            return;
        }
        const enCache = juliosPorTelar[telar];
        if (enCache) {
            pintarJulios(sel, enCache);
            return;
        }
        sel.disabled = true;
        sel.replaceChildren(new Option('Cargando Julios...', ''));
        const params = new URLSearchParams({ telar });
        if (atado.tipo) params.set('tipo', atado.tipo);
        if (atado.refId) params.set('exclude_id', String(atado.refId));
        try {
            const res = await http.get<RespuestaAtadores>(`${cfg.rutas.devolucionJulios}?${params}`);
            if (res.ok && Array.isArray(res.julios)) {
                const datos: DatosJulios = {
                    julios: res.julios,
                    sugerido: typeof res.sugerido === 'string' || typeof res.sugerido === 'number' ? String(res.sugerido) : null,
                    registros: Array.isArray(res.registros) ? (res.registros as RegistroJulio[]) : [],
                };
                juliosPorTelar[telar] = datos;
                pintarJulios(sel, datos);
                return;
            }
            sel.replaceChildren(new Option('Sin Julios disponibles', ''));
            void notify.alert(res.message || 'No se pudo cargar los julios atados de ese telar.', 'Julios no disponibles', 'warning');
        } catch (err) {
            sel.replaceChildren(new Option('No se pudieron cargar los Julios', ''));
            void notify.alert(mensajeError(err, 'No se pudo cargar los julios de ese telar.'), 'Error de red', 'error');
        } finally {
            sel.disabled = false;
        }
    };

    // Catálogo de ubicaciones (WMSLocation en TI-PRO), una vez por página.
    const cargarUbicaciones = async (): Promise<void> => {
        const input = $<HTMLInputElement>('dev_ubicacion');
        const lista = $('dev_ubicaciones_sugeridas');
        if (!input || !lista || ubicacionesCargadas) return;
        input.disabled = true;
        try {
            const res = await http.get<RespuestaAtadores>(cfg.rutas.devolucionUbicaciones);
            if (res.ok && Array.isArray(res.ubicaciones)) {
                lista.replaceChildren(...res.ubicaciones.map((u) => new Option('', String(u))));
                ubicacionesCargadas = true;
            } else {
                void notify.alert(res.message || 'No se pudo cargar el catálogo de ubicaciones (TI-PRO).', 'Ubicaciones no disponibles', 'warning');
            }
        } catch (err) {
            void notify.alert(mensajeError(err, 'No se pudo conectar con TI-PRO para cargar las ubicaciones.'), 'Error de conexión', 'error');
        } finally {
            input.disabled = false;
        }
    };

    const prepararFormulario = (): void => {
        const telar = $<HTMLSelectElement>('dev_telar');
        const telarActual = texto(atado.telar);
        if (telar && !telar.value && telarActual) {
            asegurarOpcion(telar, telarActual);
            telar.value = telarActual;
        }
        // Lote y Tipo salen del julio previo sugerido, no del atado actual.
        if (devGuardada?.ubicacion && !valorDe('dev_ubicacion')) ponerValor('dev_ubicacion', devGuardada.ubicacion);
        if (!valorDe('dev_fecha')) ponerValor('dev_fecha', new Date().toLocaleDateString('en-CA'));
        bloquearCamposFijos();
    };

    const eliminar = async (check: HTMLInputElement | null, panel: HTMLElement): Promise<void> => {
        const confirmado = await notify.confirm({
            title: '¿Estás seguro de eliminar devolución?',
            text: 'Esta acción eliminará los datos capturados de la devolución.',
            confirmText: 'Sí, eliminar',
            confirmColor: '#dc2626',
        });
        const regresar = (): void => {
            if (check) check.checked = true;
            panel.classList.remove('hidden');
        };
        if (!confirmado) {
            regresar();
            return;
        }
        eliminando = true;
        clearTimeout(temporizador);
        pendiente = false;
        while (enCurso) await new Promise((r) => setTimeout(r, 50));
        try {
            exigirOk(
                await http.delete<RespuestaAtadores>(cfg.rutas.devolucionEliminar, { data: { ref_id: atado.refId } }),
                'No se pudo eliminar la devolución.',
            );
            registrada = false;
            for (const id of CAMPOS_FORMULARIO) ponerValor(id, '');
            ponerValor('dev_telar', '');
            ponerValor('dev_tipo', '');
            panel.classList.add('hidden');
            estado('');
            notify.success('Devolución eliminada');
        } catch (err) {
            regresar();
            void notify.alert(mensajeError(err, 'No se pudo eliminar la devolución.'), 'No se pudo eliminar', 'error');
        } finally {
            eliminando = false;
        }
    };

    const alternar = async (marcado: boolean, check: HTMLInputElement | null): Promise<void> => {
        const panel = $('devolucionPanel');
        if (!panel) return;
        if (bloqueadaPorAx) {
            if (check) check.checked = true;
            panel.classList.remove('hidden');
            bloquearCamposFijos();
            return;
        }
        if (!marcado) {
            if (registrada) await eliminar(check, panel);
            else panel.classList.add('hidden');
            return;
        }
        panel.classList.remove('hidden');
        const noSeCreo = (): void => {
            if (check) check.checked = false;
            panel.classList.add('hidden');
        };
        if (cfg.esKm) {
            if (!document.querySelector('#devKmBody tr[data-julio]') || registrada) return;
            if (!(await sincronizar({ permitirCrear: true }))) noSeCreo();
            return;
        }
        prepararFormulario();
        // Primero los julios (y el sugerido); después se crea, para no grabar el julio del atado actual.
        void cargarUbicaciones();
        const telar = valorDe('dev_telar');
        if (telar) await cargarJulios(telar);
        bloquearCamposFijos();
        if (!registrada && !(await sincronizar({ permitirCrear: true }))) {
            noSeCreo();
            return;
        }
        bloquearCamposFijos();
    };

    // ----- Eventos -----
    const check = $<HTMLInputElement>('chkDevolucion');
    check?.addEventListener('change', () => void alternar(check.checked, check));

    if (cfg.esKm) {
        document.querySelectorAll('.dev-km-input').forEach((campo) => {
            campo.addEventListener('input', () => programar());
            campo.addEventListener('change', () => programar());
        });
        // El select de atado anterior recarga la página con ?anterior=Id; el de cuenta solo filtra (se guardan todas).
        $<HTMLSelectElement>('dev_anterior_km_select')?.addEventListener('change', (ev) => {
            const url = new URL(window.location.href);
            url.searchParams.set('anterior', (ev.currentTarget as HTMLSelectElement).value);
            window.location.assign(url.toString());
        });
        $<HTMLSelectElement>('dev_cuenta_km')?.addEventListener('change', (ev) => {
            const cuenta = (ev.currentTarget as HTMLSelectElement).value;
            document.querySelectorAll<HTMLTableRowElement>('#devKmBody tr[data-julio]').forEach((tr) => {
                tr.hidden = cuenta !== '' && tr.dataset.cuenta !== cuenta;
            });
        });
    } else {
        for (const id of CAMPOS_AUTOGUARDADO) {
            const campo = $(id);
            campo?.addEventListener('input', () => programar());
            campo?.addEventListener('change', () => {
                if (id !== 'dev_telar') programar();
            });
        }
        $<HTMLSelectElement>('dev_telar')?.addEventListener('change', (ev) => {
            ponerValor('dev_no_julio', '');
            limpiarDatosJulio();
            limpiarDisponibilidad();
            void cargarJulios((ev.currentTarget as HTMLSelectElement).value);
        });
        $<HTMLSelectElement>('dev_no_julio')?.addEventListener('change', (ev) => alCambiarJulio((ev.currentTarget as HTMLSelectElement).value));
        // Datepicker nativo al tocar la fecha (mejor en tablet).
        const fecha = $<HTMLInputElement>('dev_fecha');
        const abrirCalendario = (): void => {
            if (!fecha || fecha.disabled) return;
            try {
                fecha.showPicker?.();
            } catch {
                // Algunos navegadores bloquean showPicker fuera de un gesto directo; el control nativo sigue.
            }
        };
        fecha?.addEventListener('click', abrirCalendario);
        fecha?.addEventListener('focus', abrirCalendario);
    }

    bloquearCamposFijos();
    if (devGuardada && check) {
        check.checked = true;
        void alternar(true, check);
    }
}
