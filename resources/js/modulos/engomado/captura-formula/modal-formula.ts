/**
 * Captura de Fórmula: selección de fila, modal Crear / Editar / Ver (un solo #createModal) y
 * eliminación. El guardado sigue siendo el POST/PUT del formulario (redirect con flash).
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { debounce } from '../../../utils/format.ts';
import { el, exigirExito, icono, mensajeError, rutaCon, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import { estado, esMetodo, mostrar, ponerValor, porId, valorDe } from './estado.ts';
import {
    cargandoComponentes,
    cargarComponentesAx,
    estadoTabla,
    leerComponentesDeTabla,
    mostrarErrorComponentes,
    renderizarComponentes,
} from './componentes.ts';
import {
    LITROS_MAX,
    aplicarMaxConsumoTotal,
    componenteGuardado,
    componentesParaGuardar,
    esPrimerRegistro,
    mensajeLimiteConsumo,
    num,
    opcionesFormula,
    primerConsumoExcedido,
    queryFormulasDisponibles,
    redondear2,
    statusEsFinalizado,
    tieneArticulo,
    validarCaptura,
    type Componente,
} from './logica.ts';

interface Formulacion {
    Id: number;
    Folio?: string | null;
    Cuenta?: string | null;
    Calibre?: number | string | null;
    Tipo?: string | null;
    CveEmpl?: string | null;
    NomEmpl?: string | null;
    Olla?: string | number | null;
    Formula?: string | null;
    Kilos?: number | string | null;
    Litros?: number | string | null;
    TiempoCocinado?: number | string | null;
    Solidos?: number | string | null;
    Viscocidad?: number | string | null;
    obs_calidad?: string | null;
}

interface RespuestaFormulacion extends RespuestaApi {
    formulacion?: Formulacion;
    componentes?: Componente[];
}

const HEADER = 'text-white px-6 py-4 rounded-t-xl flex justify-between items-center sticky top-0 z-10';
const HEADER_AZUL = `bg-gradient-to-r from-blue-500 to-blue-600 ${HEADER}`;
const HEADER_AMARILLO = `bg-gradient-to-r from-yellow-500 to-yellow-600 ${HEADER}`;
const PLACEHOLDER_FORMULA = '-- Seleccione fórmula --';

// ---------------------------------------------------------------- selección y botones

function formulacionTieneAX1(): boolean {
    return (estado.filaSeleccionada?.dataset.ax ?? '') === '1';
}

function habilitarBoton(id: string, habilitado: boolean): void {
    const boton = document.getElementById(id) as HTMLButtonElement | null;
    if (!boton) return;
    boton.disabled = !habilitado;
    boton.classList.toggle('opacity-50', !habilitado);
    boton.classList.toggle('cursor-not-allowed', !habilitado);
}

export function actualizarBotonesAccion(): void {
    const hay = !!estado.filaSeleccionada;
    const bloqueadoPorAX = formulacionTieneAX1();
    habilitarBoton('btn-view', hay);
    habilitarBoton('btn-delete', hay && !bloqueadoPorAX);
    habilitarBoton('btn-edit', hay && !bloqueadoPorAX);
}

export function seleccionarFila(fila: HTMLTableRowElement): void {
    document.querySelectorAll('#formulaTable tbody tr.selected').forEach((f) => f.classList.remove('selected'));
    estado.filaSeleccionada = fila;
    estado.folioSeleccionado = fila.dataset.folio ?? '';
    estado.idSeleccionado = parseInt(fila.dataset.id ?? '', 10) || 0;
    fila.classList.add('selected');
    actualizarBotonesAccion();
}

function formulacionSeleccionadaValida(): number | null {
    if (!estado.filaSeleccionada || !estado.folioSeleccionado || !estado.idSeleccionado) {
        void notify.alert('Debe seleccionar una fórmula primero', 'Selección requerida', 'warning');
        return null;
    }
    return estado.idSeleccionado;
}

// ---------------------------------------------------------------- folio y fórmula del modal

function selectFolio(): HTMLSelectElement {
    return porId<HTMLSelectElement>('create_folio_prog');
}

function selectFormula(): HTMLSelectElement {
    return porId<HTMLSelectElement>('create_formula');
}

function opcionFolio(folio: string): HTMLOptionElement | undefined {
    return [...selectFolio().options].find((o) => o.value === folio);
}

function filasDelFolio(folio: string): HTMLTableRowElement[] {
    return [...document.querySelectorAll<HTMLTableRowElement>(`#formulaTableBody tr[data-folio="${CSS.escape(folio)}"]`)];
}

function actualizarPresentacionFolio(textoPlano: boolean): void {
    const select = selectFolio();
    const display = porId<HTMLInputElement>('create_folio_prog_display');
    display.value = select.value || '';
    display.classList.toggle('hidden', !textoPlano);
    select.classList.toggle('hidden', textoPlano);
}

/** Pone el folio real aunque ya no esté en la lista (programa finalizado): opción temporal. */
function ponerFolio(folio: string | null | undefined): void {
    const select = selectFolio();
    const display = porId<HTMLInputElement>('create_folio_prog_display');
    const valor = String(folio ?? '').trim();
    select.querySelector('option[data-temp-folio="1"]')?.remove();
    if (valor && !opcionFolio(valor)) {
        const temporal = new Option(valor, valor, true, true);
        temporal.setAttribute('data-temp-folio', '1');
        select.add(temporal);
    }
    select.value = valor;
    display.value = valor;
}

