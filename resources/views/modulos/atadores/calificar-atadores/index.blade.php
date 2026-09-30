@extends('layouts.app')

@section('page-title', ($esKm ?? false) ? 'Atado Karl Mayer' : 'Calificar Atadores')

@section('navbar-right')
    <div class="flex items-center gap-2">
        @php
            $item = $montadoTelas->isNotEmpty() ? $montadoTelas->first() : null;
            $estatusActual = $item?->Estatus ?? 'En Proceso';
        @endphp
        <button type="button" id="btnTerminar" data-accion="terminar"
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            @if(in_array($estatusActual, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif>
            <i class="fas fa-stop mr-1"></i> Terminar Atado
        </button>
        <button type="button" id="btnCalificar" data-accion="calificar"
            class="px-2 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            @if($estatusActual !== 'Terminado') disabled @endif>
            <i class="fas fa-user-check mr-1"></i> Califica Tejedor
        </button>
        <button type="button" id="btnAutorizar" data-accion="autorizar"
            class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            @if($estatusActual !== 'Calificado') disabled @endif>
            <i class="fas fa-user-tie mr-1"></i> Autoriza Supervisor
        </button>
    </div>
@endsection

@section('content')
    @php
        $atadoActual = $montadoTelas->first();
        $usuarioActual = auth()->user();
        $configPagina = [
            'rutas' => [
                'guardar' => route('atadores.save'),
                'programa' => route('atadores.programa'),
                'devolucionGuardar' => route('atadores.devoluciones.store'),
                'devolucionEliminar' => route('atadores.devoluciones.destroy'),
                'devolucionJulios' => route('atadores.devoluciones.julios'),
                'devolucionUbicaciones' => route('atadores.devoluciones.ubicaciones'),
            ],
            'usuario' => $usuarioActual ? ['cve' => (string) $usuarioActual->numero_empleado, 'nombre' => (string) $usuarioActual->nombre] : null,
            'esKm' => (bool) ($esKm ?? false),
            'atado' => $atadoActual ? [
                'noJulio' => (string) $atadoActual->NoJulio,
                'noOrden' => (string) $atadoActual->NoProduccion,
                'refId' => $atadoActual->Id,
                'telar' => $atadoActual->NoTelarId,
                'tipo' => $atadoActual->Tipo,
                'soloLectura' => $atadoActual->Estatus === 'Autorizado',
            ] : null,
            // En Karl Mayer $devolucionActual es la primera fila de $devolucionesKm (el controller).
            'devolucion' => $atadoActual && $devolucionActual ? [
                'id' => $devolucionActual->Id,
                'no_julio' => $devolucionActual->NoJulio,
                'ubicacion' => $devolucionActual->Ubicacion,
                'bloqueada_por_ax' => ($esKm ?? false)
                    ? ($devolucionesKm ?? collect())->contains(fn ($fila) => (int) ($fila->AX ?? 0) === 1)
                    : (int) ($devolucionActual->AX ?? 0) === 1,
            ] : null,
            'km' => ($esKm ?? false) && $atadoActual ? [
                'guardar' => route('atadores.save'),
                'empleados' => url('/obtener-empleados'),
                'noJulio' => (string) $atadoActual->NoJulio,
                'noOrden' => (string) $atadoActual->NoProduccion,
                'yo' => $usuarioActual ? ['cve' => (string) $usuarioActual->numero_empleado, 'nombre' => (string) $usuarioActual->nombre, 'area' => (string) $usuarioActual->area] : null,
            ] : null,
        ];
    @endphp
    <div class="container mx-auto px-4 py-6" id="calificar-atado" data-pagina='@json($configPagina)'>


        @if($montadoTelas->isNotEmpty())
            @php
                $item = $montadoTelas->first();
                $esAutorizado = $item->Estatus === 'Autorizado';
                $devolucionesKm = $devolucionesKm ?? collect();
                $juliosKm = $juliosKm ?? [];
                if ($esKm ?? false) {
                    $hayDevolucion = $devolucionesKm->isNotEmpty();
                    $devolucionBloqueadaPorAx = $devolucionesKm->contains(fn ($fila) => (int) ($fila->AX ?? 0) === 1);
                    $primeraDevolucion = $devolucionesKm->first();
                    $fechaDevolucion = $primeraDevolucion && $primeraDevolucion->FechaDevol
                        ? \Carbon\Carbon::parse($primeraDevolucion->FechaDevol)->format('Y-m-d')
                        : now('America/Mexico_City')->format('Y-m-d');
                    $tipoBarra = trim((string) ($item->Tipo ?? ''));
                    $barraKm = preg_match('/^[1-4]$/', $tipoBarra) ? 'Barra '.$tipoBarra : ($tipoBarra !== '' ? $tipoBarra : '-');
                } else {
                    $hayDevolucion = !empty($devolucionActual);
                    $devolucionBloqueadaPorAx = $hayDevolucion && (int) ($devolucionActual->AX ?? 0) === 1;
                    $fechaDevolucion = $hayDevolucion && $devolucionActual->FechaDevol
                        ? \Carbon\Carbon::parse($devolucionActual->FechaDevol)->format('Y-m-d')
                        : '';
                }
            @endphp

            @if($esAutorizado)
                <!-- Alerta de solo lectura para registros Autorizados -->
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-r-md">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-green-600 text-xl mr-3"></i>
                        <div>
                            <h3 class="text-green-800 font-semibold">Registro Autorizado - Modo Solo Lectura</h3>
                            <p class="text-green-700 text-sm mt-1">Este registro ha sido autorizado y está disponible solo para
                                visualización. No se pueden realizar modificaciones.</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Resumen del Atado (4 bloques combinados + comentarios) -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <h3 class="mb-4 font-semibold text-gray-700 {{ ($esKm ?? false) ? 'text-xl font-bold text-center' : 'text-base' }}">{{ $esKm ? 'Atado de barra' : 'Resumen del Atado' }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Columna 1 -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Fecha del Atado</span>
                            <span
                                class="text-sm font-semibold text-gray-800">{{ $item->Fecha ? \Carbon\Carbon::parse($item->Fecha)->format('d/m/Y') : '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Un Orden</span>
                            <span class="text-sm font-semibold text-gray-800">{{ $item->NoProduccion ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Metros</span>
                            <span
                                class="text-sm font-semibold text-gray-800">{{ $item->Metros ? number_format($item->Metros, 2) : '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Merma Kg</span>
                            <div class="relative">
                                <input type="number" id="mergaKg" step="any" min="0" max="5" inputmode="decimal"
                                    value="{{ $item->MergaKg ?? '' }}"
                                    class="w-28 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                                    placeholder="0.00"
                                    @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                                <span id="mergaSavedIndicator"
                                    class="absolute -right-6 top-1/2 -translate-y-1/2 text-green-600 text-xs hidden">
                                    <i class="fas fa-check"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Calidad de Atado (1-10)</span>
                            @if($item->Calidad)
                                <span id="valCalidad"
                                    class="px-2 py-1 bg-blue-100 text-blue-800 rounded font-semibold text-sm">{{ $item->Calidad }}</span>
                            @else
                                <span id="valCalidad" class="text-sm text-gray-400">-</span>
                            @endif
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Cve Supervisor</span>
                            <span id="valCveSupervisor"
                                class="text-sm font-semibold text-gray-800">{{ $item->Estatus === 'Autorizado' ? ($item->CveSupervisor ?? '-') : '-' }}</span>
                        </div>
                    </div>

                    <!-- Columna 2 -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Fecha y Hora de Paro</span>
                            <div class="flex gap-2 items-center">
                                <input type="date" id="fechaParo"
                                    value="{{ data_get($item, 'FechaParo') ? \Carbon\Carbon::parse($item->FechaParo)->format('Y-m-d') : now('America/Mexico_City')->format('Y-m-d') }}"
                                    class="w-36 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                                    @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                                <input type="time" id="hrParo"
                                    value="{{ $item->HoraParo ? \Carbon\Carbon::parse($item->HoraParo)->format('H:i') : '' }}"
                                    class="w-24 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                                    @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                            </div>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Inicio de Atado</span>
                            <div class="flex gap-2 items-center">
                                <input type="date" id="fechaInicio"
                                    value="{{ $item->FechaInicio ? \Carbon\Carbon::parse($item->FechaInicio)->format('Y-m-d') : now('America/Mexico_City')->format('Y-m-d') }}"
                                    class="w-36 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                                    @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                                <input type="time" id="hrInicio"
                                    value="{{ $item->HrInicio ? \Carbon\Carbon::parse($item->HrInicio)->format('H:i') : '' }}"
                                    class="w-24 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                                    @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                            </div>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Fecha y Hora de Arranque</span>
                            <div class="flex gap-2 items-center">
                                <input type="date" id="fechaArranque"
                                    value="{{ data_get($item, 'FechaArranque') ? \Carbon\Carbon::parse($item->FechaArranque)->format('Y-m-d') : now('America/Mexico_City')->format('Y-m-d') }}"
                                    class="w-36 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                                    @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                                <input type="time" id="horaArranque"
                                    value="{{ $item->HoraArranque ? \Carbon\Carbon::parse($item->HoraArranque)->format('H:i') : '' }}"
                                    class="w-24 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                                    @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                            </div>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Lote Provee</span>
                            <span class="text-sm font-semibold text-gray-800">{{ $item->LoteProveedor ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Folio Paro</span>
                            <div class="relative">
                                <input type="text" id="folioParo" value="{{ $item->FolioParo ?? '' }}"
                                    class="w-36 px-2 py-1 text-sm text-right border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                                    placeholder="Folio paro"
                                    @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled @endif />
                                <span id="folioParoSavedIndicator"
                                    class="absolute -right-6 top-1/2 -translate-y-1/2 text-green-600 text-xs hidden">
                                    <i class="fas fa-check"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">5'S Orden y Limpieza (5-10)</span>
                            @if($item->Limpieza)
                                <span id="valLimpieza"
                                    class="px-2 py-1 bg-green-100 text-green-800 rounded font-semibold text-sm">{{ $item->Limpieza }}</span>
                            @else
                                <span id="valLimpieza" class="text-sm text-gray-400">-</span>
                            @endif
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Nom Supervisor</span>
                            <span id="valNomSupervisor"
                                class="text-sm font-semibold text-gray-800">{{ $item->Estatus === 'Autorizado' ? ($item->NomSupervisor ?? '-') : '-' }}</span>
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
                            <span class="text-sm font-semibold text-gray-800">{{ $esKm && preg_match('/^[1-4]$/', trim((string) $item->Tipo)) ? 'Barra '.$item->Tipo : ($item->Tipo ?? '-') }}</span>
                        </div>
                        <div class="flex justify-between items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase tracking-wide">No Julio</span>
                            <span class="text-sm font-semibold text-gray-800">{{ $item->NoJulio ?? '-' }}</span>
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
                            <span class="text-xs text-gray-500 uppercase tracking-wide">Fecha Supervisor</span>
                            <span id="valFechaHoraSupervisor" class="text-sm font-semibold text-gray-800">
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
                    <form id="formObservaciones">
                        <textarea id="observaciones" name="observaciones" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 transition-all duration-200"
                            placeholder="Escriba aquí las observaciones sobre el atado..."
                            @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled
                            @endif>{{ $item->Obs }}</textarea>

                    </form>
                    @if($item->comments_ata)
                        <p class="text-sm text-gray-700 mt-2"><strong>Comentarios del Atador:</strong> {{ $item->comments_ata }}</p>
                    @endif
                    @if($item->comments_tej)
                        <p class="text-sm text-gray-700 mt-2"><strong>Comentarios del Tejedor:</strong> {{ $item->comments_tej }}
                        </p>
                    @endif
                    @if($item->comments_sup)
                        <p class="text-sm text-gray-700 mt-2"><strong>Comentarios del Supervisor:</strong> {{ $item->comments_sup }}
                        </p>
                    @endif
                </div>
            </div>

            @unless($esKm)
            <!-- Maquinas y Actividades -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <!-- Tabla: AtaMontadoMaquinas -->
                <div class="bg-white rounded-lg shadow-md p-4 overflow-x-auto lg:col-span-1">
                    <h3 class="text-sm font-semibold text-gray-600 mb-3 border-b pb-2">Máquinas</h3>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Máquina</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($maquinasCatalogo as $maq)
                                @php
                                    $m = $maquinasMontado->get($maq->MaquinaId);
                                    $checked = $m && (int) ($m->Estado ?? 0) === 1;
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900">{{ $maq->MaquinaId }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <input type="checkbox" {{ $checked ? 'checked' : '' }} class="h-4 w-4 text-blue-600 rounded"
                                            data-maquina="{{ $maq->MaquinaId }}"
                                            @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled
                                            @endif />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-3 text-center text-sm text-gray-500">No hay máquinas registradas
                                    </td>
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
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                    Actividad</th>
                                <th
                                    class="px-1 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                                    %</th>
                                <th
                                    class="px-1 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                                    Estado</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Operador</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($actividadesCatalogo->reverse() as $act)
                                @php
                                    $a = $actividadesMontado->get($act->ActividadId);
                                    $checked = $a && (int) ($a->Estado ?? 0) === 1;
                                    $porcentaje = $a->Porcentaje ?? $act->Porcentaje;
                                    $operador = $a && ($a->NomEmpl || $a->CveEmpl)
                                        ? trim(($a->CveEmpl ? $a->CveEmpl : '') . ($a->NomEmpl ? ' - ' . $a->NomEmpl : ''))
                                        : '-';
                                @endphp
                                <tr id="actividad-{{ $act->ActividadId }}">
                                    <td class="px-2 py-2 text-sm text-gray-900 w-24">{{ $act->ActividadId }}</td>
                                    <td class="px-1 py-2 text-sm text-right text-gray-900 w-20">
                                        {{ number_format((float) $porcentaje, 0) }}%</td>
                                    <td class="px-1 py-2 text-center w-16">
                                        <input type="checkbox" {{ $checked ? 'checked' : '' }}
                                            class="h-4 w-4 text-green-600 rounded"
                                            data-actividad="{{ $act->ActividadId }}"
                                            @if(in_array($item->Estatus, ['Terminado', 'Calificado', 'Autorizado'])) disabled
                                            @endif />
                                    </td>
                                    <td class="px-2 py-2 text-sm text-gray-900 operador-cell">{{ $operador }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-center text-sm text-gray-500">No hay actividades
                                        registradas</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endunless

            @if($esKm)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6 items-start">
                @include('modulos.atadores.calificar-atadores._proceso-km', [
                    'titulo' => 'Montado',
                    'prefijo' => 'montado',
                    'registro' => $kmMontado,
                ])
                @include('modulos.atadores.calificar-atadores._proceso-km', [
                    'titulo' => 'Enhebrado',
                    'prefijo' => 'enhebrado',
                    'registro' => $kmEnhebrado,
                ])
            </div>
            @endif

            <!-- Devolución -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <label class="flex items-center gap-3 cursor-pointer select-none w-fit">
                    <input type="checkbox" id="chkDevolucion" class="h-4 w-4 text-blue-600 rounded"
                        @if($hayDevolucion) checked @endif
                        @if($esAutorizado || $devolucionBloqueadaPorAx) disabled @endif />
                    <span class="text-base font-semibold text-gray-700">Devolución</span>
                </label>

                <div id="devolucionPanel" class="{{ $hayDevolucion ? '' : 'hidden' }} mt-4 border-t pt-4">
                    <!-- @if($devolucionBloqueadaPorAx)
                        <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            Esta devolución ya fue procesada en AX (AX = 1) y está bloqueada para cambios.
                        </div>
                    @endif -->

                    <fieldset class="m-0 min-w-0 border-0 p-0" @disabled($devolucionBloqueadaPorAx)>
                    @if($esKm ?? false)
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900">{{ $barraKm }}</span>
                        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-800">KM1</span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-700">
                            Ubicación
                            <span class="rounded-full bg-white px-2 py-0.5 text-sm font-semibold text-blue-800">KM1</span>
                        </span>
                        <span class="text-sm text-gray-600">Telar {{ $item->NoTelarId ?? '-' }}</span>
                        @php $anterioresKm = $anterioresKm ?? collect(); @endphp
                        <input type="hidden" id="dev_anterior_km" value="{{ $anteriorKm->Id ?? '' }}">
                        @if($anterioresKm->isNotEmpty())
                            {{-- Atado anterior de la barra del que salen los julios a devolver (como el select de julio en Jacquard/Smit). --}}
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                Atado anterior
                                <select id="dev_anterior_km_select"
                                    class="min-h-9 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    @foreach($anterioresKm as $atadoAnt)
                                        <option value="{{ $atadoAnt->Id }}" @selected((int) ($anteriorKm->Id ?? 0) === (int) $atadoAnt->Id)>
                                            Orden {{ $atadoAnt->NoProduccion }} · {{ $atadoAnt->NoJulio }} · {{ \Carbon\Carbon::parse($atadoAnt->Fecha)->format('d/m/Y') }} T{{ $atadoAnt->Turno }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        @php $cuentasKm = collect($juliosKm)->pluck('cuenta')->filter()->unique()->values(); @endphp
                        @if($cuentasKm->count() > 1)
                            {{-- ponytail: filtro solo visual; se guardan todas las filas --}}
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                Cuenta
                                <select id="dev_cuenta_km"
                                    class="min-h-9 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Todas</option>
                                    @foreach($cuentasKm as $cuentaKm)
                                        <option value="{{ $cuentaKm }}">{{ $cuentaKm }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label class="ml-auto flex items-center gap-2 text-sm font-medium text-gray-700">
                            Fecha
                            <input type="date" id="dev_fecha_km" required value="{{ $fechaDevolucion }}"
                                class="dev-km-input min-h-9 px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </label>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-blue-500 text-white">
                                <tr>
                                    <th class="px-2 py-2 text-left font-medium">Julio</th>
                                    <th class="px-2 py-2 text-left font-medium">Orden</th>
                                    <th class="px-2 py-2 text-left font-medium">Folio Dev</th>
                                    <th class="px-2 py-2 text-left font-medium">Cuenta</th>
                                    <th class="px-2 py-2 text-left font-medium">Calibre</th>
                                    <th class="px-2 py-2 text-left font-medium">Hilo</th>
                                    <th class="px-2 py-2 text-left font-medium">Metros</th>
                                    <th class="px-2 py-2 text-left font-medium">Kilos</th>
                                    <th class="px-2 py-2 text-left font-medium">Obs</th>
                                </tr>
                            </thead>
                            <tbody id="devKmBody">
                                @forelse($juliosKm as $filaKm)
                                    <tr data-julio="{{ $filaKm['julio'] }}" data-orden="{{ $filaKm['orden'] }}" data-cuenta="{{ $filaKm['cuenta'] }}">
                                        <td class="px-2 py-2 whitespace-nowrap font-medium text-gray-800">{{ $filaKm['julio'] }}</td>
                                        <td class="px-2 py-2 whitespace-nowrap text-gray-700">{{ $filaKm['orden'] !== '' ? $filaKm['orden'] : '-' }}</td>
                                        <td class="px-2 py-2 whitespace-nowrap font-medium text-blue-800">{{ $filaKm['folio_dev'] ?? '-' }}</td>
                                        <td class="px-2 py-2">
                                            <input data-campo="cuenta" maxlength="10" value="{{ $filaKm['cuenta'] }}"
                                                class="dev-km-input w-24 px-2 py-1 border border-gray-300 rounded" />
                                        </td>
                                        <td class="px-2 py-2">
                                            <input data-campo="calibre" maxlength="10" value="{{ $filaKm['calibre'] }}"
                                                class="dev-km-input w-20 px-2 py-1 border border-gray-300 rounded" />
                                        </td>
                                        <td class="px-2 py-2">
                                            <input data-campo="hilo" maxlength="20" value="{{ $filaKm['hilo'] }}" title="{{ $filaKm['hilo_completo'] }}"
                                                class="dev-km-input w-36 px-2 py-1 border border-gray-300 rounded" />
                                        </td>
                                        <td class="px-2 py-2">
                                            <input data-campo="metros" type="number" step="any" min="0" value="{{ $filaKm['metros'] }}"
                                                class="dev-km-input w-24 px-2 py-1 border border-gray-300 rounded" />
                                        </td>
                                        <td class="px-2 py-2">
                                            <input data-campo="kilos" type="number" step="any" min="0" value="{{ $filaKm['kilos'] }}"
                                                class="dev-km-input w-24 px-2 py-1 border border-gray-300 rounded" />
                                        </td>
                                        <td class="px-2 py-2" style="width: 36rem; min-width: 36rem;">
                                            <input data-campo="obs" maxlength="255" value="{{ $filaKm['obs'] }}"
                                                class="dev-km-input box-border px-2 py-1 border border-gray-300 rounded"
                                                style="width: 100%; min-width: 34rem;" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-3 py-4 text-center text-gray-500">No hay un atado anterior de esta barra. Desmarca Devolución para terminar sin devolver.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                        {{-- Fila 1: Telar | Ubicación | Cuenta | Lote --}}
                        <div>
                            <label for="dev_telar" class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Telar
                            </label>
                            <select id="dev_telar" required disabled
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed focus:outline-none">
                                <option value="">Seleccione</option>
                                @if($hayDevolucion && $devolucionActual->NoTelarId && !($telaresCatalogo ?? collect())->contains($devolucionActual->NoTelarId))
                                    <option value="{{ $devolucionActual->NoTelarId }}" selected>{{ $devolucionActual->NoTelarId }}</option>
                                @endif
                                @foreach($telaresCatalogo ?? [] as $telarOpcion)
                                    <option value="{{ $telarOpcion }}" @selected($hayDevolucion && (string) $devolucionActual->NoTelarId === (string) $telarOpcion)>{{ $telarOpcion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="dev_ubicacion" class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Ubicación
                            </label>
                            <input type="text" id="dev_ubicacion" list="dev_ubicaciones_sugeridas" autocomplete="off" required
                                value="{{ $hayDevolucion ? $devolucionActual->Ubicacion : '' }}"
                                placeholder="Escriba o seleccione una ubicación"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                            <datalist id="dev_ubicaciones_sugeridas">
                                @if($hayDevolucion && $devolucionActual->Ubicacion)
                                    <option value="{{ $devolucionActual->Ubicacion }}"></option>
                                @endif
                            </datalist>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Cuenta</label>
                            <input type="text" id="dev_cuenta" maxlength="10" required
                                value="{{ $hayDevolucion ? $devolucionActual->Cuenta : '' }}"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Lote</label>
                            {{-- readonly (no disabled): el valor debe poder leerse/actualizarse al cambiar Julio --}}
                            <input type="text" id="dev_lote" readonly required tabindex="-1"
                                value="{{ $hayDevolucion ? $devolucionActual->NoProduccion : '' }}"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed focus:outline-none" />
                        </div>

                        {{-- Fila 2: Julio | Metros | Calibre | Tipo --}}
                        <div>
                            <label for="dev_no_julio" class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Julio
                            </label>
                            <select id="dev_no_julio" required
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                <option value="">Seleccione un telar primero</option>
                                @if($hayDevolucion && $devolucionActual->NoJulio)
                                    <option value="{{ $devolucionActual->NoJulio }}" selected>{{ $devolucionActual->NoJulio }}</option>
                                @endif
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Metros
                            </label>
                            <input type="number" step="any" min="0" id="dev_metros" required
                                value="{{ $hayDevolucion ? $devolucionActual->Metros : '' }}"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Calibre</label>
                            <input type="text" id="dev_calibre" maxlength="10" required
                                value="{{ $hayDevolucion ? $devolucionActual->Calibre : '' }}"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Tipo</label>
                            <select id="dev_tipo" disabled required
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed focus:outline-none">
                                <option value="">Seleccione</option>
                                @if($hayDevolucion && $devolucionActual->Tipo && !in_array($devolucionActual->Tipo, ['Rizo', 'Pie'], true))
                                    <option value="{{ $devolucionActual->Tipo }}" selected>{{ $devolucionActual->Tipo }}</option>
                                @endif
                                <option value="Rizo" @selected($hayDevolucion && $devolucionActual->Tipo === 'Rizo')>Rizo</option>
                                <option value="Pie" @selected($hayDevolucion && $devolucionActual->Tipo === 'Pie')>Pie</option>
                            </select>
                        </div>

                        {{-- Fila 3: Kilos | Fecha | Hilo | Obs --}}
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Kilos
                            </label>
                            <input type="number" step="any" min="0" id="dev_kilos" required
                                value="{{ $hayDevolucion ? $devolucionActual->Kilos : '' }}"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" />
                        </div>
                        <div>
                            <label for="dev_fecha" class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Fecha
                            </label>
                            <input type="date" id="dev_fecha" required
                                value="{{ $fechaDevolucion }}"
                                class="w-full min-h-10 px-2 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200 cursor-pointer" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Hilo</label>
                            <input type="text" id="dev_hilo" maxlength="20" required
                                value="{{ $hayDevolucion ? $devolucionActual->Hilo : '' }}"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Obs</label>
                            <textarea id="dev_obs" rows="2" maxlength="255"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">{{ $hayDevolucion ? $devolucionActual->Obs : '' }}</textarea>
                        </div>
                    </div>

                    {{-- Validación temporalmente pausada: <div id="dev_disponibilidad" class="hidden mt-4 rounded border px-3 py-2 text-sm" aria-live="polite"></div> --}}
                    @endif

                    <div id="estadoAutoguardadoDevolucion" class="mt-4 text-right text-xs text-gray-500" aria-live="polite"></div>
                    </fieldset>
                </div>
            </div>

        @else
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                <p class="text-gray-500 text-lg">No hay datos disponibles en montado de telas</p>
                <p class="text-gray-400 text-sm mt-2">Seleccione un registro desde el programa de atadores</p>
            </div>
        @endif

        @if(! ($esKm ?? false) && isset($comentarios))
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
                            <h4
                                class="text-sm font-semibold text-gray-700 mb-3 bg-gray-50 px-3 py-2 rounded-t-md border-b-2 border-blue-500">
                                Nota 1</h4>
                            <div class="space-y-3">
                                @foreach($comentarios->pluck('Nota1')->filter()->unique()->values() as $n1)
                                    <div
                                        class="px-4 py-3 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-md text-sm leading-relaxed whitespace-normal">
                                        {{ $n1 }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <!-- Nota 2 -->
                        <div>
                            <h4
                                class="text-sm font-semibold text-gray-700 mb-3 bg-gray-50 px-3 py-2 rounded-t-md border-b-2 border-green-500">
                                Nota 2</h4>
                            <div class="space-y-3">
                                @foreach($comentarios->pluck('Nota2')->filter()->unique()->values() as $n2)
                                    <div
                                        class="px-4 py-3 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-md text-sm leading-relaxed whitespace-normal">
                                        {{ $n2 }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Formularios de las acciones del navbar (antes SweetAlert2 con html); los abre calificar/index.ts --}}
    <x-ui.modal-base id="modalTerminarAtado" title="¿Terminar atado?">
        <form id="formTerminarAtado" class="space-y-3">
            <p class="text-sm text-gray-600">Se registrará la hora de arranque con la hora actual y el estatus cambiará a "Terminado".</p>
            <x-ui.field as="textarea" name="comentarios" id="comentariosAtador" label="Comentarios del Atador" rows="4"
                placeholder="Escriba sus comentarios aquí (opcional)..." />
        </form>
        <x-slot:footer>
            <x-ui.button variant="neutral" data-ui-modal-close-target="modalTerminarAtado">Cancelar</x-ui.button>
            <x-ui.button variant="create" type="submit" form="formTerminarAtado">Sí, terminar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal-base>

    <x-ui.modal-base id="modalCalificarTejedor" title="Calificar tejedor">
        <form id="formCalificarTejedor" class="space-y-3">
            <x-ui.field as="select" name="calidad" id="calificarCalidad" label="Calidad de Atado (1-10)" required>
                <option value="">Seleccione</option>
                @foreach (range(1, 10) as $n)
                    <option value="{{ $n }}">{{ $n }}</option>
                @endforeach
            </x-ui.field>
            <x-ui.field as="select" name="limpieza" id="calificarLimpieza" label="Orden y Limpieza (5-10)" required>
                <option value="">Seleccione</option>
                @foreach (range(5, 10) as $n)
                    <option value="{{ $n }}">{{ $n }}</option>
                @endforeach
            </x-ui.field>
            <x-ui.field as="textarea" name="comentarios" id="comentariosTejedor" label="Comentarios del Tejedor" rows="3"
                placeholder="Escriba sus comentarios aquí (opcional)..." />
        </form>
        <x-slot:footer>
            <x-ui.button variant="neutral" data-ui-modal-close-target="modalCalificarTejedor">Cancelar</x-ui.button>
            <x-ui.button variant="create" type="submit" form="formCalificarTejedor">Guardar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal-base>

    <x-ui.modal-base id="modalAutorizarSupervisor" title="Autorizar supervisor">
        <form id="formAutorizarSupervisor" class="space-y-3">
            <p class="text-sm text-gray-600">Esto completará el proceso y regresará al programa de atadores.</p>
            <x-ui.field as="textarea" name="comentarios" id="comentariosSupervisor" label="Comentarios del Supervisor" rows="4"
                placeholder="Escriba sus comentarios aquí (opcional)..." />
        </form>
        <x-slot:footer>
            <x-ui.button variant="neutral" data-ui-modal-close-target="modalAutorizarSupervisor">Cancelar</x-ui.button>
            <x-ui.button variant="create" type="submit" form="formAutorizarSupervisor">Sí, autorizar</x-ui.button>
        </x-slot:footer>
    </x-ui.modal-base>
@endsection

@push('scripts')
    @vite('resources/js/modulos/atadores/calificar/index.ts')
@endpush
