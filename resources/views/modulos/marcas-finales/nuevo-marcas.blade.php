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
    <span id="folio-text" class="text-sm font-bold ml-1">-</span>
</div>
@endsection

@section('content')
<!-- Contenedor principal sin alertas flotantes (ahora usa SweetAlert) -->

<div class="w-screen h-full overflow-hidden flex flex-col px-4 py-4 md:px-6 lg:px-8">
    <!-- Tabla principal -->
    <div id="segunda-tabla" class="flex flex-col flex-1 bg-white rounded-lg shadow-md overflow-hidden max-w-full">
        <!-- Header fijo (sticky) dentro del contenedor -->
        <div class="bg-blue-600 text-white sticky top-0 z-10">
            <table class="w-full text-sm">
                <colgroup>
                    <col style="width: 10%">
                    <col style="width: 10%">
                    <col style="width: 13.33%">
                    <col style="width: 13.33%">
                    <col style="width: 13.33%">
                    <col style="width: 13.33%">
                    <col style="width: 13.33%">
                    <col style="width: 13.33%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-center uppercase text-sm font-semibold">Telar</th>
                        <th class="px-4 py-3 text-center uppercase text-sm font-semibold">Salón</th>
                        @foreach($colsEditables as $col)
                            <th class="px-4 py-3 text-center uppercase text-sm font-semibold">
                                {{ $col['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            </table>
        </div>
        <!-- Solo el contenido con scroll -->
        <div class="flex-1 overflow-auto">
            <table class="w-full text-sm">
                <colgroup>
                    <col style="width: 10%">
                    <col style="width: 10%">
                    <col style="width: 13.33%">
                    <col style="width: 13.33%">
                    <col style="width: 13.33%">
                    <col style="width: 13.33%">
                    <col style="width: 13.33%">
                    <col style="width: 13.33%">
                </colgroup>
                <tbody id="telares-body" class="bg-white divide-y divide-gray-200">
                @foreach(($telares ?? []) as $telar)
                    <tr class="even:bg-gray-50 hover:bg-gray-100 transition-colors duration-150">
                        <!-- Telar -->
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 text-center border-r border-gray-200">
                            {{ $telar->NoTelarId }}
                        </td>

                        <!-- Salón (badge) -->
                        <td class="px-4 py-3 text-center border-r border-gray-200">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                  data-telar="{{ $telar->NoTelarId }}" data-field="salon">
                                {{ $telar->SalonId ?? '-' }}
                            </span>
                        </td>

                        <!-- Celdas editables (DRY) -->
                        @foreach($colsEditables as $col)
                            <td class="px-3 py-3 text-center {{ !$loop->last ? 'border-r border-gray-200' : '' }}">
                                <div class="relative">
                                    @if($puedeEditar)
                                        <button type="button"
                                            class="valor-display-btn w-full min-w-[70px] px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-700
                                                   hover:bg-blue-50 hover:border-blue-400 hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500
                                                   transition-all duration-200 flex items-center justify-between bg-white shadow-sm"
                                            data-telar="{{ $telar->NoTelarId }}" data-type="{{ $col['key'] }}">
                                            <span class="valor-display-text text-blue-600 font-semibold">
                                                {{ $col['key'] === 'efi' ? '0%' : '0' }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>

                                        <div class="valor-edit-container hidden absolute left-1/2 top-full mt-1 -translate-x-1/2 z-[9999]">
                                            <div class="number-scroll-container w-56 h-12 overflow-x-auto overflow-y-hidden bg-white border border-gray-300 rounded-md shadow-lg scrollbar-hide">
                                                <div class="number-options-flex px-2 py-1 flex items-center space-x-1 min-w-max whitespace-nowrap"></div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="w-full min-w-[70px] px-3 py-2 border border-gray-300 rounded text-sm font-medium text-gray-400 bg-gray-100
                                                    cursor-not-allowed flex items-center justify-between">
                                            <span class="valor-display-text text-gray-500 font-semibold">
                                                {{ $col['key'] === 'efi' ? '-' : '0' }}
                                            </span>
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
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
    marcas: [0,   250],
    efi:    [0,   100],
    trama:  [0,   100],
    pie:    [0,   100],
    rizo:   [0,   100],
    otros:  [0,   100],
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
    const tipoSwal = {
        success: 'success',
        error: 'error', 
        warning: 'warning',
        info: 'info'
    }[tipo] || 'success';

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: tipoSwal,
        title: mensaje,
        showConfirmButton: false,
        timer: 1300,
        timerProgressBar: true
    });
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
        let currentValue = parseValorDisplay(currentText, tipo);
        // Si es Efi y el valor mostrado es 0, usar recomendado (STD) como sugerencia
        if (tipo === 'efi' && currentValue === 0) {
            const rec = parseInt(btn.dataset.recommended || 'NaN', 10);
            if (!Number.isNaN(rec)) currentValue = rec;
        }
        buildNumberOptions(selector, tipo, currentValue);
        
        // Determinar si debe abrir hacia arriba o abajo según la posición en la ventana
        const rect = btn.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;
        const dropdownHeight = 60; // altura aproximada del dropdown
        
        // Si hay más espacio arriba o no hay suficiente espacio abajo, abrir hacia arriba
        if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
            selector.classList.remove('top-full', 'mt-1');
            selector.classList.add('bottom-full', 'mb-1');
        } else {
            selector.classList.remove('bottom-full', 'mb-1');
            selector.classList.add('top-full', 'mt-1');
        }
        
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
        const opt = document.createElement('button');
        opt.type = 'button';
        opt.className = 'number-option shrink-0 w-12 h-10 text-center rounded-md border border-gray-300 bg-white text-base font-medium hover:bg-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500';
        opt.dataset.value = String(i);
        opt.textContent = String(i);
        if (i === current) opt.classList.add('bg-blue-600','text-white','border-blue-600');
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
    .then(r => r.json().then(data => ({ status: r.status, data })))
    .then(({ status, data }) => {
        if (status === 400 && data.folio_existente) {
            // Ya existe un folio en proceso
            Swal.fire({
                icon: 'warning',
                title: 'Folio en proceso',
                html: data.message + '<br><br>¿Desea continuar editando ese folio?',
                showCancelButton: true,
                confirmButtonText: 'Sí, editar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '/modulo-marcas?folio=' + data.folio_existente;
                } else {
                    window.location.href = '/modulo-marcas/consultar';
                }
            });
            return Promise.reject('Folio en proceso');
        }
        
        if (!data.success || !data.folio) {
            throw new Error(data.message || 'Error al generar folio');
        }
        
        currentFolio = data.folio;
        isNewRecord  = true;
        isEditing    = true;
        actualizarBadgeFolio();

        if (elements.folio) elements.folio.value = data.folio;
        if (elements.fecha) elements.fecha.value = new Date().toISOString().split('T')[0];
        if (elements.turno) elements.turno.value = data.turno || '1';
        if (elements.status) elements.status.value = 'En Proceso';
        if (elements.usuario) elements.usuario.value = data.usuario || '';
        if (elements.noEmpleado) elements.noEmpleado.value = data.numero_empleado || '';
        if (elements.headerSection) elements.headerSection.style.display = 'block';
        return data;
    })
    .catch((err) => {
        if (err !== 'Folio en proceso') {
            Swal.fire('Error', typeof err === 'string' ? err : 'No se pudo generar el folio', 'error');
        }
    });
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

                // % Efi - no modificar el valor mostrado (debe iniciar en 0%), solo guardar recomendación (STD)
                const btnEfi = q(`button[data-telar="${item.telar}"][data-type="efi"]`);
                if (btnEfi) {
                    const p = (item.porcentaje_efi ?? null);
                    if (p != null) btnEfi.setAttribute('data-recommended', String(parseInt(p, 10)));
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

            // Actualizar % Efi - Ya viene como entero 0-100 desde la BD
            const efiVal = l.Eficiencia ?? null;
            const efiPercent = efiVal !== null ? parseInt(efiVal, 10) : null;
            const efiSpan = q(`button[data-telar="${telar}"][data-type="efi"] .valor-display-text`);
            if (efiSpan) {
                efiSpan.textContent = (efiPercent !== null && efiPercent > 0) ? `${efiPercent}%` : '-';
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
/* Estilos mínimos que no se pueden hacer con Tailwind */
table {
    border-collapse: separate;
    border-spacing: 0;
}

thead th {
    position: sticky;
    top: 0;
    z-index: 20;
}

/* Scrollbar personalizado */
.scrollbar-thin {
    scrollbar-width: thin;
}

.scrollbar-thumb-gray-300::-webkit-scrollbar-thumb {
    background-color: #d1d5db;
    border-radius: 6px;
}

.scrollbar-track-gray-100::-webkit-scrollbar-track {
    background-color: #f3f4f6;
}

.scrollbar-thin::-webkit-scrollbar {
    height: 6px;
}

/* Ocultar scrollbar (similar a cortes-eficiencia) */
.scrollbar-hide{ -ms-overflow-style:none; scrollbar-width:none; }
.scrollbar-hide::-webkit-scrollbar{ display:none; }

/* Opciones numéricas: transición suave (Tailwind maneja el resto en clases) */
.number-option { transition: background-color .15s ease, transform .15s ease; }
    </style>
@endsection
