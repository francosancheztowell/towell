@extends('layouts.app')

@section('page-title', 'Calificar Atadores')

@section('navbar-right')
    <div class="flex items-center gap-2">
        @php
            $item = $montadoTelas->isNotEmpty() ? $montadoTelas->first() : null;
            $estatusActual = $item?->Estatus ?? 'En Proceso';
        @endphp
        <button id="btnTerminar" onclick="terminarAtado()"
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            @if(in_array($estatusActual, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif>
            <i class="fas fa-stop mr-1"></i> Terminar Atado
        </button>
        <button id="btnCalificar" onclick="calificarTejedor()"
            class="px-2 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            @if($estatusActual !== 'Terminado') disabled @endif>
            <i class="fas fa-user-check mr-1"></i> Califica Tejedor
        </button>
        <button id="btnAutorizar" onclick="autorizaSupervisor()"
            class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            @if($estatusActual !== 'Calificado') disabled @endif>
            <i class="fas fa-user-tie mr-1"></i> Autoriza Supervisor
        </button>
    </div>
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">


    @if($montadoTelas->isNotEmpty())
        @php
            $item = $montadoTelas->first();
        @endphp

        <!-- Resumen del Atado (4 bloques combinados + comentarios) -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <h3 class="text-base font-semibold text-gray-700 mb-4">Resumen del Atado</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Columna 1 -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Fecha del Atado</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $item->Fecha ? \Carbon\Carbon::parse($item->Fecha)->format('d/m/Y') : '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Un Orden</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $item->NoProduccion ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Metros</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $item->Metros ? number_format($item->Metros, 2) : '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Merma Kg</span>
                        <div class="relative">
                            <input type="number" id="mergaKg" step="0.01" value="{{ $item->MergaKg ?? '' }}"
                                   class="w-28 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                                   placeholder="0.00" oninput="handleMergaChange(this.value)"
                                   @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                            <span id="mergaSavedIndicator" class="absolute -right-6 top-1/2 -translate-y-1/2 text-green-600 text-xs hidden">
                                <i class="fas fa-check"></i>
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Calidad de Atado (1-10)</span>
                        @if($item->Calidad)
                            <span id="valCalidad" class="px-2 py-1 bg-blue-100 text-blue-800 rounded font-semibold text-sm">{{ $item->Calidad }}</span>
                        @else
                            <span id="valCalidad" class="text-sm text-gray-400">-</span>
                        @endif
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Cve Supervisor</span>
                        <span id="valCveSupervisor" class="text-sm font-semibold text-gray-800">{{ $item->Estatus === 'Autorizado' ? ($item->CveSupervisor ?? '-') : '-' }}</span>
                    </div>
                </div>

                <!-- Columna 2 -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Hora de Paro</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $item->HoraParo ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">No Julio</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $item->NoJulio ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Lote Provee</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $item->LoteProveedor ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Hora de Arranque</span>
                        <span class="text-sm font-semibold text-gray-800">
                            {{ $item->HoraArranque ? \Carbon\Carbon::parse($item->HoraArranque)->format('H:i') : '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">5'S Orden y Limpieza (5-10)</span>
                        @if($item->Limpieza)
                            <span id="valLimpieza" class="px-2 py-1 bg-green-100 text-green-800 rounded font-semibold text-sm">{{ $item->Limpieza }}</span>
                        @else
                            <span id="valLimpieza" class="text-sm text-gray-400">-</span>
                        @endif
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Nom Supervisor</span>
                        <span id="valNomSupervisor" class="text-sm font-semibold text-gray-800">{{ $item->Estatus === 'Autorizado' ? ($item->NomSupervisor ?? '-') : '-' }}</span>
                    </div>
                </div>

                <!-- Columna 3 -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Telar</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $item->NoTelarId ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Tipo</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $item->Tipo ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">No Provee</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $item->NoProveedor ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide"></span>
                        <span id="valFechaSupervisor" class="text-sm font-semibold text-gray-800">
                            {{ '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Tejedor</span>
                        <div class="text-sm font-semibold text-gray-800 flex flex-wrap justify-end gap-1 text-right">
                            @if(in_array($item->Estatus, ['Calificado', 'Autorizado']) && $item->CveTejedor)
                                <span id="valCveTejedor">{{ $item->CveTejedor }}</span>
                                <span id="tejedorDash" class="text-gray-400">-</span>
                                <span id="valNomTejedor">{{ $item->NomTejedor }}</span>
                            @else
                                <span id="valCveTejedor">-</span>
                                <span id="tejedorDash" class="text-gray-400 hidden">-</span>
                                <span id="valNomTejedor"></span>
                            @endif
                        </div>
                    </div>
                    <div class="flex justify-between items-center gap-4">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Fecha Hora</span>
                        <span id="valFechaSupervisor" class="text-sm font-semibold text-gray-800">
                            {{ '-' }}
                        </span>
                    </div>

                </div>
            </div>

            <!-- Observaciones dentro del mismo card -->
            <div class="mt-4">
                <h4 class="text-sm font-semibold text-gray-600 mb-2 border-b pb-1">
                    Observaciones
                    <span id="autoSaveIndicator" class="text-xs text-gray-400 ml-2 hidden">
                        <i class="fas fa-circle-notch fa-spin"></i> Guardando...
                    </span>
                    <span id="savedIndicator" class="text-xs text-green-600 ml-2 hidden">
                        <i class="fas fa-check-circle"></i> Guardado
                    </span>
                </h4>
                <form id="formObservaciones" onsubmit="guardarObservaciones(event)">
                    <textarea id="observaciones" name="observaciones" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 transition-all duration-200"
                        placeholder="Escriba aquí las observaciones sobre el atado..."
                        oninput="handleObservacionesChange()"
                        @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif>{{ $item->Obs }}</textarea>
                    
                </form>
            </div>
        </div>

        <!-- Maquinas y Actividades -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <!-- Tabla: AtaMontadoMaquinas -->
            <div class="bg-white rounded-lg shadow-md p-4 overflow-x-auto lg:col-span-1">
                <h3 class="text-sm font-semibold text-gray-600 mb-3 border-b pb-2">Máquinas</h3>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Máquina</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($maquinasCatalogo as $maq)
                            @php
                                $m = $maquinasMontado->get($maq->MaquinaId);
                                $checked = $m && (int)($m->Estado ?? 0) === 1;
                            @endphp
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">{{ $maq->MaquinaId }}</td>
                                <td class="px-4 py-2 text-center">
                                    <input type="checkbox" {{ $checked ? 'checked' : '' }} class="h-4 w-4 text-blue-600 rounded"
                                           onchange="toggleMaquina('{{ $maq->MaquinaId }}', this.checked)"
                                           @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-center text-sm text-gray-500">No hay máquinas registradas</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Tabla: AtaMontadoActividades -->
            <div class="bg-white rounded-lg shadow-md p-4 overflow-x-auto lg:col-span-2">
                <h3 class="text-sm font-semibold text-gray-600 mb-3 border-b pb-2">Actividades</h3>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Actividad</th>
                            <th class="px-1 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-20">%</th>
                            <th class="px-1 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Estado</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operador</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($actividadesCatalogo as $act)
                            @php
                                $a = $actividadesMontado->get($act->ActividadId);
                                $checked = $a && (int)($a->Estado ?? 0) === 1;
                                $porcentaje = $a->Porcentaje ?? $act->Porcentaje;
                                $operador = $a && ($a->NomEmpl || $a->CveEmpl)
                                    ? trim(($a->CveEmpl ? $a->CveEmpl : '').($a->NomEmpl ? ' - '.$a->NomEmpl : ''))
                                    : '-';
                            @endphp
                            <tr id="actividad-{{ $act->ActividadId }}">
                                <td class="px-2 py-2 text-sm text-gray-900 w-24">{{ $act->ActividadId }}</td>
                                <td class="px-1 py-2 text-sm text-right text-gray-900 w-20">{{ number_format((float)$porcentaje, 0) }}%</td>
                                <td class="px-1 py-2 text-center w-16">
                                    <input type="checkbox" {{ $checked ? 'checked' : '' }} class="h-4 w-4 text-green-600 rounded"
                                           onchange="toggleActividad('{{ $act->ActividadId }}', this.checked)"
                                           @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                                </td>
                                <td class="px-2 py-2 text-sm text-gray-900 operador-cell">{{ $operador }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-center text-sm text-gray-500">No hay actividades registradas</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @else
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
            <p class="text-gray-500 text-lg">No hay datos disponibles en montado de telas</p>
            <p class="text-gray-400 text-sm mt-2">Seleccione un registro desde el programa de atadores</p>
        </div>
    @endif

    @isset($comentarios)
    <!-- Notas / Comentarios Catálogo -->
    <div class="bg-white rounded-lg shadow-md p-4 mt-6">
        <h3 class="text-sm font-semibold text-gray-600 mb-3 border-b pb-2">
            <i class="fa-solid fa-comment text-blue-600 mr-2"></i>Notas
        </h3>
        @if($comentarios->isEmpty())
            <p class="text-sm text-gray-500">No hay notas configuradas.</p>
        @else
            <div class="grid grid-cols-2 gap-6 mb-28">
                <!-- Nota 1 -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 bg-gray-50 px-3 py-2 rounded-t-md border-b-2 border-blue-500">Nota 1</h4>
                    <div class="space-y-3">
                        @foreach($comentarios->pluck('Nota1')->filter()->unique()->values() as $n1)
                            <div class="px-4 py-3 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-md text-sm leading-relaxed whitespace-normal">
                                {{ $n1 }}
                            </div>
                        @endforeach
                    </div>
                </div>
                <!-- Nota 2 -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 bg-gray-50 px-3 py-2 rounded-t-md border-b-2 border-green-500">Nota 2</h4>
                    <div class="space-y-3">
                        @foreach($comentarios->pluck('Nota2')->filter()->unique()->values() as $n2)
                            <div class="px-4 py-3 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-md text-sm leading-relaxed whitespace-normal">
                                {{ $n2 }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endisset
</div>
@endsection

@push('scripts')
<script>
// Usuario actual disponible para reflejar en UI tras guardados
const currentUser = {!! auth()->check() ? json_encode(['numero_empleado' => auth()->user()->numero_empleado, 'nombre' => auth()->user()->nombre]) : 'null' !!};

// Datos del registro actual para identificar correctamente en las peticiones
@if($montadoTelas->isNotEmpty())
    const currentNoJulio = '{{ $montadoTelas->first()->NoJulio }}';
    const currentNoOrden = '{{ $montadoTelas->first()->NoProduccion }}';
@else
    const currentNoJulio = null;
    const currentNoOrden = null;
@endif

// Información de actividades para validación
const actividadesData = {!! json_encode($actividadesCatalogo->map(function($act) use ($actividadesMontado) {
    $a = $actividadesMontado->get($act->ActividadId);
    return [
        'id' => $act->ActividadId,
        'estado' => $a && (int)($a->Estado ?? 0) === 1
    ];
})) !!};

// Auto-guardado de observaciones
let autoSaveTimeout = null;
let mergaSaveTimeout = null;

function handleObservacionesChange() {
    const autoSaveIndicator = document.getElementById('autoSaveIndicator');
    const savedIndicator = document.getElementById('savedIndicator');

    // Mostrar indicador de guardando
    if (autoSaveIndicator) {
        autoSaveIndicator.classList.remove('hidden');
    }
    if (savedIndicator) {
        savedIndicator.classList.add('hidden');
    }

    // Limpiar timeout anterior
    if (autoSaveTimeout) {
        clearTimeout(autoSaveTimeout);
    }

    // Guardar después de 2 segundos de inactividad
    autoSaveTimeout = setTimeout(() => {
        guardarObservacionesAuto();
    }, 2000);
}

function handleMergaChange(valor) {
    const indicator = document.getElementById('mergaSavedIndicator');

    // Ocultar indicador mientras se escribe
    if (indicator) {
        indicator.classList.add('hidden');
    }

    // Limpiar timeout anterior
    if (mergaSaveTimeout) {
        clearTimeout(mergaSaveTimeout);
    }

    // Guardar después de 1.5 segundos de inactividad
    if (valor && valor !== '') {
        mergaSaveTimeout = setTimeout(() => {
            guardarMerga(valor);
        }, 1500);
    }
}


function guardarObservacionesAuto() {
    const observaciones = document.getElementById('observaciones').value;
    const autoSaveIndicator = document.getElementById('autoSaveIndicator');
    const savedIndicator = document.getElementById('savedIndicator');

    fetch('{{ route('atadores.save') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            action: 'observaciones',
            observaciones: observaciones,
            no_julio: currentNoJulio,
            no_orden: currentNoOrden
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            // Mostrar indicador de guardado
            if (autoSaveIndicator) {
                autoSaveIndicator.classList.add('hidden');
            }
            if (savedIndicator) {
                savedIndicator.classList.remove('hidden');
                // Ocultar después de 2 segundos
                setTimeout(() => {
                    savedIndicator.classList.add('hidden');
                }, 2000);
            }
        } else {
            if (autoSaveIndicator) {
                autoSaveIndicator.classList.add('hidden');
            }
        }
    })
    .catch(() => {
        if (autoSaveIndicator) {
            autoSaveIndicator.classList.add('hidden');
        }
    });
}


async function terminarAtado(){
    // Validar que todas las actividades estén marcadas
    const total = actividadesData.length;
    const completadas = actividadesData.filter(a => a.estado).length;

    // Verificar checkboxes actuales en la interfaz
    const checkboxes = document.querySelectorAll('input[type="checkbox"][onchange*="toggleActividad"]');
    const marcados = Array.from(checkboxes).filter(cb => cb.checked).length;

    if (marcados < total) {
        Swal.fire({
            icon: 'warning',
            title: 'Actividades incompletas',
            text: `Debe marcar todas las actividades antes de terminar el atado. (${marcados}/${total} completadas)`,
            confirmButtonText: 'Entendido'
        });
        return;
    }

    // Validar que la merma (Merma Kg) esté capturada
    const mergaInput = document.getElementById('mergaKg');
    const mergaValorStr = mergaInput ? mergaInput.value.trim() : '';
    const mergaValor = mergaValorStr !== '' ? parseFloat(mergaValorStr) : NaN;
    if (!mergaInput || mergaValorStr === '' || isNaN(mergaValor)) {
        Swal.fire({
            icon: 'warning',
            title: 'Merma pendiente',
            text: 'Captura la merma (Kg) antes de terminar el atado.',
            confirmButtonText: 'Entendido'
        });
        return;
    }

    const { value: formValues } = await Swal.fire({
        title: '¿Terminar Atado?',
        html: `
            <div style="text-align:left; padding:0 10px;">
                <p style="font-size:14px; color:#666; margin-bottom:16px;">
                    Se registrará la hora de arranque con la hora actual y el estatus cambiará a "Terminado"
                </p>
                <label style="display:block; font-size:14px; margin-bottom:4px; font-weight:500;">Comentarios del Atador</label>
                <textarea id="swComentariosAtador" style="width:100%; min-height:100px; padding:10px; border:1px solid #d9d9d9; border-radius:4px; resize:vertical; font-size:14px; box-sizing:border-box;" placeholder="Escriba sus comentarios aquí (opcional)..."></textarea>
            </div>
        `,
        focusConfirm: false,
        preConfirm: () => {
            const comentarios = document.getElementById('swComentariosAtador').value.trim();
            return { comentarios };
        },
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, terminar',
        cancelButtonText: 'Cancelar',
        width: '450px'
    });

    if(!formValues) return;

    fetch('{{ route('atadores.save') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            action: 'terminar',
            comments_ata: formValues.comentarios || '',
            no_julio: currentNoJulio,
            no_orden: currentNoOrden
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            Swal.fire({ icon: 'success', title: 'Atado terminado', text: 'El estatus ha cambiado a "Terminado"', timer: 1500, showConfirmButton: false });
            // Deshabilitar botón de terminar atado
            const btnTerminar = document.getElementById('btnTerminar');
            if (btnTerminar) {
                btnTerminar.disabled = true;
                btnTerminar.classList.add('opacity-50', 'cursor-not-allowed');
            }
            // Habilitar botón de calificar
            const btnCalificar = document.getElementById('btnCalificar');
            if (btnCalificar) {
                btnCalificar.disabled = false;
                btnCalificar.classList.remove('opacity-50', 'cursor-not-allowed');
            }
            // Deshabilitar todos los checkboxes de máquinas y actividades
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.disabled = true);
            // Deshabilitar campo de observaciones y merga
            const obsTextarea = document.getElementById('observaciones');
            if (obsTextarea) obsTextarea.disabled = true;
            const mergaInput = document.getElementById('mergaKg');
            if (mergaInput) mergaInput.disabled = true;
            const obsForm = document.getElementById('formObservaciones');
            if (obsForm) {
                const btnGuardar = obsForm.querySelector('button[type="submit"]');
                if (btnGuardar) btnGuardar.disabled = true;
            }
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo terminar' });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error de red' }));
}
}

async function calificarTejedor(){
    const { value: formValues } = await Swal.fire({
        title: 'Calificar Tejedor',
        html: `
            <div style="text-align:left; padding:0 10px;">
                <label style="display:block; font-size:14px; margin-bottom:4px;">Calidad de Atado (1-10)</label>
                <select id="swCalidad" class="swal2-input" style="width:100%; margin:0 0 12px 0;">
                    <option value="">Seleccione</option>
                    ${Array.from({length:10}, (_,i)=>`<option value="${i+1}">${i+1}</option>`).join('')}
                </select>
                <label style="display:block; font-size:14px; margin-bottom:4px;">Orden y Limpieza (5-10)</label>
                <select id="swLimpieza" class="swal2-input" style="width:100%; margin:0 0 12px 0;">
                    <option value="">Seleccione</option>
                    ${Array.from({length:6}, (_,i)=>`<option value="${i+5}">${i+5}</option>`).join('')}
                </select>
                <label style="display:block; font-size:14px; margin-bottom:4px;">Comentarios del Tejedor</label>
                <textarea id="swComentariosTejedor" style="width:100%; min-height:80px; padding:10px; border:1px solid #d9d9d9; border-radius:4px; resize:vertical; font-size:14px; box-sizing:border-box;" placeholder="Escriba sus comentarios aquí (opcional)..."></textarea>
            </div>
        `,
        focusConfirm: false,
        preConfirm: () => {
            const calidad = document.getElementById('swCalidad').value;
            const limpieza = document.getElementById('swLimpieza').value;
            const comentarios = document.getElementById('swComentariosTejedor').value.trim();
            if(!calidad || !limpieza){
                Swal.showValidationMessage('Seleccione calidad y limpieza');
                return false;
            }
            return { calidad, limpieza, comentarios };
        },
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        width: '450px'
    });

    if(!formValues) return;

    // Enviar calificación y, si no existe, asignar operador con el usuario en sesión
    fetch('{{ route('atadores.save') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            action: 'calificacion',
            calidad: Number(formValues.calidad),
            limpieza: Number(formValues.limpieza),
            comments_tej: formValues.comentarios || '',
            no_julio: currentNoJulio,
            no_orden: currentNoOrden
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            // Actualizar tabla en vivo sin recargar
            const calidad = document.getElementById('valCalidad');
            const limpieza = document.getElementById('valLimpieza');
            if (calidad) { calidad.textContent = formValues.calidad; calidad.className = 'px-2 py-1 bg-blue-100 text-blue-800 rounded font-semibold text-sm'; }
            if (limpieza) { limpieza.textContent = formValues.limpieza; limpieza.className = 'px-2 py-1 bg-green-100 text-green-800 rounded font-semibold text-sm'; }

            // Actualizar el campo TEJEDOR con el usuario actual que está calificando
            if (res.tejedor && currentUser) {
                const cveTej = document.getElementById('valCveTejedor');
                const nomTej = document.getElementById('valNomTejedor');
                const dashTej = document.getElementById('tejedorDash');
                
                if (cveTej) cveTej.textContent = res.tejedor.cve || currentUser.numero_empleado || '-';
                if (nomTej) nomTej.textContent = res.tejedor.nombre || currentUser.nombre || '';
                if (dashTej) dashTej.classList.remove('hidden');
            }

            // Deshabilitar botones Terminar Atado y Calificar Tejedor
            const btnTerminar = document.getElementById('btnTerminar');
            if (btnTerminar) {
                btnTerminar.disabled = true;
                btnTerminar.classList.add('opacity-50', 'cursor-not-allowed');
            }
            const btnCalificar = document.getElementById('btnCalificar');
            if (btnCalificar) {
                btnCalificar.disabled = true;
                btnCalificar.classList.add('opacity-50', 'cursor-not-allowed');
            }

            // Habilitar automáticamente el botón de Autoriza Supervisor
            const btnAutorizar = document.getElementById('btnAutorizar');
            if (btnAutorizar) {
                btnAutorizar.disabled = false;
                btnAutorizar.classList.remove('opacity-50', 'cursor-not-allowed');
            }

            Swal.fire({ icon: 'success', title: 'Calificación guardada', timer: 1200, showConfirmButton: false });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo guardar' });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error de red' }));
}

