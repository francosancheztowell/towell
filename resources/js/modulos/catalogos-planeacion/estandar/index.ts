/**
 * Eficiencia STD / Velocidad STD (catalagos/comun/estandar): un solo módulo para las dos
 * pantallas; la variante sale de la clave del catálogo.
 */
import { onReady, qs } from '../../../utils/dom.ts';
import { arrancarCatalogo } from '../comun/catalogo.ts';
import { aFormularioEstandar, procesarEstandar, resumenEstandar, validarEstandar, type Variante } from './logica.ts';

onReady(() => {
    const clave = JSON.parse(qs('[data-catalogo]')?.dataset.catalogo ?? '{}').clave as string | undefined;
    const variante: Variante = clave === 'velocidad' ? 'velocidad' : 'eficiencia';
    arrancarCatalogo({
        aFormulario: (v) => aFormularioEstandar(variante, v),
        validar: (d) => validarEstandar(variante, d),
        procesar: (d) => procesarEstandar(variante, d),
        resumen: (v) => resumenEstandar(variante, v),
    });
});
