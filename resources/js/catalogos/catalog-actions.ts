/**
 * Acciones del navbar de catálogos (components/buttons/catalog-actions.blade.php), sin onclick
 * ni <script> inline (19-06b, HANDOFF 19-01 U6).
 *
 * Cada botón lleva data-accion-catalogo="<accion>". Al hacer clic se busca, en este orden:
 *   1. el handler que registró la pantalla con registrarAccionesCatalogo(ruta, {...});
 *   2. PUENTE 19-06b: window.<prefijo><RouteJs>() — lo siguen definiendo las pantallas en JS
 *      (Codificación, de 19-06a). Se quita cuando ninguna lo use;
 *   3. para "subir-excel", la carga genérica del componente (modal + http.upload).
 * "restablecer" además gira el ícono un segundo, como antes.
 */
import { abrir, cerrarPorId } from '../componentes/dialog.ts';
import { loader } from '../componentes/loader.ts';

export type AccionCatalogo =
    | 'agregar'
    | 'editar'
    | 'eliminar'
    | 'filtrar'
    | 'restablecer'
    | 'subir-excel'
    | 'recalcular'
    | 'excel-calendarios'
    | 'excel-lineas'
    | 'eliminar-rango';

export type HandlersCatalogo = Partial<Record<AccionCatalogo, () => unknown>>;

export interface ConfigAcciones {
    ruta: string;
    routeJs: string;
    excel: string | null;
}

/** Nombre de la función global que definían las vistas en JS para cada acción. */
const PREFIJOS: Record<AccionCatalogo, (routeJs: string) => string> = {
    agregar: (r) => `agregar${r}`,
    editar: (r) => `editar${r}`,
    eliminar: (r) => `eliminar${r}`,
    filtrar: (r) => `filtrar${r}`,
    restablecer: (r) => `limpiarFiltros${r}`,
    'subir-excel': (r) => `subirExcel${r}`,
    recalcular: () => 'recalcularProgramasCalendarioNavbar',
    'excel-calendarios': () => 'subirExcelCalendariosMaestro',
    'excel-lineas': () => 'subirExcelLineas',
    'eliminar-rango': () => 'eliminarCalendariosPorRango',
};

export function nombrePuente(accion: AccionCatalogo, routeJs: string): string {
    return PREFIJOS[accion](routeJs);
}

export function esAccion(valor: string | undefined): valor is AccionCatalogo {
    return valor !== undefined && Object.hasOwn(PREFIJOS, valor);
}

const registro = new Map<string, HandlersCatalogo>();

/** La pantalla (bundle TS) dice qué hacer con cada botón de su catálogo. */
export function registrarAccionesCatalogo(ruta: string, handlers: HandlersCatalogo): void {
    registro.set(ruta, { ...(registro.get(ruta) ?? {}), ...handlers });
}

/** Handler a ejecutar (registrado o puente window), o null si nadie atiende la acción. */
export function resolverAccion(
    config: Pick<ConfigAcciones, 'ruta' | 'routeJs'>,
    accion: AccionCatalogo,
    registrados: ReadonlyMap<string, HandlersCatalogo>,
    ventana: Record<string, unknown>,
): (() => unknown) | null {
    const propio = registrados.get(config.ruta)?.[accion];
    if (propio) return propio;
    const puente = ventana[nombrePuente(accion, config.routeJs)];

    return typeof puente === 'function' ? (puente as () => unknown) : null;
}

/** Globo rojo con el número de filtros activos sobre el botón Filtrar. */
export function actualizarContadorFiltros(cantidad: number, raiz: ParentNode = document): void {
    const globo = raiz.querySelector<HTMLElement>('[data-catalogo-contador]');
    if (!globo) return;
    globo.textContent = String(cantidad);
    globo.classList.toggle('hidden', cantidad <= 0);
}

/** Habilita o deshabilita Editar/Eliminar del navbar (el aspecto sale de disabled:). */
export function habilitarEdicion(habilitar: boolean, raiz: ParentNode = document): void {
    raiz.querySelectorAll<HTMLButtonElement>('[data-accion-catalogo="editar"], [data-accion-catalogo="eliminar"]').forEach(
        (boton) => {
            boton.disabled = !habilitar;
        },
    );
}

function animarRestablecer(raiz: HTMLElement): void {
    const icono = raiz.querySelector<HTMLElement>('[data-catalogo-icono-restablecer]');
    if (!icono) return;
    icono.classList.add('fa-spin');
    window.setTimeout(() => icono.classList.remove('fa-spin'), 1000);
}

// ============ Excel genérico ============

export interface ResumenExcel {
    titulo: string;
    texto: string;
    icono: 'success' | 'warning';
}

type DatosExcel = Record<string, unknown> | undefined;

const numero = (v: unknown): number => (typeof v === 'number' ? v : Number(v ?? 0) || 0);

