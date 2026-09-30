/**
 * Checklist BPM (BPM-Line) de un folio, una implementación para Urdido y Engomado (19-01).
 * Vista: resources/views/modulos/urdido/comun/bpm-line.blade.php (config en data-bpm-line).
 * Los botones Terminar/Autorizar/Rechazar están en el navbar (fuera de la raíz): se delegan en document.
 */
import { http } from '../../../../utils/http.ts';
import { notify } from '../../../../utils/notifications.ts';
import { delegate, onReady, qsa } from '../../../../utils/dom.ts';
import { exigirExito, leerDatos, mensajeError, type RespuestaApi } from '../pagina.ts';
import {
    CLASES_VALOR,
    ETIQUETA_VALOR,
    ICONO_VALOR,
    TODAS_LAS_CLASES_VALOR,
    mensajePendientes,
    pendientes,
    siguienteValor,
    valorDe,
} from './logica.ts';

interface ConfigBpmLine {
    variante: 'urdido' | 'engomado';
    rutas: { toggle: string; indice: string };
}

function pintar(boton: HTMLButtonElement, valor: 0 | 1 | 2): void {
    boton.dataset.valor = String(valor);
    boton.classList.remove(...TODAS_LAS_CLASES_VALOR);
    boton.classList.add(...CLASES_VALOR[valor]);
    const icono = boton.querySelector('.cell-icon');
    if (icono) icono.textContent = ICONO_VALOR[valor];
    boton.setAttribute('aria-label', `${boton.dataset.actividad ?? ''}: ${ETIQUETA_VALOR[valor]}`);
}

function iniciar(raiz: HTMLElement, cfg: ConfigBpmLine): void {
    const botones = (): HTMLButtonElement[] => qsa<HTMLButtonElement>('.cell-btn[data-actividad]', raiz);

    async function alternar(boton: HTMLButtonElement): Promise<void> {
        const nuevo = siguienteValor(valorDe(boton.dataset.valor));
        boton.disabled = true;
        try {
            exigirExito(
                await http.post<RespuestaApi>(cfg.rutas.toggle, { actividad: boton.dataset.actividad ?? '', valor: nuevo }),
                'Error al actualizar la actividad',
            );
            pintar(boton, nuevo);
        } catch (err) {
            notify.error(mensajeError(err, 'Error al actualizar la actividad'));
        } finally {
            boton.disabled = false;
        }
    }

    function terminar(boton: HTMLElement): void {
        const faltantes = pendientes(botones().map((b) => b.dataset.valor));
        if (faltantes > 0) {
            void notify.alert(mensajePendientes(faltantes), 'Actividades pendientes', 'warning');
            return;
        }
        boton.closest('form')?.submit();
    }

    delegate<HTMLButtonElement>(raiz, 'click', '.cell-btn[data-actividad]', (_e, boton) => {
        if (!boton.disabled) void alternar(boton);
    });

    delegate(document, 'click', '[data-bpm-line-accion]', (_e, boton) => {
        if (boton.dataset.bpmLineAccion === 'terminar') terminar(boton);
        else boton.closest('form')?.submit(); // autorizar / rechazar
    });

    // "Atrás" del navegador: volver al índice sin apilar páginas (y con datos frescos).
    const volverAlIndice = (): void => window.location.replace(cfg.rutas.indice);
    window.addEventListener('popstate', volverAlIndice);
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) window.location.reload();
    });
}

onReady(() => {
    const raiz = document.getElementById('bpm-line-pagina');
    const cfg = leerDatos<ConfigBpmLine>(raiz, 'bpmLine');
    if (raiz && cfg) iniciar(raiz, cfg);
});
