/**
 * Llamadas al servidor de Programación de Requerimientos (todas por window.http).
 */
import { http, HttpError } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { ErrorApi, exigirExito, mensajeError, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import type { Estado } from './estado.ts';
import {
    TIPOS_JULIO,
    mensajeGuardado,
    normalizarTipo,
    payloadActualizarTelar,
    type DatosResumen,
    type DetalleActualizacion,
    type Semana,
    type TelarEntrada,
} from './logica.ts';

interface RespuestaCatalogo extends RespuestaApi {
    data?: Record<string, unknown>[];
}

/**
 * Catálogo por tipo de julio (RIZO/PIE). Si un tipo falla queda vacío y se avisa en consola,
 * como antes: la pantalla sigue usable y el tamaño/hilo simplemente no ofrece opciones.
 */
async function catalogoPorTipo(ruta: string, campoValor: string, etiqueta: string): Promise<Record<string, string[]>> {
    const entradas = await Promise.all(
        TIPOS_JULIO.map(async (tipo): Promise<[string, string[]]> => {
            const clave = tipo.toUpperCase();
            try {
                const r = await http.get<RespuestaCatalogo>(ruta, { params: { tipo } });
                if (r.success && Array.isArray(r.data)) {
                    return [clave, r.data.map((item) => String(item[campoValor] ?? '')).filter(Boolean)];
                }
                console.warn(`No se pudieron cargar los ${etiqueta} de ${tipo}:`, r.message || 'Respuesta inválida');
            } catch (err) {
                console.error(`Error al cargar los ${etiqueta} de ${tipo}:`, err);
            }
            return [clave, []];
        }),
    );
    return Object.fromEntries(entradas);
}

export async function cargarCatalogos(estado: Estado): Promise<void> {
    const [hilos, tamanos] = await Promise.all([
        catalogoPorTipo(estado.cfg.rutas.hilos, 'ConfigId', 'hilos'),
        catalogoPorTipo(estado.cfg.rutas.tamanos, 'InventSizeId', 'tamaños'),
    ]);
    estado.hilos = hilos;
    estado.tamanos = tamanos;
}

interface RespuestaActualizar extends RespuestaApi {
    detalle?: DetalleActualizacion;
}

/**
 * Guarda un campo del telar en TejInventarioTelares (solo_inventario). La fila aporta
 * id/fecha/turno como discriminadores; el tipo se lee de la fila al momento de guardar.
 */
export async function guardarCampoTelar(
    estado: Estado,
    campo: string,
    valor: string,
    fila: HTMLElement,
    tipo: string,
    { silencioso = false } = {},
): Promise<void> {
    const noTelar = fila.dataset.telarId ?? '';
    if (!noTelar) {
        console.warn('No se puede guardar: telarId no disponible');
        return;
    }
    const inventarioId = fila.dataset.inventarioId ?? '';
    const payload = payloadActualizarTelar(campo, valor, noTelar, tipo, {
        inventarioId,
        fecha: fila.dataset.fecha ?? '',
        turno: fila.dataset.turno ?? '',
    });

    try {
        const r = exigirExito(
            await http.post<RespuestaActualizar>(estado.cfg.rutas.actualizarTelar, payload),
            'Error al guardar los cambios',
        );

        const telar: TelarEntrada | undefined = inventarioId
            ? estado.telares.find((t) => String(t.id) === inventarioId)
            : estado.telares.find((t) => String(t.no_telar ?? '') === noTelar);
        if (telar) {
            if (campo === 'calibre') telar.calibre = parseFloat(valor) || null;
            else if (campo === 'tipo') telar.tipo = normalizarTipo(valor);
            else (telar as unknown as Record<string, unknown>)[campo] = valor;
        }

        if (!silencioso) notify.success(mensajeGuardado(campo, r.detalle));
    } catch (err) {
        console.error('Error al guardar campo:', err);
        if (err instanceof HttpError && err.status === 422 && err.errors) {
            void notify.validation(err.errors);
            return;
        }
        void notify.alert(
            mensajeError(err, err instanceof HttpError ? 'Error al guardar los cambios' : 'Error de conexión al guardar los cambios'),
            'Error',
            'error',
        );
    }
}

export interface RespuestaResumen extends RespuestaApi {
    data?: DatosResumen;
    semanas?: Semana[];
}

/** Resumen de 5 semanas del grupo. Lanza si el servidor no confirma éxito o no manda datos. */
export async function pedirResumen(estado: Estado, telares: TelarEntrada[]): Promise<{ data: DatosResumen; semanas: Semana[] }> {
    const r = exigirExito(
        await http.post<RespuestaResumen>(estado.cfg.rutas.resumen, { telares }),
        'La respuesta del servidor indica error',
    );
    if (r.success !== true) throw new ErrorApi('La respuesta del servidor indica error');
    if (!r.data) throw new ErrorApi('El servidor no devolvió datos');
    return { data: r.data, semanas: Array.isArray(r.semanas) ? r.semanas : [] };
}
