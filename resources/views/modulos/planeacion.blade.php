@extends('layouts.app')

@section('menu-planeacion')
    <div class="relative" style="position: absolute; left: 200px;">
        <button id="btnReportes"
            class="bg-gray-800 text-white font-bold px-4 py-1 rounded-md shadow hover:bg-gray-700 transition-all duration-200 cursor-pointer text-xs">
            REPORTES
        </button>
        <div id="menuReportes"
            class="hidden absolute bg-white border border-gray-300 mt-1 w-40 rounded-md shadow-lg z-[99] transition transform scale-95 opacity-0"
            style="left: 0px;">
            <a href="{{ route('reportes.consumo') }}" class="block px-3 py-2 text-xs text-gray-800 hover:bg-gray-100">📈
                CONSUMO</a>
            <a href="{{ route('reportes.run') }}" class="block px-3 py-2 text-xs text-gray-800 hover:bg-gray-100">🧵 RUN</a>
            <a href="{{ route('reportes.resumen.tejido') }}"
                class="block px-3 py-2 text-xs text-gray-800 hover:bg-gray-100">📅 RESUMEN
                TEJIDO</a>
            <a href="{{ route('reportes.aplicaciones') }}"
                class="block px-3 py-2 text-xs text-gray-800 hover:bg-gray-100">📊
                APLICACIONES</a>
            <a href="{{ route('reportes.rasurado') }}" class="block px-3 py-2 text-xs text-gray-800 hover:bg-gray-100">🔍
                RASURADO</a>
            <a href="{{ route('reportes.peso.por.mod') }}"
                class="block px-3 py-2 text-xs text-gray-800 hover:bg-gray-100">🔍 PESO X
                MOD.</a>
            <a href="{{ route('reportes.peso.tenido') }}" class="block px-3 py-2 text-xs text-gray-800 hover:bg-gray-100">🔍
                P-TEÑIDO</a>
        </div>
    </div>
@endsection

