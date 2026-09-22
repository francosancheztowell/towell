<div class="container mx-auto space-y-6 pb-12">
    @forelse($telares as $idx => $telar)
        @php
            $d = $telar['telarData'] ?? [];
            $rail = (($telar['tipo'] ?? '') === 'itema'
                ? 'bg-gradient-to-b from-gray-400 to-gray-500'
                : 'bg-gradient-to-b from-blue-600 to-blue-700');
        @endphp
        <div id="telar-{{ $telar['numero'] }}"
            class="relative bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden"
            data-telar="{{ $telar['numero'] }}">

            <div
                class="{{ $rail }} absolute left-0 top-0 bottom-0 w-[86px] sm:w-[92px] md:w-[110px] flex flex-col items-center justify-between py-2 px-1.5 border-r border-gray-200">
                <div class="text-center w-full mt-1">
                    <h2 class="text-[2rem] sm:text-[2.25rem] md:text-[2.5rem] font-extrabold text-white leading-none py-3 text-center drop-shadow-sm">
                        {{ $telar['numero'] }}</h2>
                </div>
                <div class="w-full mt-auto">
                    <button type="button" wire:click="abrirModal('{{ $telar['numero'] }}')"
                        class="w-full flex flex-col items-center justify-center gap-0.5 px-2 py-2 bg-white/95 text-blue-700 hover:bg-white shadow-sm rounded-md transition-colors">
                        <span class="text-[10px] leading-3 font-semibold">Nuevo</span>
                        <span class="text-[10px] leading-3 font-semibold">Requerimiento</span>
                    </button>
                </div>
            </div>

            <div class="p-4 sm:p-6 ml-[88px] sm:ml-[96px] md:ml-[112px]">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-6 mb-6">
                    <div class="space-y-3">
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Folio:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $folioActual ?: '-' }}</span>
                        </div>
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Fecha:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $fecha ?? '' }}</span>
                        </div>
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Turno:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $turnoDesc ?? '' }}</span>
                        </div>
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Orden:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $d['Orden_Prod'] ?? '-' }}</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">No Flog:</span>
                            <span class="text-sm font-semibold text-gray-900">
                                {{ trim(($d['Id_Flog'] ?? '') . ' / ' . ($d['Calidad'] ?? '')) ?: '-' }}
                            </span>
                        </div>
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Cliente:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $d['Cliente'] ?? '-' }}</span>
                        </div>
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Tamaño:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $d['InventSizeId'] ?? '-' }}</span>
                        </div>
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Artículo:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ ($d['ItemId'] ?? '-') . ' ' . ($d['Nombre_Producto'] ?? '-') }}</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Pedido:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $d['Saldos'] ?? '-' }}</span>
                        </div>
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Producción:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $d['Produccion'] ?? '-' }}</span>
                        </div>
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Inicio:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $d['Inicio_Tejido'] ?? '-' }}</span>
                        </div>
                        <div class="flex justify-start items-center border-b border-gray-200 pb-2">
                            <span class="text-sm font-semibold text-gray-600">Fin:</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $d['Fin_Tejido'] ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <div class="w-full h-px bg-gray-300 my-4" aria-hidden="true"></div>

                <div class="rounded-lg relative z-10 overflow-x-auto">
                    <table class="w-full min-w-[640px] mt-2.5">
                        <thead class="relative z-10">
                            <tr class="bg-gray-100">
                                <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Artículo</th>
                                <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Fibra</th>
                                <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Cod Color</th>
                                <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider border-r border-gray-200">Nombre Color</th>
                                <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($telar['rows'] ?? [] as $rIdx => $row)
                                <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }} hover:bg-blue-50">
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">
                                        {{ $row['calibre'] !== null ? number_format((float) $row['calibre'], 2) : '-' }}
                                    </td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $row['fibra'] ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $row['cod_color'] ?? '-' }}</td>
                                    <td class="px-4 py-1 text-sm text-gray-900 border-r border-gray-200">{{ $row['color'] ?? '-' }}</td>
                                    <td class="px-4 py-1">
                                        <input type="number" min="0" max="100"
                                            wire:change="actualizarCantidad({{ $idx }}, {{ $rIdx }}, $event.target.value)"
                                            value="{{ (int) ($row['cantidad'] ?? 0) }}"
                                            class="w-20 px-2 py-1 text-sm text-center border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-center text-gray-400 text-sm italic">
                                        Sin requerimientos — presiona "Nuevo" para agregar
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="text-center py-12">
            <div class="bg-gray-100 rounded-2xl p-8 max-w-md mx-auto">
                <i class="fas fa-trash text-2xl mb-2"></i>
                <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay requerimientos disponibles</h3>
                <p class="text-gray-500">No se encontraron requerimientos que coincidan con los filtros seleccionados</p>
            </div>
        </div>
    @endforelse

    {{-- Sin botón Guardar: todo cambio (cantidad, agregar, eliminar) se persiste solo --}}

    {{-- Modal Agregar Requerimiento --}}
    @if($modalAbierto)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
            wire:click.self="cerrarModal">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="bg-blue-500 px-6 py-4 rounded-t-lg flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-white">Agregar Nuevo Requerimiento — Telar {{ $telarModal }}</h3>
                    <button wire:click="cerrarModal" class="text-white hover:text-gray-200 transition-colors ml-4">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Calibre</label>
                            <select wire:model.live="modalCalibre"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar calibre...</option>
                                @foreach($calibres as $c)
                                    <option value="{{ $c['value'] }}">{{ $c['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Fibra</label>
                            <select wire:model.live="modalFibra"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="">{{ $modalCalibre ? 'Selecciona fibra' : 'Selecciona calibre primero' }}</option>
                                @foreach($fibras as $f)
                                    <option value="{{ $f }}">{{ $f }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cod Color</label>
                            <select wire:model.live="modalCodColor"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="">{{ $modalCalibre ? 'Selecciona color' : 'Selecciona calibre primero' }}</option>
                                @foreach($colores as $c)
                                    <option value="{{ $c['value'] }}">{{ $c['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre Color</label>
                            <input type="text" wire:model="modalNombreColor" readonly
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-700"
                                placeholder="Se llena automáticamente">
                        </div>
                    </div>
                    <div class="mt-6">
                        <button wire:click="agregarRequerimiento" wire:loading.attr="disabled"
                            class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50 flex items-center justify-center gap-2">
                            <span wire:loading.remove>Agregar</span>
                            <span wire:loading><i class="fas fa-spinner fa-spin"></i> Agregando...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
