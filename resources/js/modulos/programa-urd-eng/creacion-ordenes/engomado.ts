/** Tabla 5 "Datos de Engomado": catálogos de los selects, metraje por tela y lectura del formulario. */
import { http } from '../../../utils/http.ts';
import { el } from '../../urdido/comun/pagina.ts';
import { filaActual, rutas } from './estado.ts';
import { conQuery, enBlanco, metrajePorTela } from './logica.ts';
import type { CampoEngomado, DatosEngomadoCrudos } from './logica.ts';

interface RespuestaLista<T> {
    success?: boolean;
    data?: T[];
}

/** Id del control de cada campo (mismos ids que la vista anterior). */
export const ID_CAMPO: Readonly<Record<CampoEngomado, string>> = {
    nucleo: 'inputNucleo',
    noTelas: 'inputNoTelas',
    anchoBalonas: 'inputAnchoBalonas',
    metrajeTelas: 'inputMetrajeTelas',
    cuendeadosMin: 'inputCuendeadosMin',
    maquinaEngomado: 'inputMaquinaEngomado',
    lMatEngomado: 'inputLMatEngomado',
    bomFormula: 'inputBomFormula',
    observaciones: 'inputObservaciones',
};

type Control = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

export function control(campo: CampoEngomado): Control | null {
    const nodo = document.getElementById(ID_CAMPO[campo]);
    return nodo instanceof HTMLInputElement || nodo instanceof HTMLSelectElement || nodo instanceof HTMLTextAreaElement
        ? nodo
        : null;
}

const lista = <T>(r: RespuestaLista<T> | null | undefined): T[] => (r && r.success && Array.isArray(r.data) ? r.data : []);

function llenarSelect(select: HTMLSelectElement, opciones: Array<[valor: string, texto: string]>, primera = 'Seleccione'): void {
    select.replaceChildren(
        el('option', { texto: primera, attrs: { value: '' } }),
        ...opciones.map(([valor, texto]) => el('option', { texto, attrs: { value: valor } })),
    );
}

export async function cargarAnchosBalona(cuenta: unknown, tipo: unknown): Promise<void> {
    const select = control('anchoBalonas');
    if (!(select instanceof HTMLSelectElement)) return;
    const query: Record<string, string> = {};
    if (!enBlanco(cuenta)) query.cuenta = String(cuenta).trim();
    if (!enBlanco(tipo)) query.tipo = String(tipo).trim();
    try {
        const items = lista(await http.get<RespuestaLista<{ anchoBalona?: string | number | null }>>(conQuery(rutas().anchosBalona, query)));
        llenarSelect(
            select,
            items.map((it) => {
                const valor = it.anchoBalona != null ? String(it.anchoBalona) : '';
                return [valor, valor || '(Sin valor)'];
            }),
        );
        const primero = items[0];
        if (primero && primero.anchoBalona != null) select.value = String(primero.anchoBalona);
    } catch {
        llenarSelect(select, [], 'Seleccione (error al cargar)');
        select.value = '';
    }
}

export async function cargarMaquinasEngomado(): Promise<void> {
    const select = control('maquinaEngomado');
    if (!(select instanceof HTMLSelectElement)) return;
    try {
        const items = lista(await http.get<RespuestaLista<{ maquinaId?: string | null; nombre?: string | null }>>(rutas().maquinasEngomado));
        llenarSelect(select, items.map((it) => [it.maquinaId || '', it.nombre || it.maquinaId || '']));
        if (items[0]?.maquinaId) select.value = items[0].maquinaId;
    } catch {
        // sin catálogo: el select queda con "Seleccione" y la validación lo pide
    }
}

export async function cargarNucleos(): Promise<void> {
    const select = control('nucleo');
    if (!(select instanceof HTMLSelectElement)) return;
    try {
        const items = lista(
            await http.get<RespuestaLista<{ value?: string | null; text?: string | null; nombre?: string | null }>>(rutas().nucleos),
        );
        llenarSelect(select, items.map((it) => [it.value || it.nombre || '', it.text || it.nombre || '']));
    } catch {
        // sin catálogo: el select queda con "Seleccione" y la validación lo pide
    }
}

/** Metraje de telas = metros del grupo seleccionado / No. de telas. */
export function actualizarMetrajeTelas(): void {
    const actual = filaActual();
    const destino = control('metrajeTelas');
    if (!actual || !destino) return;
    destino.value = metrajePorTela(actual.grupo.metros, control('noTelas')?.value);
}

export function leerDatosEngomado(): DatosEngomadoCrudos {
    const valor = (campo: CampoEngomado): string => control(campo)?.value || '';
    return {
        nucleo: valor('nucleo'),
        noTelas: valor('noTelas'),
        anchoBalonas: valor('anchoBalonas'),
        metrajeTelas: valor('metrajeTelas'),
        cuendeadosMin: valor('cuendeadosMin'),
        maquinaEngomado: valor('maquinaEngomado'),
        lMatEngomado: valor('lMatEngomado'),
        bomFormula: valor('bomFormula'),
        observaciones: valor('observaciones'),
    };
}

export function enfocarCampo(campo: CampoEngomado): void {
    const c = control(campo);
    if (!c) return;
    c.focus();
    c.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
