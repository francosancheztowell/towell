/**
 * Producción Engomado — filas de la tabla: catálogo de julios, bloqueo por "Listo"
 * (Finalizar) y eventos (captura, fecha, horas, temperaturas, merma).
 */
import { http } from '../../../utils/http.ts';
import { notify } from '../../../utils/notifications.ts';
import { delegate } from '../../../utils/dom.ts';
import { el, exigirExito, mensajeError, type RespuestaApi } from '../../urdido/comun/pagina.ts';
import {
    MENSAJE_FILA_FINALIZADA,
    avisarOficialRequerido,
    calcularNetoFila,
    campo,
    cfg,
    cuerpoTabla,
    requireCanEdit,
    tieneOficial,
} from './contexto.ts';
import {
    actualizarCampoOrden,
    actualizarCampoProduccion,
    actualizarFecha,
    actualizarHora,
    actualizarJulioTara,
    actualizarKgBruto,
    actualizarTurnoOficial,
    aviso,
    decimales,
} from './guardado.ts';
import { cerrarSelectoresCantidad, elegirNumero } from './temperaturas.ts';
import { CAMPO_PRODUCCION, calcularNeto, fechaCorta, horaActual, parsearOficiales, redondear } from './logica.ts';
import { validarFila } from './validacion.ts';

interface Julio {
    julio: string | number;
    tara: string | number | null;
}

// ─── Catálogo de julios ─────────────────────────────────────────────────

let catalogoJulios: Julio[] | null = null;

/** Llena los selects de julio sin repetir los ya usados en otras filas. */
export async function cargarCatalogosJulios(): Promise<void> {
    try {
        if (!catalogoJulios) {
            const r = exigirExito(await http.get<RespuestaApi & { data?: Julio[] }>(cfg().rutas.catalogosJulios), 'Error al cargar julios');
            catalogoJulios = r.data ?? [];
        }
    } catch (err) {
        console.error('Error al cargar catálogo de julios:', mensajeError(err, 'Error desconocido'));
        return;
    }

    const selects = Array.from(document.querySelectorAll<HTMLSelectElement>('.select-julio'));
    const usados = new Set(selects.map((s) => (s.dataset.valorInicial ?? '').trim()).filter((v) => v !== ''));

    for (const select of selects) {
        const inicial = (select.dataset.valorInicial ?? '').trim();
        const opciones = catalogoJulios
            .filter((j) => !usados.has(String(j.julio).trim()) || String(j.julio).trim() === inicial)
            .map((j) => {
                const op = el('option', { texto: String(j.julio), attrs: { value: String(j.julio), 'data-tara': String(j.tara || '0') } });
                op.selected = inicial !== '' && String(j.julio).trim() === inicial;
                return op;
            });
        select.replaceChildren(select.options[0] ?? el('option', { texto: 'Seleccionar...', attrs: { value: '' } }), ...opciones);
        select.dataset.valorAnterior = inicial;

        if (inicial && select.value) {
            const fila = select.closest('tr');
            const tara = fila ? campo(fila, 'tara') : null;
            const opcion = select.options[select.selectedIndex];
            if (fila && tara && opcion) {
                tara.value = decimales(opcion.dataset.tara || '0', 1);
                const neto = campo(fila, 'kg_neto');
                const bruto = campo(fila, 'kg_bruto');
                if (neto && bruto) neto.value = calcularNeto(bruto.value, tara.value).toFixed(2);
            }
        }
    }
}

// ─── Bloqueo por "Listo" ────────────────────────────────────────────────

const CONTROLES_EXTRA = '.edit-quantity-btn, .btn-agregar-oficial, .btn-fecha-display, .set-current-time';

export function bloquearFila(fila: HTMLElement): void {
    fila.classList.add('bg-green-50', 'opacity-75');
    fila.querySelectorAll<HTMLInputElement>('input:not(.checkbox-finalizar), select, button').forEach((c) => {
        c.disabled = true;
        c.classList.add('cursor-not-allowed', 'pointer-events-none');
    });
    fila.querySelectorAll<HTMLElement>(CONTROLES_EXTRA).forEach((c) => {
        if ('disabled' in c) (c as HTMLButtonElement).disabled = true;
        c.classList.add('cursor-not-allowed', 'pointer-events-none', 'opacity-50');
    });
}

