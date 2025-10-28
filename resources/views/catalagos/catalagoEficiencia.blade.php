@extends('layouts.app')

@section('page-title', 'Catálogo de Eficiencia')

@section('content')
<div class="container">

    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
            <table class="table table-bordered table-sm w-full">
                <thead class="sticky top-0 bg-blue-500 text-white z-10">
                    <tr>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Salón</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Telar</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Tipo de Hilo</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Eficiencia</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Densidad</th>
                    </tr>
                </thead>
                <tbody id="eficiencia-body" class="bg-white text-black">
                    @foreach ($eficiencia as $item)
                        @php
                            $uniqueId = $item->NoTelarId . '_' . $item->FibraId;
                            $recordId = $item->Id ?: $item->SalonTejidoId . '_' . $item->NoTelarId . '_' . $item->FibraId;
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="selectRow(this, '{{ $uniqueId }}', '{{ $recordId }}')"
                            ondblclick="deselectRow(this)"
                            data-eficiencia="{{ $uniqueId }}"
                            data-eficiencia-id="{{ $recordId }}"
                            data-salon="{{ $item->SalonTejidoId }}"
                            data-telar="{{ $item->NoTelarId }}"
                            data-fibra="{{ $item->FibraId }}"
                            data-eficiencia-dec="{{ $item->Eficiencia }}"
                            data-densidad="{{ $item->Densidad ?? 'Normal' }}"
                        >
                            <td class="py-1 px-4 border-b">{{ $item->SalonTejidoId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->NoTelarId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->FibraId }}</td>
                            <td class="py-1 px-4 border-b font-semibold">{{ number_format($item->Eficiencia * 100, 0) }}%</td>
                            <td class="py-1 px-4 border-b">{{ $item->Densidad ?? 'Normal' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>


    </div>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* ===========================
   Estado y datos
=========================== */
let selectedRow = null;
let selectedEficiencia = null;
let selectedEficienciaId = null;

let filtrosActuales = { salon:'', telar:'', fibra:'', densidad:'', eficiencia_min:'', eficiencia_max:'' };
let datosOriginales = @json($eficiencia);
let datosActuales = datosOriginales;
const cacheFiltros = new Map();

/* ===========================
   Catálogo de telares
=========================== */
const telaresPorSalon = {
    'JACQUARD': [201,202,203,204,205,206,207,208,209,210,211,213,214,215],
    'SMITH': [299,300,301,302,303,304,305,306,307,308,309,310,311,312,313,314,315,316,317,318,319,320]
};

/* ===========================
   Helpers UI
=========================== */
function crearToast(icon, msg, ms = 1500) {
    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: ms, timerProgressBar: true,
        didOpen: (t) => { t.addEventListener('mouseenter', Swal.stopTimer); t.addEventListener('mouseleave', Swal.resumeTimer); }
    });
    Toast.fire({ icon, title: msg });
}

function repoblarSelect(selectEl, opciones, selectedValue = '') {
    if (!selectEl) return;
    selectEl.innerHTML = '';
    const def = document.createElement('option');
    def.value = ''; def.textContent = 'Seleccionar';
    selectEl.appendChild(def);

    (opciones || []).forEach(v => {
        const opt = document.createElement('option');
        opt.value = String(v);
        opt.textContent = v;
        selectEl.appendChild(opt);
    });

    if (selectedValue !== undefined && selectedValue !== null && selectedValue !== '') {
        selectEl.value = String(selectedValue);
    }
}

