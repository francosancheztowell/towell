/**
 * Modal Calificar Julios, una sola implementación para las dos variantes (19-01):
 *  - urdido:   julios de UrdProduccionUrdido, se abre desde Producción Engomado con el folio de la orden.
 *  - engomado: registros de EngProduccionEngomado, se abre desde Edición/Reimpresión Engomado.
 * Vista: resources/views/modulos/urdido/comun/calificar-julios.blade.php
 */
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { delegate, onReady } from '../../../../utils/dom.ts';
import { el, exigirExito, icono, leerDatos, mensajeError, type RespuestaApi } from '../pagina.ts';
import {
    TODAS_LAS_CLASES_DEFECTO,
    clasesDefecto,
    defectoSeleccionado,
    fechaCorta,
    fechaHoy,
    operadorDelJulio,
    type Defecto,
    type JulioCalificable,
} from './logica.ts';

interface ConfigCalificar {
    variante: 'urdido' | 'engomado';
    folio: string | null;
    zona: string;
    rutas: { julios: string; calificar: string };
    textos: { exito: string; sufijoInfo: string };
}

interface RespuestaJulios extends RespuestaApi {
    julios?: JulioCalificable[];
    defectos?: Defecto[];
}

class ModalCalificarJulios {
    private defectos: Defecto[] = [];
    private readonly cuerpo: HTMLTableSectionElement;
    private readonly tabla: HTMLElement;
    private readonly vacio: HTMLElement;
    private readonly cargando: HTMLElement;
    private readonly modal: HTMLElement;
    private readonly cfg: ConfigCalificar;

