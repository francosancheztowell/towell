/**
 * Captura de Fórmula: configuración del servidor (data-pagina) y estado compartido entre módulos.
 */
import type { Componente } from './logica.ts';

export interface ConfigFormula {
    desdeProduccion: boolean;
    usuario: { nombre: string; numero: string };
    rutas: {
        guardar: string;
        /** eng-formulacion.update/destroy con marcador __FOLIO__. */
        formulacion: string;
        porId: string;
        componentes: string;
        formulasDisponibles: string;
        calibres: string;
        fibras: string;
    };
}

export const estado = {
    cfg: null as unknown as ConfigFormula,
    filaSeleccionada: null as HTMLTableRowElement | null,
    folioSeleccionado: '',
    idSeleccionado: 0,
    /** Modal en modo Ver (todo de solo lectura). */
    soloLectura: false,
    /** Modal en modo Editar o Ver (datos de una formulación existente). */
    modoEdicion: false,
    /** JSON del formulario al abrir Editar: el botón Guardar se habilita solo si cambia. */
    snapshotInicial: null as string | null,
    /** El select de fórmula solo se puede cambiar en el primer registro del folio. */
    formulaSoloConsulta: false,
    formulaActual: '',
    litros: 0,
    kilos: 0,
    componentes: [] as Componente[],
};

/** getElementById tipado; los ids de la vista existen siempre (si falta uno, es un bug de la vista). */
export function porId<T extends HTMLElement = HTMLElement>(id: string): T {
    return document.getElementById(id) as T;
}

export function valorDe(id: string): string {
    return (document.getElementById(id) as HTMLInputElement | HTMLSelectElement | null)?.value ?? '';
}

export function ponerValor(id: string, valor: string | number): void {
    const campo = document.getElementById(id) as HTMLInputElement | HTMLSelectElement | null;
    if (campo) campo.value = String(valor);
}

export function esMetodo(metodo: 'POST' | 'PUT'): boolean {
    return valorDe('create_method') === metodo;
}

export function mostrar(id: string, visible: boolean): void {
    document.getElementById(id)?.classList.toggle('hidden', !visible);
}

/** Abre un x-ui.modal-base (runtime de componentes; respaldo si aún no cargó). */
export function abrirModalBase(id: string): void {
    if (window.uiModal) {
        window.uiModal.abrir(id);
        return;
    }
    document.getElementById(id)?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

export function cerrarModalBase(id: string): void {
    if (window.uiModal) {
        window.uiModal.cerrar(id);
        return;
    }
    document.getElementById(id)?.classList.add('hidden');
    document.body.style.overflow = '';
}
