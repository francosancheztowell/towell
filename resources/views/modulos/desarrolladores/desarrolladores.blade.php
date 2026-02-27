@extends('layouts.app', ['ocultarBotones' => true])

@section('page-title', 'Desarrolladores')

@section('content')
    <div class="flex w-full flex-col px-4 py-4 md:px-6 lg:px-6">
        <div class="bg-white flex flex-col rounded-md max-w-full p-6">
            <!-- Layout en columnas: Select a la izquierda, tabla a la derecha -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Columna: Select de Telares -->
                @include('modulos.desarrolladores.partials.select-telar')

                <!-- Columna: Tabla de Producciones -->
                @include('modulos.desarrolladores.partials.tabla-producciones')
            </div>

            <!-- Formulario inline debajo -->
            @include('modulos.desarrolladores.partials.form-desarrollador')
        </div>
    </div>

    <!-- Modales -->
    @include('modulos.desarrolladores.partials.modales')
@endsection

@push('scripts')
    @include('modulos.desarrolladores.partials.scripts')
@endpush