import { DetailLoader } from './detail-loader';
import { FilterSelects } from './filter-selects';
import { FlogDetail } from './flog-detail';
import { FlogImageViewer } from './flog-image-viewer';
import { MatrixDetail } from './matrix-detail';
import { ProductionDetail } from './production-detail';
import { RedboothLauncher } from './redbooth';
import type { TrazabilidadConfig } from './types';

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
    const imageModal = document.getElementById('modal-flog-imagen');
    const filtersRoot = document.getElementById('trazabilidad-livewire');

    if (!config || !page || !result || !imageModal || !filtersRoot) return;

    // El visor va a <body> para quedar por encima del navbar.
    document.body.appendChild(imageModal);

    const filterSelects = new FilterSelects(filtersRoot);
    const imageViewer = new FlogImageViewer(result, imageModal);
    const flogDetail = new FlogDetail(result);
    const productionDetail = new ProductionDetail(result);
    const matrixDetail = new MatrixDetail(result);

    const loader = new DetailLoader(page, result, config.rutas?.detalles || {}, {
        flogs: () => {
            imageViewer.initImages(result);
            flogDetail.render();
        },
        produccion: () => productionDetail.render(),
        trazabilidad: (response) => matrixDetail.render(response.meta),
    });

    const redbooth = new RedboothLauncher(config.rutas?.redbooth || '/trazabilidad/redbooth');

    window.addEventListener('trazabilidad-filtros-actualizados', (event) => {
        const filters = event instanceof CustomEvent
            ? (event.detail?.filtros as { flog?: string } | undefined)
            : undefined;

        redbooth.toggle(Boolean(filters?.flog));
        loader.invalidateAndClose();
    });

    filterSelects.init();
    filterSelects.bindToLivewire(filtersRoot);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
} else {
    bootstrap();
}