function reiniciarSelectFormula(): void {
    selectFormula().replaceChildren(el('option', { texto: PLACEHOLDER_FORMULA, attrs: { value: '' } }));
}

function ponerFormula(valor: string): void {
    const v = valor || '';
    ponerValor('create_formula_value', v);
    const select = selectFormula();
    if (v && ![...select.options].some((o) => o.value === v)) {
        select.append(el('option', { texto: v, attrs: { value: v } }));
    }
    select.value = v;
    estado.formulaActual = v;
}

function actualizarEstadoFormulaSelect(esPrimero: boolean): void {
    const select = selectFormula();
    estado.formulaSoloConsulta = estado.soloLectura || !esPrimero;
    select.disabled = false;
    select.classList.toggle('bg-gray-50', estado.formulaSoloConsulta);
    select.classList.toggle('cursor-not-allowed', estado.formulaSoloConsulta);
    select.classList.toggle('cursor-pointer', !estado.formulaSoloConsulta);
}

/** Fórmulas de AX (+ la guardada si AX no la trae); si falla, solo la guardada. */
async function poblarFormulas(bomEng: string, formulaGuardada: string, esPrimero: boolean): Promise<void> {
    const query = queryFormulasDisponibles(bomEng, formulaGuardada);
    let formulas: string[] = formulaGuardada ? [formulaGuardada] : [];
    if (query) {
        try {
            const r = await http.get<RespuestaApi & { formulas?: string[] }>(`${estado.cfg.rutas.formulasDisponibles}?${query}`);
            formulas = opcionesFormula(r.success ? r.formulas : [], formulaGuardada);
        } catch {
            // Sin AX: queda la fórmula guardada (igual que antes).
        }
    }
    reiniciarSelectFormula();
    selectFormula().append(...formulas.map((f) => el('option', { texto: f, attrs: { value: f } })));
    ponerFormula(formulaGuardada);
    actualizarEstadoFormulaSelect(esPrimero);
}

/** Crear: bloquea el guardado si el programa del folio está Finalizado/Terminado. */
function disponibilidadPorStatus(mostrarAlerta: boolean): boolean {
    if (!esMetodo('POST')) return true;
    const status = selectFolio().selectedOptions[0]?.dataset.status ?? '';
    const bloqueado = statusEsFinalizado(status);
    const boton = porId<HTMLButtonElement>('btn-submit-create');
    boton.disabled = bloqueado;
    boton.classList.toggle('opacity-50', bloqueado);
    boton.classList.toggle('cursor-not-allowed', bloqueado);
    boton.classList.toggle('pointer-events-none', bloqueado);
    if (bloqueado && mostrarAlerta) {
        void notify.alert('No se puede registrar una fórmula para un folio con status Finalizado/Terminado.', 'Registro bloqueado', 'warning');
    }
    return !bloqueado;
}

