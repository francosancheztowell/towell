import { errorMessage, queryElement } from './dom';
import type { RedboothOrder, RedboothResponse } from './types';

export class RedboothLauncher {
    private readonly route: string;
    private busy = false;

    public constructor(route: string) {
        this.route = route;
        queryElement<HTMLButtonElement>('#btn-redbooth')
            ?.addEventListener('click', () => void this.openForSelectedFlog());
    }

    /** El botón vive en un contenedor: flux:button trae su propio display. */
    public toggle(hasFlog: boolean): void {
        queryElement<HTMLElement>('[data-redbooth]')?.classList.toggle('hidden', !hasFlog);
    }

    private async openForSelectedFlog(): Promise<void> {
        const flog = queryElement<HTMLSelectElement>('#filtro-flog')?.value?.trim() || '';
        if (!flog) {
            window.notify?.warning('Selecciona un Flog para consultar Redbooth.');
            return;
        }
        if (this.busy) return;

        const button = queryElement<HTMLButtonElement>('#btn-redbooth');
        this.busy = true;
        if (button) button.disabled = true;

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
            await window.notify?.alert(
                errorMessage(error, 'Ocurrió un error al buscar las órdenes del Flog.'),
                'No se pudo consultar Redbooth',
                'error',
            );
        } finally {
            this.busy = false;
            if (button) button.disabled = false;
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