    constructor(modal: HTMLElement, cfg: ConfigCalificar) {
        this.modal = modal;
        this.cfg = cfg;
        this.cuerpo = modal.querySelector<HTMLTableSectionElement>('[data-cj="filas"]')!;
        this.tabla = modal.querySelector<HTMLElement>('[data-cj="tabla"]')!;
        this.vacio = modal.querySelector<HTMLElement>('[data-cj="vacio"]')!;
        this.cargando = modal.querySelector<HTMLElement>('[data-cj="cargando"]')!;

        delegate(modal, 'click', '[data-cj-cerrar]', () => this.cerrar());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.cerrar();
        });
        delegate<HTMLSelectElement, Event>(modal, 'change', 'select[data-julio-id]', (_e, sel) => {
            void this.calificar(sel);
        });
    }

    async abrir(folio: string | null = this.cfg.folio): Promise<void> {
        const etiquetaFolio = this.modal.querySelector('[data-cj="folio"]');
        if (etiquetaFolio) etiquetaFolio.textContent = folio ?? '';
        const hoy = this.modal.querySelector('[data-cj="hoy"]');
        if (hoy) hoy.textContent = fechaHoy(this.cfg.zona);

        this.modal.classList.remove('hidden');
        this.modal.classList.add('flex');
        this.cargando.classList.remove('hidden');
        this.tabla.classList.add('hidden');
        this.vacio.classList.add('hidden');

        try {
            const url = `${this.cfg.rutas.julios}?folio=${encodeURIComponent(folio ?? '')}`;
            const r = exigirExito(await http.get<RespuestaJulios>(url), 'Error al cargar');
            this.defectos = r.defectos ?? [];
            this.pintar(r.julios ?? []);
        } catch (err) {
            notify.error(mensajeError(err, 'Error al cargar'));
        } finally {
            this.cargando.classList.add('hidden');
        }
    }

    cerrar(): void {
        this.modal.classList.add('hidden');
        this.modal.classList.remove('flex');
    }

    private pintar(julios: JulioCalificable[]): void {
        this.cuerpo.replaceChildren();
        if (!julios.length) {
            this.tabla.classList.add('hidden');
            this.vacio.classList.remove('hidden');
            return;
        }
        this.vacio.classList.add('hidden');
        this.tabla.classList.remove('hidden');

        for (const j of julios) {
            const select = this.select(j);
            const celda = el('td', { clase: 'px-3 py-2' }, this.info(j), select);
            this.cuerpo.append(
                el(
                    'tr',
                    { clase: 'border-b hover:bg-gray-50' },
                    el('td', { clase: 'px-3 py-2', texto: j.Folio ?? '' }),
                    el('td', { clase: 'px-3 py-2 font-semibold', texto: j.NoJulio ?? '' }),
                    celda,
                ),
            );
            this.colorear(select);
        }
    }

    /** Operador y fecha del julio; null si no hay ninguno. */
    private info(j: Partial<JulioCalificable>): HTMLElement | null {
        const operador = operadorDelJulio(j);
        const fecha = fechaCorta(j.Fecha);
        if (!operador && !fecha) return null;
        const sufijo = this.cfg.textos.sufijoInfo;
        return el(
            'div',
            { clase: 'info-julio text-xs text-gray-500 mb-1 flex flex-wrap gap-3' },
            operador &&
                el('span', { attrs: { title: `Oficial con mayor metraje en este julio${sufijo}` } }, icono('fa-solid fa-user mr-1'), operador),
            fecha && el('span', { attrs: { title: `Fecha del julio${sufijo}` } }, icono('fa-solid fa-calendar mr-1'), fecha),
        );
    }

    private select(j: JulioCalificable): HTMLSelectElement {
        const select = el('select', {
            clase: 'w-full border rounded px-2 py-1 text-sm font-semibold',
            attrs: { 'data-julio-id': String(j.Id), 'aria-label': `Defecto del julio ${j.NoJulio ?? ''}` },
        });
        const ninguno = el('option', { texto: '— Sin defecto —', attrs: { value: '', 'data-clave': '' } });
        select.append(ninguno);
        for (const d of this.defectos) {
            const opcion = el('option', {
                texto: `${d.Clave} — ${d.Defecto ?? ''}`,
                attrs: { value: String(d.Id), 'data-clave': d.Clave },
            });
            opcion.selected = defectoSeleccionado(j, d);
            select.append(opcion);
        }
        return select;
    }

    private colorear(select: HTMLSelectElement): void {
        select.classList.remove(...TODAS_LAS_CLASES_DEFECTO);
        const clave = select.options[select.selectedIndex]?.dataset.clave;
        select.classList.add(...clasesDefecto(clave));
    }

    private async calificar(select: HTMLSelectElement): Promise<void> {
        this.colorear(select);
        select.disabled = true;
        try {
            const r = exigirExito(
                await http.post<RespuestaApi & { data?: Partial<JulioCalificable> }>(this.cfg.rutas.calificar, {
                    julio_id: Number(select.dataset.julioId),
                    defecto_id: select.value || null,
                }),
                'Error al guardar',
            );
            notify.success(this.cfg.textos.exito);
            const celda = select.closest('td');
            celda?.querySelector('.info-julio')?.remove();
            const info = this.info(r.data ?? {});
            if (celda && info) celda.prepend(info);
        } catch (err) {
            notify.error(mensajeError(err, 'Error al guardar'));
        } finally {
            select.disabled = false;
        }
    }
}

onReady(() => {
    const modales = new Map<string, ModalCalificarJulios>();
    document.querySelectorAll<HTMLElement>('[data-calificar-julios]').forEach((nodo) => {
        const cfg = leerDatos<ConfigCalificar>(nodo, 'calificarJulios');
        if (cfg) modales.set(cfg.variante, new ModalCalificarJulios(nodo, cfg));
    });

    // Botón de la vista que lo incluye: <button data-calificar-julios-abrir="urdido">.
    delegate(document, 'click', '[data-calificar-julios-abrir]', (_e, boton) => {
        void modales.get((boton as HTMLElement).dataset.calificarJuliosAbrir ?? '')?.abrir();
    });

    // PUENTE 19-01: resources/js/urd-eng/edicion-ordenes.ts (19-05) abre la variante engomado
    // con el folio que manda el evento Livewire 'engomado-calificar-julios'. Quitar cuando lo importe.
    const engomado = modales.get('engomado');
    if (engomado) {
        (window as Window & { abrirModalCalificarJuliosEng?: (folio: string) => void }).abrirModalCalificarJuliosEng = (folio) =>
            void engomado.abrir(folio);
    }
});
