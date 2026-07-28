import { errorMessage, queryElement } from './dom';
import type { RedboothOrder, RedboothResponse } from './types';

export class RedboothLauncher {
    public constructor(
        private readonly button: HTMLButtonElement,
        private readonly route: string,
    ) {
        this.button.addEventListener('click', () => void this.openForSelectedFlog());
    }

    public toggle(hasFlog: boolean): void {
        this.button.classList.toggle('hidden', !hasFlog);
        this.button.classList.toggle('flex', hasFlog);
    }

    private async openForSelectedFlog(): Promise<void> {
        const flog = queryElement<HTMLSelectElement>('#filtro-flog')?.value?.trim() || '';
        if (!flog) {
            window.notify?.warning('Selecciona un Flog para consultar Redbooth.');
            return;
        }

        const icon = queryElement<HTMLElement>('i', this.button);
        this.button.disabled = true;
        icon?.classList.remove('fa-comments');
        icon?.classList.add('fa-circle-notch', 'fa-spin');

        try {
            const data = await window.http.get<RedboothResponse>(this.route, { params: { flog } });
            const orders = Array.isArray(data.ordenes) ? data.ordenes : [];
            if (orders.length === 0) {
                window.notify?.warning('No se encontraron órdenes vinculadas a este Flog.');
                return;
            }

            if (data.primerVinculo) {
                this.openRecord(data.primerVinculo);
                return;
            }

            const available = orders.filter((order) => Number(order.registroId || 0) > 0);
            if (available.length === 0) {
                window.notify?.warning('Las órdenes del Flog no existen en Programa Tejido ni en CatCodificados.');
                return;
            }

            this.openRecord({
                ...available[0],
                flogAsignacion: flog,
                totalOrdenes: available.length,
            });
        } catch (error) {
            await window.Swal?.fire({
                icon: 'error',
                title: 'No se pudo consultar Redbooth',
                text: errorMessage(error, 'Ocurrió un error al buscar las órdenes del Flog.'),
            });
        } finally {
            this.button.disabled = false;
            icon?.classList.remove('fa-circle-notch', 'fa-spin');
            icon?.classList.add('fa-comments');
        }
    }

    private openRecord(order: RedboothOrder): void {
        const recordId = Number(order.registroId || 0);
        if (!recordId || typeof window.abrirModalRedboothProgramaTejido !== 'function') {
            window.notify?.warning('La orden no tiene un registro de Programa Tejido o CatCodificados disponible.');
            return;
        }

        window.abrirModalRedboothProgramaTejido({
            registroId: recordId,
            source: order.source === 'catcodificados' ? 'catcodificados' : 'programa',
            flogAsignacion: order.flogAsignacion || '',
            totalOrdenes: Number(order.totalOrdenes || 0),
        });
    }
}

