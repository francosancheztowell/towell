import { montarDiagramaFlujo } from '../../../componentes/diagrama-flujo/index.ts';
import {
    ejemploKardex,
    type DatosAristaKardex,
    type DatosNodoKardex,
} from '../../../componentes/diagrama-flujo/ejemplo-kardex.ts';
import type { AristaFlujo, NodoFlujo } from '../../../componentes/diagrama-flujo/tipos.ts';
import { escapeHtml, formatNumber } from '../../../utils/format.ts';

function tabla(filas: Array<[string, string]>): string {
    const cuerpo = filas
        .map(([k, v]) => `<tr><th class="pr-4 text-left font-semibold">${escapeHtml(k)}</th><td class="text-right tabular-nums">${escapeHtml(v)}</td></tr>`)
        .join('');

    return `<table class="w-full text-sm"><tbody>${cuerpo}</tbody></table>`;
}

function detalleNodo(nodo: NodoFlujo): void {
    const d = nodo.datos as DatosNodoKardex;
    const filas: Array<[string, string]> = [['Almacén', `${nodo.id} · ${d.nombre}`]];
    if (d.inicial !== null && d.final !== null) {
        filas.push(['Inicial', `${formatNumber(d.inicial, 0)} kg`], ['Final', `${formatNumber(d.final, 0)} kg`]);
        filas.push(['Variación', `${formatNumber(d.final - d.inicial, 0)} kg`]);
    }
    if (d.ajuste !== undefined) filas.push(['± recuentos / regresos', `${formatNumber(d.ajuste, 0)} kg`]);
    for (const a of ejemploKardex.aristas) {
        const kg = (a.datos as DatosAristaKardex).desglose.reduce((s, x) => s + x.kg, 0);
        if (a.a === nodo.id) filas.push([`Entra de ${a.de}`, `${formatNumber(kg, 0)} kg`]);
        if (a.de === nodo.id) filas.push([`Sale a ${a.a}`, `${formatNumber(kg, 0)} kg`]);
    }
    void window.notify.html(tabla(filas), nodo.id, 'info');
}

function detalleArista(arista: AristaFlujo): void {
    const d = arista.datos as DatosAristaKardex;
    const filas: Array<[string, string]> = d.desglose.map((x) => [x.concepto, `${formatNumber(x.kg, 0)} kg`]);
    void window.notify.html(tabla(filas), `${arista.de} → ${arista.a}`, 'info');
}

const contenedor = document.getElementById('kitDiagramaFlujo');
if (contenedor) {
    montarDiagramaFlujo(contenedor, ejemploKardex, { onNodo: detalleNodo, onArista: detalleArista });
}
