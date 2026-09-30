/** Matriz de Hilos: reglas puras (antes MatrizHilosCatalog.processData). */
import type { Registro } from '../../../catalogos/catalog-base.ts';
import { numeroONull } from '../comun/logica.ts';

const NUMERICOS = ['Calibre', 'Calibre2', 'N1', 'N2'];

/** Números como número (o null) y el resto de vacíos como null; Hilo se manda tal cual. */
export function procesarHilo(datos: Registro): Registro {
    const salida: Registro = {};
    for (const [campo, valor] of Object.entries(datos)) {
        if (NUMERICOS.includes(campo)) salida[campo] = numeroONull(valor);
        else if (campo !== 'Hilo' && String(valor ?? '').trim() === '') salida[campo] = null;
        else salida[campo] = typeof valor === 'string' ? valor.trim() : valor;
    }

    return salida;
}
