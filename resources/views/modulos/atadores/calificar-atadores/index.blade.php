@extends('layouts.app')

@section('page-title', 'Calificar Atadores')

@section('navbar-right')
    <div class="flex items-center gap-2">
        <button onclick="terminarAtado()" 
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors duration-200">
            <i class="fas fa-stop mr-1"></i> Terminar Atado
        </button>
        <button onclick="calificarTejedor()" 
            class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors duration-200">
            <i class="fas fa-user-check mr-1"></i> Calificar Tejedor
        </button>
        <button onclick="autorizaSupervisor()" 
            class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition-colors duration-200">
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Información General -->
                <div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Fecha:</span>
                            <span class="text-sm font-medium">{{ $item->Fecha ? \Carbon\Carbon::parse($item->Fecha)->format('d/m/Y') : '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Turno:</span>
                            <span class="text-sm font-medium">{{ $item->Turno ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">No. Julio:</span>
                            <span class="text-sm font-medium">{{ $item->NoJulio ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">No. Producción:</span>
                            <span class="text-sm font-medium">{{ $item->NoProduccion ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Detalles de Producción -->
                <div>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Tipo:</span>
                            <span class="text-sm font-medium">{{ $item->Tipo ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Metros:</span>
                            <span class="text-sm font-medium">{{ $item->Metros ? number_format($item->Metros, 2) : '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">No. Telar:</span>
                            <span class="text-sm font-medium">{{ $item->NoTelarId ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Lote Proveedor:</span>
                            <span class="text-sm font-medium">{{ $item->LoteProveedor ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">No. Proveedor:</span>
                            <span class="text-sm font-medium">{{ $item->NoProveedor ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Tiempos y Pesos -->
                <div>
                    {{-- <h4 class="text-sm font-semibold text-gray-600 mb-2 border-b pb-1">Tiempos y Pesos</h4> --}}
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Merga Kg:</span>
                            <span class="text-sm font-medium">{{ $item->MergaKg ? number_format($item->MergaKg, 2) : '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Hora Paro:</span>
                            <span class="text-sm font-medium">{{ $item->HoraParo ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Hora Arranque:</span>
                            <span class="text-sm font-medium">{{ $item->HoraArranque ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Calificaciones -->
                <div>
                    {{-- <h4 class="text-sm font-semibold text-gray-600 mb-2 border-b pb-1">Calificaciones</h4> --}}
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Calidad:</span>
                            @if($item->Calidad)
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded font-semibold text-sm">{{ $item->Calidad }}</span>
                            @else
                                <span class="text-sm text-gray-400">-</span>
                            @endif
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Limpieza:</span>
                            @if($item->Limpieza)
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded font-semibold text-sm">{{ $item->Limpieza }}</span>
                            @else
                                <span class="text-sm text-gray-400">-</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Observaciones dentro del mismo card -->
            <div class="mt-4">
                <h4 class="text-sm font-semibold text-gray-600 mb-2 border-b pb-1">Observaciones</h4>
                <form id="formObservaciones" onsubmit="guardarObservaciones(event)">
                    <textarea id="observaciones" name="observaciones" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 transition-all duration-200" placeholder="Escriba aquí las observaciones sobre el atado...">{{ $item->Obs }}</textarea>
                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors duration-200">
                            <i class="fas fa-save mr-1"></i> Guardar Observaciones
                        </button>
                    </div>
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
                                    <input type="checkbox" {{ $checked ? 'checked' : '' }} disabled class="h-4 w-4 text-blue-600 rounded" />
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
                            <tr>
                                <td class="px-2 py-2 text-sm text-gray-900 w-24">{{ $act->ActividadId }}</td>
                                <td class="px-1 py-2 text-sm text-right text-gray-900 w-20">{{ number_format((float)$porcentaje, 0) }}%</td>
                                <td class="px-1 py-2 text-center w-16">
                                    <input type="checkbox" {{ $checked ? 'checked' : '' }} disabled class="h-4 w-4 text-green-600 rounded" />
                                </td>
                                <td class="px-2 py-2 text-sm text-gray-900">{{ $operador }}</td>
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

        <!-- Personal Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <!-- Tejedor -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="text-sm font-semibold text-gray-600 mb-3 border-b pb-2">
                    <i class="fas fa-user mr-2 text-blue-500"></i>Tejedor
                </h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">Clave:</span>
                        <span class="text-sm font-medium">{{ $item->CveTejedor ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">Nombre:</span>
                        <span class="text-sm font-medium">{{ $item->NomTejedor ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- Supervisor -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <h3 class="text-sm font-semibold text-gray-600 mb-3 border-b pb-2">
                    <i class="fas fa-user-tie mr-2 text-purple-500"></i>Supervisor
                </h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">Clave:</span>
                        <span class="text-sm font-medium">{{ $item->CveSupervisor ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">Nombre:</span>
                        <span class="text-sm font-medium">{{ $item->NomSupervisor ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">Fecha:</span>
                        <span class="text-sm font-medium">{{ $item->FechaSupervisor ? \Carbon\Carbon::parse($item->FechaSupervisor)->format('d/m/Y') : '-' }}</span>
                    </div>
                </div>
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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Nota 1 -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 mb-2">Nota 1</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($comentarios->pluck('Nota1')->filter()->unique()->values() as $n1)
                            <button type="button" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-md text-sm"
                                    onclick="agregarNota(`{{ $n1 }}`)">{{ $n1 }}</button>
                        @endforeach
                    </div>
                </div>
                <!-- Nota 2 -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 mb-2">Nota 2</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach($comentarios->pluck('Nota2')->filter()->unique()->values() as $n2)
                            <button type="button" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-md text-sm"
                                    onclick="agregarNota(`{{ $n2 }}`)">{{ $n2 }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Clic en una nota para agregarla a Observaciones.</p>
        @endif
    </div>
    @endisset
</div>
@endsection

@push('scripts')
<script>
function terminarAtado(){
    Swal.fire({
        title: '¿Terminar Atado?',
        text: '¿Está seguro de que desea terminar este atado?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, terminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Aquí irá la lógica para terminar el atado
            console.log("Terminar Atado confirmado");
        }
    });
}

function calificarTejedor(){
    // Asignar operador = usuario en sesión
    fetch('{{ route('atadores.save') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ action: 'operador' })
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            Swal.fire({ icon: 'success', title: 'Operador asignado', timer: 1500, showConfirmButton: false });
            setTimeout(() => location.reload(), 800);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo asignar operador' });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error de red' }));
}

function autorizaSupervisor(){
    // Asignar supervisor = usuario en sesión
    fetch('{{ route('atadores.save') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ action: 'supervisor' })
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok){
            Swal.fire({ icon: 'success', title: 'Supervisor asignado', timer: 1500, showConfirmButton: false });
            setTimeout(() => location.reload(), 800);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo asignar supervisor' });
        }
    })
    .catch(() => Swal.fire({ icon: 'error', title: 'Error de red' }));
}

function guardarObservaciones(event){
    event.preventDefault();
    
    const observaciones = document.getElementById('observaciones').value;
    
    // Aquí irá la lógica para guardar las observaciones en la DB
    console.log('Observaciones:', observaciones);
    
    Swal.fire({
        icon: 'success',
        title: 'Observaciones Guardadas',
        text: 'Las observaciones se han guardado correctamente',
        showConfirmButton: false,
        timer: 2000
    });
}

// Agregar nota a Observaciones
function agregarNota(texto){
    const ta = document.getElementById('observaciones');
    if(!ta) return;
    const sep = ta.value && !ta.value.endsWith('\n') ? '\n' : '';
    ta.value = ta.value + sep + texto;
    ta.focus();
}
</script>
@endpush
