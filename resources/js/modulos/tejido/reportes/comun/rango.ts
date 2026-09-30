/**
 * Modal de fechas de los reportes de Tejido (19-02). Vista:
 * resources/views/modulos/tejido/reportes/partials/rango-fechas.blade.php (x-ui.modal-base).
 * Reemplaza el Swal con inputs que tenían copiado inv-telas, promedio paros, marcas finales
 * (rango) y RPM semanal (una fecha de la semana). Los botones lo abren con
 * data-ui-modal-open; aquí: validar, navegar y abrir solo cuando la página llega sin fechas.
 */
import { abrir } from '../../../../componentes/dialog.ts';
import { leerDatos } from '../../comun/pagina.ts';
import {
    textoSemana,
    urlConsulta,
    validarRango,
    validarSemana,
    type CampoRango,
} from './rango-logica.ts';

export interface ConfigRango {
    modal: string;
    modo: 'rango' | 'semana';
    ruta: string;
    maxDias: number | null;
    /** true cuando la página llegó sin datos: el modal se abre solo. */
    abrirAlCargar: boolean;
}

const CAMPOS: CampoRango[] = ['fecha_ini', 'fecha_fin', 'semana'];

function mostrarError(form: HTMLFormElement, campo: CampoRango | null, mensaje = ''): void {
    for (const nombre of CAMPOS) {
        const input = form.elements.namedItem(nombre);
        if (!(input instanceof HTMLInputElement)) continue;
        const error = document.getElementById(`${input.id}-error`);
        const conError = nombre === campo;
        input.classList.toggle('border-red-400', conError);
        input.classList.toggle('border-gray-300', !conError);
        if (error) {
            error.textContent = conError ? mensaje : '';
            error.hidden = !conError;
        }
        if (conError) {
            input.setAttribute('aria-invalid', 'true');
            if (error) input.setAttribute('aria-describedby', error.id);
            input.focus();
        } else {
            input.removeAttribute('aria-invalid');
            input.removeAttribute('aria-describedby');
        }
    }
}

function valor(form: HTMLFormElement, nombre: string): string {
    const input = form.elements.namedItem(nombre);
    return input instanceof HTMLInputElement ? input.value : '';
}

/** Cablea el modal de fechas de la página (si lo tiene). */
export function iniciarRango(): void {
    const form = document.querySelector<HTMLFormElement>('form[data-rango-tejido]');
    const cfg = leerDatos<ConfigRango>(form, 'rangoTejido');
    if (!form || !cfg) return;

    const vistaSemana = form.querySelector<HTMLElement>('[data-semana-texto]');
    const pintarSemana = (): void => {
        if (vistaSemana) vistaSemana.textContent = textoSemana(valor(form, 'semana'));
    };

    form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        if (cfg.modo === 'semana') {
            const semana = valor(form, 'semana');
            const r = validarSemana(semana);
            if (!r.ok) return mostrarError(form, r.campo, r.mensaje);
            mostrarError(form, null);
            window.location.href = urlConsulta(cfg.ruta, { semana });
            return;
        }
        const fechaIni = valor(form, 'fecha_ini');
        const fechaFin = valor(form, 'fecha_fin');
        const r = validarRango(fechaIni, fechaFin, cfg.maxDias);
        if (!r.ok) return mostrarError(form, r.campo, r.mensaje);
        mostrarError(form, null);
        window.location.href = urlConsulta(cfg.ruta, { fecha_ini: fechaIni, fecha_fin: fechaFin });
    });

    // Al corregir, sin el error de la vez anterior.
    form.addEventListener('input', () => {
        mostrarError(form, null);
        pintarSemana();
    });
    pintarSemana();

    if (cfg.abrirAlCargar) abrir(cfg.modal);
}
