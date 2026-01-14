<!-- resources/views/modelos/index.blade.php -->
@extends('layouts.app')

@section('content')
    <div class="container mx-auto">
        <h1 class="text-3xl font-bold text-center -mt-6">LISTA DE MODELOS</h1>

        <div class="flex justify-between items-center w-full -mt-5 mb-1">
            <!--FILTROS DE BÚSQUEDA ***************************************************************************************************************-->
            <div class="flex items-center space-x-2 -mt-2">
                <!-- Botón de búsqueda (lupa) -->
                <button id="search-toggle" class="p-1 w-16 rounded-full bg-blue-500 text-white hover:bg-blue-600">
                    <i class="fas fa-search text-2xl"></i>
                </button>

                <!-- Botón de restablecer (cruz o refresh) -->
                <div class="w-auto text-left">
                    <button id="reset-search"
                        class="p-2 rounded-full bg-green-500 text-black font-bold hover:bg-green-600 text-xs">
                        {{ strtoupper('Restablecer bÚsqueda') }}
                    </button>
                </div>
                <div class="w-auto text-left">
                    <a href="{{ route('modelos.create') }}"
                        class="p-2 w-20 rounded-full bg-blue-500 text-white hover:bg-blue-600  text-xs">NUEVO 🆕
                    </a>
                </div>
                <div class="w-auto text-left" id="editar-modelo-btn">
                    <a href="{{ route('planeacion.aplicaciones') }}"
                        class="p-2  w-20 rounded-full bg-blue-500 text-white hover:bg-blue-600  text-xs">EDITAR 📝
                    </a>
                </div>
                <button id="btnDelete" class="p-1 w-16 rounded-full bg-red-500 text-white hover:bg-red-600 ">
                    <i class="fas fa-trash text-2xl"></i>

                </button>
            </div>
            @php
                $totalPages = ceil($total / $perPage);
                $startPage = max(1, $currentPage - 5);
                $endPage = min($totalPages, $currentPage + 5);
            @endphp

            <nav class="">
                <ul class="pagination">
                    {{-- Botón Anterior --}}
                    @if ($currentPage > 1)
                        <li class="page-item">
                            <a class="page-link"
                                href="{{ request()->fullUrlWithQuery(['page' => $currentPage - 1]) }}">Anterior</a>
                        </li>
                    @endif

                    {{-- Botones de página visibles --}}
                    @for ($i = $startPage; $i <= $endPage; $i++)
                        <li class="page-item {{ $currentPage == $i ? 'active' : '' }}">
                            <a class="page-link"
                                href="{{ request()->fullUrlWithQuery(['page' => $i]) }}">{{ $i }}</a>
                        </li>
                    @endfor

                    {{-- Botón Siguiente --}}
                    @if ($currentPage < $totalPages)
                        <li class="page-item">
                            <a class="page-link"
                                href="{{ request()->fullUrlWithQuery(['page' => $currentPage + 1]) }}">Siguiente</a>
                        </li>
                    @endif
                </ul>
            </nav>
        </div>


        <!-- Modal -->
        <div id="search-modal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
            <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-lg">
                <h2 class="text-xl font-bold mb-4">Búsqueda Avanzada</h2>

                <form action="{{ route('modelos.index') }}" method="GET" class="flex flex-col gap-4">
                    <!-- Select para escoger la primera columna -->
                    <div class="flex gap-4 items-center">
                        <select name="column[]"
                            class="form-control p-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Selecciona una columna</option>
                            @foreach ($fillableFields as $field)
                                <option value="{{ $field }}"> {{ str_replace('_', ' ', $field) }} </option>
                            @endforeach
                        </select>
                        <input type="text" name="value[]"
                            class="form-control p-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Valor a buscar">
                    </div>

                    <!-- Contenedor para filtros adicionales -->
                    <div id="additional-filters" class="max-h-60 overflow-y-auto p-2 border border-gray-300 rounded-lg">
                    </div>


                    <!-- Botón para agregar más filtros -->
                    <!-- Botón para agregar más filtros -->
                    <button type="button" id="add-filter"
                        class="w-1/3 block mx-auto bg-gray-700 text-white px-3 py-1.5 rounded-md hover:bg-gray-800 transition duration-300 text-sm shadow">
                        Agregar Otro Filtro
                    </button>

                    <!-- Botón de buscar -->
                    <button type="submit"
                        class="block mx-auto w-1/5  bg-blue-600 text-white px-3 py-1.5 rounded-md hover:bg-blue-700 transition duration-300 shadow">
                        Buscar
                    </button>
                </form>

                <!-- Botón para cerrar el modal -->
                <button id="close-modal"
                    class="block mx-auto w-1/5 mt-4 px-3 py-1.5 bg-red-500 text-white rounded-lg hover:bg-red-600">Cerrar</button>
            </div>
        </div>

        <!--FIN DE FILTROS DE BÚSQUEDA ***************************************************************************************************************-->
        <!--inicia tabla MODELOS-->
        <div class="overflow-x-auto overflow-y-auto bigScroll table-container-plane table-wrapper bg-white shadow-lg rounded-lg p-1"
            style="max-height: calc(100vh - 100px);">
            <table id="tablonMODELOS" class="min-w-full celP plane-table border border-gray-300 text-center">
                <thead>
                    <tr class="plane-thead-tr text-white text-xs">
                        @foreach ($fillableFields as $field)
                            <th class="sticky top-0 z-10 px-2 py-1 whitespace-nowrap" data-field="{{ $field }}"
                                title="{{ $labelMap[$field] }}">
                                {{ $labelMap[$field] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="text-xs">
                    @foreach ($modelos as $modelo)
                        <tr data-CONCATENA={{ $modelo->CONCATENA }} data-clave-ax="{{ $modelo->CLAVE_AX }}"
                            data-tamanio-ax="{{ $modelo->Tamanio_AX }}">
                            @foreach ($fillableFields as $field)
                                @php
                                    $value = $modelo->$field;

                                    // Campos que necesitan quitar ".0"
                                    $fieldsSinDecimal = ['RASEMA', 'Clave_Modelo', 'CUENTA', 'PASADAS', 'Telar_Actual'];

                                    // Campos de fecha
                                    $fieldsFecha = ['Fecha_Orden', 'Fecha_Cumplimiento', 'Fecha_Compromiso'];

                                    // Campos que requieren 2 decimales
                                    $fieldsDosDecimales = [
                                        'C1_Trama_de_Fondo',
                                        'No_De_Marbetes',
                                        'Tra',
                                        'Rizo',
                                        'No#_De_Marbetes',
                                        'C11',
                                        'C21',
                                        'Hilo5',
                                        'Hilo6',
                                        'KG_x_Dia',
                                        'Densidad',
                                        'Pzas_Dia__pasadas',
                                        'Pzas_Día_formula',
                                        'DIF',
                                        'Rev',
                                        'TIRAS1',
                                        'PASADAS5',
                                        'A',
                                        'B',
                                        'C',
                                        'COMPROBAR modelos duplicados',
                                        'C1_A_1',
                                        'Hilo_A_3',
                                        'C3_A_3',
                                        'Hilo_A_4',
                                        'KG_p_dia',
                                        'Pzas_p_dia_pasadas',
                                        'Pzas_p_dia_formula',
                                        'DIF',
                                        'EFIC',
                                        'Rev',
                                        'TIRAS_2',
                                        'PASADAS',
                                        'CU',
                                        'CV',
                                        'CW',
                                    ];

                                    // Campo que requiere 3 decimales
                                    $fieldsTresDecimales = ['Hilo4'];

                                    // Formatear según el campo
                                    if (in_array($field, $fieldsSinDecimal)) {
                                        if ($value === null || $value === '' || $value == '0' || $value == 0.0) {
                                            $fieldsSinDecimal = '';
                                        } else {
                                            $value = is_numeric($value) ? decimales($value) : $value;
                                        }
                                    } elseif (in_array($field, $fieldsFecha)) {
                                        $value = \Carbon\Carbon::parse($value)->format('d-m-y');
                                    } elseif (in_array($field, $fieldsDosDecimales)) {
                                        $value = is_numeric($value) ? number_format($value, 2) : $value;
                                    } elseif (in_array($field, $fieldsTresDecimales)) {
                                        $value = is_numeric($value) ? number_format($value, 3) : $value;
                                    }
                                @endphp
                                <td class="border border-gray-300 px-2 py-1">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

    <script>
        // Mostrar modal
        document.getElementById('search-toggle').addEventListener('click', function() {
            document.getElementById('search-modal').classList.remove('hidden');
        });

        // Cerrar modal
        document.getElementById('close-modal').addEventListener('click', function() {
            document.getElementById('search-modal').classList.add('hidden');
        });

        // Agregar más filtros
        document.getElementById('add-filter').addEventListener('click', function() {
            const newFilter = `
            <div class="flex gap-4 items-center mt-4 bg-gray-100 p-3 rounded-lg shadow-md">
                <select name="column[]" class="form-control p-2 border border-gray-400 rounded-md text-gray-800 focus:ring-2 focus:ring-blue-400">
                    <option value="">Selecciona una columna</option>
                        @foreach ($fillableFields as $field)
                            <option value="{{ $field }}">        {{ str_replace('_', ' ', $field) }}      </option>
                        @endforeach
                </select>

                <input type="text" name="value[]" class="form-control p-2 border border-gray-400 rounded-md text-gray-800 focus:ring-2 focus:ring-blue-400" placeholder="Ingrese el valor">

                <button type="button" class="w-1/5 remove-filter bg-gray-700 text-white px-3 py-1.5 rounded-md hover:bg-red-600 transition" onclick="removeFilter(this)">
                    X
                </button>
            </div>
        `;
            document.getElementById('additional-filters').insertAdjacentHTML('beforeend', newFilter);
        });

        // Eliminar filtro
        function removeFilter(button) {
            button.parentElement.remove();
        }
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("reset-search").addEventListener("click", function() {
                window.location.href =
                    "{{ route('modelos.index') }}"; // Redirige a la ruta planificacion.index
            });
        });
    </script>
    <!-- SWEET ALERT -->
    <script>
        @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: "{{ session('success') }}",
                confirmButtonColor: '#3085d6',
                timer: 1800,
                showConfirmButton: false
            });
        @endif
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabla = document.getElementById('tablonMODELOS');
            let selectedRow = null;

            tabla.querySelectorAll('tbody tr').forEach(function(fila) {
                // Al hacer clic, marcar como seleccionada y desmarcar las demás
                fila.addEventListener('click', function() {
                    // Quitar la clase de seleccionada a todas
                    tabla.querySelectorAll('tbody tr').forEach(f => f.classList.remove(
                        'selected-row'));
                    // Poner la clase solo a la fila seleccionada
                    fila.classList.add('selected-row');
                    selectedRow = fila;

                    // Obtener los datos de la fila
                    const clave_ax = fila.getAttribute('data-clave-ax');
                    const tamanio_ax = fila.getAttribute('data-tamanio-ax');
                    const concatena = fila.getAttribute('data-CONCATENA');

                    // Aquí puedes hacer más cosas con los datos si necesitas
                });
            });
        });
    </script>
    <script>
        let selectedClaveAx = null;
        let selectedTamanioAx = null;

        // Al seleccionar una fila, guarda los datos en variables globales
        document.querySelectorAll('#tablonMODELOS tbody tr').forEach(fila => {
            fila.addEventListener('click', function() {
                selectedClaveAx = fila.getAttribute('data-clave-ax');
                selectedTamanioAx = fila.getAttribute('data-tamanio-ax');
            });
        });

        // Al dar clic en el botón editar
        document.getElementById('editar-modelo-btn').addEventListener('click', function(e) {
            e.preventDefault(); // Evita el enlace por default

            if (!selectedClaveAx || !selectedTamanioAx) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Selecciona un registro',
                    text: 'Por favor, seleccione un registro de la tabla para editar.',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            // Aquí defines la ruta edit (ajusta a tu ruta real si cambian los nombres)
            // Supongamos que la ruta es: /modelos/{clave_ax}/{tamanio_ax}/edit
            const url =
                `/modelos/${encodeURIComponent(selectedClaveAx)}/${encodeURIComponent(selectedTamanioAx)}/edit`;

            // Abre la edición en la misma página, o usa window.open(url, '_blank') para nueva pestaña
            window.location.href = url;
        });
    </script>
    <!--DELETE un registro tomando en cuenta su valor en CONCATENA-->
    <script>
        let selectedConcatena = null;

        // Al seleccionar una fila, guarda los datos en variables globales
        document.querySelectorAll('#tablonMODELOS tbody tr').forEach(fila => {
            fila.addEventListener('click', function() {
                selectedConcatena = fila.getAttribute('data-CONCATENA');
            });
        });

        document.getElementById('btnDelete').addEventListener('click', function(e) {
            e.preventDefault(); // Evita el enlace por default


            if (!selectedConcatena) {
                Swal.fire({
                    icon: 'warning',
                    title: 'SELECCIONE UN REGISTRO',
                    text: 'Por favor, seleccione un registro de la tabla para eliminar.',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: 'CONFIRMACIÓN',
                text: '¿Estás seguro de eliminar el registro?',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    const url = `/modelos/${encodeURIComponent(selectedConcatena)}`;

                    axios.delete(url, {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content')
                            }
                        })
                        .then(function(response) {
                            Swal.fire({
                                icon: 'success',
                                title: 'ELIMINADO EXITOSAMENTE',
                                text: 'Se ha eliminado el registro selccionado.'
                            }).then(() => {
                                // Recarga la tabla, la página, o redirige, según lo que tú quieras:
                                window.location.href = '/modelos';
                            });
                        })
                        .catch(function(error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'No se pudo eliminar el modelo.'
                            });
                        });
                }
            });
        });
    </script>

    @push('styles')
        <style>
            #tablonMODELOS tbody tr:hover {
                background-color: #fff59d !important;
                /* Amarillo claro al pasar el mouse */
                cursor: pointer;
            }

            #tablonMODELOS tbody tr.selected-row {
                background-color: #ffd600 !important;
                /* Amarillo fuerte al seleccionar */
            }
        </style>
    @endpush
@endsection
