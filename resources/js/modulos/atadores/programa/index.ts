/**
 * Tablero Programa Atadores (/atadores/programaatadores). Antes 647 líneas inline (19-03).
 *
 * El refresco de estatus pide GET programaatadores/estatus (JSON id + estatus) cada 15 s solo con la
 * pestaña visible; al ocultarla se detiene y al volver refresca en el momento. Nunca vuelve a bajar el HTML.
 */
import { abrir, cerrarPorId } from '../../../componentes/dialog.ts';
import { accionesTactiles } from '../../../utils/acciones-tactiles.ts';
import { delegate } from '../../../utils/dom.ts';
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { leerPagina } from '../comun/pagina.ts';
import {
    COLUMNAS_NUMERICAS,
    aplicarFiltro,
    atributoOrden,
    botonesActivos,
    comparar,
    filaVisible,
    motivoNoIniciar,
    type ContextoRol,
} from './logica.ts';

interface ConfigPrograma extends ContextoRol {
    rutas: { programa: string; estatus: string; iniciar: string };
    filtros: string[];
}

const REFRESCO_MS = 15000;

const MODAL_COLUMNA = 'modalFiltroColumna';

const pagina = leerPagina<ConfigPrograma>('programa-atadores');
if (pagina) iniciar(pagina.raiz, pagina.datos);

