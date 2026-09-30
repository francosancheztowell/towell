/**
 * Catálogos de atadores (Actividades, Comentarios, Máquinas): una vista, un módulo.
 * La configuración llega de PHP en data-catalogo (CatalogosAtadoresVista).
 */
import { CatalogBase, elementosDesde, type CatalogoConfig, type Registro } from '../../catalogos/catalog-base.ts';
import { capturarGuardado, type CapturaGuardado } from './guardado.ts';

/** Pinta la fila con lo que respondió el servidor (trae el Id de Comentarios); si no mandó nada, lo de siempre. */
class CatalogoAtadores extends CatalogBase {
    captura: CapturaGuardado | null = null;

    async leerGuardado(enviado: Registro): Promise<Registro> {
        return this.captura?.tomar() ?? super.leerGuardado(enviado);
    }
}

const raiz = document.querySelector<HTMLElement>('[data-catalogo]');
const elementos = raiz ? elementosDesde(raiz) : null;

if (raiz && elementos) {
    const config = JSON.parse(raiz.dataset.catalogo ?? '{}') as CatalogoConfig;
    const captura = capturarGuardado(window.http);
    const catalogo = new CatalogoAtadores(config, elementos, { http: captura.http, notify: window.notify });
    catalogo.captura = captura;
}
