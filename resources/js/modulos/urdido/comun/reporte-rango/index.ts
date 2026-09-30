/**
 * Modal "Consultar en rango" de los reportes de Urdido/Engomado (19-01).
 * Vista: resources/views/modulos/urdido/comun/reporte-rango.blade.php (x-ui.modal-base).
 * El botón del navbar lo abre con data-ui-modal-open; aquí: validar, navegar y abrir solo
 * cuando la página llega sin fechas.
 */
import { abrir } from '../../../../componentes/dialog.ts';
import { onReady } from '../../../../utils/dom.ts';
import { leerDatos } from '../pagina.ts';
import { faltanFechas, urlConsulta, validarRango } from './logica.ts';

interface ConfigRango {
    ruta: string;
    conCheckbox: boolean;
    /** Fechas con las que llegó la página ('' si no vinieron en la URL). */
    fechaIni: string;
    fechaFin: string;
}

const ID_MODAL = 'modalReporteRango';

function mostrarError(form: HTMLFormElement, campo: string | null, mensaje = ''): void {
    for (const nombre of ['fecha_ini', 'fecha_fin']) {
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

function iniciar(): void {
    const form = document.querySelector<HTMLFormElement>('form[data-reporte-rango]');
    const config = leerDatos<ConfigRango>(form, 'reporteRango');
    if (!form || !config) return;

    form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const fechaIni = valor(form, 'fecha_ini');
        const fechaFin = valor(form, 'fecha_fin');
        const r = validarRango(fechaIni, fechaFin);
        if (!r.ok) {
            mostrarError(form, r.campo, r.mensaje);
            return;
        }
        mostrarError(form, null);
        const solo = form.elements.namedItem('solo_finalizados');
        window.location.href = urlConsulta(config.ruta, {
            fechaIni,
            fechaFin,
            soloFinalizados: config.conCheckbox && solo instanceof HTMLInputElement ? solo.checked : undefined,
        });
    });

    // Al reabrir, sin el error de la vez anterior.
    form.addEventListener('input', () => mostrarError(form, null));

    // Sin rango todavía: el modal se abre solo, con hoy precargado por el Blade.
    if (faltanFechas(config.fechaIni, config.fechaFin)) abrir(ID_MODAL);
}

onReady(iniciar);
