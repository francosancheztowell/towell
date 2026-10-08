/**
 * Reportar paro (19-08). Antes: <script> inline de nuevo-paro/index.blade.php.
 * Cascada departamento → máquina → tipo de falla → falla (una sola lista "Falla — Descripción"),
 * con la OT sugerida. Se usa en piso desde tablet: mismos textos y mismo orden de habilitación.
 */
import { HttpError, http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { leerDatos, mensajeError, ocultarBotonParo, rutaCon, soloPlaceholder } from '../comun/pagina.ts';
import type { RespuestaApi } from '../comun/pagina.ts';
import { fechaCorta, relojLocal } from '../comun/fechas.ts';
import {
    departamentoDelArea,
    departamentoParaOrden,
    gruposDeMaquinas,
    opcionesDeFallas,
    ordenSugerida,
    sinEspacios,
} from './logica.ts';
import type { Falla, Maquina } from './logica.ts';

interface ConfigNuevoParo {
    rutas: {
        departamentos: string;
        maquinas: string;
        tiposFalla: string;
        fallasPorTipo: string;
        ordenTrabajo: string;
        guardar: string;
        solicitudes: string;
        nuevoParo: string;
    };
    areaUsuario: string | null;
    /** Departamentos que pueden reportar, ya filtrados por el servidor (sin petición extra). */
    departamentos: string[];
}

const TXT_SIN_DEPTO = 'Seleccione primero un departamento';
const TXT_SIN_MAQUINA = 'Seleccione primero una máquina';
const TXT_SIN_TIPO = 'Seleccione primero un tipo de falla';

type Campo = 'depto' | 'maquina' | 'tipo_falla' | 'falla' | 'orden_trabajo' | 'obs';

/** Campo del formulario al que apunta cada clave de error del servidor. */
const CAMPO_DEL_SERVIDOR: Record<string, Campo> = {
    depto: 'depto',
    maquina: 'maquina',
    falla_id: 'falla',
    orden_trabajo: 'orden_trabajo',
    obs: 'obs',
};

function iniciar(): void {
    const raiz = document.getElementById('pagina-nuevo-paro');
    const cfg = leerDatos<ConfigNuevoParo>(raiz);
    const form = document.getElementById('form-paro') as HTMLFormElement | null;
    if (!raiz || !cfg || !form) return;

    const $ = <T extends HTMLElement>(id: string): T => document.getElementById(id) as T;
    const selDepto = $<HTMLSelectElement>('depto');
    const selMaquina = $<HTMLSelectElement>('maquina');
    const selTipo = $<HTMLSelectElement>('tipo_falla');
    const selFalla = $<HTMLSelectElement>('falla');
    const inputOrden = $<HTMLInputElement>('orden_trabajo');
    const textoFecha = $('fecha');
    const textoHora = $('hora');
    const inputObs = $<HTMLTextAreaElement>('obs');
    const btnReportar = $<HTMLButtonElement>('btn-aceptar');
    const estado = $('estado-formulario');

    const controles: Record<Campo, HTMLElement> = {
        depto: selDepto,
        maquina: selMaquina,
        tipo_falla: selTipo,
        falla: selFalla,
        orden_trabajo: inputOrden,
        obs: inputObs,
    };
    // Regiones aria-live: los errores (de carga y de validación) se anuncian y se ven bajo el campo.
    const regionesError: Record<Campo, HTMLElement | null> = {
        depto: $('error-depto'),
        maquina: $('error-maquina'),
        tipo_falla: $('error-tipo-falla'),
        falla: $('error-falla'),
        orden_trabajo: $('error-orden-trabajo'),
        obs: $('error-obs'),
    };
    // Ayudas visibles: dicen qué falta para habilitar cada campo bloqueado.
    const cascada: Array<{ campo: Campo; control: HTMLSelectElement; ayuda: HTMLElement | null }> = [
        { campo: 'maquina', control: selMaquina, ayuda: $('ayuda-maquina') },
        { campo: 'tipo_falla', control: selTipo, ayuda: $('ayuda-tipo-falla') },
        { campo: 'falla', control: selFalla, ayuda: $('ayuda-falla') },
    ];

    const anunciar = (texto: string): void => {
        estado.textContent = texto;
    };

    const errorCarga = (campo: Campo, mensaje = ''): void => {
        const region = regionesError[campo];
        if (region) region.textContent = mensaje;
        if (mensaje) controles[campo].setAttribute('aria-invalid', 'true');
        else controles[campo].removeAttribute('aria-invalid');
    };

    const refrescarAyudas = (): void => {
        for (const { campo, control, ayuda } of cascada) {
            if (!ayuda) continue;
            const hayError = (regionesError[campo]?.textContent ?? '').trim() !== '';
            ayuda.classList.toggle('hidden', !control.disabled || hayError || control.dataset.cargando === '1');
        }
    };

    // Mientras la petición está en vuelo el combo queda bloqueado con "Cargando...":
    // en la red de la planta el operador creía que el combo estaba vacío.
    const marcarCargando = (select: HTMLSelectElement): void => {
        select.dataset.cargando = '1';
        select.disabled = true;
        select.setAttribute('aria-busy', 'true');
        soloPlaceholder(select, 'Cargando...');
        anunciar('Cargando opciones…');
    };
    const terminarCarga = (select: HTMLSelectElement): void => {
        delete select.dataset.cargando;
        select.removeAttribute('aria-busy');
        anunciar('');
    };
    const reiniciar = (select: HTMLSelectElement, texto: string): void => {
        select.disabled = true;
        soloPlaceholder(select, texto);
    };

    // La OT se puede capturar a mano: en cuanto el operador escribe, la sugerencia deja
    // de pisarle el valor; si la borra, vuelve a sugerirse sola.
    let ordenManual = false;
    inputOrden.addEventListener('input', () => {
        const limpio = sinEspacios(inputOrden.value);
        if (limpio !== inputOrden.value) inputOrden.value = limpio;
        ordenManual = inputOrden.value !== '';
        errorCarga('orden_trabajo');
    });
    const limpiarOrdenSugerida = (): void => {
        if (!ordenManual) inputOrden.value = '';
    };

    // Elegir un valor limpia el error de ese campo.
    for (const campo of ['depto', 'maquina', 'tipo_falla', 'falla'] as const) {
        controles[campo].addEventListener('change', () => errorCarga(campo));
    }
    inputObs.addEventListener('input', () => errorCarga('obs'));

    form.addEventListener('change', refrescarAyudas);

    // Fecha y hora son informativas (las estampa el servidor); la pantalla puede
    // quedar abierta horas, así que el reloj se mantiene al día.
    const actualizarReloj = (): void => {
        const { fecha, hora } = relojLocal();
        textoFecha.textContent = fechaCorta(fecha);
        textoHora.textContent = hora;
    };
    actualizarReloj();
    window.setInterval(actualizarReloj, 30000);

    ocultarBotonParo(cfg.rutas.nuevoParo);

    // Cada combo de la cascada descarta las respuestas rezagadas: en la red de la planta,
    // cambiar de departamento A → B podía pintar las máquinas de A con B elegido (y el
    // store no revisa que la máquina sea del departamento).
    const vueltas = { tipos: 0, fallas: 0, maquinas: 0, orden: 0 };
    type Vuelta = keyof typeof vueltas;
    const nuevaVuelta = (k: Vuelta): (() => boolean) => {
        const mia = ++vueltas[k];
        return () => mia === vueltas[k];
    };

    async function cargarTiposFalla(departamento: string): Promise<void> {
        errorCarga('tipo_falla');
        soloPlaceholder(selTipo, TXT_SIN_MAQUINA);
        if (!departamento) {
            nuevaVuelta('tipos');
            refrescarAyudas();
            return;
        }
        marcarCargando(selTipo);
        const vigente = nuevaVuelta('tipos');
        try {
            const r = await http.get<RespuestaApi<string[]>>(rutaCon(cfg!.rutas.tiposFalla, { __DEPTO__: departamento }));
            if (!vigente()) return;
            soloPlaceholder(selTipo, TXT_SIN_MAQUINA);
            (r.data ?? []).forEach((tipo) => selTipo.add(new Option(tipo, tipo)));
            // El tipo de falla solo se abre cuando ya hay una máquina elegida.
            selTipo.disabled = !selMaquina.value;
        } catch (err) {
            if (!vigente()) return;
            soloPlaceholder(selTipo, TXT_SIN_MAQUINA);
            selTipo.disabled = true;
            errorCarga('tipo_falla', mensajeError(err, 'No se pudieron cargar los tipos de falla de este departamento.'));
        }
        terminarCarga(selTipo);
        refrescarAyudas();
    }

    function cargarDepartamentos(): void {
        errorCarga('depto');
        const departamentos = cfg!.departamentos ?? [];
        soloPlaceholder(selDepto, 'Seleccione un departamento');
        departamentos.forEach((d) => selDepto.add(new Option(d, d)));
        if (departamentos.length === 0) errorCarga('depto', 'No hay departamentos disponibles para reportar paros.');

        const propio = departamentoDelArea(departamentos, cfg!.areaUsuario);
        if (propio) {
            selDepto.value = propio;
            void cargarMaquinas(propio);
            void cargarTiposFalla(propio);
        }
        refrescarAyudas();
    }

    /** "Falla — Descripción" por Id del catálogo; sin descripción, solo la falla. */
    const etiquetasDeFallas = (fallas: Falla[]): Array<[string, string]> => {
        const descripcion = new Map(opcionesDeFallas(fallas).descripciones);
        return opcionesDeFallas(fallas).fallas.map(([id, falla]) => [id, descripcion.has(id) ? `${falla} — ${descripcion.get(id)}` : falla]);
    };

    async function cargarFallas(departamento: string, tipo: string): Promise<void> {
        errorCarga('falla');
        marcarCargando(selFalla);
        const vigente = nuevaVuelta('fallas');
        try {
            const url = rutaCon(cfg!.rutas.fallasPorTipo, { __DEPTO__: departamento, __TIPO__: tipo });
            const r = await http.get<RespuestaApi<Falla[]>>(url);
            if (!vigente()) return;
            const opciones = etiquetasDeFallas(r.data ?? []);

            soloPlaceholder(selFalla, 'Seleccione una falla');
            opciones.forEach(([id, texto]) => selFalla.add(new Option(texto, id)));
            selFalla.disabled = opciones.length === 0;
            if (opciones.length === 0) errorCarga('falla', 'No hay fallas registradas para este tipo.');
        } catch (err) {
            if (!vigente()) return;
            reiniciar(selFalla, 'Error al cargar fallas');
            errorCarga('falla', mensajeError(err, 'No se pudieron cargar las fallas de este tipo.'));
        }
        terminarCarga(selFalla);
        refrescarAyudas();
    }

    async function cargarMaquinas(departamento: string): Promise<void> {
        errorCarga('maquina');
        if (!departamento) {
            nuevaVuelta('maquinas');
            reiniciar(selMaquina, TXT_SIN_DEPTO);
            refrescarAyudas();
            return;
        }
        marcarCargando(selMaquina);
        const vigente = nuevaVuelta('maquinas');
        try {
            const r = await http.get<RespuestaApi<Maquina[]>>(rutaCon(cfg!.rutas.maquinas, { __DEPTO__: departamento }));
            if (!vigente()) return;
            const maquinas = r.data ?? [];
            const opcion = (m: Maquina): HTMLOptionElement => {
                const o = new Option(String(m.MaquinaId), String(m.MaquinaId));
                o.dataset.departamentoOrigen = m.DepartamentoOrigen || departamento;
                return o;
            };

            soloPlaceholder(selMaquina, 'Seleccione una máquina');
            const grupos = gruposDeMaquinas(departamento, maquinas);
            if (grupos) {
                for (const { grupo, maquinas: lista } of grupos) {
                    const og = document.createElement('optgroup');
                    og.label = grupo;
                    og.append(...lista.map(opcion));
                    selMaquina.append(og);
                }
            } else {
                selMaquina.append(...maquinas.map(opcion));
            }
            selMaquina.disabled = maquinas.length === 0;
            if (maquinas.length === 0) errorCarga('maquina', 'Este departamento no tiene máquinas asignadas a su usuario.');
        } catch (err) {
            if (!vigente()) return;
            reiniciar(selMaquina, 'Error al cargar máquinas');
            errorCarga('maquina', mensajeError(err, 'No se pudieron cargar las máquinas de este departamento.'));
        }
        terminarCarga(selMaquina);
        refrescarAyudas();
    }

    const departamentoOrden = (): string =>
        departamentoParaOrden(selDepto.value, selMaquina.selectedOptions[0]?.dataset.departamentoOrigen);

    // OT sugerida por depto + máquina. Nunca pisa lo que el operador escribió a mano.
    async function cargarOrdenTrabajo(departamento: string, maquina: string): Promise<void> {
        if (ordenManual) return;
        if (!departamento || !maquina) {
            inputOrden.value = '';
            return;
        }
        const vigente = nuevaVuelta('orden');
        try {
            const r = await http.get<RespuestaApi<Array<{ Orden_Prod?: string }>>>(
                rutaCon(cfg!.rutas.ordenTrabajo, { __DEPTO__: departamento, __MAQ__: maquina }),
            );
            // Si llegó mientras el operador escribía, se respeta lo suyo.
            if (vigente() && !ordenManual) inputOrden.value = ordenSugerida(r.data?.[0]?.Orden_Prod);
        } catch {
            // Sin sugerencia: se limpia para no dejar una OT de otra máquina.
            if (vigente() && !ordenManual) inputOrden.value = '';
        }
    }

    selDepto.addEventListener('change', () => {
        const departamento = selDepto.value;
        // Lo que venía en camino para el departamento anterior ya no aplica.
        nuevaVuelta('fallas');
        nuevaVuelta('orden');
        if (departamento) {
            void cargarMaquinas(departamento);
            void cargarTiposFalla(departamento);
            selTipo.disabled = true;
            selTipo.value = '';
            limpiarOrdenSugerida();
            reiniciar(selFalla, TXT_SIN_TIPO);
        } else {
            nuevaVuelta('maquinas');
            nuevaVuelta('tipos');
            reiniciar(selMaquina, TXT_SIN_DEPTO);
            selTipo.disabled = true;
            selFalla.disabled = true;
        }
    });

    selMaquina.addEventListener('change', () => {
        nuevaVuelta('fallas');
        nuevaVuelta('orden');
        limpiarOrdenSugerida();
        if (selMaquina.value) {
            // Si los tipos aún están cargando, el combo se abre cuando terminen.
            selTipo.disabled = selTipo.dataset.cargando === '1';
            selTipo.value = '';
            reiniciar(selFalla, TXT_SIN_TIPO);
            void cargarOrdenTrabajo(departamentoOrden(), selMaquina.value);
        } else {
            selTipo.disabled = true;
            selTipo.value = '';
            selFalla.value = '';
            selFalla.disabled = true;
        }
    });

    selTipo.addEventListener('change', () => {
        selFalla.value = '';
        if (selDepto.value && selTipo.value) {
            void cargarFallas(selDepto.value, selTipo.value);
        } else {
            nuevaVuelta('fallas');
            reiniciar(selFalla, TXT_SIN_TIPO);
        }
    });

    selFalla.addEventListener('change', () => {
        if (selFalla.value && selDepto.value && selMaquina.value && !inputOrden.value) {
            void cargarOrdenTrabajo(departamentoOrden(), selMaquina.value);
        }
    });

    refrescarAyudas();
    cargarDepartamentos();

    /** Marca el error bajo el campo y manda el foco al primero (orden visual del formulario). */
    const mostrarErrores = (errores: Partial<Record<Campo, string>>): void => {
        const orden: Campo[] = ['depto', 'maquina', 'tipo_falla', 'falla', 'orden_trabajo', 'obs'];
        let primero: Campo | null = null;
        for (const campo of orden) {
            const mensaje = errores[campo];
            if (!mensaje) continue;
            errorCarga(campo, mensaje);
            primero ??= campo;
        }
        if (primero) controles[primero].focus();
    };

    /** Lo mínimo para poder enviar; el servidor es quien valida de verdad. */
    const faltantes = (): Partial<Record<Campo, string>> => {
        const f: Partial<Record<Campo, string>> = {};
        if (!selDepto.value) f.depto = 'Selecciona un departamento.';
        if (!selMaquina.value) f.maquina = 'Selecciona una máquina.';
        if (!selTipo.value) f.tipo_falla = 'Selecciona un tipo de falla.';
        if (!selFalla.value) f.falla = 'Selecciona una falla.';
        return f;
    };

    // Enlaces que navegan (Cancelar, Ver solicitudes): spinner al tocar, para que se vea que respondió.
    const enlacesCarga = Array.from(raiz.querySelectorAll<HTMLAnchorElement>('a[data-carga]'));
    for (const enlace of enlacesCarga) {
        enlace.addEventListener('click', (ev) => {
            if (ev.defaultPrevented || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button !== 0) return;
            enlace.querySelector('[data-spinner]')?.classList.remove('hidden');
            enlace.setAttribute('aria-busy', 'true');
            anunciar('Cargando la página…');
        });
    }
    // Al volver con "atrás" el navegador restaura la página tal cual: los spinners no deben quedar girando.
    window.addEventListener('pageshow', (ev) => {
        if (!ev.persisted) return;
        for (const enlace of enlacesCarga) {
            enlace.querySelector('[data-spinner]')?.classList.add('hidden');
            enlace.removeAttribute('aria-busy');
        }
        anunciar('');
    });

    let enviando = false;
    const textoAceptar = document.getElementById('texto-aceptar');
    const textoBoton = textoAceptar?.textContent ?? 'Reportar';
    const bloquear = (si: boolean): void => {
        enviando = si;
        btnReportar.disabled = si;
        btnReportar.querySelector('[data-spinner]')?.classList.toggle('hidden', !si);
        if (textoAceptar) textoAceptar.textContent = si ? 'Enviando...' : textoBoton;
        form.setAttribute('aria-busy', String(si));
        anunciar(si ? 'Enviando el reporte…' : '');
    };

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (enviando) return;

        const faltan = faltantes();
        if (Object.keys(faltan).length > 0) {
            mostrarErrores(faltan);
            return;
        }
        bloquear(true);

        // Fecha y hora no viajan: las estampa el servidor. La falla va como Id del
        // catálogo y el servidor deriva Falla y Descripción. El duplicado lo resuelve
        // el propio store (422).
        const payload = {
            depto: selDepto.value,
            maquina: selMaquina.value,
            falla_id: selFalla.value,
            orden_trabajo: inputOrden.value || null,
            obs: inputObs.value || null,
        };

        try {
            const r = await http.post<RespuestaApi<{ folio?: string; notificacion_enviada?: boolean }>>(cfg!.rutas.guardar, payload);
            const folio = r.data?.folio || '—';
            // Siempre a Solicitudes. El aviso con el folio espera a que el usuario lo
            // cierre: un temporizador no le da tiempo a un lector de pantalla (WCAG 2.2.1).
            // Sin aviso por Telegram el reporte sí quedó, pero el operador debe avisar a mano.
            const sinAviso = r.data?.notificacion_enviada === false;
            await notify.alert(`Folio: ${folio}. ${r.message ?? ''}`.trim(), 'Reportado correctamente', sinAviso ? 'warning' : 'success');
            window.location.href = cfg!.rutas.solicitudes;
        } catch (err) {
            bloquear(false);
            const delServidor: Partial<Record<Campo, string>> = {};
            if (err instanceof HttpError && err.errors) {
                for (const [clave, mensajes] of Object.entries(err.errors)) {
                    const campo = CAMPO_DEL_SERVIDOR[clave];
                    if (campo && mensajes[0]) delServidor[campo] = mensajes[0];
                }
            }
            if (Object.keys(delServidor).length > 0) {
                mostrarErrores(delServidor);
            } else {
                void notify.alert(mensajeError(err, 'Error al reportar el paro. Por favor, intenta nuevamente.'), 'Error', 'error');
            }
        }
    });
}

iniciar();
