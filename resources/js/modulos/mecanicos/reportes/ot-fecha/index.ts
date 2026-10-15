/**
 * Reporte "OT x Fecha" (mecánicos).
 * Vista: resources/views/modulos/mecanicos/reportes/ot-fecha.blade.php.
 * Mantiene el rango válido (la fecha final no puede quedar antes de la inicial) y solo
 * habilita Consultar con ambas fechas.
 */
import { onReady, qs } from '../../../../utils/dom.ts';

onReady(() => {
    const desde = qs<HTMLInputElement>('#desde');
    const hasta = qs<HTMLInputElement>('#hasta');
    const consultar = qs<HTMLButtonElement>('#btn-consultar');
    if (!desde || !hasta || !consultar) return;

    const actualizar = (): void => {
        hasta.min = desde.value;
        if (desde.value && hasta.value && hasta.value < desde.value) hasta.value = desde.value;
        consultar.disabled = !desde.value || !hasta.value;
    };

    desde.addEventListener('change', actualizar);
    hasta.addEventListener('change', actualizar);
    actualizar();
});
