@props([
    'telar',
    'ordenSig' => null,
    'salon' => '',
    'dias' => 7,
    'turnos' => 3
])


@php
    // Karl Mayer no teje rizo/pie: son cuatro barras. El tipo que se guarda es '1'..'4',
    // igual que UrdProgramaUrdido.RizoPie, y la etiqueta visible es "Barra N".
    $esKarlMayer = strtolower(str_replace('-', ' ', (string) $salon)) === 'karl mayer';
    $componentes = $esKarlMayer
        ? collect([1, 2, 3, 4])->map(fn ($n) => [
            'tipo' => (string) $n,
            'etiqueta' => 'BARRA ' . $n,
            'cuenta' => $telar->barras[$n]->Cuenta ?? '',
            'calibre' => $telar->barras[$n]->Calibre ?? '',
            'fibra' => $telar->barras[$n]->Fibra ?? '',
        ])->all()
        : [
            ['tipo' => 'rizo', 'etiqueta' => 'RIZO', 'cuenta' => $telar->Cuenta ?? '', 'calibre' => $telar->CalibreRizo2 ?? '', 'fibra' => $telar->Fibra_Rizo ?? ''],
            ['tipo' => 'pie', 'etiqueta' => 'PIE', 'cuenta' => $telar->Cuenta_Pie ?? '', 'calibre' => $telar->CalibrePie2 ?? '', 'fibra' => $telar->Fibra_Pie ?? ''],
        ];

    //esta es una funcion para verificar si el usuario tiene permiso de crear requerimientos
    // Verificar permisos del usuario actual
    $usuarioActual = Auth::user();
    $idusuario = $usuarioActual ? $usuarioActual->idusuario : null;

    // Obtener permisos del usuario para el módulo "Requerimientos" (idrol 21)
    $permisos = null;
    if ($idusuario) {
        $permisos = \App\Models\Sistema\SYSUsuariosRoles::where('idusuario', $idusuario)
            ->where('idrol', 21) // Requerimientos
            ->first();
    }

    // Verificar si tiene permiso de crear
    $puedeCrear = $permisos ? $permisos->crear == 1 : false;

    $containerClass = 'p-3 md:p-1.5 lg:p-3';
    $accountBoxClass = 'mb-2 md:mb-1.5 lg:mb-0 mr-0 md:mr-0 lg:mr-4 mt-0 md:mt-0 lg:mt-[32px] rounded-lg p-3 md:p-1.5 lg:p-3 border border-gray-200';
    $accountTitleClass = 'text-sm md:text-[10px] lg:text-sm font-semibold text-gray-700 mb-2 md:mb-0.5 lg:mb-2 md:inline md:mr-2 lg:block lg:mr-0';
    $accountListClass = 'space-y-1 md:space-y-0 md:inline-flex md:gap-3 lg:space-y-1 lg:gap-0 lg:block text-sm md:text-[10px] lg:text-sm';
    $accountRowClass = 'flex items-center md:justify-start lg:justify-between';
    $accountValueClass = 'ml-2 md:ml-1 lg:ml-2 font-bold text-blue-600';
    $accountButtonClass = 'ml-2 md:ml-1 lg:ml-2 p-1 md:p-0.5 lg:p-1 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors';
    $calendarTableClass = 'border border-gray-300 rounded overflow-hidden shadow-sm w-24 flex-shrink-0';
    $calendarHeaderClass = 'text-center border-b border-gray-300 bg-blue-500 text-white px-1 py-1 text-xs font-bold';
    $calendarCellClass = 'border border-gray-200 text-center px-1 py-1 bg-white min-w-[40px]';
    $checkboxBaseClass = 'w-3 h-3 text-blue-600 rounded border-gray-300 focus:ring-blue-500';
    $checkboxDisabledClass = 'opacity-50 cursor-not-allowed';
    $modalThBaseClass = 'px-4 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider';
    $modalThBorderClass = $modalThBaseClass . ' border-r border-gray-200';
    $modalRowClass = 'hover:bg-blue-50 transition-colors';
    $modalRadioClass = 'w-5 h-5 text-blue-600 border-gray-300 focus:ring-blue-500 focus:ring-2';
    $modalTdBaseClass = 'px-4 py-4 text-base font-semibold text-gray-900';
    $modalTdBorderClass = $modalTdBaseClass . ' border-r border-gray-200';
    $modalCancelButtonClass = 'px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200';
    $modalConfirmButtonClass = 'px-6 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 border border-transparent rounded-lg hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-lg transition-all duration-200';