function desbloquearFila(fila: HTMLElement): void {
    fila.classList.remove('bg-green-50', 'opacity-75');
    fila.querySelectorAll<HTMLInputElement>('input:not(.checkbox-finalizar), select, button').forEach((c) => {
        const soloLectura = ['tara', 'metros', 'kg_neto'].includes(c.dataset.field ?? '');
        if (!soloLectura) c.disabled = false;
        c.classList.remove('cursor-not-allowed', 'pointer-events-none');
    });
    fila.querySelectorAll<HTMLElement>(CONTROLES_EXTRA).forEach((c) => {
        if ('disabled' in c) (c as HTMLButtonElement).disabled = false;
        c.classList.remove('cursor-not-allowed', 'pointer-events-none', 'opacity-50');
    });
}

function actualizarBotonImprimirParcial(): void {
    const btn = document.getElementById('btn-imprimir-parcial') as HTMLButtonElement | null;
    if (!btn) return;
    const hay = (cuerpoTabla()?.querySelectorAll('.checkbox-finalizar:checked').length ?? 0) > 0;
    btn.disabled = !hay;
    btn.title = hay ? 'Imprimir producción parcial' : 'Marca al menos un registro como "Listo" (Finalizar) para imprimir';
}

async function marcarRegistroListo(registroId: string, listo: boolean, check: HTMLInputElement): Promise<void> {
    const fila = check.closest('tr');
    try {
        exigirExito(await http.post<RespuestaApi>(cfg().rutas.marcarListo, { registro_id: registroId, listo }), 'Error al actualizar el registro');
        if (fila) (listo ? bloquearFila : desbloquearFila)(fila);
        actualizarBotonImprimirParcial();
        notify.success(listo ? 'Registro parcialmente finalizado' : 'Registro desbloqueado para edición');
    } catch (err) {
        check.checked = !listo;
        aviso(err, 'Error al actualizar el registro. Por favor, intenta nuevamente.');
    }
}

function alCambiarListo(check: HTMLInputElement): void {
    if (!requireCanEdit()) return;
    const registroId = check.dataset.registroId;
    const listo = check.checked;
    if (!registroId) return;

    if (check.dataset.ax === '1') {
        check.checked = !listo;
        void notify.alert('Este registro ya fue enviado a AX y no se puede modificar.', 'No modificable', 'warning');
        return;
    }
    if (listo) {
        const fila = check.closest('tr');
        const faltan = fila ? validarFila(fila) : [];
        if (faltan.length) {
            check.checked = false;
            void notify.alert(`Completa los campos requeridos antes de finalizar: ${faltan.join(', ')}`, 'Registro incompleto', 'warning');
            return;
        }
    }
    void marcarRegistroListo(registroId, listo, check);
}

// ─── Eventos de la tabla ─────────────────────────────────────────────────

const pendientesBruto = new Map<string, number>();
const ultimoBrutoEnviado = new Map<string, string>();

function enviarBruto(registroId: string, valor: string): void {
    // Evita el doble envío del mismo valor (debounce + blur).
    if (ultimoBrutoEnviado.get(registroId) === valor) return;
    ultimoBrutoEnviado.set(registroId, valor);
    void actualizarKgBruto(registroId, valor);
}

function cancelarPendienteBruto(registroId: string): void {
    const t = pendientesBruto.get(registroId);
    if (t !== undefined) window.clearTimeout(t);
    pendientesBruto.delete(registroId);
}

function alEscribir(e: Event): void {
    const input = e.target as HTMLInputElement;
    quitarErrorVisual(input);
    const fila = input.closest('tr');
    const nombre = input.dataset.field;
    if (!fila || (nombre !== 'kg_bruto' && nombre !== 'tara')) return;

    const recortado = calcularNetoFila(fila);
    const max = cfg().maxKgBruto;
    if (nombre === 'kg_bruto' && recortado && input.value !== '' && max !== null) {
        notify.warning(`Kg. Bruto máx. ${max.toFixed(0)}`);
    }
    if (nombre !== 'kg_bruto') return;

    const registroId = fila.dataset.registroId;
    if (!registroId) return;
    cancelarPendienteBruto(registroId);
    if (!tieneOficial(registroId)) return;

    const valor = input.value;
    pendientesBruto.set(
        registroId,
        window.setTimeout(() => {
            pendientesBruto.delete(registroId);
            const formateado = redondear(valor, 2) || valor;
            if (formateado !== valor) input.value = formateado;
            enviarBruto(registroId, formateado);
        }, 1000),
    );
}