/** Al elegir folio del programa: datos del programa, fórmulas de AX y componentes. */
export async function cargarDatosPrograma(mostrarAlerta = true): Promise<void> {
    const opcion = selectFolio().selectedOptions[0];
    if (!opcion?.value) {
        ponerValor('create_cuenta', '');
        ponerValor('create_calibre', '');
        ponerValor('create_tipo', '');
        reiniciarSelectFormula();
        ponerValor('create_formula_value', '');
        estado.formulaActual = '';
        actualizarEstadoFormulaSelect(false);
        estado.componentes = [];
        renderizarComponentes();
        estadoTabla(false);
        disponibilidadPorStatus(false);
        return;
    }

    const d = opcion.dataset;
    ponerValor('create_cuenta', d.cuenta ?? '');
    ponerValor('create_calibre', d.calibre ?? '');
    ponerValor('create_tipo', d.tipo ?? '');

    const esPrimero = filasDelFolio(opcion.value).length === 0;
    const bomEng = d.bomeng ?? '';
    const bomFormula = d.formula ?? '';
    if (bomEng || bomFormula) {
        await poblarFormulas(bomEng, bomFormula, esPrimero);
    } else {
        reiniciarSelectFormula();
        ponerFormula('');
        actualizarEstadoFormulaSelect(esPrimero);
    }

    if (estado.formulaActual) {
        void cargarComponentesAx(estado.formulaActual);
    } else {
        estado.componentes = [];
        renderizarComponentes();
        mostrar('create_componentes_tabla_container', false);
    }

    ponerValor('create_nom_empl', estado.cfg.usuario.nombre);
    ponerValor('create_cve_empl', estado.cfg.usuario.numero);
    disponibilidadPorStatus(mostrarAlerta);
}

/** Cambio manual del select de fórmula (solo en el primer registro del folio). */
function cambiarFormula(): void {
    const select = selectFormula();
    if (estado.formulaSoloConsulta) {
        select.value = estado.formulaActual || valorDe('create_formula_value');
        actualizarBotonGuardar();
        return;
    }
    ponerFormula(select.value);
    if (estado.formulaActual) {
        void cargarComponentesAx(estado.formulaActual);
    } else {
        estado.componentes = [];
        renderizarComponentes();
        mostrar('create_componentes_tabla_container', false);
    }
    actualizarBotonGuardar();
}

// ---------------------------------------------------------------- snapshot (Editar)

function snapshot(): string {
    const componentes = leerComponentesDeTabla()
        .filter(tieneArticulo)
        .map((c) => ({ ItemId: c.ItemId, ItemName: c.ItemName, ConfigId: c.ConfigId, ConsumoTotal: c.ConsumoTotal, ConsumoUnitario: c.ConsumoUnitario }));
    const v = (id: string, def = ''): string => valorDe(id) || def;
    return JSON.stringify({
        Formula: v('create_formula_value'),
        Olla: v('create_olla'),
        Kilos: v('create_kilos', '0'),
        Litros: v('create_litros', '0'),
        TiempoCocinado: v('create_tiempo', '0'),
        Solidos: v('create_solidos', '0'),
        Viscocidad: v('create_viscocidad', '0'),
        NomEmpl: v('create_nom_empl'),
        CveEmpl: v('create_cve_empl'),
        obs_calidad: v('create_obs_calidad'),
        componentes,
    });
}

function haCambiado(): boolean {
    return estado.snapshotInicial !== null && snapshot() !== estado.snapshotInicial;
}

export function actualizarBotonGuardar(): void {
    if (!estado.modoEdicion || estado.soloLectura || !esMetodo('PUT')) return;
    const hayCambios = haCambiado();
    const boton = porId<HTMLButtonElement>('btn-submit-create');
    boton.disabled = !hayCambios;
    boton.classList.toggle('opacity-50', !hayCambios);
    boton.classList.toggle('cursor-not-allowed', !hayCambios);
    boton.classList.toggle('pointer-events-none', !hayCambios);
}

// ---------------------------------------------------------------- abrir / cerrar

