/**
 * Programación Karl Mayer (Programa Urd-Eng, 19-05). Antes: 823 líneas inline en
 * resources/views/modulos/programa_urd_eng/karl-mayer/crear-karl-mayer.blade.php.
 * Receta: .planning/phases/19-modulos/19-00-RECETA.md
 */
import { http, HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { debounce } from '../../../utils/format.ts';
import { onReady } from '../../../utils/dom.ts';
import { pedirFechaRequerimiento } from '../comun/modal-fecha-requerimiento.ts';
import { leerDatos, mensajeError } from '../../urdido/comun/pagina.ts';
import type { MaterialInventario } from '../comun/inventario-materiales.ts';
import { TablasInventario, type FilaResumen } from './inventario.ts';
import { autocompletarTamano } from './tamano.ts';
import {
    armarPayload,
    cuentaYCalibre,
    formularioValido,
    listaDeCatalogo,
    MIN_CARACTERES_BOM,
    opcionesBom,
} from './logica.ts';

interface ConfigKarlMayer {
    rutas: {
        buscarBomUrdido: string;
        materialesCompleto: string;
        hilos: string;
        tamanos: string;
        crearOrden: string;
        index: string;
    };
}

interface RespuestaMateriales {
    resumen?: FilaResumen[];
    detalle?: MaterialInventario[];
    error?: string;
}

interface RespuestaCrear {
    success?: boolean;
    error?: string;
    message?: string;
    errors?: Record<string, string[]>;
    data?: { folio?: string };
}

// Karl Mayer sugería "ahora" (Creación sugiere +1 h); se conserva.
const MODAL_FECHA = 'modal-fecha-req-km';

function iniciar(): void {
    const form = document.getElementById('form-karl-mayer') as HTMLFormElement | null;
    const cfg = leerDatos<ConfigKarlMayer>(form);
    const tbodyResumen = document.getElementById('tabla-resumen-lmat-body') as HTMLTableSectionElement | null;
    const tbodyDetalle = document.getElementById('tabla-detalle-lmat-body') as HTMLTableSectionElement | null;
    if (!form || !cfg || !tbodyResumen || !tbodyDetalle) return;

    const campo = <T extends HTMLElement>(id: string): T | null => document.getElementById(id) as T | null;
    const inputLmat = campo<HTMLInputElement>('input-lmat');
    const datalistLmat = campo<HTMLDataListElement>('input-lmat-options');
    const inputTamano = campo<HTMLInputElement>('input-tamano');
    const listaTamano = campo<HTMLElement>('tamano-dropdown');
    const inputCuenta = campo<HTMLInputElement>('input-cuenta');
    const inputCalibre = campo<HTMLInputElement>('input-calibre');
    const inputFibra = campo<HTMLSelectElement>('input-fibra');
    const inputLote = campo<HTMLInputElement>('input-lote-proveedor');
    const boton = (): HTMLButtonElement | null => campo<HTMLButtonElement>('btnCrearOrden');

    const tablas = new TablasInventario(tbodyResumen, tbodyDetalle);

    const actualizarBoton = (): void => {
        const b = boton();
        if (b) b.disabled = !formularioValido(new FormData(form), tablas.seleccionados().length);
    };
    tablas.onSeleccion = actualizarBoton;

    const rellenarCuentaYCalibre = (): void => {
        const { cuenta, calibre } = cuentaYCalibre(inputTamano?.value);
        if (inputCuenta) inputCuenta.value = cuenta;
        if (inputCalibre) inputCalibre.value = calibre;
    };

    /* ---------- Catálogos de fibra y tamaño ---------- */
    const tamano =
        inputTamano && listaTamano
            ? autocompletarTamano({
                  input: inputTamano,
                  lista: listaTamano,
                  alCambiar: () => {
                      rellenarCuentaYCalibre();
                      actualizarBoton();
                  },
                  alInvalidar: () => {
                      rellenarCuentaYCalibre();
                      actualizarBoton();
                  },
              })
            : null;

    const cargarCatalogos = async (): Promise<void> => {
        try {
            const [resHilos, resTamanos] = await Promise.all([http.get(cfg.rutas.hilos), http.get(cfg.rutas.tamanos)]);
            tamano?.opciones(listaDeCatalogo(resTamanos, 'InventSizeId'));
            if (inputFibra) {
                inputFibra.replaceChildren(
                    new Option('Seleccionar...', ''),
                    ...listaDeCatalogo(resHilos, 'ConfigId').map((h) => new Option(h, h)),
                );
            }
        } catch (err) {
            notify.error(mensajeError(err, 'No se pudieron cargar los catálogos de fibra y tamaño.'));
        }
    };

    /* ---------- BOM y materiales ---------- */
    const cargarMateriales = async (): Promise<void> => {
        const bomId = (inputLmat?.value ?? '').trim();
        if (!bomId) {
            tablas.limpiar();
            return;
        }
        try {
            const data = await http.get<RespuestaMateriales>(cfg.rutas.materialesCompleto, { params: { bomId, kilosTotal: 1 } });
            if (data?.error) {
                tablas.limpiar();
                notify.warning(data.error);
                return;
            }
            tablas.pintarResumen(data?.resumen ?? []);
            tablas.pintarDetalle(data?.detalle ?? []);
            if (inputLote) inputLote.value = '';
        } catch {
            tablas.limpiar();
            // El 500 de este endpoint todavía trae el detalle interno: mensaje genérico (SEC-07).
            notify.error('No se pudieron cargar los materiales del BOM.');
        }
        actualizarBoton();
    };

    const buscarBom = debounce(async (termino: string): Promise<void> => {
        if (!datalistLmat) return;
        const t = termino.trim();
        if (t.length < MIN_CARACTERES_BOM) {
            datalistLmat.replaceChildren();
            return;
        }
        try {
            const data = await http.get(cfg.rutas.buscarBomUrdido, { params: { q: t } });
            datalistLmat.replaceChildren(...opcionesBom(data).map((o) => new Option(o.nombre, o.valor)));
        } catch {
            datalistLmat.replaceChildren(); // autocompletar: sin sugerencias si falla
        }
    }, 300);

    inputLmat?.addEventListener('change', () => void cargarMateriales());
    inputLmat?.addEventListener('blur', (e) => {
        if (tbodyDetalle.contains(e.relatedTarget as Node | null)) return;
        void cargarMateriales();
    });
    inputLmat?.addEventListener('input', () => buscarBom(inputLmat.value));
    inputLmat?.addEventListener('focus', () => {
        if (inputLmat.value.trim().length >= MIN_CARACTERES_BOM) buscarBom(inputLmat.value);
    });

    inputFibra?.addEventListener('change', rellenarCuentaYCalibre);
    form.addEventListener('input', actualizarBoton);
    form.addEventListener('change', actualizarBoton);

    /* ---------- Crear orden ---------- */
    const textoBoton = (texto: string, deshabilitado: boolean): void => {
        const b = boton();
        if (!b) return;
        b.disabled = deshabilitado;
        const span = b.querySelector('span');
        if (span) span.textContent = texto;
    };

    let enviando = false;
    const crearOrden = async (): Promise<void> => {
        if (enviando) return;
        const materiales = tablas.seleccionados();
        if (!formularioValido(new FormData(form), materiales.length)) {
            if (materiales.length === 0 && tablas.hayFilas()) {
                notify.warning('Seleccione al menos un material de la tabla de inventario.');
            }
            return;
        }

        enviando = true;
        textoBoton('Guardando...', true);
        try {
            const fechaRequerimiento = await pedirFechaRequerimiento(MODAL_FECHA, 0);
            if (!fechaRequerimiento) return;

            const payload = armarPayload(new FormData(form), materiales, fechaRequerimiento);
            const data = await http.post<RespuestaCrear>(cfg.rutas.crearOrden, payload);
            if (data?.success) {
                await notify.alert(`Orden creada. Folio: ${data.data?.folio ?? ''}`, 'Creado', 'success');
                window.location.href = cfg.rutas.index;
                return;
            }
            const msg = data?.errors ? Object.values(data.errors).flat().join('\n') : (data?.error ?? 'Error desconocido');
            void notify.alert(msg, 'Error', 'error');
        } catch (err) {
            if (err instanceof HttpError && err.status === 422 && err.errors) {
                void notify.validation(err.errors);
            } else if (!(err instanceof HttpError && [401, 419].includes(err.status))) {
                void notify.alert(mensajeError(err, 'No se pudo conectar con el servidor.'), 'Error', 'error');
            }
        } finally {
            enviando = false;
            textoBoton('Crear Orden', false);
            actualizarBoton();
        }
    };

    boton()?.addEventListener('click', (e) => {
        e.preventDefault();
        void crearOrden();
    });
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        void crearOrden();
    });

    /* ---------- Arranque ---------- */
    void cargarCatalogos();
    actualizarBoton();
    // El navegador puede restaurar el BOM al volver atrás.
    if ((inputLmat?.value ?? '').trim()) {
        window.setTimeout(() => void cargarMateriales(), 500);
    }
}

onReady(iniciar);
