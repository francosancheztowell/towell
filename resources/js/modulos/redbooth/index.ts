/**
 * Entrada del modal de Redbooth (HANDOFF PT-05 B4). La carga el propio
 * modal/redbooth.blade.php con @vite, así la tienen Programa Tejido, Trazabilidad y
 * CatCodificación sin tocar sus vistas. Las rutas llegan en data-redbooth-boot del modal.
 */
import { leerBoot } from './logica.ts';
import { iniciarRedbooth } from './modal.ts';

const boot = leerBoot(document.getElementById('modalRedboothProgramaTejido')?.dataset.redboothBoot);
if (boot) iniciarRedbooth(boot);
