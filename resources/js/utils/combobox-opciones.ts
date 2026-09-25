/**
 * Lógica pura del combobox (sin DOM ni Tom Select): textos, plantillas y carga
 * remota. Vive aparte de combobox.ts para probarla en node.
 */
import { escapeHtml } from './format.ts';

export interface TextosCombobox {
    sinResultados: string;
    buscando: string;
    errorCarga: string;
}

export const TEXTOS_COMBOBOX: TextosCombobox = {
    sinResultados: 'No se encontraron resultados',
    buscando: 'Buscando…',
    errorCarga: 'No se pudieron cargar las opciones',
};

/** Opción en el formato interno del combobox (el mismo que lee Tom Select de un <option>). */
export interface OpcionCombobox {
    value: string;
    text: string;
}

export interface RemotoCombobox {
    /** Endpoint GET. Responde `{ results: [{ id, text }] }` (formato de los endpoints actuales) o un arreglo. */
    url: string;
    /** Parámetros extra, se leen en cada búsqueda (p. ej. otros filtros activos). */
    params?: () => Record<string, string>;
}

type GetJson = (url: string, config: { params: Record<string, string> }) => Promise<unknown>;

/** Convierte la respuesta del servidor a opciones; ignora filas sin id. */
export function opcionesRemotas(respuesta: unknown): OpcionCombobox[] {
    const filas = Array.isArray(respuesta)
        ? respuesta
        : (respuesta as { results?: unknown } | null)?.results;
    if (!Array.isArray(filas)) return [];

    return filas.flatMap((fila) => {
        const { id, text } = (fila ?? {}) as { id?: unknown; text?: unknown };
        if (id == null || id === '') return [];
        return [{ value: String(id), text: text == null ? String(id) : String(text) }];
    });
}

export interface CargadorRemoto {
    (consulta: string, callback: (opciones?: OpcionCombobox[]) => void): void;
    /** true si la última búsqueda falló (para mostrar el texto de error). */
    fallo(): boolean;
}

/** `load` de Tom Select: GET con `q` + params extra; en error devuelve vacío y marca `fallo()`. */
export function cargadorRemoto(remoto: RemotoCombobox, get: GetJson): CargadorRemoto {
    let fallo = false;

    const cargar = (consulta: string, callback: (opciones?: OpcionCombobox[]) => void): void => {
        const params = { q: consulta, ...(remoto.params?.() ?? {}) };
        get(remoto.url, { params })
            .then((respuesta) => {
                fallo = false;
                callback(opcionesRemotas(respuesta));
            })
            .catch(() => {
                fallo = true;
                callback();
            });
    };

    return Object.assign(cargar, { fallo: () => fallo });
}

type Plantilla = (datos: Record<string, unknown>) => string;

/** Plantillas en español. Todo texto pasa por escapeHtml. */
export function plantillas(textos: TextosCombobox, fallo: () => boolean = () => false): Record<string, Plantilla> {
    return {
        option: (datos) => `<div>${escapeHtml(datos.text)}</div>`,
        item: (datos) => `<div>${escapeHtml(datos.text)}</div>`,
        no_results: () => `<div class="no-results">${escapeHtml(fallo() ? textos.errorCarga : textos.sinResultados)}</div>`,
        loading: () => `<div class="combobox-cargando">${escapeHtml(textos.buscando)}</div>`,
    };
}
