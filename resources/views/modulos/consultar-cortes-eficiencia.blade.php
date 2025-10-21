@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
<div class="container mx-auto">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
        <div class="bg-blue-500 px-6 py-4 border-t-4 border-orange-400">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-white">Consultar Cortes de Eficiencia</h1>
                <div class="flex space-x-2">
                    <a href="/modulo-cortes-de-eficiencia" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nuevo Corte
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Cortes de Eficiencia -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Folio</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Turno</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Usuario</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <!-- Ejemplo de datos estáticos - después se puede conectar con BD -->
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 border-r border-gray-200">CE001</td>
                        <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">15/01/2025</td>
                        <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">Turno 1</td>
                        <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">Usuario Actual</td>
                        <td class="px-4 py-3 text-sm border-r border-gray-200">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Finalizado</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div class="flex space-x-2">
                                <button class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 border-r border-gray-200">CE002</td>
                        <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">14/01/2025</td>
                        <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">Turno 2</td>
                        <td class="px-4 py-3 text-sm text-gray-900 border-r border-gray-200">Usuario Actual</td>
                        <td class="px-4 py-3 text-sm border-r border-gray-200">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">En Proceso</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div class="flex space-x-2">
                                <button class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mensaje cuando no hay cortes -->
    <div class="bg-white rounded-lg shadow-md p-8 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
        </svg>
        <h3 class="text-lg font-semibold text-gray-600 mb-2">No hay cortes de eficiencia registrados</h3>
        <p class="text-gray-500">Haz clic en "Nuevo Corte" para crear el primer corte de eficiencia</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Funcionalidad básica para los botones de acción
    document.addEventListener('DOMContentLoaded', function() {
        // Aquí se puede agregar funcionalidad JavaScript para:
        // - Ver detalles del corte
        // - Editar cortes existentes
        // - Filtrar por fecha, turno, etc.
        console.log('Consultar Cortes de Eficiencia cargado');
    });
</script>
@endsection
