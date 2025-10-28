@extends('layouts.app')

@section('page-title', 'Catálogo de Velocidad')

@section('content')
    <div class="container">


    <!-- Tabla -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-y-auto h-[640px] scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100">
                <table class="table table-bordered table-sm w-full">
                    <thead class="sticky top-0 bg-blue-500 text-white z-10">
                        <tr>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Salón</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Telar</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Fibra</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">RPM</th>
                        <th class="py-1 px-2 font-bold  tracking-wider text-center">Densidad</th>
                </tr>
            </thead>
                    <tbody id="velocidad-body" class="bg-white text-black">
                @foreach ($velocidad as $item)
                        @php
                            $uniqueId = $item->NoTelarId . '_' . $item->FibraId;
                            $recordId = $item->Id ?: $item->SalonTejidoId . '_' . $item->NoTelarId . '_' . $item->FibraId;
                        @endphp
                        <tr class="text-center hover:bg-blue-50 transition cursor-pointer"
                            onclick="selectRow(this, '{{ $uniqueId }}', '{{ $recordId }}')"
                                ondblclick="deselectRow(this)"
                            data-velocidad="{{ $uniqueId }}"
                            data-velocidad-id="{{ $recordId }}"
                            data-salon="{{ $item->SalonTejidoId }}"
                            data-telar="{{ $item->NoTelarId }}"
                            data-fibra="{{ $item->FibraId }}"
                            data-rpm="{{ $item->Velocidad }}"
                            data-densidad="{{ $item->Densidad ?? 'Normal' }}"
                        >
                            <td class="py-1 px-4 border-b">{{ $item->SalonTejidoId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->NoTelarId }}</td>
                            <td class="py-1 px-4 border-b">{{ $item->FibraId }}</td>
                            <td class="py-1 px-4 border-b font-semibold">{{ $item->Velocidad }} RPM</td>
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

