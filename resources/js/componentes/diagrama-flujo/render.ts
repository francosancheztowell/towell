import { delegate } from '../../utils/dom.ts';
import { calcularArista, validar, vecinos, vecinosArista, type Vecinos } from './geometria.ts';
import type { AristaFlujo, DiagramaFlujo, HandleFlujo, NodoFlujo, OpcionesFlujo } from './tipos.ts';

const SVG_NS = 'http://www.w3.org/2000/svg';
const COLOR_ARISTA = '#2a78d6';
let contador = 0;

type Atributos = Record<string, string | number>;

function crear<K extends keyof SVGElementTagNameMap>(
    etiqueta: K,
    atributos: Atributos = {},
    texto?: string,
): SVGElementTagNameMap[K] {
    const nodo = document.createElementNS(SVG_NS, etiqueta);
    for (const [clave, valor] of Object.entries(atributos)) nodo.setAttribute(clave, String(valor));
    if (texto !== undefined) nodo.textContent = texto;

    return nodo;
}

function etiquetaNodo(n: NodoFlujo): string {
    return [n.titulo, n.subtitulo, n.valor, n.nota].filter(Boolean).join(', ');
}

function textoNodo(n: NodoFlujo): string {
    return [n.titulo, n.subtitulo, n.valor, n.nota].filter(Boolean).join('\n');
}

