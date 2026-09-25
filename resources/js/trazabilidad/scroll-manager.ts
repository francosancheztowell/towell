import { comboboxDe } from '../utils/combobox.ts';
import { isOpen, queryElement } from './dom';

export class ScrollManager {
    private readonly main = queryElement<HTMLElement>('main.app-main');

    public release(): void {
        if (this.main) {
            this.main.style.overflowY = 'auto';
        }
    }

    public restoreInteraction(): void {
        document.querySelectorAll<HTMLSelectElement>('select.filtro-select').forEach((select) => {
            try {
                comboboxDe(select)?.close();
            } catch {
                // El componente Livewire puede estar reemplazando el select.
            }
        });

        const active = document.activeElement;
        // La caja de búsqueda vive en la lista (.ts-dropdown, en <body>), no en el control.
        if (active instanceof HTMLElement && active.closest('.ts-wrapper, .ts-dropdown')) {
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

    /** Bloquea o libera el scroll del contenedor según haya modales abiertos. */
    public sync(): void {
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

