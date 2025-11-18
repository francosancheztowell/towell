@extends('layouts.app')

@section('page-title', 'Simulación - Editar Programa de Tejido')

@section('navbar-right')
<div class="flex items-center gap-2">
    <button onclick="ProgramaTejidoCRUD.actualizar()" class="bg-stone-600 hover:bg-stone-700 flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
        <i class="fas fa-edit"></i>
        Actualizar
    </button>
</div>
@endsection

@section('content')
<div class="w-full">
    {{-- Formulario de datos generales --}}
    <div class="bg-white shadow-xl overflow-hidden rounded-2xl mt-1">
        <div class="p-8">
            {{-- SECCIÓN: DATOS GENERALES --}}
            <div class="mb-8">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <tbody>
                            <!-- Fila 1: Salon, Aplicación, Calibres Trama-C4 -->
                            <tr>
                                <td class="px-2 py-1 font-medium text-gray-800 w-24">Salon</td>
                                <td class="px-2 py-1">
                                    <input id="salon-input" type="text" value="{{ $registro->SalonTejidoId ?? '' }}" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100">
                                </td>
                                <td class="px-2 py-1 font-medium text-gray-800 w-24">Aplicación</td>
                                <td class="px-2 py-1">
                                    <input type="text" id="aplicacion-input" disabled value="{{ $registro->AplicacionId ?? '' }}" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100">
                                </td>
                                <td class="px-2 py-1 font-medium text-gray-800 w-24">Calibre Trama</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-trama" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CalibreTrama ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800 w-24">Calibre C2</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-c2" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CalibreComb22 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800 w-24">Calibre C4</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-c4" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CalibreComb42 ?? '' }}"></td>
                            </tr>

                            <!-- Fila 2: Clave Modelo, Cuenta Rizo, Hilos Trama-C4 -->
                            <tr>
                                <td class="px-2 py-1 font-medium text-gray-800">Clave Modelo</td>
                                <td class="px-2 py-1 relative">
                                    <input type="text" id="clave-modelo-input" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->TamanoClave ?? '' }}">
                                </td>
                                <td class="px-2 py-1 font-medium text-gray-800">Cuenta Rizo</td>
                                <td class="px-2 py-1"><input type="text" id="cuenta-rizo" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100" disabled value="{{ $registro->CuentaRizo ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Hilo Trama</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-trama" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->FibraTrama ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Hilo C2</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-c2" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->FibraComb2 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Hilo C4</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-c4" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->FibraComb4 ?? '' }}"></td>
                            </tr>

                            <!-- Fila 3: Nombre Modelo, Calibre Rizo, Cod/Color 1-3 -->
                            <tr>
                                <td class="px-2 py-1 font-medium text-gray-800">Nombre Modelo</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-modelo" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100" disabled value="{{ $registro->NombreProducto ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Calibre Rizo</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-rizo" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100" disabled value="{{ $registro->CalibreRizo ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-1" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CodColorTrama ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-2" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CodColorComb2 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-3" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CodColorComb4 ?? '' }}"></td>
                            </tr>

                            <!-- Fila 4: Tamaño, Hilo Rizo, Nombre Color 1-3 -->
                            <tr>
                                <td class="px-2 py-1 font-medium text-gray-800">Tamaño</td>
                                <td class="px-2 py-1"><input type="text" id="tamano" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100" disabled value="{{ $registro->InventSizeId ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Hilo Rizo</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-rizo" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100" disabled value="{{ $registro->FibraRizo ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-1" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->ColorTrama ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-2" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->NombreCC2 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-3" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->NombreCC4 ?? '' }}"></td>
                            </tr>

                            <!-- Fila 5: Hilo, Cuenta Pie, Calibres C1-C3-C5 -->
                            <tr>
                                <td class="px-2 py-1 font-medium text-gray-800">Hilo</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-select" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100" value="{{ $registro->FibraRizo ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Cuenta Pie</td>
                                <td class="px-2 py-1"><input type="text" id="cuenta-pie" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100" disabled value="{{ $registro->CuentaPie ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Calibre C1</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-c1" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CalibreComb12 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Calibre C3</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-c3" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CalibreComb32 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Calibre C5</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-c5" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CalibreComb52 ?? '' }}"></td>
                            </tr>

                            <!-- Fila 6: IdFlog, Calibre Pie, Hilos C1-C3-C5 -->
                            <tr>
                                <td class="px-2 py-1 font-medium text-gray-800">IdFlog</td>
                                <td class="px-2 py-1 relative">
                                    <input type="text" id="idflog-input" value="{{ $registro->FlogsId ?? '' }}" placeholder="Escriba para buscar..." class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-white">
                                    <div id="idflog-suggestions" class="absolute z-10 w-full bg-white border border-gray-300 rounded-b shadow-lg hidden max-h-40 overflow-y-auto"></div>
                                </td>
                                <td class="px-2 py-1 font-medium text-gray-800">Calibre Pie</td>
                                <td class="px-2 py-1"><input type="text" id="calibre-pie" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100" disabled value="{{ $registro->CalibrePie ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Hilo C1</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-c1" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->FibraComb1 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Hilo C3</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-c3" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->FibraComb3 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Hilo C5</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-c5" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->FibraComb5 ?? '' }}"></td>
                            </tr>

                            <!-- Fila 7: Descripción, Hilo Pie, Cod Color 4-6 -->
                            <tr>
                                <td class="px-2 py-1 font-medium text-gray-800">Descripción</td>
                                <td class="px-2 py-1"><textarea id="descripcion" rows="1" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs resize-none">{{ $registro->NombreProyecto ?? '' }}</textarea></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Hilo Pie</td>
                                <td class="px-2 py-1"><input type="text" id="hilo-pie" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100" disabled value="{{ $registro->FibraPie ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-4" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CodColorComb1 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-5" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CodColorComb3 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                <td class="px-2 py-1"><input type="text" id="cod-color-6" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->CodColorComb5 ?? '' }}"></td>
                            </tr>

                            <!-- Fila 8: Calendario, Rasurado, Nombre Color 1-3-6 -->
                            <tr>
                                <td class="px-2 py-1 font-medium text-gray-800">Calendario</td>
                                <td class="px-2 py-1">
                                    <input type="text" id="calendario-input" value="{{ $registro->CalendarioId ?? '' }}" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100">
                                </td>
                                <td class="px-2 py-1 font-medium text-gray-800">Rasurado</td>
                                <td class="px-2 py-1"><input type="text" id="rasurado" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs bg-gray-100" value="{{ $registro->Rasurado ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-1" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->NombreCC1 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-3" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->NombreCC3 ?? '' }}"></td>
                                <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                <td class="px-2 py-1"><input type="text" id="nombre-color-6" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-stone-500 text-xs" value="{{ $registro->NombreCC5 ?? '' }}"></td>
                            </tr>

                            <!-- Campos ocultos -->
                            <div class="hidden">
                                <input type="number" id="ancho" step="0.01" disabled>
                                <input type="number" id="eficiencia-std" step="0.01" disabled>
                                <input type="number" id="velocidad-std" step="0.01" disabled>
                                <input type="text" id="maquina" readonly>
                            </div>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- SECCIÓN: TELARES Y MÉTRICAS --}}
    @php
        $modeloCodificado = $modeloCodificado ?? new stdClass();
    @endphp
    <div class="bg-white shadow-xl overflow-hidden rounded-2xl mt-1">
        <div class="p-8">
            <div class="overflow-x-auto">
                <table class="w-full text-xs border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">TELAR</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">CANTIDAD</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">FECHA INICIO</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">FECHA FIN</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">COMPROMISO TEJIDO</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">FECHA CLIENTE</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">FECHA ENTREGA</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyTelares">
                        <tr data-stdtoa="{{ $registro->StdToaHra ?? 0 }}" data-eficiencia="{{ $registro->EficienciaSTD ?? 1 }}" data-pesocrudo="{{ optional($modeloCodificado)->PesoCrudo ?? 0 }}" data-velocidadstd="{{ $registro->VelocidadSTD ?? 0 }}" data-totalpedido="{{ $registro->TotalPedido ?? $registro->SaldoPedido ?? $registro->Produccion ?? 0 }}" data-calendario="{{ $registro->CalendarioId ?? 'Calendario Tej1' }}" data-notiras="{{ optional($modeloCodificado)->NoTiras ?? 0 }}" data-total="{{ optional($modeloCodificado)->Total ?? 0 }}" data-luchaje="{{ optional($modeloCodificado)->Luchaje ?? 0 }}" data-repeticiones="{{ optional($modeloCodificado)->Repeticiones ?? 0 }}">
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" value="{{ $registro->NoTelarId ?? '' }}" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="number" id="cantidad-input" min="0" value="{{ $registro->SaldoPedido ?? $registro->Produccion ?? $registro->TotalPedido ?? '' }}" class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-white text-gray-800">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" id="fecha-inicio-input" value="{{ $registro->FechaInicio ?? '' }}" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" id="fecha-fin-input" value="{{ $registro->FechaFinal ?? '' }}" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" value="{{ $registro->EntregaProduc ? \Carbon\Carbon::parse($registro->EntregaProduc)->format('d/m/Y') : '' }}" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" value="{{ $registro->EntregaCte ? \Carbon\Carbon::parse($registro->EntregaCte)->format('d/m/Y') : '' }}" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" value="{{ $registro->EntregaCte ?? '' }}" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- SECCIÓN: MÉTRICAS --}}
            <div class="mt-3 hidden" id="seccion-metricas">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs border border-gray-300">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">Días Ef.</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">Prod (Kg)/Día</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">Std/Día</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">Prod (Kg)/Día 2</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">Std (Toa/Hr) 100%</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">Días Jornada</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">Horas</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700 border border-gray-300">Std/Hr Efectivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="px-3 py-2 border border-gray-300"><input id="DiasEficiencia-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->DiasEficiencia ?? '' }}"></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="ProdKgDia-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->ProdKgDia ?? '' }}"></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="StdDia-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->StdDia ?? '' }}"></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="ProdKgDia2-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->ProdKgDia2 ?? '' }}"></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="StdToaHra-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->StdToaHra ?? '' }}"></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="DiasJornada-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->DiasJornada ?? '' }}"></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="HorasProd-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->HorasProd ?? '' }}"></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="StdHrsEfect-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->StdHrsEfect ?? '' }}"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Scripts modulares --}}
