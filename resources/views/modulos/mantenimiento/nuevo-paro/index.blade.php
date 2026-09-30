@extends('layouts.app')

@section('page-title', 'Reportar Paro')

@section('content')
@php
    $configPagina = [
        'rutas' => [
            'departamentos' => route('api.mantenimiento.departamentos'),
            'maquinas' => route('api.mantenimiento.maquinas', ['departamento' => '__DEPTO__']),
            'tiposFalla' => route('api.mantenimiento.tipos-falla', ['departamento' => '__DEPTO__']),
            'fallasPorTipo' => route('api.mantenimiento.fallas', ['departamento' => '__DEPTO__', 'tipoFallaId' => '__TIPO__']),
            'ordenTrabajo' => route('api.mantenimiento.orden-trabajo', ['departamento' => '__DEPTO__', 'maquina' => '__MAQ__']),
            'guardar' => route('api.mantenimiento.paros.store'),
            'solicitudes' => route('mantenimiento.solicitudes'),
            'nuevoParo' => route('mantenimiento.nuevo-paro'),
        ],
        'areaUsuario' => $areaUsuario ?? null,
    ];
@endphp
<div id="pagina-nuevo-paro" class="w-full p-3 md:p-6 lg:p-8" data-pagina='@json($configPagina)'>
    <div class="bg-white rounded-lg shadow-lg  border border-gray-200 p-4 md:p-6 lg:p-8 max-w-5xl mx-auto">

        <div class="mb-3 flex justify-end md:mb-4">
            <a href="{{ route('mantenimiento.solicitudes') }}"
                class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 md:text-base">
                <i class="fas fa-list"></i> Ver solicitudes
            </a>
        </div>

        <!-- Formulario -->
        <form id="form-paro">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 md:gap-3 items-start">

                <!-- Fecha (informativa: el servidor la estampa al guardar) -->
                <div>
                    <label for="fecha" class="block text-xs md:text-sm font-medium text-gray-700">Fecha</label>
                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        value="{{ date('Y-m-d') }}"
                        readonly
                        aria-describedby="ayuda-fecha-hora"
                    >
                    <p id="ayuda-fecha-hora" class="text-xs md:text-sm text-gray-700">
                        La fecha y la hora las registra el sistema al guardar.
                    </p>
                </div>

                <!-- Hora (informativa: el servidor la estampa al guardar) -->
                <div>
                    <label for="hora" class="block text-xs md:text-sm font-medium text-gray-700">Hora</label>
                    <input
                        type="time"
                        id="hora"
                        name="hora"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                        value="{{ date('H:i') }}"
                        readonly
                        aria-describedby="ayuda-fecha-hora"
                    >
                </div>

                <!-- Depto -->
                <div>
                    <label for="depto" class="block text-xs md:text-sm font-medium text-gray-700">Departamento</label>
                    <select
                        id="depto"
                        name="depto"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        aria-describedby="error-depto"
                        required
                    >
                        <option value="">Cargando...</option>
                    </select>
                    <p id="error-depto" aria-live="polite" class="text-xs md:text-sm text-red-700"></p>
                </div>

                <!-- Maquina -->
                <div>
                    <label for="maquina" class="block text-xs md:text-sm font-medium text-gray-700">Máquina</label>
                    <p id="ayuda-maquina" class="text-xs md:text-sm text-gray-700">
                        Seleccione primero un departamento para habilitar este campo.
                    </p>
                    <select
                        id="maquina"
                        name="maquina"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        aria-describedby="ayuda-maquina error-maquina"
                        disabled
                    >
                        <option value="">Seleccione primero un departamento</option>
                    </select>
                    <p id="error-maquina" aria-live="polite" class="text-xs md:text-sm text-red-700"></p>
                </div>

                <!-- Tipo Falla -->
                <div>
                    <label for="tipo_falla" class="block text-xs md:text-sm font-medium text-gray-700">Tipo Falla</label>
                    <p id="ayuda-tipo-falla" class="text-xs md:text-sm text-gray-700">
                        Seleccione primero una máquina para habilitar este campo.
                    </p>
                    <select
                        id="tipo_falla"
                        name="tipo_falla"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        aria-describedby="ayuda-tipo-falla error-tipo-falla"
                        disabled
                    >
                        <option value="">Seleccione primero una máquina</option>
                    </select>
                    <p id="error-tipo-falla" aria-live="polite" class="text-xs md:text-sm text-red-700"></p>
                </div>

                <!-- Descripción -->
                <div>
                    <label for="descripcion" class="block text-xs md:text-sm font-medium text-gray-700">Descripción</label>
                    <p id="ayuda-descripcion" class="text-xs md:text-sm text-gray-700">
                        Seleccione primero un tipo de falla para habilitar este campo.
                    </p>
                    <select
                        id="descripcion"
                        name="descrip"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        aria-describedby="ayuda-descripcion error-descripcion"
                        disabled
                    >
                        <option value="">Seleccione primero un tipo de falla</option>
                    </select>
                    <p id="error-descripcion" aria-live="polite" class="text-xs md:text-sm text-red-700"></p>
                </div>

                <!-- Orden de Trabajo -->
                <div>
                    <label for="orden_trabajo" class="block text-xs md:text-sm font-medium text-gray-700">Orden de Trabajo</label>
                    <p id="ayuda-orden-trabajo" class="text-xs md:text-sm text-gray-700">
                        Se sugiere sola al elegir la máquina. Puede escribirla o corregirla a mano. Máximo 20 caracteres, sin espacios.
                    </p>
                    <input
                        type="text"
                        id="orden_trabajo"
                        name="orden_trabajo"
                        maxlength="20"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        aria-describedby="ayuda-orden-trabajo"
                    >
                </div>

                <!-- Falla -->
                <div>
                    <label for="falla" class="block text-xs md:text-sm font-medium text-gray-700">Falla</label>
                    <p id="ayuda-falla" class="text-xs md:text-sm text-gray-700">
                        Seleccione primero un tipo de falla para habilitar este campo.
                    </p>
                    <select
                        id="falla"
                        name="falla"
                        class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        aria-describedby="ayuda-falla error-falla"
                        disabled
                    >
                        <option value="">Seleccione primero un tipo de falla</option>
                    </select>
                    <p id="error-falla" aria-live="polite" class="text-xs md:text-sm text-red-700"></p>
                </div>
            </div>

            <!-- Obs - Ancho completo -->
            <div class="mt-3 md:mt-4">
                <label for="obs" class="block text-xs md:text-sm font-medium text-gray-700">Observaciones</label>
                <textarea
                    id="obs"
                    name="obs"
                    rows="2"
                    class="w-full px-2 py-1.5 md:px-3 md:py-2 text-xs md:text-sm border-2 border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none outline-none"
                ></textarea>
            </div>

            <!-- Botones -->
            <div class="grid grid-cols-2 gap-3 md:gap-4 mt-2 md:mt-3 pt-2 md:pt-3 border-t border-gray-200">
                <a
                    href="{{ route('produccion.index') }}"
                    id="btn-cancelar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-white hover:bg-gray-50 text-gray-700 text-sm md:text-base font-medium rounded-md transition-colors border-2 border-gray-300 text-center"
                >
                    Cancelar
                </a>
                <button
                    type="submit"
                    id="btn-aceptar"
                    class="px-4 py-2.5 md:px-6 md:py-3 bg-blue-600 hover:bg-blue-700 text-white text-base md:text-lg font-semibold rounded-md transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Reportar
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
    @vite('resources/js/modulos/mantenimiento/nuevo-paro/index.ts')
@endpush
