/**
 * Programación de Requerimientos (Programa Urd-Eng, 19-05).
 * Vista: resources/views/modulos/programa_urd_eng/programacion-requerimientos.blade.php
 *
 * Llega desde reservar-programar (?telares= y sessionStorage, ver comun/contrato-flujo.ts),
 * se capturan tamaño/hilo/urdido/metros por grupo de cuenta y se sigue a Creación de Órdenes.
 */
import { delegate, onReady } from '../../../utils/dom.ts';
import { leerDatos } from '../../urdido/comun/pagina.ts';
import { CLAVE_SESION_TELARES, PARAM_TELARES, parsearTelares, type TelarSeleccionado } from '../comun/contrato-flujo.ts';
import type { ConfigPagina, Dom, Estado } from './estado.ts';
import {
    agruparPorCuenta,
    filtrarPorGrupo,
    formatearNumeroInput,
    kilosProgramados,
    limpiarNumeroInput,
    normalizarEntrada,
    soloCaracteresNumericos,
    validarGrupo,
} from './logica.ts';
import { cargarResumen, pintarMensajeResumen } from './resumen.ts';
import { cargarCatalogos } from './servidor.ts';
import { actualizarBotonSiguiente, cambiarHilo, cambiarTipo, continuar, pintarErrorValidacion, pintarFilas, pintarVacio } from './tabla.ts';
import { instalarTamano, rellenarCuentaYCalibre } from './tamano.ts';

/** El servidor ya parsea ?telares=; si no llegó nada, se intenta la query y luego sessionStorage. */
function telaresDeEntrada(cfg: ConfigPagina): TelarSeleccionado[] {
    if (Array.isArray(cfg.telares) && cfg.telares.length) return cfg.telares;
    let crudo = new URLSearchParams(location.search).get(PARAM_TELARES);
    try {
        crudo ||= sessionStorage.getItem(CLAVE_SESION_TELARES);
        if (crudo) sessionStorage.removeItem(CLAVE_SESION_TELARES);
    } catch {
        // sessionStorage bloqueado (modo privado): solo queda la query.
    }
    return parsearTelares(crudo);
}

function pintarTabla(estado: Estado, dom: Dom): void {
    if (!estado.telares.length) {
        pintarVacio(dom);
        pintarMensajeResumen(dom, 'No hay telares seleccionados para mostrar el resumen.');
        return;
    }

    const v = validarGrupo(estado.telares);
    if (!v.valido) {
        pintarErrorValidacion(dom, v.mensaje);
        pintarMensajeResumen(dom, 'No se puede cargar el resumen por error de validación.');
        return;
    }

    estado.telares = filtrarPorGrupo(estado.telares, v);
    estado.validacion = v;
    pintarFilas(estado, dom, agruparPorCuenta(estado.telares));
    // Cuenta y calibre se derivan del tamaño que ya traiga la fila (sin guardar).
    for (const fila of dom.cuerpo.querySelectorAll<HTMLElement>('tr[data-index]')) void rellenarCuentaYCalibre(estado, fila, false);
    void cargarResumen(estado, dom);
}

/** Metros: sin formato al editar, recalcula kilos al teclear, formato al salir (solo si hay resumen del telar). */
function instalarMetros(estado: Estado, dom: Dom): void {
    const sel = 'input[data-field="metros"]';
    const contexto = (input: HTMLInputElement) => {
        const fila = input.closest<HTMLElement>('tr');
        const totales = estado.porTelar.get(fila?.dataset.telarId ?? '');
        if (!fila || !totales || totales.totalMetros <= 0) return null;
        return { totales, kilos: fila.querySelector<HTMLInputElement>('input[data-field="kilos"]') };
    };
    const recalcular = (input: HTMLInputElement, metros: string) => {
        const ctx = contexto(input);
        if (!ctx?.kilos) return;
        const kilos = kilosProgramados(ctx.totales, Number(metros) || 0);
        ctx.kilos.value = kilos > 0 ? formatearNumeroInput(kilos) : '';
    };

    delegate<HTMLInputElement>(dom.cuerpo, 'focusin', sel, (_e, input) => {
        if (contexto(input)) input.value = limpiarNumeroInput(input.value);
    });
    delegate<HTMLInputElement>(dom.cuerpo, 'input', sel, (_e, input) => {
        if (!contexto(input)) return;
        input.value = soloCaracteresNumericos(input.value);
        recalcular(input, limpiarNumeroInput(input.value));
    });
    delegate<HTMLInputElement>(dom.cuerpo, 'focusout', sel, (_e, input) => {
        const valor = limpiarNumeroInput(input.value);
        if (!contexto(input) || !valor) return;
        input.value = formatearNumeroInput(valor);
        recalcular(input, valor);
    });
}

async function iniciar(): Promise<void> {
    const raiz = document.getElementById('pagina-programacion-requerimientos');
    const cfg = leerDatos<ConfigPagina>(raiz);
    const cuerpo = document.getElementById('tbodyRequerimientos') as HTMLTableSectionElement | null;
    const resumen = document.getElementById('tbodyResumen') as HTMLTableSectionElement | null;
    const encabezadoResumen = document.getElementById('theadResumen');
    if (!raiz || !cfg || !cuerpo || !resumen || !encabezadoResumen) return;

    const dom: Dom = {
        raiz,
        cuerpo,
        resumen,
        encabezadoResumen,
        siguiente: document.getElementById('btnSiguiente') as HTMLButtonElement | null,
    };
    const estado: Estado = {
        cfg,
        telares: normalizarEntrada(telaresDeEntrada(cfg)),
        grupos: [],
        hilos: {},
        tamanos: {},
        validacion: null,
        porTelar: new Map(),
    };

    delegate<HTMLSelectElement>(cuerpo, 'change', 'select[data-field="hilo"]', (_e, s) => void cambiarHilo(estado, s));
    delegate<HTMLSelectElement>(cuerpo, 'change', 'select[data-field="tipo"]', (_e, s) => void cambiarTipo(estado, s));
    instalarTamano(estado, dom);
    instalarMetros(estado, dom);
    const refrescarBoton = () => actualizarBotonSiguiente(estado, dom);
    cuerpo.addEventListener('input', refrescarBoton);
    cuerpo.addEventListener('change', refrescarBoton);
    dom.siguiente?.addEventListener('click', () => continuar(estado, dom));
    delegate(raiz, 'click', '[data-accion="reintentar-resumen"]', () => void cargarResumen(estado, dom));

    await cargarCatalogos(estado);
    pintarTabla(estado, dom);
    refrescarBoton();
}

onReady(() => void iniciar());