function setModalSoloLectura(soloLectura: boolean): void {
    const modal = porId('createModal');
    modal.classList.toggle('view-only-presentation', soloLectura);
    modal.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea').forEach((campo) => {
        if (campo.type === 'hidden' || campo.id === 'create_formula') return; // la fórmula: actualizarEstadoFormulaSelect()
        if (campo.id === 'create_folio_prog_display') {
            campo.setAttribute('readonly', 'readonly');
            campo.disabled = false;
            campo.classList.remove('bg-gray-50', 'text-gray-700', 'cursor-not-allowed', 'pointer-events-none');
            return;
        }
        // Nunca se desbloquean: Folio, Fecha, Hora, No Empleado, Operador.
        if (campo.classList.contains('campo-siempre-bloqueado')) {
            campo.setAttribute('readonly', 'readonly');
            if (campo instanceof HTMLSelectElement) {
                campo.style.pointerEvents = 'none';
                campo.tabIndex = -1;
            }
            // Sin disabled en los que tienen name (fecha, Hora, FolioProg) para que se envíen.
            if (!campo.name || campo.id === 'create_display_numero' || campo.id === 'create_display_operador') campo.disabled = true;
            campo.classList.add('bg-gray-50', 'cursor-not-allowed');
            return;
        }
        if (soloLectura) {
            campo.setAttribute('readonly', 'readonly');
            campo.classList.add('bg-gray-50', 'text-gray-700', 'cursor-not-allowed');
            if (campo instanceof HTMLSelectElement) {
                campo.disabled = true;
                campo.classList.add('pointer-events-none');
            }
        } else {
            campo.removeAttribute('readonly');
            campo.disabled = false;
            campo.classList.remove('bg-gray-50', 'text-gray-700', 'cursor-not-allowed', 'pointer-events-none');
        }
    });

    const agregar = porId<HTMLButtonElement>('btn-create-add-row');
    agregar.classList.toggle('hidden', soloLectura);
    agregar.disabled = soloLectura;
    agregar.classList.toggle('opacity-50', soloLectura);
    agregar.classList.toggle('cursor-not-allowed', soloLectura);

    const guardar = porId<HTMLButtonElement>('btn-submit-create');
    guardar.classList.toggle('hidden', soloLectura);
    guardar.disabled = soloLectura;
    porId('btn-cancel-create').classList.toggle('hidden', soloLectura);
}

function alturaModal(max: '70' | '90'): void {
    const contenido = porId('createModalContent');
    contenido.classList.toggle('max-h-[70vh]', max === '70');
    contenido.classList.toggle('max-h-[90vh]', max === '90');
}

function botonGuardar(texto: string, colores: 'azul' | 'amarillo'): HTMLButtonElement {
    const boton = porId<HTMLButtonElement>('btn-submit-create');
    const textoBoton = document.getElementById('submit-text-create');
    if (textoBoton) textoBoton.textContent = texto;
    const azul = colores === 'azul';
    boton.classList.toggle('bg-blue-600', azul);
    boton.classList.toggle('hover:bg-blue-700', azul);
    boton.classList.toggle('bg-yellow-600', !azul);
    boton.classList.toggle('hover:bg-yellow-700', !azul);
    return boton;
}

export function abrirNueva(): void {
    estado.modoEdicion = false;
    estado.snapshotInicial = null;
    estado.soloLectura = false;
    porId('createModal').classList.remove('hidden');

    porId('create_modal_title').textContent = 'Nueva Formulación de Engomado';
    ponerValor('create_method', 'POST');
    porId<HTMLFormElement>('createForm').action = estado.cfg.rutas.guardar;

    const guardar = botonGuardar('Crear Formulación', 'azul');
    guardar.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
    guardar.disabled = false;
    porId('create_modal_header').className = HEADER_AZUL;
    alturaModal('90');

    // Limpiar para capturar una nueva. Operador y No. Empleado vuelven al usuario en sesión
    // (antes quedaban los de la última formulación abierta en Editar/Ver).
    ['create_display_numero', 'create_display_operador'].forEach((id) => {
        const campo = porId<HTMLInputElement>(id);
        campo.value = campo.defaultValue;
    });
    ponerValor('create_formulacion_id', '');
    ponerValor('create_olla', '');
    ['create_kilos', 'create_litros', 'create_tiempo', 'create_solidos', 'create_viscocidad'].forEach((id) => ponerValor(id, '0'));
    ponerValor('create_obs_calidad', '');
    estado.kilos = 0;
    estado.litros = 0;
    estado.componentes = [];
    estado.formulaActual = '';

    reiniciarSelectFormula();
    selectFormula().disabled = false;
    ponerValor('create_formula_value', '');
    actualizarEstadoFormulaSelect(false);

    const select = selectFolio();
    if (select.value) {
        actualizarEstadoFormulaSelect(filasDelFolio(select.value).length === 0);
        void cargarDatosPrograma(false);
    }

    actualizarPresentacionFolio(false);
    disponibilidadPorStatus(false);
    setModalSoloLectura(false);
}

export function cerrarModal(): void {
    porId('createModal').classList.add('hidden');
}