@section('content')
    <div class="container mx-auto px-4 py-6">
        <!-- Header usando componente reutilizable -->
        <x-produccion-proceso-header titulo="Planeación" />
    </div>

    <div
        class="w-auto text-white text-xs mt-2 mb-1 flex  space-x-2 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-gray-300 p-1 mt-1 sm:mt-2 sm:p-0">
        <!-- Botón de búsqueda (lupa) -->
        <button id="search-toggle" class="w-16 rounded-full text-white hover:bg-white sm:mt-8 flex">
            <span style="font-size: 34px;">🔎</span>
        </button>
        <!--SEGUNDO CONTENEDOR para botones-->
        <!-- Botones alineados a la derecha -->
        <a href="#" id="reset-search"
            class="text-xs bg-red-500 ml-1 rounded-full sm:mt-8 flex font-bold px-4 py-2">RESTABLECER
            BÚSQUEDA 🧹</a>

        <div class=" bg-blue-500 ml-1 rounded-full p-1 sm:mt-8 relative">
            <button id="btnCatalogos"
                class=" text-white font-bold px-4 py-1 rounded-md shadow hover:bg-blue-700 transition-all duration-200 cursor-pointer text-xs">
                CATÁLOGOS 📚
            </button>
            <div id="menuCatalogos"
                class="hidden absolute bg-white border border-gray-300 mt-1 w-40 rounded-md shadow-lg z-[99] transition transform scale-95 opacity-0"
                style="left: 0px;">
                <a href="{{ route('telares.index') }}" class=" button-plane ml-1 rounded-full p-1  mt-1 flex">TELARES
                    📑</a>
                <a href="{{ route('eficiencia.index') }}" class="button-plane rounded-full ml-1 p-1 mt-1 flex">EFICIENCIA
                    STD
                    📑</a>
                <a href="{{ route('velocidad.index') }}" class="button-plane rounded-full ml-1 p-1 mt-1 ">VELOCIDAD STD
                    📑</a>
                <a href="{{ route('calendariot1.index') }}" class="button-plane rounded-full ml-1 p-1 mt-1">CALENDARIOS
                    🗓️</a>
                <a href="{{ route('planeacion.aplicaciones') }}"
                    class="button-plane rounded-full ml-2 p-1 mt-1">APLICACIONES
                    🧩</a>
                <a href="{{ route('modelos.index') }}" class="button-plane-2 rounded-full ml-1 p-1 mt-1">MODELOS 🛠️</a>
            </div>
        </div>

        <div class=" bg-green-500 ml-1 rounded-full p-1 sm:mt-8 relative">
            <button id="btnNuevo"
                class=" text-white font-bold px-4 py-1 rounded-md shadow hover:bg-green-700 transition-all duration-200 cursor-pointer text-xs">
                NUEVO 💡
            </button>
            <div id="menuNuevo"
                class="hidden absolute bg-white border border-gray-300 mt-1 w-40 rounded-md shadow-lg z-[99] transition transform scale-95 opacity-0"
                style="left: 0px;">
                <button id="btnCompras"
                    class="bg-yellow-400 hover:bg-yellow-200 rounded-full ml-1 p-1 mt-1 button-plane w-36 text-black">
                    ALTAS C. E. 🛒
                </button>
                <button id="btnUnico" class="button-plane rounded-full ml-1 p-1 mt-1 w-36">NUEVO REGISTRO 📝</button>
                <button id="btnEditar" class="button-plane rounded-full ml-1 p-1 mt-1 w-36">EDITAR
                    🛠️</button><!--VISTA EDICION y METODO EN CONTROLLER pendientes-->
                <button id="btnPronosticos"
                    class="bg-teal-400 hover:bg-blue-300 rounded-full ml-1 p-1 mt-1 button-plane w-36 text-black">ALTA DE
                    PRONÓSTICOS
                </button>
                <button id="btnImportExcel"
                    class="bg-green-500 hover:bg-green-300 rounded-full ml-1 p-1 mt-1 button-plane w-36">EXCEL
                </button>
            </div>
        </div>

        <button
            class="w-[40px] h-[40px] rounded-full p-1 items-center justify-center text-2xl hover:bg-blue-100 focus:ring-2 mt-6"
            onclick="moverArriba()">🔼</button>
        <button
            class="w-[40px] h-[40px] rounded-full p-1 items-center justify-center text-2xl hover:bg-blue-100 focus:ring-2 mt-6"
            onclick="moverAbajo()">🔽</button>
    </div>

    <!-- Modal -->
    <div id="search-modal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-lg">
            <h2 class="text-xl font-bold mb-4">Búsqueda Avanzada</h2>

            <form action="{{ route('planeacion.index') }}" method="GET" class="flex flex-col gap-4">
                <!-- Select para escoger la primera columna -->
                <div class="flex gap-4 items-center">
                    <select name="column[]"
                        class="form-control p-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecciona una columna</option>
                        @foreach ($headers as $header)
                            <option value="{{ $header }}">{{ $header }}</option>
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

    <div class="mx-auto text-sm ">
        <div class="table-container relative">
            <div class="table-container-plane table-wrapper bg-white shadow-lg rounded-lg p-1
            bigScroll">
                <table id="tablaPlaneacion" class="celP plane-table border border-gray-300">
                    <thead>
                        <tr class="plane-thead-tr text-white">
                            @php
                                $headers = [
                                    'en_proceso',
                                    'Cuenta',
                                    'Salon',
                                    'Telar',
                                    'Ultimo',
                                    'Cambios_Hilo',
                                    'Maquina',
                                    'Ancho',
                                    'Eficiencia_Std',
                                    'Velocidad_STD',
                                    'Hilo',
                                    'Calibre_Rizo',
                                    'Calibre_Pie',
                                    'Calendario',
                                    'Clave_Estilo', // <-- descomentado aquí
                                    'Clave_AX',
                                    'Tamano_AX',
                                    'Estilo_Alternativo',
                                    'Nombre_Producto',
                                    'Saldos',
                                    'cantidad', // <-- aquí va cantidad, después de Saldos
                                    'Fecha_Captura',
                                    'Orden_Prod',
                                    'Fecha_Liberacion',
                                    'Id_Flog',
                                    'Descrip',
                                    'Aplic',
                                    'Obs',
                                    'Tipo_Ped',
                                    'Tiras',
                                    'Peine',
                                    'Largo_Crudo',
                                    'Peso_Crudo',
                                    'Luchaje',
                                    'CALIBRE_TRA',
                                    'Dobladillo',
                                    'PASADAS_TRAMA',
                                    'PASADAS_C1',
                                    'PASADAS_C2',
                                    'PASADAS_C3',
                                    'PASADAS_C4',
                                    'PASADAS_C5',
                                    'ancho_por_toalla',
                                    'COLOR_TRAMA',
                                    'CALIBRE_C1',
                                    'Clave_Color_C1',
                                    'COLOR_C1',
                                    'CALIBRE_C2',
                                    'Clave_Color_C2',
                                    'COLOR_C2',
                                    'CALIBRE_C3',
                                    'Clave_Color_C3',
                                    'COLOR_C3',
                                    'CALIBRE_C4',
                                    'Clave_Color_C4',
                                    'COLOR_C4',
                                    'CALIBRE_C5',
                                    'Clave_Color_C5',
                                    'COLOR_C5',
                                    'Plano',
                                    'Cuenta_Pie',
                                    'Clave_Color_Pie',
                                    'Color_Pie',
                                    'Peso_gr_m2',
                                    'Dias_Ef',
                                    'Prod_Kg_Dia',
                                    'Std_Dia',
                                    'Prod_Kg_Dia1',
                                    'Std_Toa_Hr_100',
                                    'Dias_jornada_completa',
                                    'Horas',
                                    'Std_Hr_efectivo',
                                    'Inicio_Tejido',
                                    'Calc4',
                                    'Calc5',
                                    'Calc6',
                                    'Fin_Tejido',
                                    'Fecha_Compromiso',
                                    'Fecha_Compromiso1',
                                    'Entrega',
                                    'Dif_vs_Compromiso',
                                    'id',
                                ];

                            @endphp

                            @foreach ($headers as $index => $header)
    @if ($header !== 'id')
        <th class="plane-th border pt-2 pr-4 pb-4 pl-4 relative"
            data-index="{{ $index }}">
            {{ $header }}
            <div class="absolute top-6 right-0 flex">
                <button class="toggle-column bg-red-500 text-white text-xs px-0.5 py-0.5"
                    data-index="{{ $index }}">⛔</button>
                <button class="pin-column bg-blue-500 text-white text-xs px-0.5 py-0.5 ml-0.5"
                    data-index="{{ $index }}">📌</button>
            </div>
        </th>
    @endif @endforeach

                        </tr>
                    </thead>
                    <tbody class="">
                        @foreach ($datos as $registro)
                            <tr class="px-1 py-0.5 text-sm" data-num-registro="{{ $registro->id }}"
                                data-inicio="{{ $registro->Inicio_Tejido }}" data-fin="{{ $registro->Fin_Tejido }}"
                                onclick="seleccionarRegistro({{ $registro->id }}, '{{ $registro->Telar }}'
                                , this)">
                                <!-- Agregar checkbox 'en_proceso' -->
                                <td class="px-1 py-0.5">
                                    <form action="{{ route('tejido_scheduling.update', $registro->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <input type="checkbox" name="en_proceso" value="1"
                                            {{ $registro->en_proceso ? 'checked' : '' }} onclick="this.form.submit()">
                                    </form>
                                </td>
                                @foreach ($headers as $header)
                                    @if ($header !== 'en_proceso' && $header !== 'id')
                                        {{-- Aquí va tu lógica y el <td> --}}
                                        @php
                                            $value = $registro->$header;
                                            if ($header === 'Hilo') {
                                                $formattedValue = $value;
                                            } elseif ($header === 'Eficiencia_Std') {
                                                $formattedValue = intval($value * 100);
                                            } else {
                                                $camposConHora = ['Inicio_Tejido', 'Calc5', 'Fin_Tejido', 'Entrega'];
                                                if (is_numeric($value)) {
                                                    if (intval($value) == $value) {
                                                        $formattedValue = intval($value);
                                                    } else {
                                                        $formattedValue = number_format($value, 2, '.', '');
                                                    }
                                                } elseif (strtotime($value) && !in_array($header, ['Calibre_Rizo'])) {
                                                    if (in_array($header, $camposConHora)) {
                                                        $formattedValue = \Carbon\Carbon::parse($value)->format(
                                                            'd-m-Y H:i:s',
                                                        );
                                                    } else {
                                                        $formattedValue = \Carbon\Carbon::parse($value)->format(
                                                            'd-m-Y',
                                                        );
                                                    }
                                                } else {
                                                    $formattedValue = $value;
                                                }
                                            }
                                        @endphp
                                        @if (is_numeric($value) && intval($value) != $value && $header !== 'Hilo' && $header !== 'Eficiencia_Std')
                                            <td class="small px-1 py-0.5 dec-hidden"
                                                data-valor="{{ number_format($value, 2, '.', '') }}">
                                                {{ intval($value) }}
                                            </td>
                                        @else
                                            <td class="small px-1 py-0.5">{{ $formattedValue }}</td>
                                        @endif
                                    @endif
                                @endforeach

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div id="contenedorTabla2" class="text-center" style="display: none; max-height: 300px; overflow-y: auto;">
            <div class="table-wrapper bg-white shadow-lg rounded-lg p-1 ">
                <table id="tablaDatosPlaneacion" class="w-full border border-gray-300 text-xs table-fixed">
                    <thead>
                        <tr class="bg-gray-800 text-white text-center">
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm w-24">Fecha</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Pzas</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Kilos</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Rizo</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Cambio</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Trama</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Combinacion1</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Combinacion2</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Combinacion3</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Combinacion4</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Pie1</th>
                            <th class="border border-gray-400 px-2 py-1 font-semibold text-sm">Riso</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTablaPlaneacion">
                        <!-- Se generará dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!--SCRIPT que sirve para ocultar y fijar columnas de la tabla-->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".toggle-column").forEach(button => {
                button.addEventListener("click", function() {
                    let index = this.getAttribute("data-index");
                    document.querySelectorAll(
                        `th:nth-child(${+index + 1}), td:nth-child(${+index + 1})`).forEach(
                        el => {
                            el.classList.toggle("hidden");
                        });
                });
            });

            document.querySelectorAll(".pin-column").forEach(button => {
                button.addEventListener("click", function() {
                    let index = parseInt(this.getAttribute("data-index")) +
                        1; // Ajuste por nth-child
                    let columnCells = document.querySelectorAll(
                        `th:nth-child(${index}), td:nth-child(${index})`);

                    // Verificar si la columna ya está fijada
                    let isPinned = columnCells[0].classList.contains("sticky");

                    if (isPinned) {
                        // Si está fijada, quitar clases y restaurar
                        columnCells.forEach(el => {
                            el.classList.remove("sticky", "z-10", "shadow-md");
                            el.style.left = "";
                            el.style.backgroundColor = ""; // Restaurar fondo
                        });
                    } else {
                        // Obtener el ancho acumulado de las columnas fijas previas
                        let pinnedColumns = document.querySelectorAll("th.sticky");
                        let leftOffset = 0;
                        pinnedColumns.forEach(col => {
                            leftOffset += col.offsetWidth;
                        });

                        // Fijar la columna con estilos adecuados
                        columnCells.forEach(el => {
                            el.classList.add("sticky", "z-10", "shadow-md");
                            el.style.left = `${leftOffset}px`;
                            el.style.backgroundColor =
                                "#70bbe1"; // Mantener el fondo visible
                        });
                    }
                });
            });

        });
    </script>

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
                        @foreach ($headers as $header)
                            <option value="{{ $header }}">{{ $header }}</option>
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
            document.getElementById("reset-search").addEventListener("click", function(e) {
                e.preventDefault();
                // Si route() te da la URL absoluta y no limpia, usa esto:
                window.location.href = "{{ route('planeacion.index') }}";
                // Si necesitas forzar una URL completamente limpia:
                // window.location.href = window.location.origin + "{{ route('planeacion.index', [], false) }}";
            });
        });
    </script>
    <!--*******************************************************************************************************************************************************************************************
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                *********************************************************************************************************************************************************************************************-->
    <!--SCRIPTS que implentan el funcionamiento de la tabla TIPO DE MOVIMIENTOS, se selecciona un registro, se obtiene el valor de id y con
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        ese valor se filtran los datos de la tabla tipo_movimientos para mostrarlos en la tabla de abajo-->

    <script>
        let filaSeleccionada = null;
        let numRegistroSeleccionado = null;

        document.addEventListener("DOMContentLoaded", function() {
            const filas = document.querySelectorAll("#tablaPlaneacion tbody tr");

            filas.forEach(fila => {
                fila.addEventListener("click", function() {
                    // Quitar selección anterior
                    if (filaSeleccionada) {
                        filaSeleccionada.classList.remove("fila-seleccionada");
                    }
                    this.classList.add("fila-seleccionada");
                    filaSeleccionada = this;

                    // Obtener datos del registro
                    numRegistroSeleccionado = this.getAttribute('data-num-registro');
                    const fechaInicioTejido = this.getAttribute('data-inicio'); // "2025-05-13"
                    const fechaFinTejido = this.getAttribute('data-fin'); // "2025-05-25"

                    // Mostrar segunda tabla
                    document.getElementById("contenedorTabla2").style.display = "block";

                    // Generar fechas en la tabla dinámica
                    const inicio = new Date(fechaInicioTejido);
                    const fin = new Date(fechaFinTejido);
                    console.log("inicio:", inicio.toISOString(), "fin:", fin.toISOString());


                    const tbody = document.getElementById("cuerpoTablaPlaneacion");
                    tbody.innerHTML = "";

                    for (let d = new Date(inicio.toDateString()), i = 0; d <= new Date(fin
                            .toDateString()); d.setDate(d.getDate() + 1), i++) {

                        function formatFechaLocal(date) {
                            const year = date.getFullYear();
                            const month = String(date.getMonth() + 1).padStart(2,
                                '0'); // meses comienzan en 0
                            const day = String(date.getDate()).padStart(2, '0');
                            return `${year}-${month}-${day}`;
                        }

                        const fechaFormateada = formatFechaLocal(
                            d); // en vez de d.toISOString().split('T')[0]
                        const opciones = {
                            day: 'numeric',
                            month: 'long'
                        };
                        const fechaFormateadaDiaMes = d.toLocaleDateString('es-MX', opciones);

                        const fila = document.createElement("tr");
                        fila.classList.add(i % 2 === 0 ? 'bg-white' : 'bg-gray-100',
                            'text-gray-800');

                        fila.innerHTML = `
                        <td class="border px-2 py-1 text-center" data-campo="fecha">${fechaFormateadaDiaMes}</td>
                        <td class="border px-2 py-1 text-center" data-campo="pzas" data-fecha="${fechaFormateada}"></td>
                        <td class="border px-2 py-1 text-center" data-campo="kilos" data-fecha="${fechaFormateada}"></td>
                        <td class="border px-2 py-1 text-center" data-campo="rizo" data-fecha="${fechaFormateada}"></td>
                        <td class="border px-2 py-1 text-center" data-campo="cambio" data-fecha="${fechaFormateada}"></td>
                        <td class="border px-2 py-1 text-center" data-campo="trama" data-fecha="${fechaFormateada}"></td>
                        <td class="border px-2 py-1 text-center" data-campo="combinacion1" data-fecha="${fechaFormateada}"></td>
                        <td class="border px-2 py-1 text-center" data-campo="combinacion2" data-fecha="${fechaFormateada}"></td>
                        <td class="border px-2 py-1 text-center" data-campo="combinacion3" data-fecha="${fechaFormateada}"></td>
                        <td class="border px-2 py-1 text-center" data-campo="combinacion4" data-fecha="${fechaFormateada}"></td>
                        <td class="border px-2 py-1 text-center" data-campo="piel1" data-fecha="${fechaFormateada}"></td>
                        <td class="border px-2 py-1 text-center" data-campo="riso" data-fecha="${fechaFormateada}"></td>
                    `;

                        tbody.appendChild(fila);
                    }

                    // Cargar datos del servidor
                    fetch(`/planeacion/tipo-movimientos/${numRegistroSeleccionado}`)
                        .then(res => res.json())
                        .then(data => {
                            // Limpiar celdas anteriores
                            document.querySelectorAll('[data-campo][data-fecha]').forEach(
                                cell => {
                                    cell.textContent = "";
                                });
                            data.forEach(item => {
                                //console.log(item);


                                const fecha = item.fecha; // "YYYY-MM-DD"
                                const campos = [
                                    "pzas", "kilos", "rizo", "cambio", "trama",
                                    "combinacion1", "combinacion2",
                                    "combinacion3",
                                    "combinacion4",
                                    "piel1", "riso"
                                ];

                                campos.forEach(campo => {
                                    const celda = document.querySelector(
                                        `[data-campo="${campo}"][data-fecha="${fecha}"]`
                                    );
                                    if (!celda) {
                                        console.warn(
                                            `No se encontró la celda para campo: ${campo}, fecha: ${fecha}`
                                        );
                                    }

                                    if (celda) {
                                        const valor = item[campo];
                                        celda.textContent = typeof valor ===
                                            "number" ? Math.floor(valor)
                                            .toString() : (valor || '0');
                                    }
                                });
                            });
                        })
                        .catch(err => {
                            console.error("Error al obtener detalles:", err);
                        });
                });
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabla = document.getElementById('tablaPlaneacion');

            if (tabla) {
                tabla.addEventListener('click', function(e) {
                    const fila = e.target.closest('tr');
                    if (!fila) return;

                    fila.querySelectorAll('.dec-hidden').forEach(td => {
                        const fullValue = td.getAttribute('data-valor');
                        if (fullValue) {
                            td.textContent = fullValue;
                            td.classList.remove('dec-hidden');
                        }
                    });
                    // Aquí puedes agregar tu lógica para resaltar en amarillo, si no ya existe.
                });
            }
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const tabla = document.getElementById("tablaPlaneacion");
            const botonUnico = document.getElementById("btnUnico");
            let datosFilaSeleccionada = null;

            const nombresColumnas = [
                'en_proceso',
                'Cuenta',
                'Salon',
                'Telar',
                'Ultimo',
                'Cambios_Hilo',
                'Maquina',
                'Ancho',
                'Eficiencia_Std',
                'Velocidad_STD',
                'Hilo',
                'Calibre_Rizo',
                'Calibre_Pie',
                'Calendario',
                'Clave_Estilo',
                'Clave_AX', // <- movido aquí
                'Tamano_AX',
                'Estilo_Alternativo',
                'Nombre_Producto',
                'Saldos',
                'cantidad', // <- después de Saldos
                'Fecha_Captura',
                'Orden_Prod',
                'Fecha_Liberacion',
                'Id_Flog',
                'Descrip',
                'Aplic',
                'Obs',
                'Tipo_Ped',
                'Tiras',
                'Peine',
                'Largo_Crudo',
                'Peso_Crudo',
                'Luchaje',
                'CALIBRE_TRA',
                'Dobladillo',
                'PASADAS_TRAMA',
                'PASADAS_C1',
                'PASADAS_C2',
                'PASADAS_C3',
                'PASADAS_C4',
                'PASADAS_C5',
                'ancho_por_toalla',
                'COLOR_TRAMA',
                'CALIBRE_C1',
                'Clave_Color_C1',
                'COLOR_C1',
                'CALIBRE_C2',
                'Clave_Color_C2',
                'COLOR_C2',
                'CALIBRE_C3',
                'Clave_Color_C3',
                'COLOR_C3',
                'CALIBRE_C4',
                'Clave_Color_C4',
                'COLOR_C4',
                'CALIBRE_C5',
                'Clave_Color_C5',
                'COLOR_C5',
                'Plano',
                'Cuenta_Pie',
                'Clave_Color_Pie',
                'Color_Pie',
                'Peso_gr_m2',
                'Dias_Ef',
                'Prod_Kg_Dia',
                'Std_Dia',
                'Prod_Kg_Dia1',
                'Std_Toa_Hr_100',
                'Dias_jornada_completa',
                'Horas',
                'Std_Hr_efectivo',
                'Inicio_Tejido',
                'Calc4',
                'Calc5',
                'Calc6',
                'Fin_Tejido',
                'Fecha_Compromiso',
                'Fecha_Compromiso1',
                'Entrega',
                'Dif_vs_Compromiso',
                'id', // id siempre al final
            ];


            if (tabla) {
                tabla.querySelectorAll("tbody tr").forEach(fila => {
                    fila.addEventListener("click", function() {
                        const celdas = this.querySelectorAll("td");
                        let datosFila = {};

                        celdas.forEach((celda, index) => {
                            const clave = nombresColumnas[index];
                            // Esta línea es la clave:
                            const valorConDecimales = celda.getAttribute('data-valor');
                            datosFila[clave] = valorConDecimales ? valorConDecimales
                                .trim() : celda.textContent.trim();
                        });

                        datosFilaSeleccionada = datosFila;
                        console.log("Fila seleccionada:", datosFilaSeleccionada);
                    });
                });
            }


            botonUnico.addEventListener("click", function() {
                window.location.href =
                    "{{ route('planeacion.create', ['from' => 'planeacion']) }}"; //Cuando rediriges desde Planeacion, agregas un parámetro, por ejemplo: ?from=planeacion
            });
            btnEditar.addEventListener("click", function() {
                if (datosFilaSeleccionada) {
                    const query = new URLSearchParams(datosFilaSeleccionada).toString();
                    window.location.href =
                        `Tejido-Scheduling/editar?${query}`; //enviamos todos los datos dentro de la query
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'ATENCIÓN',
                        text: 'Por favor, primero seleccione el registro que desea editar.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#3085d6',
                        background: '#fff',
                        color: '#333'
                    });

                }
            });
        });
    </script>
    <script>
        document.getElementById('btnImportExcel').addEventListener('click', function() {
            window.location.href = "{{ route('tejido.import.form') }}";
        });
    </script>
    <script>
        document.getElementById('btnCompras').addEventListener('click', function() {
            window.location.href = "{{ route('tejido.scheduling.ventas') }}";
        });
    </script>
    <script>
        document.getElementById('btnPronosticos').addEventListener('click', function() {
            window.location.href = "{{ route('tejido.pronosticos.blade') }}";
        });
    </script>

    @push('styles')
        <style>
            .plane-table td {
                font-size: 10px;
                /* Ajustar el tamaño de la fuente */
            }

            .plane-table td {
                padding: 1px 2px !important;
                /* Reducir padding */
            }

            .plane-table th,
            .plane-table td {
                width: 100px;
                /* Establecer un ancho fijo más pequeño */
            }

            .plane-table td {
                word-wrap: break-word;
                /* Asegura que el contenido largo se divida en varias líneas */
                white-space: normal;
                /* Evitar que el texto se mantenga en una sola línea */
            }

            /* Contenedor para los botones en columna */
            .button-column {
                display: flex;
                flex-direction: column;
                /* Espacio entre los botones */
                width: 100px;
                /* Define el ancho de la columna de botones */
                margin-right: 4px;
                font-size: 8px !important;
            }

            /* Estilos para los botones */
            .button-plane {
                background-color: #0876d1;
                color: white;
                padding: 8px 2px;
                text-decoration: none;
                transition: background-color 0.3s ease;
                text-align: center;

                /* Centrado vertical y horizontal con flex */
                display: flex;
                align-items: center;
                /* Centra verticalmente */
                justify-content: center;
                /* Centra horizontalmente */
            }

            .button-plane-2 {
                background-color: #7839ed;
                /* Fondo azul */
                color: white;
                /* Color del texto */
                padding: 8px 2px;
                /* Espaciado interno */
                border-radius: 6px;
                /* Bordes redondeados */
                text-decoration: none;
                /* Elimina el subrayado */

                transition: background-color 0.3s ease;
                /* Efecto de transición */
                text-align: center;
                /* Centra el texto en cada botón */
                /* Centrado vertical y horizontal con flex */
                display: flex;
                align-items: center;
                /* Centra verticalmente */
                justify-content: center;
                /* Centra horizontalmente */
            }

            .button-plane:hover {
                background-color: #2779bd;
                /* Cambio de color en hover */
            }


            #tablaPlaneacion tbody tr:hover {
                background-color: #fef08a;
                /* Amarillo suave */
                cursor: pointer;
            }

            .fila-seleccionada {
                background-color: #fde047 !important;
                /* Amarillo más intenso al hacer clic */
            }
        </style>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const btn = document.getElementById("btnReportes");
                const menu = document.getElementById("menuReportes");

                btn.addEventListener("click", function(e) {
                    e.stopPropagation(); // Evita que el evento se propague y se cierre de inmediato
                    const isOpen = !menu.classList.contains("hidden");

                    // Cerrar si ya está abierto
                    if (isOpen) {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    } else {
                        menu.classList.remove("hidden");
                        // Forzar reflow para animación (truco CSS)
                        void menu.offsetWidth;
                        menu.classList.remove("scale-95", "opacity-0");
                        menu.classList.add("scale-100", "opacity-100");
                    }
                });

                // Ocultar el menú si haces clic fuera de él
                document.addEventListener("click", function(e) {
                    if (!btn.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    }
                });

                // También cerrar al hacer clic en una opción
                menu.querySelectorAll("a").forEach(link => {
                    link.addEventListener("click", () => {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    });
                });
            });
        </script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const btn = document.getElementById("btnCatalogos");
                const menu = document.getElementById("menuCatalogos");

                btn.addEventListener("click", function(e) {
                    e.stopPropagation(); // Evita que el evento se propague y se cierre de inmediato
                    const isOpen = !menu.classList.contains("hidden");

                    // Cerrar si ya está abierto
                    if (isOpen) {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    } else {
                        menu.classList.remove("hidden");
                        // Forzar reflow para animación (truco CSS)
                        void menu.offsetWidth;
                        menu.classList.remove("scale-95", "opacity-0");
                        menu.classList.add("scale-100", "opacity-100");
                    }
                });

                // Ocultar el menú si haces clic fuera de él
                document.addEventListener("click", function(e) {
                    if (!btn.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    }
                });

                // También cerrar al hacer clic en una opción
                menu.querySelectorAll("a").forEach(link => {
                    link.addEventListener("click", () => {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    });
                });
            });
        </script>
        <script>
            // Variable global para guardar el registro seleccionado
            let registroSeleccionado = null;

            // Función para seleccionar y marcar una fila
            function seleccionarRegistro(id, telar) {
                registroSeleccionado = {
                    id: id,
                    telar: telar
                };
                // Imprime el valor en la consola
                console.log('Registro seleccionado:', registroSeleccionado);
            }

            // Función para subir registro
            function moverArriba() {
                if (!registroSeleccionado) {
                    Swal.fire({
                        title: '¡ATENCIÓN!',
                        text: 'Por favor selecciona un registro primero.',
                        icon: 'info',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#1CC6DD',
                        background: '#fff3cd',
                        color: '#000'
                    });
                    return;
                }
                moverRegistro(registroSeleccionado, 'arriba');
            }

            // Función para bajar registro
            function moverAbajo() {
                if (!registroSeleccionado) {
                    Swal.fire({
                        title: '¡ATENCIÓN!',
                        text: 'Por favor selecciona un registro primero.',
                        icon: 'info',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#1CC6DD',
                        background: '#fff3cd',
                        color: '#856404'
                    });
                    return;
                }
                moverRegistro(registroSeleccionado, 'abajo');
            }

            // Función que envía la petición al backend
            function moverRegistro(registro, accion) {
                fetch('/tejido-scheduling/mover', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            id: registro.id,
                            telar: registro.telar,
                            accion: accion
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.ok) {
                            location.reload();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '¡UPS! OCURRIÓ UN ERROR',
                                text: data.error || 'Ocurrió un error al reordenar los registros.',
                                background: '#fff6f8', // Un rosita claro de fondo para un toque sweet
                                color: '#c0262e', // Color para el texto principal
                                confirmButtonColor: '#3085d6', // Botón azulito bonito
                                confirmButtonText: 'Entendido', // Mejor que "OK"
                                customClass: {
                                    title: 'text-xl font-bold',
                                    popup: 'rounded-2xl shadow-lg'
                                }
                            });

                        }
                    });
            }
        </script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const btn = document.getElementById("btnNuevo");
                const menu = document.getElementById("menuNuevo");

                btn.addEventListener("click", function(e) {
                    e.stopPropagation(); // Evita que el evento se propague y se cierre de inmediato
                    const isOpen = !menu.classList.contains("hidden");

                    // Cerrar si ya está abierto
                    if (isOpen) {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    } else {
                        menu.classList.remove("hidden");
                        // Forzar reflow para animación (truco CSS)
                        void menu.offsetWidth;
                        menu.classList.remove("scale-95", "opacity-0");
                        menu.classList.add("scale-100", "opacity-100");
                    }
                });

                // Ocultar el menú si haces clic fuera de él
                document.addEventListener("click", function(e) {
                    if (!btn.contains(e.target) && !menu.contains(e.target)) {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    }
                });

                // También cerrar al hacer clic en una opción
                menu.querySelectorAll("a").forEach(link => {
                    link.addEventListener("click", () => {
                        menu.classList.add("hidden");
                        menu.classList.remove("scale-100", "opacity-100");
                        menu.classList.add("scale-95", "opacity-0");
                    });
                });
            });
        </script>
    @endpush
@endsection
