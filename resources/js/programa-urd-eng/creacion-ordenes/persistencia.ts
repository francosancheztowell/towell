/**
 * Cache en localStorage de los materiales de cada BOM y de las casillas
 * marcadas, para no repedirlos al ERP al volver a la misma fila.
 *
 * localStorage puede lanzar (modo privado, cuota, cookies bloqueadas), asi que
 * toda lectura y escritura va protegida y devuelve el valor por defecto.
 */

import { estaVacio } from './formato.ts'
import type { MaterialesGuardados } from './types.ts'

const CLAVE_MATERIALES = 'creacion_ordenes_materiales'
const CLAVE_SELECCIONES = 'creacion_ordenes_selecciones'

const leer = <T>(clave: string, porDefecto: T): T => {
    try {
        return (JSON.parse(localStorage.getItem(clave) || 'null') as T | null) ?? porDefecto
    } catch {
        return porDefecto
    }
}

const escribir = (clave: string, valor: unknown): void => {
    try {
        localStorage.setItem(clave, JSON.stringify(valor))
    } catch {
        // sin almacenamiento la pantalla sigue funcionando, solo repide al ERP
    }
}

export const materialesGuardados = (bomId: string): MaterialesGuardados | null =>
    leer<Record<string, MaterialesGuardados>>(CLAVE_MATERIALES, {})[bomId] ?? null

export const guardarMateriales = (
    bomId: string,
    materialesUrdido: unknown[] = [],
    materialesEngomado: unknown[] = [],
): void => {
    if (estaVacio(bomId)) return

    const todos = leer<Record<string, MaterialesGuardados>>(CLAVE_MATERIALES, {})
    todos[bomId] = {
        materialesUrdido: Array.isArray(materialesUrdido) ? materialesUrdido : [],
        materialesEngomado: Array.isArray(materialesEngomado) ? materialesEngomado : [],
        timestamp: Date.now(),
    }
    escribir(CLAVE_MATERIALES, todos)
}

/** Al cambiar el BOM de una fila, lo cacheado del anterior ya no sirve. */
export const olvidarMateriales = (bomId: string): void => {
    const todos = leer<Record<string, MaterialesGuardados>>(CLAVE_MATERIALES, {})
    delete todos[bomId]
    escribir(CLAVE_MATERIALES, todos)
}

export const seleccionesGuardadas = (bomId: string): unknown[] =>
    leer<Record<string, unknown[]>>(CLAVE_SELECCIONES, {})[bomId] ?? []

export const guardarSelecciones = (bomId: string, selecciones: unknown[] = []): void => {
    const todas = leer<Record<string, unknown[]>>(CLAVE_SELECCIONES, {})
    todas[bomId] = selecciones
    escribir(CLAVE_SELECCIONES, todas)
}