function iniciar(raiz: HTMLElement, cfg: ConfigPrograma): void {
    const cuerpo = document.getElementById('tb-body');
    const cabecera = document.getElementById('atadoresTableHead');
    const menu = document.getElementById('tableContextMenu');
    let filtros = [...cfg.filtros];
    const columnas: Record<string, string> = {};
    const orden: { columna: string; dir: 'asc' | 'desc' } = { columna: 'fecha', dir: 'asc' };
    let seleccionada: HTMLTableRowElement | null = null;
    let columnaMenu = { columna: '', etiqueta: '' };

    const filas = (): HTMLTableRowElement[] => [...(cuerpo?.querySelectorAll<HTMLTableRowElement>('tr.table-row') ?? [])];

    // ----- Filtros (estatus del navbar + columna) -----
    const aplicarFiltrosFilas = (): void => {
        let visibles = 0;
        for (const tr of filas()) {
            const valores: Record<string, string> = {};
            for (const col of Object.keys(columnas)) valores[col] = tr.getAttribute(`data-${col}`) ?? '';
            const ver = filaVisible({ status: tr.dataset.status || 'Activo', telar: tr.dataset.telar ?? '', valores }, filtros, columnas, cfg);
            tr.hidden = !ver; // hidden, no display: la cebra cuenta solo las visibles
            if (ver) visibles++;
        }
        const vacia = cuerpo?.querySelector('tr.no-results');
        if (visibles > 0) {
            vacia?.remove();
        } else if (!vacia && cuerpo) {
            const plantilla = document.getElementById('plantillaSinResultados') as HTMLTemplateElement | null;
            const fila = plantilla?.content.firstElementChild?.cloneNode(true);
            if (fila) cuerpo.append(fila);
        }
    };

    /** Botones de estatus del navbar: el activo se pinta por aria-pressed (app.css). */
    const pintarBotones = (): void => {
        const activos = botonesActivos(filtros, cfg).map((clave) => (clave === 'activo-proceso' ? 'activo' : clave));
        document.querySelectorAll<HTMLElement>('[data-filtro]').forEach((b) => {
            b.setAttribute('aria-pressed', String(activos.includes(b.dataset.filtro ?? '')));
        });
    };

    const marcarColumnas = (): void => {
        cabecera?.querySelectorAll<HTMLElement>('th[data-column]').forEach((th) => {
            const conFiltro = Boolean(columnas[th.dataset.column ?? '']);
            th.toggleAttribute('data-filtro-activo', conFiltro); // verde: app.css
        });
    };

    // ----- Orden -----
    const ordenar = (): void => {
        if (!cuerpo) return;
        cuerpo.querySelector('tr.no-results')?.remove();
        const atributo = atributoOrden(orden.columna);
        const numerica = COLUMNAS_NUMERICAS.includes(orden.columna);
        const ordenadas = filas().sort((a, b) => comparar(a.getAttribute(atributo) ?? '', b.getAttribute(atributo) ?? '', numerica, orden.dir));
        cuerpo.append(...ordenadas);
        cabecera?.querySelectorAll<HTMLElement>('.th-sortable').forEach((th) => {
            const icono = th.querySelector('.sort-icon');
            if (icono) icono.textContent = th.dataset.sort === orden.columna ? (orden.dir === 'asc' ? '▲' : '▼') : '';
        });
        aplicarFiltrosFilas();
    };

    // ----- Selección e iniciar -----
    const pintarSeleccion = (tr: HTMLTableRowElement | null): void => {
        // Color de la seleccionada: .tabla-seleccionable (app.css) por aria-selected.
        for (const fila of filas()) fila.setAttribute('aria-selected', String(fila === tr));
        const boton = document.getElementById('btnIniciarAtado') as HTMLButtonElement | null;
        if (boton) {
            boton.disabled = tr === null;
            boton.classList.toggle('opacity-50', tr === null);
            boton.classList.toggle('cursor-not-allowed', tr === null);
        }
    };

    const seleccionar = (tr: HTMLTableRowElement): void => {
        const motivo = motivoNoIniciar(tr.dataset.status || 'Activo', tr.dataset.horaParo ?? '', tr.dataset.noJulio ?? '', tr.dataset.noOrden ?? '');
        if (motivo) {
            void notify.alert(motivo, 'Atención', 'warning');
            return;
        }
        seleccionada = seleccionada === tr ? null : tr;
        pintarSeleccion(seleccionada);
    };

    const iniciarAtado = (): void => {
        if (!seleccionada?.isConnected) {
            void notify.alert('Debe seleccionar un registro primero', 'Atención', 'warning');
            return;
        }
        const { id = '', noJulio = '', noOrden = '', horaParo = '' } = seleccionada.dataset;
        if ((seleccionada.dataset.status || 'Activo') !== 'Autorizado' && !horaParo.trim()) {
            void notify.alert('Este telar aún no registra la hora de paro. Detén el telar antes de iniciar el atado', 'Atención', 'warning');
            return;
        }
        if (!noJulio || !noOrden) {
            void notify.alert('El registro seleccionado no tiene los datos necesarios (No. Julio o No. Orden)', 'Error', 'error');
            return;
        }
        const url = new URL(cfg.rutas.iniciar, window.location.origin);
        url.search = new URLSearchParams({ id, no_julio: noJulio, no_orden: noOrden }).toString();
        window.location.assign(url.toString());
    };

    // ----- Refresco de estatus -----
    let enVuelo = false;
    let intervalo: ReturnType<typeof setInterval> | undefined;
    const refrescar = async (): Promise<void> => {
        if (document.hidden || enVuelo) return;
        enVuelo = true;
        try {
            const url = new URL(cfg.rutas.estatus, window.location.origin);
            const filtro = new URLSearchParams(window.location.search).get('filtro');
            if (filtro) url.searchParams.set('filtro', filtro);
            const datos = await http.get<{ id: number | string; status?: string }[]>(url.toString());
            const porId = new Map((datos ?? []).map((f) => [String(f.id), f.status || 'Activo']));
            let cambio = false;
            for (const tr of filas()) {
                const status = porId.get(tr.dataset.id ?? '');
                if (!status || tr.dataset.status === status) continue;
                tr.dataset.status = status;
                tr.dataset.estatus = status;
                const celda = tr.querySelector<HTMLElement>('td[data-status]');
                if (celda) {
                    celda.dataset.status = status;
                    // flux:badge ya renderizado en <template data-badge-estatus> (la vista); sin plantilla, texto.
                    const plantilla = document.querySelector<HTMLTemplateElement>(`template[data-badge-estatus="${CSS.escape(status)}"]`);
                    celda.replaceChildren(plantilla?.content.cloneNode(true) ?? status);
                }
                cambio = true;
            }
            if (cambio) aplicarFiltrosFilas();
        } catch {
            // Sin red o sesión: el siguiente ciclo lo reintenta (http ya avisa 419/401).
        } finally {
            enVuelo = false;
        }
    };
    const detener = (): void => clearInterval(intervalo);
    const arrancar = (): void => {
        detener();
        intervalo = setInterval(() => void refrescar(), REFRESCO_MS);
    };
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            detener();
            return;
        }
        void refrescar();
        arrancar();
    });
    if (!document.hidden) arrancar();

    // ----- Menú de columna: clic derecho y, en tablet, mantener presionado (UX-06) -----
    const cerrarMenu = (): void => menu?.classList.add('hidden');
    if (cabecera) {
        accionesTactiles(cabecera, '.th-sortable', (th, pos) => {
            if (!menu) return;
            columnaMenu = { columna: th.dataset.column ?? '', etiqueta: (th.textContent ?? '').replace(/[▲▼]/g, '').trim() };
            menu.classList.remove('hidden');
            const x = pos.x + 220 > window.innerWidth ? window.innerWidth - 230 : pos.x;
            const y = pos.y + 130 > window.innerHeight ? pos.y - 130 : pos.y;
            menu.style.left = `${x}px`;
            menu.style.top = `${y}px`;
        });
    }
    document.addEventListener('click', (ev) => {
        if (!(ev.target as Element | null)?.closest?.('#tableContextMenu')) cerrarMenu();
    });

    const formColumna = document.getElementById('formFiltroColumna') as HTMLFormElement | null;
    formColumna?.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const valor = (formColumna.elements.namedItem('valor') as HTMLInputElement | null)?.value.trim() ?? '';
        if (valor) columnas[columnaMenu.columna] = valor;
        else delete columnas[columnaMenu.columna];
        cerrarPorId(MODAL_COLUMNA);
        aplicarFiltrosFilas();
        marcarColumnas();
    });

    const accionesMenu: Record<string, () => void> = {
        'filter-column': () => {
            if (!columnaMenu.columna) return;
            const titulo = document.getElementById(`${MODAL_COLUMNA}-titulo`);
            if (titulo) titulo.textContent = `Filtrar: ${columnaMenu.etiqueta}`;
            const input = formColumna?.elements.namedItem('valor') as HTMLInputElement | null;
            if (input) input.value = columnas[columnaMenu.columna] ?? '';
            abrir(MODAL_COLUMNA);
        },
        'clear-column-filter': () => {
            if (!columnas[columnaMenu.columna]) return;
            delete columnas[columnaMenu.columna];
            aplicarFiltrosFilas();
            marcarColumnas();
            notify.success(`Filtro de "${columnaMenu.etiqueta}" eliminado`);
        },
        'clear-all-filters': () => {
            const total = Object.keys(columnas).length;
            if (total === 0) return;
            for (const col of Object.keys(columnas)) delete columnas[col];
            aplicarFiltrosFilas();
            marcarColumnas();
            notify.success(`${total} filtro(s) eliminados`);
        },
    };
    if (menu) {
        delegate<HTMLElement>(menu, 'click', '[data-action]', (_ev, boton) => {
            accionesMenu[boton.dataset.action ?? '']?.();
            cerrarMenu();
        });
    }

    // ----- Eventos -----
    cabecera?.addEventListener('click', (ev) => {
        const th = (ev.target as Element).closest<HTMLElement>('.th-sortable');
        const col = th?.dataset.sort;
        if (!col) return;
        ev.stopPropagation();
        if (orden.columna === col) orden.dir = orden.dir === 'asc' ? 'desc' : 'asc';
        else Object.assign(orden, { columna: col, dir: 'asc' });
        ordenar();
    });

    if (cuerpo) delegate<HTMLTableRowElement>(cuerpo, 'click', 'tr.table-row', (_ev, tr) => seleccionar(tr));


    delegate<HTMLElement>(document, 'click', '[data-accion]', (_ev, el) => {
        switch (el.dataset.accion) {
            case 'iniciar-atado':
                iniciarAtado();
                break;
        }
    });

    delegate<HTMLElement>(document, 'click', '[data-filtro]', (_ev, boton) => {
        const r = aplicarFiltro(boton.dataset.filtro ?? '', filtros, cfg.filtroGlobalActivo, cfg.rutas.programa);
        if ('navegar' in r) {
            window.location.href = r.navegar;
            return;
        }
        filtros = r.filtros;
        pintarBotones();
        aplicarFiltrosFilas();
    });

    pintarBotones();
    aplicarFiltrosFilas();
}
