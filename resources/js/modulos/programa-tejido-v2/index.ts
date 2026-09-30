/**
 * Shell Livewire v2 de Programa Tejido / Muestras (PT 03). Solo lo carga
 * req-programa-tejido-v2.blade.php (canary ShellV2): con el canary apagado la página legacy no
 * lo referencia. La grilla, la selección y las acciones siguen en resources/js/programa-tejido/.
 */
import { notify } from '../../utils/notifications.ts';
import { mensajeDeError } from './logica.ts';

window.addEventListener('programa-tejido-error', (event) => {
    notify.error(mensajeDeError(event instanceof CustomEvent ? event.detail : null));
});
