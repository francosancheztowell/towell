@extends('layouts.app', ['ocultarBotones' => true])
@section('page-title')
    <x-layout.page-title
        title="{{ $moduloPrincipal }}"
    />
@endsection

@section('content')
    <div class="container mx-auto"  id="globalLoader">
        @if (count($subModulos) === 0)
            <!-- Estado vacío -->
            <div class="text-center py-12">
                <div class="bg-gray-100 rounded-2xl p-8 max-w-md mx-auto">
                    <i class="fa-solid fa-folder-open text-gray-400 mx-auto mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay sub-módulos disponibles</h3>
                    <p class="text-gray-500">No tienes permisos para acceder a los sub-módulos de {{ $moduloPrincipal }}</p>
                </div>
            </div>
        @else
            <!-- Grid de sub-módulos usando componente -->
            <x-layout.module-grid :modulos="$subModulos" columns="xl:grid-cols-4" :filterConfig="true" />
        @endif
    </div>
@endsection
