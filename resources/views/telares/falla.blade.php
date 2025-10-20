@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
        <div class="bg-yellow-500 px-6 py-4 border-t-4 border-orange-400">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-white">Fallas de Telares</h1>
                <div class="text-white text-right">
                    <div class="text-sm opacity-90">Sistema de Reporte de Fallas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="text-center py-12">
            <svg class="w-16 h-16 text-yellow-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <h3 class="text-lg font-semibold text-gray-600 mb-2">Módulo de Fallas en Desarrollo</h3>
            <p class="text-gray-500">Esta funcionalidad estará disponible próximamente</p>
        </div>
    </div>
</div>
@endsection
