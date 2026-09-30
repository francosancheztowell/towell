/** Estado de la pantalla Creación de órdenes y caché en localStorage (mismas claves que antes). */
import type { Direccion } from '../comun/inventario-materiales.ts';
import type { Grupo, MaterialEngomado, MaterialUrdido } from './logica.ts';

export interface ConfigPagina {
    telares: unknown[];
    rutas: {
        buscarBomUrdido: string;
        buscarBomEngomado: string;
        materialesUrdido: string;
        materialesEngomado: string;
        anchosBalona: string;
        maquinasEngomado: string;
        nucleos: string;
        crearOrdenes: string;
        /** A dónde vuelve tras crear (antes '/programa-urd-eng/reservar-programar' a mano). */
        despues: string;
    };
}

export interface DatosFila {
    grupo: Grupo;
    bomId: string;
    kilos: number;
    materialesUrdido: MaterialUrdido[] | null;
    materialesEngomado?: MaterialEngomado[];
    destinoSeleccionado: string;
    requiereDestinoManual: boolean;
}

export const estado: {
    config: ConfigPagina | null;
    filaSeleccionadaId: string | null;
    filas: Record<string, DatosFila>;
    orden: { columna: string | null; direccion: Direccion | null };
} = {
    config: null,
    filaSeleccionadaId: null,
    filas: Object.create(null) as Record<string, DatosFila>,
    orden: { columna: null, direccion: null },
};

export function rutas(): ConfigPagina['rutas'] {
    if (!estado.config) throw new Error('Creación de órdenes sin configuración');
    return estado.config.rutas;
}

export function filaActual(): DatosFila | null {
    return (estado.filaSeleccionadaId && estado.filas[estado.filaSeleccionadaId]) || null;
}

/* =================== localStorage (conveniencia por navegador) =================== */

export const CLAVE_MATERIALES = 'creacion_ordenes_materiales';
export const CLAVE_SELECCIONES = 'creacion_ordenes_selecciones';

export interface Seleccion {
    materialId: string;
    serialId: string;
    checkboxKey: string;
}

interface MaterialesGuardados {
    materialesUrdido: MaterialUrdido[];
    materialesEngomado: MaterialEngomado[];
    timestamp: number;
}

function leer<T>(clave: string): Record<string, T> {
    try {
        const valor: unknown = JSON.parse(localStorage.getItem(clave) || 'null');
        return valor && typeof valor === 'object' ? (valor as Record<string, T>) : {};
    } catch {
        return {};
    }
}

function escribir(clave: string, valor: unknown): void {
    try {
        localStorage.setItem(clave, JSON.stringify(valor));
    } catch {
        // almacenamiento lleno o bloqueado: la caché es opcional
    }
}

export const cache = {
    materiales(bomId: string): MaterialesGuardados | null {
        return leer<MaterialesGuardados>(CLAVE_MATERIALES)[bomId] ?? null;
    },
    guardarMateriales(bomId: string, urdido: MaterialUrdido[], engomado: MaterialEngomado[]): void {
        if (!bomId.trim()) return;
        const todos = leer<MaterialesGuardados>(CLAVE_MATERIALES);
        todos[bomId] = { materialesUrdido: urdido, materialesEngomado: engomado, timestamp: Date.now() };
        escribir(CLAVE_MATERIALES, todos);
    },
    borrarMateriales(bomId: string): void {
        const todos = leer<MaterialesGuardados>(CLAVE_MATERIALES);
        delete todos[bomId];
        escribir(CLAVE_MATERIALES, todos);
    },
    selecciones(bomId: string): Seleccion[] {
        const valor = leer<Seleccion[]>(CLAVE_SELECCIONES)[bomId];
        return Array.isArray(valor) ? valor : [];
    },
    guardarSelecciones(bomId: string, selecciones: Seleccion[]): void {
        const todos = leer<Seleccion[]>(CLAVE_SELECCIONES);
        todos[bomId] = selecciones;
        escribir(CLAVE_SELECCIONES, todos);
    },
    limpiar(): void {
        try {
            localStorage.removeItem(CLAVE_MATERIALES);
            localStorage.removeItem(CLAVE_SELECCIONES);
        } catch {
            // sin almacenamiento: nada que limpiar
        }
    },
};
