@extends('layouts.app')

@php
    // Determinar si es modo edición basado en el parámetro folio en la URL
    $esModoEdicion = request()->has('folio');
    $tituloPagina = $esModoEdicion ? 'Editar Marcas Finales' : 'Nuevas Marcas Finales';
@endphp

@section('page-title', $tituloPagina)

@section('navbar-right')
@php
    // Permisos del módulo
    $permisosMarcas = userPermissions('Marcas Finales') ?? userPermissions('Nuevas Marcas Finales');
    $puedeCrear     = (bool)($permisosMarcas->crear     ?? false);
    $puedeModificar = (bool)($permisosMarcas->modificar ?? false);
    $puedeEliminar  = (bool)($permisosMarcas->eliminar  ?? false);
    $tieneAcceso    = (bool)($permisosMarcas->acceso    ?? false);
    $puedeEditar    = $puedeCrear || $puedeModificar;

    // Columnas editables con su "type" para JS
    $colsEditables = [
        ['key' => 'efi',    'label' => '% Efi'],
        ['key' => 'marcas', 'label' => 'Marcas'],
        ['key' => 'trama',  'label' => 'Trama'],
        ['key' => 'pie',    'label' => 'Pie'],
        ['key' => 'rizo',   'label' => 'Rizo'],
        ['key' => 'otros',  'label' => 'Otros'],
    ];
@endphp

<!-- Badge de folio (se muestra cuando exista folio activo) -->
<div id="badge-folio" class="hidden md:flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg shadow-md">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709M15 10a3 3 0 11-6 0 3 3 0 016 0z"/>
    </svg>
    <span class="text-sm font-semibold">Folio:</span>
</div>
@endsection

@section('content')
<!-- Alertas flotantes -->
<div id="alert-container" class="fixed top-4 right-4 z-50 space-y-2 max-w-[400px]"></div>

