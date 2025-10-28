@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Inventario Itema')

@section('content')
    <div class="container mx-auto">

        <!-- Navbar de telares usando componente (ordenados por secuencia) -->
        @if(count($telaresItema) > 0)
            <x-telar-navbar :telares="$telaresItema" />
        @endif

        <!-- Vista completa de todos los telares Itema con datos reales -->
        @if(count($telaresItema) > 0)
            <div class="space-y-6">
                @foreach ($telaresItema as $telar)
                    @php
                        // Obtener datos reales desde el controlador
                        $telarData = $datosTelaresCompletos[$telar]['telarData'] ?? (object) [
                            'Telar' => $telar,
                            'en_proceso' => false
                        ];

                        $ordenSig = $datosTelaresCompletos[$telar]['ordenSig'] ?? null;
                    @endphp

                    <!-- Usar componente de sección de telar con datos reales -->
                    <div id="telar-{{ $telar }}">
                        <x-telar-section
                            :telar="$telarData"
                            :ordenSig="$ordenSig"
                            tipo="itema"
                            :showRequerimiento="true"
                            :showSiguienteOrden="true"
                        />
                    </div>
                @endforeach
            </div>
        @else
            <!-- Mensaje cuando no hay datos -->
            <div class="flex flex-col items-center justify-center py-12 px-4">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-gray-100 mb-4">
                        <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709M15 6.291A7.962 7.962 0 0012 5c-2.34 0-4.29 1.009-5.824 2.709M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No hay telares Itema en proceso</h3>
                    <p class="text-gray-500 mb-4">
                        Actualmente no hay telares Itema con producción activa.
                    </p>
                    <p class="text-sm text-gray-400">
                        Los telares aparecerán aquí cuando tengan órdenes con <span class="font-semibold">EnProceso = 1</span>
                    </p>
                </div>
            </div>
        @endif
    </div>

    <!-- JavaScript manejado por los componentes telar-navbar y telar-requerimiento -->
@endsection
