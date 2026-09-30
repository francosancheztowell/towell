/**
 * Catálogo de Telares (catalagos/catalagoTelares). Antes: public/js/catalogs/TelaresCatalog.js.
 * El nombre se sugiere a partir de Salón y Telar hasta que el usuario lo escribe.
 */
import { onReady } from '../../../utils/dom.ts';
import { arrancarCatalogo } from '../comun/catalogo.ts';
import { nombreDesde, procesarTelar, resumenTelar } from './logica.ts';

onReady(() => {
    let tocado = false;
    arrancarCatalogo({
        procesar: procesarTelar,
        resumen: resumenTelar,
        alAbrir(form) {
            const salon = form.elements.namedItem('SalonTejidoId') as HTMLInputElement | null;
            const telar = form.elements.namedItem('NoTelarId') as HTMLInputElement | null;
            const nombre = form.elements.namedItem('Nombre') as HTMLInputElement | null;
            // Como antes: al cambiar Salón o Telar se vuelve a sugerir, hasta que se escribe el nombre.
            tocado = false;
            if (!salon || !telar || !nombre || form.dataset.autonombre) return;
            form.dataset.autonombre = '1';
            const sugerir = (): void => {
                if (!tocado) nombre.value = nombreDesde(salon.value, telar.value);
            };
            salon.addEventListener('input', sugerir);
            telar.addEventListener('input', sugerir);
            nombre.addEventListener('input', () => {
                tocado = true;
            });
        },
    });
});
