/** Lógica pura del catálogo de Núcleos (sin DOM). */
import type { Validacion } from '../../urdido/catalogo-julios/logica.ts';

export const MAX_NOMBRE = 120;

/** Salón y Nombre obligatorios; Nombre de a lo más 120 caracteres (mismas reglas que el Swal). */
export function validarNucleo(salon: string, nombre: string): Validacion<{ Salon: string; Nombre: string }> {
    const s = salon.trim();
    const n = nombre.trim();
    if (!s) return { ok: false, campo: 'Salon', mensaje: 'Todos los campos son requeridos' };
    if (!n) return { ok: false, campo: 'Nombre', mensaje: 'Todos los campos son requeridos' };
    if (n.length > MAX_NOMBRE) {
        return { ok: false, campo: 'Nombre', mensaje: `El nombre no puede tener más de ${MAX_NOMBRE} caracteres` };
    }

    return { ok: true, datos: { Salon: s, Nombre: n } };
}

/** Acción del formulario: crear (POST a store) o editar (PUT a update con el id). */
export function destinoFormulario(
    rutas: { guardar: string; actualizar: string },
    id: string | null,
): { accion: string; metodo: 'POST' | 'PUT' } {
    return id
        ? { accion: rutas.actualizar.split('__ID__').join(encodeURIComponent(id)), metodo: 'PUT' }
        : { accion: rutas.guardar, metodo: 'POST' };
}