<script src="{{ asset('js/programa-tejido/config.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/programa-tejido/utils.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/programa-tejido/calendario-manager.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/programa-tejido/telar-manager.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/programa-tejido/form-manager.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/programa-tejido/crud-manager.js') }}?v={{ time() }}"></script>

<script>
// Configuración específica de edición
const registroId = @json($registro->Id ?? $registro->id ?? null);
const registroData = @json($registro ?? null);
const modeloCodificado = @json($modeloCodificado ?? null);

if (modeloCodificado) {
    window.datosModeloActual = modeloCodificado;
}

/**
 * Calcular fecha final para modo edición
 */
function calcularFechaFinalFila(tr) {
    const cantidadEl = document.getElementById('cantidad-input');
    const inicioEl = document.getElementById('fecha-inicio-input');
    const finEl = document.getElementById('fecha-fin-input');

    if (!cantidadEl || !inicioEl || !finEl) return;

    const cantidadNueva = Number(cantidadEl.value || 0);
    const inicioVal = (inicioEl.value || '').trim();
    const finOriginalVal = (finEl.getAttribute('data-original') || finEl.value || '').trim();

    if (!finEl.getAttribute('data-original') && finEl.value) {
        finEl.setAttribute('data-original', finEl.value);
    }

    const dInicio = ProgramaTejidoUtils.parseDateFlexible(inicioVal);
    const dFinOriginal = ProgramaTejidoUtils.parseDateFlexible(finOriginalVal);

    if (!dInicio) return;

    if (cantidadNueva === 0) {
        finEl.value = ProgramaTejidoUtils.formatYmdHms(dInicio);
    } else {
        const row = tr || cantidadEl.closest('tr');
        const stdToa = Number((row?.getAttribute('data-stdtoa')) || 0);
        let eficiencia = Number((row?.getAttribute('data-eficiencia')) || 1);
        if (eficiencia > 1) eficiencia = eficiencia / 100;
        const calendario = row?.getAttribute('data-calendario') || 'Calendario Tej1';

        if (stdToa > 0 && eficiencia > 0) {
            const horasNecesarias = cantidadNueva / (stdToa * eficiencia);
            const nuevoFin = CalendarioManager.sumarHorasCalendario(dInicio, horasNecesarias, calendario);
            finEl.value = ProgramaTejidoUtils.formatYmdHms(nuevoFin);
        } else if (dFinOriginal) {
            const cantidadOriginal = Number(cantidadEl.getAttribute('data-original') || cantidadEl.defaultValue || cantidadEl.value || 0);
            const durMs = dFinOriginal.getTime() - dInicio.getTime();
            if ((cantidadOriginal > 0) && (durMs >= 0)) {
                const msPorPieza = durMs / cantidadOriginal;
                const nuevaDurMs = msPorPieza * cantidadNueva;
                const horasProporcionales = nuevaDurMs / 3600000;
                const nuevoFin = CalendarioManager.sumarHorasCalendario(dInicio, horasProporcionales, calendario);
                finEl.value = ProgramaTejidoUtils.formatYmdHms(nuevoFin);
            } else {
                finEl.value = ProgramaTejidoUtils.formatYmdHms(dInicio);
            }
        } else {
            finEl.value = ProgramaTejidoUtils.formatYmdHms(dInicio);
        }
    }

    try {
        const metrics = calcularMetricas(tr);
        if (metrics) {
            pintarMetricas(metrics);
        }
    } catch (err) {
        console.error('Error en calcularFechaFinalFila:', err);
    }
}