function alSalir(e: FocusEvent): void {
    const input = e.target as HTMLInputElement;
    const nombre = input.dataset.field;
    const registroId = input.closest('tr')?.dataset.registroId;
    if (!registroId || (nombre !== 'kg_bruto' && nombre !== 'solidos')) return;

    if (nombre === 'kg_bruto') {
        cancelarPendienteBruto(registroId);
        if (!tieneOficial(registroId)) return;
        const valor = redondear(input.value, 2);
        if (valor === '') return;
        input.value = valor;
        enviarBruto(registroId, valor);
        return;
    }

    // Sólidos: el change ya lo guardó (y fijó data-valor-inicial); aquí solo si quedó distinto.
    if (!tieneOficial(registroId)) {
        input.value = input.dataset.valorInicial ?? '';
        return;
    }
    const valor = redondear(input.value, 2);
    if (valor === '' || valor === (input.dataset.valorInicial ?? '')) return;
    input.value = valor;
    input.dataset.valorInicial = valor;
    void actualizarCampoProduccion(registroId, 'Solidos', valor);
}

function quitarErrorVisual(elemento: Element): void {
    if (elemento.classList.contains('border-red-500')) {
        elemento.classList.remove('border-red-500', 'border-2');
        elemento.classList.add('border-gray-300');
    }
}

function alCambiarJulio(select: HTMLSelectElement, fila: HTMLElement, registroId: string): void {
    const tara = campo(fila, 'tara');
    const opcion = select.options[select.selectedIndex];
    if (!tara || !opcion) return;

    if (!tieneOficial(registroId)) {
        select.dataset.valorAnterior ??= select.dataset.valorInicial ?? '';
        select.value = select.dataset.valorAnterior;
        avisarOficialRequerido();
        return;
    }

    const noJulio = opcion.value;
    select.dataset.valorAnterior = noJulio;
    const taraStr = opcion.dataset.tara;
    const taraNum = taraStr !== undefined && taraStr !== '' ? parseFloat(taraStr) : null;
    tara.value = taraNum !== null ? taraNum.toFixed(1) : '';

    const bruto = campo(fila, 'kg_bruto');
    const neto = campo(fila, 'kg_neto');
    let kgNeto: number | null = null;
    if (bruto && neto) {
        kgNeto = calcularNeto(bruto.value, taraNum ?? 0);
        neto.value = kgNeto.toFixed(2);
    }

    void actualizarJulioTara(registroId, noJulio, taraNum, kgNeto).then(() => {
        select.dataset.valorInicial = noJulio;
        void cargarCatalogosJulios();
    });
}

function alCambiar(e: Event): void {
    const control = e.target as HTMLInputElement | HTMLSelectElement;
    quitarErrorVisual(control);

    if (control.classList.contains('checkbox-finalizar')) {
        alCambiarListo(control as HTMLInputElement);
        return;
    }
    if (!requireCanEdit()) return;

    const nombre = control.dataset.field ?? '';
    const fila = control.closest('tr');
    const registroId = fila?.dataset.registroId;
    if (!fila || !registroId) return;

    if (control.classList.contains('select-julio')) {
        alCambiarJulio(control as HTMLSelectElement, fila, registroId);
        return;
    }

    if (nombre === 'turno' && control.value) {
        const primero = parsearOficiales(fila.querySelector<HTMLElement>('.oficial-texto')?.dataset.oficialesJson)[0];
        if (primero) void actualizarTurnoOficial(registroId, primero.numero, control.value);
        return;
    }

    if (nombre === 'fecha' && control.classList.contains('input-fecha')) {
        const valor = control.value;
        const corta = valor ? fechaCorta(valor) : null;
        const texto = fila.querySelector('.fecha-display-text');
        if (corta && texto) texto.textContent = corta;
        if (valor && valor !== control.dataset.fechaInicial) {
            void actualizarFecha(registroId, valor);
            control.dataset.fechaInicial = valor;
        }
        return;
    }

    if (nombre === 'h_inicio' || nombre === 'h_fin') {
        if (!tieneOficial(registroId)) {
            avisarOficialRequerido();
            control.value = control.dataset.valorAnterior ?? '';
            return;
        }
        void actualizarHora(registroId, nombre === 'h_inicio' ? 'HoraInicial' : 'HoraFinal', control.value || null);
        control.dataset.valorAnterior = control.value;
        return;
    }

    const columna = CAMPO_PRODUCCION[nombre];
    if (!columna) return;
    if (!tieneOficial(registroId)) {
        if (nombre === 'solidos') control.value = control.dataset.valorInicial ?? '';
        avisarOficialRequerido();
        return;
    }
    let valor = control.value.trim();
    if (nombre === 'solidos') {
        valor = redondear(valor, 2);
        control.value = valor;
        control.dataset.valorInicial = valor;
    }
    void actualizarCampoProduccion(registroId, columna, valor || null);
}

