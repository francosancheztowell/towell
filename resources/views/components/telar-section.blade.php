{{--
    Componente: Telar Section

    Descripción:
        Sección reutilizable para mostrar información de un telar.
        Incluye header dinámico, datos del proceso y siguiente orden.

    Props:
        @param object $telar - Datos del telar actual
        @param object $ordenSig - Datos de la siguiente orden (opcional)
        @param string $tipo - Tipo de telar: 'jacquard', 'itema', 'smith' (default: 'jacquard')
        @param bool $showRequerimiento - Si mostrar sección de requerimiento (default: true)
        @param bool $showSiguienteOrden - Si mostrar siguiente orden (default: true)

    Uso:
        <x-telar-section
            :telar="$telar"
            :ordenSig="$ordenSig"
            tipo="jacquard"
        />
--}}

@props([
    'telar',
    'ordenSig' => null,
    'tipo' => 'jacquard',
    'showRequerimiento' => true,
    'showSiguienteOrden' => true
])

@php
    $tipos = [
        'jacquard' => 'JACQUARD SULZER',
        'itema' => 'ITEMA',
        'smith' => 'SMITH'
    ];

    $tipoNombre = $tipos[$tipo] ?? $tipos['jacquard'];
    $isActive = $telar->en_proceso ?? false;
@endphp

