/**
 * Modal de alta / edición masiva de calendario (catalagos/calendarios/modal-calendario).
 * La plantilla vive en los inputs; los horarios, topes y checks salen de logica.ts.
 */
import { abrir, cerrarPorId } from '../../../componentes/dialog.ts';
import { delegate, qs } from '../../../utils/dom.ts';
import {
    DIAS,
    HORAS_POR_DEFECTO,
    TURNOS,
    celdasDelDia,
    construirTurnos,
    horariosDelDia,
    maximoHoras,
    plantillaInicial,
    siguienteCalendario,
    validarPlantilla,
    type Dia,
    type Plantilla,
    type Turno,
} from './logica.ts';

export const MODAL_CALENDARIO = 'modalCalendario';

export interface RutasCalendario {
    json: string;
    detalle: string;
    crear: string;
    masivo: string;
}

interface Detalle {
    calendarioId: string;
    nombre: string;
    fechaInicial: string;
    fechaFinal: string;
    turnos: Record<string, Record<string, { horas?: number; activo?: boolean }>>;
}

const conId = (url: string, id: string): string => url.replace('__ID__', encodeURIComponent(id));

export class ModalCalendario {
    private readonly form: HTMLFormElement;
    private edicion: { id: string; nombre: string } | null = null;
    private alta: { id: string; nombre: string } = siguienteCalendario([]);
    private readonly rutas: RutasCalendario;

