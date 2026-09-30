/**
 * Saldos 2026 (19-02). Vista: resources/views/modulos/tejido/reportes/saldos-2026.blade.php.
 * Tabla tipo Excel: menú por columna (fijar, ocultar, filtrar, ordenar), fila de filtros de
 * texto, filtro por valores en x-ui.modal-base, selección de fila y grupos de orden compartida.
 * El menú se abre con clic derecho, mantener el dedo sobre una celda o el botón "⋮" del navbar
 * (UX-06, HANDOFF 17-02 C1). La lógica pura vive en logica.ts.
 */
import { accionesTactiles, botonAcciones, type PosicionAcciones } from '../../../../utils/acciones-tactiles.ts';
import { onReady } from '../../../../utils/dom.ts';
import { crearModalFiltro } from './filtro-modal.ts';
import {
    aplicarSeleccion,
    contarValores,
    filaPasa,
    mapaColumnas,
    ordenarPorBloques,
    posicionMenu,
    separadoresAntes,
    tituloBotonFiltro,
    visibilidadConGrupos,
    type Filtros,
} from './logica.ts';

type Celda = HTMLTableCellElement;

interface Fila {
    tr: HTMLTableRowElement;
    esGrupo: boolean;
    lider: boolean;
    noTelar: string;
    ordCompartida: string;
    /** Texto de cada celda por columna visual (no cambia: se calcula una vez). */
    textos: string[];
}

const CLASES_FILTRO_INACTIVO = ['bg-indigo-50', 'hover:bg-indigo-100', 'text-indigo-700', 'border-indigo-200'];
const CLASES_FILTRO_ACTIVO = ['bg-blue-500', 'hover:bg-blue-600', 'text-white', 'border-blue-600'];

