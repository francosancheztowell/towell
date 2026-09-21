<div class="w-full h-[calc(100vh-64px)] flex flex-col">
    @if(count($folios) > 0)
        <div class="bg-white w-full flex flex-col h-full min-h-0">
            {{-- Listado de folios + acciones --}}
            <div class="p-4 sm:p-6 md:p-8 w-full">
                <div class="flex flex-col md:flex-row lg:flex-row gap-3 sm:gap-4 md:gap-6 lg:gap-6 w-full">
                    <!-- Tabla 1: Folios -->
                    <div class="flex-1 border border-gray-300 rounded-lg overflow-hidden min-w-0 w-full">
                        <div class="overflow-y-auto h-32 md:h-32 lg:h-48">
                            <table class="w-full">
                                <thead class="sticky top-0 z-10">
                                    <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 border-r border-gray-200">Folio</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 border-r border-gray-200">Fecha</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 border-r border-gray-200">Status</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700 border-r border-gray-200">Turno</th>
                                        <th class="px-4 py-1 text-left text-sm font-semibold text-gray-700">Operador</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($folios as $req)
                                        @php
                                            $statusClass = $statusColors[$req['Status']] ?? 'bg-gray-100 text-gray-800';
                                            $isSelected = $req['Folio'] === $folioSeleccionado;
                                            $rowClass = $isSelected
                                                ? 'bg-blue-500 text-white'
                                                : ($loop->even ? 'bg-gray-50' : 'bg-white').' hover:bg-gray-50';
                                        @endphp
                                        <tr wire:key="folio-{{ $req['Folio'] }}"
                                            wire:click="seleccionar('{{ $req['Folio'] }}')"
                                            class="cursor-pointer {{ $rowClass }}">
                                            <td class="px-4 py-1 text-sm font-semibold {{ $isSelected ? 'text-white' : 'text-gray-900' }} border-r border-gray-200">{{ $req['Folio'] }}</td>
                                            <td class="px-4 py-1 text-sm font-semibold {{ $isSelected ? 'text-white' : 'text-gray-900' }} border-r border-gray-200">{{ \Carbon\Carbon::parse($req['Fecha'])->format('d/m/Y') }}</td>
                                            <td class="px-4 py-1 border-r border-gray-200">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $isSelected ? 'bg-blue-600 text-white' : $statusClass }}">{{ $req['Status'] }}</span>
                                            </td>
                                            <td class="px-4 py-1 text-sm font-semibold {{ $isSelected ? 'text-white' : 'text-gray-900' }} border-r border-gray-200">{{ $turnoDesc[$req['Turno']] ?? $req['Turno'] }}</td>
                                            <td class="px-4 py-1 text-sm font-semibold {{ $isSelected ? 'text-white' : 'text-gray-900' }}">{{ $req['numero_empleado'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex items-center justify-between gap-2 px-3 py-2 border-t border-gray-200 bg-gray-50">
                            <span class="text-xs text-gray-500">Mostrando {{ count($folios) }} folios más recientes</span>
                            @if($hayMas)
                                <button type="button" wire:click="cargarMas" wire:loading.attr="disabled"
                                    class="px-3 py-1 text-sm font-medium text-blue-600 bg-white border border-blue-300 rounded-md hover:bg-blue-50 transition-colors disabled:opacity-50">
                                    <span wire:loading.remove wire:target="cargarMas">Cargar más</span>
                                    <span wire:loading wire:target="cargarMas">Cargando...</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Acciones dinámicas -->
                    <div class="flex flex-col space-y-2 lg:min-w-48">
                        @if(($statusSeleccionado ?? '') === 'En Proceso')
                            <button type="button" wire:click="cambiarStatus('Solicitado')"
                                wire:confirm="¿Está seguro de cambiar el status a Solicitar consumo?"
                                class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition-colors w-full">
                                <i class="fas fa-list mr-2"></i>Solicitar consumo
                            </button>
                            <button type="button" wire:click="editar"
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors w-full">
                                <i class="fas fa-pen-to-square mr-2"></i>Editar
                            </button>
                            <button type="button" wire:click="cambiarStatus('Cancelado')"
                                wire:confirm="¿Está seguro de cancelar este requerimiento?"
                                class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors w-full">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </button>
                        @endif

                        @if($resumenUrl)
                            <a href="{{ $resumenUrl }}" target="_blank"
                                class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 transition-colors text-center">
                                <i class="fas fa-eye mr-2"></i>Resumen de articulo
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tabla 2: Detalle, ocupa el alto restante de la pantalla (sin scroll de página) --}}
            <div class="flex-1 min-h-0 px-4 sm:px-6 md:px-8 pb-4">
                <div class="border border-gray-300 rounded-lg overflow-hidden h-full flex flex-col">
                    <div class="overflow-y-auto overflow-x-auto flex-1 min-h-0">
                        <table class="w-full min-w-[760px]">
                            <thead class="sticky top-0 z-10">
                                <tr class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-r border-gray-200 whitespace-nowrap">Folio</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-r border-gray-200 whitespace-nowrap">Telar</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-r border-gray-200 whitespace-nowrap">Articulo</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-r border-gray-200 whitespace-nowrap">Nombre</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-r border-gray-200 whitespace-nowrap">Fibra</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-r border-gray-200 whitespace-nowrap">Cod Color</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 border-r border-gray-200 whitespace-nowrap">Nombre Color</th>
                                    <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 whitespace-nowrap">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($detalles as $consumo)
                                    <tr wire:key="det-{{ $loop->index }}" class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }} hover:bg-gray-100 transition-colors">
                                        <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200 whitespace-nowrap">{{ $consumo['Folio'] }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200 whitespace-nowrap">{{ $consumo['NoTelarId'] }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200 whitespace-nowrap">{{ $consumo['CalibreTrama'] ? number_format((float) $consumo['CalibreTrama'], 2) : '-' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo['NombreProducto'] ?: '-' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200 whitespace-nowrap">{{ $consumo['FibraTrama'] ?: '-' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200 whitespace-nowrap">{{ $consumo['CodColorTrama'] ?: '-' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 border-r border-gray-200">{{ $consumo['ColorTrama'] ?: '-' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">{{ $consumo['Cantidad'] ? number_format((float) $consumo['Cantidad'], 0) : '0' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-4 text-center text-gray-500 bg-white">
                                            <i class="fas fa-inbox text-2xl mb-2"></i>
                                            <p>No hay consumos registrados</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div id="no-requerimientos" class="p-4">
            <div class="text-center py-12">
                <div class="bg-gray-100 rounded-2xl p-8 max-w-md mx-auto">
                    <i class="fas fa-trash text-2xl mb-2"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay requerimientos disponibles</h3>
                    <p class="text-gray-500">No se encontraron requerimientos guardados en el sistema</p>
                </div>
            </div>
        </div>
    @endif
</div>
