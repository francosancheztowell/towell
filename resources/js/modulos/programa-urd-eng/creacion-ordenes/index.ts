/**
 * Creación de órdenes de Programa Urd-Eng (19-05, BUG-033: antes public/js/…/creacion-ordenes.js
 * fuera de Vite + 36 líneas inline). Vista: modulos/programa_urd_eng/creacion-ordenes.blade.php.
 *
 * Los telares llegan de Programación de requerimientos por `?telares=<JSON>` (comun/contrato-flujo.ts):
 * el controller los decodifica y los pasa en data-pagina; si no vinieron, se leen de la query.
 */
import { delegate, onReady } from '../../../utils/dom.ts';
import { notify } from '../../../utils/notifications.ts';
import { leerDatos } from '../../urdido/comun/pagina.ts';
import { PARAM_TELARES, parsearTelares } from '../comun/contrato-flujo.ts';
import { autocompletarBom } from './autocompletar.ts';
import { crearOrdenes } from './envio.ts';
import { actualizarMetrajeTelas, cargarMaquinasEngomado, cargarNucleos } from './engomado.ts';
import type { ConfigPagina } from './estado.ts';
import { estado, rutas } from './estado.ts';
import { cambiarDestino, elegirBomUrdido, pintarGrupos, salirDeBomUrdido, seleccionarFila } from './grupos.ts';
import { MAX_JULIOS, normalizarTelares } from './logica.ts';
import type { TelarCrudo } from './logica.ts';
import { actualizarBotonCrear, alCambiarSeleccion, ordenarPor, pintarMaterialesEngomado, pintarMaterialesUrdido } from './materiales.ts';

function iniciar(): void {
    const raiz = document.getElementById('creacion-ordenes');
    const cfg = leerDatos<ConfigPagina>(raiz);
    if (!raiz || !cfg) return;
    estado.config = cfg;

    let telares = normalizarTelares((cfg.telares ?? []) as TelarCrudo[]);
    if (!telares.length) {
        telares = normalizarTelares(parsearTelares<TelarCrudo>(new URLSearchParams(location.search).get(PARAM_TELARES)));
    }

    pintarMaterialesUrdido([], 0, null, false);
    pintarMaterialesEngomado([], null);
    pintarGrupos(telares);
    actualizarBotonCrear();

    // Tabla 1: seleccionar fila (salvo al tocar sus controles) y destino por grupo.
    delegate<HTMLTableRowElement, MouseEvent>(raiz, 'click', '#tbodyOrdenes tr[data-fila-id]', (e, tr) => {
        const t = e.target;
        if (t instanceof Element && t.closest('input, select, textarea')) return;
        seleccionarFila(tr.dataset.filaId ?? '');
    });
    delegate<HTMLSelectElement>(raiz, 'change', '[data-destino-select="true"]', (_e, select) => cambiarDestino(select));

    // BOM de urdido por fila y L.Mat de engomado.
    autocompletarBom({
        raiz,
        selector: '[data-bom-input="true"]',
        ruta: () => rutas().buscarBomUrdido,
        idLista: 'bom-suggestions-global',
        alElegir: (input, s) => elegirBomUrdido(input, s.BOMID),
    });
    delegate<HTMLInputElement>(raiz, 'focusout', '[data-bom-input="true"]', (_e, input) => salirDeBomUrdido(input));
    autocompletarBom({
        raiz,
        selector: '#inputLMatEngomado',
        ruta: () => rutas().buscarBomEngomado,
        idLista: 'bom-engomado-suggestions',
        alElegir: (input, s) => {
            input.value = s.BOMID;
        },
    });

    // Tabla 3: orden por columna y selección de materiales.
    delegate<HTMLElement>(raiz, 'click', '#tablaMaterialesEngomado th.sortable', (_e, th) => {
        if (th.dataset.sort) ordenarPor(th.dataset.sort);
    });
    delegate<HTMLElement, KeyboardEvent>(raiz, 'keydown', '#tablaMaterialesEngomado th.sortable', (e, th) => {
        if ((e.key === 'Enter' || e.key === ' ') && th.dataset.sort) {
            e.preventDefault();
            ordenarPor(th.dataset.sort);
        }
    });
    delegate<HTMLInputElement>(raiz, 'change', '.checkbox-material', (_e, check) => alCambiarSeleccion(check));

    // Tabla 4: No. Julios nunca pasa de 15.
    delegate<HTMLInputElement>(raiz, 'input', '[data-julios]', (_e, input) => {
        if (parseInt(input.value, 10) > MAX_JULIOS) {
            input.value = String(MAX_JULIOS);
            notify.warning(`Máximo ${MAX_JULIOS} julios: el número de julios no puede ser mayor a ${MAX_JULIOS}.`);
        }
    });

    // Tabla 5: metraje por tela y catálogos.
    delegate(raiz, 'input', '#inputNoTelas', actualizarMetrajeTelas);
    delegate(raiz, 'change', '#inputNoTelas', actualizarMetrajeTelas);
    void cargarMaquinasEngomado();
    void cargarNucleos();

    // Botón del navbar (fuera de la raíz).
    delegate<HTMLElement>(document, 'click', '[data-accion="crear-ordenes"]', (_e, boton) => void crearOrdenes(boton));
}

onReady(iniciar);
