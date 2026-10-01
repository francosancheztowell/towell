/**
 * Índice BPM (folios de Buenas Prácticas de Manufactura), una implementación para Urdido y Engomado (19-01).
 * Vista: resources/views/modulos/urdido/comun/bpm.blade.php (config en data-bpm del nodo #bpm-pagina).
 * Los botones del navbar viven fuera de la raíz, por eso las acciones se delegan en document.
 */
import { notify } from '../../../../utils/notifications.ts';
import { delegate, onReady, qs, qsa } from '../../../../utils/dom.ts';
import { el, icono, leerDatos, rutaCon } from '../pagina.ts';
import {
    alternar,
    camposAutollenado,
    estadoInicial,
    filaVisible,
    mensajeSinResultados,
    type EstadoFiltros,
    type FiltroAlternable,
} from './logica.ts';

interface ConfigBpm {
    variante: 'urdido' | 'engomado';
    esSupervisor: boolean;
    usuario: string;
    rutas: { checklist: string; actualizar: string; eliminar: string };
}

/** Input del modal Editar ← data-* de la fila seleccionada. */
const CAMPOS_EDICION: Readonly<Record<string, string>> = {
    edit_Folio: 'folio',
    edit_Status: 'status',
    edit_Fecha: 'fechaEdicion',
    edit_select_NombreEmplRec: 'nombreemplrec',
    edit_input_CveEmplRec: 'cveemplrec',
    edit_input_TurnoRecibe: 'turnorecibe',
    edit_NombreEmplEnt: 'nombreemplent',
    edit_CveEmplEnt: 'cveemplent',
    edit_TurnoEntrega: 'turnoentrega',
};

const FILTROS: readonly FiltroAlternable[] = ['terminados', 'misFolios', 'todos'];

function abrir(id: string): void {
    document.getElementById(id)?.classList.remove('hidden');
}

function cerrar(id: string): void {
    document.getElementById(id)?.classList.add('hidden');
}

