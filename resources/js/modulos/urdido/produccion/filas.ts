/**
 * Captura por fila: Kg. Bruto / Tara / Neto, No. Julio, fecha, horas, roturas (editor de
 * cantidad), Vueltas/Diámetro (Karl Mayer) y la casilla "Fin" (marcar listo).
 */
import { notify } from '../../../utils/notifications.ts';
import { mensajeError, type RespuestaApi } from '../comun/pagina.ts';
import {
    alerta,
    avisoFilaParcial,
    avisoOficialRequerido,
    campo,
    ctx,
    enviar,
    filaDe,
    marcarCampoError,
    requireCanEdit,
    verificarFilaNoFinalizada,
    verificarOficialSeleccionado,
} from './contexto.ts';
import { actualizarTodosLosSelectsJulios, taraSeleccionada } from './julios.ts';
import {
    CAMPOS_EDITABLES_EN_PARCIAL,
    CAMPO_ROTURA,
    brutoExcede,
    brutoMaximo,
    camposFaltantes,
    fechaCorta,
    horaActual,
    netoDe,
    netoFueraDeRango,
    peso,
    pesoNum,
    type CamposFila,
} from './logica.ts';

type RespuestaNeto = RespuestaApi & { data?: { kg_neto?: number | string | null } };

const max = (): number | null => ctx.cfg.maxKgNeto;

// ─── Kg. Neto ───────────────────────────────────────────────────────

/** Recalcula el neto de la fila y marca bruto/neto fuera de rango. */
export function calcularNeto(fila: ParentNode): void {
    const bruto = campo(fila, 'kg_bruto');
    const tara = campo(fila, 'tara');
    const neto = campo(fila, 'kg_neto');
    if (!bruto || !tara || !neto) return;

    const taraNum = pesoNum(tara.value);
    const tope = max();
    if (tope !== null) {
        const maxBruto = brutoMaximo(taraNum, tope);
        bruto.max = String(maxBruto);
        bruto.title = `Kg. Bruto máximo: ${maxBruto.toFixed(2)} (Kg. Neto ≤ ${tope})`;
        marcarCampoError(bruto, brutoExcede(bruto.value, taraNum, tope));
    } else {
        bruto.removeAttribute('max');
        bruto.removeAttribute('title');
    }

    const n = netoDe(bruto.value, taraNum);
    neto.value = n.toFixed(2);
    marcarCampoError(neto, netoFueraDeRango(n, tope));
}

/** Pinta el neto que devolvió el servidor (null → vacío). */
function pintarNetoServidor(registroId: string, kgNeto: number | string | null | undefined): void {
    const neto = filaDe(registroId)?.querySelector<HTMLInputElement>('input[data-field="kg_neto"]');
    if (!neto || kgNeto === undefined) return;
    if (kgNeto !== null) {
        const n = parseFloat(String(kgNeto));
        neto.value = n.toFixed(2);
        marcarCampoError(neto, netoFueraDeRango(n, max()));
    } else {
        neto.value = '';
        marcarCampoError(neto, false);
    }
}

function avisoLimite(): void {
    alerta('warning', 'Límite', `Kg. Neto no puede ser mayor a ${max()} kg.`);
}

// ─── Kg. Bruto (debounce 1 s + guardado al salir) ───────────────────

const pendientesBruto = new Map<string, ReturnType<typeof setTimeout>>();

export function hayCapturaPendiente(): boolean {
    return pendientesBruto.size > 0;
}

function cancelarPendiente(registroId: string): void {
    const t = pendientesBruto.get(registroId);
    if (t !== undefined) clearTimeout(t);
    pendientesBruto.delete(registroId);
}