function enableButtons() {
    const e = document.getElementById('btn-editar');
    const d = document.getElementById('btn-eliminar');
    if (e) { e.disabled = false; e.className = 'inline-flex items-center px-3 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg text-sm font-medium'; }
    if (d) { d.disabled = false; d.className = 'inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium'; }
}
function disableButtons() {
    const e = document.getElementById('btn-editar');
    const d = document.getElementById('btn-eliminar');
    if (e) { e.disabled = true; e.className = 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg text-sm font-medium cursor-not-allowed'; }
    if (d) { d.disabled = true; d.className = 'inline-flex items-center px-3 py-2 bg-gray-400 text-gray-200 rounded-lg text-sm font-medium cursor-not-allowed'; }
}

/* ===========================
   Selección de filas
=========================== */
function selectRow(row, uniqueId, eficienciaId) {
    document.querySelectorAll('#eficiencia-body tr').forEach(r => {
        r.classList.remove('bg-blue-500','text-white');
        r.classList.add('hover:bg-blue-50');
    });
    row.classList.remove('hover:bg-blue-50');
    row.classList.add('bg-blue-500','text-white');

    selectedRow = row;
    selectedEficiencia = uniqueId;
    selectedEficienciaId = eficienciaId;
    enableButtons();
}
function deselectRow(row) {
    if (!row.classList.contains('bg-blue-500')) return;
    row.classList.remove('bg-blue-500','text-white');
    row.classList.add('hover:bg-blue-50');
    selectedRow = null;
    selectedEficiencia = null;
    selectedEficienciaId = null;
    disableButtons();
}

/* ===========================
   Crear (Agregar)
=========================== */
function actualizarTelaresCreate() {
    const salon = document.getElementById('swal-salon').value;
    repoblarSelect(document.getElementById('swal-telar'), telaresPorSalon[salon] || [], '');
}
function actualizarEficiencia(valor) {
    document.getElementById('eficiencia-value').textContent = valor + '%';
}
function agregarEficienciaLocal() {
    Swal.fire({
        title: 'Crear Nueva Eficiencia',
        html: `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Salón *</label>
                    <select id="swal-salon" class="w-full px-2 py-2 border border-gray-300 rounded text-center" required onchange="actualizarTelaresCreate()">
                        <option value="">Seleccionar</option>
                        <option value="JACQUARD">JACQUARD</option>
                        <option value="SMITH">SMITH</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Telar *</label>
                    <select id="swal-telar" class="w-full px-2 py-2 border border-gray-300 rounded text-center" required>
                        <option value="">Seleccionar</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hilo *</label>
                    <input id="swal-fibra" type="text" class="w-full px-2 py-2 border border-gray-300 rounded text-center" placeholder="H" maxlength="15" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Densidad</label>
                    <select id="swal-densidad" class="w-full px-2 py-2 border border-gray-300 rounded text-center">
                        <option value="Normal">Normal</option>
                        <option value="Alta">Alta</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Eficiencia:
                        <span id="eficiencia-value" class="font-bold text-blue-600">78%</span></label>
                    <input id="swal-eficiencia" type="range" min="0" max="100" value="78" step="1"
                           class="w-full h-4 bg-gray-200 rounded-lg appearance-none cursor-pointer slider-compact"
                           oninput="actualizarEficiencia(this.value)">
                </div>
            </div>
        `,
        width: '420px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save me-2"></i>Crear',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        confirmButtonColor: '#255be6',
        cancelButtonColor: '#6c757d',
        preConfirm: () => {
            const SalonTejidoId = document.getElementById('swal-salon').value;
            const NoTelarId     = document.getElementById('swal-telar').value;
            const FibraId       = document.getElementById('swal-fibra').value.trim();
            const Densidad      = document.getElementById('swal-densidad').value;
            const pct           = document.getElementById('swal-eficiencia').value;

            if (!SalonTejidoId || !NoTelarId || !FibraId) {
                Swal.showValidationMessage('Por favor completa los campos requeridos'); return false;
            }
            const Eficiencia = Number(pct) / 100;
            if (isNaN(Eficiencia) || Eficiencia < 0 || Eficiencia > 1) {
                Swal.showValidationMessage('La eficiencia debe estar entre 0% y 100%'); return false;
            }
            return { NoTelarId, SalonTejidoId, FibraId, Eficiencia, Densidad };
        }
    }).then((res) => {
        if (!res.isConfirmed) return;
        Swal.fire({ title: 'Creando...', allowOutsideClick: false, showConfirmButton: false, didOpen: Swal.showLoading });

        fetch('/planeacion/eficiencia', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(res.value)
        })
        .then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t || `HTTP ${r.status}`); }))
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Error al crear');
            Swal.fire({ icon:'success', title:'¡Eficiencia creada!', timer:2000, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'Error al crear la eficiencia.' }));
    });
}