@endphp



<div class="inv-telas-req {{ $containerClass }}">
    <div class="inv-telas-req-row md:flex md:flex-col lg:flex-row">
        <!-- Información de cuentas -->
        <div class="inv-telas-cuentas {{ $accountBoxClass }}">
            <div class="{{ $accountTitleClass }}">Cuentas:</div>
            <div class="inv-telas-cuentas-list {{ $accountListClass }}">
                @foreach($componentes as $componente)
                    <div class="{{ $accountRowClass }}">
                        <div class="flex items-center">
                            <span class="font-medium text-gray-600">{{ $componente['etiqueta'] }}</span>
                            <span class="{{ $accountValueClass }}" id="cuenta-{{ $componente['tipo'] }}-{{ $telar->Telar }}">
                                {{ $componente['cuenta'] }}
                            </span>
                        </div>
                        <button
                            type="button"
                            class="{{ $accountButtonClass }}"
                            title="Seleccionar cuenta {{ $componente['etiqueta'] }}"
                            onclick="abrirModalSeleccion('{{ $telar->Telar }}', '{{ $componente['tipo'] }}', '{{ $componente['cuenta'] }}', '{{ $componente['calibre'] }}', '{{ $componente['fibra'] }}')"
                        >
                            <i class="fas fa-chevron-right text-sm"></i>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="inv-telas-cal-wrap lg:flex lg:items-start">
            <div class="inv-telas-fecha mr-4 md:mr-1.5 lg:mr-4 hidden lg:block">
                <b id="fecha-{{ $telar->Telar }}"></b>
            </div>

            <!-- Calendario: por defecto hoy es la primera columna; JS puede ajustar si hay registros con fecha anterior -->
            <div class="inv-telas-calendario flex gap-1 overflow-x-auto pb-2">
        @for($dia = 0; $dia < $dias; $dia++)
            @php
                // Por defecto: día 0 = hoy, 1 = hoy+1, ... 6 = hoy+6 (JS actualizará si hay fechas anteriores)
                $fechaCarbon = \Carbon\Carbon::now()->addDays($dia);
                $fecha = $fechaCarbon->format('d/m');
                $claseTabla = 't' . ($dia + 1);
                $prefijoId = $telar->Telar . '_' . $claseTabla;
                $diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                $diaSemana = $diasSemana[$fechaCarbon->dayOfWeek];
            @endphp

            <table class="inv-telas-dia {{ $calendarTableClass }}">
                <thead>
                    <tr>
                        <th colspan="{{ $turnos }}" class="{{ $calendarHeaderClass }}">
                            <div class="text-xs leading-tight">{{ $fecha }}</div>
                            <div class="text-xs opacity-75 leading-tight">{{ $diaSemana }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @for($turno = 1; $turno <= $turnos; $turno++)
                            <td class="{{ $calendarCellClass }}">
                                <div class="font-bold text-gray-700 mb-1 text-xs">{{ $turno }}</div>
                                <div class="space-y-0.5">
                                    @foreach($componentes as $componente)
                                        <label class="block">
                                            <input
                                                type="checkbox"
                                                name="{{ $componente['tipo'] }}{{ $turno }}"
                                                class="{{ $claseTabla }}{{ $componente['tipo'] }} {{ $checkboxBaseClass }} {{ !$puedeCrear ? $checkboxDisabledClass : '' }}"
                                                value="{{ $componente['tipo'] }}{{ $turno }}"
                                                id="{{ $prefijoId }}_{{ $componente['tipo'] }}{{ $turno }}"
                                                data-telar="{{ $telar->Telar }}"
                                                data-tipo="{{ $componente['tipo'] }}"
                                                data-turno="{{ $turno }}"
                                                title="{{ $componente['etiqueta'] }}"
                                                {{ !$puedeCrear ? 'disabled' : '' }}
                                            >
                                        </label>
                                    @endforeach
                                </div>
                            </td>
                        @endfor
                    </tr>
                </tbody>
            </table>
        @endfor
            </div>
        </div>
    </div>
</div>

<!-- Modal de Selección -->
@once
<div id="modalSeleccion" class="fixed inset-0 bg-black/40 bg-opacity-60 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative mx-auto p-0 w-full max-w-2xl shadow-2xl rounded-xl bg-white transform transition-all">
        <!-- Header del Modal con gradiente -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 rounded-t-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold" id="modalTitulo">Telar JACQUARD SULZER <span id="modalTelarNumero" class="text-yellow-300"></span></h3>
                <button type="button" onclick="cerrarModalSeleccion()" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Contenido del Modal -->
        <div class="p-6">
            <!-- Tabla de Selección mejorada -->
            <div class="overflow-hidden border border-gray-200 rounded-lg shadow-sm">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <th class="{{ $modalThBorderClass }}">Seleccionar</th>
                            <th class="{{ $modalThBorderClass }}">Cuenta</th>
                            <th class="{{ $modalThBorderClass }}">Calibre</th>
                            <th class="{{ $modalThBaseClass }}">Fibra</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <!-- Fila: Siguiente Orden (primera) -->
                        <tr class="{{ $modalRowClass }}">
                            <td class="px-4 py-4 border-r border-gray-200 text-center">
                                <input type="radio" name="seleccion" value="siguiente" id="radioSiguiente" class="{{ $modalRadioClass }}">
                            </td>
                            <td class="{{ $modalTdBorderClass }}" id="cuentaSiguiente">-</td>
                            <td class="{{ $modalTdBorderClass }}" id="calibreSiguiente">-</td>
                            <td class="{{ $modalTdBaseClass }}" id="fibraSiguiente">-</td>
                        </tr>
                        <!-- Fila: Producción en Proceso (segunda) -->
                        <tr class="{{ $modalRowClass }}">
                            <td class="px-4 py-4 border-r border-gray-200 text-center">
                                <input type="radio" name="seleccion" value="proceso" id="radioProceso" class="{{ $modalRadioClass }}">
                            </td>
                            <td class="{{ $modalTdBorderClass }}" id="cuentaProceso">-</td>
                            <td class="{{ $modalTdBorderClass }}" id="calibreProceso">-</td>
                            <td class="{{ $modalTdBaseClass }}" id="fibraProceso">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Botones de Acción mejorados -->
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="cerrarModalSeleccion()" class="{{ $modalCancelButtonClass }}">
                    Cancelar
                </button>
                <button type="button" onclick="confirmarSeleccion()" class="{{ $modalConfirmButtonClass }}">
                    Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Tela Reservada -->
<div id="modalTelaReservada" class="fixed inset-0 hidden flex items-center justify-center" style="z-index: 100001 !important; background-color: rgba(0, 0, 0, 0.6) !important; position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100% !important; height: 100% !important;" onclick="if(event.target === this && typeof window.cerrarModalTelaReservada === 'function') window.cerrarModalTelaReservada()">
    <div class="relative mx-auto p-0 w-full max-w-xl shadow-2xl rounded-xl bg-white transform transition-all" style="position: relative !important; z-index: 100002 !important;" onclick="event.stopPropagation()">
        <!-- Contenido del Modal -->
        <div class="p-8">
            <!-- Mensaje principal -->
            <div class="text-center mb-8">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-yellow-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-3xl text-yellow-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2" id="modalTelaReservadaTitulo">Ya tiene tela reservada</h3>
                <p class="text-gray-600" id="modalTelaReservadaDescripcion">Este telar tiene tela reservada. ¿Qué desea hacer?</p>
            </div>

            <!-- Botones de acción -->
            <div class="flex flex-col sm:flex-row gap-3">
                <!-- Botón Eliminar -->
                <button
                    type="button"
                    id="btnEliminarReservado"
                    onclick="confirmarEliminarConReserva()"
                    class="flex-1 px-4 py-2.5 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all duration-200 shadow-md hover:shadow-lg"
                >
                    <div class="flex flex-col items-center">
                        <span class="text-base mb-1">Eliminar</span>
                        <span class="text-xs opacity-90" id="modalTelaReservadaEliminarTexto">Si elimina el registro elimina la reserva</span>
                    </div>
                </button>

                <!-- Botón Actualizar (muestra calendario para seleccionar nueva fecha) -->
                <button
                    type="button"
                    id="btnActualizarReservado"
                    onclick="mostrarCalendarioParaActualizar()"
                    class="flex-1 px-4 py-2.5 bg-yellow-600 text-white font-semibold rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition-all duration-200 shadow-md hover:shadow-lg"
                >
                    <span class="text-base">Actualizar</span>
                </button>
                <button
                    type="button"
                    id="btnCancelarReservado"
                    onclick="if(typeof window.cerrarModalTelaReservada === 'function') window.cerrarModalTelaReservada()"
                    class="flex-1 px-4 py-2.5 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200 shadow-md hover:shadow-lg"
                >
                    <span class="text-base">Cancelar</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Calendario Semanal -->
<div id="modalCalendarioSemanal" class="fixed inset-0 hidden flex items-center justify-center" style="z-index: 99998; background-color: rgba(0, 0, 0, 0.6); position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100% !important; height: 100% !important; display: none !important;">
    <div class="relative mx-auto p-0 w-full max-w-4xl px-4 shadow-2xl bg-white transform transition-all border border-gray-300" style="position: relative; z-index: 100000;" onclick="event.stopPropagation()">
        <!-- Contenido del Modal -->
        <div class="p-6 md:p-8">
            <!-- Header -->
            <div class="flex items-center justify-center mb-6 md:mb-8">
                <h3 class="text-xl md:text-2xl font-bold text-gray-900 uppercase tracking-wide">Seleccionar Fecha</h3>
            </div>

            <!-- Calendario Semanal Horizontal -->
            <div class="mb-6 md:mb-8" id="seccionCalendario">
                <div id="calendarioSemanalGrid" class="grid grid-cols-7 gap-0 border border-gray-300 w-full" style="background-color: #f9fafb; display: grid; grid-template-columns: repeat(7, 1fr);">
                    <!-- Los días se generarán dinámicamente con JavaScript -->
                </div>
            </div>


            <!-- Botones -->
            <div class="flex justify-center gap-2 mt-6 md:mt-8">
                <button
                    type="button"
                    id="btnCancelarCalendario"
                    onclick="if(typeof window.manejarCancelarModal2 === 'function') { window.manejarCancelarModal2(); } else if(typeof window.cerrarModalCalendarioSemanal === 'function') { window.cerrarModalCalendarioSemanal(); }"
                    class="px-6 py-2 md:px-8 md:py-2.5 text-sm md:text-base bg-gray-200 text-gray-800 font-semibold border border-gray-400 hover:bg-gray-300 focus:outline-none transition-all"
                >
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>
@endonce

{{-- La logica vive en resources/js/tejido/inventario-telas.ts (una sola copia
     para toda la pagina); aqui solo viaja la configuracion de este telar. --}}
<div
    data-telar-config="{{ json_encode(['telarId' => (int) $telar->Telar, 'telarData' => $telar, 'ordenSigData' => $ordenSig, 'salonTelar' => $salon]) }}"
    hidden
></div>
