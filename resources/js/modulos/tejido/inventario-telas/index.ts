/**
 * Inventario de telas (19-02): menú "Telares" del navbar y scroll al telar. Antes era un
 * <script> inline en resources/views/modulos/tejido/inventario-telas/inventario-telas.blade.php
 * con window.irATelar llamado desde onclick. La lógica de requerimientos por telar sigue en
 * resources/js/tejido/inventario-telas.ts (entrada fija de vite.config.js).
 */
import { delegate, onReady, qs, qsa } from '../../../utils/dom.ts';
import { destinoScroll, telarDesdeUrl, urlParaTelar } from './logica.ts';

/** Primer ancestro con scroll vertical; si no hay, el documento. */
function contenedorScroll(nodo: HTMLElement): Element {
    let n = nodo.parentElement;
    while (n && n !== document.body) {
        const cs = getComputedStyle(n);
        if ((cs.overflowY === 'auto' || cs.overflowY === 'scroll') && n.scrollHeight > n.clientHeight) return n;
        n = n.parentElement;
    }
    return document.scrollingElement ?? document.documentElement;
}

function iniciar(): void {
    const btn = qs<HTMLButtonElement>('#btnDropdownTelares');
    const menu = qs('#menuDropdownTelares');
    const icono = qs('#iconDropdown');
    if (!btn || !menu) return;

    const abrirMenu = (abrir: boolean): void => {
        menu.classList.toggle('hidden', !abrir);
        icono?.classList.toggle('rotate-180', abrir);
        icono?.classList.toggle('rotate-0', !abrir);
        btn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    };

    const irATelar = (telar: string): void => {
        // Se cierra un poco después para que el clic termine de procesarse (como antes).
        window.setTimeout(() => abrirMenu(false), 100);
        qsa('[id^="telar-"]').forEach((el) => el.classList.remove('hidden'));

        if (!telar) {
            history.replaceState(null, '', urlParaTelar(location.href, ''));
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        const el = document.getElementById('telar-' + telar);
        if (!el) return;
        const fijo = qs('nav.sticky, nav.fixed, .sticky.top-0');
        const scroller = contenedorScroll(el);
        // Bug de antes: con el documento como scroller, su rect.top ya es -scrollY y el
        // scroll actual se contaba dos veces (se pasaba de largo si la página ya estaba bajada).
        const esDocumento = scroller === document.scrollingElement || scroller === document.documentElement;
        const top = destinoScroll(
            el.getBoundingClientRect().top,
            esDocumento ? 0 : scroller.getBoundingClientRect().top,
            scroller.scrollTop || window.pageYOffset || 0,
            fijo ? fijo.getBoundingClientRect().height : 0,
        );
        scroller.scrollTo({ top, behavior: 'smooth' });
        history.replaceState(null, '', urlParaTelar(location.href, telar));
    };

    btn.addEventListener('click', (ev) => {
        ev.stopPropagation();
        abrirMenu(menu.classList.contains('hidden'));
    });

    delegate(menu, 'click', '[data-accion="ir-telar"]', (ev, el) => {
        ev.stopPropagation();
        irATelar(el.dataset.telar ?? '');
    });

    document.addEventListener('click', (ev) => {
        const destino = ev.target as Node | null;
        if (!menu.classList.contains('hidden') && destino && !btn.contains(destino) && !menu.contains(destino)) abrirMenu(false);
    });

    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape' && !menu.classList.contains('hidden')) {
            abrirMenu(false);
            btn.focus();
        }
    });

    const telar = telarDesdeUrl(location.href);
    if (telar) window.setTimeout(() => irATelar(telar), 500);
}

onReady(iniciar);
