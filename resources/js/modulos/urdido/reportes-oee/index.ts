// OEE Urdido: select de meses con casillas (varios meses a la vez) y loader al consultar.
import { loader } from '../../../componentes/loader.ts';
import { combobox } from '../../../utils/combobox.ts';

const meses = document.querySelector<HTMLSelectElement>('#oee-meses');
if (meses) {
    combobox(meses, { placeholder: 'Seleccione meses', casillas: true });
}

// Desglose: muestra junto a Producción sus dos partes (min. producción y paro necesario) en todo el reporte.
// Lo abre el botón del navbar o tocar Producción (encabezados y tarjeta, [data-desglose-toggle]).
const reporte = document.querySelector<HTMLElement>('#oee-reporte');
const botonDesglose = document.querySelector<HTMLButtonElement>('#oee-desglose');
document.addEventListener('click', (e) => {
    if (!(e.target as Element).closest('#oee-desglose, [data-desglose-toggle]')) return;
    const abierto = reporte?.toggleAttribute('data-desglose') ?? false;
    botonDesglose?.setAttribute('aria-pressed', String(abierto));
});

// La consulta tarda (varios meses = miles de julios): el loader se quita solo al cargar la página nueva.
document.querySelector<HTMLFormElement>('#oee-consulta')?.addEventListener('submit', () => loader.show());
