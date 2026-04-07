<div id="modalCalificarJuliosEng"
     class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/60"
     onclick="if(event.target===this) cerrarModalCalificarJuliosEng()">
    <div class="bg-white rounded-xl shadow-2xl w-[95%] max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 bg-purple-600 text-white">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <i class="fa-solid fa-clipboard-check"></i>
                Calificar Julios &mdash; Folio <span id="calificarJuliosEngFolioLabel" class="ml-1"></span>
            </h3>
            <button type="button" class="text-white hover:text-gray-200 text-2xl leading-none"
                    onclick="cerrarModalCalificarJuliosEng()">&times;</button>
        </div>

        <div class="p-4 overflow-auto flex-1">
            <div id="calificarJuliosEngLoading" class="text-center py-6 text-gray-500 hidden">
                <i class="fa-solid fa-spinner fa-spin text-2xl"></i>
                <div class="mt-2">Cargando registros...</div>
            </div>
            <div id="calificarJuliosEngEmpty" class="text-center py-6 text-gray-500 hidden">
                No se encontraron registros para este folio.
            </div>

            <table id="tablaCalificarJuliosEng" class="w-full text-sm hidden">
                <thead>
                    <tr class="bg-gray-100 text-gray-700">
                        <th class="px-3 py-2 text-left">Folio</th>
                        <th class="px-3 py-2 text-left">Julio</th>
                        <th class="px-3 py-2 text-left">Defecto</th>
                    </tr>
                </thead>
                <tbody id="tbodyCalificarJuliosEng"></tbody>
            </table>
        </div>

        <div class="px-5 py-3 bg-gray-50 border-t flex justify-end">
            <button type="button"
                    class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded-lg"
                    onclick="cerrarModalCalificarJuliosEng()">Cerrar</button>
        </div>
    </div>
</div>