<div class="telar-section bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
    <!-- Header dinámico -->
    @if($isActive)
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-3 relative">
            <!-- Separador superior -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500"></div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <h2 class="text-3xl font-bold">{{ $tipoNombre }}</h2>
                    <div class="ml-4 h-8 w-px bg-white opacity-30"></div>
                    <span class="ml-4 text-lg font-medium">TEJIDO</span>
                </div>

                <!-- Número del telar más prominente -->
                <div class="bg-red-500 text-white px-5 py-2 rounded-lg shadow-lg transform hover:scale-105 transition-transform">
                    <div class="text-center">
                        <div class="text-xs uppercase tracking-wider opacity-90">TELAR</div>
                        <div class="text-3xl font-bold">{{ $telar->Telar }}</div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="bg-gray-100 border-b border-gray-300 px-4 py-3 relative">
            <!-- Separador superior -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-gray-400 via-gray-500 to-gray-600"></div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <h2 class="text-3xl font-bold text-gray-600">{{ $tipoNombre }}</h2>
                    <div class="ml-4 h-8 w-px bg-gray-400 opacity-30"></div>
                    <span class="ml-4 text-lg font-medium text-gray-500">TEJIDO</span>
                </div>

                <!-- Número del telar más prominente -->
                <div class="bg-gray-400 text-white px-5 py-2 rounded-lg shadow-lg">
                    <div class="text-center">
                        <div class="text-xs uppercase tracking-wider opacity-90">TELAR</div>
                        <div class="text-3xl font-bold">{{ $telar->Telar }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($isActive)
        <!-- Sección EN PROCESO -->
        <div class="p-3">
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                <!-- Información Principal -->
                <div class="space-y-2">
                    <div class="flex items-start justify-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Orden:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">{{ $telar->Orden_Prod ?? '-' }}</span>
                    </div>
                    <div class="flex items-start justify-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Flog:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">{{ $telar->Id_Flog ?? '-' }}</span>
                    </div>
                    <div class="flex items-start justify-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Cliente:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-1">{{ $telar->Cliente ?? '-' }}</span>
                    </div>
                    <div class="flex items-start justify-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Tiras:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">{{ $telar->Tiras ?? '-' }}</span>
                    </div>
                    <div class="flex items-start justify-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Tamaño:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">{{ $telar->Tamano_AX ?? '-' }}</span>
                    </div>
                    <div class="flex items-start justify-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Artículo:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">{{ ($telar->ItemId ?? '') . ' ' . ($telar->Nombre_Producto ?? '') }}</span>
                    </div>
                </div>

                <!-- Especificaciones Técnicas -->
                <div class="space-y-2">
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Rizo:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">
                            {{ ($telar->Cuenta ?? '') . ($telar->Calibre_Rizo ? ' - ' . $telar->Calibre_Rizo : '') . ($telar->Fibra_Rizo ? ' - ' . $telar->Fibra_Rizo : '') }}
                        </span>
                    </div>
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Pie:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">
                            {{ ($telar->Cuenta_Pie ?? '') . ($telar->Calibre_Pie ? ' - ' . $telar->Calibre_Pie : '') . ($telar->Fibra_Pie ? ' - ' . $telar->Fibra_Pie : '') }}
                        </span>
                    </div>
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Trama:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">
                            {{ ($telar->CALIBRE_TRA ?? '') . ($telar->COLOR_TRAMA ? ' - ' . $telar->COLOR_TRAMA : '') }}
                        </span>
                    </div>
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Pedido:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">{{ $telar->Saldos ?? '-' }}</span>
                    </div>
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Producción:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">{{ $telar->Prod_Kg_Dia ?? '-' }}</span>
                    </div>
                </div>

                <!-- Información Adicional -->
                <div class="space-y-2">
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Marbetes:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">{{ $telar->Marbetes_Pend ?? '-' }}</span>
                    </div>
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Inicio:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">
                            @if($telar->Inicio_Tejido && $telar->Inicio_Tejido !== '1900-01-01')
                                {{ \Carbon\Carbon::parse($telar->Inicio_Tejido)->format('d/m/Y') }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Fin:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">
                            @if($telar->Fin_Tejido && $telar->Fin_Tejido !== '1900-01-01')
                                {{ \Carbon\Carbon::parse($telar->Fin_Tejido)->format('d/m/Y') }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Comp:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">
                            @if($telar->Fecha_Compromiso && $telar->Fecha_Compromiso !== '1900-01-01')
                                {{ \Carbon\Carbon::parse($telar->Fecha_Compromiso)->format('d/m/Y') }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Paros:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">{{ $telar->Total_Paros ?? '-' }}</span>
                    </div>
                    <div class="flex items-start">
                        <span class="text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">T. Paro:</span>
                        <span class="text-sm font-semibold text-gray-900 ml-2">{{ $telar->Tiempo_Paro ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($showSiguienteOrden)
            <!-- Separador visual -->
            <div>
                <div class="text-gray-900 bg-gray-200 px-4 py-2">
                    <h2 class="text-md font-bold text-center">DATOS DE LA SIGUIENTE ORDEN</h2>
                </div>
            </div>

            @if($ordenSig && ($ordenSig->Orden_Prod || $ordenSig->Nombre_Producto))
                <!-- Sección SIGUIENTE ORDEN con datos -->
                <div class="p-3">
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                        <!-- Columna 1: Orden -->
                        <div class="space-y-2">
                            <div class="flex items-start">
                                <span class="text-xs text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Orden:</span>
                                <span class="text-sm font-semibold text-gray-900 ml-2">{{ $ordenSig->Orden_Prod ?? '-' }}</span>
                            </div>
                            <!-- <div class="flex items-start">
                                <span class="text-xs text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Artículo:</span>
                                <span class="text-sm font-semibold text-gray-900 ml-2">{{ ($ordenSig->ItemId ?? '') . ' - ' . ($ordenSig->Tamano_AX ?? '') }}</span>
                            </div> -->
                            <div class="flex items-start">
                                <span class="text-xs text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Producto:</span>
                                <span class="text-sm font-semibold text-gray-900 ml-2">{{ $ordenSig->Nombre_Producto ?? '-' }}</span>
                            </div>
                        </div>

                        <!-- Columna 2: Especificaciones -->
                        <div class="space-y-2">
                            <div class="flex items-start">
                                <span class="text-xs text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Rizo:</span>
                                <span class="text-sm font-semibold text-gray-900 ml-2">
                                    {{ ($ordenSig->Cuenta ?? '') . ($ordenSig->Calibre_Rizo ? ' - ' . $ordenSig->Calibre_Rizo : '') . ($ordenSig->Fibra_Rizo ? ' - ' . $ordenSig->Fibra_Rizo : '') }}
                                </span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-xs text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Pie:</span>
                                <span class="text-sm font-semibold text-gray-900 ml-2">
                                    {{ ($ordenSig->Cuenta_Pie ?? '') . ($ordenSig->Calibre_Pie ? ' - ' . $ordenSig->Calibre_Pie : '') . ($ordenSig->Fibra_Pie ? ' - ' . $ordenSig->Fibra_Pie : '') }}
                                </span>
                            </div>
                        </div>

                        <!-- Columna 3: Pedido -->
                        <div class="space-y-2">
                            <div class="flex items-start">
                                <span class="text-xs text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Pedido:</span>
                                <span class="text-sm font-semibold text-gray-900 ml-2">{{ $ordenSig->Saldos ?? '-' }}</span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-xs text-gray-500 uppercase tracking-wide w-20 flex-shrink-0">Inicio:</span>
                                <span class="text-sm font-semibold text-gray-900 ml-2">
                                    @if($ordenSig->Inicio_Tejido && $ordenSig->Inicio_Tejido !== '1900-01-01')
                                        {{ \Carbon\Carbon::parse($ordenSig->Inicio_Tejido)->format('d/m/Y') }}
                                    @else
                                        -
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- Mensaje cuando no hay datos de siguiente orden -->
                <div class="p-6">
                    <div class="flex items-center justify-center">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 mb-3">
                                <svg class="h-6 w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-medium text-gray-900 mb-1">Sin siguiente orden programada</h3>
                            <p class="text-xs text-gray-500">No hay órdenes futuras programadas para este telar</p>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if($showRequerimiento)
            <!-- Separador visual -->
            <div>
                <div class="text-gray-900 bg-gray-200 px-4 py-2">
                    <h2 class="text-md font-bold text-center">REQUERIMIENTO</h2>
                </div>
            </div>

            <!-- Sección REQUERIMIENTO -->
            <x-telar-requerimiento :telar="$telar" :ordenSig="$ordenSig" :salon="ucfirst($tipo)" />
        @endif
    @else
        <!-- Telar sin proceso activo -->
        <div class="p-4">
            <div class="flex items-center justify-center py-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-3">
                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Telar {{ $telar->Telar }}</h3>
                    <p class="text-sm text-gray-500 mb-2">Sin proceso activo</p>
                    <p class="text-xs text-gray-400">Órdenes futuras programadas</p>
                </div>
            </div>
        </div>
    @endif
</div>

