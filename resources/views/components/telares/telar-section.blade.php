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
        <x-telares.telar-section
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

    $formatSpec = function ($cuenta, $calibre, $fibra) {
        $cuenta = $cuenta ?? '';
        $calibre = $calibre ? number_format((float) $calibre, 2, '.', '') : '';
        $fibra = $fibra ?? '';
        $texto = $cuenta;
        if ($calibre !== '') {
            $texto .= ($texto !== '' ? ' - ' : '') . $calibre;
        }
        if ($fibra !== '') {
            $texto .= ($texto !== '' ? ' - ' : '') . $fibra;
        }
        return $texto ?: '-';
    };

    $formatTrama = function ($calibre, $color) {
        $calibre = $calibre ? number_format((float) $calibre, 2, '.', '') : '';
        $color = $color ?? '';
        $texto = $calibre;
        if ($color !== '') {
            $texto .= ($texto !== '' ? ' - ' : '') . $color;
        }
        return $texto ?: '-';
    };

    $formatDate = function ($value) {
        if (!$value || $value === '1900-01-01') {
            return '-';
        }
        return \Carbon\Carbon::parse($value)->format('d/m/Y');
    };

    $rowClass = 'flex items-start';
    $labelClass = 'text-sm text-gray-500 uppercase tracking-wide w-20 flex-shrink-0';
    $labelClassXs = 'text-xs text-gray-500 uppercase tracking-wide w-20 flex-shrink-0';
    $valueClass = 'text-sm font-semibold text-gray-900 ml-2';
    $valueClassTight = 'text-sm font-semibold text-gray-900 ml-1';
    $sectionTitleClass = 'text-gray-900 bg-gray-200 px-4 py-2';

    $tieneOrdenSig = $ordenSig && array_filter([
        $ordenSig->Orden_Prod ?? null,
        $ordenSig->Nombre_Producto ?? null,
        $ordenSig->Cuenta ?? null,
        $ordenSig->Cuenta_Pie ?? null,
        $ordenSig->Inicio_Tejido ?? null,
    ], function ($value) {
        return !empty($value);
    });
@endphp

