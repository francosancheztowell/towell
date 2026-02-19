@extends('layouts.app')

@php
    use App\Helpers\TurnoHelper;
    $soloLectura = $soloLectura ?? false;
    $folioInicial = $folioInicial ?? request()->query('folio');
    $esModoEdicion = $soloLectura ? true : !empty($folioInicial);
    $tituloPagina = $soloLectura
        ? 'Visualizar Marcas Finales'
        : ($esModoEdicion ? 'Editar Marcas Finales' : 'Nuevas Marcas Finales');
    $turnoActual = TurnoHelper::getTurnoActual();
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
    $puedeEditar    = ($puedeCrear || $puedeModificar) && !$soloLectura;
    $configVista    = [
        'soloLectura' => $soloLectura,
        'folioInicial' => $folioInicial,
    ];

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

<!-- Modal Fecha/Turno para crear folio -->
<div id="modal-fecha-turno" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/40" data-close="true"></div>
    <div class="relative w-full max-w-md rounded-lg bg-white shadow-lg">
        <div class="px-4 py-3 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Crear nuevo folio</h3>
            <button id="modal-fecha-turno-close" class="text-gray-500 hover:text-gray-700" aria-label="Cerrar">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <div class="p-4 space-y-3">
            <div>
                <label for="select-fecha-folio" class="block text-sm font-medium text-gray-700 mb-1">Fecha</label>
                <select id="select-fecha-folio" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500"></select>
            </div>
            <div>
                <label for="select-turno-folio" class="block text-sm font-medium text-gray-700 mb-1">Turno</label>
                <select id="select-turno-folio" class="w-full rounded-md border border-gray-300 bg-white p-2 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="1">Turno 1</option>
                    <option value="2">Turno 2</option>
                    <option value="3">Turno 3</option>
                </select>
            </div>
        </div>
        <div class="px-4 py-3 border-t flex justify-end gap-2">
            <button id="modal-fecha-turno-cancel" class="px-4 py-2 rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">Cancelar</button>
            <button id="modal-fecha-turno-ok" class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">Continuar</button>
        </div>
    </div>
</div>
@endsection

@section('content')
<!-- Contenedor principal sin alertas flotantes (ahora usa SweetAlert) -->

<div class="w-full h-full overflow-hidden flex flex-col px-4 py-4 md:px-8 lg:px-12 xl:px-16">
    <!-- Tabla principal -->
    <div id="segunda-tabla" class="flex flex-col flex-1 bg-white rounded-lg shadow-md overflow-hidden mx-auto w-full max-w-7xl">
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
                        <th class="px-2 md:px-4 py-2 md:py-3 text-center uppercase text-xs md:text-sm font-semibold">Telar</th>
                        <th class="px-2 md:px-4 py-2 md:py-3 text-center uppercase text-xs md:text-sm font-semibold">Salón</th>
                        @foreach($colsEditables as $col)
                            <th class="px-2 md:px-4 py-2 md:py-3 text-center uppercase text-xs md:text-sm font-semibold">
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
                        <td class="px-2 md:px-4 py-2 md:py-3 text-xs md:text-sm font-medium text-gray-900 text-center border-r border-gray-200">
                            {{ $telar->NoTelarId }}
                        </td>

                        <!-- Salón (badge) -->
                        <td class="px-2 md:px-4 py-2 md:py-3 text-center border-r border-gray-200">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                  data-telar="{{ $telar->NoTelarId }}" data-field="salon">
                                {{ $telar->SalonId ?? '-' }}
                            </span>
                        </td>

                        <!-- Celdas editables (DRY) -->
                        @foreach($colsEditables as $col)
                            <td class="px-1 md:px-3 py-2 md:py-3 text-center {{ !$loop->last ? 'border-r border-gray-200' : '' }}">
                                @php
                                    $maxVal = $col['key'] === 'marcas' ? 250 : 100;
                                    $isEfi = $col['key'] === 'efi';
                                @endphp
                                <input type="number" 
                                    class="valor-input w-full max-w-[60px] md:max-w-[70px] mx-auto px-1 md:px-2 py-1 md:py-1.5 border border-gray-300 rounded text-xs md:text-sm text-gray-900 text-center focus:ring-2 focus:ring-blue-400 focus:border-blue-400 {{ !$puedeEditar ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'bg-white' }}" 
                                    data-telar="{{ $telar->NoTelarId }}"
                                    data-type="{{ $col['key'] }}"
                                    value="0"
                                    min="0"
                                    max="{{ $maxVal }}"
                                    placeholder="{{ $isEfi ? '0%' : '0' }}"
                                    {{ !$puedeEditar ? 'readonly' : '' }}>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