async function actualizarKgBruto(registroId: string, kgBruto: string): Promise<void> {
    if (!verificarFilaNoFinalizada(registroId)) return;
    if (!verificarOficialSeleccionado(registroId)) {
        avisoOficialRequerido();
        return;
    }

    const fila = filaDe(registroId);
    if (fila) {
        calcularNeto(fila);
        const neto = campo(fila, 'kg_neto');
        const n = neto ? parseFloat(neto.value) : NaN;
        const tope = max();
        if (!Number.isNaN(n) && tope !== null && n > tope) {
            avisoLimite();
            marcarCampoError(neto, true);
            return;
        }
    }

    try {
        const r = await enviar<RespuestaNeto>(
            ctx.cfg.rutas.actualizarKgBruto,
            { registro_id: registroId, kg_bruto: kgBruto !== '' ? parseFloat(kgBruto) : null },
            'Error al actualizar Kg. Bruto',
        );
        notify.success('Kg. Bruto actualizado correctamente');
        if (r.data) pintarNetoServidor(registroId, r.data.kg_neto);
    } catch (err) {
        console.error('Error al actualizar KgBruto:', err);
        alerta('error', 'Error', mensajeError(err, 'Error al actualizar Kg. Bruto. Por favor, intenta nuevamente.'));
        const f = filaDe(registroId);
        if (f) calcularNeto(f);
    }
}

export function alTeclearPeso(input: HTMLInputElement): void {
    const fila = input.closest('tr');
    if (!fila) return;
    calcularNeto(fila);
    if (input.dataset.field !== 'kg_bruto') return;

    const registroId = fila.dataset.registroId;
    if (!registroId) return;
    cancelarPendiente(registroId);
    if (!verificarOficialSeleccionado(registroId)) return;

    const valor = input.value;
    pendientesBruto.set(
        registroId,
        setTimeout(() => {
            pendientesBruto.delete(registroId);
            void actualizarKgBruto(registroId, valor);
        }, 1000),
    );
}

/** Guardar de inmediato al salir del campo: el debounce ya no es la única oportunidad. */
export function alSalirDeBruto(input: HTMLInputElement): void {
    const registroId = input.closest('tr')?.dataset.registroId;
    if (!registroId) return;
    cancelarPendiente(registroId);
    if (!verificarOficialSeleccionado(registroId)) return;
    void actualizarKgBruto(registroId, input.value);
}

// ─── No. Julio ──────────────────────────────────────────────────────

async function actualizarJulioTara(registroId: string, noJulio: string, tara: number | null): Promise<void> {
    if (!verificarFilaNoFinalizada(registroId)) return;
    if (!verificarOficialSeleccionado(registroId)) {
        avisoOficialRequerido();
        return;
    }
    try {
        const r = await enviar<RespuestaNeto>(
            ctx.cfg.rutas.actualizarJulioTara,
            { registro_id: registroId, no_julio: noJulio || null, tara },
            'Error al actualizar No. Julio y Tara',
        );
        notify.success('No. Julio y Tara actualizados correctamente');
        if (r.data && r.data.kg_neto !== undefined) pintarNetoServidor(registroId, r.data.kg_neto);
    } catch (err) {
        console.error('Error al actualizar NoJulio y Tara:', err);
        alerta('error', 'Error', mensajeError(err, 'Error al actualizar No. Julio y Tara. Por favor, intenta nuevamente.'));
        const f = filaDe(registroId);
        if (f) calcularNeto(f);
    }
}

function alCambiarJulio(select: HTMLSelectElement): void {
    const fila = select.closest('tr');
    const registroId = fila?.dataset.registroId;
    const tara = fila ? campo(fila, 'tara') : null;
    if (!fila || !registroId || !tara) return;

    const valor = select.value;
    const anterior = select.dataset.valorAnterior ?? '';

    if (!verificarOficialSeleccionado(registroId)) {
        select.value = select.dataset.valorAnterior ?? select.dataset.valorInicial ?? '';
        avisoOficialRequerido();
        return;
    }

    actualizarTodosLosSelectsJulios();
    if (valor) {
        select.value = valor;
        const taraJulio = taraSeleccionada(select);
        tara.value = taraJulio !== null ? peso(taraJulio) : '';

        const bruto = campo(fila, 'kg_bruto');
        const neto = campo(fila, 'kg_neto');
        let kgNeto: number | null = null;
        if (bruto && neto) {
            kgNeto = netoDe(bruto.value, taraJulio ?? 0);
            neto.value = kgNeto.toFixed(2);
            marcarCampoError(neto, netoFueraDeRango(kgNeto, max()));
        }

        const tope = max();
        if (tope !== null && kgNeto !== null && kgNeto > tope) {
            avisoLimite();
            select.value = anterior;
            const taraPrevia = taraSeleccionada(select);
            tara.value = taraPrevia !== null ? peso(taraPrevia) : '';
            calcularNeto(fila);
            actualizarTodosLosSelectsJulios();
            return;
        }
        void actualizarJulioTara(registroId, valor, taraJulio);
    }
    select.dataset.valorAnterior = valor;
}

