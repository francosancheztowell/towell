/**
 * Solicitudes / Reporte de fallos y paros (19-08). Antes: <script> inline de
 * reporte-fallos-paros/index.blade.php. Lista de paros con filtros y "Terminar Paro".
 * Las filas salen de <template> del Blade con textContent (antes innerHTML).
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { leerDatos, mensajeError, ocultarBotonParo, soloPlaceholder } from '../comun/pagina.ts';
import type { RespuestaApi } from '../comun/pagina.ts';
import { fechaCorta } from '../comun/fechas.ts';
import {
    departamentosCombo,
    esActivo,
    hayFiltro,
    parametrosCarga,
    pasaFiltros,
    statusTrasCarga,
    valoresUnicos,
} from './logica.ts';
import type { Filtros, ModoCarga, ParoFila } from './logica.ts';

interface ConfigSolicitudes {
    rutas: { paros: string; departamentos: string; nuevoParo: string; finalizar: string };
    usuario: { nombre: string; numeroEmpleado: string; area: string };
}

const COLUMNAS: Array<keyof ParoFila> = ['Folio', 'Fecha', 'Hora', 'Depto', 'MaquinaId', 'TipoFallaId', 'Falla', 'NomEmpl'];

function iniciar(): void {
    const raiz = document.getElementById('pagina-solicitudes');
    const cfg = leerDatos<ConfigSolicitudes>(raiz);
    const tbody = document.getElementById('tbody-paros');
    const tplFila = document.getElementById('tpl-fila-paro') as HTMLTemplateElement | null;
    const tplMensaje = document.getElementById('tpl-fila-mensaje') as HTMLTemplateElement | null;
    if (!raiz || !cfg || !tbody || !tplFila || !tplMensaje) return;

    const $ = <T extends HTMLElement>(id: string): T | null => document.getElementById(id) as T | null;
    const selDepto = $<HTMLSelectElement>('filter-depto');
    const selStatus = $<HTMLSelectElement>('filter-status');
    const selMaquina = $<HTMLSelectElement>('filter-maquina');
    const chkTerminados = $<HTMLInputElement>('filter-incluir-terminados');
    const chkSoloMias = $<HTMLInputElement>('filter-solo-mis');
    const modal = $<HTMLDialogElement>('modal-filters');

    ocultarBotonParo(cfg.rutas.nuevoParo);

    let incluirTerminados = false;
    let ultimoModo: ModoCarga = 'default';
    let ultimoDepto: string | undefined;
    let catalogoDeptos: string[] = [];
    /** Identifica cada carga para descartar respuestas rezagadas que pisarían datos más nuevos. */
    let cargaId = 0;
    const paroDeFila = new WeakMap<HTMLTableRowElement, ParoFila>();

    const filas = (): HTMLTableRowElement[] => Array.from(tbody.querySelectorAll<HTMLTableRowElement>('tr.row-paro'));

    function filaMensaje(texto: string, clase = 'text-gray-700', cargando = false): HTMLTableRowElement {
        const fila = (tplMensaje!.content.firstElementChild as HTMLTableRowElement).cloneNode(true) as HTMLTableRowElement;
        const celda = fila.querySelector('td')!;
        celda.classList.add(clase);
        const span = celda.querySelector('span')!;
        span.querySelector('i')?.classList.toggle('hidden', !cargando);
        span.append(texto);
        return fila;
    }

    // Clases desde el <template>: Tailwind no escanea los .ts y no las generaría.
    const clases = (clave: string): string[] => (tplFila!.dataset[clave] ?? '').split(/\s+/).filter(Boolean);
    const [claseFilaSel = '', claseTextoSel = ''] = clases('claseSeleccionada');
    const [claseFilaNormal = '', claseTextoNormal = ''] = clases('claseNormal');

    function pintarFila(fila: HTMLTableRowElement, seleccionada: boolean): void {
        fila.classList.toggle(claseFilaSel, seleccionada);
        fila.classList.toggle(claseFilaNormal, !seleccionada);
        fila.querySelectorAll('td').forEach((td) => {
            td.classList.toggle(claseTextoSel, seleccionada);
            td.classList.toggle(claseTextoNormal, !seleccionada);
        });
        const radio = fila.querySelector<HTMLInputElement>('input[name="paro-seleccionado"]');
        if (radio) radio.checked = seleccionada;
    }

    function limpiarSeleccion(): void {
        filas().forEach((fila) => pintarFila(fila, false));
    }

    /** El radio marcado es la señal accesible; el fondo azul, el refuerzo visual. */
    function seleccionar(fila: HTMLTableRowElement | null): void {
        limpiarSeleccion();
        if (fila) pintarFila(fila, true);
    }

    function filaSeleccionadaVisible(): HTMLTableRowElement | null {
        const radio = tbody!.querySelector<HTMLInputElement>('input[name="paro-seleccionado"]:checked');
        const fila = radio?.closest<HTMLTableRowElement>('tr.row-paro') ?? null;
        return fila && !fila.hidden ? fila : null;
    }

    const filtros = (): Filtros => ({
        depto: (selDepto?.value ?? '').trim(),
        status: (selStatus?.value ?? '').trim(),
        maquina: (selMaquina?.value ?? '').trim(),
        soloMias: Boolean(chkSoloMias?.checked),
    });

    function aplicarFiltros(): void {
        const f = filtros();
        let visibles = 0;
        for (const fila of filas()) {
            const paro = paroDeFila.get(fila);
            fila.hidden = !paro || !pasaFiltros(paro, f, cfg!.usuario);
            if (!fila.hidden) visibles++;
        }
        // Si el filtro ocultó la fila seleccionada, la selección deja de valer.
        const marcada = tbody!.querySelector<HTMLInputElement>('input[name="paro-seleccionado"]:checked')?.closest('tr');
        if (marcada && (marcada as HTMLTableRowElement).hidden) limpiarSeleccion();

        const sinResultados = $('filter-no-results');
        if (sinResultados) sinResultados.hidden = !(hayFiltro(f) && visibles === 0);
    }

    function llenarCombo(select: HTMLSelectElement | null, valores: string[], valor?: string): void {
        if (!select) return;
        soloPlaceholder(select, 'Todos');
        valores.forEach((v) => select.add(new Option(v, v)));
        if (valor !== undefined) select.value = valor;
    }

    function llenarDepartamentos(deptos: string[], forzado: string | undefined): void {
        const area = cfg!.usuario.area;
        llenarCombo(selDepto, deptos, forzado !== undefined ? forzado : deptos.includes(area) ? area : undefined);
    }

    function crearFila(paro: ParoFila): HTMLTableRowElement {
        const fila = (tplFila!.content.firstElementChild as HTMLTableRowElement).cloneNode(true) as HTMLTableRowElement;
        paroDeFila.set(fila, paro);
        fila.dataset.paroId = String(paro.Id ?? '');
        for (const col of COLUMNAS) {
            const celda = fila.querySelector<HTMLElement>(`[data-col="${col}"]`);
            if (celda) celda.textContent = col === 'Fecha' ? fechaCorta(paro.Fecha) : String(paro[col] ?? '');
        }
        const estatus = String(paro.Estatus ?? '').trim();
        const badge = fila.querySelector<HTMLElement>('[data-col="Estatus"]');
        if (badge) {
            badge.textContent = estatus || '—';
            badge.classList.add(...clases(esActivo(estatus) ? 'claseActivo' : 'claseTerminado'));
        }
        const radio = fila.querySelector<HTMLInputElement>('input[type="radio"]');
        if (radio) {
            radio.value = String(paro.Id ?? '');
            const folio = String(paro.Folio ?? '').trim();
            radio.setAttribute('aria-label', folio ? `Seleccionar paro folio ${folio}` : 'Seleccionar paro');
        }
        return fila;
    }

    async function departamentosCatalogo(): Promise<string[]> {
        if (catalogoDeptos.length) return catalogoDeptos;
        try {
            const r = await http.get<RespuestaApi<string[]>>(cfg!.rutas.departamentos);
            catalogoDeptos = [...new Set((r.data ?? []).map((d) => String(d).trim()).filter(Boolean))].sort();
        } catch {
            // Sin catálogo el combo se arma con las áreas de los paros.
        }
        return catalogoDeptos;
    }

    async function cargarParos(modo: ModoCarga = 'default', deptoCombo?: string, forzarActivo = false): Promise<void> {
        limpiarSeleccion();
        const id = ++cargaId;
        tbody!.setAttribute('aria-busy', 'true');
        tbody!.replaceChildren(filaMensaje('Cargando paros...', 'text-lg', true));
        const statusPrevio = (selStatus?.value ?? '').trim();
        try {
            const catalogo = await departamentosCatalogo();
            const r = await http.get<RespuestaApi<ParoFila[]>>(cfg!.rutas.paros + parametrosCarga(modo, deptoCombo, incluirTerminados));
            if (id !== cargaId) return;

            const paros = r.data ?? [];
            llenarDepartamentos(departamentosCombo(catalogo, paros), deptoCombo);
            const statuses = valoresUnicos(paros, 'Estatus');
            llenarCombo(selStatus, statuses, statusTrasCarga(statuses, statusPrevio, incluirTerminados, forzarActivo));
            llenarCombo(selMaquina, valoresUnicos(paros, 'MaquinaId'));

            if (paros.length === 0) {
                tbody!.replaceChildren(filaMensaje('No hay paros/fallas'));
            } else {
                const sinResultados = filaMensaje('No hay paros con el filtro aplicado');
                sinResultados.id = 'filter-no-results';
                sinResultados.hidden = true;
                tbody!.replaceChildren(...paros.map(crearFila), sinResultados);
            }
            ultimoModo = modo;
            ultimoDepto = deptoCombo;
            aplicarFiltros();
        } catch (err) {
            if (id !== cargaId) return;
            tbody!.replaceChildren(filaMensaje(mensajeError(err, 'Error al cargar los paros. Por favor, recarga la página.'), 'text-red-700'));
        } finally {
            if (id === cargaId) tbody!.setAttribute('aria-busy', 'false');
        }
    }

    // Teclado: moverse con flechas en el grupo de radios dispara 'change'.
    tbody.addEventListener('change', (ev) => {
        const radio = (ev.target as Element).closest('input[name="paro-seleccionado"]');
        if (radio) seleccionar(radio.closest<HTMLTableRowElement>('tr.row-paro'));
    });
    // Ratón/tablet: tocar cualquier punto de la fila también selecciona.
    tbody.addEventListener('click', (ev) => {
        const fila = (ev.target as Element).closest<HTMLTableRowElement>('tr.row-paro');
        if (fila) seleccionar(fila);
    });

    // <dialog> nativo: foco atrapado, Escape y devolución del foco al botón que lo abrió.
    const abrirModal = (): void => {
        if (modal && !modal.open) modal.showModal();
    };
    const cerrarModal = (): void => modal?.close();
    $('btn-open-filters')?.addEventListener('click', abrirModal);
    $('btn-close-modal-filters')?.addEventListener('click', cerrarModal);
    modal?.addEventListener('click', (ev) => {
        if (ev.target === modal) cerrarModal();
    });

    selDepto?.addEventListener('change', () => {
        const v = selDepto.value.trim();
        void (v === '' ? cargarParos('todos', '') : cargarParos('depto', v));
    });
    chkTerminados?.addEventListener('change', () => {
        incluirTerminados = chkTerminados.checked;
        void cargarParos(ultimoModo, ultimoDepto, incluirTerminados);
    });
    selStatus?.addEventListener('change', aplicarFiltros);
    selMaquina?.addEventListener('change', aplicarFiltros);
    chkSoloMias?.addEventListener('change', aplicarFiltros);
    $('btn-clear-filter')?.addEventListener('click', async () => {
        if (selStatus) selStatus.value = '';
        if (selMaquina) selMaquina.value = '';
        const area = cfg.usuario.area;
        const deptoArea = area && selDepto && Array.from(selDepto.options).some((o) => o.value === area) ? area : '';
        if (chkSoloMias) chkSoloMias.checked = false;
        if (chkTerminados) chkTerminados.checked = false;
        incluirTerminados = false;
        await cargarParos('default', deptoArea);
        cerrarModal();
    });

    $('btn-nuevo-paro')?.addEventListener('click', (ev) => {
        ev.preventDefault();
        window.location.href = cfg.rutas.nuevoParo;
    });

    $('btn-terminar-paro')?.addEventListener('click', (ev) => {
        ev.preventDefault();
        // Solo vale la selección que sigue visible tras recargar o refiltrar.
        const fila = filaSeleccionadaVisible();
        if (!fila) {
            void notify.alert('Por favor, seleccione un paro de la tabla para finalizar.', 'Seleccione un paro', 'warning');
            return;
        }
        // El servidor rechaza recerrar un paro (422): se avisa antes de llenar el formulario.
        if (!esActivo(paroDeFila.get(fila)?.Estatus)) {
            void notify.alert('Solo se pueden cerrar los paros con estatus Activo.', 'Este paro ya fue finalizado', 'warning');
            return;
        }
        window.location.href = cfg.rutas.finalizar + '?id=' + encodeURIComponent(fila.dataset.paroId ?? '');
    });

    void cargarParos();
}

iniciar();