/* ===========================
   Editar
=========================== */
function editarEficiencia() {
    if (!selectedRow || !selectedEficienciaId) {
        Swal.fire({ title:'Error', text:'Por favor selecciona una eficiencia para editar', icon:'warning' });
        return;
    }

    const salonActual    = selectedRow.getAttribute('data-salon');
    const telarActual    = selectedRow.getAttribute('data-telar');
    const fibraActual    = selectedRow.getAttribute('data-fibra');
    const efDecimal      = parseFloat(selectedRow.getAttribute('data-eficiencia-dec') || '0');
    const densidadActual = selectedRow.getAttribute('data-densidad') || 'Normal';

    Swal.fire({
        title: 'Editar Eficiencia',
        html: `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Salón *</label>
                    <select id="swal-salon-edit" class="w-full px-2 py-1 border border-gray-300 rounded text-center" required>
                        <option value="JACQUARD" ${salonActual === 'JACQUARD' ? 'selected' : ''}>JACQUARD</option>
                        <option value="SMITH" ${salonActual === 'SMITH' ? 'selected' : ''}>SMITH</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Telar *</label>
                    <select id="swal-telar-edit" class="w-full px-2 py-1 border border-gray-300 rounded text-center" required></select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hilo *</label>
                    <input id="swal-fibra-edit" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-center" maxlength="15" required value="${fibraActual}">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Densidad</label>
                    <select id="swal-densidad-edit" class="w-full px-2 py-1 border border-gray-300 rounded text-center">
                        <option value="Normal" ${densidadActual === 'Normal' ? 'selected' : ''}>Normal</option>
                        <option value="Alta" ${densidadActual === 'Alta' ? 'selected' : ''}>Alta</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Eficiencia: <span id="eficiencia-value-edit" class="font-bold text-blue-600">${Math.round(efDecimal * 100)}%</span>
                    </label>
                    <input id="swal-eficiencia-edit" type="range" min="0" max="100" value="${Math.round(efDecimal * 100)}" step="1"
                           class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider-compact"
                           oninput="document.getElementById('eficiencia-value-edit').textContent = this.value + '%';">
                </div>
            </div>
        `,
        width: '420px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save me-2"></i>Actualizar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        didOpen: () => {
            const salonSel = document.getElementById('swal-salon-edit');
            const telarSel = document.getElementById('swal-telar-edit');

            repoblarSelect(telarSel, telaresPorSalon[salonSel.value] || [], telarActual);

            salonSel.addEventListener('change', () => {
                repoblarSelect(telarSel, telaresPorSalon[salonSel.value] || [], '');
            });
        },
        preConfirm: () => {
            const SalonTejidoId = document.getElementById('swal-salon-edit').value;
            const NoTelarId     = document.getElementById('swal-telar-edit').value.trim();
            const FibraId       = document.getElementById('swal-fibra-edit').value.trim();
            const Densidad      = document.getElementById('swal-densidad-edit').value;
            const pct           = document.getElementById('swal-eficiencia-edit').value;

            if (!SalonTejidoId || !NoTelarId || !FibraId) {
                Swal.showValidationMessage('Por favor completa los campos requeridos'); return false;
            }
            const Eficiencia = Number(pct) / 100;
            if (isNaN(Eficiencia) || Eficiencia < 0 || Eficiencia > 1) {
                Swal.showValidationMessage('La eficiencia debe estar entre 0% y 100%'); return false;
            }
            return { NoTelarId, SalonTejidoId, FibraId, Eficiencia, Densidad };
        }
    }).then((res) => {
        if (!res.isConfirmed) return;
        Swal.fire({ title:'Actualizando...', allowOutsideClick:false, showConfirmButton:false, didOpen:Swal.showLoading });

        fetch(`/planeacion/eficiencia/${selectedEficienciaId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(res.value)
        })
        .then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t || `HTTP ${r.status}`); }))
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Error al actualizar');
            Swal.fire({ icon:'success', title:'¡Eficiencia actualizada!', timer:1800, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'No se pudo actualizar la eficiencia.' }));
    });
}

/* ===========================
   Eliminar
=========================== */
function eliminarEficiencia() {
    if (!selectedRow || !selectedEficienciaId) {
        Swal.fire({ title:'Error', text:'Selecciona una eficiencia para eliminar', icon:'warning' });
        return;
    }

    Swal.fire({
        title: '¿Eliminar Eficiencia?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({ title:'Eliminando...', allowOutsideClick:false, showConfirmButton:false, didOpen:Swal.showLoading });

        fetch(`/planeacion/eficiencia/${selectedEficienciaId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t || `HTTP ${r.status}`); }))
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Error al eliminar');
            Swal.fire({ icon:'success', title:'¡Eficiencia eliminada!', timer:1800, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'No se pudo eliminar la eficiencia.' }));
    });
}