// ─── Fecha ──────────────────────────────────────────────────────────

async function actualizarFecha(registroId: string, fecha: string): Promise<void> {
    if (!verificarFilaNoFinalizada(registroId)) return;
    if (!verificarOficialSeleccionado(registroId)) {
        avisoOficialRequerido();
        return;
    }
    try {
        await enviar(ctx.cfg.rutas.actualizarFecha, { registro_id: registroId, fecha }, 'Error al actualizar la fecha');
        notify.success('La fecha ha sido actualizada correctamente');
    } catch (err) {
        console.error('Error al actualizar fecha:', err);
        alerta('error', 'Error', mensajeError(err, 'Error al actualizar la fecha. Por favor, intenta nuevamente.'));
    }
}

function alCambiarFecha(input: HTMLInputElement): void {
    const registroId = input.dataset.registroId;
    const valor = input.value;
    if (valor) {
        const texto = input.closest('tr')?.querySelector('.fecha-display-text');
        const corta = fechaCorta(valor);
        if (texto && corta) texto.textContent = corta;
    }
    if (registroId && valor && valor !== input.dataset.fechaInicial) {
        void actualizarFecha(registroId, valor);
        input.dataset.fechaInicial = valor;
    }
}

/** El input date está oculto: se pone encima del botón un momento y se abre el selector. */
export function abrirSelectorFecha(boton: HTMLElement, ev: Event): void {
    ev.preventDefault();
    ev.stopPropagation();
    const fila = boton.closest('tr');
    const registroId = boton.dataset.registroId ?? '';
    const input = fila?.querySelector<HTMLInputElement>(`input.input-fecha[data-registro-id="${CSS.escape(registroId)}"]`);
    if (!input) return;

    const estilo = input.style;
    const original = {
        position: estilo.position, opacity: estilo.opacity, width: estilo.width, height: estilo.height,
        zIndex: estilo.zIndex, pointerEvents: estilo.pointerEvents, cursor: estilo.cursor, top: estilo.top, left: estilo.left,
    };
    const r = boton.getBoundingClientRect();
    Object.assign(estilo, {
        position: 'fixed', opacity: '0', width: `${r.width}px`, height: `${r.height}px`, top: `${r.top}px`,
        left: `${r.left}px`, zIndex: '9999', pointerEvents: 'auto', cursor: 'pointer',
    });

    setTimeout(() => {
        input.focus();
        try {
            input.showPicker();
        } catch {
            input.click();
        }
    }, 10);

    setTimeout(() => {
        Object.assign(estilo, {
            position: original.position || 'absolute', opacity: original.opacity || '0', width: original.width || '0',
            height: original.height || '0', zIndex: original.zIndex || '-1', pointerEvents: original.pointerEvents || 'none',
            cursor: original.cursor || '', top: original.top || '', left: original.left || '',
        });
    }, 500);
}

// ─── Horas ──────────────────────────────────────────────────────────

async function actualizarHora(registroId: string, columna: string, valor: string | null): Promise<void> {
    if (!verificarFilaNoFinalizada(registroId)) return;
    try {
        const r = await enviar(ctx.cfg.rutas.actualizarHoras, { registro_id: registroId, campo: columna, valor }, 'Error al actualizar la hora');
        notify.success(r.message || 'La hora ha sido actualizada correctamente');
    } catch (err) {
        console.error('Error al actualizar hora:', err);
        alerta('error', 'Error', mensajeError(err, 'Error al actualizar la hora. Por favor, intenta nuevamente.'));
    }
}

