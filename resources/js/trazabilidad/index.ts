import { DetailLoader } from './detail-loader';
import { FilterSelects } from './filter-selects';
import { FlogDetail } from './flog-detail';
import { FlogImageViewer } from './flog-image-viewer';
import { MatrixDetail } from './matrix-detail';
import { ProductionDetail } from './production-detail';
import { RedboothLauncher } from './redbooth';
import { ScrollManager } from './scroll-manager';
import type { TrazabilidadConfig } from './types';

function moveToBody(id: string): HTMLElement | null {
    const element = document.getElementById(id);
    if (element && element.parentElement !== document.body) document.body.appendChild(element);

    return element;
}

function readConfig(): TrazabilidadConfig | null {
    const node = document.getElementById('trazabilidad-config');
    if (!node) return null;

    try {
        return JSON.parse(node.textContent || '{}') as TrazabilidadConfig;
    } catch {
        window.notify?.error('La configuración de Trazabilidad no es válida.');
        return null;
    }
}

function bootstrap(): void {
    const config = readConfig();
    const page = document.querySelector<HTMLElement>('.trazabilidad-page');
    const result = document.getElementById('resultado-detalle');
    const flogImageModal = moveToBody('modal-flog-imagen');
    const rollosModal = moveToBody('modal-rollos-maquina');

    if (!config || !page || !result || !flogImageModal || !rollosModal) return;

    const scroll = new ScrollManager();
    const filterSelects = new FilterSelects(
        document.getElementById('trazabilidad-livewire') || page,
    );
    const imageViewer = new FlogImageViewer(result, flogImageModal, scroll);
    const flogDetail = new FlogDetail(result);
    const productionDetail = new ProductionDetail(result, rollosModal, scroll);
    const matrixDetail = new MatrixDetail(result);

    const loader = new DetailLoader(
        page,
        result,
        config.rutas?.detalles || {},
        {
            flogs: () => {
                imageViewer.initImages(result);
                flogDetail.render();
            },
            produccion: () => productionDetail.render(),
            trazabilidad: (response) => matrixDetail.render(response.meta),
        },
        scroll,
    );

    const redboothButton = document.querySelector<HTMLButtonElement>('#btn-redbooth');
    const redbooth = redboothButton
        ? new RedboothLauncher(
            redboothButton,
            config.rutas?.redbooth || '/trazabilidad/redbooth',
        )
        : null;

    document.querySelector<HTMLButtonElement>('#btn-restablecer')
        ?.addEventListener('click', () => {
            filterSelects.destroy();
            window.Livewire?.dispatch('trazabilidad-restablecer');
        });

    window.addEventListener('trazabilidad-filtros-actualizados', (event) => {
        const filters = event instanceof CustomEvent
            ? (event.detail?.filtros as { flog?: string } | undefined)
            : undefined;

        redbooth?.toggle(Boolean(filters?.flog));
        loader.invalidateAndClose();
        window.requestAnimationFrame(() => filterSelects.init());
    });

    filterSelects.init();
    scroll.bindRecovery();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
} else {
    bootstrap();
}