async function autorizaSupervisor(){
    const { value: formValues } = await Swal.fire({
        title: 'Autorizar Supervisor',
        html: `
            <div style="text-align:left; padding:0 10px;">
                <p style="font-size:14px; color:#666; margin-bottom:16px;">
                    Esto completará el proceso y regresará al programa de atadores
                </p>
                <label style="display:block; font-size:14px; margin-bottom:4px; font-weight:500;">Comentarios del Supervisor</label>
                <textarea id="swComentariosSupervisor" style="width:100%; min-height:100px; padding:10px; border:1px solid #d9d9d9; border-radius:4px; resize:vertical; font-size:14px; box-sizing:border-box;" placeholder="Escriba sus comentarios aquí (opcional)..."></textarea>
            </div>
        `,
        focusConfirm: false,
        preConfirm: () => {
            const comentarios = document.getElementById('swComentariosSupervisor').value.trim();
            return { comentarios };
        },
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, autorizar',
        cancelButtonText: 'Cancelar',
        width: '450px'
    });

    if(!formValues) return;

    // Asignar supervisor = usuario en sesión
    fetch('{{ route('atadores.save') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            action: 'supervisor',
            comentarios_supervisor: formValues.comentarios || '',
            no_julio: currentNoJulio,
            no_orden: currentNoOrden
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            // Actualizar supervisor en la interfaz antes de redirigir
            if (res.supervisor) {
                const cveSup = document.getElementById('valCveSupervisor');
                const nomSup = document.getElementById('valNomSupervisor');
                if (cveSup) cveSup.textContent = res.supervisor.cve || '-';
                if (nomSup) nomSup.textContent = res.supervisor.nombre || '-';
            }

            Swal.fire({
                icon: 'success',
                title: 'Proceso Completado',
                text: 'El atado ha sido autorizado y guardado en el historial',
                showConfirmButton: false,
                timer: 2000
            });
            setTimeout(() => {
                // Redirigir al programa de atadores
                window.location.href = res.redirect || '{{ route('atadores.programa') }}';
            }, 2100);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo autorizar el proceso' });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error de red' }));
}

