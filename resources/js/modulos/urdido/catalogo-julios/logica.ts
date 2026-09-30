/**
 * Lógica pura del catálogo de Julios (Urdido y Engomado) y de los filtros por URL
 * que comparten los catálogos de la unidad. Sin DOM: se prueba con node --test.
 */

/** Resultado de validar un formulario: datos listos para enviar o el campo con error. */
export type Validacion<T> = { ok: true; datos: T } | { ok: false; campo: string; mensaje: string };

export interface DatosJulio {
    NoJulio: string;
    Tara: number;
    Departamento: string;
}

/** Mismas reglas que el Swal anterior: No. Julio obligatorio; Tara vacía = 0 y numérica. */
export function validarJulio(noJulio: string, tara: string, departamento: string): Validacion<DatosJulio> {
    const no = noJulio.trim();
    if (!no) return { ok: false, campo: 'NoJulio', mensaje: 'El No. Julio es requerido' };

    const texto = tara.trim();
    const valor = texto === '' ? 0 : Number(texto);
    if (Number.isNaN(valor)) return { ok: false, campo: 'Tara', mensaje: 'La Tara debe ser un número válido' };

    return { ok: true, datos: { NoJulio: no, Tara: valor, Departamento: departamento } };
}

/**
 * URL del listado con los filtros no vacíos (antes: `${pathname}?${params}` armado en el Swal).
 * Los valores se recortan; los vacíos no se mandan.
 */
export function urlFiltro(ruta: string, filtros: Record<string, string>): string {
    const params = new URLSearchParams();
    for (const [clave, valor] of Object.entries(filtros)) {
        const v = valor.trim();
        if (v) params.append(clave, v);
    }
    const qs = params.toString();

    return qs ? `${ruta}?${qs}` : ruta;
}