function iniciar(raiz: HTMLElement, cfg: ConfigBpm): void {
    const cuerpo = qs<HTMLTableSectionElement>('#tb-body', raiz);
    let filtros: EstadoFiltros = estadoInicial(cfg.esSupervisor);
    let seleccionada: HTMLTableRowElement | null = null;

    const filas = (): HTMLTableRowElement[] => qsa<HTMLTableRowElement>('tr[data-bpm-fila]', raiz);

    function aplicarFiltros(): void {
        let visibles = 0;
        for (const fila of filas()) {
            const ver = filaVisible(
                {
                    status: fila.dataset.status ?? '',
                    nombreRecibe: fila.dataset.nombreemplrec ?? '',
                },
                filtros,
                { esSupervisor: cfg.esSupervisor, usuario: cfg.usuario },
            );
            fila.hidden = !ver; // hidden, no display: la cebra de la tabla cuenta solo las visibles
            if (ver) visibles++;
        }

        cuerpo?.querySelector('tr.no-results')?.remove();
        if (visibles === 0 && cuerpo) {
            cuerpo.append(
                el(
                    'tr',
                    { clase: 'no-results' },
                    el(
                        'td',
                        { clase: 'px-4 py-6 text-center text-slate-500', attrs: { colspan: '11' } },
                        el(
                            'div',
                            { clase: 'flex flex-col items-center gap-2' },
                            icono('fa-solid fa-inbox text-4xl text-gray-300'),
                            el('span', { clase: 'text-base font-medium', texto: mensajeSinResultados(filtros) }),
                        ),
                    ),
                ),
            );
        }
    }

    function pintarBotonesFiltro(): void {
        for (const filtro of FILTROS) {
            // Navbar (fuera de la raíz). El color del activo sale de aria-pressed (app.css).
            qs(`[data-bpm-filtro="${filtro}"]`)?.setAttribute('aria-pressed', String(filtros[filtro]));
        }
    }

    function refrescar(): void {
        pintarBotonesFiltro();
        aplicarFiltros();
    }

    function seleccionar(fila: HTMLTableRowElement): void {
        // El color lo pone .tabla-seleccionable (app.css) por aria-selected.
        for (const f of filas()) f.setAttribute('aria-selected', 'false');
        fila.setAttribute('aria-selected', 'true');
        seleccionada = fila;

        for (const id of ['btn-checklist', 'btn-edit', 'btn-delete']) {
            const boton = document.getElementById(id) as HTMLButtonElement | null;
            if (!boton) continue;
            boton.disabled = false;
            boton.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    /** Fila seleccionada o aviso (mismo texto que la vista original). */
    function exigirSeleccion(para: string): HTMLTableRowElement | null {
        if (!seleccionada) {
            void notify.alert(`Por favor seleccione un registro para ${para}`, 'Ningún registro seleccionado', 'warning');
        }
        return seleccionada;
    }

    function abrirChecklist(): void {
        const fila = exigirSeleccion('abrir el checklist');
        if (fila) window.location.href = rutaCon(cfg.rutas.checklist, { folio: fila.dataset.folio ?? '' });
    }

    /** Modal Editar. Sin botón en el navbar desde ae3fde85; queda por si se reactiva (data-bpm-accion="editar"). */
    function abrirEdicion(): void {
        const fila = exigirSeleccion('editar');
        if (!fila) return;
        for (const [id, clave] of Object.entries(CAMPOS_EDICION)) {
            const campo = document.getElementById(id) as HTMLInputElement | HTMLSelectElement | null;
            if (campo) campo.value = fila.dataset[clave] ?? '';
        }
        const folio = document.getElementById('edit_FolioDisplay');
        if (folio) folio.textContent = fila.dataset.folio ?? '';
        const form = document.getElementById('editForm') as HTMLFormElement | null;
        if (form) form.action = rutaCon(cfg.rutas.actualizar, { id: fila.dataset.id ?? '' });
        abrir('editModal');
    }

    async function eliminar(): Promise<void> {
        const fila = exigirSeleccion('eliminar');
        if (!fila) return;
        const confirmado = await notify.confirm({
            title: '¿Está seguro?',
            text: `¿Desea eliminar el folio ${fila.dataset.folio ?? 'este registro'}? Esta acción no se puede deshacer.`,
            confirmText: 'Sí, eliminar',
            confirmColor: '#dc2626',
        });
        const form = document.getElementById('deleteForm') as HTMLFormElement | null;
        if (!confirmado || !form) return;
        form.action = rutaCon(cfg.rutas.eliminar, { id: fila.dataset.id ?? '' });
        form.submit();
    }

    const acciones: Record<string, () => void> = {
        crear: () => abrir('createModal'),
        checklist: abrirChecklist,
        editar: abrirEdicion,
        eliminar: () => void eliminar(),
    };

    delegate(document, 'click', '[data-bpm-accion]', (_e, boton) => acciones[boton.dataset.bpmAccion ?? '']?.());
    delegate(raiz, 'click', '[data-bpm-cerrar]', (_e, boton) => cerrar(boton.dataset.bpmCerrar ?? ''));
    delegate(raiz, 'click', 'tr[data-bpm-fila]', (_e, fila) => seleccionar(fila as HTMLTableRowElement));
    delegate(document, 'click', '[data-bpm-filtro]', (_e, boton) => {
        filtros = alternar(filtros, boton.dataset.bpmFiltro as FiltroAlternable);
        refrescar();
    });

    // Autollenado: <select data-bpm-autollenar data-llenar-numero="input_X"> copia data-numero de la opción.
    delegate<HTMLSelectElement>(raiz, 'change', 'select[data-bpm-autollenar]', (_e, select) => {
        const opcion = select.options[select.selectedIndex];
        for (const [destino, valor] of camposAutollenado(select.dataset, opcion?.dataset ?? {})) {
            const campo = document.getElementById(destino) as HTMLInputElement | null;
            if (campo) campo.value = valor;
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        cerrar('createModal');
        cerrar('editModal');
    });

    // Al volver con "atrás" desde el checklist, recargar para ver el status nuevo.
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) window.location.reload();
    });

    refrescar();
}

onReady(() => {
    const raiz = document.getElementById('bpm-pagina');
    const cfg = leerDatos<ConfigBpm>(raiz, 'bpm');
    if (raiz && cfg) iniciar(raiz, cfg);
});
