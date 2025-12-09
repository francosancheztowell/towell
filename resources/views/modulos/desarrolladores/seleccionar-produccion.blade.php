@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Seleccionar Producción - Telar ' . $telarId)

@section('content')
<div class="flex w-screen h-full overflow-hidden flex-col px-4 py-4 md:px-6 lg:px-6">
    <div class="bg-white flex flex-col flex-1 rounded-md overflow-hidden max-w-full p-6">
        <!-- Botón para regresar -->
        <div class="mb-4">
            <a href="{{ route('desarrolladores') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Regresar
            </a>
        </div>

        <h2 class="text-2xl font-bold mb-6 text-gray-800">Telar: <span class="text-blue-600">{{ $telarId }}</span></h2>

        @if($producciones->isEmpty())
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-yellow-800 font-medium">No se encontraron producciones activas para este telar.</p>
                </div>
            </div>
        @else
            <div class="mb-4">
                <p class="text-gray-600">Selecciona un número de producción para continuar con el formulario:</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($producciones as $produccion)
                    <a href="{{ route('desarrolladores.formulario', ['telarId' => $telarId, 'noProduccion' => $produccion->NoProduccion]) }}"
                       class="block p-6 bg-white border-2 border-gray-200 rounded-lg shadow hover:shadow-xl transition-all hover:border-blue-500 hover:-translate-y-1 duration-200">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg font-bold text-gray-900">
                                {{ $produccion->NoProduccion }}
                            </h3>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                        @if($produccion->NombreProducto)
                            <p class="text-sm text-gray-600 line-clamp-2">{{ $produccion->NombreProducto }}</p>
                        @endif
                        @if($produccion->ItemId)
                            <p class="text-xs text-gray-500 mt-2">Item: {{ $produccion->ItemId }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