<div class="telar-section bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
    <!-- Header dinámico -->
    @if($isActive)
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-3 relative">
            <!-- Separador superior -->
            <div class="absolute top-0 left-0 right-0 "></div>

            <div class="flex items-center justify-between">

                <!-- Número del telar más prominente -->
                <div class=" text-white px-5 py-2">
                    <div class="text-4xl font-bold">{{ $telar->Telar }}</div>
                </div>
            </div>
        </div>
    @else
        <div>Telar sin proceso activo</div>
    @endif

    @if($isActive)
        <!-- Sección EN PROCESO -->
        <div class="p-3">
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                <!-- Información Principal -->
                <div class="space-y-2">
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Orden:</span>
                        <span class="{{ $valueClass }}">{{ $telar->Orden_Prod ?? '-' }}</span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Flog:</span>
                        <span class="{{ $valueClass }}">{{ $telar->Id_Flog ?? '-' }}</span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Cliente:</span>
                        <span class="{{ $valueClassTight }}">{{ $telar->Cliente ?? '-' }}</span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Tiras:</span>
                        <span class="{{ $valueClass }}">{{ $telar->Tiras ?? '-' }}</span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Tamaño:</span>
                        <span class="{{ $valueClass }}">{{ $telar->Tamano_AX ?? '-' }}</span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Artículo:</span>
                        <span class="{{ $valueClass }}">{{ ($telar->ItemId ?? '') . ' ' . ($telar->Nombre_Producto ?? '') }}</span>
                    </div>
                </div>

                <!-- Especificaciones Técnicas -->
                <div class="space-y-2">
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Rizo:</span>
                        <span class="{{ $valueClass }}">
                            {{ $formatSpec($telar->Cuenta ?? null, $telar->CalibreRizo2 ?? null, $telar->Fibra_Rizo ?? null) }}
                        </span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Pie:</span>
                        <span class="{{ $valueClass }}">
                            {{ $formatSpec($telar->Cuenta_Pie ?? null, $telar->CalibrePie2 ?? null, $telar->Fibra_Pie ?? null) }}
                        </span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Trama:</span>
                        <span class="{{ $valueClass }}">
                            {{ $formatTrama($telar->CalibreTrama2 ?? null, $telar->COLOR_TRAMA ?? null) }}
                        </span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Pedido:</span>
                        <span class="{{ $valueClass }}">{{ $telar->Saldos ?? '-' }}</span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Producción:</span>
                        <span class="{{ $valueClass }}">{{ $telar->Prod_Kg_Dia ?? '-' }}</span>
                    </div>
                </div>

                <!-- Información Adicional -->
                <div class="space-y-2">
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Marbetes:</span>
                        <span class="{{ $valueClass }}">{{ $telar->Marbetes_Pend ?? '-' }}</span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Inicio:</span>
                        <span class="{{ $valueClass }}">
                            {{ $formatDate($telar->Inicio_Tejido ?? null) }}
                        </span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Fin:</span>
                        <span class="{{ $valueClass }}">
                            {{ $formatDate($telar->Fin_Tejido ?? null) }}
                        </span>
                    </div>
                    <div class="{{ $rowClass }}">
                        <span class="{{ $labelClass }}">Comp:</span>
                        <span class="{{ $valueClass }}">
                            {{ $formatDate($telar->Fecha_Compromiso ?? null) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        @if($showSiguienteOrden)
            <!-- Separador visual -->
            @if($tieneOrdenSig)
                <!-- Sección SIGUIENTE ORDEN con datos -->
                <div class="p-3">
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                        <!-- Columna 1: Orden -->
                        <div class="space-y-2">
                            <div class="{{ $rowClass }}">
                                <span class="{{ $labelClassXs }}">Orden:</span>
                                <span class="{{ $valueClass }}">{{ $ordenSig->Orden_Prod ?? '-' }}</span>
                            </div>
                            <div class="{{ $rowClass }}">
                                <span class="{{ $labelClassXs }}">Producto:</span>
                                <span class="{{ $valueClass }}">{{ $ordenSig->Nombre_Producto ?? '-' }}</span>
                            </div>
                        </div>

                        <!-- Columna 2: Especificaciones -->
                        <div class="space-y-2">
                            <div class="{{ $rowClass }}">
                                <span class="{{ $labelClassXs }}">Rizo:</span>
                                <span class="{{ $valueClass }}">
                                    {{ $formatSpec($ordenSig->Cuenta ?? null, $ordenSig->CalibreRizo2 ?? null, $ordenSig->Fibra_Rizo ?? null) }}
                                </span>
                            </div>
                            <div class="{{ $rowClass }}">
                                <span class="{{ $labelClassXs }}">Pie:</span>
                                <span class="{{ $valueClass }}">
                                    {{ $formatSpec($ordenSig->Cuenta_Pie ?? null, $ordenSig->CalibrePie2 ?? null, $ordenSig->Fibra_Pie ?? null) }}
                                </span>
                            </div>
                        </div>

                        <!-- Columna 3: Pedido -->
                        <div class="space-y-2">
                            <div class="{{ $rowClass }}">
                                <span class="{{ $labelClassXs }}">Pedido:</span>
                                <span class="{{ $valueClass }}">{{ $ordenSig->Saldos ?? '-' }}</span>
                            </div>
                            <div class="{{ $rowClass }}">
                                <span class="{{ $labelClassXs }}">Inicio:</span>
                                <span class="{{ $valueClass }}">
                                    {{ $formatDate($ordenSig->Inicio_Tejido ?? null) }}
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
                                <i class="fas fa-exclamation-triangle text-orange-600 text-2xl"></i>
                            </div>
                            <h3 class="text-sm font-medium text-gray-900 mb-1">Sin siguiente orden programada</h3>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if($showRequerimiento)
            <!-- Separador visual -->
            <div>
                <div class="{{ $sectionTitleClass }}">
                </div>
            </div>

            <!-- Sección REQUERIMIENTO -->
            <x-telares.telar-requerimiento :telar="$telar" :ordenSig="$ordenSig" :salon="ucfirst($tipo)" />
        @endif
    @else
        <!-- Telar sin proceso activo -->
        <div class="p-4">
            <div class="flex items-center justify-center py-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-3">
                        <i class="fas fa-clock text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Telar {{ $telar->Telar }}</h3>
                    <p class="text-sm text-gray-500 mb-2">Sin proceso activo</p>
                    <p class="text-xs text-gray-400">Órdenes futuras programadas</p>
                </div>
            </div>
        </div>
    @endif
</div>
