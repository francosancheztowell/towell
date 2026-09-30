/**
 * CRUD de los catálogos de Planeación (19-06b) sobre resources/js/catalogos/catalog-base.ts:
 * botones del navbar de catalog-actions, formulario/borrado/filtros en <dialog>
 * (catalagos/comun/modales.blade.php), valores de cada fila en data-valores y recarga tras
 * guardar (igual que antes: la tabla la formatea el servidor).
 */
import { abrir, cerrarPorId } from '../../../componentes/dialog.ts';
import {
    CatalogBase,
    type Dependencias,
    type Elementos,
    type Registro,
    type Respuesta,
    MODAL_ELIMINAR,
    MODAL_FORMULARIO,
} from '../../../catalogos/catalog-base.ts';
import { actualizarContadorFiltros, registrarAccionesCatalogo } from '../../../catalogos/catalog-actions.ts';
import {
    coincide,
    faltanteObligatorio,
    filtrosActivos,
    leerValores,
    opcionesDependientes,
    rangoInvalido,
    type ConfigPlaneacion,
    type ValoresFiltro,
} from './logica.ts';

export const MODAL_FILTROS = 'filtrosModal';
const FILA = 'tr[data-fila]';

/** Lo que cambia por catálogo (todo opcional). */
export interface Personalizacion {
    /** Reglas extra después de los obligatorios; mensaje o null. */
    validar?(datos: Registro): string | null;
    /** Datos a enviar (números, mayúsculas, campos vacíos…). */
    procesar?(datos: Registro): Registro;
    /** Valores de la fila → valores del formulario (Eficiencia 0.85 → 85). */
    aFormulario?(valores: Registro): Registro;
    /** Al abrir el formulario (alta: valores = null). */
    alAbrir?(formulario: HTMLFormElement, valores: Registro | null): void;
    /** Texto bajo la confirmación de borrado. */
    resumen?(valores: Registro): string;
}

/** Deja en `select` las opciones dadas (conserva el "Seleccionar"/"Todos" inicial y un valor fuera de lista). */
export function repoblar(select: HTMLSelectElement, opciones: string[], valor = ''): void {
    const vacia = select.querySelector<HTMLOptionElement>('option[value=""]');
    const nuevas = opciones.map((o) => new Option(o, o));
    if (valor !== '' && !opciones.includes(valor)) nuevas.push(new Option(valor, valor));
    select.replaceChildren(...(vacia ? [vacia] : []), ...nuevas);
    select.value = valor;
}

/** Selects con data-depende: al cambiar el padre se repueblan con opcionesPor[padre]. */
function enlazarDependientes(
    form: HTMLFormElement,
    opcionesPor: (nombre: string) => Record<string, Array<string | number>> | undefined,
): () => void {
    const hijos = [...form.querySelectorAll<HTMLSelectElement>('select[data-depende]')];
    const sincronizar = (conservar: boolean): void => {
        for (const hijo of hijos) {
            const padre = form.querySelector<HTMLSelectElement>(`[name="${hijo.dataset.depende}"]`);
            repoblar(hijo, opcionesDependientes(opcionesPor(hijo.name), padre?.value ?? ''), conservar ? hijo.value : '');
        }
    };
    form.addEventListener('change', (e) => {
        const nombre = (e.target as HTMLSelectElement | null)?.name;
        if (nombre && hijos.some((h) => h.dataset.depende === nombre)) sincronizar(false);
    });

    return () => sincronizar(true);
}

function actualizarSalidasRango(form: HTMLFormElement): void {
    form.querySelectorAll<HTMLOutputElement>('output[data-salida-rango]').forEach((salida) => {
        const rango = form.querySelector<HTMLInputElement>(`[name="${salida.dataset.salidaRango}"]`);
        salida.textContent = `${rango?.value ?? ''}${salida.dataset.sufijo ?? ''}`;
    });
}

export class CatalogoPlaneacion extends CatalogBase {
    readonly planeacion: ConfigPlaneacion;
    private readonly p: Personalizacion;
    private readonly sincronizarDependientes: () => void;
    recargar: () => void = () => window.setTimeout(() => window.location.reload(), 800);

    constructor(config: ConfigPlaneacion, el: Elementos, deps: Dependencias, p: Personalizacion = {}) {
        super({ ...config, columnas: [] }, el, deps);
        this.planeacion = config;
        this.p = p;
        const campos = new Map(config.campos.map((c) => [c.nombre, c]));
        this.sincronizarDependientes = enlazarDependientes(el.formulario, (n) => campos.get(n)?.opcionesPor);
        el.formulario.addEventListener('input', () => actualizarSalidasRango(el.formulario));
    }

    validar(datos: Registro): string | null {
        return faltanteObligatorio(this.config.campos, datos) ?? this.p.validar?.(datos) ?? null;
    }

    procesar(datos: Registro): Registro {
        return this.p.procesar ? this.p.procesar(datos) : datos;
    }

    async valoresParaEditar(fila: HTMLTableRowElement): Promise<Registro> {
        const valores = leerValores(fila.dataset.valores);

        return this.p.aFormulario ? this.p.aFormulario(valores) : valores;
    }

    alAbrirFormulario(valores: Registro | null): void {
        const form = this.el.formulario;
        // El select dependiente (Telar) se llena después de fijar el padre (Salón).
        this.sincronizarDependientes();
        for (const campo of this.planeacion.campos.filter((c) => c.depende)) {
            const control = this.control(campo.nombre);
            if (control instanceof HTMLSelectElement) {
                const padre = String(valores?.[campo.depende ?? ''] ?? this.control(campo.depende ?? '')?.value ?? '');
                repoblar(control, opcionesDependientes(campo.opcionesPor, padre), String(valores?.[campo.nombre] ?? ''));
            }
        }
        actualizarSalidasRango(form);
        this.p.alAbrir?.(form, valores);
    }

