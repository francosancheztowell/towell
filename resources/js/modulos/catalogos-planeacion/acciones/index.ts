/**
 * Entrada Vite del componente components/buttons/catalog-actions.blade.php (lo carga el propio
 * componente con @vite). La lógica vive en resources/js/catalogos/catalog-actions.ts: las
 * pantallas importan registrarAccionesCatalogo() de ahí y Vite comparte el módulo entre bundles.
 */
import { onReady } from '../../../utils/dom.ts';
import { iniciarAccionesCatalogo } from '../../../catalogos/catalog-actions.ts';

onReady(() => iniciarAccionesCatalogo());
