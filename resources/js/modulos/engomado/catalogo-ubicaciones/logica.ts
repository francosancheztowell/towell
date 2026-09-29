/** Lógica pura del catálogo de Ubicaciones de Engomado (sin DOM). */
import type { Validacion } from '../../urdido/catalogo-julios/logica.ts';

export const MAX_CODIGO = 10;

/** Código obligatorio, en mayúsculas y de a lo más 10 caracteres (columna de CatUbicaciones). */
export function validarUbicacion(codigo: string): Validacion<{ Codigo: string }> {
    const valor = codigo.trim().toUpperCase();
    if (!valor) return { ok: false, campo: 'Codigo', mensaje: 'El Código es requerido' };
    if (valor.length > MAX_CODIGO) {
        return { ok: false, campo: 'Codigo', mensaje: `El Código no puede tener más de ${MAX_CODIGO} caracteres` };
    }

    return { ok: true, datos: { Codigo: valor } };
}