/** Llena el modal con una formulación guardada (EngProduccionFormulacion + EngFormulacionLine). */
function llenarConFormulacion(f: Formulacion, componentes: Componente[]): void {
    ponerFolio(f.Folio);
    ponerValor('create_cuenta', f.Cuenta ?? '');
    ponerValor('create_calibre', f.Calibre ?? '');
    ponerValor('create_tipo', f.Tipo ?? '');

    const ids = filasDelFolio(f.Folio ?? '').map((fila) => parseInt(fila.dataset.id ?? '0', 10));
    const esPrimero = esPrimerRegistro(ids, f.Id);
    const bomEng = opcionFolio(f.Folio ?? '')?.dataset.bomeng ?? '';
    if (bomEng) {
        void poblarFormulas(bomEng, f.Formula ?? '', esPrimero);
    } else {
        reiniciarSelectFormula();
        ponerFormula(f.Formula ?? '');
        actualizarEstadoFormulaSelect(esPrimero);
    }
    actualizarPresentacionFolio(true);

    ponerValor('create_olla', f.Olla ?? '');
    // Kilos y Litros antes de pintar componentes: el Consumo Total depende de los Litros.
    ponerValor('create_kilos', f.Kilos || '0');
    estado.kilos = num(f.Kilos);
    ponerValor('create_litros', f.Litros || '0');
    estado.litros = num(f.Litros);
    ponerValor('create_tiempo', f.TiempoCocinado || '0');
    ponerValor('create_solidos', num(f.Solidos).toFixed(2));
    ponerValor('create_viscocidad', f.Viscocidad || '0');

    ponerValor('create_nom_empl', f.NomEmpl ?? '');
    ponerValor('create_cve_empl', f.CveEmpl ?? '');
    ponerValor('create_obs_calidad', f.obs_calidad ?? '');
    ponerValor('create_display_numero', f.CveEmpl ?? '');
    ponerValor('create_display_operador', f.NomEmpl ?? '');
    ponerFormula(f.Formula ?? '');

    // Componentes de EngFormulacionLine por EngProduccionFormulacionId (no los del BOM de AX).
    estado.componentes = componentes.map((c) => componenteGuardado(c, num(f.Litros)));
    renderizarComponentes();
    mostrar('create_componentes_tabla_container', estado.componentes.length > 0);
}

/** Editar (PUT) o Ver (solo lectura) una formulación existente. */
export async function abrirExistente(modo: 'editar' | 'ver'): Promise<void> {
    const formulacionId = formulacionSeleccionadaValida();
    if (!formulacionId) return;
    const ver = modo === 'ver';

    estado.modoEdicion = true;
    estado.soloLectura = ver;
    porId('createModal').classList.remove('hidden');
    actualizarPresentacionFolio(true);

    if (ver) {
        porId('create_modal_title').textContent = 'Visualización de Fórmula';
        porId('create_modal_header').className = HEADER_AZUL;
        alturaModal('70');
    } else {
        porId('create_modal_title').textContent = 'Editar Formulación';
        ponerValor('create_method', 'PUT');
        porId<HTMLFormElement>('createForm').action = rutaCon(estado.cfg.rutas.formulacion, { folio: estado.folioSeleccionado });
        // Deshabilitado hasta que haya cambios (snapshot).
        const guardar = botonGuardar('Guardar Cambios', 'amarillo');
        guardar.disabled = true;
        guardar.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
        porId('create_modal_header').className = HEADER_AMARILLO;
        alturaModal('90');
    }

    cargandoComponentes();
    setModalSoloLectura(ver);

    try {
        const url = `${estado.cfg.rutas.porId}?${new URLSearchParams({ id: String(formulacionId) })}`;
        const r = exigirExito(await http.get<RespuestaFormulacion>(url), 'Error al cargar la formulación');
        mostrar('create_componentes_loading', false);
        const f = r.formulacion;
        if (!f) throw new Error('sin formulación');
        if (Number(f.Id) !== formulacionId) {
            console.error('Error: El ID de la formulación no coincide', { esperado: formulacionId, recibido: f.Id });
            mostrarErrorComponentes('Error: El ID de la formulación no coincide');
            return;
        }
        llenarConFormulacion(f, r.componentes ?? []);
        if (!ver) {
            if (f.Folio) porId<HTMLFormElement>('createForm').action = rutaCon(estado.cfg.rutas.formulacion, { folio: f.Folio });
            ponerValor('create_formulacion_id', formulacionId);
            estado.snapshotInicial = snapshot();
            actualizarBotonGuardar();
        }
    } catch (err) {
        mostrar('create_componentes_loading', false);
        mostrarErrorComponentes(mensajeError(err, 'Error al cargar la formulación'));
    }
}

// ---------------------------------------------------------------- eliminar

