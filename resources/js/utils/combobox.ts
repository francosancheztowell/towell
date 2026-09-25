/**
 * Combobox con búsqueda sobre un <select> nativo (Tom Select). Reemplaza a Select2.
 *
 *   import { combobox } from '../utils/combobox.ts';
 *   combobox(select, { placeholder: 'Todos', permitirVacio: true });
 *   combobox(select, { remoto: { url, params: () => ({ mes }) } });   // carga remota
 *   select.addEventListener('change', …);                             // eventos nativos
 *
 * Desde un <script> inline de Blade: `await window.combobox(select, opciones)`
 * (bootstrap.js carga este módulo solo cuando se usa).
 *
 * El <select> sigue siendo la fuente de verdad: `select.value` siempre está al día
 * y Tom Select dispara `input`/`change` nativos. Si cambias sus opciones o su valor
 * por código, llama `refrescarCombobox(select)`.
 *
 * Livewire: pon `wire:ignore` en el contenedor, o `destruirCombobox()` antes del
 * morph y vuelve a llamar `combobox()` después (así lo hace Trazabilidad).
 */
import TomSelect from 'tom-select/base';
import clearButton from 'tom-select/plugins/clear_button/plugin.js';
import removeButton from 'tom-select/plugins/remove_button/plugin.js';
import 'tom-select/dist/css/tom-select.default.css';
import './combobox.css';

import http from './http.ts';
import {
    TEXTOS_COMBOBOX,
    cargadorRemoto,
    plantillas,
    type RemotoCombobox,
    type TextosCombobox,
} from './combobox-opciones.ts';

TomSelect.define('clear_button', clearButton);
TomSelect.define('remove_button', removeButton);

export type { RemotoCombobox, TextosCombobox };

export interface OpcionesCombobox {
    placeholder?: string;
    /** Botón para limpiar la selección (el allowClear de Select2). */
    permitirVacio?: boolean;
    /** Varias opciones; por defecto, lo que diga `select.multiple`. */
    multiple?: boolean;
    /** Cargar las opciones del servidor al buscar (y al abrir). */
    remoto?: RemotoCombobox;
    /**
     * Monta la lista en <body> por encima de todo. Úsalo dentro de modales o
     * contenedores con overflow que la recortarían (popup de Swal, Redbooth).
     */
    flotante?: boolean;
    textos?: Partial<TextosCombobox>;
}

export type Combobox = TomSelect;

type SelectConCombobox = HTMLSelectElement & { tomselect?: TomSelect };

/** Crea el combobox, o devuelve el que ya tenga el select (idempotente). */
export function combobox(select: HTMLSelectElement, opciones: OpcionesCombobox = {}): Combobox {
    const existente = (select as SelectConCombobox).tomselect;
    if (existente) return existente;

    const textos = { ...TEXTOS_COMBOBOX, ...opciones.textos };
    const multiple = opciones.multiple ?? select.multiple;
    const plugins = [
        ...(opciones.permitirVacio && !multiple ? ['clear_button'] : []),
        ...(multiple ? ['remove_button'] : []),
    ];
    const clasesDelSelect = [...select.classList];

    const cargar = opciones.remoto ? cargadorRemoto(opciones.remoto, http.get) : null;

    const ajustes: Record<string, unknown> = {
        plugins,
        maxItems: multiple ? null : 1,
        maxOptions: null,
        placeholder: opciones.placeholder ?? select.dataset.placeholder ?? '',
        hidePlaceholder: false,
        allowEmptyOption: false,
        render: plantillas(textos, cargar?.fallo),
    };

    if (cargar) {
        Object.assign(ajustes, {
            load: cargar,
            preload: 'focus',
            loadThrottle: 250,
            // Buscar también con la caja vacía (minimumInputLength: 0 de Select2).
            shouldLoad: () => true,
            // El servidor ya filtró: mostrar lo que devolvió, en su orden.
            score: () => () => 1,
        });
    }

    if (opciones.flotante) {
        ajustes.dropdownParent = 'body';
        ajustes.dropdownClass = 'ts-dropdown combobox-flotante';
    }

    const instancia = new TomSelect(select, ajustes as ConstructorParameters<typeof TomSelect>[1]);

    // Tom Select copia las clases del select al contenedor. Aquí el aspecto lo da
    // combobox.css; con las clases de Tailwind del select saldría un doble borde, y
    // un querySelectorAll('.clase-del-select') devolvería también el contenedor.
    instancia.wrapper.classList.remove(...clasesDelSelect);
    instancia.wrapper.classList.add('combobox');

    if (opciones.flotante) reposicionarAlDesplazar(instancia);

    return instancia;
}

const flotantes = new Set<TomSelect>();

/**
 * La lista flotante vive en <body>: si el modal que tenía el select se cerró
 * (Swal quita su DOM), la lista quedaría huérfana. Se barren al crear otra.
 */
function barrerFlotantesHuerfanos(): void {
    flotantes.forEach((instancia) => {
        if (instancia.wrapper.isConnected) return;
        flotantes.delete(instancia);
        instancia.destroy();
    });
}

/** Tom Select solo reubica la lista flotante al hacer scroll en window; aquí, en cualquier contenedor. */
function reposicionarAlDesplazar(instancia: TomSelect): void {
    barrerFlotantesHuerfanos();
    flotantes.add(instancia);
    instancia.on('destroy', () => flotantes.delete(instancia));
    const reubicar = (): void => instancia.positionDropdown();
    instancia.on('dropdown_open', () => document.addEventListener('scroll', reubicar, { capture: true, passive: true }));
    instancia.on('dropdown_close', () => document.removeEventListener('scroll', reubicar, { capture: true }));
    instancia.on('destroy', () => document.removeEventListener('scroll', reubicar, { capture: true }));
}

/** La instancia del select, si tiene. */
export function comboboxDe(select: Element | null | undefined): Combobox | undefined {
    return (select as SelectConCombobox | null | undefined)?.tomselect;
}

/** Relee opciones y valor del <select> tras cambiarlos por código. No dispara `change`. */
export function refrescarCombobox(select: Element | null | undefined): void {
    comboboxDe(select)?.sync();
}

/** Quita el combobox y deja el <select> como estaba al crearlo. */
export function destruirCombobox(select: Element | null | undefined): void {
    comboboxDe(select)?.destroy();
}

export default combobox;