<div class="container">
    <!-- Tabla principal -->
    <div id="segunda-tabla" class="bg-white border rounded-md overflow-hidden">
        <div class="overflow-x-auto">
            <div class="overflow-y-auto" style="max-height: 80vh;">
                <table class="min-w-full text-sm">
                    <thead class="bg-blue-600 text-white">
                        <tr>
                            <th class="px-2 py-2 text-center uppercase text-xs sticky top-0 z-30 min-w-[60px]">Telar</th>
                            <th class="px-2 py-2 text-center uppercase text-xs sticky top-0 z-30 min-w-[80px]">Salón</th>

                            @foreach($colsEditables as $col)
                                <th class="px-3 py-2 text-center uppercase text-xs sticky top-0 z-30 min-w-[100px]">
                                    {{ $col['label'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody id="telares-body" class="divide-y divide-gray-100">
                    @foreach(($telares ?? []) as $telar)
                        <tr class="hover:bg-blue-50">
                            <!-- Telar -->
                            <td class="px-2 py-1 text-xs font-semibold text-gray-900 text-center whitespace-nowrap">
                                {{ $telar->NoTelarId }}
                            </td>

                            <!-- Salón (badge) -->
                            <td class="px-2 py-1 text-xs text-center whitespace-nowrap">
                                <span class="inline-block px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-medium"
                                      data-telar="{{ $telar->NoTelarId }}" data-field="salon">
                                    {{ $telar->SalonId ?? '-' }}
                                </span>
                            </td>

                            <!-- Celdas editables (DRY) -->
                            @foreach($colsEditables as $col)
                                <td class="px-2 py-2 {{ !$loop->last ? 'border-r border-gray-200' : '' }}">
                                    <div class="relative">
                                        @if($puedeEditar)
                                            <button type="button"
                                                class="valor-display-btn w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-900
                                                       hover:bg-blue-50 hover:border-blue-400 transition-colors flex items-center justify-between bg-white shadow-sm"
                                                data-telar="{{ $telar->NoTelarId }}" data-type="{{ $col['key'] }}">
                                                <span class="valor-display-text text-blue-600 font-semibold">
                                                    {{ $col['key'] === 'efi' ? '-' : '0' }}
                                                </span>
                                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>

                                            <div class="valor-edit-container hidden absolute left-1/2 bottom-full mb-2 bg-white border-2 border-blue-300 rounded-lg shadow-xl z-[100]"
                                                 style="transform: translateX(-50%);">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide max-w-[300px]">
                                                    <div class="number-options-flex p-2 flex gap-1"></div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="w-full px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-400 bg-gray-100
                                                        cursor-not-allowed flex items-center justify-between">
                                                <span class="valor-display-text text-gray-500 font-semibold">
                                                    {{ $col['key'] === 'efi' ? '-' : '0' }}
                                                </span>
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v-3m0 0V9m0 3h3m-3 0H9"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
/* =========================
   Estado global + helpers
   ========================= */
let currentFolio   = null;
let isEditing      = false;
let isNewRecord    = true;
let guardarTimeout = null;

const q  = (s, sc=document) => sc.querySelector(s);
const qa = (s, sc=document) => [...sc.querySelectorAll(s)];

const elements = {
    folio:        null,
    fecha:        null,
    turno:        null,
    usuario:      null,
    noEmpleado:   null,
    status:       null,
    segundaTabla: null,
    headerSection:null,
    badgeFolio: () => document.getElementById('badge-folio'),
    folioText:  () => document.getElementById('folio-text'),
};

// Rango permitido por tipo
const RANGOS = {
    marcas: [100, 250],
    efi:    [0,   100],
    trama:  [1,   100],
    pie:    [1,   100],
    rizo:   [1,   100],
    otros:  [1,   100],
};
// Orden para recorrer al guardar
const CAMPOS = ['efi', 'trama', 'pie', 'rizo', 'otros', 'marcas'];

/* =========================
   Inicialización
   ========================= */
document.addEventListener('DOMContentLoaded', () => {
    initElements();

    // Delegación de clicks
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.valor-display-btn');
        if (btn) return toggleValorSelector(btn);

        const opt = e.target.closest('.number-option');
        if (opt) return selectNumberOption(opt);

        // Clic fuera: cerrar selectores
        if (!e.target.closest('.valor-edit-container')) closeAllValorSelectors();
    });

    // Cambio de turno => guardar
    if (elements.turno) elements.turno.addEventListener('change', guardarAutomatico);

    // Edición por folio o nuevo
    const folioUrl = new URLSearchParams(window.location.search).get('folio');

    if (folioUrl) {
        // Modo edición: cargar marca existente primero, luego datos STD
        cargarMarcaExistente(folioUrl)
            .then(() => {
                // Después de cargar la marca, cargar datos STD solo para actualizar vacíos
                cargarDatosSTD(true);
            })
            .catch(err => {
                console.error('Error al cargar marca existente:', err);
                Swal.fire('Error', 'No se pudo cargar la marca', 'error');
            });
    } else {
        // Modo nuevo: generar folio y cargar datos STD en paralelo
        cargarDatosSTD(false);
        generarNuevoFolio();
    }

    // Guardar al salir
    window.addEventListener('beforeunload', () => currentFolio && guardarDatosTabla());
    window.addEventListener('popstate',     () => currentFolio && guardarDatosTabla());
});

/* =========================
   UI helpers
   ========================= */
function initElements() {
    elements.folio        = q('#folio');
    elements.fecha        = q('#fecha');
    elements.turno        = q('#turno');
    elements.usuario      = q('#usuario');
    elements.noEmpleado   = q('#noEmpleado');
    elements.status       = q('#status');
    elements.segundaTabla = q('#segunda-tabla');
    elements.headerSection= q('#header-section');
}

function actualizarBadgeFolio() {
    const badge = elements.badgeFolio();
    const text  = elements.folioText();
    if (!badge || !text) return;
    if (currentFolio) {
        text.textContent = currentFolio;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function mostrarAlerta(mensaje, tipo='success') {
    const container = q('#alert-container'); if (!container) return;
    const color = {
        success: ['bg-green-500','text-white','<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'],
        error:   ['bg-red-500','text-white','<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>'],
        warning: ['bg-yellow-500','text-white','<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>'],
        info:    ['bg-blue-500','text-white','<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
    }[tipo] || ['bg-green-500','text-white',''];

    const id = 'alert-' + Date.now();
    const el = document.createElement('div');
    el.id = id;
    el.className = `${color[0]} ${color[1]} px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 animate-slide-in-right`;
    el.innerHTML = `
        <div class="flex-shrink-0">${color[2]}</div>
        <div class="flex-1 text-sm font-medium">${mensaje}</div>
        <button onclick="cerrarAlerta('${id}')" class="flex-shrink-0 hover:opacity-75">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>`;
    container.appendChild(el);
    setTimeout(() => cerrarAlerta(id), 5000);
}
function cerrarAlerta(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('animate-slide-out-right');
    setTimeout(() => el.remove(), 300);
}

/* =========================
   Selectores numéricos
   ========================= */
function parseValorDisplay(text, tipo) {
    if (!text) return 0;
    const n = parseInt(String(text).replace('%',''), 10);
    if (Number.isNaN(n)) return 0;
    const [min, max] = RANGOS[tipo] || [0,100];
    return Math.min(max, Math.max(min, n));
}

function toggleValorSelector(btn) {
    closeAllValorSelectors();
    const selector = btn.parentElement.querySelector('.valor-edit-container');
    const tipo     = btn.dataset.type;

    if (selector.classList.contains('hidden')) {
        const currentText  = btn.querySelector('.valor-display-text')?.textContent || '0';
        const currentValue = parseValorDisplay(currentText, tipo);
        buildNumberOptions(selector, tipo, currentValue);
        selector.classList.remove('hidden');
        scrollToCurrentValue(selector, currentValue);
    } else {
        selector.classList.add('hidden');
    }
}

function closeAllValorSelectors() {
    qa('.valor-edit-container').forEach(c => c.classList.add('hidden'));
}

function buildNumberOptions(selector, tipo, current) {
    const container = selector.querySelector('.number-options-flex');
    container.innerHTML = '';

    const [min, max] = RANGOS[tipo] || [0, 100];
    const frag = document.createDocumentFragment();

    for (let i = min; i <= max; i++) {
        const opt = document.createElement('span');
        opt.className = 'number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded bg-gray-100 text-gray-700';
        opt.dataset.value = String(i);
        opt.textContent = String(i);
        if (i === current) opt.classList.add('selected','bg-blue-500','text-white');
        frag.appendChild(opt);
    }
    container.appendChild(frag);
}

function selectNumberOption(option) {
    const value    = parseInt(option.dataset.value, 10);
    const selector = option.closest('.valor-edit-container');
    const btn      = selector.previousElementSibling;
    const tipo     = btn.dataset.type;
    const span     = btn.querySelector('.valor-display-text');

    span.textContent = (tipo === 'efi') ? `${value}%` : String(value);
    selector.classList.add('hidden');
    guardarAutomatico();
}

function scrollToCurrentValue(selector, value) {
    setTimeout(() => {
        const sc = selector.querySelector('.number-scroll-container');
        const op = selector.querySelector(`.number-option[data-value="${value}"]`);
        if (sc && op) sc.scrollLeft = op.offsetLeft - (sc.clientWidth/2) + (op.offsetWidth/2);
    }, 10);
}

/* =========================
   Guardado automático
   ========================= */
function guardarAutomatico() {
    if (guardarTimeout) clearTimeout(guardarTimeout);
    guardarTimeout = setTimeout(guardarDatosTabla, 1000);
}

function guardarDatosTabla() {
    if (!currentFolio) return;

    const lineas = [];
    qa('#telares-body tr').forEach(row => {
        const telar = row.querySelector('td:first-child')?.textContent?.trim();
        if (!telar) return;

        const datos = { NoTelarId: telar };
        CAMPOS.forEach(t => {
            const text = row.querySelector(`button[data-telar="${telar}"][data-type="${t}"] .valor-display-text`)?.textContent || '0';
            const val  = parseValorDisplay(text, t);
            datos[(t === 'efi' ? 'PorcentajeEfi' : t.charAt(0).toUpperCase()+t.slice(1))] = val;
        });
        lineas.push(datos);
    });

    const payload = {
        folio:  currentFolio,
        fecha:  elements.fecha?.value,
        turno:  elements.turno?.value,
        status: elements.status?.value,
        lineas
    };

    fetch('/modulo-marcas/store', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': q('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            mostrarAlerta(isNewRecord ? 'Folio creado y guardado' : 'Datos actualizados', 'success');
            isNewRecord = false;
        } else {
            mostrarAlerta('Error al guardar: ' + (d.message || 'desconocido'), 'error');
        }
    })
    .catch(() => mostrarAlerta('Error de red al guardar', 'error'));
}

/* =========================
   Folio + datos STD
   ========================= */
function generarNuevoFolio() {
    return fetch('/modulo-marcas/generar-folio', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': q('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(d => {
        if (!d.folio) throw new Error('Sin folio');
        currentFolio = d.folio;
        isNewRecord  = true;
        isEditing    = true;
        actualizarBadgeFolio();

        if (elements.folio) elements.folio.value = d.folio;
        if (elements.fecha) elements.fecha.value = new Date().toISOString().split('T')[0];
        if (elements.turno) elements.turno.value = d.turno || '1';
        if (elements.status) elements.status.value = 'En Proceso';
        if (elements.usuario) elements.usuario.value = d.usuario || '';
        if (elements.noEmpleado) elements.noEmpleado.value = d.numero_empleado || '';
        if (elements.headerSection) elements.headerSection.style.display = 'block';
        return d;
    })
    .catch(() => Swal.fire('Error', 'No se pudo generar el folio', 'error'));
}

function cargarDatosSTD(soloVacios=false) {
    // Iniciar fetch inmediatamente sin esperar
    const fetchPromise = fetch('/modulo-marcas/obtener-datos-std', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': q('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        cache: 'no-cache' // Evitar caché para datos frescos
    })
    .then(r => {
        if (!r.ok) throw new Error('Error en respuesta');
        return r.json();
    })
    .then(d => {
        if (!d.success || !Array.isArray(d.datos)) return;

        // Usar requestAnimationFrame para actualizar DOM de forma eficiente
        requestAnimationFrame(() => {
            d.datos.forEach(item => {
                // Salón - actualizar siempre
                const salon = q(`span[data-telar="${item.telar}"][data-field="salon"]`);
                if (salon) salon.textContent = item.salon || '-';

                // % Efi - solo actualizar si está vacío o si no es modo soloVacios
                const span = q(`button[data-telar="${item.telar}"][data-type="efi"] .valor-display-text`);
                if (!span) return;

                const tieneValor = span.textContent && span.textContent !== '-' && span.textContent !== '0%';
                if (!soloVacios || !tieneValor) {
                    const p = (item.porcentaje_efi ?? null);
                    span.textContent = (p && p > 0) ? `${p}%` : '-';
                }
            });
        });
    })
    .catch(err => {
        console.warn('Error al cargar datos STD:', err);
        // No mostrar error al usuario, solo log
    });

    return fetchPromise;
}

function cargarMarcaExistente(folio) {
    return fetch(`/modulo-marcas/${folio}`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': q('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        cache: 'no-cache'
    })
    .then(r => {
        if (!r.ok) {
            throw new Error(`HTTP error! status: ${r.status}`);
        }
        return r.json();
    })
    .then(d => {
        if (!d.success) {
            throw new Error(d.message || 'No se pudo cargar la marca');
        }

        currentFolio = folio;
        isNewRecord  = false;
        isEditing    = true;
        actualizarBadgeFolio();

        // Actualizar campos del header si existen
        if (elements.folio)  elements.folio.value  = d.marca.Folio || '';
        if (elements.fecha)  elements.fecha.value  = d.marca.Date || '';
        if (elements.turno)  elements.turno.value  = d.marca.Turno || '';
        if (elements.status) elements.status.value = d.marca.Status || 'En Proceso';
        if (elements.usuario) elements.usuario.value = d.marca.nombreEmpl || '';
        if (elements.noEmpleado) elements.noEmpleado.value = d.marca.numero_empleado || '';
        if (elements.headerSection) elements.headerSection.style.display = 'block';

        // Cargar líneas en la tabla
        (d.lineas || []).forEach(l => {
            const telar = l.NoTelarId;

            // Actualizar % Efi
            const efiVal = l.Eficiencia ?? l.EficienciaSTD ?? l.EficienciaStd ?? null;
            const efiPercent = efiVal !== null ? (typeof efiVal === 'number' ? Math.round(efiVal * 100) : parseInt(efiVal) || null) : null;
            const efiSpan = q(`button[data-telar="${telar}"][data-type="efi"] .valor-display-text`);
            if (efiSpan) {
                efiSpan.textContent = (efiPercent && efiPercent > 0) ? `${efiPercent}%` : '-';
            }

            // Actualizar otros campos
            const campos = {
                marcas: l.Marcas ?? 0,
                trama: l.Trama ?? 0,
                pie: l.Pie ?? 0,
                rizo: l.Rizo ?? 0,
                otros: l.Otros ?? 0
            };

            Object.entries(campos).forEach(([tipo, valor]) => {
                const span = q(`button[data-telar="${telar}"][data-type="${tipo}"] .valor-display-text`);
                if (span) {
                    span.textContent = valor ?? 0;
                }
            });
        });

        return d;
    })
    .catch(err => {
        console.error('Error al cargar marca existente:', err);
        Swal.fire('Error', 'No se pudo cargar la marca: ' + (err.message || 'Error desconocido'), 'error');
        throw err; // Re-lanzar para que el .then() no se ejecute
    });
}
</script>

<style>
table{border-collapse:separate;border-spacing:0}
tbody tr:hover{background-color:#eff6ff!important}
thead th{position:sticky;top:0;z-index:20}
.scrollbar-hide{-ms-overflow-style:none;scrollbar-width:none}
.scrollbar-hide::-webkit-scrollbar{display:none}
.valor-display-btn{transition:all .2s ease;min-width:80px}
.valor-display-btn:hover{transform:scale(1.02)}
.valor-edit-container{z-index:100!important;box-shadow:0 10px 25px rgba(0,0,0,.15)}
.number-option{transition:all .15s ease;flex-shrink:0}
.number-option:hover{transform:scale(1.1)}
.number-option.selected{background-color:#3b82f6!important;color:#fff!important;transform:scale(1.1)}
/* Alertas */
@keyframes slide-in-right{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes slide-out-right{from{transform:translateX(0);opacity:1}to{transform:translateX(100%);opacity:0}}
.animate-slide-in-right{animation:slide-in-right .3s ease-out}
.animate-slide-out-right{animation:slide-out-right .3s ease-out}
</style>
@endsection
