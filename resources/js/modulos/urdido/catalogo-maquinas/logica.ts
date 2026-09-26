/** Lógica pura del catálogo de Máquinas de Urdido (sin DOM). */
import type { Validacion } from '../catalogo-julios/logica.ts';

export interface DatosMaquina {
    MaquinaId: string;
    Nombre: string | null;
    Departamento: string | null;
}

/** Máquina ID obligatorio; Nombre y Departamento vacíos van como null (como antes). */
export function validarMaquina(maquinaId: string, nombre: string, departamento: string): Validacion<DatosMaquina> {
    const id = maquinaId.trim();
    if (!id) return { ok: false, campo: 'MaquinaId', mensaje: 'El Máquina ID es requerido' };

    return {
        ok: true,
        datos: { MaquinaId: id, Nombre: nombre.trim() || null, Departamento: departamento.trim() || null },
    };
}
