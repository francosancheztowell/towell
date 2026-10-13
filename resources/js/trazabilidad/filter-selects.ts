import { combobox, comboboxDe, destruirCombobox } from '../utils/combobox.ts';
import type { RemotoCombobox } from '../utils/combobox.ts';

interface CommitHook {
    component: { el: HTMLElement };
    succeed: (callback: () => void) => void;
    fail: (callback: () => void) => void;
}

export class FilterSelects {
    private readonly root: HTMLElement;

    private readonly onChange = (event: Event): void => {
        const select = event.currentTarget as HTMLSelectElement;
        // Múltiple mientras se escribe: cada petición desmonta Tom Select y se pierde el foco
        // (no se podía teclear 7160,7120). Se manda al salir del campo; la ✕ sin foco, al momento.
        if (select.multiple && comboboxDe(select)?.isFocused) return;
        this.send(select);
    };

    /** Avisa a Livewire solo si el valor cambió desde el último que conoce el servidor. */
    private send(select: HTMLSelectElement): void {
        const field = select.dataset.livewireFilter || '';
        const value = valorDe(select);
        const key = JSON.stringify(value);
        if (!field || key === select.dataset.enviado) return;
        select.dataset.enviado = key;

        // Fuera del evento: Tom Select sigue usando su DOM después de emitir `change`.
        window.setTimeout(() => {
            window.Livewire?.dispatch('trazabilidad-actualizar-filtro', { campo: field, valor: value });
        });
    }

    public constructor(root: HTMLElement) {
        this.root = root;
    }

    public init(): void {
        this.selects().forEach((select) => {
            // data-bloqueado: Mes sin Año.
            select.disabled = select.hasAttribute('data-bloqueado');
            if (!comboboxDe(select)) {
                // Lo que llegó del servidor: send() compara contra esto.
                select.dataset.enviado = JSON.stringify(valorDe(select));
                const remoto = this.remoteSource(select);
                const instancia = combobox(select, {
                    placeholder: select.disabled ? 'Elige año' : 'Todos',
                    permitirVacio: true,
                    // data-ax: se escribe o pega como en AX (7166,7167 · 7160..7170 · 71*).
                    crear: select.hasAttribute('data-ax'),
                    casillas: true,
                    chipConValor: true,
                    ...(remoto ? { remoto } : {}),
                });
                if (select.multiple) instancia.on('blur', () => this.send(select));
            }

            select.removeEventListener('change', this.onChange);
            select.addEventListener('change', this.onChange);
        });
    }

    /**
     * Livewire reemplaza los <select> al redibujar: Tom Select se retira antes de cada petición
     * del componente y vuelve después, también si la petición falla (antes quedaba destruido).
     */
    public bindToLivewire(component: HTMLElement): void {
        const bind = (): void => window.Livewire?.hook('commit', ({ component: target, succeed, fail }: CommitHook) => {
            if (target.el !== component) return;

            // Deshabilitados mientras viaja la petición: un cambio en ese lapso se perdería.
            this.destroy();
            succeed(() => queueMicrotask(() => this.init()));
            fail(() => this.init());
        });

        if (window.Livewire) {
            bind();
        } else {
            document.addEventListener('livewire:init', bind, { once: true });
        }
    }

    /**
     * Los selectores con data-remote-url no llevan sus opciones en el HTML:
     * el combobox las pide al servidor filtradas por los demás filtros activos.
     */
    private remoteSource(select: HTMLSelectElement): RemotoCombobox | null {
        const url = select.dataset.remoteUrl;
        if (!url) return null;

        const otherValue = (selector: string): string | string[] => {
            const other = this.root.querySelector<HTMLSelectElement>(selector);
            return other ? valorDe(other) : '';
        };

        return {
            url,
            params: () => ({
                articulo: otherValue('#filtro-articulo'),
                tamano: otherValue('#filtro-tamano'),
                cliente: otherValue('#filtro-cliente'),
                anio: otherValue('#filtro-anio'),
                mes: otherValue('#filtro-mes'),
            }),
        };
    }

    private destroy(): void {
        this.selects().forEach((select) => {
            select.removeEventListener('change', this.onChange);
            select.disabled = true;
            try {
                destruirCombobox(select);
            } catch {
                // Livewire puede haber retirado ya el nodo durante el morph.
            }
        });
    }

    private selects(): HTMLSelectElement[] {
        return [...this.root.querySelectorAll<HTMLSelectElement>('select.filtro-select')];
    }
}

/**
 * Valor de un filtro como lo espera el servidor: Flog/Artículo/Tamaño (data-ax) como
 * texto AX con comas; Cliente como lista (los nombres traen comas); el resto, el value.
 */
export function valorDe(select: HTMLSelectElement): string | string[] {
    if (!select.multiple) return select.value.trim();

    const valores = [...select.selectedOptions].map((option) => option.value.trim()).filter(Boolean);
    return select.hasAttribute('data-ax') ? valores.join(',') : valores;
}