/* =========================
   Estado global + helpers
   ========================= */
const PAGE_MODE = @json($configVista);
const TURNO_ACTUAL = @json($turnoActual);
let currentFolio   = null;
let isEditing      = false;
let isNewRecord    = true;
let guardarTimeout = null;
let folioMeta      = { fecha: null, turno: null };

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

    // Eventos para inputs de valor
    document.querySelectorAll('.valor-input').forEach(input => {
        // Al hacer focus, sugerir valor si es 0
        input.addEventListener('focus', () => {
            if (PAGE_MODE.soloLectura || input.readOnly) return;
            const tipo = input.dataset.type;
            const currentVal = parseInt(input.value, 10) || 0;
            
            // Si el valor es 0 y es % Efi, sugerir el STD recomendado
            if (currentVal === 0 && tipo === 'efi') {
                const rec = parseInt(input.dataset.recommended || '0', 10);
                if (rec > 0) {
                    input.value = rec;
                    input.select();
                    return;
                }
            }
            // Si es marcas y es 0, sugerir 100
            if (currentVal === 0 && tipo === 'marcas') {
                input.value = 100;
                input.select();
                return;
            }
            input.select();
        });
        
        // Al escribir, validar rango
        input.addEventListener('input', () => {
            if (PAGE_MODE.soloLectura || input.readOnly) return;
            const tipo = input.dataset.type;
            const [min, max] = RANGOS[tipo] || [0, 100];
            let val = parseInt(input.value, 10) || 0;
            if (val < min) val = min;
            if (val > max) val = max;
            input.value = val;
        });
        
        // Al perder focus, guardar automáticamente
        input.addEventListener('blur', () => {
            if (PAGE_MODE.soloLectura || input.readOnly) return;
            guardarAutomatico();
        });
        
        // Feedback visual al cambiar
        input.addEventListener('change', () => {
            if (PAGE_MODE.soloLectura || input.readOnly) return;
            input.classList.add('bg-green-100');
            setTimeout(() => input.classList.remove('bg-green-100'), 300);
        });
    });

    // Cambio de turno => guardar
    if (elements.turno) elements.turno.addEventListener('change', guardarAutomatico);

    // Edición por folio o nuevo
    const folioUrl = PAGE_MODE.folioInicial || new URLSearchParams(window.location.search).get('folio');

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
    } else if (!PAGE_MODE.soloLectura) {
        // Modo nuevo: pedir fecha/turno y luego generar folio
        cargarDatosSTD(false);
        abrirModalFechaTurno()
            .then(({ fecha, turno }) => generarNuevoFolio(fecha, turno))
            .catch(() => {
                // Si cancelan, regresar a consultar
                window.location.href = '/modulo-marcas/consultar';
            });
    } else {
        Swal.fire('Aviso', 'No se pudo determinar el folio a visualizar.', 'warning');
    }

    // Guardar al salir
    if (!PAGE_MODE.soloLectura) {
        window.addEventListener('beforeunload', () => currentFolio && guardarDatosTabla());
        window.addEventListener('popstate',     () => currentFolio && guardarDatosTabla());
    }
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
   Guardado automático
   ========================= */
function guardarAutomatico() {
    if (PAGE_MODE.soloLectura) return;
    if (guardarTimeout) clearTimeout(guardarTimeout);
    guardarTimeout = setTimeout(guardarDatosTabla, 1000);
}

