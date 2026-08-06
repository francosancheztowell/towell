export class FilterSelects {
    private readonly namespace = '.trazabilidadLivewire';

    public constructor(private readonly root: HTMLElement) {}

    public init(): void {
        const jquery = window.jQuery;
        if (!jquery) return;

        this.root.querySelectorAll<HTMLSelectElement>('.filtro-select').forEach((select) => {
            const bridge = jquery(select);
            if (!bridge.data('select2')) {
                bridge.select2({
                    width: '100%',
                    placeholder: 'Todos',
                    allowClear: true,
                    dropdownCssClass: 'traza-select2-dd',
                    ...this.remoteSource(select),
                });
            }

            bridge.off(this.namespace).on(`change${this.namespace}`, () => {
                const field = select.dataset.livewireFilter || '';
                if (!field) return;

                const value = select.value || '';
                this.destroy();
                window.Livewire?.dispatch('trazabilidad-actualizar-filtro', {
                    campo: field,
                    valor: value,
                });
            });
        });
    }

    /**
     * Los selectores con data-remote-url no llevan sus opciones en el HTML:
     * select2 las pide al servidor filtradas por los demás filtros activos.
     */
    private remoteSource(select: HTMLSelectElement): Record<string, unknown> {
        const url = select.dataset.remoteUrl;
        if (!url) return {};

        const otherValue = (selector: string): string =>
            document.querySelector<HTMLInputElement | HTMLSelectElement>(selector)?.value?.trim() || '';

        return {
            minimumInputLength: 0,
            ajax: {
                url,
                dataType: 'json',
                delay: 250,
                cache: true,
                data: (params: { term?: string }) => ({
                    q: params.term || '',
                    articulo: otherValue('#filtro-articulo'),
                    tamano: otherValue('#filtro-tamano'),
                    mes: otherValue('#filtro-mes'),
                }),
            },
        };
    }

    public destroy(): void {
        const jquery = window.jQuery;
        if (!jquery) return;

        this.root.querySelectorAll<HTMLSelectElement>('.filtro-select').forEach((select) => {
            const bridge = jquery(select);
            bridge.off(this.namespace);
            if (!bridge.data('select2')) return;

            try {
                bridge.select2('destroy');
            } catch {
                // Livewire puede haber retirado ya el nodo durante el morph.
            }
        });
    }
}

