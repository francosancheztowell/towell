<div class="bg-white shadow-xl overflow-hidden rounded-2xl mt-1">
    <div class="p-8">
        {{-- Sección de formulario tomada de create (fragmento) --}}
        <div class="mb-8">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <tbody>
                                <!-- Fila 1 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Salon</td>
                                    <td class="px-2 py-1">
                                            @isset($registro)
                                                @if(!empty($registro->SalonTejidoId))
                                                    <input value="{{ $registro->SalonTejidoId }}" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100">
                                                @endif
                                            @endisset
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Aplicación</td>
                                    <td class="px-2 py-1"><input type="text" id="aplicacion-select" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" value="{{ $registro->AplicacionId ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Calibre Trama</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-trama"  class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs " value="{{ $registro->CalibreTrama ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Calibre C2</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-c2"  class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CalibreComb22 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800 w-24">Calibre C4</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-c4" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CalibreComb42 ?? '' }}"></td>
                                </tr>

                                <!-- Fila 2 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Clave Modelo</td>
                                    <td class="px-2 py-1 relative">
                                        <input type="text" id="clave-modelo-input" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs" value="{{ $registro->TamanoClave ?? '' }}">
                                        <div id="clave-modelo-suggestions" class="absolute z-10 w-full bg-white border border-gray-300 rounded-b shadow-lg hidden max-h-40 overflow-y-auto"></div>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cuenta Rizo</td>
                                    <td class="px-2 py-1"><input type="text" id="cuenta-rizo" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled value="{{ $registro->CuentaRizo ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo Trama</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-trama" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->FibraTrama ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C2</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-c2" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->FibraComb2 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C4</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-c4" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->FibraComb4 ?? '' }}"></td>
                                </tr>

                                <!-- Fila 3 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Modelo</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-modelo" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled value="{{ $registro->NombreProducto ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre Rizo</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-rizo" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled value="{{ $registro->CalibreRizo ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-1" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CodColorTrama ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-2" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CodColorComb2 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-3" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CodColorComb4 ?? '' }}"></td>
                                </tr>

                                <!-- Fila 4 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Tamaño</td>
                                    <td class="px-2 py-1"><input type="text" id="tamano" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled value="{{ $registro->InventSizeId ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo Rizo</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-rizo" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled value="{{ $registro->FibraRizo ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-1" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->ColorTrama ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-2" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->NombreCC2 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-3" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->NombreCC4 ?? '' }}"></td>
                                </tr>

                                <!-- Fila 5 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-select" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" value="{{ $registro->FibraRizo ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cuenta Pie</td>
                                    <td class="px-2 py-1"><input type="text" id="cuenta-pie" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled value="{{ $registro->CuentaPie ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre C1</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-c1" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CalibreComb12 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre C3</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-c3" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CalibreComb32 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre C5</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-c5" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CalibreComb52 ?? '' }}"></td>
                                </tr>

                                <!-- Fila 6 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">IdFlog</td>
                                    <td class="px-2 py-1">
                                        <input type="text" id="idflog-select" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" value="{{ $registro->FlogsId ?? '' }}">
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calibre Pie</td>
                                    <td class="px-2 py-1"><input type="text" id="calibre-pie" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled value="{{ $registro->CalibrePie ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C1</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-c1" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->FibraComb1 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C3</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-c3" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->FibraComb3 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo C5</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-c5" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->FibraComb5 ?? '' }}"></td>
                                </tr>
                                 <!-- Fila 7 - Descripción con columnas completas -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Descripción</td>
                                    <td class="px-2 py-1">
                                        <textarea id="descripcion" rows="1" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs resize-none" >{{ $registro->NombreProyecto ?? '' }}</textarea>
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Hilo Pie</td>
                                    <td class="px-2 py-1"><input type="text" id="hilo-pie" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" disabled value="{{ $registro->FibraPie ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-4" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CodColorComb1 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-5" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CodColorComb3 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Cod Color</td>
                                    <td class="px-2 py-1"><input type="text" id="cod-color-6" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->CodColorComb5 ?? '' }}"></td>
                                </tr>

                                <!-- Fila 8 -->
                                <tr>
                                    <td class="px-2 py-1 font-medium text-gray-800">Calendario</td>
                                    <td class="px-2 py-1">
                                        <input type="text" id="calendario-select" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100" value="{{ $registro->CalendarioId ?? '' }}">
                                    </td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Rasurado</td>
                                    <td class="px-2 py-1"><input type="text" id="rasurado" disabled class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs bg-gray-100"  value="{{ $registro->Rasurado ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-1" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs " value="{{ $registro->NombreCC1 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-3" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->NombreCC3 ?? '' }}"></td>
                                    <td class="px-2 py-1 font-medium text-gray-800">Nombre Color</td>
                                    <td class="px-2 py-1"><input type="text" id="nombre-color-6" class="w-full px-2 py-1 border border-gray-300 text-gray-800 rounded focus:ring-1 focus:ring-blue-500 text-xs "  value="{{ $registro->NombreCC5 ?? '' }}"></td>
                                </tr>
                    </tbody>
                </table>
            </div>
        </div>

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
                        @isset($registro)
                        <tr data-stdtoa="{{ $registro->StdToaHra ?? 0 }}" data-eficiencia="{{ $registro->EficienciaSTD ?? 1 }}" data-pesocrudo="{{ $registro->PesoCrudo ?? 0 }}" data-velocidadstd="{{ $registro->VelocidadSTD ?? 0 }}" data-totalpedido="{{ $registro->TotalPedido ?? ($registro->SaldoPedido ?? $registro->Produccion ?? 0) }}">
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->NoTelarId ?? '' }}">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="number" id="cantidad-input" min="0" class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-white text-gray-800" value="{{ $registro->SaldoPedido ?? $registro->Produccion ?? $registro->TotalPedido ?? '' }}" oninput="if(window.calcularFechaFinalFila){calcularFechaFinalFila(this.closest('tr'));}">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" id="fecha-inicio-input" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->FechaInicio ?? '' }}">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" id="fecha-fin-input" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->FechaFinal ?? '' }}">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->EntregaProduc ? \Carbon\Carbon::parse($registro->EntregaProduc)->format('d/m/Y') : '' }}">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->EntregaCte ? \Carbon\Carbon::parse($registro->EntregaCte)->format('d/m/Y') : '' }}">
                            </td>
                            <td class="px-3 py-2 border border-gray-300">
                                <input type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->EntregaCte ?? '' }}">
                            </td>
                        </tr>
                        @endisset
                    </tbody>
                </table>
            </div>

            @isset($registro)
            <div class="mt-3">
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
                                <td class="px-3 py-2 border border-gray-300"><input id="DiasEficiencia-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->DiasEficiencia ?? '' }}" ></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="ProdKgDia-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->ProdKgDia ?? '' }}" ></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="StdDia-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->StdDia ?? '' }}" ></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="ProdKgDia2-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->ProdKgDia2 ?? '' }}" ></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="StdToaHra-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->StdToaHra ?? '' }}" ></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="DiasJornada-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->DiasJornada ?? '' }}" ></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="HorasProd-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->HorasProd ?? '' }}" ></td>
                                <td class="px-3 py-2 border border-gray-300"><input id="StdHrsEfect-input" type="text" disabled class="w-full px-2 py-1 border border-gray-300 rounded text-xs bg-gray-100 text-gray-800" value="{{ $registro->StdHrsEfect ?? '' }}" ></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @endisset
    </div>
</div>

