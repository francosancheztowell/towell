import { isOpen, queryElement } from './dom';

export class ScrollManager {
    private readonly main = queryElement<HTMLElement>('main.app-main');

    public lock(): void {
        this.sync();
    }

    public unlock(): void {
        this.sync();
    }

    public release(): void {
        if (this.main) {
            this.main.style.overflowY = 'auto';
        }
    }

    public restoreInteraction(): void {
        const jquery = window.jQuery;

        document.querySelectorAll<HTMLElement>('.filtro-select').forEach((select) => {
            if (!jquery) return;

            const bridge = jquery(select);
            if (!bridge.data('select2')) return;

            try {
                bridge.select2('close');
            } catch {
                // El componente Livewire puede estar reemplazando el select.
            }
        });

        document.querySelectorAll('.select2-container--open')
            .forEach((container) => container.classList.remove('select2-container--open'));

        const active = document.activeElement;
        if (
            active instanceof HTMLElement
            && (
                active.classList.contains('select2-search__field')
                || active.closest('.select2-container')
            )
        ) {
            active.blur();
        }

        this.sync();
        this.release();
    }

    public bindRecovery(): void {
        this.release();
        window.addEventListener('pageshow', () => this.release());
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) this.restoreInteraction();
        });
        document.addEventListener('wheel', () => {
            if (!this.hasOpenModal() && this.main?.style.overflowY === 'hidden') {
                this.restoreInteraction();
            }
        }, { passive: true, capture: true });
    }

    private sync(): void {
        if (this.main) {
            this.main.style.overflowY = this.hasOpenModal() ? 'hidden' : 'auto';
        }
    }

    private hasOpenModal(): boolean {
        return [
            queryElement<HTMLElement>('#modal-rollos-maquina'),
            queryElement<HTMLElement>('#modal-flog-imagen'),
            queryElement<HTMLElement>('#modal-resumen-telares'),
        ].some(isOpen);
    }
}

