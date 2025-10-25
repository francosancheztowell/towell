@extends('layouts.app', ['ocultarBotones' => true])
@section('page-title')
    <x-page-title
        title="{{ $moduloPrincipal }}"
    />
@endsection

@section('content')
    <div class="container"  id="globalLoader">
        @if (count($subModulos) === 0)
            <!-- Estado vacío -->
            <div class="text-center py-12">
                <div class="bg-gray-100 rounded-2xl p-8 max-w-md mx-auto">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay sub-módulos disponibles</h3>
                    <p class="text-gray-500">No tienes permisos para acceder a los sub-módulos de {{ $moduloPrincipal }}</p>
                </div>
            </div>
        @else
            <!-- Grid de sub-módulos usando componente -->
            <x-module-grid :modulos="$subModulos" columns="xl:grid-cols-4" :filterConfig="true" />
        @endif
    </div>
@endsection