export function montarRender(contenedor: HTMLElement, diagramaInicial: DiagramaFlujo, opciones: OpcionesFlujo): HandleFlujo {
    const idBase = `df${++contador}`;
    let diagrama = diagramaInicial;
    validar(diagrama);

    contenedor.classList.add('diagrama-flujo');
    const scroll = document.createElement('div');
    scroll.className = 'df-scroll';
    const tooltip = document.createElement('div');
    tooltip.className = 'df-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    contenedor.replaceChildren(scroll, tooltip);

    const nodosEl = new Map<string, SVGGElement>();
    const aristasEl = new Map<string, SVGGElement>();
    const etiquetasEl = new Map<string, SVGTextElement>();

    function construirNodo(n: NodoFlujo): SVGGElement {
        const g = crear('g', {
            class: 'df-nodo',
            'data-nodo': n.id,
            tabindex: 0,
            role: 'button',
            'aria-label': etiquetaNodo(n),
        });
        const cx = n.x + n.ancho / 2;
        g.appendChild(
            crear('rect', { x: n.x, y: n.y, width: n.ancho, height: n.alto, rx: 10, class: 'df-caja', stroke: n.color, 'stroke-width': 1.6 }),
        );
        g.appendChild(crear('rect', { x: n.x, y: n.y, width: n.ancho, height: 24, rx: 10, fill: n.color, 'fill-opacity': 0.11 }));
        g.appendChild(crear('circle', { cx: n.x + 13, cy: n.y + 12, r: 4, fill: n.color }));
        g.appendChild(crear('text', { x: cx + 4, y: n.y + 16, 'text-anchor': 'middle', 'font-size': 11.5, 'font-weight': 700, fill: n.color }, n.titulo));
        if (n.subtitulo) g.appendChild(crear('text', { x: cx, y: n.y + 38, 'text-anchor': 'middle', 'font-size': 10.5, class: 'df-subtitulo' }, n.subtitulo));
        if (n.valor) g.appendChild(crear('text', { x: cx, y: n.y + 54, 'text-anchor': 'middle', 'font-size': 11, 'font-weight': 600, class: 'df-valor' }, n.valor));
        if (n.nota) g.appendChild(crear('text', { x: cx, y: n.y + n.alto - 8, 'text-anchor': 'middle', 'font-size': 10, 'font-weight': 700, class: 'df-nota' }, n.nota));
        nodosEl.set(n.id, g);

        return g;
    }

    function construir(): void {
        nodosEl.clear();
        aristasEl.clear();
        etiquetasEl.clear();

        const svg = crear('svg', {
            viewBox: `0 0 ${diagrama.ancho} ${diagrama.alto}`,
            class: 'df-svg',
            role: 'group',
            'aria-label': 'Diagrama de flujo',
        });

        const colores = [...new Set(diagrama.aristas.map((a) => a.color ?? COLOR_ARISTA))];
        const defs = crear('defs');
        colores.forEach((color, i) => {
            const marcador = crear('marker', {
                id: `${idBase}-m${i}`,
                markerWidth: 9,
                markerHeight: 9,
                refX: 7,
                refY: 3.5,
                orient: 'auto',
            });
            marcador.appendChild(crear('path', { d: 'M0,0 L8,3.5 L0,7 z', fill: color }));
            defs.appendChild(marcador);
        });
        svg.appendChild(defs);

        for (const n of diagrama.nodos) svg.appendChild(construirNodo(n));

        const rutas = new Map(diagrama.aristas.map((a) => [a.id, calcularArista(diagrama, a)]));

        for (const a of diagrama.aristas) {
            const ruta = rutas.get(a.id);
            if (!ruta) continue;
            const marcador = `url(#${idBase}-m${colores.indexOf(a.color ?? COLOR_ARISTA)})`;
            const origen = diagrama.nodos.find((n) => n.id === a.de);
            const destino = diagrama.nodos.find((n) => n.id === a.a);
            const g = crear('g', {
                class: 'df-arista',
                'data-arista': a.id,
                tabindex: 0,
                role: 'button',
                'aria-label': `${origen?.titulo ?? a.de} a ${destino?.titulo ?? a.a}: ${a.etiqueta}`,
            });
            g.appendChild(crear('path', { d: ruta.d, class: 'df-zona', fill: 'none' }));
            g.appendChild(
                crear('path', {
                    d: ruta.d,
                    class: 'df-linea',
                    fill: 'none',
                    stroke: a.color ?? COLOR_ARISTA,
                    'stroke-width': 2.5,
                    'marker-end': marcador,
                }),
            );
            aristasEl.set(a.id, g);
            svg.appendChild(g);
        }

        for (const s of diagrama.secciones ?? []) {
            svg.appendChild(crear('text', { x: s.x, y: s.y, class: 'df-seccion' }, s.texto));
        }

        // Las etiquetas van al final para quedar sobre todas las líneas
        for (const a of diagrama.aristas) {
            const ruta = rutas.get(a.id);
            if (!ruta) continue;
            const t = crear('text', { x: ruta.etiqueta.x, y: ruta.etiqueta.y, class: 'df-etiqueta' }, a.etiqueta);
            etiquetasEl.set(a.id, t);
            svg.appendChild(t);
        }

        scroll.replaceChildren(svg);
    }

    function enfocar(v: Vecinos, hover?: Element): void {
        contenedor.classList.add('df-enfoque');
        const nodos = new Set(v.nodos);
        const aristas = new Set(v.aristas);
        nodosEl.forEach((g, id) => g.classList.toggle('df-activo', nodos.has(id)));
        aristasEl.forEach((g, id) => {
            g.classList.toggle('df-activo', aristas.has(id));
            g.classList.toggle('df-hover', g === hover);
        });
        etiquetasEl.forEach((t, id) => t.classList.toggle('df-activo', aristas.has(id)));
    }

    function limpiar(): void {
        contenedor.classList.remove('df-enfoque');
        contenedor.querySelectorAll('.df-activo, .df-hover').forEach((e) => e.classList.remove('df-activo', 'df-hover'));
        tooltip.hidden = true;
    }

    function nodoDe(el: Element): NodoFlujo | undefined {
        const id = el.getAttribute('data-nodo');

        return diagrama.nodos.find((n) => n.id === id);
    }

    function aristaDe(el: Element): AristaFlujo | undefined {
        const id = el.getAttribute('data-arista');

        return diagrama.aristas.find((a) => a.id === id);
    }

    function textoTooltip(el: Element): string {
        const n = nodoDe(el);
        if (n) return opciones.tooltipNodo?.(n) ?? textoNodo(n);
        const a = aristaDe(el);

        return a ? (opciones.tooltipArista?.(a) ?? `${a.de} → ${a.a}\n${a.etiqueta}`) : '';
    }

    function mostrarTooltip(el: Element, x: number, y: number): void {
        const texto = textoTooltip(el);
        if (!texto) return;
        const caja = contenedor.getBoundingClientRect();
        tooltip.textContent = texto;
        tooltip.hidden = false;
        tooltip.style.left = `${Math.max(0, Math.min(x - caja.left + 12, caja.width - tooltip.offsetWidth))}px`;
        tooltip.style.top = `${y - caja.top + 14}px`;
    }

    function activar(el: Element, desdeFoco: boolean): void {
        const n = nodoDe(el);
        const a = n ? undefined : aristaDe(el);
        if (n) enfocar(vecinos(diagrama, n.id));
        else if (a) enfocar(vecinosArista(diagrama, a.id), el);
        else return;
        if (desdeFoco) {
            const r = el.getBoundingClientRect();
            mostrarTooltip(el, r.left + r.width / 2, r.bottom);
        }
    }

    function disparar(el: Element): void {
        const n = nodoDe(el);
        if (n) return opciones.onNodo?.(n);
        const a = aristaDe(el);
        if (a) opciones.onArista?.(a);
    }

    const SELECTOR = '[data-nodo], [data-arista]';

    const alEntrar = (e: PointerEvent): void => {
        const el = (e.target as Element).closest?.(SELECTOR);
        if (el) activar(el, false);
        else limpiar();
    };
    const alMover = (e: PointerEvent): void => {
        const el = (e.target as Element).closest?.(SELECTOR);
        if (el) mostrarTooltip(el, e.clientX, e.clientY);
    };
    scroll.addEventListener('pointerover', alEntrar);
    scroll.addEventListener('pointermove', alMover);
    scroll.addEventListener('pointerleave', limpiar);

    const quitar = [
        delegate<Element, FocusEvent>(scroll, 'focusin', SELECTOR, (_e, el) => activar(el, true)),
        delegate<Element, FocusEvent>(scroll, 'focusout', SELECTOR, limpiar),
        delegate<Element, MouseEvent>(scroll, 'click', SELECTOR, (_e, el) => disparar(el)),
        delegate<Element, KeyboardEvent>(scroll, 'keydown', SELECTOR, (e, el) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            disparar(el);
        }),
    ];

    construir();

    return {
        destruir(): void {
            quitar.forEach((f) => f());
            scroll.removeEventListener('pointerover', alEntrar);
            scroll.removeEventListener('pointermove', alMover);
            scroll.removeEventListener('pointerleave', limpiar);
            contenedor.replaceChildren();
            contenedor.classList.remove('diagrama-flujo', 'df-enfoque');
        },
        actualizar(nuevo: DiagramaFlujo): void {
            validar(nuevo);
            diagrama = nuevo;
            limpiar();
            construir();
        },
    };
}