function guardarDatosTabla() {
    if (PAGE_MODE.soloLectura) return;
    if (!currentFolio) return;

    const fechaPayload = elements.fecha?.value || folioMeta.fecha;
    const turnoPayload = elements.turno?.value || folioMeta.turno;

    if (!fechaPayload || !turnoPayload) {
        Swal.fire('Aviso', 'Falta fecha o turno para guardar.', 'warning');
        return;
    }

    const lineas = [];
    qa('#telares-body tr').forEach(row => {
        const telar = row.querySelector('td:first-child')?.textContent?.trim();
        if (!telar) return;

        const datos = { NoTelarId: telar };
        CAMPOS.forEach(t => {
            const input = row.querySelector(`input.valor-input[data-telar="${telar}"][data-type="${t}"]`);
            const val = input ? (parseInt(input.value, 10) || 0) : 0;
            datos[(t === 'efi' ? 'PorcentajeEfi' : t.charAt(0).toUpperCase()+t.slice(1))] = val;
        });
        lineas.push(datos);
    });

    const payload = {
        folio:  currentFolio,
        fecha:  fechaPayload,
        turno:  turnoPayload,
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
function generarNuevoFolio(fechaSeleccionada, turnoSeleccionado) {
    if (PAGE_MODE.soloLectura) return Promise.resolve();
    return fetch('/modulo-marcas/generar-folio', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': q('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            fecha: fechaSeleccionada,
            turno: turnoSeleccionado
        })
    })
    .then(r => r.json().then(data => ({ status: r.status, data })))
    .then(({ status, data }) => {
        if (status === 400 && data.folio_existente) {
            // Detectar si es creación simultánea por otro usuario
            if (data.creado_por_otro) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Folio en creación',
                    text: 'Otro usuario está creando un folio en este momento (' + data.folio_existente + '). Por favor, espere unos segundos e intente nuevamente.',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#3085d6'
                }).then(() => {
                    window.location.href = '/modulo-marcas/consultar';
                });
                return Promise.reject('Folio en creación por otro usuario');
            }

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

        if (status === 409 && data.folio_existente) {
            Swal.fire({
                icon: 'warning',
                title: 'Duplicado',
                text: data.message || 'Ya existe un folio con la misma fecha y turno.'
            }).then(() => {
                return abrirModalFechaTurno()
                    .then(({ fecha, turno }) => generarNuevoFolio(fecha, turno));
            });
            return Promise.reject('Duplicado fecha/turno');
        }

        if (status === 422) {
            Swal.fire('Aviso', data.message || 'Debe seleccionar fecha y turno.', 'warning')
                .then(() => {
                    return abrirModalFechaTurno()
                        .then(({ fecha, turno }) => generarNuevoFolio(fecha, turno));
                });
            return Promise.reject('Datos inválidos');
        }

        if (!data.success || !data.folio) {
            throw new Error(data.message || 'Error al generar folio');
        }

        currentFolio = data.folio;
        folioMeta = { fecha: data.fecha || fechaSeleccionada, turno: data.turno || turnoSeleccionado };
        isNewRecord  = true;
        isEditing    = true;
        actualizarBadgeFolio();

        if (elements.folio) elements.folio.value = data.folio;
        if (elements.fecha) elements.fecha.value = folioMeta.fecha;
        if (elements.turno) elements.turno.value = String(folioMeta.turno || '1');
        if (elements.status) elements.status.value = 'En Proceso';
        if (elements.usuario) elements.usuario.value = data.usuario || '';
        if (elements.noEmpleado) elements.noEmpleado.value = data.numero_empleado || '';
        if (elements.headerSection) elements.headerSection.style.display = 'block';
        return data;
    })
    .catch((err) => {
        if (err !== 'Folio en proceso' && err !== 'Folio en creación por otro usuario') {
            Swal.fire('Error', typeof err === 'string' ? err : 'No se pudo generar el folio', 'error');
        }
    });
}

/* =========================
   Modal Fecha/Turno
   ========================= */