/** Texto del resultado de la carga (acepta las llaves en español y las de getStats()). */
export function resumenCargaExcel(nombreArchivo: string, respuesta: { message?: string; data?: DatosExcel }): ResumenExcel {
    const d = respuesta.data ?? {};
    const errores = Array.isArray(d.errores) ? (d.errores as unknown[]) : [];
    const totalErrores = numero(d.total_errores ?? errores.length);
    const lineas = [
        `Archivo ${nombreArchivo} procesado.`,
        '',
        `• Registros procesados: ${numero(d.registros_procesados ?? d.processed_rows)}`,
        `• Nuevos registros: ${numero(d.registros_creados ?? d.created_rows)}`,
        `• Registros actualizados: ${numero(d.registros_actualizados ?? d.updated_rows)}`,
    ];
    if (totalErrores > 0) {
        lineas.push(`• Errores encontrados: ${totalErrores}`, '');
        errores.slice(0, 10).forEach((e, i) => {
            const texto =
                typeof e === 'string'
                    ? e
                    : typeof e === 'object' && e !== null && 'fila' in e
                      ? `Fila ${String((e as { fila: unknown }).fila)}: ${String((e as { error?: unknown }).error ?? '')}`
                      : JSON.stringify(e);
            lineas.push(`${i + 1}. ${texto}`);
        });
        if (totalErrores > 10) lineas.push(`… y ${totalErrores - 10} errores más`);
    }

    return {
        titulo: 'Procesamiento completado',
        texto: lineas.join('\n'),
        icono: totalErrores > 0 ? 'warning' : 'success',
    };
}

const MODAL_EXCEL = 'catalogo-excel-modal';

function prepararExcel(): void {
    const form = document.querySelector<HTMLFormElement>('[data-catalogo-excel-form]');
    if (!form || form.dataset.listo) return;
    form.dataset.listo = '1';
    const archivo = form.querySelector<HTMLInputElement>('input[type="file"]');
    const nombre = form.querySelector<HTMLElement>('[data-catalogo-excel-nombre]');
    const enviar = document.querySelector<HTMLButtonElement>('[data-catalogo-excel-enviar]');

    archivo?.addEventListener('change', () => {
        const f = archivo.files?.[0];
        if (nombre) {
            nombre.hidden = !f;
            nombre.textContent = f ? `${f.name} (${(f.size / 1024).toFixed(1)} KB)` : '';
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const url = form.dataset.url ?? '';
        const f = archivo?.files?.[0];
        if (!f || url === '') {
            window.notify.warning('Selecciona un archivo Excel');
            return;
        }
        if (f.size > 10 * 1024 * 1024) {
            window.notify.warning('El archivo es demasiado grande. Máximo 10 MB.');
            return;
        }
        const datos = new FormData();
        datos.append('archivo_excel', f);
        if (enviar) enviar.disabled = true;
        loader.show();
        try {
            const res = (await window.http.upload(url, datos)) as { success?: boolean; message?: string; data?: DatosExcel };
            if (!res?.success) throw { data: res };
            cerrarPorId(MODAL_EXCEL);
            const resumen = resumenCargaExcel(f.name, res);
            await window.notify.alert(resumen.texto, resumen.titulo, resumen.icono);
            window.location.reload();
        } catch (err) {
            const mensaje = (err as { data?: { message?: unknown } }).data?.message;
            window.notify.error(typeof mensaje === 'string' && mensaje !== '' ? mensaje : 'No se pudo procesar el archivo Excel');
        } finally {
            loader.hide();
            if (enviar) enviar.disabled = false;
        }
    });
}

function abrirExcel(url: string): void {
    const form = document.querySelector<HTMLFormElement>('[data-catalogo-excel-form]');
    if (!form) return;
    prepararExcel();
    form.reset();
    form.dataset.url = url;
    const nombre = form.querySelector<HTMLElement>('[data-catalogo-excel-nombre]');
    if (nombre) nombre.hidden = true;
    abrir(MODAL_EXCEL);
}

// ============ Arranque ============

function leerConfig(raiz: HTMLElement): ConfigAcciones | null {
    try {
        return JSON.parse(raiz.dataset.catalogoAcciones ?? '') as ConfigAcciones;
    } catch {
        return null;
    }
}

export function iniciarAccionesCatalogo(doc: Document = document): void {
    doc.querySelectorAll<HTMLElement>('[data-catalogo-acciones]').forEach((raiz) => {
        if (raiz.dataset.accionesListas) return;
        raiz.dataset.accionesListas = '1';
        const config = leerConfig(raiz);
        if (!config) return;

        raiz.addEventListener('click', (ev) => {
            const boton = (ev.target as Element | null)?.closest<HTMLButtonElement>('[data-accion-catalogo]');
            const accion = boton?.dataset.accionCatalogo;
            if (!boton || boton.disabled || !esAccion(accion)) return;
            if (accion === 'restablecer') animarRestablecer(raiz);
            const handler = resolverAccion(config, accion, registro, window as unknown as Record<string, unknown>);
            if (handler) {
                void handler();
            } else if (accion === 'subir-excel' && config.excel) {
                abrirExcel(config.excel);
            }
        });
    });
}
