/**
 * Panel /admin (layouts/admin.blade.php): estilos del panel, selector de tema y lectura de barras.
 * El tema lo pinta el servidor desde la cookie towell_admin_tema, así no parpadea al cargar;
 * aquí solo se cambia en vivo y se guarda la elección.
 */
import './admin.css';
import { delegate, onReady } from '../../utils/dom.ts';

const COOKIE = 'towell_admin_tema';
const UN_ANIO = 60 * 60 * 24 * 365;

onReady(() => {
    delegate(document, 'click', '[data-admin-tema]', () => {
        const shell = document.querySelector<HTMLElement>('[data-admin-shell]');
        if (!shell) return;
        const oscuro = !shell.classList.contains('dark');
        shell.classList.toggle('dark', oscuro);
        document.cookie = `${COOKIE}=${oscuro ? 'oscuro' : 'claro'}; path=/admin; max-age=${UN_ANIO}; SameSite=Lax`;
    });

    // Lectura de x-admin.barras: una etiqueta flotante para todas (Livewire repinta las barras, no esta).
    const lectura = document.createElement('div');
    lectura.className = 'adm-lectura';
    lectura.setAttribute('aria-hidden', 'true');
    document.querySelector('[data-admin-shell]')?.append(lectura);

    document.addEventListener('pointerover', (e) => {
        const barra = (e.target as Element | null)?.closest?.<SVGRectElement>('.adm-barras rect[data-lectura]');
        if (!barra) {
            lectura.removeAttribute('data-visible');
            return;
        }
        const caja = barra.getBoundingClientRect();
        lectura.textContent = barra.dataset.lectura ?? '';
        lectura.style.left = `${caja.left + caja.width / 2}px`;
        lectura.style.top = `${caja.top}px`;
        lectura.setAttribute('data-visible', '');
    });
});