function abrirModalFechaTurno() {
    return new Promise((resolve, reject) => {
        const modal = q('#modal-fecha-turno');
        const btnClose = q('#modal-fecha-turno-close');
        const btnCancel = q('#modal-fecha-turno-cancel');
        const btnOk = q('#modal-fecha-turno-ok');
        const selectFecha = q('#select-fecha-folio');
        const selectTurno = q('#select-turno-folio');

        if (!modal || !selectFecha || !selectTurno) {
            reject();
            return;
        }

        // Cargar fechas (últimos 7 días incluyendo hoy)
        selectFecha.innerHTML = '';
        const hoy = new Date();
        for (let i = 0; i < 7; i++) {
            const d = new Date(hoy);
            d.setDate(hoy.getDate() - i);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const value = `${yyyy}-${mm}-${dd}`;
            const label = d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = label;
            selectFecha.appendChild(opt);
        }

        selectTurno.value = String(TURNO_ACTUAL || 1);

        const cerrar = () => modal.classList.add('hidden');
        const abrir = () => modal.classList.remove('hidden');

        const onCancel = () => {
            limpiar();
            cerrar();
            reject();
        };

        const onOk = () => {
            const fecha = selectFecha.value;
            const turno = selectTurno.value;
            if (!fecha || !turno) {
                Swal.fire('Aviso', 'Selecciona fecha y turno.', 'warning');
                return;
            }
            limpiar();
            cerrar();
            resolve({ fecha, turno });
        };

        const onBackdrop = (e) => {
            if (e.target?.dataset?.close === 'true') onCancel();
        };

        function limpiar() {
            btnClose?.removeEventListener('click', onCancel);
            btnCancel?.removeEventListener('click', onCancel);
            btnOk?.removeEventListener('click', onOk);
            modal?.removeEventListener('click', onBackdrop);
        }

        btnClose?.addEventListener('click', onCancel);
        btnCancel?.addEventListener('click', onCancel);
        btnOk?.addEventListener('click', onOk);
        modal?.addEventListener('click', onBackdrop);

        abrir();
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

                // % Efi - guardar recomendación (STD) en data-recommended del input
                const inputEfi = q(`input.valor-input[data-telar="${item.telar}"][data-type="efi"]`);
                if (inputEfi) {
                    const p = (item.porcentaje_efi ?? null);
                    if (p != null) inputEfi.setAttribute('data-recommended', String(parseInt(p, 10)));
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

        folioMeta = {
            fecha: d.marca.Date || null,
            turno: d.marca.Turno || null
        };

        // Actualizar campos del header si existen
        if (elements.folio)  elements.folio.value  = d.marca.Folio || '';
        if (elements.fecha)  elements.fecha.value  = d.marca.Date || '';
        if (elements.turno)  elements.turno.value  = d.marca.Turno || '';
        if (elements.status) elements.status.value = d.marca.Status || 'En Proceso';
        if (elements.usuario) elements.usuario.value = d.marca.nombreEmpl || '';
        if (elements.noEmpleado) elements.noEmpleado.value = d.marca.numero_empleado || '';
        if (elements.headerSection) elements.headerSection.style.display = 'block';

        // Cargar líneas en la tabla (usar inputs)
        (d.lineas || []).forEach(l => {
            const telar = l.NoTelarId;

            // Actualizar % Efi - Ya viene como entero 0-100 desde la BD
            const efiVal = l.Eficiencia ?? 0;
            const efiInput = q(`input.valor-input[data-telar="${telar}"][data-type="efi"]`);
            if (efiInput) {
                efiInput.value = parseInt(efiVal, 10) || 0;
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
                const input = q(`input.valor-input[data-telar="${telar}"][data-type="${tipo}"]`);
                if (input) {
                    input.value = valor ?? 0;
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

/* Inputs de valor */
.valor-input {
    transition: border-color .2s ease, background-color .2s ease;
    -moz-appearance: textfield; /* Firefox - ocultar spinners */
}
.valor-input::-webkit-outer-spin-button,
.valor-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.valor-input:focus {
    background-color: #eff6ff;
}
.valor-input.bg-green-100 {
    background-color: #dcfce7 !important;
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
</style>
@endsection
