@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
    <div class="p-2 overflow-y-auto" style="max-height: calc(100vh - 120px);">
        @php
            $esJacquardSulzer = request()->is('tejido/jacquard-sulzer/*');
            $telarData = $datos->first();
            $tipo = 'jacquard'; // Detectar tipo basado en la ruta si es necesario
        @endphp

        <!-- Usar componente de sección de telar -->
        <x-telar-section
            :telar="$telarData"
            :ordenSig="$ordenSig"
            tipo="{{ $tipo }}"
            :showRequerimiento="true"
            :showSiguienteOrden="true"
        />

        <div class="flex justify-center w-80 mt-1">
            <a href="{{ route('ordenes.programadas', ['telar' => $telar]) }}"
                class="inline-block bg-blue-800 text-white font-bold py-1 px-6 rounded hover:bg-blue-900">
                ÓRDENES PROGRAMADAS
            </a>

        </div>
    </div>

    <!-- JavaScript manejado por el componente telar-requerimiento -->
@endsection