    async despuesDeGuardar(res: Respuesta): Promise<void> {
        cerrarPorId(MODAL_FORMULARIO);
        this.deps.notify.success(res.message ?? 'Guardado');
        this.recargar();
    }

    abrirEliminar(): void {
        if (!this.seleccionada) return;
        const resumen = this.el.raiz.ownerDocument.querySelector<HTMLElement>('[data-catalogo-resumen-eliminar]');
        if (resumen) resumen.textContent = this.p.resumen?.(leerValores(this.seleccionada.dataset.valores)) ?? '';
        abrir(MODAL_ELIMINAR);
    }
}

type AvisosFiltro = Dependencias['notify'] & { warning(msg: string): unknown };

/** Filtros del lado del cliente: ocultan filas (los datos ya están en la tabla). */
export class FiltrosCatalogo {
    activos: ValoresFiltro = {};
    private readonly sincronizarDependientes: () => void;
    private readonly config: ConfigPlaneacion;
    private readonly catalogo: CatalogoPlaneacion;
    private readonly form: HTMLFormElement | null;
    private readonly notify: AvisosFiltro;

    constructor(config: ConfigPlaneacion, catalogo: CatalogoPlaneacion, form: HTMLFormElement | null, notify: AvisosFiltro) {
        this.config = config;
        this.catalogo = catalogo;
        this.form = form;
        this.notify = notify;
        const filtros = new Map(config.filtros.map((f) => [f.nombre, f]));
        this.sincronizarDependientes = form ? enlazarDependientes(form, (n) => filtros.get(n)?.opcionesPor) : () => {};
        form?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.aplicarDesdeFormulario();
        });
    }

    abrir(): void {
        if (!this.form) return;
        this.sincronizarDependientes();
        abrir(MODAL_FILTROS);
    }

    private aplicarDesdeFormulario(): void {
        if (!this.form) return;
        const valores: ValoresFiltro = {};
        new FormData(this.form).forEach((v, k) => {
            valores[k] = String(v);
        });
        const invalido = rangoInvalido(this.config.filtros, valores);
        if (invalido) {
            this.notify.warning(invalido);
            return;
        }
        const { visibles, total } = this.aplicar(valores);
        cerrarPorId(MODAL_FILTROS);
        this.notify.success(`${visibles} de ${total} registros mostrados`);
    }

    aplicar(valores: ValoresFiltro): { visibles: number; total: number } {
        this.activos = valores;
        const filas = [...this.catalogo.el.cuerpo.querySelectorAll<HTMLTableRowElement>(FILA)];
        let visibles = 0;
        for (const fila of filas) {
            const pasa = coincide(leerValores(fila.dataset.valores), this.config.filtros, valores);
            fila.hidden = !pasa;
            if (pasa) visibles++;
            if (!pasa && fila === this.catalogo.seleccionada) this.catalogo.seleccionar(null);
        }
        const sin = this.catalogo.el.cuerpo.querySelector<HTMLElement>('[data-catalogo-sin-coincidencias]');
        if (sin) sin.hidden = visibles > 0 || filas.length === 0;
        actualizarContadorFiltros(filtrosActivos(valores));

        return { visibles, total: filas.length };
    }

    limpiar(): void {
        this.form?.reset();
        const { total } = this.aplicar({});
        this.notify.success(`Filtros limpiados - Mostrando ${total} registros`);
    }
}

/** Monta el catálogo de la página: [data-catalogo] + modales comunes + botones de catalog-actions. */
export function arrancarCatalogo(p: Personalizacion = {}, doc: Document = document): CatalogoPlaneacion | null {
    const raiz = doc.querySelector<HTMLElement>('[data-catalogo]');
    const cuerpo = raiz?.querySelector<HTMLElement>('[data-catalogo-filas]');
    const formulario = doc.getElementById('catalogoForm');
    if (!raiz || !cuerpo || !(formulario instanceof HTMLFormElement)) return null;

    const config = JSON.parse(raiz.dataset.catalogo ?? '{}') as ConfigPlaneacion;
    const el: Elementos = {
        raiz,
        cuerpo,
        plantilla: null,
        vacio: cuerpo.querySelector<HTMLElement>('[data-catalogo-vacio]'),
        formulario,
        tituloFormulario: doc.getElementById(`${MODAL_FORMULARIO}-titulo`),
        botonCrear: doc.getElementById('btn-agregar'),
        botonEditar: doc.getElementById('btn-editar') as HTMLButtonElement | null,
        botonEliminar: doc.getElementById('btn-eliminar') as HTMLButtonElement | null,
        botonConfirmarEliminar: doc.querySelector<HTMLElement>('[data-catalogo-confirmar-eliminar]'),
        botonGuardar: doc.querySelector<HTMLButtonElement>('[data-catalogo-guardar]'),
        botonesExternos: true,
    };
    const catalogo = new CatalogoPlaneacion(config, el, { http: window.http, notify: window.notify }, p);
    const formFiltros = doc.getElementById('catalogoFiltrosForm');
    const filtros = new FiltrosCatalogo(
        config,
        catalogo,
        formFiltros instanceof HTMLFormElement ? formFiltros : null,
        window.notify,
    );

    registrarAccionesCatalogo(config.ruta, {
        agregar: () => catalogo.abrirCrear(),
        editar: () => catalogo.abrirEditar(),
        eliminar: () => catalogo.abrirEliminar(),
        filtrar: () => filtros.abrir(),
        restablecer: () => filtros.limpiar(),
    });
    return catalogo;
}
