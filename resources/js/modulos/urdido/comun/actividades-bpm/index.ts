/**
 * Catálogo Actividades BPM, una implementación para Urdido y Engomado (19-01).
 * Vista: resources/views/modulos/urdido/comun/actividades-bpm.blade.php (config en data-actividades-bpm).
 * Los botones Nueva/Editar/Eliminar del navbar están fuera de la raíz: se delegan en document.
 * Antes, en Urdido cada botón tenía onclick inline Y un addEventListener: disparaba dos veces
 * (dos confirmaciones de borrado). Ahora hay un solo listener delegado.
 */
import { notify } from '../../../../utils/notifications.ts';
import { delegate, onReady, qsa } from '../../../../utils/dom.ts';
import { leerDatos, rutaCon } from '../pagina.ts';
import { alternarSeleccion, valoresEdicion } from './logica.ts';

interface ConfigActividades {
    variante: 'urdido' | 'engomado';
    conMaquina: boolean;
    rutas: { actualizar: string; eliminar: string };
}

const MODALES = ['createModal', 'editModal'] as const;

function abrir(id: string): void {
    document.getElementById(id)?.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function cerrar(id: string): void {
    document.getElementById(id)?.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function iniciar(raiz: HTMLElement, cfg: ConfigActividades): void {
    let seleccion: string | null = null;

    const filaSeleccionada = (): HTMLTableRowElement | null =>
        seleccion === null ? null : raiz.querySelector<HTMLTableRowElement>(`tr[data-key="${CSS.escape(seleccion)}"]`);

    function pintarSeleccion(): void {
        for (const fila of qsa<HTMLTableRowElement>('tr[data-key]', raiz)) {
            fila.setAttribute('aria-selected', String(fila.dataset.key === seleccion));
        }
        for (const id of ['btn-top-edit', 'btn-top-delete']) {
            const boton = document.getElementById(id) as HTMLButtonElement | null;
            if (boton) boton.disabled = seleccion === null;
        }
    }

    function editar(): void {
        const fila = filaSeleccionada();
        if (!fila || seleccion === null) return;
        for (const [campo, valor] of Object.entries(valoresEdicion(fila.dataset, cfg.conMaquina))) {
            const input = document.getElementById(`edit${campo}`) as HTMLInputElement | HTMLSelectElement | null;
            if (input) input.value = valor;
        }
        const form = document.getElementById('editForm') as HTMLFormElement | null;
        if (form) form.action = rutaCon(cfg.rutas.actualizar, { id: seleccion });
        abrir('editModal');
    }

    async function eliminar(): Promise<void> {
        if (seleccion === null) return;
        const id = seleccion;
        const confirmado = await notify.confirm({
            title: '¿Estás seguro?',
            text: 'Esta acción no se puede deshacer',
            confirmText: 'Sí, eliminar',
            confirmColor: '#dc2626',
        });
        const form = document.getElementById('globalDeleteForm') as HTMLFormElement | null;
        if (!confirmado || !form) return;
        form.action = rutaCon(cfg.rutas.eliminar, { id });
        form.submit();
    }

    const acciones: Record<string, () => void> = {
        nueva: () => abrir('createModal'),
        editar,
        eliminar: () => void eliminar(),
    };

    delegate(document, 'click', '[data-actividades-accion]', (e, boton) => {
        e.preventDefault();
        acciones[boton.dataset.actividadesAccion ?? '']?.();
    });
    delegate(raiz, 'click', '[data-actividades-cerrar]', (_e, boton) => cerrar(boton.dataset.actividadesCerrar ?? ''));
    delegate(raiz, 'click', 'tr[data-key]', (_e, fila) => {
        seleccion = alternarSeleccion(seleccion, fila.dataset.key);
        pintarSeleccion();
    });

    // Cerrar al tocar el fondo o con ESC.
    for (const id of MODALES) {
        const modal = document.getElementById(id);
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) cerrar(id);
        });
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') MODALES.forEach(cerrar);
    });

    pintarSeleccion();
}

onReady(() => {
    const raiz = document.getElementById('actividades-bpm-pagina');
    const cfg = leerDatos<ConfigActividades>(raiz, 'actividadesBpm');
    if (raiz && cfg) iniciar(raiz, cfg);
});
