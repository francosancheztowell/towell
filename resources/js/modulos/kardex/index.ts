/**
 * Kardex Materia Prima y Producción en Proceso (/kardex). Vista: resources/views/modulos/kardex/index.blade.php.
 * Cada pestaña se monta la primera vez que se abre (las gráficas necesitan su panel visible).
 * Datos: simulados (./datos-simulados.ts) hasta conectar el backend.
 */
import Chart from 'chart.js/auto';
import { onReady, qsa } from '../../utils/dom.ts';
import { datosSimulados } from './datos-simulados.ts';
import { montarCorrespondencia } from './secciones/correspondencia.ts';
import { montarCrudo } from './secciones/crudo.ts';
import { montarCuadre } from './secciones/cuadre.ts';
import { montarKardexAlmacen } from './secciones/kardex-almacen.ts';
import { montarResumen } from './secciones/resumen.ts';
import type { DatosKardex } from './tipos.ts';

const MONTAJES: Record<string, (raiz: HTMLElement, d: DatosKardex) => void> = {
    resumen: montarResumen,
    kardex: montarKardexAlmacen,
    crudo: montarCrudo,
    cuadre: montarCuadre,
    correspondencia: montarCorrespondencia,
};

function iniciar(): void {
    const raiz = document.querySelector<HTMLElement>('[data-kardex]');
    if (!raiz) return;
    const datos = datosSimulados;

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = '#64748b';
    Chart.defaults.borderColor = '#e2e8f0';

    const encabezado = raiz.querySelector('[data-kardex-periodo]');
    if (encabezado) encabezado.textContent = `${datos.meta.mes} · Todo en kilogramos · Corte ${datos.meta.corte}`;

    const tabs = qsa<HTMLButtonElement>('[role="tab"]', raiz);
    const montadas = new Set<string>();

    const activar = (tab: HTMLButtonElement, foco = false): void => {
        const clave = tab.dataset.kardexTab ?? '';
        tabs.forEach((t) => {
            const activa = t === tab;
            t.setAttribute('aria-selected', String(activa));
            t.tabIndex = activa ? 0 : -1;
            const panel = document.getElementById(t.getAttribute('aria-controls') ?? '');
            if (panel) panel.hidden = !activa;
        });
        if (foco) tab.focus();
        const panel = document.getElementById(tab.getAttribute('aria-controls') ?? '');
        const montar = MONTAJES[clave];
        if (panel && montar && !montadas.has(clave)) {
            montadas.add(clave);
            montar(panel, datos);
        }
        history.replaceState(null, '', `#${clave}`);
    };

    tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => activar(tab));
        tab.addEventListener('keydown', (e) => {
            const siguiente = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
            if (siguiente === undefined) return;
            e.preventDefault();
            const destino = tabs[(siguiente + tabs.length) % tabs.length];
            if (destino) activar(destino, true);
        });
    });

    const inicial = tabs.find((t) => `#${t.dataset.kardexTab}` === location.hash) ?? tabs[0];
    if (inicial) activar(inicial);
}

onReady(iniciar);