/**
 * Calcular métricas para el modo edición
 */
function calcularMetricas(tr) {
    const cantidadEl = document.getElementById('cantidad-input');
    const inicioEl = document.getElementById('fecha-inicio-input');
    const finEl = document.getElementById('fecha-fin-input');
    const row = tr || cantidadEl?.closest('tr');

    if (!cantidadEl || !inicioEl || !finEl || !row) return null;

    const totalPedido = Number(cantidadEl.value || row.getAttribute('data-totalpedido') || 0);
    const dInicio = ProgramaTejidoUtils.parseDateFlexible(inicioEl.value || '');
    const dFin = ProgramaTejidoUtils.parseDateFlexible(finEl.value || '');

    if (!(dInicio && dFin)) return null;

    const calendario = row.getAttribute('data-calendario') || 'Calendario Tej1';
    // DiasEficiencia: diferencia directa entre fechas (sin calendario laboral)
    const diasEficiencia = (dFin - dInicio) / (1000 * 60 * 60 * 24);
    // Horas para cálculos de calendario laboral
    const horas = CalendarioManager.calcularHorasReales(dInicio, dFin, calendario);

    let stdToa100 = Number(row.getAttribute('data-stdtoa') || 0);
    let eficiencia = Number(row.getAttribute('data-eficiencia') || 1);
    if (eficiencia > 1) eficiencia /= 100;

    if (!(stdToa100 > 0) && horas > 0) {
        const toallasPorHora = totalPedido / horas;
        stdToa100 = eficiencia > 0 ? toallasPorHora / eficiencia : toallasPorHora;
    }

    const pesoCrudo = Number(modeloCodificado?.PesoCrudo || row.getAttribute('data-pesocrudo') || 0);
    const velocidadStd = Number(row.getAttribute('data-velocidadstd') || 0);
    const noTiras = Number(modeloCodificado?.NoTiras || row.getAttribute('data-notiras') || 0);
    const total = Number(modeloCodificado?.Total || row.getAttribute('data-total') || 0);
    const luchaje = Number(modeloCodificado?.Luchaje || row.getAttribute('data-luchaje') || 0);
    const repeticiones = Number(modeloCodificado?.Repeticiones || row.getAttribute('data-repeticiones') || 0);

    // Calcular StdToaHra según fórmula oficial de la imagen
    let stdToaHra = 0;
    if (noTiras > 0 && total > 0 && luchaje > 0 && repeticiones > 0 && velocidadStd > 0) {
        const parte1 = total / 1;
        const parte2 = ((luchaje * 0.5) / 0.0254) / repeticiones;
        const denominador = (parte1 + parte2) / velocidadStd;
        stdToaHra = (noTiras * 60) / denominador;
    }

    // Si ya existe StdToa100 del registro, usarlo
    if (stdToa100 > 0) {
        stdToaHra = stdToa100;
    }

    const stdDia = stdToaHra * eficiencia * 24;
    // StdHrsEfect: (TotalPedido / DiasEficiencia) / 24
    const stdHrsEfect = diasEficiencia > 0 ? (totalPedido / diasEficiencia) / 24 : 0;
    // ProdKgDia: (StdDia * PesoCrudo) / 1000 según imagen
    const prodKgDia = (stdDia * pesoCrudo) / 1000;
    // ProdKgDia2: ((PesoCrudo * StdHrsEfect) * 24) / 1000
    const prodKgDia2 = ((pesoCrudo * stdHrsEfect) * 24) / 1000;
    const diasJornada = velocidadStd / 24;
    const horasProd = stdToaHra > 0 && eficiencia > 0 ? totalPedido / (stdToaHra * eficiencia) : 0;

    return {
        dias_eficiencia: diasEficiencia,
        prod_kg_dia: prodKgDia,
        std_dia: stdDia,
        prod_kg_dia2: prodKgDia2,
        std_toa_hra: stdToaHra,
        dias_jornada: diasJornada,
        horas_prod: horasProd,
        std_hrs_efect: stdHrsEfect,
    };
}

