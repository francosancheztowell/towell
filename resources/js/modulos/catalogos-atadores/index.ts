/**
 * Catálogos de atadores (Actividades, Comentarios, Máquinas): una vista, un módulo.
 * La configuración llega de PHP en data-catalogo (CatalogosAtadoresVista).
 */
import { CatalogBase, elementosDesde, type CatalogoConfig } from '../../catalogos/catalog-base.ts';

const raiz = document.querySelector<HTMLElement>('[data-catalogo]');
const elementos = raiz ? elementosDesde(raiz) : null;

if (raiz && elementos) {
    const config = JSON.parse(raiz.dataset.catalogo ?? '{}') as CatalogoConfig;
    new CatalogBase(config, elementos, { http: window.http, notify: window.notify });
}
