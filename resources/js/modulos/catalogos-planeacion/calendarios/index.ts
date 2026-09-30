/**
 * Catálogo de Calendarios (catalagos/calendarios/index). Antes ~1 650 líneas de <script> inline
 * en la vista y 9 modales Swal + public/js/catalog-core.js.
 *
 * Dos tablas: calendarios (arriba, selección azul; filtra las líneas) y líneas (abajo, verde).
 * Los botones del navbar (catalog-actions) llegan por registrarAccionesCatalogo.
 */
import { abrir, cerrarPorId } from '../../../componentes/dialog.ts';
import { loader } from '../../../componentes/loader.ts';
import { actualizarContadorFiltros, habilitarEdicion, registrarAccionesCatalogo } from '../../../catalogos/catalog-actions.ts';
import { delegate, onReady, qs, qsa } from '../../../utils/dom.ts';
import { leerValores } from '../comun/logica.ts';
import {
    COLUMNAS,
    filtroDuplicado,
    pasaFiltros,
    validarLinea,
    validarRango,
    type FiltroColumna,
    type TablaFiltro,
} from './logica.ts';
import { ModalCalendario, type RutasCalendario } from './modal-calendario.ts';

interface Rutas extends RutasCalendario {
    eliminar: string;
    crearLinea: string;
    linea: string;
    rango: string;
    excel: string;
    recalcular: string;
}

type Respuesta = { success?: boolean; message?: string; eliminadas?: number; data?: { registros_procesados?: number } };

const conId = (url: string, id: string): string => url.replace('__ID__', encodeURIComponent(id));

function mensaje(err: unknown, porDefecto: string): string {
    const m = (err as { data?: { message?: unknown } } | null)?.data?.message;

    return typeof m === 'string' && m !== '' ? m : porDefecto;
}

function recargar(): void {
    window.setTimeout(() => window.location.reload(), 800);
}

