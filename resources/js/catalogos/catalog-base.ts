/**
 * CatalogBase (TS) — CRUD de catálogos simples sobre REST. Evolución de
 * public/js/catalogs/CatalogBase.js (piloto DS-12, fase 16):
 *
 *   - URL base configurable (el JS viejo tenía `/planeacion/` fijo).
 *   - window.http / window.notify en vez de fetch + Swal, formularios en <dialog>
 *     (x-ui.modal-base) declarados en Blade en vez de HTML armado dentro de Swal.
 *   - Tras guardar actualiza la fila en el DOM en vez de recargar la página.
 *   - Las filas nuevas se clonan de un <template> de Blade (mismas clases; nada de innerHTML).
 *   - Mismos hooks Template Method: validar(), procesar(), alSeleccionar().
 *
 * Contrato del servidor (el de los controllers de catálogos): GET/PUT/DELETE {endpoint}/{id},
 * POST {endpoint}; respuestas { success, message, data? }.
 *
 * Los 4 catálogos de Planeación (public/js/catalogs/*.js) siguen con la versión JS hasta que
 * su sesión 19-xx los migre. Receta: docs/cerebro-towell/Arquitectura/receta-componentes.md
 */
import { abrir, cerrarPorId } from '../componentes/dialog.ts';

export interface ColumnaCatalogo {
    campo: string;
    titulo?: string;
    sufijo?: string;
}

export interface CampoCatalogo {
    nombre: string;
    tipo: string;
    requerido?: boolean;
    etiqueta?: string;
}

export interface CatalogoConfig {
    clave: string;
    endpoint: string;
    llave: string;
    columnas: ColumnaCatalogo[];
    campos: CampoCatalogo[];
    textos: Record<string, string>;
}

export type Registro = Record<string, string | number | null>;

interface Respuesta {
    success?: boolean;
    message?: string;
    data?: Registro;
}

/** Lo mínimo que se usa de window.http y window.notify (inyectable en tests). */
export interface Dependencias {
    http: {
        get(url: string): Promise<unknown>;
        post(url: string, data?: unknown): Promise<unknown>;
        put(url: string, data?: unknown): Promise<unknown>;
        delete(url: string): Promise<unknown>;
    };
    notify: {
        success(msg: string): unknown;
        error(msg: string): unknown;
    };
}

export interface Elementos {
    raiz: HTMLElement;
    cuerpo: HTMLElement;
    plantilla: HTMLTemplateElement;
    vacio: HTMLElement | null;
    formulario: HTMLFormElement;
    tituloFormulario: HTMLElement | null;
    botonEditar: HTMLButtonElement | null;
    botonEliminar: HTMLButtonElement | null;
    botonCrear: HTMLElement | null;
    botonConfirmarEliminar: HTMLElement | null;
    botonGuardar: HTMLButtonElement | null;
}

export const MODAL_FORMULARIO = 'formModal';
export const MODAL_ELIMINAR = 'deleteModal';

/** URL del recurso: el id va codificado (las llaves de Comentarios son texto libre). */
export function urlRecurso(endpoint: string, id?: string): string {
    const base = endpoint.replace(/\/+$/, '');

    return id === undefined ? base : `${base}/${encodeURIComponent(id)}`;
}

/** Texto de celda como lo pinta Blade: vacío si no hay valor, con sufijo si lo hay. */
export function textoCelda(valor: unknown, sufijo = ''): string {
    if (valor === null || valor === undefined || String(valor).trim() === '') return '';

    return `${String(valor)}${sufijo}`;
}

/**
 * Mensaje de error a mostrar: el del servidor si lo hay. Sirve igual para un HttpError
 * (4xx/5xx) que para un 2xx con { success: false } (se lanza como { data: respuesta }).
 */
export function mensajeDeError(err: unknown, porDefecto: string): string {
    const data = (err as { data?: { message?: unknown } } | null)?.data;
    const mensaje = typeof data?.message === 'string' ? data.message.trim() : '';

    return mensaje !== '' ? mensaje : porDefecto;
}

export class CatalogBase {
    readonly config: CatalogoConfig;
    readonly el: Elementos;
    protected readonly deps: Dependencias;
    seleccionada: HTMLTableRowElement | null = null;
    private ocupado = false;

    constructor(config: CatalogoConfig, el: Elementos, deps: Dependencias) {
        this.config = config;
        this.el = el;
        this.deps = deps;
        this.escuchar();
        this.actualizarBotones();
    }

    // ============ Hooks (Template Method) ============

    /** Devuelve un mensaje si los datos no son válidos. */
    validar(datos: Registro): string | null {
        const faltante = this.config.campos.find((c) => c.requerido && String(datos[c.nombre] ?? '').trim() === '');

        return faltante ? `${faltante.etiqueta ?? faltante.nombre} es obligatorio` : null;
    }

    /** Ajusta los datos antes de enviarlos. */
    procesar(datos: Registro): Registro {
        return datos;
    }

    alSeleccionar(_fila: HTMLTableRowElement | null): void {}

    // ============ Selección ============