function guardarObservaciones(event){
    event.preventDefault();

    const observaciones = document.getElementById('observaciones').value;

    fetch('{{ route('atadores.save') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            action: 'observaciones',
            observaciones: observaciones,
            no_julio: currentNoJulio,
            no_orden: currentNoOrden
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            Swal.fire({
                icon: 'success',
                title: 'Observaciones Guardadas',
                text: 'Las observaciones se han guardado correctamente',
                showConfirmButton: false,
                timer: 2000
            });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudieron guardar las observaciones' });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error de red' }));
}

// Guardar Merga Kg
function guardarMerga(valor){
    if (!valor || valor === '') return;

    fetch('{{ route('atadores.save') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            action: 'merga',
            mergaKg: parseFloat(valor),
            no_julio: currentNoJulio,
            no_orden: currentNoOrden
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            // Mostrar confirmación visual temporal
            const input = document.getElementById('mergaKg');
            if (input) {
                input.classList.add('border-green-500', 'bg-green-50');
                setTimeout(() => {
                    input.classList.remove('border-green-500', 'bg-green-50');
                }, 1000);
            }
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo guardar la merma' });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error de red' }));
}

// Agregar nota a Observaciones
function agregarNota(texto){
    const ta = document.getElementById('observaciones');
    if(!ta) return;
    const sep = ta.value && !ta.value.endsWith('\n') ? '\n' : '';
    ta.value = ta.value + sep + texto;
    ta.focus();
}