/* ===========================
   Filtros (modal) — IDs -filter
=========================== */
function mostrarFiltros() {
    Swal.fire({
        title: 'Filtrar Eficiencias',
        html: `
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Salón</label>
              <select id="swal-salon-filter" class="w-full px-2 py-1 border border-gray-300 rounded text-center">
                <option value="">Seleccionar</option>
                <option value="JACQUARD">JACQUARD</option>
                <option value="SMITH">SMITH</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Telar</label>
              <select id="swal-telar-filter" class="w-full px-2 py-1 border border-gray-300 rounded text-center">
                <option value="">Seleccionar</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Tipo de Hilo</label>
              <input id="swal-fibra-filter" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-center" placeholder="H, PAP, FIL370">
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Densidad</label>
              <select id="swal-densidad-filter" class="w-full px-2 py-1 border border-gray-300 rounded text-center">
                <option value="">Todas</option>
                <option value="Normal">Normal</option>
                <option value="Alta">Alta</option>
              </select>
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-gray-600 mb-1">Eficiencia Mínima (%)</label>
              <div class="flex items-center space-x-2">
                <input id="swal-eficiencia-min-filter" type="range" min="0" max="100" value="0"
                      class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider-compact"
                      oninput="document.getElementById('eficiencia-min-value-filter').textContent = this.value + '%'">
                <span id="eficiencia-min-value-filter" class="text-xs font-bold text-blue-600 w-12">0%</span>
              </div>
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-gray-600 mb-1">Eficiencia Máxima (%)</label>
              <div class="flex items-center space-x-2">
                <input id="swal-eficiencia-max-filter" type="range" min="0" max="100" value="100"
                      class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider-compact"
                      oninput="document.getElementById('eficiencia-max-value-filter').textContent = this.value + '%'">
                <span id="eficiencia-max-value-filter" class="text-xs font-bold text-blue-600 w-12">100%</span>
              </div>
            </div>
          </div>
          <div class="mt-3 text-xs text-gray-500 bg-blue-50 p-2 rounded">
            <i class="fas fa-info-circle mr-1"></i>Deja campos vacíos para no aplicar filtro.
          </div>
        `,
        width: '420px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-search mr-2"></i>Filtrar',
        cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        didOpen: () => {
            const salonSel = document.getElementById('swal-salon-filter');
            const telarSel = document.getElementById('swal-telar-filter');
            const fibraInp = document.getElementById('swal-fibra-filter');
            const densSel  = document.getElementById('swal-densidad-filter');
            const minR     = document.getElementById('swal-eficiencia-min-filter');
            const maxR     = document.getElementById('swal-eficiencia-max-filter');

            // Prefill
            salonSel.value = filtrosActuales.salon || '';
            fibraInp.value = filtrosActuales.fibra || '';
            densSel.value  = filtrosActuales.densidad || '';
            minR.value     = filtrosActuales.eficiencia_min || 0;
            maxR.value     = filtrosActuales.eficiencia_max || 100;
            document.getElementById('eficiencia-min-value-filter').textContent = (filtrosActuales.eficiencia_min || 0) + '%';
            document.getElementById('eficiencia-max-value-filter').textContent = (filtrosActuales.eficiencia_max || 100) + '%';

            // Repoblar telar según salón
            repoblarSelect(telarSel, telaresPorSalon[salonSel.value] || [], filtrosActuales.telar || '');

            salonSel.addEventListener('change', () => {
                repoblarSelect(telarSel, telaresPorSalon[salonSel.value] || [], '');
            });
        },
        preConfirm: () => {
            const salon = document.getElementById('swal-salon-filter').value.trim();
            const telar = document.getElementById('swal-telar-filter').value.trim();
            const fibra = document.getElementById('swal-fibra-filter').value.trim();
            const densidad = document.getElementById('swal-densidad-filter').value;
            const eficienciaMin = document.getElementById('swal-eficiencia-min-filter').value;
            const eficienciaMax = document.getElementById('swal-eficiencia-max-filter').value;

            if (Number(eficienciaMin) > Number(eficienciaMax)) {
                Swal.showValidationMessage('La eficiencia mínima no puede ser mayor que la máxima');
                return false;
            }
            return { salon, telar, fibra, densidad, eficiencia_min: eficienciaMin, eficiencia_max: eficienciaMax };
        }
    }).then(res => {
        if (res.isConfirmed && res.value) {
            aplicarFiltros(res.value);
        }
    });
}