    idDe(fila: HTMLTableRowElement): string {
        return fila.dataset.id ?? '';
    }

    seleccionar(fila: HTMLTableRowElement | null): void {
        if (this.seleccionada) this.seleccionada.setAttribute('aria-selected', 'false');
        // Tocar la fila ya seleccionada la deselecciona (como antes).
        this.seleccionada = fila && fila !== this.seleccionada ? fila : null;
        this.seleccionada?.setAttribute('aria-selected', 'true');
        this.actualizarBotones();
        this.alSeleccionar(this.seleccionada);
    }

    actualizarBotones(): void {
        const sinSeleccion = this.seleccionada === null;
        if (this.el.botonEditar) this.el.botonEditar.disabled = sinSeleccion;
        if (this.el.botonEliminar) this.el.botonEliminar.disabled = sinSeleccion;
    }

    // ============ CRUD ============

    abrirCrear(): void {
        this.el.formulario.reset();
        this.valorOculto('');
        this.titulo(this.config.textos.nuevo ?? 'Nuevo');
        abrir(MODAL_FORMULARIO);
    }

    async abrirEditar(): Promise<void> {
        if (!this.seleccionada) return;
        const id = this.idDe(this.seleccionada);
        try {
            const res = (await this.deps.http.get(urlRecurso(this.config.endpoint, id))) as Respuesta;
            if (!res?.success || !res.data) throw { data: res };
            this.el.formulario.reset();
            this.valorOculto(id);
            for (const campo of this.config.campos) {
                const control = this.control(campo.nombre);
                if (control) control.value = String(res.data[campo.nombre] ?? '');
            }
            this.titulo(this.config.textos.editar ?? 'Editar');
            abrir(MODAL_FORMULARIO);
        } catch (err) {
            this.deps.notify.error(mensajeDeError(err, this.config.textos.errorCargar ?? 'No se pudo cargar el registro'));
        }
    }

    abrirEliminar(): void {
        if (this.seleccionada) abrir(MODAL_ELIMINAR);
    }

    async eliminar(): Promise<void> {
        const fila = this.seleccionada;
        if (!fila || this.ocupado) return;
        this.ocupado = true;
        try {
            const res = (await this.deps.http.delete(urlRecurso(this.config.endpoint, this.idDe(fila)))) as Respuesta;
            if (!res?.success) throw { data: res };
            // Cerrar primero (el foco vuelve a "Eliminar") y después llevarlo a la fila vecina:
            // el botón queda deshabilitado sin selección y el foco se perdería en <body>.
            cerrarPorId(MODAL_ELIMINAR);
            const vecina = [fila.nextElementSibling, fila.previousElementSibling].find(
                (f): f is HTMLTableRowElement => f instanceof HTMLElement && f.matches('tr[data-fila]'),
            );
            this.seleccionar(null);
            fila.remove();
            this.actualizarVacio();
            (vecina ?? this.el.botonCrear)?.focus();
            this.deps.notify.success(res.message ?? 'Eliminado');
        } catch (err) {
            cerrarPorId(MODAL_ELIMINAR);
            this.deps.notify.error(mensajeDeError(err, this.config.textos.errorEliminar ?? 'No se pudo eliminar'));
        } finally {
            this.ocupado = false;
        }
    }

    datosFormulario(): Registro {
        const datos: Registro = {};
        for (const campo of this.config.campos) {
            datos[campo.nombre] = this.control(campo.nombre)?.value ?? '';
        }

        return datos;
    }

    async guardar(): Promise<void> {
        if (this.ocupado) return;
        const datos = this.datosFormulario();
        const invalido = this.validar(datos);
        if (invalido) {
            this.deps.notify.error(invalido);
            return;
        }

        const original = this.valorOculto();
        const esEdicion = original !== '';
        const url = urlRecurso(this.config.endpoint, esEdicion ? original : undefined);
        const cuerpo = this.procesar(datos);

        this.ocupado = true;
        if (this.el.botonGuardar) this.el.botonGuardar.disabled = true;
        try {
            const res = (await (esEdicion ? this.deps.http.put(url, cuerpo) : this.deps.http.post(url, cuerpo))) as Respuesta;
            if (!res?.success) throw { data: res };
            this.pintarFila(await this.leerGuardado(cuerpo), esEdicion ? this.filaPorId(original) : null);
            cerrarPorId(MODAL_FORMULARIO);
            this.deps.notify.success(res.message ?? 'Guardado');
        } catch (err) {
            this.deps.notify.error(mensajeDeError(err, this.config.textos.errorGuardar ?? 'No se pudo guardar'));
        } finally {
            this.ocupado = false;
            if (this.el.botonGuardar) this.el.botonGuardar.disabled = false;
        }
    }