function alCambiarHora(input: HTMLInputElement, cual: 'h_inicio' | 'h_fin'): void {
    const registroId = input.closest('tr')?.dataset.registroId;
    if (!registroId) return;
    if (!verificarOficialSeleccionado(registroId)) {
        avisoOficialRequerido();
        input.value = input.dataset.valorAnterior ?? '';
        return;
    }
    void actualizarHora(registroId, cual === 'h_inicio' ? 'HoraInicial' : 'HoraFinal', input.value || null);
    input.dataset.valorAnterior = input.value || '';
}

export function ponerHoraActual(icono: HTMLElement, ev: Event): void {
    ev.preventDefault();
    const fila = icono.closest('tr');
    const input = fila?.querySelector<HTMLInputElement>(`input[data-field="${icono.dataset.timeTarget ?? ''}"]`);
    const registroId = fila?.dataset.registroId;
    if (!input || !registroId) return;
    if (!verificarOficialSeleccionado(registroId)) {
        avisoOficialRequerido();
        return;
    }
    const hora = horaActual();
    input.value = hora;
    input.dataset.valorAnterior = hora;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    icono.classList.add('text-blue-500');
    setTimeout(() => icono.classList.remove('text-blue-500'), 300);
}

// ─── Roturas y Karl Mayer ───────────────────────────────────────────

async function actualizarCampoProduccion(registroId: string, columna: string, valor: string): Promise<void> {
    if (!requireCanEdit()) return;
    if (!CAMPOS_EDITABLES_EN_PARCIAL.includes(columna) && !verificarFilaNoFinalizada(registroId)) return;
    if (!verificarOficialSeleccionado(registroId)) {
        avisoOficialRequerido();
        return;
    }
    try {
        const r = await enviar(
            ctx.cfg.rutas.actualizarCampo,
            // parseFloat, no parseInt: Vueltas y Diámetro son decimales (step 0.01).
            { registro_id: registroId, campo: columna, valor: valor !== '' ? parseFloat(valor) : null },
            'Error al actualizar campo',
        );
        notify.success(r.message || 'Campo actualizado correctamente');
    } catch (err) {
        console.error('Error al actualizar campo de producción:', err);
        alerta('error', 'Error', mensajeError(err, 'Error al actualizar campo. Por favor, intenta nuevamente.'));
    }
}

const pendientesKarlMayer = new Map<string, ReturnType<typeof setTimeout>>();

function alCambiarKarlMayer(input: HTMLInputElement): void {
    const registroId = input.closest('tr')?.dataset.registroId;
    const columna = input.dataset.campo;
    if (!registroId || !columna) return;
    const clave = `${registroId}-${columna}`;
    const previo = pendientesKarlMayer.get(clave);
    if (previo !== undefined) clearTimeout(previo);
    const valor = input.value;
    pendientesKarlMayer.set(
        clave,
        setTimeout(() => {
            pendientesKarlMayer.delete(clave);
            void actualizarCampoProduccion(registroId, columna, valor);
        }, 500),
    );
}

function pintarOpciones(contenedor: ParentNode, valor: string): void {
    contenedor.querySelectorAll<HTMLElement>('.number-option').forEach((o) => {
        const activa = o.dataset.value === valor;
        o.classList.toggle('bg-blue-500', activa);
        o.classList.toggle('text-white', activa);
        o.classList.toggle('bg-gray-100', !activa);
        o.classList.toggle('text-gray-700', !activa);
    });
}

export function cerrarEditoresCantidad(): void {
    document.querySelectorAll<HTMLElement>('.quantity-edit-container:not(.hidden)').forEach((editor) => {
        const celda = editor.closest('td');
        const display = celda?.querySelector('.quantity-display');
        if (display && display.textContent?.trim() === '') display.textContent = '0';
        editor.classList.add('hidden');
        const boton = celda?.querySelector<HTMLElement>('.edit-quantity-btn');
        if (boton) {
            boton.classList.remove('hidden');
            boton.style.display = '';
        }
    });
}

