<div id="modalCalificarJulios"
     class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/60"
     onclick="if(event.target===this) cerrarModalCalificarJulios()">
    <div class="bg-white rounded-xl shadow-2xl w-[95%] max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-5 py-3 bg-purple-600 text-white">
            <h3 class="text-lg font-semibold flex flex-wrap items-center gap-2 min-w-0">
                <i class="fa-solid fa-clipboard-check shrink-0"></i>
                <span>Calificar Julios &mdash; Folio {{ $orden->Folio }}</span>
            </h3>
            <button type="button" class="text-white hover:text-gray-200 text-2xl leading-none shrink-0"
                    onclick="cerrarModalCalificarJulios()">&times;</button>
        </div>

        <div class="px-5 py-4 bg-gray-50 border-b border-gray-200">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-700">
                        <i class="fa-solid fa-user text-xl"></i>
                    </div>
                    <div class="text-xl font-semibold text-gray-900 truncate min-w-0">
                        {{ $usuarioNombre ?? auth()->user()->nombre ?? '—' }}
                    </div>
                </div>
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                        <i class="fa-solid fa-calendar text-xl"></i>
                    </div>
                    <div id="calificarJuliosFechaHoyLabel" class="text-xl font-semibold text-gray-900 min-w-0">
                        {{ now()->format('Y-m-d') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 overflow-auto flex-1">
            <div id="calificarJuliosLoading" class="text-center py-6 text-gray-500 hidden">
                <i class="fa-solid fa-spinner fa-spin text-2xl"></i>
                <div class="mt-2">Cargando julios...</div>
            </div>
            <div id="calificarJuliosEmpty" class="text-center py-6 text-gray-500 hidden">
                No se encontraron julios para este folio.
            </div>

            <table id="tablaCalificarJulios" class="w-full text-sm hidden">
                <thead>
                    <tr class="bg-gray-100 text-gray-700">
                        <th class="px-3 py-2 text-left">Folio</th>
                        <th class="px-3 py-2 text-left">Julio</th>
                        <th class="px-3 py-2 text-left">Defecto</th>
                    </tr>
                </thead>
                <tbody id="tbodyCalificarJulios"></tbody>
            </table>
        </div>

        <div class="px-5 py-3 bg-gray-50 border-t flex justify-end">
            <button type="button"
                    class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded-lg"
                    onclick="cerrarModalCalificarJulios()">Cerrar</button>
        </div>
    </div>
</div>

<script>
(function () {
    const URL_GET = "{{ route('engomado.modulo.produccion.engomado.calificar.julios.get') }}";
    const URL_SAVE = "{{ route('engomado.modulo.produccion.engomado.calificar.julios.save') }}";
    const FOLIO = @json($orden->Folio);
    const CSRF = "{{ csrf_token() }}";
    const APP_TIMEZONE = @json(config('app.timezone'));

    function fechaHoyCalificacionYmd() {
        return new Date().toLocaleDateString('en-CA', { timeZone: APP_TIMEZONE });
    }

    // Mapeo de Clave -> clase Tailwind por familia
    const COLOR_MAP = {
        'RHC': 'bg-red-100 text-red-800',
        'PHC': 'bg-red-100 text-red-800',
        'RHS': 'bg-orange-100 text-orange-800',
        'PHS': 'bg-orange-100 text-orange-800',
        'RHCE': 'bg-amber-100 text-amber-900',
        'PHCE': 'bg-amber-100 text-amber-900',
        'N': 'bg-yellow-100 text-yellow-800',
        'D': 'bg-yellow-100 text-yellow-800',
        'M': 'bg-yellow-100 text-yellow-800',
        'MA': 'bg-yellow-100 text-yellow-800',
        'J': 'bg-lime-100 text-lime-800',
        'HP': 'bg-lime-100 text-lime-800',
        'HD': 'bg-lime-100 text-lime-800',
        'MI': 'bg-green-100 text-green-800',
        'DM': 'bg-teal-100 text-teal-800',
        'TL': 'bg-teal-100 text-teal-800',
        'FH': 'bg-cyan-100 text-cyan-800',
        'G': 'bg-cyan-100 text-cyan-800',
        'TF': 'bg-indigo-100 text-indigo-800',
        'Z': 'bg-indigo-100 text-indigo-800',
        'E': 'bg-indigo-100 text-indigo-800',
        'DT': 'bg-purple-100 text-purple-900',
    };
    const ALL_COLOR_CLASSES = [...new Set(Object.values(COLOR_MAP).flatMap(c => c.split(' ')))];

    let CACHE_DEFECTOS = [];

    function aplicarColor(selectEl) {
        ALL_COLOR_CLASSES.forEach(c => selectEl.classList.remove(c));
        const opt = selectEl.options[selectEl.selectedIndex];
        const clave = opt ? (opt.dataset.clave || '') : '';
        if (clave && COLOR_MAP[clave]) {
            COLOR_MAP[clave].split(' ').forEach(c => selectEl.classList.add(c));
        }
    }

    function parseFecha(val) {
        if (!val) return '';
        // Acepta: "2026-04-07T00:00:00.000000Z", "2026-04-07 14:30:00", "2026-04-07"
        return String(val).replace('T', ' ').substring(0, 10);
    }

    function resolveOperadorJulioUrd(j) {
        const slots = [
            { m: parseFloat(j.Metros1) || 0, n: j.NomEmpl1 },
            { m: parseFloat(j.Metros2) || 0, n: j.NomEmpl2 },
            { m: parseFloat(j.Metros3) || 0, n: j.NomEmpl3 },
        ];
        let bestI = 0;
        for (let i = 1; i < 3; i++) {
            if (slots[i].m > slots[bestI].m) bestI = i;
        }
        const maxM = slots[bestI].m;
        if (maxM > 0) {
            for (let i = 0; i < 3; i++) {
                if (slots[i].m === maxM && slots[i].n != null && String(slots[i].n).trim() !== '') {
                    return String(slots[i].n).trim();
                }
            }
        }
        for (const s of slots) {
            if (s.n != null && String(s.n).trim() !== '') return String(s.n).trim();
        }
        return '';
    }

    function buildInfoJulioUrd(j) {
        const operador = resolveOperadorJulioUrd(j);
        const fechaJulio = j.Fecha ? parseFecha(j.Fecha) : '';
        if (!operador && !fechaJulio) return '';
        let html = '<div class="info-julio-urdido text-xs text-gray-500 mb-1 flex flex-wrap gap-3">';
        if (operador) {
            html += '<span title="Oficial con mayor metraje en este julio (urdido)"><i class="fa-solid fa-user mr-1"></i>' + operador + '</span>';
        }
        if (fechaJulio) {
            html += '<span title="Fecha del julio (urdido)"><i class="fa-solid fa-calendar mr-1"></i>' + fechaJulio + '</span>';
        }
        html += '</div>';
        return html;
    }

    function buildSelect(julio) {
        let html = '<select class="w-full border rounded px-2 py-1 text-sm font-semibold" '
                 + 'onchange="window.__calificarJulioChange(' + julio.Id + ', this)">';
        html += '<option value="" data-clave="">— Sin defecto —</option>';
        CACHE_DEFECTOS.forEach(d => {
            const sel = (julio.ClaveDefecto && parseInt(julio.ClaveDefecto) === parseInt(d.Id)) ? ' selected' : '';
            html += '<option value="' + d.Id + '" data-clave="' + d.Clave + '"' + sel + '>'
                  + d.Clave + ' — ' + (d.Defecto || '')
                  + '</option>';
        });
        html += '</select>';
        return html;
    }

    function renderTabla(julios) {
        const tbody = document.getElementById('tbodyCalificarJulios');
        const tabla = document.getElementById('tablaCalificarJulios');
        const empty = document.getElementById('calificarJuliosEmpty');
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
                '<td class="px-3 py-2">' + buildInfoJulioUrd(j) + buildSelect(j) + '</td>';
            tbody.appendChild(tr);
            const sel = tr.querySelector('select');
            aplicarColor(sel);
        });
    }

    window.abrirModalCalificarJulios = async function () {
        const feHoy = document.getElementById('calificarJuliosFechaHoyLabel');
        if (feHoy) feHoy.textContent = fechaHoyCalificacionYmd();

        const modal = document.getElementById('modalCalificarJulios');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('calificarJuliosLoading').classList.remove('hidden');
        document.getElementById('tablaCalificarJulios').classList.add('hidden');
        document.getElementById('calificarJuliosEmpty').classList.add('hidden');

        try {
            const res = await fetch(URL_GET + '?folio=' + encodeURIComponent(FOLIO), {
                headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Error al cargar');
            CACHE_DEFECTOS = json.defectos || [];
            renderTabla(json.julios || []);
        } catch (e) {
            if (typeof toastr !== 'undefined') toastr.error(e.message);
            else alert(e.message);
        } finally {
            document.getElementById('calificarJuliosLoading').classList.add('hidden');
        }
    };

    window.cerrarModalCalificarJulios = function () {
        const modal = document.getElementById('modalCalificarJulios');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    window.__calificarJulioChange = async function (julioId, selectEl) {
        const defectoId = selectEl.value || null;
        aplicarColor(selectEl);
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
            if (typeof toastr !== 'undefined') toastr.success('Julio calificado');
            // Actualizar info de operador/fecha en el DOM sin recargar
            const td = selectEl.closest('td');
            if (td) {
                const existing = td.querySelector('.info-julio-urdido');
                if (existing) existing.remove();
                const data = json.data || {};
                const infoHtml = buildInfoJulioUrd(data);
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
