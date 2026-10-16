/**
 * Kardex Materia Prima y Producción en Proceso (/kardex). Vista: resources/views/modulos/kardex/index.blade.php.
 * Cada pestaña se monta la primera vez que se abre (las gráficas necesitan su panel visible).
 * Datos: el libro del mes (/kardex/datos/libro) sobre los simulados (./datos-simulados.ts) para lo
 * que el libro todavía no trae (MP, pretejidos, detalle de crudo, cuadre ERP, correspondencia).
 */
import Chart from 'chart.js/auto';
import { onReady, qsa } from '../../utils/dom.ts';
import { http } from '../../utils/http.ts';
import { notify } from '../../utils/notifications.ts';
import { datosSimulados } from './datos-simulados.ts';
import { aplicarLibro, type LibroKardex, type Periodo } from './libro.ts';
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

const NOMBRE_MES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

/** Mes pedido en la URL (?anio=2026&mes=8) si está entre los disponibles; si no, el más reciente. */
function periodoElegido(periodos: Periodo[]): Periodo | undefined {
    const q = new URLSearchParams(location.search);
    const anio = Number(q.get('anio'));
    const mes = Number(q.get('mes'));

    return periodos.find((p) => p.anio === anio && p.mes === mes) ?? periodos[0];
}

/** Libro del mes mezclado con lo simulado; si el backend falla, solo lo simulado. */
async function cargarDatos(raiz: HTMLElement): Promise<DatosKardex> {
    const select = raiz.querySelector<HTMLSelectElement>('[data-kardex-periodo-select]');
    const fuente = raiz.querySelector<HTMLElement>('[data-kardex-fuente]');
    try {
        const { periodos } = await http.get<{ periodos: Periodo[] }>(raiz.dataset.urlPeriodos ?? '');
        const periodo = periodoElegido(periodos);
        if (!periodo) throw new Error('Sin meses con libro');
        if (select) {
            select.replaceChildren(
                ...periodos.map((p) => {
                    const op = new Option(`${NOMBRE_MES[p.mes - 1] ?? p.mes} ${p.anio}`, `${p.anio}-${p.mes}`);
                    op.selected = p === periodo;
                    return op;
                }),
            );
            select.disabled = false;
            select.addEventListener('change', () => {
                const [anio, mes] = select.value.split('-');
                location.search = new URLSearchParams({ anio: anio ?? '', mes: mes ?? '' }).toString();
            });
        }
        const libro = await http.get<LibroKardex>(raiz.dataset.urlLibro ?? '', { params: periodo });
        if (fuente) fuente.textContent = 'Libro (hojas Kdx) · resto simulado';

        return aplicarLibro(datosSimulados, libro);
    } catch {
        notify.warning('No se pudo cargar el libro del Kardex: se muestran datos simulados.');
        if (fuente) fuente.textContent = 'Datos simulados';

        return datosSimulados;
    }
}

async function iniciar(): Promise<void> {
    const raiz = document.querySelector<HTMLElement>('[data-kardex]');
    if (!raiz) return;
    const datos = await cargarDatos(raiz);

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

onReady(() => void iniciar());