/** Antes window.toggleQuantityEdit(this, campo) desde onclick; ahora data-accion="editar-cantidad". */
export function toggleQuantityEdit(boton: HTMLElement): void {
    const celda = boton.closest('td');
    const editor = celda?.querySelector<HTMLElement>('.quantity-edit-container');
    const display = celda?.querySelector('.quantity-display');
    const editBtn = celda?.querySelector<HTMLElement>('.edit-quantity-btn');
    cerrarEditoresCantidad();
    if (!editor || !display || !editBtn) return;

    if (!display.textContent?.trim()) display.textContent = '0';
    const abrir = editor.classList.contains('hidden');
    editor.classList.toggle('hidden', !abrir);
    editBtn.classList.toggle('hidden', abrir);
    if (!abrir) editBtn.style.display = '';
    else pintarOpciones(editor, display.textContent?.trim() || '0');
}

export function elegirCantidad(opcion: HTMLElement, ev: Event): void {
    ev.preventDefault();
    ev.stopPropagation();
    const celda = opcion.closest('td');
    if (!celda) return;
    const valor = opcion.dataset.value ?? '';
    const contenedor = opcion.closest('.number-scroll-container');
    if (contenedor) pintarOpciones(contenedor, valor);

    const display = celda.querySelector<HTMLElement>('.quantity-display');
    if (display) {
        display.textContent = valor || '0';
        const columna = CAMPO_ROTURA[display.dataset.field ?? ''];
        const registroId = celda.closest('tr')?.dataset.registroId;
        if (registroId && columna) void actualizarCampoProduccion(registroId, columna, valor);
    }

    celda.querySelector('.quantity-edit-container')?.classList.add('hidden');
    const boton = celda.querySelector<HTMLElement>('.edit-quantity-btn');
    if (boton) {
        boton.classList.remove('hidden');
        boton.style.display = '';
    }
}

// ─── Casilla Fin (marcar listo) ─────────────────────────────────────

const SELECTOR_BOTONES_FILA = '.edit-quantity-btn, .btn-agregar-oficial, .btn-fecha-display, .set-current-time';

export function bloquearFila(fila: HTMLElement): void {
    fila.classList.add('bg-green-50', 'opacity-75');
    fila.querySelectorAll<HTMLInputElement>('input:not(.checkbox-finalizar), select, button').forEach((c) => {
        const f = c.dataset.field;
        if (f === 'vueltas' || f === 'diametro') return;
        c.disabled = true;
        c.classList.add('cursor-not-allowed', 'pointer-events-none');
    });
    fila.querySelectorAll<HTMLButtonElement>(SELECTOR_BOTONES_FILA).forEach((c) => {
        c.disabled = true;
        c.classList.add('cursor-not-allowed', 'pointer-events-none', 'opacity-50');
    });
}

function desbloquearFila(fila: HTMLElement): void {
    fila.classList.remove('bg-green-50', 'opacity-75');
    fila.querySelectorAll<HTMLInputElement>('input:not(.checkbox-finalizar), select, button').forEach((c) => {
        // Tara, hilos, metros y neto son de solo lectura desde el Blade.
        const f = c.dataset.field;
        if (!(f === 'tara' || f === 'hilos' || f === 'metros' || f === 'kg_neto')) c.disabled = false;
        c.classList.remove('cursor-not-allowed', 'pointer-events-none');
    });
    fila.querySelectorAll<HTMLButtonElement>(SELECTOR_BOTONES_FILA).forEach((c) => {
        c.disabled = false;
        c.classList.remove('cursor-not-allowed', 'pointer-events-none', 'opacity-50');
    });
}

/** Lee los campos de una fila para validarla. */
export function camposDeFila(fila: ParentNode): CamposFila {
    const v = (selector: string): string => fila.querySelector<HTMLInputElement>(selector)?.value ?? '';
    return {
        fecha: v('input.input-fecha'),
        oficial: fila.querySelector('.oficial-texto')?.textContent ?? '',
        hInicio: v('input[data-field="h_inicio"]'),
        hFin: v('input[data-field="h_fin"]'),
        noJulio: v('select[data-field="no_julio"]'),
        kgBruto: v('input[data-field="kg_bruto"]'),
        tara: v('input[data-field="tara"]'),
        kgNeto: v('input[data-field="kg_neto"]'),
        metros: v('input[data-field="metros"]'),
        vueltas: v('input[data-field="vueltas"]'),
        diametro: v('input[data-field="diametro"]'),
    };
}