onReady(() => {
    const raiz = qs('#pagina-calendarios');
    const tabBodyEl = qs('#calendario-tab-body');
    const lineBodyEl = qs('#calendario-line-body');
    if (!raiz || !tabBodyEl || !lineBodyEl) return;
    const tabBody: HTMLElement = tabBodyEl;
    const lineBody: HTMLElement = lineBodyEl;
    const rutas = (JSON.parse(raiz.dataset.pagina ?? '{}') as { rutas: Rutas }).rutas;
    const modalCalendario = new ModalCalendario(rutas);

    let calendario: HTMLTableRowElement | null = null;
    let linea: HTMLTableRowElement | null = null;
    let filtros: FiltroColumna[] = [];

    const filasTab = (): HTMLTableRowElement[] => qsa<HTMLTableRowElement>('tr[data-fila-tab]', tabBody);
    const filasLinea = (): HTMLTableRowElement[] => qsa<HTMLTableRowElement>('tr[data-fila-linea]', lineBody);
    const textos = (fila: HTMLTableRowElement): string[] => [...fila.cells].map((c) => (c.textContent ?? '').trim());

    // ============ Selección y visibilidad ============

    /** Oculta lo que no pasa los filtros y, si hay calendario elegido, las líneas de otros. */
    function aplicarVisibilidad(): void {
        filasTab().forEach((f) => {
            f.hidden = !pasaFiltros(textos(f), 'tab', filtros);
        });
        let visibles = 0;
        filasLinea().forEach((f) => {
            const delCalendario = !calendario || f.dataset.calendario === calendario.dataset.id;
            f.hidden = !(delCalendario && pasaFiltros(textos(f), 'line', filtros));
            if (!f.hidden) visibles++;
        });
        const vacio = qs('[data-lineas-vacio]', lineBody);
        if (vacio) vacio.hidden = !(calendario && visibles === 0);
        actualizarContadorFiltros(filtros.length);
    }

    function seleccionar(fila: HTMLTableRowElement | null): void {
        const esTab = fila?.hasAttribute('data-fila-tab') ?? false;
        const actual = esTab ? calendario : linea;
        const nueva = fila && fila !== actual ? fila : null; // tocar la seleccionada la deselecciona
        [calendario, linea].forEach((f) => f?.setAttribute('aria-selected', 'false'));
        calendario = esTab ? nueva : null;
        linea = esTab ? null : nueva;
        nueva?.setAttribute('aria-selected', 'true');
        habilitarEdicion(nueva !== null);
        aplicarVisibilidad();
    }

    for (const cuerpo of [tabBody, lineBody]) {
        delegate<HTMLTableRowElement>(cuerpo, 'click', 'tr[data-fila-tab], tr[data-fila-linea]', (_e, fila) => seleccionar(fila));
        delegate<HTMLTableRowElement, KeyboardEvent>(cuerpo, 'keydown', 'tr[data-fila-tab], tr[data-fila-linea]', (e, fila) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                seleccionar(fila);
            }
        });
    }

    // ============ Líneas ============

    const formLinea = qs<HTMLFormElement>('#formLinea')!;
    const campoLinea = (n: string) => formLinea.elements.namedItem(n) as HTMLInputElement | HTMLSelectElement;

    function abrirLinea(existente: HTMLTableRowElement | null): void {
        formLinea.reset();
        const v = existente ? leerValores(existente.dataset.valores) : {};
        campoLinea('__id').value = existente?.dataset.id ?? '';
        campoLinea('CalendarioId').value = String(v.CalendarioId ?? calendario?.dataset.id ?? linea?.dataset.calendario ?? '');
        (campoLinea('CalendarioId') as HTMLInputElement).readOnly = existente !== null;
        campoLinea('FechaInicio').value = String(v.FechaInicio ?? '');
        campoLinea('FechaFin').value = String(v.FechaFin ?? '');
        campoLinea('HorasTurno').value = String(v.HorasTurno ?? '');
        campoLinea('Turno').value = String(v.Turno ?? '1');
        const titulo = document.getElementById('modalLinea-titulo');
        if (titulo) titulo.textContent = existente ? 'Editar Línea de Calendario' : 'Agregar Nueva Línea de Calendario';
        abrir('modalLinea');
    }

    formLinea.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = campoLinea('__id').value;
        const datos = {
            CalendarioId: campoLinea('CalendarioId').value.trim(),
            FechaInicio: campoLinea('FechaInicio').value,
            FechaFin: campoLinea('FechaFin').value,
            HorasTurno: campoLinea('HorasTurno').value,
            Turno: campoLinea('Turno').value,
        };
        const error = validarLinea(id ? { ...datos, CalendarioId: undefined } : datos);
        if (error) {
            window.notify.warning(error);
            return;
        }
        const cuerpo = { ...datos, HorasTurno: Number(datos.HorasTurno) };
        loader.show();
        try {
            const res = (await (id ? window.http.put(conId(rutas.linea, id), cuerpo) : window.http.post(rutas.crearLinea, cuerpo))) as Respuesta;
            if (!res?.success) throw { data: res };
            cerrarPorId('modalLinea');
            window.notify.success(res.message ?? 'Línea guardada');
            recargar();
        } catch (err) {
            window.notify.error(mensaje(err, id ? 'Error al actualizar línea de calendario' : 'Error al crear línea de calendario'));
        } finally {
            loader.hide();
        }
    });

    // ============ Eliminar ============

    async function eliminar(): Promise<void> {
        const fila = calendario ?? linea;
        if (!fila) {
            window.notify.warning('Por favor selecciona una fila para eliminar');
            return;
        }
        const esCalendario = fila === calendario;
        const t = textos(fila);
        const ok = await window.notify.confirm({
            title: esCalendario ? '¿Eliminar calendario?' : '¿Eliminar línea de calendario?',
            text: esCalendario
                ? `Vas a eliminar el calendario ${t[0] ?? ''} - ${t[1] ?? ''}.`
                : `Vas a eliminar la línea del calendario ${t[0] ?? ''} turno ${t[4] ?? ''}.`,
            icon: 'warning',
            confirmText: 'Sí, eliminar',
            confirmColor: '#dc2626',
        });
        if (!ok) return;
        loader.show();
        try {
            const res = (await window.http.delete(conId(esCalendario ? rutas.eliminar : rutas.linea, fila.dataset.id ?? ''))) as Respuesta;
            if (!res?.success) throw { data: res };
            window.notify.success(res.message ?? 'Eliminado');
            recargar();
        } catch (err) {
            window.notify.error(mensaje(err, 'Error al eliminar'));
        } finally {
            loader.hide();
        }
    }

    // ============ Rango ============

    const formRango = qs<HTMLFormElement>('#formRango')!;

    function abrirRango(): void {
        if (!calendario) {
            void window.notify.alert('Por favor selecciona un calendario de la tabla superior para eliminar sus líneas por rango', 'Selección requerida', 'info');
            return;
        }
        formRango.reset();
        const etiqueta = qs('[data-rango-calendario]', formRango);
        if (etiqueta) etiqueta.textContent = calendario.dataset.nombre ?? calendario.dataset.id ?? '';
        abrir('modalRango');
    }

    formRango.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!calendario) return;
        const datos = new FormData(formRango);
        const fechaInicio = String(datos.get('fechaInicio') ?? '');
        const fechaFin = String(datos.get('fechaFin') ?? '');
        const turnos = datos.getAll('turnos').map(Number);
        const error = validarRango(fechaInicio, fechaFin, turnos);
        if (error) {
            window.notify.warning(error);
            return;
        }
        const id = calendario.dataset.id ?? '';
        const ok = await window.notify.confirm({
            title: '¿Confirmar eliminación?',
            text: `Calendario: ${id} · Del ${fechaInicio} al ${fechaFin} · Turnos: ${turnos.join(', ')}`,
            confirmText: 'Sí, eliminar',
            confirmColor: '#dc2626',
        });
        if (!ok) return;
        loader.show();
        try {
            const res = (await window.http.delete(conId(rutas.rango, id), { data: { fechaInicio, fechaFin, turnos } })) as Respuesta;
            if (!res?.success) throw { data: res };
            cerrarPorId('modalRango');
            await window.notify.alert(`Líneas eliminadas: ${res.eliminadas ?? 0}`, '¡Eliminación Exitosa!', 'success');
            window.location.reload();
        } catch (err) {
            window.notify.error(mensaje(err, 'Error al eliminar las líneas'));
        } finally {
            loader.hide();
        }
    });

    // ============ Filtros ============

    const formFiltros = qs<HTMLFormElement>('#formFiltros')!;
    const selTabla = formFiltros.elements.namedItem('tabla') as HTMLSelectElement;
    const selColumna = formFiltros.elements.namedItem('columna') as HTMLSelectElement;

    function columnas(): void {
        selColumna.replaceChildren(...COLUMNAS[selTabla.value as TablaFiltro].map((c) => new Option(c.titulo, c.campo)));
    }

    function pintarFiltros(): void {
        const caja = qs('[data-filtros-activos]');
        const lista = qs('[data-filtros-lista]');
        const plantilla = qs<HTMLTemplateElement>('template[data-filtro-plantilla]');
        if (!caja || !lista || !plantilla) return;
        caja.hidden = filtros.length === 0;
        lista.replaceChildren(
            ...filtros.map((f, i) => {
                const li = plantilla.content.firstElementChild!.cloneNode(true) as HTMLElement;
                qs('[data-filtro-texto]', li)!.textContent = `${f.columna}: ${f.valor}`;
                qs<HTMLButtonElement>('[data-quitar-filtro]', li)!.dataset.indice = String(i);
                return li;
            }),
        );
    }

    selTabla.addEventListener('change', columnas);
    delegate<HTMLButtonElement>(qs('#modalFiltros')!, 'click', '[data-quitar-filtro]', (_e, boton) => {
        filtros.splice(Number(boton.dataset.indice), 1);
        aplicarVisibilidad();
        pintarFiltros();
        window.notify.info('Filtro eliminado');
    });
    formFiltros.addEventListener('submit', (e) => {
        e.preventDefault();
        const nuevo: FiltroColumna = { tabla: selTabla.value as TablaFiltro, columna: selColumna.value, valor: (formFiltros.elements.namedItem('valor') as HTMLInputElement).value.trim() };
        if (!nuevo.valor) {
            window.notify.warning('Por favor ingresa un valor para filtrar');
            return;
        }
        if (filtroDuplicado(filtros, nuevo)) {
            window.notify.warning('Este filtro ya está activo');
            return;
        }
        filtros.push(nuevo);
        aplicarVisibilidad();
        pintarFiltros();
        (formFiltros.elements.namedItem('valor') as HTMLInputElement).value = '';
        window.notify.success('Filtro agregado correctamente');
    });

    // ============ Excel ============

    const formExcel = qs<HTMLFormElement>('#formExcelCalendario')!;
    const archivo = formExcel.elements.namedItem('archivo_excel') as HTMLInputElement;
    const nombreArchivo = qs('[data-excel-nombre]', formExcel);
    const AYUDA = {
        calendarios: ['Subir Excel de Calendarios', 'Columnas: No Calendario, Nombre. Reemplaza todos los calendarios y sus líneas.'],
        lineas: ['Subir Excel de Líneas de Calendarios', 'Columnas: No Calendario, Inicio (Fecha Hora), Fin (Fecha Hora), Horas, Turno. Reemplaza todas las líneas.'],
    } as const;

    function abrirExcel(tipo: keyof typeof AYUDA): void {
        formExcel.reset();
        (formExcel.elements.namedItem('tipo') as HTMLInputElement).value = tipo;
        const titulo = document.getElementById('modalExcelCalendario-titulo');
        if (titulo) titulo.textContent = AYUDA[tipo][0];
        const ayuda = qs('[data-excel-ayuda]', formExcel);
        if (ayuda) ayuda.textContent = AYUDA[tipo][1];
        if (nombreArchivo) nombreArchivo.hidden = true;
        abrir('modalExcelCalendario');
    }

    archivo.addEventListener('change', () => {
        if (!nombreArchivo) return;
        nombreArchivo.hidden = !archivo.files?.[0];
        nombreArchivo.textContent = archivo.files?.[0]?.name ?? '';
    });
    formExcel.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!archivo.files?.[0]) {
            window.notify.warning('Por favor selecciona un archivo');
            return;
        }
        loader.show();
        try {
            const res = (await window.http.upload(rutas.excel, new FormData(formExcel))) as Respuesta;
            if (!res?.success) throw { data: res };
            cerrarPorId('modalExcelCalendario');
            await window.notify.alert(`Registros procesados: ${res.data?.registros_procesados ?? 0}`, '¡Procesado Exitosamente!', 'success');
            window.location.reload();
        } catch (err) {
            window.notify.error(mensaje(err, 'Hubo un problema al procesar el archivo'));
        } finally {
            loader.hide();
        }
    });

    // ============ Recalcular ============

    async function recalcular(): Promise<void> {
        if (!calendario) {
            void window.notify.alert('Por favor selecciona un calendario de la tabla superior para recalcular sus programas', 'Selección requerida', 'info');
            return;
        }
        const id = calendario.dataset.id ?? '';
        const ok = await window.notify.confirm({
            title: 'Recalcular Programas de Tejido',
            text: `Calendario: ${calendario.dataset.nombre ?? id}. Se recalcularán las fechas de inicio y fin de todos los programas de tejido que usan este calendario y sus fórmulas dependientes.`,
            confirmText: 'Recalcular Programas',
            confirmColor: '#f59e0b',
        });
        if (!ok) return;
        window.notify.loading('Recalculando programas…');
        try {
            const res = (await window.http.post(conId(rutas.recalcular, id))) as Respuesta;
            window.notify.close();
            if (!res?.success) throw { data: res };
            await window.notify.alert(res.message ?? 'Recálculo completado', '¡Recálculo Completado!', 'success');
        } catch (err) {
            window.notify.close();
            void window.notify.alert(mensaje(err, 'Hubo un problema al recalcular los programas'), 'Error en el Recálculo', 'error');
        }
    }

    // ============ Navbar ============

    registrarAccionesCatalogo('calendarios', {
        agregar: () => (linea ? abrirLinea(null) : void modalCalendario.abrirAlta()),
        editar: () => {
            if (calendario) void modalCalendario.abrirEdicion(calendario.dataset.id ?? '');
            else if (linea) abrirLinea(linea);
            else window.notify.warning('Por favor selecciona una fila para editar');
        },
        eliminar: () => void eliminar(),
        'eliminar-rango': abrirRango,
        filtrar: () => {
            columnas();
            pintarFiltros();
            abrir('modalFiltros');
        },
        restablecer: () => {
            filtros = [];
            aplicarVisibilidad();
            window.notify.success('Restablecido: se quitaron todos los filtros');
        },
        'excel-calendarios': () => abrirExcel('calendarios'),
        'excel-lineas': () => abrirExcel('lineas'),
        recalcular: () => void recalcular(),
    });
    habilitarEdicion(false);
});
