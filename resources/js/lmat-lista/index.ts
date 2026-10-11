import { openLMatModal, type RegistroCat } from '../catcodificacion/lmat-modal.ts';
import { combobox } from '../utils/combobox.ts';
import { http } from '../utils/http.ts';
import { qs, qsa } from '../utils/dom.ts';
import { showToast } from '../utils/notifications.ts';

let ordenSeleccionada: string | null = null;

const btnVerLista = qs<HTMLButtonElement>('#btn-ver-lista');

function actualizarBotonVerLista(): void {
    if (btnVerLista) btnVerLista.disabled = !ordenSeleccionada;
}

function seleccionarFila(tr: HTMLElement): void {
    qsa('tr.lmat-row-selected').forEach((prev) => prev.classList.remove('lmat-row-selected'));
    if (ordenSeleccionada === tr.dataset.orden) {
        ordenSeleccionada = null;
    } else {
        tr.classList.add('lmat-row-selected');
        ordenSeleccionada = tr.dataset.orden ?? null;
    }
    actualizarBotonVerLista();
}

async function verListaSeleccionada(): Promise<void> {
    if (!ordenSeleccionada) return;

    try {
        const json = await http.get<{ success?: boolean; message?: string; data?: RegistroCat }>(
            '/planeacion/lmat/api/catcodificados-por-orden/' + encodeURIComponent(ordenSeleccionada),
        );
        if (json.success !== true) throw new Error(json.message || 'Error al cargar el registro.');

        await openLMatModal({
            getSelectedRecord: () => json.data ?? null,
            onSaved: () => window.location.reload(),
        });
    } catch (e) {
        console.error('No se pudo abrir la lista de materiales', e);
        showToast((e instanceof Error && e.message) || 'No se pudo abrir la lista de materiales.', 'error');
    }
}

qsa('tr[data-orden]').forEach((tr) => {
    tr.addEventListener('click', () => seleccionarFila(tr));
});

btnVerLista?.addEventListener('click', verListaSeleccionada);

// Filtros select-buscable (orden, nombre, clave, tamaño, salón)
const filtros = [
    ['f-orden', 'fOrden'],
    ['f-nombre', 'fNombre'],
    ['f-clave', 'fClave'],
    ['f-tamano', 'fTamano'],
    ['f-salon', 'fSalon'],
] as const;
const contador = qs('#lmat-contador');
const filas = qsa('tr.lmat-row');

function aplicarFiltros(): void {
    const valores = filtros.map(([id]) => (qs<HTMLSelectElement>('#' + id)?.value || '').trim().toLowerCase());
    let visibles = 0;
    filas.forEach((tr) => {
        const coincide = filtros.every(([, dataKey], i) => (
            !valores[i] || (tr.dataset[dataKey] || '').includes(valores[i])
        ));
        tr.classList.toggle('hidden', !coincide);
        if (coincide) visibles++;
    });
    if (contador) contador.textContent = visibles + ' lista(s)';
}

qsa<HTMLSelectElement>('select.lmat-filtro-select').forEach((select) => {
    combobox(select, { permitirVacio: true });
    select.addEventListener('change', aplicarFiltros);
});