function iniciar(): void {
    const tabla = document.getElementById('saldos-table') as HTMLTableElement | null;
    const tbody = document.getElementById('saldos-tbody') as HTMLTableSectionElement | null;
    const menu = document.getElementById('saldos-ctx');
    const thead = tabla?.tHead;
    if (!tabla || !tbody || !menu || !thead) return;

    /* 1. Mapa de columnas (rowspan/colspan) */
    const filasTabla = Array.from(tabla.rows);
    const mapa = mapaColumnas(filasTabla.map((r) => Array.from(r.cells)));
    const colDe = new Map<Celda, number>();
    const celdasCol: Celda[][] = Array.from({ length: mapa.total }, () => []);
    filasTabla.forEach((r, ri) =>
        Array.from(r.cells).forEach((c, i) => {
            const col = mapa.inicio[ri]?.[i] ?? 0;
            colDe.set(c, col);
            if ((c.colSpan || 1) === 1) celdasCol[col]?.push(c);
        }),
    );
    const encabezado = (col: number): Celda | undefined => celdasCol[col]?.find((c) => c.closest('thead'));

    const filas: Fila[] = Array.from(tbody.querySelectorAll<HTMLTableRowElement>('tr.saldos-row')).map((tr) => {
        const textos: string[] = [];
        for (const c of Array.from(tr.cells)) textos[colDe.get(c) ?? 0] = (c.textContent ?? '').trim();
        return {
            tr,
            esGrupo: tr.dataset.esGrupo === '1',
            lider: tr.dataset.lider === '1',
            noTelar: tr.dataset.noTelar ?? '',
            ordCompartida: tr.dataset.ordCompartida ?? '',
            textos,
        };
    });
    const porTr = new Map(filas.map((f) => [f.tr, f]));
    const columnas = filas[0]?.tr.cells.length ?? 82;
    const ordenOriginal = [...filas];
    let ordenActual = [...filas];

    /* 2. Fila de filtros de texto (una por columna visual) */
    const filaFiltros = document.createElement('tr');
    filaFiltros.id = 'saldos-filter-row';
    const inputs: HTMLInputElement[] = [];
    for (let col = 0; col < mapa.total; col++) {
        const th = document.createElement('th');
        const input = document.createElement('input');
        input.type = 'text';
        input.placeholder = '…';
        input.className = 'saldos-filter-inp';
        input.dataset.col = String(col);
        input.setAttribute('aria-label', `Filtrar ${(encabezado(col)?.textContent ?? '').trim() || `columna ${col + 1}`}`);
        inputs[col] = input;
        th.appendChild(input);
        if (encabezado(col)?.classList.contains('saldos-col-extra')) th.classList.add('saldos-col-extra');
        filaFiltros.appendChild(th);
    }
    thead.appendChild(filaFiltros);

    const fijarAlturaFiltros = (): void => {
        requestAnimationFrame(() => {
            const alto = Array.from(thead.rows)
                .filter((r) => r !== filaFiltros)
                .reduce((s, r) => s + r.offsetHeight, 0);
            Array.from(filaFiltros.cells).forEach((th) => (th.style.top = `${alto}px`));
        });
    };
    fijarAlturaFiltros();

    /* 3. Filtros */
    let filtros: Filtros = { texto: {}, valores: {} };

    const aplicarFiltros = (): void => {
        const visibles = visibilidadConGrupos(ordenActual, (f) => filaPasa(f.textos, filtros));
        ordenActual.forEach((f, i) => f.tr.classList.toggle('saldos-hidden', !visibles[i]));
        // Separadores solo entre filas visibles (antes quedaban huecos dobles al filtrar).
        redibujar();
    };

    filaFiltros.addEventListener('input', (ev) => {
        const input = ev.target as HTMLInputElement;
        filtros = { ...filtros, texto: { ...filtros.texto, [Number(input.dataset.col)]: input.value } };
        aplicarFiltros();
    });

    const botonFiltro = document.getElementById('saldos-filter-btn');
    const insignia = document.getElementById('saldos-filter-badge');
    let filaFiltrosVisible = false;

    const mostrarFilaFiltros = (visible: boolean): void => {
        filaFiltrosVisible = visible;
        // Antes: style.display = '' dejaba ganar al CSS (#saldos-filter-row { display: none }) y la fila nunca se veía.
        filaFiltros.style.display = visible ? 'table-row' : 'none';
        botonFiltro?.setAttribute('aria-pressed', String(visible));
        if (visible) fijarAlturaFiltros();
    };

    const pintarInsignia = (): void => {
        const activos = Object.keys(filtros.valores).length;
        if (insignia) {
            insignia.textContent = String(activos);
            insignia.classList.toggle('hidden', activos === 0);
        }
        if (botonFiltro) {
            botonFiltro.classList.remove(...(activos > 0 ? CLASES_FILTRO_INACTIVO : CLASES_FILTRO_ACTIVO));
            botonFiltro.classList.add(...(activos > 0 ? CLASES_FILTRO_ACTIVO : CLASES_FILTRO_INACTIVO));
            botonFiltro.title = tituloBotonFiltro(activos);
        }
    };

    const limpiarFiltros = (): void => {
        filtros = { texto: {}, valores: {} };
        inputs.forEach((i) => (i.value = ''));
        aplicarFiltros();
        pintarInsignia();
    };

    botonFiltro?.addEventListener('click', () => {
        // Con filtros por valor activos, el botón los limpia (como antes); si no, muestra/oculta la fila de filtros.
        if (Object.keys(filtros.valores).length > 0) {
            limpiarFiltros();
            mostrarFilaFiltros(false);
            return;
        }
        mostrarFilaFiltros(!filaFiltrosVisible);
    });

    const modalFiltro = crearModalFiltro((col, seleccion, total) => {
        filtros = {
            ...filtros,
            valores: seleccion === null ? omitir(filtros.valores, col) : aplicarSeleccion(filtros.valores, col, seleccion, total),
        };
        aplicarFiltros();
        pintarInsignia();
    });

    const abrirFiltroValores = (col: number): void => {
        let visibles = ordenActual.filter((f) => !f.tr.classList.contains('saldos-hidden'));
        if (!visibles.length) visibles = ordenActual;
        const etiqueta = (encabezado(col)?.textContent ?? '').trim().substring(0, 40) || `Columna ${col + 1}`;
        modalFiltro.abrir(col, etiqueta, contarValores(visibles.map((f) => f.textos[col] ?? '')), filtros.valores[col] ?? null);
    };

    /* 4. Resaltado de grupo (orden compartida) y selección de fila */
    let resaltadas: Fila[] = [];
    const bloqueDe = (fila: Fila): Fila[] => {
        const i = ordenActual.indexOf(fila);
        const out = [fila];
        for (let j = i + 1; j < ordenActual.length; j++) {
            const f = ordenActual[j] as Fila;
            if (!f.esGrupo || f.lider) break;
            out.push(f);
        }
        return out;
    };
    const quitarResaltado = (): void => {
        resaltadas.forEach((f) => f.tr.classList.remove('saldos-row-group-hover'));
        resaltadas = [];
    };
    const resaltarGrupo = (fila: Fila): void => {
        quitarResaltado();
        if (!fila.esGrupo) return;
        resaltadas = bloqueDe(fila);
        resaltadas.forEach((f) => f.tr.classList.add('saldos-row-group-hover'));
    };
    const filaDeEvento = (ev: Event): Fila | undefined => {
        const t = ev.target as Element;
        if (t.closest('a, button, input, select')) return undefined;
        const tr = t.closest<HTMLTableRowElement>('tr.saldos-row');
        return tr ? porTr.get(tr) : undefined;
    };

    tbody.addEventListener('mouseover', (ev) => {
        const fila = filaDeEvento(ev);
        if (fila?.esGrupo) resaltarGrupo(fila);
    });
    tbody.addEventListener('mouseout', quitarResaltado);

    let colActiva = 0;
    tbody.addEventListener('click', (ev) => {
        const celda = (ev.target as Element).closest<Celda>('td');
        if (celda && colDe.has(celda)) colActiva = colDe.get(celda) ?? 0;
        const fila = filaDeEvento(ev);
        if (!fila) return;
        const yaSeleccionada = fila.tr.classList.contains('saldos-row-selected');
        tbody.querySelectorAll('.saldos-row-selected').forEach((r) => r.classList.remove('saldos-row-selected'));
        quitarResaltado();
        if (yaSeleccionada) return;
        fila.tr.classList.add('saldos-row-selected');
        if (fila.esGrupo) resaltarGrupo(fila);
    });

    /* 5. Columnas: fijar, ocultar, más/menos columnas */
    const fijadas = new Set<number>();
    const ocultas = new Set<number>();

    const reaplicarFijadas = (): void => {
        const izquierda = new Map<number, number>();
        let acumulado = 0;
        [...fijadas]
            .sort((a, b) => a - b)
            .forEach((col) => {
                izquierda.set(col, acumulado);
                acumulado += celdasCol[col]?.[0]?.getBoundingClientRect().width ?? 80;
            });
        celdasCol.forEach((celdas, col) => {
            const fija = fijadas.has(col);
            for (const c of celdas) {
                c.style.position = fija ? 'sticky' : '';
                c.style.left = fija ? `${izquierda.get(col) ?? 0}px` : '';
                c.style.zIndex = fija ? (c.closest('thead') ? '25' : '10') : '';
                c.classList.toggle('saldos-col-frozen', fija);
            }
        });
    };

    const verColumna = (col: number, visible: boolean): void => {
        celdasCol[col]?.forEach((c) => (c.style.display = visible ? '' : 'none'));
        const th = inputs[col]?.closest('th');
        if (th) th.style.display = visible ? '' : 'none';
    };

    const botonExtra = document.getElementById('saldos-toggle-extra-cols');
    const etiquetaExtra = document.getElementById('saldos-toggle-extra-cols-label');
    botonExtra?.addEventListener('click', () => {
        const compacta = tabla.classList.toggle('saldos-hide-extra');
        if (etiquetaExtra) etiquetaExtra.textContent = compacta ? 'Más columnas' : 'Menos columnas';
        botonExtra.setAttribute('aria-pressed', String(!compacta));
        if (filaFiltrosVisible) fijarAlturaFiltros();
    });

    /* 6. Orden y separadores entre telares */
    const redibujar = (): void => {
        const visibles = ordenActual.filter((f) => !f.tr.classList.contains('saldos-hidden'));
        const separar = new Set(separadoresAntes(visibles).map((i) => visibles[i]));
        const nodos: HTMLTableRowElement[] = [];
        ordenActual.forEach((f) => {
            if (separar.has(f)) {
                const sep = document.createElement('tr');
                sep.className = 'saldos-telar-sep';
                sep.setAttribute('aria-hidden', 'true');
                const td = document.createElement('td');
                td.colSpan = columnas;
                td.textContent = ' ';
                sep.appendChild(td);
                nodos.push(sep);
            }
            nodos.push(f.tr);
        });
        tbody.replaceChildren(...nodos);
    };

    const quitarIndicadores = (): void => tabla.querySelectorAll('[data-sort-dir]').forEach((el) => el.removeAttribute('data-sort-dir'));

    const ordenar = (col: number, dir: 'asc' | 'desc'): void => {
        ordenActual = ordenarPorBloques(ordenActual, (f) => (f.textos[col] ?? '').toLowerCase(), dir);
        redibujar();
        quitarIndicadores();
        encabezado(col)?.setAttribute('data-sort-dir', dir);
    };

    /* 7. Menú contextual por columna */
    let colMenu: number | null = null;
    const etiquetaMenu = document.getElementById('ctx-col-label');
    const etiquetaFijar = document.getElementById('ctx-freeze-lbl');

    const cerrarMenu = (): void => {
        menu.style.display = 'none';
        menu.setAttribute('aria-hidden', 'true');
    };

    const abrirMenu = (col: number, pos: PosicionAcciones): void => {
        colMenu = col;
        if (etiquetaMenu) etiquetaMenu.textContent = (encabezado(col)?.textContent ?? '').trim().substring(0, 30) || `Col ${col + 1}`;
        if (etiquetaFijar) etiquetaFijar.textContent = fijadas.has(col) ? 'Desfijar columna' : 'Fijar columna';
        const p = posicionMenu(pos.x, pos.y, window.innerWidth, window.innerHeight);
        menu.style.left = `${p.x}px`;
        menu.style.top = `${p.y}px`;
        menu.style.display = 'block';
        menu.setAttribute('aria-hidden', 'false');
        menu.querySelector<HTMLElement>('[data-action]')?.focus();
    };

    tabla.classList.add('towell-acciones-zona');
    accionesTactiles(tabla, 'thead tr:not(#saldos-filter-row) th, tbody tr.saldos-row td', (celda, pos) => {
        const col = colDe.get(celda as Celda);
        if (col === undefined) return;
        colActiva = col;
        abrirMenu(col, pos);
    });

    // Botón "⋮" visible (para quien no sabe del clic derecho o del long-press): abre el menú de la
    // última columna tocada (TELAR al inicio). Uno en el navbar y no uno por fila: el menú es por
    // columna y 44 px por fila duplicaría el alto de la tabla.
    const contenedorBoton = document.querySelector<HTMLElement>('[data-saldos-acciones]');
    if (contenedorBoton) {
        botonAcciones(tabla, (_el, pos) => abrirMenu(colActiva, pos), {
            contenedor: contenedorBoton,
            etiqueta: 'Acciones de columna',
        });
    }

    document.addEventListener('click', (ev) => {
        if (!menu.contains(ev.target as Node)) cerrarMenu();
    });
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') cerrarMenu();
    });

    const acciones: Record<string, (col: number | null) => void> = {
        freeze: (col) => {
            if (col === null) return;
            if (fijadas.has(col)) fijadas.delete(col);
            else fijadas.add(col);
            reaplicarFijadas();
        },
        hide: (col) => {
            if (col === null) return;
            ocultas.add(col);
            verColumna(col, false);
        },
        filter: (col) => {
            if (col !== null) abrirFiltroValores(col);
        },
        'sort-asc': (col) => {
            if (col !== null) ordenar(col, 'asc');
        },
        'sort-desc': (col) => {
            if (col !== null) ordenar(col, 'desc');
        },
        'clear-filters': () => limpiarFiltros(),
        'show-cols': () => {
            ocultas.forEach((col) => verColumna(col, true));
            ocultas.clear();
        },
        'reset-sort': () => {
            quitarIndicadores();
            ordenActual = [...ordenOriginal];
            redibujar();
        },
    };

    menu.addEventListener('click', (ev) => {
        const boton = (ev.target as Element).closest<HTMLElement>('[data-action]');
        if (!boton) return;
        cerrarMenu();
        acciones[boton.dataset.action ?? '']?.(colMenu);
    });

    // Separadores con el colspan real de la fila (el Blade usa 82 fijo).
    redibujar();
    pintarInsignia();
}

function omitir<T>(obj: Record<number, T>, col: number): Record<number, T> {
    const copia = { ...obj };
    delete copia[col];
    return copia;
}

onReady(iniciar);
