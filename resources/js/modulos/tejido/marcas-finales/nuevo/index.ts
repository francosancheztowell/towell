/**
 * Marcas Finales: captura (nuevo folio / editar / visualizar). 19-02.
 * Vista: resources/views/modulos/marcas-finales/nuevo-marcas.blade.php (config en data-pagina).
 */
import { delegate, onReady, qs, qsa } from '../../../../utils/dom.ts';
import { escapeHtml } from '../../../../utils/format.ts';
import { csrfToken, http, HttpError } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { alertaError, ErrorApi, exigirExito, leerDatos, mensajeError, type RespuestaApi } from '../../comun/pagina.ts';
import {
    CAMPOS,
    clasificarErrorFolio,
    construirLinea,
    limitarAlEscribir,
    normalizarAlSalir,
    sugerencia,
    valoresDesdeLinea,
    type ErrorFolio,
    type TipoCampo,
} from './logica.ts';

interface ConfigNuevo {
    soloLectura: boolean;
    folioInicial: string | null;
    turnoActual: number | null;
    rutas: {
        generarFolio: string;
        store: string;
        std: string;
        show: string;
        editar: string;
        consultar: string;
    };
}

interface RespuestaFolio extends RespuestaApi {
    folio?: string;
    fecha?: string;
    turno?: number | string;
}

interface Marca {
    Folio?: string;
    Date?: string | null;
    Turno?: number | string | null;
}

interface RespuestaShow extends RespuestaApi {
    marca?: Marca;
    lineas?: Record<string, unknown>[];
}

interface DatoStd {
    telar: string | number;
    salon?: string | null;
    porcentaje_efi?: number | null;
}

type Resultado = 'ok' | 'reintentar' | 'fin';

const GUARDAR_MS = 1000;
const DESTELLO_MS = 300;

