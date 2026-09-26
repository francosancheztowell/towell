/**
 * Índice BPM (folios de Buenas Prácticas de Manufactura), una implementación para Urdido y Engomado (19-01).
 * Vista: resources/views/modulos/urdido/comun/bpm.blade.php (config en data-bpm del nodo #bpm-pagina).
 * Los botones del navbar viven fuera de la raíz, por eso las acciones se delegan en document.
 */
import { notify } from '../../../../utils/notifications.ts';
import { delegate, onReady, qs, qsa } from '../../../../utils/dom.ts';
import { el, icono, leerDatos, rutaCon } from '../pagina.ts';
import {
    CLASES_FILTRO_ACTIVO,
    CLASES_FILTRO_INACTIVO,
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
    const turno = qs<HTMLSelectElement>('#filter-turno', raiz);
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
                    turnoRecibe: fila.dataset.turnorecibe ?? '',
                },
                filtros,
                { esSupervisor: cfg.esSupervisor, usuario: cfg.usuario },
            );
            fila.style.display = ver ? '' : 'none';
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
            const boton = qs(`[data-bpm-filtro="${filtro}"]`, raiz);
            if (!boton) continue;
            const activo = filtros[filtro];
            for (const c of CLASES_FILTRO_ACTIVO[filtro]) boton.classList.toggle(c, activo);
            for (const c of CLASES_FILTRO_INACTIVO) boton.classList.toggle(c, !activo);
            boton.setAttribute('aria-pressed', String(activo));
        }
    }

    function refrescar(): void {
        pintarBotonesFiltro();
        aplicarFiltros();
    }

    function seleccionar(fila: HTMLTableRowElement): void {
        for (const f of filas()) {
            f.classList.remove('bg-blue-500', 'text-white');
            f.classList.add('hover:bg-blue-50');
            f.setAttribute('aria-selected', 'false');
        }
        fila.classList.remove('hover:bg-blue-50');
        fila.classList.add('bg-blue-500', 'text-white');
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
        filtros: () => abrir('modal-filters'),
        checklist: abrirChecklist,
        editar: abrirEdicion,
        eliminar: () => void eliminar(),
        'limpiar-filtros': () => {
            filtros = estadoInicial(cfg.esSupervisor);
            if (turno) turno.value = '';
            refrescar();
            cerrar('modal-filters');
        },
    };

    delegate(document, 'click', '[data-bpm-accion]', (_e, boton) => acciones[boton.dataset.bpmAccion ?? '']?.());
    delegate(raiz, 'click', '[data-bpm-cerrar]', (_e, boton) => cerrar(boton.dataset.bpmCerrar ?? ''));
    delegate(raiz, 'click', 'tr[data-bpm-fila]', (_e, fila) => seleccionar(fila as HTMLTableRowElement));
    delegate(raiz, 'click', '[data-bpm-filtro]', (_e, boton) => {
        filtros = alternar(filtros, boton.dataset.bpmFiltro as FiltroAlternable);
        refrescar();
    });
    turno?.addEventListener('change', () => {
        filtros = { ...filtros, turno: turno.value || '' };
        aplicarFiltros();
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
