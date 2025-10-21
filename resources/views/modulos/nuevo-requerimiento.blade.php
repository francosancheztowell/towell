@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
    <div class="container mx-auto">
        <div class="mt-2">
            <x-back-button text="Volver a Inventario Trama" />
        </div>

        <!-- Navbar de telares -->
        <div id="telar-navbar" class="fixed top-16 left-0 right-0 bg-white/95 backdrop-blur-sm border-b border-gray-200 shadow-lg z-40 transition-all duration-300">
            <div class="container mx-auto px-4 py-3">
                <div class="flex flex-wrap justify-center gap-2 max-w-8xl mx-auto">
                    @php
                        $__telares = (isset($editMode) && $editMode) ? ($telaresEdit ?? []) : ($telaresOrdenados ?? []);

                        // Ordenar los telares por secuencia (no por número de telar)
                        // Los telares deben aparecer en el orden: 201, 203, 205, 207, 209, 211, 210, 215, 208, 213, 206, 214, 204, 202, 299, 301, 303, 305, 307, 309, 311, 310, 313, 308, 315, 306, 317, 304, 319, 302, 300, 320, 318, 316, 314, 312
                        $secuenciaCorrecta = [201, 203, 205, 207, 209, 211, 210, 215, 208, 213, 206, 214, 204, 202, 299, 301, 303, 305, 307, 309, 311, 310, 313, 308, 315, 306, 317, 304, 319, 302, 300, 320, 318, 316, 314, 312];

                        $telaresOrdenados = collect($__telares)->sortBy(function($telar) use ($secuenciaCorrecta) {
                            $posicion = array_search((int)$telar, $secuenciaCorrecta);
                            return $posicion !== false ? $posicion : 999; // Si no está en la secuencia, ponerlo al final
                        })->values()->all();
                    @endphp
                    @foreach($telaresOrdenados as $index => $telar)
                        <button onclick="scrollToTelar({{ $telar }})" class="telar-nav-btn px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 border-2 border-gray-300 bg-gray-100 text-gray-700 hover:bg-blue-100 hover:border-blue-400 hover:text-blue-800 shadow-sm hover:shadow-md" data-telar="{{ $telar }}" title="Telar {{ $telar }}">
                            {{ $telar }}
                        </button>
                    @endforeach
                </div>
            </div>
                    </div>

        <!-- Lista de Requerimientos en Proceso -->
        <div class="space-y-6">
            @php $__telares = (isset($editMode) && $editMode) ? ($telaresEdit ?? []) : ($telaresOrdenados ?? []); @endphp
            @foreach($__telares as $telar)
                @php
                    if(isset($editMode) && $editMode){
                        $consTel = $consumosPorTelar[$telar] ?? null;
                        $tipo = (isset($consTel['salon']) && strtoupper($consTel['salon']) === 'ITEMA') ? 'itema' : 'jacquard';
                        $telarData = (object) [
                            'Orden_Prod' => $consTel['orden'] ?? '',
                            'Id_Flog' => '-',
                            'Cliente' => '-',
                            'InventSizeId' => '-',
                            'ItemId' => '-',
                            'Nombre_Producto' => $consTel['producto'] ?? '',
                            'Saldos' => '-',
                            'Produccion' => '-',
                            'Inicio_Tejido' => $editFolio->Fecha ?? '-',
                            'Fin_Tejido' => '-',
                        ];
                        $ordenSig = null;
                    } else {
                        $info = $datosPorTelar[$telar] ?? null;
                        $telarData = $info['telarData'] ?? null;
                        $ordenSig = $info['ordenSig'] ?? null;
                        $tipo = $info['tipo'] ?? 'jacquard';
                    }
                @endphp
            <div id="telar-{{ $telar }}" class="telar-section bg-white rounded-lg shadow-lg  border border-gray-200 overflow-hidden" data-telar="{{ $telar }}" data-salon="{{ $tipo === 'itema' ? 'ITEMA' : 'JACQUARD' }}" data-orden="{{ $telarData->Orden_Prod ?? '' }}" data-producto="{{ $telarData->Nombre_Producto ?? '' }}">
                <div class="{{ $tipo === 'itema' ? 'bg-green-700' : 'bg-blue-700' }} px-4 py-4 border-t-4 border-orange-400">
                    <h2 class="text-xl font-bold text-white text-center">
                        PRODUCCIÓN EN PROCESO TELAR {{ strtoupper($tipo) }}
                        <span class="inline-block bg-red-600 text-white px-3 py-1 rounded-lg ml-2 font-bold text-xl">{{ $telar }}</span>
                    </h2>
                    </div>

                <div class="p-6">
                    <div class="grid grid-cols-3 lg:grid-cols-3 gap-6 mb-6">
                        <!-- Columna izquierda -->
                        <div class="space-y-3">
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Folio:</span>
                                <span class="text-sm font-semibold text-gray-900 folio-actual">{{ (isset($editMode) && $editMode && $editFolio) ? $editFolio->Folio : '-' }}</span>
                    </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Fecha:</span>
                                <span class="text-sm font-semibold text-gray-900">{{ (isset($editMode) && $editMode && $editFolio) ? $editFolio->Fecha : (getdate()['year'] . '-' . getdate()['mon'] . '-' . getdate()['mday']) }}</span>
                    </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Turno:</span>
                                <span class="text-sm font-semibold text-gray-900 turno-actual">{{ (isset($editMode) && $editMode && $editFolio) ? ('Turno ' . $editFolio->Turno) : '-' }}</span>
                    </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Orden (NoProduccion):</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $telarData->Orden_Prod ?? '-' }}</span>
                    </div>
                </div>

                        <!-- Columna derecha -->
                        <div class="space-y-3">
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">No Flog (FlogsId):</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $telarData->Id_Flog ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Cliente (CustName):</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $telarData->Cliente ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Tamaño (InventSizeId):</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $telarData->InventSizeId ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Artículo (ItemId + NombreProducto):</span>
                                <span class="text-sm font-semibold text-gray-900">{{ ($telarData->ItemId ?? '-') . ' ' . ($telarData->Nombre_Producto ?? '-') }}</span>
                            </div>
                        </div>

                        <!-- Columna adicional -->
                        <div class="space-y-3">
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Pedido (TotalPedido):</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $telarData->Saldos ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Producción (Produccion):</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $telarData->Produccion ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Inicio (FechaInicio):</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $telarData->Inicio_Tejido ?? '-' }}</span>
                            </div>
                            <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                                <span class="text-sm font-semibold text-gray-600">Fin (FechaFinal):</span>
                                <span class="text-sm font-semibold text-gray-900">{{ $telarData->Fin_Tejido ?? '-' }}</span>
                        </div>
                        </div>
                        </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-end mb-4 relative z-10">
                        <button onclick="agregarNuevoRequerimiento()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 shadow-md">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Nuevo Requerimiento
                        </button>
                    </div>

                    <!-- Tabla de detalles -->
                    <div class=" rounded-lg overflow-hidden relative z-10">
                        <table class="w-full mt-2.5 ">
                            <thead class="relative z-10">
                                <tr class="bg-gray-100">
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Artículo</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Fibra</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Cod Color</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Nombre Color</th>
                                    <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Cantidad (Conos)</th>
                                </tr>
                            </thead>
                            <tbody class="">
                                @if(isset($editMode) && $editMode)
                                    @php $items = ($consumosPorTelar[$telar]['items'] ?? []); @endphp
                                    @foreach($items as $index => $it)
                                        <tr class="{{ $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50" data-consumo-id="{{ $it['id'] }}">
                                            <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $it['calibre'] !== null ? number_format($it['calibre'], 2) : '-' }}</td>
                                            <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $it['fibra'] ?: '-' }}</td>
                                            <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $it['cod_color'] ?: '-' }}</td>
                                            <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $it['color'] ?: '-' }}</td>
                                            <td class="px-4 py-1">
                                                <div class="flex items-center justify-center relative">
                                                    <button class="edit-quantity-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors flex items-center gap-2" onclick="toggleQuantityEdit(this)">
                                                        <span class="quantity-display text-md font-semibold">{{ (int)($it['cantidad'] ?? 0) }}</span>

                                                    </button>
                                                    <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                                        <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                            <div class="flex space-x-1 min-w-max">
                                                                @for($i = 0; $i <= 100; $i++)
                                                                    <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ (int)($it['cantidad'] ?? 0) == $i ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                                @endfor
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr class="bg-white hover:bg-blue-50">
                                        <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ isset($telarData->CALIBRE_TRA) && $telarData->CALIBRE_TRA !== null ? number_format($telarData->CALIBRE_TRA, 2) : '-' }}</td>
                                        <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->FIBRA_TRA ?? '-' }}</td>
                                        <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->CODIGO_COLOR_TRAMA ?? '-' }}</td>
                                        <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->COLOR_TRAMA ?? '-' }}</td>
                                        <td class="px-4 py-1">
                                            <div class="flex items-center justify-center relative">
                                                <button class="edit-quantity-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors flex items-center gap-2" onclick="toggleQuantityEdit(this)">
                                                    <span class="quantity-display text-md font-semibold">0</span>
                                                </button>
                                                <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                                    <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                        <div class="flex space-x-1 min-w-max">
                                                            @for($i = 0; $i <= 100; $i++)
                                                                <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 0 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                            @endfor
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                                @if(isset($telarData->CALIBRE_C2) && $telarData->CALIBRE_C2 !== null && $telarData->CALIBRE_C2 != 0)
                                <tr class="bg-gray-50 hover:bg-blue-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ number_format($telarData->CALIBRE_C2, 2) }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->FIBRA_C2 ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->CODIGO_COLOR_C2 ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->COLOR_C2 ?? '-' }}</td>
                                    <td class="px-4 py-1">
                                        <div class="flex items-center justify-center">
                                            <button class="edit-quantity-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors flex items-center gap-2" onclick="toggleQuantityEdit(this)">
                                                <span class="quantity-display text-md font-semibold">0</span>
                                            </button>
                                            <div class="quantity-edit-container hidden relative w-20">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($i = 0; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 0 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                        @endfor
                        </div>
                    </div>
                </div>
                                </div>
                                    </td>
                                </tr>
                                @endif
                                @if(isset($telarData->CALIBRE_C3) && $telarData->CALIBRE_C3 !== null && $telarData->CALIBRE_C3 != 0)
                                <tr class="bg-white hover:bg-blue-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ number_format($telarData->CALIBRE_C3, 2) }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->FIBRA_C3 ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->CODIGO_COLOR_C3 ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->COLOR_C3 ?? '-' }}</td>
                                    <td class="px-4 py-1">
                                        <div class="flex items-center justify-center">
                                            <button class="edit-quantity-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors flex items-center gap-2" onclick="toggleQuantityEdit(this)">
                                                <span class="quantity-display text-md font-semibold">0</span>
                                            </button>
                                            <div class="quantity-edit-container hidden relative w-20">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($i = 0; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 0 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                        @endfor
                                </div>
                                </div>
                                </div>
                            </div>
                                    </td>
                                </tr>
                                @endif
                                @if(isset($telarData->CALIBRE_C4) && $telarData->CALIBRE_C4 !== null && $telarData->CALIBRE_C4 != 0)
                                <tr class="bg-gray-200 hover:bg-blue-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ number_format($telarData->CALIBRE_C4, 2) }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->FIBRA_C4 ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->CODIGO_COLOR_C4 ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->COLOR_C4 ?? '-' }}</td>
                                    <td class="px-4 py-1">
                                        <div class="flex items-center justify-center relative">
                                            <button class="edit-quantity-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors flex items-center gap-2" onclick="toggleQuantityEdit(this)">
                                                <span class="quantity-display text-md font-semibold">0</span>
                                            </button>
                                            <div class="quantity-edit-container hidden absolute top-full left-1/2 transform -translate-x-1/2 mt-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($i = 0; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 0 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                        @endfor
                    </div>
                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @endif
                                @if(isset($telarData->CALIBRE_C5) && $telarData->CALIBRE_C5 !== null && $telarData->CALIBRE_C5 != 0)
                                <tr class="bg-white hover:bg-blue-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ number_format($telarData->CALIBRE_C5, 2) }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->FIBRA_C5 ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->CODIGO_COLOR_C5 ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $telarData->COLOR_C5 ?? '-' }}</td>
                                    <td class="px-4 py-1">
                                        <div class="flex items-center justify-center relative">
                                            <button class="edit-quantity-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors flex items-center gap-2" onclick="toggleQuantityEdit(this)">
                                                <span class="quantity-display text-md font-semibold">0</span>
                                            </button>
                                            <div class="quantity-edit-container hidden absolute top-full left-1/2 transform -translate-x-1/2 mt-2 z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                                                <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                                    <div class="flex space-x-1 min-w-max">
                                                        @for($i = 0; $i <= 100; $i++)
                                                            <span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors {{ $i == 0 ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700' }}" data-value="{{ $i }}">{{ $i }}</span>
                                                        @endfor
                                                    </div>
                        </div>
                        </div>
                        </div>
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endforeach

                </div>

        <!-- Mensaje cuando no hay requerimientos -->
        <div id="no-requerimientos" class="hidden">
            <div class="text-center py-12">
                <div class="bg-gray-100 rounded-2xl p-8 max-w-md mx-auto">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay requerimientos disponibles</h3>
                    <p class="text-gray-500">No se encontraron requerimientos que coincidan con los filtros seleccionados</p>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
        <!-- Los toasts se agregarán aquí dinámicamente -->
    </div>

    <!-- Modal para Agregar Nuevo Requerimiento -->
    <div id="modal-nuevo-requerimiento" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <!-- Header del modal -->
            <div class="bg-blue-500 px-6 py-4 rounded-t-lg">
                <div class="flex justify-center items-center">
                    <h3 class="text-lg font-semibold text-white">Agregar Nuevo Requerimiento</h3>
                    <button onclick="cerrarModal()" class="text-white hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Contenido del modal -->
            <div class="p-6">
                <form id="form-nuevo-requerimiento">
                    <div class="space-y-4">
                        <!-- Artículo -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Artículo</label>
                            <input type="number" step="0.01" id="modal-articulo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: 10.5" required>
                        </div>

                        <!-- Fibra -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Fibra</label>
                            <input type="text" id="modal-fibra" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: ALGODÓN" required>
                        </div>

                        <!-- Cod Color -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cod Color</label>
                            <input type="text" id="modal-cod-color" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: A8" required>
                        </div>

                        <!-- Nombre Color -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre Color</label>
                            <input type="text" id="modal-nombre-color" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Ej: BLANCO" required>
                        </div>

                        <!-- Cantidad (Conos) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad (Conos)</label>
                            <input type="number" min="0" max="100" id="modal-cantidad" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="0" value="0" required>
                        </div>
                    </div>

                    <!-- Botones del modal -->
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="cerrarModal()" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            Agregar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Funcionalidad del navbar de telares
        function scrollToTelar(telarNumber) {
            const element = document.getElementById(`telar-${telarNumber}`);
            if (!element) return;

            // Calcular posición exacta
            const elementRect = element.getBoundingClientRect();
            const absoluteElementTop = elementRect.top + window.pageYOffset;
            const navbarHeight = 56;
            const offsetTop = absoluteElementTop - navbarHeight - 60; // 60px de margen

            window.scrollTo({
                top: Math.max(0, offsetTop),
                behavior: 'smooth'
            });

            // Actualizar botón activo después del scroll
            setTimeout(() => {
                updateActiveButton(telarNumber);
            }, 500);
        }

        function updateActiveButton(activeTelar) {
            document.querySelectorAll('.telar-nav-btn').forEach(btn => {
                btn.classList.remove('bg-blue-600', 'border-blue-600', 'text-white');
                btn.classList.add('bg-gray-100', 'border-gray-300', 'text-gray-700');
            });

            // Agregar clase activa al botón del telar actual
            const activeBtn = document.querySelector(`[data-telar="${activeTelar}"]`);
            if (activeBtn) {
                activeBtn.classList.remove('bg-gray-100', 'border-gray-300', 'text-gray-700');
                activeBtn.classList.add('bg-blue-600', 'border-blue-600', 'text-white');
            }
        }

        // Funcionalidad del scroll horizontal de números
        document.addEventListener('DOMContentLoaded', function() {
            // Bloqueo de acceso si hay orden En Proceso y NO venimos con folio en query (modo edición)
            try {
                const paramsInit = new URLSearchParams(window.location.search);
                const folioInit = paramsInit.get('folio');
                if (!folioInit) {
                    fetch('/modulo-nuevo-requerimiento/en-proceso')
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.exists) {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Orden en Proceso',
                                    text: 'Aún sigue en proceso esta orden. Será redirigido a Consultar Requerimiento.',
                                    timer: 9000,
                                    timerProgressBar: true,
                                    showConfirmButton: false,
                                    allowOutsideClick: false,
                                    allowEscapeKey: false
                                }).then(() => {
                                    window.location.href = `{{ route('tejido.inventario.trama.consultar.requerimiento') }}`;
                                });
                            }
                        })
                        .catch(() => {});
                }
            } catch (e) {}
            // Mostrar folio pasado por query si aplica
            mostrarFolioDeQuery();

            // Cargar información del turno y folio
            cargarInformacionTurno();
            cargarFolioYAutoGuardar();
            // Ocultar/mostrar navbar según scroll (oculto cuando está arriba)
            const navbar = document.getElementById('telar-navbar');
            function handleNavVisibility() {
                const y = window.scrollY || document.documentElement.scrollTop;
                if (y < 80) {
                    navbar.classList.add('opacity-0', '-translate-y-full', 'pointer-events-none');
                } else {
                    navbar.classList.remove('opacity-0', '-translate-y-full', 'pointer-events-none');
                }
            }
            handleNavVisibility();
            window.addEventListener('scroll', handleNavVisibility, { passive: true });

            // ScrollSpy: resaltar botón activo al pasar de telar en telar
            const telarSections = Array.from(document.querySelectorAll('[id^="telar-" ]'));
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const id = entry.target.id; // telar-XXX
                            const num = parseInt(id.split('-')[1], 10);
                            updateActiveButton(num);
                        }
                    });
                }, { threshold: 0.6 });

                telarSections.forEach(sec => observer.observe(sec));
            } else {
                // Fallback simple por scroll si no hay IntersectionObserver
                window.addEventListener('scroll', () => {
                    let current = null;
                    telarSections.forEach(sec => {
                        const rect = sec.getBoundingClientRect();
                        if (rect.top <= window.innerHeight * 0.4 && rect.bottom >= window.innerHeight * 0.4) {
                            current = sec;
                        }
                    });
                    if (current) {
                        const num = parseInt(current.id.split('-')[1], 10);
                        updateActiveButton(num);
                    }
                }, { passive: true });
            }
            // Cerrar editores al hacer clic fuera de ellos
            document.addEventListener('click', function(event) {
                const isInsideEditor = event.target.closest('.quantity-edit-container');
                const isEditButton = event.target.closest('.edit-quantity-btn');

                if (!isInsideEditor && !isEditButton) {
                    closeAllQuantityEditors();
                }
            });

            // Event listener para el formulario del modal
            document.getElementById('form-nuevo-requerimiento').addEventListener('submit', function(e) {
                e.preventDefault();

                const datos = {
                    articulo: document.getElementById('modal-articulo').value,
                    fibra: document.getElementById('modal-fibra').value,
                    codColor: document.getElementById('modal-cod-color').value,
                    nombreColor: document.getElementById('modal-nombre-color').value,
                    cantidad: parseInt(document.getElementById('modal-cantidad').value)
                };

                // Validar que todos los campos estén llenos
                if (!datos.articulo || !datos.fibra || !datos.codColor || !datos.nombreColor) {
                    showToast('Por favor, complete todos los campos', 'warning');
                    return;
                }

                // Agregar la nueva fila a la tabla
                agregarFilaATabla(datos);

                // Cerrar el modal
                cerrarModal();

                // Mostrar mensaje de éxito
                showToast('Nuevo requerimiento agregado exitosamente', 'success');

                // Guardar automáticamente
                scheduleGuardarRequerimientos();
            });

            // Cerrar modal al hacer clic fuera de él
            document.getElementById('modal-nuevo-requerimiento').addEventListener('click', function(e) {
                if (e.target === this) {
                    cerrarModal();
                }
            });
            document.querySelectorAll('.number-option').forEach(option => {
                option.addEventListener('click', function() {
                    const container = this.closest('.number-scroll-container');
                    const allOptions = container.querySelectorAll('.number-option');
                    const row = this.closest('tr');
                    const quantityDisplay = row.querySelector('.quantity-display');
                    const selectedValue = this.getAttribute('data-value');

                    // Remover selección anterior
                    allOptions.forEach(opt => {
                        opt.classList.remove('bg-blue-500', 'text-white');
                        opt.classList.add('bg-gray-100', 'text-gray-700');
                    });

                    // Seleccionar opción actual
                    this.classList.remove('bg-gray-100', 'text-gray-700');
                    this.classList.add('bg-blue-500', 'text-white');

                    // Actualizar el texto mostrado
                    quantityDisplay.textContent = selectedValue;

                    // Verificar si es un consumo existente (tiene ID)
                    const consumoId = row.getAttribute('data-consumo-id');
                    if (consumoId) {
                        // Actualizar directamente en la BD
                        actualizarCantidadEnBD(consumoId, selectedValue);
                    } else {
                        // Mostrar toast para nuevos requerimientos
                        showToast(`Cantidad actualizada a ${selectedValue} conos`);
                    }

                    // Centrar el número seleccionado
                    const containerWidth = container.offsetWidth;
                    const optionLeft = this.offsetLeft;
                    const optionWidth = this.offsetWidth;
                    const scrollLeft = optionLeft - (containerWidth / 2) + (optionWidth / 2);

                    container.scrollTo({
                        left: scrollLeft,
                        behavior: 'smooth'
                    });

                    // Ocultar el editor después de seleccionar
                    setTimeout(() => {
                        const editContainer = row.querySelector('.quantity-edit-container');
                        const editBtn = row.querySelector('.edit-quantity-btn');
                        const display = row.querySelector('.quantity-display');

                        editContainer.classList.add('hidden');
                        editBtn.classList.remove('hidden');
                        display.classList.remove('hidden');
                    }, 500);

                    // Guardado automático (incluye segunda tabla)
                    scheduleGuardarRequerimientos();
                });
            });
        });

        // Función para mostrar/ocultar el editor de cantidad
        function toggleQuantityEdit(element) {
            const row = element.closest('tr');
            const editContainer = row.querySelector('.quantity-edit-container');
            const quantityDisplay = row.querySelector('.quantity-display');
            const editBtn = row.querySelector('.edit-quantity-btn');

            if (editContainer.classList.contains('hidden')) {
                // Cerrar todos los editores abiertos primero
                closeAllQuantityEditors();

                // Mostrar editor actual
                editContainer.classList.remove('hidden');
                // Ocultar el botón si existe
                if (editBtn) {
                    editBtn.classList.add('hidden');
                }
            } else {
                // Ocultar editor
                editContainer.classList.add('hidden');
                // Mostrar el botón si existe
                if (editBtn) {
                    editBtn.classList.remove('hidden');
                }
            }
        }

        // Función para cerrar todos los editores de cantidad
        function closeAllQuantityEditors() {
            document.querySelectorAll('.quantity-edit-container').forEach(container => {
                if (!container.classList.contains('hidden')) {
                    const row = container.closest('tr');
                    const editBtn = row.querySelector('.edit-quantity-btn');
                    const display = row.querySelector('.quantity-display');

                    container.classList.add('hidden');
                    // Solo mostrar el botón si existe
                    if (editBtn) {
                    editBtn.classList.remove('hidden');
                    }
                    // El display siempre debe permanecer visible
                    if (display) {
                    display.classList.remove('hidden');
                    }
                }
            });
        }

        // Función para cargar información del turno
        function cargarInformacionTurno() {
            const hasQueryFolio = new URLSearchParams(window.location.search).has('folio');
            if (hasQueryFolio) {
                // En modo edición, mantener turno y folio de TejTrama
                return;
            }
            fetch('/modulo-nuevo-requerimiento/turno-info')
                .then(response => response.json())
                .then(data => {
                    const desc = descripcionTurno(data.turno);
                    document.querySelectorAll('.turno-actual').forEach(el => {
                        el.textContent = desc;
                    });
                    if (data.folio) {
                        document.querySelectorAll('.folio-actual').forEach(el => {
                            el.textContent = data.folio;
                        });
                    }
                })
                .catch(error => {
                    console.error('Error al cargar información del turno:', error);
                });
        }

        // Mostrar folio proveniente de la query en el encabezado
        function mostrarFolioDeQuery() {
            const params = new URLSearchParams(window.location.search);
            const folio = params.get('folio');
            if (!folio) return;
            document.querySelectorAll('.folio-actual').forEach(el => {
                el.textContent = folio;
            });
            showToast(`Editando folio ${folio}`, 'info');
        }

        // Cargar folio desde el servidor y guardar automáticamente si no hay En Proceso
        function cargarFolioYAutoGuardar() {
            fetch('/modulo-nuevo-requerimiento/turno-info')
                .then(r => r.json())
                .then(data => {
                    const hasQueryFolio = new URLSearchParams(window.location.search).has('folio');
                    if (!hasQueryFolio && data.folio) {
                        document.querySelectorAll('.folio-actual').forEach(el => {
                            el.textContent = data.folio;
                        });
                    }

                    // Intentar guardar automáticamente al cargar solo si no hay folio en query (modo edición)
                    if (!hasQueryFolio) {
                        autoGuardarRequerimientos();
                    }
                })
                .catch(() => {});
        }

        function autoGuardarRequerimientos() {
            // En modo edición (folio en query), no autoguardar para evitar sobrescribir
            const params = new URLSearchParams(window.location.search);
            const folioQuery = params.get('folio') || '';
            if (folioQuery) {
                // Modo edición: no autoguardar inicial
                return;
            }

            // Construir payload con filas que tengan cantidad > 0
            const consumos = [];
            document.querySelectorAll('.telar-section').forEach(section => {
                const telarId = section.getAttribute('data-telar');
                const salon = section.getAttribute('data-salon');
                const orden = section.getAttribute('data-orden') || '';
                const producto = section.getAttribute('data-producto') || '';
                // Buscar filas de detalle dentro de la sección
                section.querySelectorAll('tbody tr').forEach(row => {
                    const q = row.querySelector('.quantity-display');
                    if (!q) return;
                    const cantidad = parseInt(q.textContent);
                    // Forzar guardado incluso con cantidad = 0
                    const celdas = row.querySelectorAll('td');
                    if (celdas.length < 4) return;
                    // Artículo (calibre) puede venir como '-' => enviar null
                    const articuloTexto = celdas[0].textContent.trim();
                    const calibre = parseFloat(articuloTexto.replace(',', '.'));
                    const calibreValor = isNaN(calibre) ? null : calibre;
                    // Normalizar strings vacíos o '-' a null para evitar duplicados por claves
                    const fibraTxt = celdas[1].textContent.trim();
                    const codTxt = celdas[2].textContent.trim();
                    const colorTxt = celdas[3].textContent.trim();
                    const fibraNorm = (!fibraTxt || fibraTxt === '-') ? null : fibraTxt;
                    const codNorm = (!codTxt || codTxt === '-') ? null : codTxt;
                    const colorNorm = (!colorTxt || colorTxt === '-') ? null : colorTxt;
                    consumos.push({
                        telar: telarId,
                        salon: salon,
                        orden: orden,
                        calibre: calibreValor,
                        producto: producto,
                        fibra: fibraNorm,
                        cod_color: codNorm,
                        color: colorNorm,
                        cantidad: cantidad,
                        fecha_inicio: new Date().toISOString().split('T')[0], // Fecha actual
                        fecha_final: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0] // 7 días después
                    });
                });
            });

            fetch('/modulo-nuevo-requerimiento/guardar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ consumos: consumos, numero_empleado: '', nombre_empleado: '', folio: folioQuery })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Mostrar folio confirmado desde servidor
                    if (data.folio) {
                        document.querySelectorAll('.folio-actual').forEach(el => {
                            el.textContent = data.folio;
                        });
                    }
                    const isEditMode = !!folioQuery;
                    if (isEditMode) {
                        showToast(`Cambios editados. Folio: ${data.folio}`, 'success');
                        // Redirigir a consultar tras breve espera
                        setTimeout(() => {
                            window.location.href = `{{ route('tejido.inventario.trama.consultar.requerimiento') }}?folio=${encodeURIComponent(data.folio)}`;
                        }, 600);
                    } else {
                        showToast(`Folio creado: ${data.folio}`, 'success');
                    }
                } else {
                    showToast(data.message || 'No se pudo crear folio', data.message ? 'warning' : 'error');
                }
            })
            .catch(() => {
                showToast('No se pudo crear folio', 'error');
            });
        }

        // Mapa local para evitar problemas de texto en descripción
        function descripcionTurno(turno) {
            switch (String(turno)) {
                case '1':
                    return 'Turno 1';
                case '2':
                    return 'Turno 2';
                case '3':
                    return 'Turno 3';
                default:
                    return '';
            }
        }

        // Función para agregar nuevo requerimiento
        function agregarNuevoRequerimiento() {
            console.log('Función agregarNuevoRequerimiento llamada');
            // Verificar si estamos en modo edición (con folio en query)
            const params = new URLSearchParams(window.location.search);
            const folioQuery = params.get('folio');

            if (folioQuery) {
                console.log('Modo edición detectado');
                // Modo edición: permitir agregar sin verificar "En Proceso"
                const modal = document.getElementById('modal-nuevo-requerimiento');
                console.log('Modal encontrado:', modal);
                modal.classList.remove('hidden');
                document.getElementById('form-nuevo-requerimiento').reset();
                document.getElementById('modal-cantidad').value = 0;
                return;
            }

            // Modo creación: verificar si hay En Proceso
            console.log('Modo creación detectado');
            fetch('/modulo-nuevo-requerimiento/en-proceso')
                .then(r => r.json())
                .then(data => {
                    console.log('Respuesta del servidor:', data);
                    if (data && data.exists) {
                        console.log('Orden en proceso encontrada');
                        Swal.fire({
                            icon: 'info',
                            title: 'Orden en Proceso',
                            text: 'Aún sigue en proceso esta orden. Será redirigido a Consultar Requerimiento.',
                            timer: 8000,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then(() => {
                            window.location.href = `{{ route('tejido.inventario.trama.consultar.requerimiento') }}`;
                        });
                        return;
                    }

                    console.log('Abriendo modal');
                    const modal = document.getElementById('modal-nuevo-requerimiento');
                    console.log('Modal encontrado:', modal);
                    modal.classList.remove('hidden');
                    document.getElementById('form-nuevo-requerimiento').reset();
                    document.getElementById('modal-cantidad').value = 0;
                })
                .catch((error) => {
                    console.log('Error en fetch:', error);
                    const modal = document.getElementById('modal-nuevo-requerimiento');
                    modal.classList.remove('hidden');
                    document.getElementById('form-nuevo-requerimiento').reset();
                    document.getElementById('modal-cantidad').value = 0;
                });
        }

        // Función para cerrar el modal
        function cerrarModal() {
            const modal = document.getElementById('modal-nuevo-requerimiento');
            modal.classList.add('hidden');
        }

        // Función para agregar la nueva fila a la tabla
        function agregarFilaATabla(datos) {
            // Buscar la tabla del telar actualmente visible
            const telarActivo = document.querySelector('.telar-section:not(.hidden)') || document.querySelector('.telar-section');
            if (!telarActivo) return;

            const tbody = telarActivo.querySelector('tbody');
            if (!tbody) return;

            // Crear nueva fila
            const nuevaFila = document.createElement('tr');
            // Calcular el índice para el patrón alternado
            const existingRows = tbody.querySelectorAll('tr');
            const newIndex = existingRows.length;
            nuevaFila.className = `${newIndex % 2 === 0 ? 'bg-white' : 'bg-gray-200'} hover:bg-blue-50`;
            nuevaFila.innerHTML = `
                <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">${datos.articulo}</td>
                <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">${datos.fibra}</td>
                <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">${datos.codColor}</td>
                <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">${datos.nombreColor}</td>
                <td class="px-4 py-1">
                    <div class="flex items-center justify-center relative">
                        <button class="edit-quantity-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-colors flex items-center gap-2" onclick="toggleQuantityEdit(this)">
                            <span class="quantity-display text-md font-semibold">${datos.cantidad}</span>
                        </button>
                        <div class="quantity-edit-container hidden absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-full z-[9999] bg-white border border-gray-300 rounded-lg shadow-lg p-3">
                            <div class="number-scroll-container overflow-x-auto scrollbar-hide w-48" style="scrollbar-width: none; -ms-overflow-style: none;">
                                <div class="flex space-x-1 min-w-max">
                                    ${Array.from({length: 101}, (_, i) =>
                                        `<span class="number-option inline-block w-8 h-8 text-center leading-8 text-sm cursor-pointer hover:bg-blue-100 rounded transition-colors ${i == datos.cantidad ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'}" data-value="${i}">${i}</span>`
                                    ).join('')}
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            `;

            // Agregar la nueva fila al tbody
            tbody.appendChild(nuevaFila);

            // Agregar event listeners a los nuevos elementos
            agregarEventListenersANuevaFila(nuevaFila);
        }

        // Función para agregar event listeners a una nueva fila
        function agregarEventListenersANuevaFila(fila) {
            const numberOptions = fila.querySelectorAll('.number-option');
            numberOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const container = this.closest('.number-scroll-container');
                    const allOptions = container.querySelectorAll('.number-option');
                    const row = this.closest('tr');
                    const quantityDisplay = row.querySelector('.quantity-display');
                    const selectedValue = this.getAttribute('data-value');

                    // Remover selección anterior
                    allOptions.forEach(opt => {
                        opt.classList.remove('bg-blue-500', 'text-white');
                        opt.classList.add('bg-gray-100', 'text-gray-700');
                    });

                    // Seleccionar opción actual
                    this.classList.remove('bg-gray-100', 'text-gray-700');
                    this.classList.add('bg-blue-500', 'text-white');

                    // Actualizar el texto mostrado
                    quantityDisplay.textContent = selectedValue;

                    // Verificar si es un consumo existente (tiene ID)
                    const consumoId = row.getAttribute('data-consumo-id');
                    if (consumoId) {
                        // Actualizar directamente en la BD
                        actualizarCantidadEnBD(consumoId, selectedValue);
                    } else {
                        // Mostrar toast para nuevos requerimientos
                        showToast(`Cantidad actualizada a ${selectedValue} conos`);
                    }

                    // Centrar el número seleccionado
                    const containerWidth = container.offsetWidth;
                    const optionLeft = this.offsetLeft;
                    const optionWidth = this.offsetWidth;
                    const scrollLeft = optionLeft - (containerWidth / 2) + (optionWidth / 2);

                    container.scrollTo({
                        left: scrollLeft,
                        behavior: 'smooth'
                    });

                    // Ocultar el editor después de seleccionar
                    setTimeout(() => {
                        const editContainer = row.querySelector('.quantity-edit-container');
                        const editBtn = row.querySelector('.edit-quantity-btn');
                        const display = row.querySelector('.quantity-display');

                        editContainer.classList.add('hidden');
                        editBtn.classList.remove('hidden');
                        display.classList.remove('hidden');
                    }, 500);

                    // Guardado automático
                    scheduleGuardarRequerimientos();
                });
            });
        }

        // Función para guardar requerimientos
        function guardarRequerimientos() {
            const consumos = [];

            // Recopilar datos de todas las filas con cantidad > 0
            document.querySelectorAll('tr').forEach(row => {
                const quantityDisplay = row.querySelector('.quantity-display');
                if (quantityDisplay) {
                    const cantidad = parseInt(quantityDisplay.textContent);
                    if (cantidad > 0) {
                        // Obtener datos de la fila
                        const celdas = row.querySelectorAll('td');
                        if (celdas.length >= 4) {
                            const telarSection = row.closest('[id^="telar-"]');
                            const telarId = telarSection ? telarSection.id.replace('telar-', '') : '';
                            const salon = telarSection ? (telarSection.getAttribute('data-salon') || (telarId >= 300 ? 'ITEMA' : 'JACQUARD')) : (telarId >= 300 ? 'ITEMA' : 'JACQUARD');
                            const orden = telarSection ? (telarSection.getAttribute('data-orden') || '') : '';
                            const producto = telarSection ? (telarSection.getAttribute('data-producto') || '') : '';
                            // Normalizar calibre como número (punto decimal)
                            const articuloTexto = celdas[0].textContent.trim();
                            const calibreParsed = parseFloat(articuloTexto.replace(',', '.'));
                            const calibreValor = isNaN(calibreParsed) ? null : calibreParsed;
                            consumos.push({
                                telar: telarId,
                                salon: salon,
                                orden: orden,
                                calibre: calibreValor,
                                producto: producto,
                                fibra: celdas[1].textContent.trim(),
                                cod_color: celdas[2].textContent.trim(),
                                color: celdas[3].textContent.trim(),
                                cantidad: cantidad,
                                fecha_inicio: new Date().toISOString().split('T')[0], // Fecha actual
                                fecha_final: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0] // 7 días después
                            });
                        }
                    }
                }
            });

            if (consumos.length === 0) {
                showToast('No hay cantidades para editar', 'warning');
                return;
            }

            const params = new URLSearchParams(window.location.search);
            const folioQuery = params.get('folio') || '';
            const isEditMode = !!folioQuery;

            if (isEditMode) {
                // Guardar directamente sin confirmación en modo edición
                fetch('/modulo-nuevo-requerimiento/guardar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        consumos: consumos,
                        numero_empleado: '',
                        nombre_empleado: '',
                        folio: folioQuery
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(`Cambios editados. Folio: ${data.folio}`, 'success');
                    } else {
                        showToast(data.message || 'No se pudo editar', 'warning');
                    }
                })
                .catch(() => {
                    showToast('Error al editar', 'error');
                });
                return;
            }

            // Confirmación solo en modo creación
            Swal.fire({
                title: 'Confirmación',
                text: `¿Desea guardar ${consumos.length} requerimientos?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (!result.isConfirmed) return;
                fetch('/modulo-nuevo-requerimiento/guardar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        consumos: consumos,
                        numero_empleado: '',
                        nombre_empleado: ''
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Guardado', text: `Requerimientos guardados. Folio: ${data.folio}` });
                    } else {
                        Swal.fire({ icon: 'warning', title: 'Atención', text: data.message || 'No se pudo guardar' });
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al guardar requerimientos' });
                });
            });
        }

        // Debounce para evitar múltiples guardados consecutivos
        let _guardarTimeout = null;
        function scheduleGuardarRequerimientos() {
            if (_guardarTimeout) clearTimeout(_guardarTimeout);
            _guardarTimeout = setTimeout(() => {
                guardarRequerimientos();
            }, 400);
        }

        // Función para mostrar toast
        function showToast(message, type = 'success') {
            const map = { success: 'success', error: 'error', warning: 'warning', info: 'info' };
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: map[type] || 'success',
                title: message,
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
        }

        // Función para actualizar cantidad directamente en la BD
        function actualizarCantidadEnBD(consumoId, cantidad) {
            console.log('Enviando actualización:', { id: consumoId, cantidad: cantidad });

            fetch('/modulo-nuevo-requerimiento/actualizar-cantidad', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    id: parseInt(consumoId),
                    cantidad: parseFloat(cantidad)
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showToast(`Cantidad actualizada: ${data.cantidad}`, 'success');
                } else {
                    showToast(data.message || 'Error al actualizar cantidad', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error al actualizar cantidad', 'error');
            });
        }
    </script>

    <style>
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
    </style>
@endsection

