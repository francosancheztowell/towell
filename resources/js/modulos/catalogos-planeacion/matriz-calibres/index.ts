/**
 * Matriz de Calibres (catalagos/matriz-calibres). Antes: public/js/catalogs/MatrizCalibresCatalog.js.
 * Barra propia de búsqueda + tipo (filtra en vivo) en lugar del modal de filtros.
 */
import { onReady, qs } from '../../../utils/dom.ts';
import { habilitarEdicion } from '../../../catalogos/catalog-actions.ts';
import { leerValores } from '../comun/logica.ts';
import { arrancarCatalogo, repoblar } from '../comun/catalogo.ts';
import { coincideCalibre, procesarCalibre, reglasPorTipo, tiposConActual, validarCalibre } from './logica.ts';

function conectarTipo(form: HTMLFormElement): void {
    const tipo = form.elements.namedItem('Tipo') as HTMLSelectElement | null;
    const cuenta = form.elements.namedItem('Cuenta') as HTMLInputElement | null;
    const calibre = form.elements.namedItem('Calibre') as HTMLInputElement | null;
    const fibra = form.elements.namedItem('FibraId') as HTMLInputElement | null;
    if (!tipo || !cuenta || !calibre || !fibra) return;
    const aplicar = (): void => {
        const r = reglasPorTipo(tipo.value);
        cuenta.disabled = !r.cuentaHabilitada;
        if (!r.cuentaHabilitada) cuenta.value = '';
        calibre.required = r.calibreRequerido;
        fibra.required = r.calibreRequerido;
    };
    if (!form.dataset.tipoListo) {
        form.dataset.tipoListo = '1';
        tipo.addEventListener('change', aplicar);
    }
    aplicar();
}

onReady(() => {
    const catalogo = arrancarCatalogo({
        validar: validarCalibre,
        procesar: procesarCalibre,
        resumen: (v) => `Registro: ${String(v.Tipo || v.ItemId || v.Id || '')}`,
        alAbrir(form, valores) {
            const tipo = form.elements.namedItem('Tipo') as HTMLSelectElement | null;
            if (tipo) {
                const opciones = [...tipo.options].map((o) => o.value).filter((v) => v !== '');
                repoblar(tipo, tiposConActual(opciones, valores?.Tipo), String(valores?.Tipo ?? '').toUpperCase());
            }
            conectarTipo(form);
        },
    });
    if (!catalogo) return;

    const buscar = qs<HTMLInputElement>('#matriz-calibres-search');
    const tipo = qs<HTMLSelectElement>('#matriz-calibres-tipo');
    const contador = qs('#matriz-calibres-count');
    const vacio = qs('#matriz-calibres-empty');
    const filas = [...catalogo.el.cuerpo.querySelectorAll<HTMLTableRowElement>('tr[data-fila]')];

    const filtrar = (): void => {
        const termino = (buscar?.value ?? '').trim();
        let visibles = 0;
        for (const fila of filas) {
            const pasa = coincideCalibre(leerValores(fila.dataset.valores), termino, tipo?.value ?? '');
            fila.hidden = !pasa;
            if (pasa) visibles++;
        }
        if (contador) contador.textContent = String(visibles);
        vacio?.classList.toggle('hidden', visibles > 0);
        catalogo.seleccionar(null);
        habilitarEdicion(false);
    };
    buscar?.addEventListener('input', filtrar);
    tipo?.addEventListener('change', filtrar);
    qs('#matriz-calibres-clear')?.addEventListener('click', () => {
        if (buscar) buscar.value = '';
        if (tipo) tipo.value = '';
        filtrar();
    });
});
