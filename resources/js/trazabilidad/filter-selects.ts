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
        const field = select.dataset.livewireFilter || '';
        if (!field) return;

        const value = select.value || '';
        // Fuera del evento: Tom Select sigue usando su DOM después de emitir `change`.
        window.setTimeout(() => {
            window.Livewire?.dispatch('trazabilidad-actualizar-filtro', { campo: field, valor: value });
        });
    };

    public constructor(root: HTMLElement) {
        this.root = root;
    }

    public init(): void {
        this.selects().forEach((select) => {
            select.disabled = false;
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

        const otherValue = (selector: string): string =>
            this.root.querySelector<HTMLSelectElement>(selector)?.value?.trim() || '';

        return {
            url,
            params: () => ({
                articulo: otherValue('#filtro-articulo'),
                tamano: otherValue('#filtro-tamano'),
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
