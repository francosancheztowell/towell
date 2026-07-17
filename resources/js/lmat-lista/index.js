import { openLMatModal } from '../catcodificacion/lmat-modal';

(function () {
    let ordenSeleccionada = null;

    const btnVerLista = document.getElementById('btn-ver-lista');

    function actualizarBotonVerLista() {
        if (btnVerLista) btnVerLista.disabled = !ordenSeleccionada;
    }

    function seleccionarFila(tr) {
        document.querySelectorAll('tr.lmat-row-selected').forEach((prev) => {
            prev.classList.remove('lmat-row-selected');
        });
        if (ordenSeleccionada === tr.dataset.orden) {
            ordenSeleccionada = null;
        } else {
            tr.classList.add('lmat-row-selected');
            ordenSeleccionada = tr.dataset.orden;
        }
        actualizarBotonVerLista();
    }

    async function verListaSeleccionada() {
        if (!ordenSeleccionada) return;

        try {
            const resp = await fetch('/planeacion/lmat/api/catcodificados-por-orden/' + encodeURIComponent(ordenSeleccionada), {
                headers: { Accept: 'application/json' },
            });
            const json = await resp.json().catch(() => ({}));
            if (!resp.ok || json.success !== true) {
                throw new Error(json.message || `Error ${resp.status} al cargar el registro.`);
            }

            await openLMatModal({
                getSelectedRecord: () => json.data,
                onSaved: () => window.location.reload(),
            });
        } catch (e) {
            console.error('No se pudo abrir la lista de materiales', e);
            (window.showToast || ((msg) => alert(msg)))(e.message || 'No se pudo abrir la lista de materiales.', 'error');
        }
    }

    document.querySelectorAll('tr[data-orden]').forEach((tr) => {
        tr.addEventListener('click', () => seleccionarFila(tr));
    });

    if (btnVerLista) btnVerLista.addEventListener('click', verListaSeleccionada);

    // Filtros select-buscable (orden, nombre, clave, tamaño, salón)
    const filtros = [
        ['f-orden', 'fOrden'],
        ['f-nombre', 'fNombre'],
        ['f-clave', 'fClave'],
        ['f-tamano', 'fTamano'],
        ['f-salon', 'fSalon'],
    ];
    const contador = document.getElementById('lmat-contador');
    const filas = Array.from(document.querySelectorAll('tr.lmat-row'));

    function aplicarFiltros() {
        const valores = filtros.map(([id]) => (document.getElementById(id)?.value || '').trim().toLowerCase());
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

    const jq = window.jQuery;
    if (jq?.fn?.select2) {
        jq('.lmat-filtro-select').each(function () {
            jq(this).select2({
                width: '100%',
                allowClear: true,
                placeholder: jq(this).data('placeholder'),
                dropdownCssClass: 'lmat-select2-dd',
            });
        }).on('select2:select select2:clear', aplicarFiltros);
    }
})();
