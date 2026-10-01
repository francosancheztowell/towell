/**
 * Índice BPM Tejedores. Vista: resources/views/modulos/bpm-tejedores/tel-bpm/index.blade.php
 * (config en data-tel-bpm de #tel-bpm-pagina). Antes era un <script> en la vista.
 *
 * La tabla es flux:table con .tabla-cebra / .tabla-seleccionable / data-filtros-columna: aquí
 * solo se pone `hidden` (alcance) y `aria-selected` (selección); el color sale de app.css.
 * Los botones del navbar viven fuera de la raíz: se delega en document.
 */
import { notify } from '../../../utils/notifications.ts';
import { delegate, onReady, qs, qsa } from '../../../utils/dom.ts';
import { filaSinResultados, leerDatos, rutaCon } from '../../urdido/comun/pagina.ts';
import {
    FILTROS_TEL_BPM,
    alcanceInicial,
    alternar,
    filaVisible,
    mensajeSinResultados,
    type FiltroTelBpm,
} from './logica.ts';

interface ConfigTelBpm {
    usuario: string;
    esSupervisor: boolean;
    usuarioEsOperador: boolean;
    /** Modal a reabrir tras un error de validación. */
    reabrir: 'create' | 'edit' | null;
    rutas: { consultar: string; actualizar: string; eliminar: string };
}

function abrir(id: string): void {
    const modal = document.getElementById(id);
    modal?.classList.remove('hidden');
    modal?.classList.add('flex');
}

function cerrar(id: string): void {
    const modal = document.getElementById(id);
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
}

function valor(id: string, texto: string): void {
    const campo = document.getElementById(id) as HTMLInputElement | null;
    if (campo) campo.value = texto;
}

