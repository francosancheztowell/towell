@extends('layouts.app')

@section('page-title', 'Reporte de Fallos y Paros')

@section('content')
<div class="w-full">
    <div class="bg-white  p-6">
        <!-- Contenedor principal con tabla y botones -->
        <div class="flex gap-4">
            <!-- Tabla -->
            <div class="flex-1 overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300 text-xs md:text-sm">
                    <!-- Encabezados -->
                    <thead>
                        <tr class="bg-blue-500 text-white text-center ">
                            <th class="px-2 py-2  font-semibold ">Folio</th>
                            <th class="px-2 py-2  font-semibold  ">Estatus</th>
                            <th class="px-2 py-2  font-semibold  ">Fecha</th>
                            <th class="px-2 py-2  font-semibold ">Hora</th>
                            <th class="px-2 py-2  font-semibold ">Depto</th>
                            <th class="px-2 py-2  font-semibold  ">Maquina</th>
                            <th class="px-2 py-2  font-semibold  ">Tipo Falla</th>
                            <th class="px-2 py-2  font-semibold  ">Falla</th>
                            <th class="px-2 py-2  font-semibold ">Hora Fin</th>
                            <th class="px-2 py-2  font-semibold ">Atendio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Fila 1 -->
                        <tr>
                            <td class="border border-gray-300 px-2 py-2 text-gray-900">Nuevo Consecutivo "PF"</td>
                            <td class="border border-gray-300 px-2 py-2 text-gray-900">Solo debe mostrar Activos</td>
                            <td class="border border-gray-300 px-2 py-2"></td>
                            <td class="border border-gray-300 px-2 py-2"></td>
                            <td class="border border-gray-300 px-2 py-2"></td>
                            <td class="border border-gray-300 px-2 py-2"></td>
                            <td class="border border-gray-300 px-2 py-2"></td>
                            <td class="border border-gray-300 px-2 py-2"></td>
                            <td class="border border-gray-300 px-2 py-2"></td>
                            <td class="border border-gray-300 px-2 py-2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Botones -->
            <div class="flex flex-col gap-3">
                <button
                    type="button"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors whitespace-nowrap"
                >
                    Nuevo
                </button>
                <button
                    type="button"
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors whitespace-nowrap"
                >
                    Terminar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

