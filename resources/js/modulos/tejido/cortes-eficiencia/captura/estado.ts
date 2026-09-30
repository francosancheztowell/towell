/**
 * Estado compartido de la captura de Cortes de Eficiencia (19-02).
 * Vista: resources/views/modulos/cortes-eficiencia/cortes-eficiencia.blade.php.
 */
import { hoyLocal } from '../comun/logica.ts';

export interface RutasCaptura {
    store: string;
    consultar: string;
    notificarTelegram: string;
    turnoInfo: string;
    datosPrograma: string;
    datosTelares: string;
    fallas: string;
    generarFolio: string;
    guardarHora: string;
    /** Con __FOLIO__. */
    corte: string;
    pdf: string;
    finalizar: string;
}

export interface ConfigCaptura {
    soloLectura: boolean;
    folioInicial: string | null;
    aviso: string | null;
    rutas: RutasCaptura;
}

export interface Falla {
    Clave: string;
    Descripcion: string | null;
}

export const estado = {
    folio: null as string | null,
    fecha: hoyLocal(new Date()),
    turno: '',
    usuario: '',
    noEmpleado: '',
    status: 'En Proceso',
    /** Texto de observación por "telar-horario". */
    observaciones: {} as Record<string, string>,
};

let config: ConfigCaptura | null = null;

export function fijarConfig(c: ConfigCaptura): void {
    config = c;
}

export function cfg(): ConfigCaptura {
    if (!config) throw new Error('Cortes: configuración no cargada');
    return config;
}

/** Ruta con el folio en lugar de __FOLIO__. */
export function rutaFolio(plantilla: string, folio: string): string {
    return plantilla.replace('__FOLIO__', encodeURIComponent(folio));
}

export function cuerpoTabla(): HTMLElement {
    const el = document.getElementById('telares-body');
    if (!el) throw new Error('Cortes: falta #telares-body');
    return el;
}

export function horaHorario(h: number): HTMLElement | null {
    return document.getElementById(`hora-horario-${h}`);
}

export const botones = {
    imagen: (): HTMLButtonElement | null => document.getElementById('btn-capturar-imagen') as HTMLButtonElement | null,
    telegram: (): HTMLButtonElement | null => document.getElementById('btn-telegram-folio') as HTMLButtonElement | null,
    finalizar: (): HTMLButtonElement | null => document.getElementById('btn-finalizar-folio') as HTMLButtonElement | null,
};

export function actualizarEstadoBotonesHeader(): void {
    const tieneFolio = !!estado.folio;
    const finalizado = estado.status === 'Finalizado';
    const t = botones.telegram();
    const f = botones.finalizar();
    if (t) t.disabled = cfg().soloLectura || !tieneFolio;
    if (f) f.disabled = cfg().soloLectura || !tieneFolio || finalizado;
}

export function actualizarBadgeFolio(): void {
    const badge = document.getElementById('badge-folio');
    const texto = document.getElementById('folio-text');
    if (badge && texto) {
        if (estado.folio) {
            texto.textContent = estado.folio;
            badge.classList.remove('hidden');
            badge.classList.add('flex');
        } else {
            badge.classList.add('hidden');
            badge.classList.remove('flex');
        }
    }
    actualizarEstadoBotonesHeader();
}