function iniciar(raiz: HTMLElement, cfg: ConfigTelBpm): void {
    const cuerpo = qs<HTMLTableSectionElement>('#tb-body', raiz);
    let alcance = alcanceInicial(cfg.esSupervisor);
    let seleccionada: HTMLTableRowElement | null = null;

    const filas = (): HTMLTableRowElement[] => qsa<HTMLTableRowElement>('tr[data-tel-fila]', raiz);

    function aplicarAlcance(): void {
        let visibles = 0;
        for (const fila of filas()) {
            const ver = filaVisible({ status: fila.dataset.status ?? '', nombreRecibe: fila.dataset.nomrec ?? '' }, alcance, cfg.usuario);
            fila.hidden = !ver;
            if (ver) visibles++;
        }
        for (const filtro of FILTROS_TEL_BPM) {
            qs(`[data-tel-filtro="${filtro}"]`)?.setAttribute('aria-pressed', String(alcance[filtro]));
        }

        filaSinResultados(cuerpo, 12, visibles === 0 ? mensajeSinResultados(alcance) : null);
    }

    function pintarAcciones(): void {
        const editable = seleccionada?.dataset.status === 'Creado';
        const estados: Record<string, boolean> = { 'btn-consult': !!seleccionada, 'btn-edit': editable, 'btn-delete': editable };
        for (const [id, activo] of Object.entries(estados)) {
            const boton = document.getElementById(id) as HTMLButtonElement | null;
            if (!boton) continue;
            boton.disabled = !activo;
            boton.classList.toggle('opacity-50', !activo);
            boton.classList.toggle('cursor-not-allowed', !activo);
        }
    }

    function seleccionar(fila: HTMLTableRowElement): void {
        for (const f of filas()) f.setAttribute('aria-selected', 'false');
        fila.setAttribute('aria-selected', 'true');
        seleccionada = fila;
        pintarAcciones();
    }

    /** Fila seleccionada o aviso. */
    function exigirSeleccion(para: string): HTMLTableRowElement | null {
        if (!seleccionada) void notify.alert(`Debes seleccionar un folio para ${para}`, 'Selecciona un folio', 'info');
        return seleccionada;
    }

    function crear(): void {
        if (!cfg.usuarioEsOperador) {
            void notify.alert('No es posible crear el folio porque tu usuario no existe en la tabla de operadores.', 'No eres operador registrado', 'error');
            return;
        }
        abrir('modal-create');
    }

    function consultar(): void {
        const fila = exigirSeleccion('consultar');
        if (fila) window.location.href = rutaCon(cfg.rutas.consultar, { folio: fila.dataset.folio ?? '' });
    }

    function editar(): void {
        const fila = exigirSeleccion('editar');
        if (!fila) return;
        if (fila.dataset.status !== 'Creado') {
            void notify.alert('Sólo se puede editar en estado Creado', 'No editable', 'warning');
            return;
        }
        const folio = fila.dataset.folio ?? '';
        valor('pk-edit', folio);
        valor('edit-cve', fila.dataset.cveent ?? '');
        valor('edit-nombre', fila.dataset.noment ?? '');
        valor('edit-turno', fila.dataset.turnoent ?? '');
        const form = document.getElementById('form-edit') as HTMLFormElement | null;
        if (form) form.action = rutaCon(cfg.rutas.actualizar, { folio });
        abrir('modal-edit');
    }

    async function eliminar(): Promise<void> {
        const fila = exigirSeleccion('eliminar');
        if (!fila) return;
        const status = fila.dataset.status ?? '';
        if (status !== 'Creado') {
            void notify.alert(`No se puede eliminar un folio en estado "${status}". Solo se pueden eliminar folios en estado "Creado".`, 'Eliminación no permitida', 'error');
            return;
        }
        const folio = fila.dataset.folio ?? '';
        const confirmado = await notify.confirm({ title: '¿Eliminar?', text: `Se eliminará el folio ${folio}.`, confirmText: 'Sí, eliminar' });
        const form = document.getElementById('form-delete') as HTMLFormElement | null;
        if (!confirmado || !form) return;
        form.action = rutaCon(cfg.rutas.eliminar, { folio });
        form.submit();
    }

    /** Entrega ← operador elegido; no puede ser el mismo que recibe. */
    function autollenarEntrega(select: HTMLSelectElement): void {
        const recibe = (document.querySelector<HTMLInputElement>('#form-create input[name="CveEmplRec"]')?.value ?? '').trim();
        if (select.value && recibe && select.value === recibe) {
            void notify.alert('Entrega y Recibe no pueden ser el mismo operador.', 'Operador duplicado', 'warning');
            select.value = '';
        }
        const opcion = select.options[select.selectedIndex];
        valor('inp-nombre-ent', select.value ? opcion?.dataset.nombre ?? '' : '');
        valor('inp-cve-ent', select.value);
        valor('inp-turno-ent', select.value ? opcion?.dataset.turno ?? '' : '');
    }

    const acciones: Record<string, () => void> = {
        'btn-open-create': crear,
        'btn-consult': consultar,
        'btn-edit': editar,
        'btn-delete': () => void eliminar(),
    };
    for (const [id, accion] of Object.entries(acciones)) document.getElementById(id)?.addEventListener('click', accion);

    delegate(document, 'click', '[data-tel-filtro]', (_e, boton) => {
        alcance = alternar(alcance, boton.dataset.telFiltro as FiltroTelBpm);
        aplicarAlcance();
    });
    delegate(document, 'click', '[data-close]', (_e, boton) => cerrar((boton.dataset.close ?? '').replace('#', '')));
    delegate(raiz, 'click', 'tr[data-tel-fila]', (_e, fila) => seleccionar(fila as HTMLTableRowElement));

    const entrega = document.getElementById('sel-entrega') as HTMLSelectElement | null;
    entrega?.addEventListener('change', () => autollenarEntrega(entrega));
    if (entrega?.value) autollenarEntrega(entrega);

    aplicarAlcance();
    pintarAcciones();
    if (cfg.reabrir) abrir(cfg.reabrir === 'edit' ? 'modal-edit' : 'modal-create');
}

onReady(() => {
    const raiz = document.getElementById('tel-bpm-pagina');
    const cfg = leerDatos<ConfigTelBpm>(raiz, 'telBpm');
    if (raiz && cfg) iniciar(raiz, cfg);
});