<script>
(function () {
    const URL_GET  = "{{ route('engomado.modulo.produccion.engomado.calificar.julios.eng.get') }}";
    const URL_SAVE = "{{ route('engomado.modulo.produccion.engomado.calificar.julios.eng.save') }}";
    const CSRF = "{{ csrf_token() }}";

    const COLOR_MAP = {
        'RHC': 'bg-red-100 text-red-800',    'PHC': 'bg-red-100 text-red-800',
        'RHS': 'bg-orange-100 text-orange-800', 'PHS': 'bg-orange-100 text-orange-800',
        'RHCE': 'bg-amber-100 text-amber-900',  'PHCE': 'bg-amber-100 text-amber-900',
        'N': 'bg-yellow-100 text-yellow-800',   'D': 'bg-yellow-100 text-yellow-800',
        'M': 'bg-yellow-100 text-yellow-800',   'MA': 'bg-yellow-100 text-yellow-800',
        'J': 'bg-lime-100 text-lime-800',       'HP': 'bg-lime-100 text-lime-800',
        'HD': 'bg-lime-100 text-lime-800',      'MI': 'bg-green-100 text-green-800',
        'DM': 'bg-teal-100 text-teal-800',      'TL': 'bg-teal-100 text-teal-800',
        'FH': 'bg-cyan-100 text-cyan-800',      'G': 'bg-cyan-100 text-cyan-800',
        'TF': 'bg-indigo-100 text-indigo-800',  'Z': 'bg-indigo-100 text-indigo-800',
        'E': 'bg-indigo-100 text-indigo-800',   'DT': 'bg-purple-100 text-purple-900',
    };
    const ALL_COLOR_CLASSES = [...new Set(Object.values(COLOR_MAP).flatMap(c => c.split(' ')))];

    let CACHE_DEFECTOS_ENG = [];

    function aplicarColorEng(selectEl) {
        ALL_COLOR_CLASSES.forEach(c => selectEl.classList.remove(c));
        const opt = selectEl.options[selectEl.selectedIndex];
        const clave = opt ? (opt.dataset.clave || '') : '';
        if (clave && COLOR_MAP[clave]) {
            COLOR_MAP[clave].split(' ').forEach(c => selectEl.classList.add(c));
        }
    }

    function parseFechaEng(val) {
        if (!val) return '';
        return String(val).replace('T', ' ').substring(0, 10);
    }

    function buildInfoOperadorEng(j) {
        if (!j.OperadorDefecto && !j.FechaDefecto) return '';
        let html = '<div class="info-operador-defecto text-xs text-gray-500 mb-1 flex gap-3">';
        if (j.OperadorDefecto) html += '<span><i class="fa-solid fa-user mr-1"></i>' + j.OperadorDefecto + '</span>';
        if (j.FechaDefecto)    html += '<span><i class="fa-solid fa-calendar mr-1"></i>' + parseFechaEng(j.FechaDefecto) + '</span>';
        html += '</div>';
        return html;
    }

    function buildSelectEng(registro) {
        let html = '<select class="w-full border rounded px-2 py-1 text-sm font-semibold" '
                 + 'onchange="window.__calificarJulioEngChange(' + registro.Id + ', this)">';
        html += '<option value="" data-clave="">— Sin defecto —</option>';
        CACHE_DEFECTOS_ENG.forEach(d => {
            const sel = (registro.ClaveDefecto && parseInt(registro.ClaveDefecto) === parseInt(d.Id)) ? ' selected' : '';
            html += '<option value="' + d.Id + '" data-clave="' + d.Clave + '"' + sel + '>'
                  + d.Clave + ' — ' + (d.Defecto || '')
                  + '</option>';
        });
        html += '</select>';
        return html;
    }

    function renderTablaEng(julios) {
        const tbody = document.getElementById('tbodyCalificarJuliosEng');
        const tabla = document.getElementById('tablaCalificarJuliosEng');
        const empty = document.getElementById('calificarJuliosEngEmpty');
        tbody.innerHTML = '';
        if (!julios.length) {
            tabla.classList.add('hidden');
            empty.classList.remove('hidden');
            return;
        }
        empty.classList.add('hidden');
        tabla.classList.remove('hidden');

        julios.forEach(j => {
            const tr = document.createElement('tr');
            tr.className = 'border-b hover:bg-gray-50';
            tr.innerHTML =
                '<td class="px-3 py-2">' + (j.Folio ?? '') + '</td>' +
                '<td class="px-3 py-2 font-semibold">' + (j.NoJulio ?? '') + '</td>' +
                '<td class="px-3 py-2">' + buildInfoOperadorEng(j) + buildSelectEng(j) + '</td>';
            tbody.appendChild(tr);
            const sel = tr.querySelector('select');
            aplicarColorEng(sel);
        });
    }

    window.abrirModalCalificarJuliosEng = async function (folio) {
        document.getElementById('calificarJuliosEngFolioLabel').textContent = folio;
        const modal = document.getElementById('modalCalificarJuliosEng');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('calificarJuliosEngLoading').classList.remove('hidden');
        document.getElementById('tablaCalificarJuliosEng').classList.add('hidden');
        document.getElementById('calificarJuliosEngEmpty').classList.add('hidden');

        try {
            const res = await fetch(URL_GET + '?folio=' + encodeURIComponent(folio), {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Error al cargar');
            CACHE_DEFECTOS_ENG = json.defectos || [];
            renderTablaEng(json.julios || []);
        } catch (e) {
            if (typeof toastr !== 'undefined') toastr.error(e.message);
            else alert(e.message);
        } finally {
            document.getElementById('calificarJuliosEngLoading').classList.add('hidden');
        }
    };

    window.cerrarModalCalificarJuliosEng = function () {
        const modal = document.getElementById('modalCalificarJuliosEng');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    window.__calificarJulioEngChange = async function (julioId, selectEl) {
        const defectoId = selectEl.value || null;
        aplicarColorEng(selectEl);
        selectEl.disabled = true;
        try {
            const res = await fetch(URL_SAVE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF
                },
                body: JSON.stringify({ julio_id: julioId, defecto_id: defectoId })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Error al guardar');
            if (typeof toastr !== 'undefined') toastr.success('Calificado correctamente');
            // Actualizar info de operador/fecha en el DOM sin recargar
            const td = selectEl.closest('td');
            if (td) {
                const existing = td.querySelector('.info-operador-defecto');
                if (existing) existing.remove();
                const data = json.data || {};
                const infoHtml = buildInfoOperadorEng(data);
                if (infoHtml) td.insertAdjacentHTML('afterbegin', infoHtml);
            }
        } catch (e) {
            if (typeof toastr !== 'undefined') toastr.error(e.message);
            else alert(e.message);
        } finally {
            selectEl.disabled = false;
        }
    };
})();
</script>
