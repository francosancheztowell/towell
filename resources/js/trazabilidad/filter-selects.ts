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

