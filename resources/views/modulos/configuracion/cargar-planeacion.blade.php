@extends('layouts.app')

@section('page-title', 'Cargar Planeación - Programa Tejido')

@section('content')
<div class="w-full">
    <!-- Panel principal blanco -->
    <div class="bg-white shadow-xl overflow-hidden rounded-2xl mt-1">
        <div class="p-4">
            <form id="excelForm" enctype="multipart/form-data" class="max-w-2xl mx-auto"
                  data-ruta-upload="{{ route('configuracion.cargar.planeacion.upload') }}"
                  data-ruta-update="{{ route('configuracion.utileria.cargar-planeacion.upload-update') }}"
                  data-ruta-programa="{{ route('catalogos.req-programa-tejido') }}">
                @csrf
                <!-- Zona de carga de archivo -->
                <div class="mb-4">
                    <div class="border-2 border-dashed border-green-700 rounded-lg p-4 text-center hover:border-blue-400 transition-colors">
                        <i class="fas fa-file-excel text-green-600 mr-2 text-2xl"></i>
                        <div class="mt-2">
                            <label for="excel_file" class="cursor-pointer">
                                <span class="block text-sm font-medium text-gray-900">
                                    Arrastra y suelta tu archivo Excel aquí
                                </span>
                                <span class="block text-xs text-gray-500">
                                    o haz click para seleccionar
                                </span>
                                <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" class="sr-only" required>
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            Formatos soportados: .xlsx, .xls (Máximo 10MB)
                        </p>
                    </div>
                </div>

                <!-- Información del archivo seleccionado -->
                <div id="file-info" class="hidden mb-4 p-3 bg-blue-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-file-excel text-green-600 mr-2"></i>
                        <div>
                            <p class="text-sm font-medium text-gray-900" id="file-name"></p>
                            <p class="text-xs text-gray-500" id="file-size"></p>
                        </div>
                    </div>
                </div>

                <!-- Modo de importación -->
                <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" id="modoActualizacion" name="modo_actualizacion" class="mt-1 mr-2 w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <div>
                            <span class="text-sm font-medium text-gray-900">Modo Actualización</span>
                            <p class="text-xs text-gray-600 mt-1">
                                Si está marcado, solo actualizará registros existentes sin eliminar los demás. 
                                Recalculará fechas y EnProceso para cada telar basándose en el registro que inicia primero.
                            </p>
                        </div>
                    </label>
                </div>

                <!-- Instrucciones compactas -->
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-900 mb-2">Instrucciones:</h3>
                    <div class="">
                        <ul class="space-y-1 text-xs text-gray-700">
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-1 mt-0.5 text-xs"></i>
                                <span>Archivo debe contener columnas: Salón, Telar, Nombre, Clave Mod., etc.</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-1 mt-0.5 text-xs"></i>
                                <span>Primera fila debe contener encabezados de columna</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-1 mt-0.5 text-xs"></i>
                                <span id="modo-texto">Datos se importarán a la tabla ReqProgramaTejido (se eliminarán registros existentes)</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-center space-x-3">
                    <button type="button" data-volver
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-1"></i>
                        Volver
                    </button>
                    <button type="submit" id="uploadBtn"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-upload mr-1"></i>
                        Procesar Excel
                    </button>
                </div>
            </form>

            <!-- Barra de progreso (oculta por defecto) -->
            <div id="progress-section" class="hidden mt-4">
                <div class="bg-gray-200 rounded-full h-2">
                    <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p id="progress-text" class="text-xs text-gray-600 mt-1 text-center">Procesando archivo...</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    @vite('resources/js/modulos/configuracion/cargar-planeacion/index.ts')
@endpush
