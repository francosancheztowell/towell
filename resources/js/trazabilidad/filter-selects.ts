import { combobox, comboboxDe, destruirCombobox } from '../utils/combobox.ts';
import type { RemotoCombobox } from '../utils/combobox.ts';

export class FilterSelects {
    private readonly root: HTMLElement;

    private readonly onChange = (event: Event): void => {
        const select = event.currentTarget as HTMLSelectElement;
        const field = select.dataset.livewireFilter || '';
        if (!field) return;

        const value = select.value || '';
        // Fuera del evento: Tom Select sigue usando su DOM después de emitir `change`.
        window.setTimeout(() => {
            this.destroy();
            window.Livewire?.dispatch('trazabilidad-actualizar-filtro', {
                campo: field,
                valor: value,
            });
        });
    };

    public constructor(root: HTMLElement) {
        this.root = root;
    }

    public init(): void {
        this.selects().forEach((select) => {
            if (!comboboxDe(select)) {
                const remoto = this.remoteSource(select);
                combobox(select, {
                    placeholder: 'Todos',
                    permitirVacio: true,
                    ...(remoto ? { remoto } : {}),
                });
            }

            select.removeEventListener('change', this.onChange);
            select.addEventListener('change', this.onChange);
        });
    }

    /**
     * Los selectores con data-remote-url no llevan sus opciones en el HTML:
     * el combobox las pide al servidor filtradas por los demás filtros activos.
     */
    private remoteSource(select: HTMLSelectElement): RemotoCombobox | null {
        const url = select.dataset.remoteUrl;
        if (!url) return null;

        const otherValue = (selector: string): string =>
            document.querySelector<HTMLInputElement | HTMLSelectElement>(selector)?.value?.trim() || '';

        return {
            url,
            params: () => ({
                articulo: otherValue('#filtro-articulo'),
                tamano: otherValue('#filtro-tamano'),
                mes: otherValue('#filtro-mes'),
            }),
        };
    }

    public destroy(): void {
        this.selects().forEach((select) => {
            select.removeEventListener('change', this.onChange);
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