// Toggle estado de máquina y guardar en DB
function toggleMaquina(maquinaId, checked){
    fetch('{{ route('atadores.save') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            action: 'maquina_estado',
            maquinaId: maquinaId,
            estado: !!checked,
            no_julio: currentNoJulio,
            no_orden: currentNoOrden
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            // Confirmación visual guardada
            console.log(`Máquina ${maquinaId} ${checked ? 'activada' : 'desactivada'} - Guardado en BD`);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo actualizar máquina' });
            // Revertir checkbox si falló
            const checkbox = document.querySelector(`input[onchange*="toggleMaquina('${maquinaId}'"]`);
            if (checkbox) checkbox.checked = !checked;
        }
    })
    .catch(() => {
        Swal.fire({ icon: 'error', title: 'Error de red' });
        // Revertir checkbox si falló
        const checkbox = document.querySelector(`input[onchange*="toggleMaquina('${maquinaId}'"]`);
        if (checkbox) checkbox.checked = !checked;
    });
}

// Toggle estado de actividad y guardar en DB
function toggleActividad(actividadId, checked){
    // Regla: solo el usuario que marcó puede desmarcar su propia actividad
    try {
        if (!checked) {
            const fila = document.getElementById('actividad-' + actividadId);
            const celdaOperador = fila ? fila.querySelector('.operador-cell') : null;
            const operadorTexto = (celdaOperador ? (celdaOperador.textContent || '').trim() : '');

            // Extraer la clave de empleado del texto (formato esperado: "111 - Nombre" o "111 Nombre")
            let operadorId = null;
            if (operadorTexto && operadorTexto !== '-') {
                const match = operadorTexto.match(/^(\d{1,10})\b/);
                operadorId = match ? match[1] : null;
            }

            if (operadorId && currentUser && String(operadorId) !== String(currentUser.numero_empleado)) {
                // Revertir cambio en UI y alertar
                const checkbox = document.querySelector(`input[onchange*="toggleActividad('${actividadId}'"]`);
                if (checkbox) checkbox.checked = true;
                Swal.fire({
                    icon: 'warning',
                    title: 'No permitido',
                    text: 'No puedes desmarcar una actividad realizada por otro usuario. Por favor, consúltalo con tu supervisor.',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
        }
    } catch (e) {
        // Si hay algún error en la validación, continuamos con el flujo normal
        console.warn('Validación de propietario de actividad falló:', e);
    }

    fetch('{{ route('atadores.save') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            action: 'actividad_estado',
            actividadId: actividadId,
            estado: !!checked,
            no_julio: currentNoJulio,
            no_orden: currentNoOrden
        })
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            // Actualizar el operador en la tabla dinámicamente
            const fila = document.getElementById('actividad-' + actividadId);
            const celdaOperador = fila ? fila.querySelector('.operador-cell') : null;
            if (fila && celdaOperador) {
                if (checked) {
                    // Si el backend provee operador, úsalo; si no, refleja usuario actual
                    const operadorTexto = res.operador
                        ? String(res.operador)
                        : (currentUser ? `${currentUser.numero_empleado} - ${currentUser.nombre || ''}` : '-');
                    celdaOperador.textContent = operadorTexto.trim();
                } else {
                    // Al desmarcar, limpiar operador
                    celdaOperador.textContent = '-';
                }
            }

            // Actualizar el estado en actividadesData
            const actividadIndex = actividadesData.findIndex(a => a.id === actividadId);
            if (actividadIndex !== -1) {
                actividadesData[actividadIndex].estado = !!checked;
            }

            // Confirmación visual guardada
            console.log(`Actividad ${actividadId} ${checked ? 'completada' : 'pendiente'} - Guardado en BD`);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo actualizar actividad' });
            // Revertir checkbox si falló
            const checkbox = document.querySelector(`input[onchange*="toggleActividad('${actividadId}'"]`);
            if (checkbox) checkbox.checked = !checked;
        }
    })
    .catch(() => {
        Swal.fire({ icon: 'error', title: 'Error de red' });
        // Revertir checkbox si falló
        const checkbox = document.querySelector(`input[onchange*="toggleActividad('${actividadId}'"]`);
        if (checkbox) checkbox.checked = !checked;
    });
}
</script>
@endpush