async function marcarRegistroListo(registroId: string, listo: boolean, casilla: HTMLInputElement): Promise<void> {
    const fila = casilla.closest('tr');
    try {
        await enviar(ctx.cfg.rutas.marcarListo, { registro_id: registroId, listo }, 'Error al actualizar el registro');
        if (fila) (listo ? bloquearFila : desbloquearFila)(fila);
        notify.success(listo ? 'Registro parcialmente finalizado' : 'Registro desbloqueado para edición');
    } catch (err) {
        console.error('Error al marcar como listo:', err);
        casilla.checked = !listo;
        alerta('error', 'Error', mensajeError(err, 'Error al actualizar el registro. Por favor, intenta nuevamente.'));
    }
}

function alCambiarFin(casilla: HTMLInputElement): void {
    const registroId = casilla.dataset.registroId;
    const listo = casilla.checked;
    if (!registroId) return;

    if (parseInt(casilla.dataset.ax || '0', 10) === 1) {
        casilla.checked = !listo;
        alerta('warning', 'No modificable', 'Este registro ya fue enviado a AX y no se puede modificar.');
        return;
    }
    if (listo) {
        const fila = casilla.closest('tr');
        const faltan = fila ? camposFaltantes(camposDeFila(fila), ctx.cfg.esKarlMayer, max()) : [];
        if (faltan.length) {
            casilla.checked = false;
            alerta('warning', 'Registro incompleto', `Completa los campos requeridos antes de finalizar: ${faltan.join(', ')}`);
            return;
        }
    }
    void marcarRegistroListo(registroId, listo, casilla);
}

/** Clic en una fila marcada como lista: se avisa y no se deja editar (salvo Vueltas/Diámetro). */
export function interceptarFilaBloqueada(ev: MouseEvent): void {
    const origen = ev.target as Element | null;
    const fila = origen?.closest<HTMLElement>('tr[data-registro-id]');
    if (!origen || !fila || origen.closest('.checkbox-finalizar')) return;
    if (fila.querySelector<HTMLInputElement>('.checkbox-finalizar')?.checked !== true) return;
    const td = origen.closest('td');
    if (td?.querySelector('input[data-field="vueltas"], input[data-field="diametro"]')) return;
    ev.preventDefault();
    ev.stopPropagation();
    avisoFilaParcial();
}

// ─── Cambio en la tabla (un solo listener) ──────────────────────────

export function alCambiarEnTabla(ev: Event): void {
    const objetivo = ev.target;
    if (!(objetivo instanceof HTMLInputElement || objetivo instanceof HTMLSelectElement)) return;

    if (objetivo instanceof HTMLInputElement && objetivo.classList.contains('checkbox-finalizar')) {
        if (!requireCanEdit()) return;
        alCambiarFin(objetivo);
        return;
    }
    if (!requireCanEdit()) return;

    if (objetivo instanceof HTMLSelectElement) {
        if (objetivo.classList.contains('select-julio')) alCambiarJulio(objetivo);
        return;
    }
    if (objetivo.classList.contains('karl-mayer-input')) {
        alCambiarKarlMayer(objetivo);
        return;
    }
    const f = objetivo.dataset.field;
    if (f === 'fecha' && objetivo.classList.contains('input-fecha')) alCambiarFecha(objetivo);
    else if (f === 'h_inicio' || f === 'h_fin') alCambiarHora(objetivo, f);
}

/** Estado inicial: neto calculado, hora previa guardada y filas listas bloqueadas. */
export function iniciarFilas(tbody: HTMLElement): void {
    tbody.querySelectorAll<HTMLTableRowElement>('tr').forEach((fila) => {
        calcularNeto(fila);
        for (const f of ['h_inicio', 'h_fin']) {
            const h = campo(fila, f);
            if (h) h.dataset.valorAnterior = h.value || '';
        }
    });
    tbody.querySelectorAll<HTMLInputElement>('.checkbox-finalizar:checked').forEach((c) => {
        const fila = c.closest('tr');
        if (fila) bloquearFila(fila);
    });
}
