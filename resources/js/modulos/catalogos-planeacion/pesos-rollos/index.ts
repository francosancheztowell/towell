/** Pesos por Rollos (catalagos/pesos-rollos): antes ~390 líneas de <script> inline. */
import { onReady } from '../../../utils/dom.ts';
import { arrancarCatalogo } from '../comun/catalogo.ts';
import { procesarPeso, resumenPeso, validarPeso } from './logica.ts';

onReady(() => {
    arrancarCatalogo({ validar: validarPeso, procesar: procesarPeso, resumen: resumenPeso });
});
