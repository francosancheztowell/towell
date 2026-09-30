/**
 * Entrada del modal de Redbooth (HANDOFF PT-05 B4). La carga el propio
 * modal/redbooth.blade.php con @vite, así la tienen Programa Tejido, Trazabilidad y
 * CatCodificación sin tocar sus vistas.
 */
import { leerBoot } from './logica.ts';
import { iniciarRedbooth } from './modal.js';

const boot = leerBoot(document.getElementById('redbooth-boot')?.textContent);
if (boot) iniciarRedbooth(boot);