    constructor(rutas: RutasCalendario) {
        this.rutas = rutas;
        this.form = qs<HTMLFormElement>('#formCalendario')!;
        delegate<HTMLInputElement>(this.form, 'input', '[data-horas]', (_e, input) => this.alCambiarHoras(input));
        delegate<HTMLInputElement>(this.form, 'change', '[data-check-dia]', (_e, c) => this.marcar((_t, d) => d === c.dataset.checkDia, c.checked));
        delegate<HTMLInputElement>(this.form, 'change', '[data-check-turno]', (_e, c) => this.marcar((t) => String(t) === c.dataset.checkTurno, c.checked));
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            void this.guardar();
        });
    }

    // ============ Abrir ============

    async abrirAlta(): Promise<void> {
        try {
            const res = (await window.http.get(this.rutas.json)) as { data?: Array<{ CalendarioId: string; Nombre: string }> };
            this.alta = siguienteCalendario(res?.data ?? []);
        } catch {
            this.alta = siguienteCalendario([]);
        }
        this.edicion = null;
        const anio = new Date().getFullYear();
        this.cargar(`Agregar ${this.alta.nombre}`, 'Agregar', `${anio}-01-01`, `${anio}-12-31`, plantillaInicial(null));
    }

    async abrirEdicion(calendarioId: string): Promise<void> {
        window.notify.loading();
        try {
            const res = (await window.http.get(conId(this.rutas.detalle, calendarioId))) as { success?: boolean; data?: Detalle };
            window.notify.close();
            if (!res?.success || !res.data) throw { data: res };
            const d = res.data;
            this.edicion = { id: d.calendarioId, nombre: d.nombre };
            this.cargar('Editar Calendario', 'Guardar', d.fechaInicial, d.fechaFinal, plantillaInicial(d.turnos));
        } catch {
            window.notify.close();
            window.notify.error('Error al cargar calendario');
        }
    }

    private cargar(titulo: string, boton: string, desde: string, hasta: string, plantilla: Plantilla): void {
        const tituloEl = document.getElementById(`${MODAL_CALENDARIO}-titulo`);
        if (tituloEl) tituloEl.textContent = titulo;
        const texto = qs('[data-texto-guardar]');
        if (texto) texto.textContent = boton;
        (this.form.elements.namedItem('FechaInicial') as HTMLInputElement).value = desde;
        (this.form.elements.namedItem('FechaFinal') as HTMLInputElement).value = hasta;
        for (const turno of TURNOS) {
            for (const dia of DIAS) {
                const { activo, horas } = plantilla[turno][dia];
                this.check(turno, dia).checked = activo;
                this.horas(turno, dia).value = activo ? String(horas) : '0';
            }
        }
        this.pintar();
        abrir(MODAL_CALENDARIO);
    }

    // ============ Estado ⇄ inputs ============

    private check(turno: Turno, dia: Dia): HTMLInputElement {
        return qs<HTMLInputElement>(`[data-check-celda][data-turno="${turno}"][data-dia="${dia}"]`, this.form)!;
    }

    private horas(turno: Turno, dia: Dia): HTMLInputElement {
        return qs<HTMLInputElement>(`[data-horas][data-turno="${turno}"][data-dia="${dia}"]`, this.form)!;
    }

    plantilla(): Plantilla {
        const p = plantillaInicial(null);
        for (const turno of TURNOS) {
            for (const dia of DIAS) {
                const horas = Number.parseFloat(this.horas(turno, dia).value);
                p[turno][dia] = { activo: this.check(turno, dia).checked, horas: Number.isFinite(horas) ? horas : 0 };
            }
        }

        return p;
    }

    /** Horarios calculados, inputs deshabilitados y checks de fila/columna sincronizados. */
    private pintar(): void {
        const p = this.plantilla();
        for (const dia of DIAS) {
            const horarios = horariosDelDia(celdasDelDia(p, dia));
            for (const turno of TURNOS) {
                const h = horarios[turno];
                qs(`[data-inicio][data-turno="${turno}"][data-dia="${dia}"]`, this.form)!.textContent = h?.inicio ?? '';
                qs(`[data-fin][data-turno="${turno}"][data-dia="${dia}"]`, this.form)!.textContent = h?.fin ?? '';
                this.horas(turno, dia).disabled = !p[turno][dia].activo;
            }
            const cabecera = qs<HTMLInputElement>(`[data-check-dia="${dia}"]`, this.form);
            if (cabecera) cabecera.checked = TURNOS.some((t) => p[t][dia].activo);
        }
        for (const turno of TURNOS) {
            const fila = qs<HTMLInputElement>(`[data-check-turno="${turno}"]`, this.form);
            if (fila) fila.checked = DIAS.some((d) => p[turno][d].activo);
        }
    }

    /** Activa/desactiva celdas; al activar, las vacías toman 8 h; al desactivar quedan en 0. */
    private marcar(coincide: (turno: Turno, dia: Dia) => boolean, activo: boolean): void {
        for (const turno of TURNOS) {
            for (const dia of DIAS) {
                if (!coincide(turno, dia)) continue;
                this.check(turno, dia).checked = activo;
                const input = this.horas(turno, dia);
                if (!activo) input.value = '0';
                else if (input.value === '' || input.value === '0') input.value = String(HORAS_POR_DEFECTO);
            }
        }
        this.pintar();
    }

    private alCambiarHoras(input: HTMLInputElement): void {
        const turno = Number(input.dataset.turno) as Turno;
        const dia = input.dataset.dia as Dia;
        const valor = Number.parseFloat(input.value);
        if (!Number.isFinite(valor) || valor < 0) input.value = '0';
        const tope = maximoHoras(celdasDelDia(this.plantilla(), dia), turno);
        if (Number.parseFloat(input.value) > tope) input.value = tope.toFixed(1).replace(/\.0$/, '');
        this.pintar();
    }

    // ============ Guardar ============

    private async guardar(): Promise<void> {
        const desde = (this.form.elements.namedItem('FechaInicial') as HTMLInputElement).value;
        const hasta = (this.form.elements.namedItem('FechaFinal') as HTMLInputElement).value;
        const plantilla = this.plantilla();
        const error = validarPlantilla(desde, hasta, plantilla);
        if (error) {
            window.notify.warning(error);
            return;
        }
        const base = { FechaInicial: desde, FechaFinal: hasta, Turnos: construirTurnos(plantilla) };
        try {
            const res = (await (this.edicion
                ? window.http.put(conId(this.rutas.masivo, this.edicion.id), { Nombre: this.edicion.nombre, ...base })
                : window.http.post(this.rutas.crear, { CalendarioId: this.alta.id, Nombre: this.alta.nombre, ...base }))) as {
                success?: boolean;
                message?: string;
            };
            if (!res?.success) throw { data: res };
            cerrarPorId(MODAL_CALENDARIO);
            window.notify.success(res.message ?? 'Calendario guardado');
            window.setTimeout(() => window.location.reload(), 800);
        } catch (err) {
            const mensaje = (err as { data?: { message?: unknown } }).data?.message;
            window.notify.error(typeof mensaje === 'string' && mensaje ? mensaje : `Error al ${this.edicion ? 'actualizar' : 'crear'} calendario`);
        }
    }
}