function iniciar(): void {
    const raiz = qs('#pagina-marcas-nuevo');
    const cfg = leerDatos<ConfigNuevo>(raiz);
    const cuerpo = qs('#telares-body');
    if (!raiz || !cfg || !cuerpo) return;

    let folio: string | null = null;
    let esNuevo = true;
    let meta: { fecha: string | null; turno: string | null } = { fecha: null, turno: null };
    let temporizador: ReturnType<typeof setTimeout> | null = null;

    /* ---------- Celdas por telar ---------- */
    const celdas = new Map<string, HTMLInputElement>();
    qsa<HTMLInputElement>('input.valor-input', cuerpo).forEach((input) => {
        celdas.set(`${input.dataset.telar}|${input.dataset.type}`, input);
    });
    const celda = (telar: string, tipo: TipoCampo): HTMLInputElement | undefined => celdas.get(`${telar}|${tipo}`);
    const filas = (): HTMLTableRowElement[] => qsa<HTMLTableRowElement>('tr[data-telar]', cuerpo);
    const bloqueado = (input: HTMLInputElement): boolean => cfg.soloLectura || input.readOnly;

    const pintarBadge = (): void => {
        const badge = qs('#badge-folio');
        const texto = qs('#folio-text');
        if (!badge || !texto) return;
        if (folio) {
            texto.textContent = folio;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    };

    /* ---------- Guardado ---------- */
    const payload = (): Record<string, unknown> | null => {
        const fecha = meta.fecha;
        const turno = meta.turno;
        if (!folio || !fecha || !turno) return null;
        const lineas = filas().map((fila) => {
            const telar = fila.dataset.telar ?? '';
            const valores: Partial<Record<TipoCampo, string>> = {};
            for (const tipo of CAMPOS) valores[tipo] = celda(telar, tipo)?.value ?? '';
            return construirLinea(telar, valores);
        });
        return { folio, fecha, turno, lineas };
    };

    async function guardar(): Promise<void> {
        temporizador = null;
        if (cfg!.soloLectura || !folio) return;
        const datos = payload();
        if (!datos) {
            void notify.alert('Falta fecha o turno para guardar.', 'Aviso', 'warning');
            return;
        }
        try {
            exigirExito(await http.post<RespuestaApi>(cfg!.rutas.store, datos), 'desconocido');
            notify.success(esNuevo ? 'Folio creado y guardado' : 'Datos actualizados');
            esNuevo = false;
        } catch (err) {
            notify.error(`Error al guardar: ${mensajeError(err, 'desconocido')}`);
        }
    }

    const guardarLuego = (): void => {
        if (cfg.soloLectura) return;
        if (temporizador) clearTimeout(temporizador);
        temporizador = setTimeout(() => void guardar(), GUARDAR_MS);
    };

    /** Al salir con cambios pendientes: sendBeacon sobrevive a la descarga de la página (fetch no). */
    const guardarAlSalir = (): void => {
        if (!temporizador) return;
        clearTimeout(temporizador);
        temporizador = null;
        const datos = payload();
        if (!datos || typeof navigator.sendBeacon !== 'function') return;
        const cuerpoBeacon = new Blob([JSON.stringify({ ...datos, _token: csrfToken() })], { type: 'application/json' });
        navigator.sendBeacon(cfg.rutas.store, cuerpoBeacon);
    };

    /* ---------- Celdas ---------- */
    delegate<HTMLInputElement>(cuerpo, 'focusin', 'input.valor-input', (_ev, input) => {
        if (bloqueado(input)) return;
        const valor = sugerencia(input.dataset.type ?? '', input.value, input.dataset.recommended);
        if (valor !== null) input.value = valor;
        input.select();
    });
    delegate<HTMLInputElement>(cuerpo, 'input', 'input.valor-input', (_ev, input) => {
        if (bloqueado(input)) return;
        const valor = limitarAlEscribir(input.dataset.type ?? '', input.value);
        if (valor !== null) input.value = valor;
    });
    delegate<HTMLInputElement>(cuerpo, 'focusout', 'input.valor-input', (_ev, input) => {
        if (bloqueado(input)) return;
        const valor = normalizarAlSalir(input.dataset.type ?? '', input.value);
        if (valor !== input.value) input.value = valor;
        guardarLuego();
    });
    delegate<HTMLInputElement>(cuerpo, 'change', 'input.valor-input', (_ev, input) => {
        if (bloqueado(input)) return;
        input.classList.add('bg-green-100');
        setTimeout(() => input.classList.remove('bg-green-100'), DESTELLO_MS);
    });

    /* ---------- Datos STD (salón y % Efi sugerido) ---------- */
    async function cargarDatosStd(): Promise<void> {
        try {
            const d = await http.get<RespuestaApi & { datos?: DatoStd[] }>(cfg!.rutas.std, { headers: { 'Cache-Control': 'no-cache' } });
            if (!d.success || !Array.isArray(d.datos)) return;
            const salones = new Map<string, HTMLElement>();
            qsa('[data-field="salon"]', cuerpo!).forEach((el) => salones.set(el.dataset.telar ?? '', el));
            for (const item of d.datos) {
                const telar = String(item.telar);
                const salon = salones.get(telar);
                if (salon) salon.textContent = item.salon || '-';
                const efi = celda(telar, 'efi');
                if (efi && item.porcentaje_efi != null) efi.dataset.recommended = String(Math.trunc(Number(item.porcentaje_efi)));
            }
        } catch {
            // Sin STD la captura sigue funcionando: solo no hay sugerencia de % Efi.
        }
    }

    /* ---------- Folio existente ---------- */
    async function cargarMarca(folioCargar: string): Promise<boolean> {
        try {
            const url = cfg!.rutas.show.replace('__FOLIO__', encodeURIComponent(folioCargar));
            const d = exigirExito(await http.get<RespuestaShow>(url, { headers: { 'Cache-Control': 'no-cache' } }), 'No se pudo cargar la marca');
            folio = folioCargar;
            esNuevo = false;
            meta = { fecha: d.marca?.Date ?? null, turno: d.marca?.Turno != null ? String(d.marca.Turno) : null };
            pintarBadge();
            for (const linea of d.lineas ?? []) {
                const telar = String(linea.NoTelarId ?? '');
                const valores = valoresDesdeLinea(linea);
                for (const tipo of CAMPOS) {
                    const input = celda(telar, tipo);
                    if (input) input.value = valores[tipo];
                }
            }
            return true;
        } catch (err) {
            alertaError(err, 'No se pudo cargar la marca');
            return false;
        }
    }

    /* ---------- Folio nuevo ---------- */
    const modal = qs('#modal-fecha-turno');
    const inputFecha = qs<HTMLInputElement>('#input-fecha-folio');
    const selectTurno = qs<HTMLSelectElement>('#select-turno-folio');

    /** Pide fecha y turno. null = canceló. */
    function pedirFechaTurno(): Promise<{ fecha: string; turno: string } | null> {
        return new Promise((resolve) => {
            if (!modal || !inputFecha || !selectTurno) {
                resolve(null);
                return;
            }
            selectTurno.value = String(cfg!.turnoActual || 1);
            const terminar = (valor: { fecha: string; turno: string } | null): void => {
                quitarClic();
                document.removeEventListener('keydown', teclado);
                modal.classList.add('hidden');
                resolve(valor);
            };
            const quitarClic = delegate(modal, 'click', '[data-modal-accion]', (_ev, el) => {
                if (el.dataset.modalAccion !== 'ok') {
                    terminar(null);
                    return;
                }
                const fecha = inputFecha.value;
                const turno = selectTurno.value;
                if (!fecha || !turno) {
                    void notify.alert('Selecciona fecha y turno.', 'Aviso', 'warning');
                    return;
                }
                terminar({ fecha, turno });
            });
            const teclado = (ev: KeyboardEvent): void => {
                if (ev.key === 'Escape') terminar(null);
            };
            document.addEventListener('keydown', teclado);
            modal.classList.remove('hidden');
            inputFecha.focus();
        });
    }

    const irA = (url: string): void => {
        window.location.href = url;
    };
    const urlEditar = (f: string): string => `${cfg.rutas.editar}?folio=${encodeURIComponent(f)}`;

    async function generarFolio(fecha: string, turno: string): Promise<Resultado> {
        try {
            const d = await http.post<RespuestaFolio>(cfg!.rutas.generarFolio, { fecha, turno });
            if (!d.success || !d.folio) throw new ErrorApi(d.message || 'Error al generar folio');
            folio = d.folio;
            meta = { fecha: d.fecha || fecha, turno: String(d.turno || turno) };
            esNuevo = true;
            pintarBadge();
            return 'ok';
        } catch (err) {
            const r = err instanceof HttpError ? clasificarErrorFolio(err.status, err.data as ErrorFolio) : { tipo: 'error' as const };
            switch (r.tipo) {
                case 'en-creacion':
                    await notify.alert(
                        `Otro usuario está creando un folio en este momento (${r.folio}). Por favor, espere unos segundos e intente nuevamente.`,
                        'Folio en creación',
                        'warning',
                    );
                    irA(cfg!.rutas.consultar);
                    return 'fin';
                case 'en-proceso': {
                    const editar = await notify.confirm({
                        title: 'Folio en proceso',
                        html: `${escapeHtml(r.mensaje)}<br><br>¿Desea continuar editando ese folio?`,
                        confirmText: 'Sí, editar',
                        cancelText: 'Cancelar',
                    });
                    irA(editar ? urlEditar(r.folio) : cfg!.rutas.consultar);
                    return 'fin';
                }
                case 'duplicado':
                    await notify.alert(r.mensaje, 'Duplicado', 'warning');
                    return 'reintentar';
                case 'invalido':
                    await notify.alert(r.mensaje, 'Aviso', 'warning');
                    return 'reintentar';
                default:
                    alertaError(err, 'No se pudo generar el folio');
                    return 'fin';
            }
        }
    }

    /** Pide fecha/turno hasta obtener folio; cancelar regresa a Consultar. */
    async function nuevoFolio(): Promise<void> {
        for (;;) {
            const eleccion = await pedirFechaTurno();
            if (!eleccion) {
                irA(cfg!.rutas.consultar);
                return;
            }
            if ((await generarFolio(eleccion.fecha, eleccion.turno)) !== 'reintentar') return;
        }
    }

    /* ---------- Arranque ---------- */
    const folioUrl = cfg.folioInicial || new URLSearchParams(window.location.search).get('folio');
    if (folioUrl) {
        void cargarMarca(folioUrl).then((ok) => {
            if (ok) void cargarDatosStd();
        });
    } else if (!cfg.soloLectura) {
        void cargarDatosStd();
        void nuevoFolio();
    } else {
        void notify.alert('No se pudo determinar el folio a visualizar.', 'Aviso', 'warning');
    }

    if (!cfg.soloLectura) {
        window.addEventListener('pagehide', guardarAlSalir);
        window.addEventListener('beforeunload', guardarAlSalir);
    }
}

onReady(iniciar);