<style>
    .slider-compact {
        -webkit-appearance: none; appearance: none;
        height: 6px; outline: none; border-radius: 3px; background: #e5e7eb;
    }
    .slider-compact::-webkit-slider-thumb {
        -webkit-appearance: none; appearance: none;
        width: 18px; height: 18px; background: #3b82f6; cursor: pointer;
        border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }
    .slider-compact::-moz-range-thumb {
        width: 18px; height: 18px; background: #3b82f6; cursor: pointer;
        border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }
    .slider-compact::-moz-range-track { height: 6px; border-radius: 3px; background: #e5e7eb; }
</style>

    <script>
/* ===========================
   Estado y datos
=========================== */
let selectedRow = null;
let selectedKey = null;
let selectedId = null;

let filtrosActuales = { salon:'', telar:'', fibra:'', densidad:'', velocidad_min:'', velocidad_max:'' };
        let datosOriginales = @json($velocidad);
        let datosActuales = datosOriginales;
        const cacheFiltros = new Map();

/* ===========================
   Catálogo de telares
=========================== */
const telaresPorSalon = {
    'JACQUARD': [201,202,203,204,205,206,207,208,209,210,211,212,213,214,215,216,217,218,219,220],
    'SMITH':   [299,300,301,302,303,304,305,306,307,308,309,310,311,312,313,314,315,316,317,318,319,320]
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
function selectRow(row, uniqueId, id) {
    document.querySelectorAll('#velocidad-body tr').forEach(r => {
        r.classList.remove('bg-blue-500','text-white');
        r.classList.add('hover:bg-blue-50');
    });
    row.classList.remove('hover:bg-blue-50');
    row.classList.add('bg-blue-500','text-white');

    selectedRow = row;
    selectedKey = uniqueId;
    selectedId = id;
    enableButtons();
}
function deselectRow(row) {
    if (!row.classList.contains('bg-blue-500')) return;
    row.classList.remove('bg-blue-500','text-white');
    row.classList.add('hover:bg-blue-50');
    selectedRow = null;
    selectedKey = null;
    selectedId = null;
    disableButtons();
}

/* ===========================
   Crear
=========================== */
function actualizarTelaresCreate() {
    const salon = document.getElementById('swal-salon').value;
    repoblarSelect(document.getElementById('swal-telar'), telaresPorSalon[salon] || [], '');
}
        function agregarVelocidadLocal() {
            Swal.fire({
                title: 'Crear Nueva Velocidad',
                html: `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Salón *</label>
                    <select id="swal-salon" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" required onchange="actualizarTelaresCreate()">
                        <option value="">Seleccionar</option>
                        <option value="JACQUARD">JACQUARD</option>
                        <option value="SMITH">SMITH</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Telar *</label>
                    <select id="swal-telar" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" required>
                        <option value="">Seleccionar</option>
                    </select>
                        </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Fibra *</label>
                    <input id="swal-fibra" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="H, PAP" maxlength="20" required>
                        </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Densidad</label>
                    <select id="swal-densidad" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                        <option value="Normal" selected>Normal</option>
                        <option value="Alta">Alta</option>
                    </select>
                        </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Velocidad (RPM) *</label>
                    <input id="swal-velocidad" type="number" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="850" min="0" step="1" required>
                        </div>
                    </div>
                `,
        width: '420px',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save me-2"></i>Crear',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                preConfirm: () => {
            const SalonTejidoId = document.getElementById('swal-salon').value.trim();
            const NoTelarId     = document.getElementById('swal-telar').value.trim();
            const FibraId       = document.getElementById('swal-fibra').value.trim();
            const Densidad      = document.getElementById('swal-densidad').value.trim();
            const Velocidad     = document.getElementById('swal-velocidad').value.trim();

            if (!SalonTejidoId || !NoTelarId || !FibraId || !Velocidad) {
                Swal.showValidationMessage('Por favor completa los campos requeridos'); return false;
            }
            const v = Number(Velocidad);
            if (!Number.isFinite(v) || v < 0) { Swal.showValidationMessage('La velocidad debe ser un número válido'); return false; }
            return { SalonTejidoId, NoTelarId, FibraId, Densidad, Velocidad: v };
        }
    }).then((res) => {
        if (!res.isConfirmed) return;
        Swal.fire({ title:'Creando...', allowOutsideClick:false, showConfirmButton:false, didOpen:Swal.showLoading });

        fetch('/planeacion/velocidad', {
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
            Swal.fire({ icon:'success', title:'¡Velocidad creada!', timer:2000, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'Error al crear la velocidad.' }));
    });
}

/* ===========================
   Editar
=========================== */
        function editarVelocidad() {
    if (!selectedRow || !selectedId) {
        Swal.fire({ title:'Error', text:'Selecciona una velocidad para editar', icon:'warning' });
                return;
            }

    const salonActual    = selectedRow.getAttribute('data-salon');
    const telarActual    = selectedRow.getAttribute('data-telar');
    const fibraActual    = selectedRow.getAttribute('data-fibra');
    const rpmActual      = Number(selectedRow.getAttribute('data-rpm') || '0');
    const densidadActual = selectedRow.getAttribute('data-densidad') || 'Normal';

            Swal.fire({
                title: 'Editar Velocidad',
                html: `
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Salón *</label>
                    <select id="swal-salon-edit" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" required>
                        <option value="JACQUARD" ${salonActual === 'JACQUARD' ? 'selected' : ''}>JACQUARD</option>
                        <option value="SMITH" ${salonActual === 'SMITH' ? 'selected' : ''}>SMITH</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Telar *</label>
                    <select id="swal-telar-edit" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" required></select>
                        </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Fibra *</label>
                    <input id="swal-fibra-edit" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" maxlength="20" required value="${fibraActual}">
                        </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Densidad</label>
                    <select id="swal-densidad-edit" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                        <option value="Normal" ${densidadActual === 'Normal' ? 'selected' : ''}>Normal</option>
                        <option value="Alta" ${densidadActual === 'Alta' ? 'selected' : ''}>Alta</option>
                    </select>
                        </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Velocidad (RPM) *</label>
                    <input id="swal-velocidad-edit" type="number" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" min="0" step="1" required value="${rpmActual}">
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
            const SalonTejidoId = document.getElementById('swal-salon-edit').value.trim();
            const NoTelarId     = document.getElementById('swal-telar-edit').value.trim();
            const FibraId       = document.getElementById('swal-fibra-edit').value.trim();
            const Densidad      = document.getElementById('swal-densidad-edit').value.trim();
            const Velocidad     = document.getElementById('swal-velocidad-edit').value.trim();

            if (!SalonTejidoId || !NoTelarId || !FibraId || !Velocidad) {
                Swal.showValidationMessage('Por favor completa los campos requeridos'); return false;
            }
            const v = Number(Velocidad);
            if (!Number.isFinite(v) || v < 0) { Swal.showValidationMessage('La velocidad debe ser un número válido'); return false; }
            return { SalonTejidoId, NoTelarId, FibraId, Densidad, Velocidad: v };
        }
    }).then((res) => {
        if (!res.isConfirmed) return;

        Swal.fire({ title:'Actualizando...', allowOutsideClick:false, showConfirmButton:false, didOpen:Swal.showLoading });

        fetch(`/planeacion/velocidad/${selectedId}`, {
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
            Swal.fire({ icon:'success', title:'¡Velocidad actualizada!', timer:1800, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'No se pudo actualizar la velocidad.' }));
    });
}

/* ===========================
   Eliminar
=========================== */
        function eliminarVelocidad() {
    if (!selectedRow || !selectedId) {
        Swal.fire({ title:'Error', text:'Selecciona una velocidad para eliminar', icon:'warning' });
                return;
            }

            Swal.fire({
                title: '¿Eliminar Velocidad?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Sí, eliminar',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancelar'
            }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({ title:'Eliminando...', allowOutsideClick:false, showConfirmButton:false, didOpen:Swal.showLoading });

        fetch(`/planeacion/velocidad/${selectedId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
        .then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t || `HTTP ${r.status}`); }))
                    .then(data => {
            if (!data.success) throw new Error(data.message || 'Error al eliminar');
            Swal.fire({ icon:'success', title:'¡Velocidad eliminada!', timer:1800, showConfirmButton:false })
                .then(() => location.reload());
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error', text: err.message || 'No se pudo eliminar la velocidad.' }));
    });
}

/* ===========================
   Filtros (modal)
=========================== */
        function mostrarFiltros() {
            Swal.fire({
                title: 'Filtrar Velocidades',
                html: `
          <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Salón</label>
              <select id="swal-salon-filter" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                <option value="">Seleccionar</option>
                <option value="JACQUARD" ${filtrosActuales.salon === 'JACQUARD' ? 'selected' : ''}>JACQUARD</option>
                <option value="SMITH" ${filtrosActuales.salon === 'SMITH' ? 'selected' : ''}>SMITH</option>
              </select>
                            </div>
                            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Telar</label>
              <select id="swal-telar-filter" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                <option value="">Seleccionar</option>
              </select>
                        </div>
                            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Fibra</label>
              <input id="swal-fibra-filter" type="text" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="H, PAP" value="${filtrosActuales.fibra || ''}">
                            </div>
                        <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Densidad</label>
              <select id="swal-densidad-filter" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                                    <option value="">Todas</option>
                                    <option value="Normal" ${filtrosActuales.densidad === 'Normal' ? 'selected' : ''}>Normal</option>
                                    <option value="Alta" ${filtrosActuales.densidad === 'Alta' ? 'selected' : ''}>Alta</option>
                            </select>
                        </div>
                            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Velocidad Mínima (RPM)</label>
              <input id="swal-velocidad-min-filter" type="number" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" min="0" value="${filtrosActuales.velocidad_min || ''}">
                            </div>
                        <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Velocidad Máxima (RPM)</label>
              <input id="swal-velocidad-max-filter" type="number" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" min="0" value="${filtrosActuales.velocidad_max || ''}">
                            </div>
                        </div>
          <div class="mt-3 text-xs text-gray-500 bg-blue-50 p-2 rounded">
            <i class="fas fa-info-circle mr-1"></i>Deja campos vacíos para no aplicar filtro.
                    </div>
                `,
        width: '420px',
                showCancelButton: true,
        confirmButtonText: '<i class="fas fa-filter mr-2"></i>Filtrar',
        cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
        didOpen: () => {
            // Prefill + telar por salón
            const salonSel = document.getElementById('swal-salon-filter');
            const telarSel = document.getElementById('swal-telar-filter');
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
            const velocidad_min = document.getElementById('swal-velocidad-min-filter').value.trim();
            const velocidad_max = document.getElementById('swal-velocidad-max-filter').value.trim();

            if (velocidad_min && velocidad_max && Number(velocidad_min) > Number(velocidad_max)) {
                Swal.showValidationMessage('La velocidad mínima no puede ser mayor que la máxima'); return false;
            }
            return { salon, telar, fibra, densidad, velocidad_min, velocidad_max };
        }
    }).then(res => {
        if (res.isConfirmed && res.value) aplicarFiltros(res.value);
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

    const minV = f.velocidad_min ? Number(f.velocidad_min) : null;
    const maxV = f.velocidad_max ? Number(f.velocidad_max) : null;

    const filtrados = (datosOriginales || []).filter(it => {
        const salon = String(it.SalonTejidoId || '').toLowerCase();
        const telar = String(it.NoTelarId || '').toLowerCase();
        const fibra = String(it.FibraId || '').toLowerCase();
        const dens  = (it.Densidad || 'Normal');

        if (f.salon && !salon.includes(f.salon.toLowerCase())) return false;
        if (f.telar && !telar.includes(f.telar.toLowerCase())) return false;
        if (f.fibra && !fibra.includes(f.fibra.toLowerCase())) return false;
        if (f.densidad && dens !== f.densidad) return false;

        const rpm = Number(it.Velocidad || 0);
        if (minV !== null && rpm < minV) return false;
        if (maxV !== null && rpm > maxV) return false;
        return true;
    });

    if (cacheFiltros.size >= 10) cacheFiltros.delete(cacheFiltros.keys().next().value);
    cacheFiltros.set(cacheKey, filtrados);

    datosActuales = filtrados;
    actualizarTablaOptimizada(filtrados);
    actualizarContador();
    crearToast('success', `${filtrados.length} de ${datosOriginales.length} registros mostrados`);
}

/* ===========================
   Render tabla
=========================== */
        function actualizarTablaOptimizada(datos) {
            const tbody = document.getElementById('velocidad-body');
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

        tr.className = 'text-center hover:bg-blue-50 transition cursor-pointer';
        tr.onclick = () => selectRow(tr, uniqueId, item.Id || null);
        tr.ondblclick = () => deselectRow(tr);

        tr.setAttribute('data-velocidad', uniqueId);
        tr.setAttribute('data-velocidad-id', item.Id || 'null');
        tr.setAttribute('data-salon', item.SalonTejidoId);
        tr.setAttribute('data-telar', item.NoTelarId);
        tr.setAttribute('data-fibra', item.FibraId);
        tr.setAttribute('data-rpm', item.Velocidad);
        tr.setAttribute('data-densidad', item.Densidad || 'Normal');

        tr.innerHTML = `
                    <td class="py-1 px-4 border-b">${item.SalonTejidoId}</td>
                    <td class="py-1 px-4 border-b">${item.NoTelarId}</td>
                    <td class="py-1 px-4 border-b">${item.FibraId}</td>
                    <td class="py-1 px-4 border-b font-semibold">${item.Velocidad} RPM</td>
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
window.filtrarVelocidad = function() {
    mostrarFiltros();
};

window.limpiarFiltrosVelocidad = function() {
    limpiarFiltros();
};

window.agregarVelocidad = function() {
    agregarVelocidadLocal();
};

function limpiarFiltros() {
    filtrosActuales = { salon:'', telar:'', fibra:'', densidad:'', velocidad_min:'', velocidad_max:'' };
    cacheFiltros.clear();
    datosActuales = datosOriginales;
    actualizarTablaOptimizada(datosOriginales);
    actualizarContador();
    crearToast('success', `Filtros limpiados - Mostrando ${datosOriginales.length} registros`, 2000);
}
    </script>
@endsection
