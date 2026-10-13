// OEE Urdido: select de meses con casillas (varios meses a la vez) y loader al consultar.
import { loader } from '../../../componentes/loader.ts';
import { combobox } from '../../../utils/combobox.ts';

const meses = document.querySelector<HTMLSelectElement>('#oee-meses');
if (meses) {
    combobox(meses, { placeholder: 'Seleccione meses', casillas: true });
}

// La consulta tarda (varios meses = miles de julios): el loader se quita solo al cargar la página nueva.
document.querySelector<HTMLFormElement>('#oee-consulta')?.addEventListener('submit', () => loader.show());