export async function confirmarEliminar(): Promise<void> {
    if (!estado.folioSeleccionado) {
        void notify.alert('', 'Ningún registro seleccionado', 'warning');
        return;
    }
    if (formulacionTieneAX1()) {
        void notify.alert('No se puede eliminar una formulación con AX = 1.', 'Eliminación bloqueada', 'info');
        return;
    }
    const ok = await notify.confirm({
        title: '¿Estás seguro?',
        text: `Se eliminará la formulación con folio ${estado.folioSeleccionado} y todas sus líneas asociadas`,
        confirmText: 'Sí, eliminar',
        confirmColor: '#dc2626',
    });
    if (!ok) return;
    const form = porId<HTMLFormElement>('deleteForm');
    form.action = rutaCon(estado.cfg.rutas.formulacion, { folio: estado.folioSeleccionado });
    ponerValor('delete_formulacion_id', estado.idSeleccionado || '');
    form.submit();
}

// ---------------------------------------------------------------- captura y envío

function recalcularComponentes(): void {
    if (estado.componentes.length === 0) return;
    for (const comp of estado.componentes) {
        comp.ConsumoTotal = aplicarMaxConsumoTotal(comp, num(comp.ConsumoUnitario) * estado.litros, estado.litros);
    }
    renderizarComponentes();
    actualizarBotonGuardar();
}

function alEnviar(e: SubmitEvent): void {
    const cancelar = (mensaje?: string): void => {
        e.preventDefault();
        if (mensaje) notify.warning(mensaje);
    };
    if (!disponibilidadPorStatus(true)) return cancelar();

    const valor = (id: string): number => parseFloat(valorDe(id));
    const litros = valor('create_litros');
    const solidos = valor('create_solidos');
    const error = validarCaptura({
        kilos: valor('create_kilos'),
        litros,
        tiempo: valor('create_tiempo'),
        solidos,
        viscocidad: valor('create_viscocidad'),
    });
    if (error) return cancelar(error);

    // Sólidos a 2 decimales antes de enviar.
    ponerValor('create_solidos', redondear2(solidos).toFixed(2));
    const edicion = esMetodo('PUT');
    if (edicion && !haCambiado()) return cancelar();

    const componentes = leerComponentesDeTabla();
    const excedido = primerConsumoExcedido(componentes, litros);
    if (excedido) return cancelar(mensajeLimiteConsumo(excedido, litros));
    ponerValor('create_componentes_payload', JSON.stringify(componentesParaGuardar(componentes)));

    const boton = porId<HTMLButtonElement>('btn-submit-create');
    boton.disabled = true;
    boton.classList.add('opacity-70', 'cursor-not-allowed', 'pointer-events-none');
    boton.replaceChildren(icono('fa-solid fa-spinner fa-spin mr-1'), el('span', { texto: edicion ? 'Actualizando...' : 'Creando...' }));
}

export function iniciarModalFormula(): void {
    selectFormula().addEventListener('change', cambiarFormula);
    // El select de folio está bloqueado (siempre viene de Producción), pero se respeta su cambio.
    selectFolio().addEventListener('change', () => void cargarDatosPrograma(false));

    porId<HTMLInputElement>('create_kilos').addEventListener(
        'input',
        debounce(() => {
            const valor = valorDe('create_kilos');
            if (valor === '') return;
            estado.kilos = num(valor);
            recalcularComponentes();
        }, 300),
    );

    const litros = porId<HTMLInputElement>('create_litros');
    const recalcularLitros = debounce(recalcularComponentes, 300);
    litros.addEventListener('input', () => {
        if (litros.value === '') return;
        let valor = num(litros.value);
        if (valor > LITROS_MAX) {
            valor = LITROS_MAX;
            litros.value = String(valor);
        }
        estado.litros = valor;
        recalcularLitros();
    });
    litros.addEventListener('blur', () => {
        if (num(litros.value) > LITROS_MAX) {
            litros.value = String(LITROS_MAX);
            estado.litros = LITROS_MAX;
            recalcularComponentes();
        }
    });

    const solidos = porId<HTMLInputElement>('create_solidos');
    solidos.addEventListener('blur', () => {
        const valor = parseFloat(solidos.value);
        if (!Number.isNaN(valor)) solidos.value = redondear2(valor).toFixed(2);
    });

    const form = porId<HTMLFormElement>('createForm');
    form.addEventListener('input', debounce(actualizarBotonGuardar, 200));
    form.addEventListener('change', actualizarBotonGuardar);
    form.addEventListener('submit', alEnviar);
}