/** Abre el selector nativo de fecha sobre el botón "dd/mm". */
function abrirSelectorFecha(boton: HTMLElement): void {
    const fila = boton.closest('tr');
    const input = fila?.querySelector<HTMLInputElement>(`input.input-fecha[data-registro-id="${CSS.escape(boton.dataset.registroId ?? '')}"]`);
    if (!input) return;

    const original = input.getAttribute('style') ?? '';
    const r = boton.getBoundingClientRect();
    Object.assign(input.style, {
        position: 'fixed',
        opacity: '0',
        width: `${r.width}px`,
        height: `${r.height}px`,
        top: `${r.top}px`,
        left: `${r.left}px`,
        zIndex: '9999',
        pointerEvents: 'auto',
        cursor: 'pointer',
    });
    window.setTimeout(() => {
        try {
            if (typeof input.showPicker === 'function') input.showPicker();
            else input.click();
        } catch {
            input.click();
        }
    }, 10);
    window.setTimeout(() => input.setAttribute('style', original), 500);
}

function ponerHoraActual(icono: HTMLElement): void {
    const fila = icono.closest('tr');
    const input = fila ? campo(fila, icono.dataset.timeTarget ?? '') : null;
    const registroId = fila?.dataset.registroId;
    if (!input || !registroId) return;
    if (!tieneOficial(registroId)) {
        avisarOficialRequerido();
        return;
    }
    const hora = horaActual();
    input.value = hora;
    input.dataset.valorAnterior = hora;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    icono.classList.add('text-blue-500');
    window.setTimeout(() => icono.classList.remove('text-blue-500'), 300);
}

/** Estado inicial de las filas y listeners de la tabla. */
export function iniciarFilas(): void {
    const cuerpo = cuerpoTabla();
    if (cuerpo) {
        cuerpo.querySelectorAll<HTMLInputElement>('.checkbox-finalizar:checked').forEach((c) => {
            const fila = c.closest('tr');
            if (fila) bloquearFila(fila);
        });

        cuerpo.querySelectorAll<HTMLTableRowElement>('tr').forEach((fila) => {
            calcularNetoFila(fila);
            const primero = parsearOficiales(fila.querySelector<HTMLElement>('.oficial-texto')?.dataset.oficialesJson)[0];
            const turno = fila.querySelector<HTMLSelectElement>('select[data-field="turno"]');
            if (primero?.turno && turno) turno.value = String(primero.turno);
            for (const nombre of ['h_inicio', 'h_fin']) {
                const hora = campo(fila, nombre);
                if (hora) hora.dataset.valorAnterior = hora.value;
            }
        });

        // Fila "Listo": cualquier interacción (salvo la casilla) solo avisa.
        cuerpo.addEventListener(
            'mousedown',
            (e) => {
                const destino = e.target as Element;
                const fila = destino.closest<HTMLElement>('tr[data-registro-id]');
                if (!fila || destino.closest('.checkbox-finalizar')) return;
                if (fila.querySelector<HTMLInputElement>('.checkbox-finalizar')?.checked) {
                    e.preventDefault();
                    e.stopPropagation();
                    notify.info(MENSAJE_FILA_FINALIZADA);
                }
            },
            true,
        );
        cuerpo.addEventListener('input', alEscribir);
        cuerpo.addEventListener('change', alCambiar);
        cuerpo.addEventListener('focusout', alSalir);
    }

    // Merma con/sin goma (fuera de la tabla).
    delegate<HTMLInputElement>(document, 'input', 'input[data-field^="merma_"]', (_e, input) => quitarErrorVisual(input));
    delegate<HTMLInputElement>(document, 'change', 'input[data-field="merma_con_goma"], input[data-field="merma_sin_goma"]', (_e, input) => {
        quitarErrorVisual(input);
        void actualizarCampoOrden(input.dataset.field ?? '', input.value !== '' ? parseFloat(input.value) : null);
    });

    // Cerrar selectores de temperatura al tocar fuera.
    document.addEventListener('click', (e) => {
        const t = e.target as Element;
        if (!t.closest?.('.quantity-edit-container, .edit-quantity-btn, .number-option')) cerrarSelectoresCantidad();
    });
    delegate<HTMLElement, MouseEvent>(document, 'click', '.number-option', (e, op) => {
        e.preventDefault();
        e.stopPropagation();
        void elegirNumero(op);
    });
    delegate<HTMLElement, MouseEvent>(document, 'click', '.btn-fecha-display', (e, btn) => {
        e.preventDefault();
        e.stopPropagation();
        abrirSelectorFecha(btn);
    });
    delegate<HTMLElement, MouseEvent>(document, 'click', '.set-current-time', (e, icono) => {
        e.preventDefault();
        ponerHoraActual(icono);
    });

    void cargarCatalogosJulios();
}