function aplicarFiltros(f) {
    filtrosActuales = { ...f };

    const cacheKey = JSON.stringify(filtrosActuales);
    if (cacheFiltros.has(cacheKey)) {
        const cached = cacheFiltros.get(cacheKey);
        datosActuales = cached;
        actualizarTablaOptimizada(cached);
        actualizarContador();
        crearToast('success', `${cached.length} de ${datosOriginales.length} registros mostrados`);
        return;
    }

    const fMin = f.eficiencia_min ? Number(f.eficiencia_min) / 100 : null;
    const fMax = f.eficiencia_max ? Number(f.eficiencia_max) / 100 : null;

    const filtrados = (datosOriginales || []).filter(it => {
        const salon = String(it.SalonTejidoId || '').toLowerCase();
        const telar = String(it.NoTelarId || '').toLowerCase();
        const fibra = String(it.FibraId || '').toLowerCase();
        const dens  = (it.Densidad || 'Normal');

        if (f.salon && !salon.includes(f.salon.toLowerCase())) return false;
        if (f.telar && !telar.includes(f.telar.toLowerCase())) return false;
        if (f.fibra && !fibra.includes(f.fibra.toLowerCase())) return false;
        if (f.densidad && dens !== f.densidad) return false;

        const ef = Number(it.Eficiencia || 0); // 0..1
        if (fMin !== null && ef < fMin) return false;
        if (fMax !== null && ef > fMax) return false;

        return true;
    });

    if (cacheFiltros.size >= 10) cacheFiltros.delete(cacheFiltros.keys().next().value);
    cacheFiltros.set(cacheKey, filtrados);

    datosActuales = filtrados;
    actualizarTablaOptimizada(filtrados);
    actualizarContador();
    crearToast('success', `${filtrados.length} de ${datosOriginales.length} registros mostrados`);
}

function limpiarFiltros() {
    filtrosActuales = { salon:'', telar:'', fibra:'', densidad:'', eficiencia_min:'', eficiencia_max:'' };
    cacheFiltros.clear();
    datosActuales = datosOriginales;
    actualizarTablaOptimizada(datosOriginales);
    actualizarContador();
    crearToast('success', `Filtros limpiados - Mostrando ${datosOriginales.length} registros`, 1800);
}

/* ===========================
   Render tabla
=========================== */
function actualizarTablaOptimizada(datos) {
    const tbody = document.getElementById('eficiencia-body');
    if (!datos || datos.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-8 text-gray-500">
                    <i class="fas fa-search text-4xl mb-2"></i><br>No se encontraron resultados
                </td>
            </tr>`;
        return;
    }
    const frag = document.createDocumentFragment();
    datos.forEach(item => {
        const tr = document.createElement('tr');
        const uniqueId = item.NoTelarId + '_' + item.FibraId;
        const pct = Math.round((Number(item.Eficiencia || 0)) * 100);

        tr.className = 'text-center hover:bg-blue-50 transition cursor-pointer';
        tr.onclick = () => selectRow(tr, uniqueId, item.Id || null);
        tr.ondblclick = () => deselectRow(tr);
        tr.setAttribute('data-eficiencia', uniqueId);
        tr.setAttribute('data-eficiencia-id', item.Id || 'null');
        tr.setAttribute('data-salon', item.SalonTejidoId);
        tr.setAttribute('data-telar', item.NoTelarId);
        tr.setAttribute('data-fibra', item.FibraId);
        tr.setAttribute('data-eficiencia-dec', item.Eficiencia);
        tr.setAttribute('data-densidad', item.Densidad || 'Normal');

        tr.innerHTML = `
            <td class="py-1 px-4 border-b">${item.SalonTejidoId}</td>
            <td class="py-1 px-4 border-b">${item.NoTelarId}</td>
            <td class="py-1 px-4 border-b">${item.FibraId}</td>
            <td class="py-1 px-4 border-b font-semibold">${pct}%</td>
            <td class="py-1 px-4 border-b">${item.Densidad || 'Normal'}</td>`;
        frag.appendChild(tr);
    });
    tbody.innerHTML = '';
    tbody.appendChild(frag);
}

function actualizarContador() {
    const el = document.getElementById('filter-count');
    if (!el) return;
    const n = Object.values(filtrosActuales).filter(v => v !== '' && v !== null && v !== undefined).length;
    if (n > 0) { el.textContent = n; el.classList.remove('hidden'); }
    else { el.classList.add('hidden'); }
}

/* ===========================
   Bootstrap
=========================== */
document.addEventListener('DOMContentLoaded', () => {
    disableButtons();
});

// Funciones globales para el navbar
window.filtrarEficiencia = function() {
    mostrarFiltros();
};

window.limpiarFiltrosEficiencia = function() {
    limpiarFiltros();
};

window.agregarEficiencia = function() {
    agregarEficienciaLocal();
};
</script>
@endsection
