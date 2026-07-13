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
                $esAutorizado = $item->Estatus === 'Autorizado';
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
                <h3 class="text-base font-semibold text-gray-700 mb-4">Resumen del Atado</h3>
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
                                    placeholder="0.00" oninput="handleMergaChange(this.value)" onblur="normalizarMergaInput()"
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
                                    placeholder="Folio paro" oninput="handleFolioParoChange(this.value)"
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
                            <span class="text-sm font-semibold text-gray-800">{{ $item->Tipo ?? '-' }}</span>
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
                    <form id="formObservaciones" onsubmit="guardarObservaciones(event)">
                        <textarea id="observaciones" name="observaciones" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 hover:border-blue-400 transition-all duration-200"
                            placeholder="Escriba aquí las observaciones sobre el atado..." oninput="handleObservacionesChange()"
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
                                            onchange="toggleMaquina('{{ $maq->MaquinaId }}', this.checked)"
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
                                            onchange="toggleActividad('{{ $act->ActividadId }}', this.checked)"
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

            <!-- Devolución -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <label class="flex items-center gap-3 cursor-pointer select-none w-fit">
                    <input type="checkbox" id="chkDevolucion" class="h-4 w-4 text-blue-600 rounded"
                        onchange="toggleDevolucion(this.checked)" />
                    <span class="text-base font-semibold text-gray-700">Devolución</span>
                </label>

                <div id="devolucionPanel" class="hidden mt-4 border-t pt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                        <!-- Fila 1: Telar | Ubicación | Cuenta | Lote -->
                        <div>
                            <label for="dev_telar" class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Telar
                            </label>
                            <select id="dev_telar" onchange="onCambioTelarDevolucion(this.value)"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                <option value="">Seleccione</option>
                                @foreach($telaresCatalogo ?? [] as $telarOpcion)
                                    <option value="{{ $telarOpcion }}">{{ $telarOpcion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="dev_ubicacion" class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Ubicación
                            </label>
                            <select id="dev_ubicacion"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                <option value="">Seleccione</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Cuenta</label>
                            <input type="text" id="dev_cuenta" maxlength="10"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Lote</label>
                            <input type="text" id="dev_lote" readonly
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed focus:outline-none" />
                        </div>

                        <!-- Fila 2: Julio | Metros | Calibre | Tipo -->
                        <div>
                            <label for="dev_no_julio" class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Julio
                            </label>
                            <select id="dev_no_julio"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                <option value="">Seleccione un telar</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Metros
                            </label>
                            <input type="number" step="any" min="0" id="dev_metros"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Calibre</label>
                            <input type="text" id="dev_calibre" maxlength="10"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Tipo</label>
                            <select id="dev_tipo"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                                <option value="">Seleccione</option>
                                <option value="Rizo">Rizo</option>
                                <option value="Pie">Pie</option>
                            </select>
                        </div>

                        <!-- Fila 3: Kilos | Fecha | Hilo | Obs -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Kilos
                            </label>
                            <input type="number" step="any" min="0" id="dev_kilos"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" />
                        </div>
                        <div>
                            <label for="dev_fecha" class="block text-xs font-bold uppercase tracking-wide mb-1">
                                Fecha
                            </label>
                            <input type="date" id="dev_fecha"
                                onclick="abrirCalendarioFecha(this)"
                                onfocus="abrirCalendarioFecha(this)"
                                class="w-full min-h-10 px-2 py-2 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200 cursor-pointer" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Hilo</label>
                            <input type="text" id="dev_hilo" maxlength="20"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-gray-600 mb-1">Obs</label>
                            <textarea id="dev_obs" rows="2" maxlength="255"
                                class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button type="button" id="btnGuardarDevolucion" onclick="guardarDevolucion()"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-save mr-1"></i> Guardar Devolución
                        </button>
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
        @endisset
    </div>
@endsection

@push('scripts')
    <script>
        // Usuario actual disponible para reflejar en UI tras guardados
        const currentUser = {!! auth()->check() ? json_encode(['numero_empleado' => auth()->user()->numero_empleado, 'nombre' => auth()->user()->nombre]) : 'null' !!};

        // Datos del registro actual para identificar correctamente en las peticiones
        @if($montadoTelas->isNotEmpty())
            const currentNoJulio = {!! json_encode($montadoTelas->first()->NoJulio ?? null) !!};
            const currentNoOrden = {!! json_encode($montadoTelas->first()->NoProduccion ?? null) !!};
            const esSoloLectura = {!! json_encode($montadoTelas->first()->Estatus === 'Autorizado') !!};
            const currentRefId = {!! json_encode($montadoTelas->first()->Id ?? null) !!};
            const currentTelar = {!! json_encode($montadoTelas->first()->NoTelarId ?? null) !!};
            const currentLote = {!! json_encode($montadoTelas->first()->LoteProveedor ?? null) !!};
            const currentTipoAtado = {!! json_encode($montadoTelas->first()->Tipo ?? null) !!};
        @else
            const currentNoJulio = null;
            const currentNoOrden = null;
            const esSoloLectura = false;
            const currentRefId = null;
            const currentTelar = null;
            const currentLote = null;
            const currentTipoAtado = null;
        @endif

    // Información de actividades para validación
    const actividadesData = {!! json_encode($actividadesCatalogo->map(function ($act) use ($actividadesMontado) {
        $a = $actividadesMontado->get($act->ActividadId);
        return [
            'id' => $act->ActividadId,
            'estado' => $a && (int) ($a->Estado ?? 0) === 1
        ];
    })) !!};

        // Abre el datepicker nativo (mejor UX en tablet al editar la fecha).
        function abrirCalendarioFecha(input) {
            if (!input || input.disabled) return;
            try {
                if (typeof input.showPicker === 'function') {
                    input.showPicker();
                }
            } catch (e) {
                // Algunos navegadores bloquean showPicker fuera de un gesto directo;
                // el type="date" sigue permitiendo editar con el control nativo.
            }
        }

        // Cache en memoria del catálogo de ubicaciones (WMSLocation en TI-PRO)
        // para no repetir la consulta cada vez que se abre el panel.
        let ubicacionesDevolucionCargadas = false;

        function cargarUbicacionesDevolucion() {
            const select = document.getElementById('dev_ubicacion');
            if (!select || ubicacionesDevolucionCargadas) return;

            const valorPrevio = select.value;
            select.disabled = true;

            fetch('{{ route('atadores.devoluciones.ubicaciones') }}')
                .then(r => r.json())
                .then(res => {
                    if (res.ok && Array.isArray(res.ubicaciones)) {
                        select.innerHTML = '<option value="">Seleccione</option>';
                        res.ubicaciones.forEach(ubi => {
                            const opt = document.createElement('option');
                            opt.value = ubi;
                            opt.textContent = ubi;
                            select.appendChild(opt);
                        });
                        ubicacionesDevolucionCargadas = true;
                        if (valorPrevio) select.value = valorPrevio;
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Ubicaciones no disponibles',
                            text: res.message || 'No se pudo cargar el catálogo de ubicaciones (TI-PRO).'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'No se pudo conectar con TI-PRO para cargar las ubicaciones.'
                    });
                })
                .finally(() => { select.disabled = false; });
        }

        // Cache en memoria de los julios ya consultados por telar (evita repetir
        // la consulta si el usuario vuelve a seleccionar el mismo telar).
        const juliosDevolucionCache = {};

        // Se dispara al cambiar el Telar en el panel de Devolución: recarga el
        // select de "Julio" filtrando por ese telar + el mismo Tipo del atado actual.
        function onCambioTelarDevolucion(telar) {
            cargarJuliosDevolucion(telar, { autoseleccionar: true });
        }

        function cargarJuliosDevolucion(telar, { autoseleccionar = false } = {}) {
            const select = document.getElementById('dev_no_julio');
            if (!select) return;

            if (!telar) {
                select.innerHTML = '<option value="">Seleccione un telar</option>';
                return;
            }

            // Precarga Cuenta/Calibre/Hilo sugeridos del julio anterior (siguen
            // siendo campos editables, solo se prellenan como punto de partida).
            const precargarDatosJulio = (datos) => {
                const setValor = (id, valor) => {
                    const el = document.getElementById(id);
                    if (el) el.value = valor ?? '';
                };
                setValor('dev_cuenta', datos.cuenta);
                setValor('dev_calibre', datos.calibre);
                setValor('dev_hilo', datos.hilo);
            };

            const render = (datos) => {
                select.innerHTML = '<option value="">Seleccione</option>';
                datos.julios.forEach(j => {
                    const opt = document.createElement('option');
                    opt.value = j;
                    opt.textContent = j;
                    select.appendChild(opt);
                });
                if (autoseleccionar && datos.sugerido) {
                    select.value = datos.sugerido;
                    precargarDatosJulio(datos);
                }
            };

            if (juliosDevolucionCache[telar]) {
                render(juliosDevolucionCache[telar]);
                return;
            }

            select.disabled = true;
            select.innerHTML = '<option value="">Cargando...</option>';

            const params = new URLSearchParams({ telar });
            if (currentTipoAtado) params.set('tipo', currentTipoAtado);
            if (currentRefId) params.set('exclude_id', currentRefId);

            fetch('{{ route('atadores.devoluciones.julios') }}?' + params.toString())
                .then(r => r.json())
                .then(res => {
                    if (res.ok && Array.isArray(res.julios)) {
                        const datos = {
                            julios: res.julios,
                            sugerido: res.sugerido || null,
                            cuenta: res.cuenta || null,
                            calibre: res.calibre || null,
                            hilo: res.hilo || null,
                        };
                        juliosDevolucionCache[telar] = datos;
                        render(datos);
                    } else {
                        select.innerHTML = '<option value="">Seleccione</option>';
                        Swal.fire({
                            icon: 'warning',
                            title: 'Julios no disponibles',
                            text: res.message || 'No se pudo cargar los julios atados de ese telar.'
                        });
                    }
                })
                .catch(() => {
                    select.innerHTML = '<option value="">Seleccione</option>';
                    Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo cargar los julios de ese telar.' });
                })
                .finally(() => { select.disabled = false; });
        }

        // Mostrar/ocultar panel de Devolución según el check
        function toggleDevolucion(checked) {
            const panel = document.getElementById('devolucionPanel');
            if (!panel) return;
            panel.classList.toggle('hidden', !checked);

            // Al abrir, prellenar campos informativos si están vacíos
            if (checked) {
                cargarUbicacionesDevolucion();

                const setSiVacio = (id, valor) => {
                    const el = document.getElementById(id);
                    if (el && !el.value && valor != null) el.value = valor;
                };

                const telarSelect = document.getElementById('dev_telar');
                if (telarSelect && !telarSelect.value && currentTelar) {
                    telarSelect.value = currentTelar;
                    // Si el telar actual no está en el catálogo, se agrega para no perder el dato.
                    if (telarSelect.value !== String(currentTelar)) {
                        const opt = document.createElement('option');
                        opt.value = currentTelar;
                        opt.textContent = currentTelar;
                        telarSelect.appendChild(opt);
                        telarSelect.value = currentTelar;
                    }
                }
                if (telarSelect && telarSelect.value) {
                    cargarJuliosDevolucion(telarSelect.value, { autoseleccionar: true });
                }

                // Lote = "Dev" + NoProduccion (se guarda en la columna NoProduccion de AtaDevoluciones)
                setSiVacio('dev_lote', currentNoOrden ? ('Dev' + currentNoOrden) : null);
                setSiVacio('dev_tipo', currentTipoAtado);
                const fecha = document.getElementById('dev_fecha');
                if (fecha && !fecha.value) {
                    fecha.value = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD local
                }
            }
        }

        function guardarDevolucion() {
            if (!currentRefId) {
                Swal.fire({ icon: 'error', title: 'Sin atado', text: 'No hay un atado asociado para registrar la devolución.' });
                return;
            }

            const val = (id) => {
                const el = document.getElementById(id);
                return el ? el.value.trim() : '';
            };

            const kilos = val('dev_kilos');
            const metros = val('dev_metros');
            if ((kilos === '' || parseFloat(kilos) <= 0) && (metros === '' || parseFloat(metros) <= 0)) {
                Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Captura al menos Kilos o Metros para registrar la devolución.' });
                return;
            }

            const payload = {
                ref_id: currentRefId,
                telar: val('dev_telar') || null,
                no_julio: val('dev_no_julio') || null,
                no_produccion: currentNoOrden || null,
                kilos: kilos !== '' ? parseFloat(kilos) : null,
                metros: metros !== '' ? parseFloat(metros) : null,
                ubicacion: val('dev_ubicacion') || null,
                fecha_devol: val('dev_fecha') || null,
                cuenta: val('dev_cuenta') || null,
                calibre: val('dev_calibre') || null,
                hilo: val('dev_hilo') || null,
                tipo: val('dev_tipo') || null,
                obs: val('dev_obs') || null,
            };

            const btn = document.getElementById('btnGuardarDevolucion');
            if (btn) btn.disabled = true;

            fetch('{{ route('atadores.devoluciones.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload)
            })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        Swal.fire({ icon: 'success', title: 'Devolución registrada', timer: 1500, showConfirmButton: false });
                        // Limpiar campos capturables y cerrar el panel
                        ['dev_ubicacion', 'dev_cuenta', 'dev_lote', 'dev_metros', 'dev_calibre', 'dev_tipo', 'dev_kilos', 'dev_hilo', 'dev_obs'].forEach(id => {
                            const el = document.getElementById(id);
                            if (el) el.value = '';
                        });
                        const chk = document.getElementById('chkDevolucion');
                        if (chk) chk.checked = false;
                        toggleDevolucion(false);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo registrar la devolución' });
                    }
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor.' }))
                .finally(() => { if (btn) btn.disabled = false; });
        }

        // Auto-guardado de observaciones
        let autoSaveTimeout = null;
        let mergaSaveTimeout = null;
        let folioParoSaveTimeout = null;
        const MERGA_MAX = 5;

        function normalizarMergaNumero(valor) {
            return Math.round((valor + Number.EPSILON) * 100) / 100;
        }

        function normalizarMergaTexto(valor) {
            return normalizarMergaNumero(valor).toString();
        }

        function normalizarMergaInput() {
            const input = document.getElementById('mergaKg');
            if (!input) {
                return;
            }

            const valor = input.value.trim();
            if (valor === '') {
                return;
            }

            const mergaNum = parseFloat(valor);
            if (!isNaN(mergaNum)) {
                input.value = normalizarMergaTexto(mergaNum);
            }
        }

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
            const input = document.getElementById('mergaKg');

            // Ocultar indicador mientras se escribe
            if (indicator) {
                indicator.classList.add('hidden');
            }

            // Limpiar timeout anterior
            if (mergaSaveTimeout) {
                clearTimeout(mergaSaveTimeout);
            }

            const mergaNum = parseFloat(valor);
            if (valor !== '' && !isNaN(mergaNum) && mergaNum > MERGA_MAX) {
                if (input) {
                    input.value = MERGA_MAX.toString();
                }
                Swal.fire({
                    icon: 'warning',
                    title: 'Merma fuera de rango',
                    text: 'La merma no puede ser mayor a 5 kg.'
                });
                guardarMerga(MERGA_MAX.toString());
                return;
            }

            // Guardar después de 1.5 segundos de inactividad
            if (valor && valor !== '') {
                mergaSaveTimeout = setTimeout(() => {
                    guardarMerga(input ? input.value : valor);
                }, 1500);
            }
        }

        function handleFolioParoChange(valor) {
            const indicator = document.getElementById('folioParoSavedIndicator');

            if (indicator) {
                indicator.classList.add('hidden');
            }

            if (folioParoSaveTimeout) {
                clearTimeout(folioParoSaveTimeout);
            }

            folioParoSaveTimeout = setTimeout(() => {
                guardarFolioParo(valor);
            }, 1200);
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
                    if (res.ok) {
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


        async function terminarAtado() {
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

            if (mergaValor > MERGA_MAX) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Merma fuera de rango',
                    text: 'La merma no puede ser mayor a 5 kg.'
                });
                mergaInput.focus();
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

            if (!formValues) return;

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
                    if (res.ok) {
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

        async function calificarTejedor() {
            const { value: formValues } = await Swal.fire({
                title: 'Calificar Tejedor',
                html: `
                <div style="text-align:left; padding:0 10px;">
                    <label style="display:block; font-size:14px; margin-bottom:4px;">Calidad de Atado (1-10)</label>
                    <select id="swCalidad" class="swal2-input" style="width:100%; margin:0 0 12px 0;">
                        <option value="">Seleccione</option>
                        ${Array.from({ length: 10 }, (_, i) => `<option value="${i + 1}">${i + 1}</option>`).join('')}
                    </select>
                    <label style="display:block; font-size:14px; margin-bottom:4px;">Orden y Limpieza (5-10)</label>
                    <select id="swLimpieza" class="swal2-input" style="width:100%; margin:0 0 12px 0;">
                        <option value="">Seleccione</option>
                        ${Array.from({ length: 6 }, (_, i) => `<option value="${i + 5}">${i + 5}</option>`).join('')}
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
                    if (!calidad || !limpieza) {
                        Swal.showValidationMessage('Seleccione calidad y limpieza');
                        return false;
                    }
                    return { calidad, limpieza, comentarios };
                },
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                width: '450px'
            });

            if (!formValues) return;

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
                    if (res.ok) {
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

        async function autorizaSupervisor() {
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

            if (!formValues) return;

            // Asignar supervisor = usuario en sesión
            fetch('{{ route('atadores.save') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    action: 'supervisor',
                    comments_sup: formValues.comentarios || '',
                    no_julio: currentNoJulio,
                    no_orden: currentNoOrden
                })
            })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
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

        function guardarObservaciones(event) {
            event.preventDefault();

            if (esSoloLectura) {
                Swal.fire({
                    icon: 'info',
                    title: 'Solo Lectura',
                    text: 'Este registro está autorizado y no se pueden realizar modificaciones'
                });
                return;
            }

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
                    if (res.ok) {
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

        // Guardar Merma Kg
        function guardarMerga(valor) {
            if (esSoloLectura) {
                console.log('Registro autorizado: solo lectura, no se puede guardar');
                return;
            }


            // Validar que sea un número válido
            const mergaNum = parseFloat(valor);
            if (isNaN(mergaNum)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Valor inválido',
                    text: 'La merma debe ser un número válido'
                });
                return;
            }

            if (mergaNum > MERGA_MAX) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Merma fuera de rango',
                    text: 'La merma no puede ser mayor a 5 kg.'
                });
                return;
            }

            const mergaNumNormalizada = normalizarMergaNumero(mergaNum);

            fetch('{{ route('atadores.save') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    action: 'merga',
                    mergaKg: mergaNumNormalizada,
                    no_julio: currentNoJulio,
                    no_orden: currentNoOrden
                })
            })
                .then(r => {
                    if (!r.ok) {
                        throw new Error('HTTP error ' + r.status);
                    }
                    return r.json();
                })
                .then(res => {
                    if (res.ok) {
                        // Mostrar confirmación visual temporal
                        const input = document.getElementById('mergaKg');
                        const indicator = document.getElementById('mergaSavedIndicator');
                        if (input) {
                            input.value = normalizarMergaTexto(mergaNumNormalizada);
                            input.classList.add('border-green-500', 'bg-green-50');
                            setTimeout(() => {
                                input.classList.remove('border-green-500', 'bg-green-50');
                            }, 2000);
                        }
                        if (indicator) {
                            indicator.classList.remove('hidden');
                            setTimeout(() => {
                                indicator.classList.add('hidden');
                            }, 2000);
                        }
                    } else {
                        console.error('Error al guardar merma:', res.message);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error al guardar',
                            text: res.message || 'No se pudo guardar la merma'
                        });
                    }
                })
                .catch(err => {
                    console.error('Error de red al guardar merga:', err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'No se pudo conectar con el servidor. Verifica tu conexión.'
                    });
                });
        }

        function guardarFolioParo(valor) {
            if (esSoloLectura) {
                return;
            }

            const folioParo = (valor ?? '').toString().trim();

            fetch('{{ route('atadores.save') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    action: 'folio_paro',
                    folio_paro: folioParo,
                    no_julio: currentNoJulio,
                    no_orden: currentNoOrden
                })
            })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        const input = document.getElementById('folioParo');
                        const indicator = document.getElementById('folioParoSavedIndicator');
                        if (input) input.value = res.folio_paro ?? folioParo;
                        if (indicator) {
                            indicator.classList.remove('hidden');
                            setTimeout(() => indicator.classList.add('hidden'), 2000);
                        }
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo guardar Folio Paro' });
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo guardar Folio Paro' });
                });
        }

        // Agregar nota a Observaciones
        function agregarNota(texto) {
            const ta = document.getElementById('observaciones');
            if (!ta) return;
            const sep = ta.value && !ta.value.endsWith('\n') ? '\n' : '';
            ta.value = ta.value + sep + texto;
            ta.focus();
        }

        // Toggle estado de máquina y guardar en DB
        function toggleMaquina(maquinaId, checked) {
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
                    if (res.ok) {
                        // Confirmación visual guardada
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
        function toggleActividad(actividadId, checked) {
            if (esSoloLectura) {
                // Revertir checkbox
                const checkbox = document.querySelector(`input[onchange*="toggleActividad('${actividadId}'"]`);
                if (checkbox) checkbox.checked = !checked;
                return;
            }
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
                    if (res.ok) {
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

                                celdaOperador.textContent = '-';
                            }
                        } else {
                            console.warn('No se encontró la fila o celda operador para actividad:', actividadId);
                        }

                        // Actualizar el estado en actividadesData
                        const actividadIndex = actividadesData.findIndex(a => a.id === actividadId);
                        if (actividadIndex !== -1) {
                            actividadesData[actividadIndex].estado = !!checked;
                        }
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