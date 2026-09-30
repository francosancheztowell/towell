/**
 * Reportar paro (19-08). Antes: <script> inline de nuevo-paro/index.blade.php.
 * Cascada departamento → máquina → tipo de falla → falla/descripción, con la OT sugerida.
 * Se usa en piso desde tablet: mismos textos y mismo orden de habilitación.
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { leerDatos, mensajeError, ocultarBotonParo, rutaCon, soloPlaceholder } from '../comun/pagina.ts';
import type { RespuestaApi } from '../comun/pagina.ts';
import { relojLocal } from '../comun/fechas.ts';
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
}

const TXT_SIN_DEPTO = 'Seleccione primero un departamento';
const TXT_SIN_MAQUINA = 'Seleccione primero una máquina';
const TXT_SIN_TIPO = 'Seleccione primero un tipo de falla';

type Campo = 'depto' | 'maquina' | 'tipo_falla' | 'falla' | 'descripcion';

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
    const selDescripcion = $<HTMLSelectElement>('descripcion');
    const inputOrden = $<HTMLInputElement>('orden_trabajo');
    const inputFecha = $<HTMLInputElement>('fecha');
    const inputHora = $<HTMLInputElement>('hora');
    const inputObs = $<HTMLTextAreaElement>('obs');
    const btnReportar = $<HTMLButtonElement>('btn-aceptar');

    // Regiones aria-live: los errores de carga se anuncian y se ven bajo el campo.
    const regionesError: Record<Campo, HTMLElement | null> = {
        depto: $('error-depto'),
        maquina: $('error-maquina'),
        tipo_falla: $('error-tipo-falla'),
        falla: $('error-falla'),
        descripcion: $('error-descripcion'),
    };
    // Ayudas visibles: dicen qué falta para habilitar cada campo bloqueado.
    const cascada: Array<{ campo: Campo; control: HTMLSelectElement; ayuda: HTMLElement | null }> = [
        { campo: 'maquina', control: selMaquina, ayuda: $('ayuda-maquina') },
        { campo: 'tipo_falla', control: selTipo, ayuda: $('ayuda-tipo-falla') },
        { campo: 'falla', control: selFalla, ayuda: $('ayuda-falla') },
        { campo: 'descripcion', control: selDescripcion, ayuda: $('ayuda-descripcion') },
    ];

    const errorCarga = (campo: Campo, mensaje = ''): void => {
        const region = regionesError[campo];
        if (region) region.textContent = mensaje;
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
        soloPlaceholder(select, 'Cargando...');
    };
    const terminarCarga = (select: HTMLSelectElement): void => {
        delete select.dataset.cargando;
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
    });
    const limpiarOrdenSugerida = (): void => {
        if (!ordenManual) inputOrden.value = '';
    };

    form.addEventListener('change', refrescarAyudas);

    // Fecha y hora son informativas (las estampa el servidor); la pantalla puede
    // quedar abierta horas, así que el reloj se mantiene al día.
    const actualizarReloj = (): void => {
        const { fecha, hora } = relojLocal();
        inputFecha.value = fecha;
        inputHora.value = hora;
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

    async function cargarDepartamentos(): Promise<void> {
        errorCarga('depto');
        try {
            const r = await http.get<RespuestaApi<string[]>>(cfg!.rutas.departamentos);
            const departamentos = r.data ?? [];
            soloPlaceholder(selDepto, 'Seleccione un departamento');
            departamentos.forEach((d) => selDepto.add(new Option(d, d)));

            const propio = departamentoDelArea(departamentos, cfg!.areaUsuario);
            if (propio) {
                selDepto.value = propio;
                void cargarMaquinas(propio);
                void cargarTiposFalla(propio);
            }
        } catch (err) {
            soloPlaceholder(selDepto, 'Error al cargar departamentos');
            errorCarga('depto', mensajeError(err, 'No se pudieron cargar los departamentos.'));
        }
        refrescarAyudas();
    }

    async function cargarFallas(departamento: string, tipo: string): Promise<void> {
        errorCarga('falla');
        errorCarga('descripcion');
        marcarCargando(selFalla);
        marcarCargando(selDescripcion);
        const vigente = nuevaVuelta('fallas');
        try {
            const url = rutaCon(cfg!.rutas.fallasPorTipo, { __DEPTO__: departamento, __TIPO__: tipo });
            const r = await http.get<RespuestaApi<Falla[]>>(url);
            if (!vigente()) return;
            const opciones = opcionesDeFallas(r.data ?? []);

            soloPlaceholder(selFalla, 'Seleccione una falla');
            opciones.fallas.forEach(([id, texto]) => selFalla.add(new Option(texto, id)));
            selFalla.disabled = opciones.fallas.length === 0;

            if (opciones.descripciones.length > 0) {
                soloPlaceholder(selDescripcion, 'Seleccione una descripción');
                opciones.descripciones.forEach(([id, texto]) => selDescripcion.add(new Option(texto, id)));
                selDescripcion.disabled = false;
            } else {
                reiniciar(selDescripcion, 'No hay descripciones disponibles');
            }
        } catch (err) {
            if (!vigente()) return;
            reiniciar(selFalla, 'Error al cargar fallas');
            reiniciar(selDescripcion, 'Error al cargar descripciones');
            errorCarga('falla', mensajeError(err, 'No se pudieron cargar las fallas de este tipo.'));
            errorCarga('descripcion', 'No se pudieron cargar las descripciones de este tipo.');
        }
        terminarCarga(selFalla);
        terminarCarga(selDescripcion);
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
            selMaquina.disabled = false;
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
            reiniciar(selDescripcion, TXT_SIN_TIPO);
            limpiarOrdenSugerida();
            reiniciar(selFalla, TXT_SIN_TIPO);
        } else {
            nuevaVuelta('maquinas');
            nuevaVuelta('tipos');
            reiniciar(selMaquina, TXT_SIN_DEPTO);
            selTipo.disabled = true;
            selDescripcion.disabled = true;
            selFalla.disabled = true;
        }
    });

    selMaquina.addEventListener('change', () => {
        nuevaVuelta('fallas');
        nuevaVuelta('orden');
        limpiarOrdenSugerida();
        if (selMaquina.value) {
            selTipo.disabled = false;
            selTipo.value = '';
            reiniciar(selDescripcion, TXT_SIN_TIPO);
            reiniciar(selFalla, TXT_SIN_TIPO);
            void cargarOrdenTrabajo(departamentoOrden(), selMaquina.value);
        } else {
            selTipo.disabled = true;
            selTipo.value = '';
            selDescripcion.disabled = true;
            selFalla.value = '';
            selFalla.disabled = true;
        }
    });

    selTipo.addEventListener('change', () => {
        selFalla.value = '';
        selDescripcion.value = '';
        if (selDepto.value && selTipo.value) {
            void cargarFallas(selDepto.value, selTipo.value);
        } else {
            nuevaVuelta('fallas');
            selFalla.disabled = true;
            reiniciar(selDescripcion, TXT_SIN_TIPO);
        }
    });

    // Falla y Descripción comparten valor (el Id del catálogo): sincronizar es copiar.
    const sincronizar = (origen: HTMLSelectElement, destino: HTMLSelectElement): void => {
        destino.value = origen.value;
        if (origen.value && selDepto.value && selMaquina.value && !inputOrden.value) {
            void cargarOrdenTrabajo(departamentoOrden(), selMaquina.value);
        }
    };
    selFalla.addEventListener('change', () => sincronizar(selFalla, selDescripcion));
    selDescripcion.addEventListener('change', () => sincronizar(selDescripcion, selFalla));

    refrescarAyudas();
    void cargarDepartamentos();

    let enviando = false;
    const textoBoton = btnReportar.textContent ?? 'Reportar';
    const bloquear = (si: boolean): void => {
        enviando = si;
        btnReportar.disabled = si;
        btnReportar.textContent = si ? 'Enviando...' : textoBoton;
    };

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (enviando) return;
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
            const r = await http.post<RespuestaApi<{ folio?: string }>>(cfg!.rutas.guardar, payload);
            const folio = r.data?.folio || '—';
            // Siempre a Solicitudes: document.referrer llevaba a cualquier parte.
            // Como antes: el aviso con el folio se va solo a los 6 s (o al tocar Aceptar).
            await Promise.race([
                notify.alert(`Folio: ${folio}. ${r.message ?? ''}`.trim(), 'Reportado correctamente', 'success'),
                new Promise((listo) => window.setTimeout(listo, 6000)),
            ]);
            window.location.href = cfg!.rutas.solicitudes;
        } catch (err) {
            bloquear(false);
            void notify.alert(mensajeError(err, 'Error al reportar el paro. Por favor, intenta nuevamente.'), 'Error', 'error');
        }
    });
}

iniciar();