/**
 * Pintar métricas en los campos correspondientes
 */
function pintarMetricas(metrics) {
    const mapping = [
        ['DiasEficiencia-input', metrics.dias_eficiencia],
        ['ProdKgDia-input', metrics.prod_kg_dia],
        ['StdDia-input', metrics.std_dia],
        ['ProdKgDia2-input', metrics.prod_kg_dia2],
        ['StdToaHra-input', metrics.std_toa_hra],
        ['DiasJornada-input', metrics.dias_jornada],
        ['HorasProd-input', metrics.horas_prod],
        ['StdHrsEfect-input', metrics.std_hrs_efect],
    ];

    mapping.forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.value = Number.isFinite(val) ? Number(val).toFixed(2) : '';
    });
}

/**
 * Calcular las fórmulas actuales basadas en los valores de la UI
 */
function calcularFormulasActuales(tr) {
    const metrics = calcularMetricas(tr);
    return metrics || {
        dias_eficiencia: NaN,
        prod_kg_dia: NaN,
        std_dia: NaN,
        prod_kg_dia2: NaN,
        std_toa_hra: NaN,
        dias_jornada: NaN,
        horas_prod: NaN,
        std_hrs_efect: NaN,
    };
}

window.calcularFechaFinalFila = calcularFechaFinalFila;
window.calcularFormulasActuales = calcularFormulasActuales;

document.addEventListener('DOMContentLoaded', function() {
    if (registroId) {
        ProgramaTejidoForm.state.registroId = registroId;
    }

    const finEl = document.getElementById('fecha-fin-input');
    const cantidadEl = document.getElementById('cantidad-input');

    if (finEl && finEl.value && !finEl.getAttribute('data-original')) {
        finEl.setAttribute('data-original', finEl.value);
    }

    if (cantidadEl && !cantidadEl.getAttribute('data-original')) {
        const orig = Number(cantidadEl.defaultValue || cantidadEl.value || 0);
        cantidadEl.setAttribute('data-original', String(orig));
    }

    const tr = cantidadEl ? cantidadEl.closest('tr') : null;

    if (cantidadEl) {
        cantidadEl.addEventListener('input', () => calcularFechaFinalFila(tr));
        cantidadEl.addEventListener('change', () => calcularFechaFinalFila(tr));
    }

    if (finEl) {
        finEl.addEventListener('change', () => calcularFechaFinalFila(tr));
    }

    ProgramaTejidoForm.init(true, registroData);
    setTimeout(() => calcularFechaFinalFila(tr), 100);
});
</script>
@endsection