    /**
     * Lo que quedó en la base, no lo que se tecleó: el servidor recorta espacios (TrimStrings)
     * y normaliza números, y la llave de la fila debe ser la real para editar/eliminar después.
     * Si la relectura falla, se usan los valores enviados ya recortados.
     */
    async leerGuardado(enviado: Registro): Promise<Registro> {
        const recortado: Registro = {};
        for (const [k, v] of Object.entries(enviado)) recortado[k] = typeof v === 'string' ? v.trim() : v;
        const llave = String(recortado[this.config.llave] ?? '');
        if (llave === '') return recortado;
        try {
            const res = (await this.deps.http.get(urlRecurso(this.config.endpoint, llave))) as Respuesta;
            if (res?.success && res.data) return res.data;
        } catch {
            // se pinta lo enviado
        }

        return recortado;
    }

    // ============ DOM ============

    filaPorId(id: string): HTMLTableRowElement | null {
        return (
            [...this.el.cuerpo.querySelectorAll<HTMLTableRowElement>('tr[data-fila]')].find((f) => this.idDe(f) === id) ?? null
        );
    }

    /** Crea (o reemplaza) la fila con los valores guardados, clonando la plantilla de Blade. */
    pintarFila(valores: Registro, existente: HTMLTableRowElement | null): HTMLTableRowElement | null {
        const molde = this.el.plantilla.content.querySelector<HTMLTableRowElement>('tr[data-fila]');
        if (!molde) return null;
        const fila = molde.cloneNode(true) as HTMLTableRowElement;
        fila.dataset.id = String(valores[this.config.llave] ?? '');
        fila.querySelectorAll<HTMLElement>('td[data-campo]').forEach((td) => {
            td.textContent = textoCelda(valores[td.dataset.campo ?? ''], td.dataset.sufijo ?? '');
        });

        if (existente) {
            const estabaSeleccionada = existente === this.seleccionada;
            existente.replaceWith(fila);
            if (estabaSeleccionada) {
                this.seleccionada = null;
                this.seleccionar(fila);
            }
        } else {
            this.el.cuerpo.insertBefore(fila, this.el.vacio);
        }
        this.actualizarVacio();

        return fila;
    }

    actualizarVacio(): void {
        if (this.el.vacio) this.el.vacio.hidden = this.el.cuerpo.querySelector('tr[data-fila]') !== null;
    }

    private control(nombre: string): HTMLInputElement | HTMLTextAreaElement | null {
        return this.el.formulario.querySelector<HTMLInputElement | HTMLTextAreaElement>(`[name="${nombre}"]`);
    }

    private valorOculto(valor?: string): string {
        const oculto = this.control('__original');
        if (!oculto) return '';
        if (valor !== undefined) oculto.value = valor;

        return oculto.value;
    }

    private titulo(texto: string): void {
        if (this.el.tituloFormulario) this.el.tituloFormulario.textContent = texto;
    }

    private escuchar(): void {
        const { cuerpo, formulario } = this.el;

        cuerpo.addEventListener('click', (e) => {
            const fila = (e.target as Element | null)?.closest<HTMLTableRowElement>('tr[data-fila]');
            if (fila) this.seleccionar(fila);
        });
        cuerpo.addEventListener('keydown', (e) => {
            const fila = (e.target as Element | null)?.closest<HTMLTableRowElement>('tr[data-fila]');
            if (!fila) return;
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.seleccionar(fila);
            } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const hermana = e.key === 'ArrowDown' ? fila.nextElementSibling : fila.previousElementSibling;
                if (hermana instanceof HTMLElement && hermana.matches('tr[data-fila]')) hermana.focus();
            }
        });

        this.el.botonCrear?.addEventListener('click', () => this.abrirCrear());
        this.el.botonEditar?.addEventListener('click', () => void this.abrirEditar());
        this.el.botonEliminar?.addEventListener('click', () => this.abrirEliminar());
        this.el.botonConfirmarEliminar?.addEventListener('click', () => void this.eliminar());
        formulario.addEventListener('submit', (e) => {
            e.preventDefault();
            void this.guardar();
        });
    }
}

/** Localiza los elementos estándar de la vista de catálogo (x-ui.modal-base + x-ui.table). */
export function elementosDesde(raiz: HTMLElement): Elementos | null {
    const cuerpo = raiz.querySelector<HTMLElement>('[data-catalogo-filas]');
    const plantilla = raiz.querySelector<HTMLTemplateElement>('template[data-catalogo-plantilla]');
    const formulario = document.getElementById('catalogoForm');
    if (!cuerpo || !plantilla || !(formulario instanceof HTMLFormElement)) return null;

    return {
        raiz,
        cuerpo,
        plantilla,
        vacio: cuerpo.querySelector<HTMLElement>('[data-catalogo-vacio]'),
        formulario,
        tituloFormulario: document.getElementById(`${MODAL_FORMULARIO}-titulo`),
        botonEditar: document.getElementById('btnEdit') as HTMLButtonElement | null,
        botonEliminar: document.getElementById('btnDelete') as HTMLButtonElement | null,
        botonCrear: document.getElementById('btnCreate'),
        botonConfirmarEliminar: document.querySelector<HTMLElement>('[data-catalogo-confirmar-eliminar]'),
        botonGuardar: document.querySelector<HTMLButtonElement>('[data-catalogo-guardar]'),
    };
}
