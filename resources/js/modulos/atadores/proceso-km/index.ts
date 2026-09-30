/** Pantalla propia de Montado o Enhebrado de una barra Karl Mayer (/atadores/calificar/{montado|enhebrado}). */
import { leerPagina } from '../comun/pagina.ts';
import { iniciarProcesoKm, type ConfigProcesoKm } from '../comun/proceso-km.ts';

const pagina = leerPagina<ConfigProcesoKm>('proceso-km');
if (pagina) iniciarProcesoKm(pagina.raiz, pagina.datos);
