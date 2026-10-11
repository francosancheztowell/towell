/**
 * Reporte "Órdenes de Trabajo Diarias" (mecánicos).
 * Vista: resources/views/modulos/mecanicos/reportes/ot-diarias.blade.php.
 * Recalcula totales por mecánico y el pie al capturar OT/cumplidas de trama y % ocupación;
 * exporta Excel/PDF con esas capturas y genera la imagen para Telegram.
 */
import { delegate, onReady, qs, qsa } from '../../../../utils/dom.ts';
import { type Campos, descargarYEnviarImagen, enviarExportacion } from '../comun/imagen.ts';

interface Fila {
    cve: string;
    realizadas_semana: number | string | null;
    firmadas_semana: number | string | null;
}

type Captura = Record<string, Record<string, string>>;

function numero(valor: unknown): number {
    const n = Number.parseFloat(String(valor ?? '').replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
}

const redondear1 = (n: number): number => Math.round(n * 10) / 10;

const fmt1 = (n: number): string => n.toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

function capturas(): Captura {
    const payload: Captura = {};
    qsa<HTMLInputElement>('.js-ot-input').forEach((input) => {
        const { cve, campo } = input.dataset;
        if (!cve || !campo) return;
        (payload[cve] ??= {})[campo] = input.value;
    });
    return payload;
}

function camposCaptura(prefijo: string): Campos {
    return Object.entries(capturas()).flatMap(([cve, campos]) =>
        Object.entries(campos).map(([campo, valor]): [string, string] => [`${prefijo}[${cve}][${campo}]`, valor]),
    );
}

function calcular(fila: Fila, captura: Record<string, string>) {
    const otTrama = numero(captura.ot_trama);
    const cumplidasTrama = numero(captura.cumplidas_trama);
    const totalRealizadas = numero(fila.realizadas_semana) + otTrama;
    const totalCumplidas = numero(fila.firmadas_semana) + cumplidasTrama;
    const pctCumplimiento = redondear1(totalRealizadas === 0 ? 0 : (totalCumplidas / totalRealizadas) * 100);
    const pctOtFinal = (numero(captura.ocupacion_pct) + pctCumplimiento) / 2;
    return { otTrama, cumplidasTrama, totalRealizadas, totalCumplidas, pctCumplimiento, pctOtFinal };
}

function pintar(selector: string, texto: string): void {
    const celda = qs(selector);
    if (celda) celda.textContent = texto;
}

function recalcular(filas: Fila[], cve: string): void {
    const todas = capturas();
    const fila = filas.find((item) => item.cve === cve);
    if (fila) {
        const r = calcular(fila, todas[cve] ?? {});
        pintar(`.js-total-realizadas[data-cve="${cve}"]`, fmt1(r.totalRealizadas));
        pintar(`.js-total-cumplidas[data-cve="${cve}"]`, fmt1(r.totalCumplidas));
        pintar(`.js-pct-cumplimiento[data-cve="${cve}"]`, fmt1(r.pctCumplimiento));
        pintar(`.js-pct-ot-final[data-cve="${cve}"]`, fmt1(redondear1(r.pctOtFinal)));
    }

    let ot = 0;
    let cumplidas = 0;
    let otFinal = 0;
    filas.forEach((f) => {
        const r = calcular(f, todas[f.cve] ?? {});
        ot += r.otTrama;
        cumplidas += r.cumplidasTrama;
        otFinal += r.pctOtFinal;
    });
    pintar('.js-pie-ot-trama', fmt1(ot));
    pintar('.js-pie-cumplidas-trama', fmt1(cumplidas));
    pintar('.js-pie-ot-final', fmt1(filas.length === 0 ? 0 : redondear1(otFinal / filas.length)));
}

onReady(() => {
    const raiz = qs('#reporte-ot-diarias');
    const fecha = qs<HTMLInputElement>('#fecha');
    const consultar = qs<HTMLButtonElement>('#btn-consultar');
    if (!raiz || !fecha || !consultar) return;

    const actualizarConsultar = (): void => {
        consultar.disabled = !fecha.value;
    };
    fecha.addEventListener('change', actualizarConsultar);
    actualizarConsultar();

    const d = raiz.dataset;
    const filas = JSON.parse(d.filas ?? '[]') as Fila[];

    delegate<HTMLInputElement>(raiz, 'input', '.js-ot-input', (_e, input) => recalcular(filas, input.dataset.cve ?? ''));

    delegate<HTMLButtonElement>(document, 'click', '[data-ot-descargar]', (_e, boton) => {
        const form = qs<HTMLFormElement>('#form-export-ot-diarias');
        const holder = qs('#inputs-export-ot-diarias');
        if (!form || !holder) return;
        const url = boton.dataset.otDescargar === 'pdf' ? d.pdfUrl : d.excelUrl;
        enviarExportacion(form, holder, url ?? '', camposCaptura('inputs'));
    });

    delegate(document, 'click', '[data-ot-imagen]', () => {
        const hoja = qs('#hoja-ot-diarias');
        if (!hoja) return;
        void descargarYEnviarImagen(hoja, d.nombreImagen ?? 'ot-diarias.png', d.imagenUrl ?? '', [
            ['fecha', d.fecha ?? ''],
            ...camposCaptura('inputs'),
        ]);
    });
});
