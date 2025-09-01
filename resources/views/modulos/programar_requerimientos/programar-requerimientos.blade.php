@extends('layouts.app')

@section('content')
    <div class="container  mx-auto overflow-y-auto md:h-[550px]">
        <form id="seleccionForm" method="GET" action="{{ route('formulario.programarRequerimientos') }}">
            <!-- Inputs ocultos para enviar el telar y tipo seleccionados -->
            <input type="hidden" name="telar" id="telarInput">
            <input type="hidden" name="tipo" id="tipoInput">
            <input type="hidden" name="calibre" id="calibreInput">

            <!-- Wrapper con borde metálico (opcional, puedes quitarlo si no lo quieres) -->
            <div class="metal-border rounded-2xl">
                <!-- Tarjeta interior -->
                <div class="bg-white/95 backdrop-blur-sm shadow-xl rounded-2xl p-1 custom-scroll">

                    <div class="w-full  flex items-center gap-3">
                        <h2
                            class="text-lg font-bold text-slate-800 flex-1 text-center
                     rounded-lg px-4 py-1.5
                     bg-gradient-to-r from-yellow-200 via-yellow-300 to-yellow-200
                     ring-1 ring-yellow-300/50 shadow-inner tracking-wide">
                            PROGRAMACIÓN DE REQUERIMIENTOS
                        </h2>

                        <input type="hidden" name="idsSeleccionados" id="idsSeleccionados">

                        <button
                            class="w-40 h-8 rounded-xl font-semibold text-lg
                     text-white bg-gradient-to-r from-blue-500 via-blue-500 to-blue-600
                     hover:from-green-500 hover:to-green-600
                     transition-all duration-200 shadow-lg ring-1 ring-blue-300/40">
                            PROGRAMAR
                        </button>
                    </div>

                    <div class="overflow-y-auto max-h-60 rounded-xl ring-1 ring-gray-300/60">
                        <!-- Tabla 1: Programación de Requerimientos -->
                        <table
                            class="w-full text-xs border-collapse border border-gray-300 requerimientos bg-white rounded-xl overflow-hidden">
                            <thead class="shadow-sm">
                                <tr
                                    class="bg-gradient-to-b from-gray-100 via-gray-200 to-gray-300
                             text-left text-slate-800 sticky top-0 z-10">
                                    <th class="border px-1 py-1.5">Telar 🔽</th>
                                    <th class="border px-1 py-1.5">Tipo 🔽</th>
                                    <th class="border px-1 py-1.5">Cuenta 🔽</th>
                                    <th class="border px-1 py-1.5">Calibre 🔽</th>
                                    <th class="border px-1 py-1.5">Hilo 🔽</th>
                                    <th class="border px-1 py-1.5">Fecha Requerida 🔽</th>
                                    <th class="border px-1 py-1.5">Turno 🔽</th>
                                    <th class="border px-1 py-1.5">Tipo Atado 🔽</th>
                                    <th class="border px-1 py-1.5">Metros 🔽</th>
                                    <th class="border px-1 py-1.5">Mc Coy 🔽</th>
                                    <th class="border px-1 py-1.5">Orden Urdido o Engomado 🔽</th>
                                    <th class="border px-1 py-1.5"></th>
                                </tr>
                            </thead>

                            <tbody class="[&>tr>td]:align-middle [&>tr>td]:text-slate-700">
                                @foreach ($requerimientos as $req)
                                    <tr class="cursor-pointer transition duration-200
                                   hover:bg-gradient-to-r hover:from-yellow-100 hover:to-yellow-200"
                                        data-tipo="{{ $req->rizo == 1 ? 'Rizo' : ($req->pie == 1 ? 'Pie' : 'N/A') }}"
                                        data-telar="{{ $req->telar }}" onclick="seleccionarFila(this)"
                                        data-cuenta="{{ $req->rizo == 1 ? $req->cuenta_rizo : ($req->pie == 1 ? $req->cuenta_pie : '-') }}"
                                        data-calibre="{{ $req->rizo == 1 ? $req->calibre_rizo : ($req->pie == 1 ? $req->calibre_pie : '-') }}">
                                        <td class="border px-1 py-1">{{ $req->telar }}</td>

                                        <td class="border px-1 py-1">
                                            @if ($req->rizo == 1)
                                                Rizo
                                            @elseif($req->pie == 1)
                                                Pie
                                            @else
                                                N/A
                                            @endif
                                        </td>

                                        <td class="border px-1 py-1">
                                            {{ $req->rizo == 1
                                                ? preg_replace('/\.0$/', '', $req->cuenta_rizo)
                                                : ($req->pie == 1
                                                    ? preg_replace('/\.0$/', '', $req->cuenta_pie)
                                                    : '-') }}
                                        </td>

                                        <td class="border px-1 py-1">
                                            {{ $req->rizo == 1
                                                ? preg_replace('/\.0$/', '', $req->calibre_rizo)
                                                : ($req->pie == 1
                                                    ? preg_replace('/\.0$/', '', $req->calibre_pie)
                                                    : '-') }}
                                        </td>

                                        <td class="border px-1 py-1">{{ $req->calibre_pie }}</td>
                                        <!-- HILO nulo -->

                                        <td class="border px-1 py-1">
                                            {{ \Carbon\Carbon::parse($req->fecha)->format('d-m-Y') }}
                                        </td>

                                        <td class="border px-1 py-1">
                                            @php
                                                $m = [];
                                                $n = preg_match(
                                                    '/^(rizo|pie)\s*([1-3])$/i',
                                                    (string) ($req->valor ?? ''),
                                                    $m,
                                                )
                                                    ? $m[2]
                                                    : '';
                                            @endphp
                                            {{ $n }}
                                        </td>

                                        <td class="border px-1 py-1">{{ $req->tipo_atado }}</td> <!-- TIPO ATADO nulo -->
                                        <td class="border px-1 py-1">-</td> <!-- Metros nulo -->
                                        <td class="border px-1 py-1">-</td> <!-- Mc Coy nulo -->
                                        <td class="border px-1 py-1"></td>
                                        <!--Aqui se insertará la orden o FOLIO que se genera en la vista que sigue al presion boton Programar-->

                                        <input type="hidden" value="{{ $req->id }}">
                                        <!--Con este TD enviamos los id de los registros seleccionados previamente-->

                                        <td class="border px-1 py-1">
                                            <div class="flex items-center justify-center">
                                                <input type="checkbox" class="fila-check scale-150 accent-blue-600"
                                                    value="{{ $req->id }}">
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </form>

        <!-- Espacio entre tablas -->
        <div class="my-1"></div>

        <!-- Tabla 2: Inventario Disponible -->
        <!-- Wrapper con borde metálico (opcional) -->
        <div class="metal-border rounded-2xl">
            <!-- Tarjeta interior -->
            <div class="bg-white/95 backdrop-blur-sm shadow-xl rounded-2xl p-3">

                <div class="w-full -mt-3 mb-2 flex items-center gap-3">
                    <h2
                        class="text-lg font-bold text-slate-800 flex-1 text-center
               rounded-lg px-4 py-1.5
               bg-gradient-to-r from-yellow-200 via-yellow-300 to-yellow-200
               ring-1 ring-yellow-300/50 shadow-inner tracking-wide">
                        INVENTARIO DISPONIBLE
                    </h2>

                    <button
                        class="w-40 h-10 rounded-xl font-semibold text-lg text-white
               bg-gradient-to-r from-blue-500 via-blue-500 to-blue-600
               hover:from-green-500 hover:to-green-600
               transition-all duration-200 shadow-lg ring-1 ring-blue-300/40"
                        id="btnReservar">
                        RESERVAR
                    </button>
                </div>

                <div class="overflow-y-auto max-h-60 rounded-xl ring-1 ring-gray-300/60 custom-scroll">
                    <!-- Tabla 2: Inventarios -->
                    <table
                        class="w-full text-xs border-collapse border border-gray-300 inventarios bg-white rounded-xl overflow-hidden">
                        <thead class="shadow-sm">
                            <tr
                                class="bg-gradient-to-b from-gray-100 via-gray-200 to-gray-300 text-left text-slate-800 sticky top-0 z-10">
                                <th class="border px-1 py-1.5">Artículo</th>
                                <th class="border px-1 py-1.5">Tipo</th>
                                <th class="border px-1 py-1.5">Cantidad</th>
                                <th class="border px-1 py-1.5">Hilo</th>
                                <th class="border px-1 py-1.5">Cuenta</th>
                                <th class="border px-1 py-1.5">Color</th>
                                <th class="border px-1 py-1.5">Almacén</th>
                                <th class="border px-1 py-1.5">Orden</th>
                                <th class="border px-1 py-1.5">Localidad</th>
                                <th class="border px-1 py-1.5">No. Julio</th>
                                <th class="border px-1 py-1.5">Metros</th>
                                <th class="border px-1 py-1.5">Fecha</th>
                                <th class="border px-1 py-1.5">Telar</th>
                            </tr>
                        </thead>
                        <tbody class="[&>tr>td]:align-middle [&>tr>td]:text-slate-700">
                            @foreach ($inventarios as $inv)
                                <tr class="cursor-pointer transition duration-200
                       hover:bg-gradient-to-r hover:from-yellow-100 hover:to-yellow-200"
                                    data-tipo="{{ $inv->TIPO }}">
                                    <td class="border px-1 py-1">{{ $inv->ITEMID }}</td>
                                    <td class="border px-1 py-1">{{ $inv->TIPO }}</td>
                                    <td class="border px-1 py-1">{{ number_format($inv->QTY, 0) }}</td>
                                    <td class="border px-1 py-1">{{ $inv->CONFIGID }}</td>
                                    <td class="border px-1 py-1">{{ $inv->INVENTSIZEID }}</td>
                                    <td class="border px-1 py-1">{{ $inv->INVENTCOLORID }}</td>
                                    <td class="border px-1 py-1">{{ $inv->INVENTLOCATIONID }}</td>
                                    <td class="border px-1 py-1">{{ $inv->INVENTBATCHID }}</td>
                                    <td class="border px-1 py-1">{{ $inv->WMSLOCATIONID }}</td>
                                    <td class="border px-1 py-1">{{ $inv->INVENTSERIALID }}</td>
                                    <td class="border px-1 py-1">{{ number_format($inv->METROS, 0) }}</td>
                                    <td class="border px-1 py-1">
                                        {{ \Carbon\Carbon::parse($inv->FECHAINGRESO)->format('d-m-y') }}
                                    </td>
                                    <td class="border px-1 py-1">
                                        @if (isset($vinculados[$inv->RECID]))
                                            {{ $vinculados[$inv->RECID]->telar }}
                                        @endif
                                    </td>
                                    <input type="hidden" value="{{ $inv->RECID }}">
                                    <!-- el input oculta se ocupa para saber que registros ya estan seleccionados por el usuarios c:-->
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>


        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const requerimientosRows = document.querySelectorAll(".requerimientos tbody tr"); // primera tabla
                const inventariosRows = document.querySelectorAll(".inventarios tbody tr"); // segunda tabla

                requerimientosRows.forEach(row => {
                    row.addEventListener("click", function() {
                        // Obtener tipo y cuenta de la fila seleccionada en la primera tabla
                        const tipoSeleccionado = this.querySelector("td:nth-child(2)").textContent
                            .trim();
                        const cuentaSeleccionada = this.querySelector("td:nth-child(3)").textContent
                            .trim();

                        // Filtrar la segunda tabla
                        inventariosRows.forEach(invRow => {
                            const tipoInventario = invRow.querySelector("td:nth-child(2)")
                                .textContent.trim();

                            // Extraer la cuenta antes del guion "-"
                            const cuentaInventarioFull = invRow.querySelector("td:nth-child(5)")
                                .textContent.trim();
                            const cuentaInventario = cuentaInventarioFull.split("-")[0];

                            // Mostrar solo si coinciden tipo y cuenta
                            if (tipoInventario.toLowerCase() === tipoSeleccionado
                                .toLowerCase() && cuentaInventario ===
                                cuentaSeleccionada) {
                                invRow.style.display = "";
                            } else {
                                invRow.style.display = "none";
                            }
                        });
                    });
                });
            });
        </script>

        <script>
            // Función para actualizar los inputs ocultos al hacer clic en la fila
            function seleccionarFila(row) {
                // Extraer los valores de los atributos data-tipo y data-telar de la fila
                const telar = row.getAttribute("data-telar");
                const tipo = row.getAttribute("data-tipo");
                const calibre = row.getAttribute("data-calibre");

                // Actualizar los campos ocultos
                document.getElementById("telarInput").value = telar;
                document.getElementById("tipoInput").value = tipo;
                document.getElementById("calibreInput").value = calibre;

                // Opcional: resaltar la fila seleccionada (por ejemplo, eliminando la clase de las demás)
                document.querySelectorAll("#seleccionForm tbody tr").forEach(r => r.classList.remove("bg-blue-200"));
                row.classList.add("bg-blue-200");
            }
        </script>
        <!--El siguiente script, marca la fila seleccionada de amarillo y obtiene todos sus datos-->
        <script>
            let selectedInventarioData = null;
            let selectedRequerimientoData = null;
            const inventariosReservados = @json($InventariosSeleccionados); // Pasar el arreglo PHP a JS


            // SELECCIÓN DE LA TABLA INVENTARIOS
            // Al cargar la tabla, marcar filas reservadas y bloquear selección
            document.querySelectorAll(".inventarios tbody tr").forEach(row => {
                let recid = row.querySelector('input[type="hidden"]').value;

                if (inventariosReservados.includes(recid)) {
                    row.classList.add("bg-red-200");
                    // Puedes añadir tooltip o cursor no permitido
                    row.title = "Este registro ya ha sido reservado.";
                    // Opcional: bloquear pointer events para no permitir click
                    row.style.pointerEvents = "none";
                    return; // No asignamos evento click porque no puede seleccionar
                }

                row.addEventListener("click", function() {
                    document.querySelectorAll(".inventarios tbody tr").forEach(r => r.classList.remove(
                        "bg-yellow-300"));
                    this.classList.add("bg-yellow-300");

                    const cells = this.querySelectorAll("td");
                    selectedInventarioData = {
                        articulo: cells[0].innerText.trim(),
                        tipo: cells[1].innerText.trim(),
                        cantidad: parseInt(cells[2].innerText.trim()),
                        hilo: cells[3].innerText.trim(),
                        cuenta: cells[4].innerText.trim(),
                        color: cells[5].innerText.trim(),
                        almacen: cells[6].innerText.trim(),
                        orden: cells[7].innerText.trim(),
                        localidad: cells[8].innerText.trim(),
                        no_julio: cells[9].innerText.trim(),
                        metros: cells[10].innerText.trim(),
                        fecha: cells[11].innerText.trim(),
                        twdis_key: 'foranea',
                        recid: recid,
                    };

                    console.log("Inventario seleccionado:", selectedInventarioData);
                });
            });


            // SELECCIÓN DE LA TABLA REQUERIMIENTOS
            document.querySelectorAll(".requerimientos tbody tr").forEach(row => {
                row.addEventListener("click", function() {
                    if (this.classList.contains("bg-red-200")) {
                        alert(
                            "Este requerimiento ya ha sido reservado y no puede ser seleccionado nuevamente."
                        );
                        return;
                    }

                    document.querySelectorAll(".requerimientos tbody tr").forEach(r => r.classList.remove(
                        "bg-yellow-300"));
                    this.classList.add("bg-yellow-300");

                    const id = this.querySelector('input[type="hidden"]').value;
                    selectedRequerimientoData = {
                        id: id
                    };

                    console.log("Requerimiento seleccionado:", selectedRequerimientoData);
                });
            });


            // BOTÓN PARA GUARDAR DATOS UNIFICADOS
            document.querySelector("#btnReservar").addEventListener("click", function() {
                if (!selectedInventarioData && selectedRequerimientoData) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Acción requerida',
                        text: 'Selecciona una fila en ambas tablas antes de reservar.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#3085d6',
                    });
                    return;
                } else if (selectedInventarioData && selectedRequerimientoData) {
                    //si hay seleccion de filas en ambas tablas, simplemente el programa continua ejecutandose
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'Acción requerida',
                        text: 'Selecciona una fila en ambas tablas antes de reservar.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#3085d6',
                    });
                    return;
                }

                const inventarioRow = document.querySelector(".inventarios tbody tr.bg-yellow-300");
                const requerimientoRow = document.querySelector(".requerimientos tbody tr.bg-yellow-300");

                // Verifica si alguna de las filas ya está en rojo
                if (inventarioRow && inventarioRow.classList.contains("bg-red-200") ||
                    requerimientoRow && requerimientoRow.classList.contains("bg-red-200")) {
                    alert("No puedes reservar registros que ya han sido seleccionados anteriormente.");
                    return;
                }


                // Combinar ambos objetos en un solo payload
                const dataToSend = {
                    inventario: selectedInventarioData,
                    requerimiento: selectedRequerimientoData
                };

                fetch("{{ route('reservar.inventario') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: JSON.stringify(dataToSend)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'ÉXITO',
                                text: data.message,
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#3085d6',
                                background: '#f0f8ff',
                                color: '#0a3d62',
                            });

                            // PINTAR AMBAS FILAS DE ROJO CLARO
                            const inventarioRow = document.querySelector(".inventarios tbody tr.bg-yellow-300");
                            const requerimientoRow = document.querySelector(
                                ".requerimientos tbody tr.bg-yellow-300");

                            inventarioRow.classList.remove("bg-yellow-300");
                            requerimientoRow.classList.remove("bg-yellow-300");

                            inventarioRow.classList.add("bg-red-200");
                            requerimientoRow.classList.add("bg-red-200");

                            // ACTUALIZAR CELDAS EN LA TABLA DE INVENTARIOS
                            // Suponiendo que "metros" es la columna 10 y "mccoy" la 11 (ajusta si es necesario)
                            const invCells = requerimientoRow.querySelectorAll("td");
                            invCells[4].innerText = data.nuevos_valores.metros;
                            invCells[5].innerText = data.nuevos_valores.mccoy;
                            invCells[6].innerText = data.nuevos_valores.orden;

                            // ACTUALIZAR CELDA EN LA TABLA DE REQUERIMIENTOS
                            // Aquí asumimos que hay una celda específica para el telar (ajusta si es necesario)
                            const reqCells = inventarioRow.querySelectorAll("td");
                            const celdaTelar = reqCells[12];
                            celdaTelar.innerText = data.nuevos_valores.telar;

                        } else {
                            Swal.fire({
                                icon: 'info',
                                title: 'ERROR',
                                text: data.message,
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#3085d6',
                                background: '#f0f8ff',
                                color: '#0a3d62',
                            });
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                        alert("Ocurrió un error en la solicitud.");
                    });
            });
        </script>

        <script>
            function ordenarTabla(th, columnaIndex) {
                const tabla = th.closest('table');
                const tbody = tabla.querySelector('tbody');
                const filas = Array.from(tbody.querySelectorAll('tr'));
                const ascendente = th.dataset.orden !== 'asc';

                // Resetear iconos de otras columnas
                tabla.querySelectorAll('th').forEach(header => {
                    header.dataset.orden = '';
                    if (header.textContent.includes('🔼') || header.textContent.includes('🔽')) {
                        header.textContent = header.textContent.replace('🔼', '').replace('🔽', '');
                    }
                });

                // Establecer icono y orden actual
                th.dataset.orden = ascendente ? 'asc' : 'desc';
                th.textContent += ascendente ? ' 🔼' : ' 🔽';

                filas.sort((a, b) => {
                    const aTexto = a.children[columnaIndex].innerText.trim();
                    const bTexto = b.children[columnaIndex].innerText.trim();

                    // Ver si se puede convertir a número o fecha
                    const aNum = parseFloat(aTexto.replace(',', ''));
                    const bNum = parseFloat(bTexto.replace(',', ''));
                    const esNumero = !isNaN(aNum) && !isNaN(bNum);

                    const aFecha = new Date(aTexto.split('-').reverse().join('-')); // d-m-Y → Y-m-d
                    const bFecha = new Date(bTexto.split('-').reverse().join('-'));
                    const esFecha = !isNaN(aFecha) && !isNaN(bFecha);

                    if (esNumero) {
                        return ascendente ? aNum - bNum : bNum - aNum;
                    } else if (esFecha) {
                        return ascendente ? aFecha - bFecha : bFecha - aFecha;
                    } else {
                        return ascendente ?
                            aTexto.localeCompare(bTexto) :
                            bTexto.localeCompare(aTexto);
                    }
                });

                // Limpiar y reinsertar las filas ordenadas
                tbody.innerHTML = '';
                filas.forEach(fila => tbody.appendChild(fila));
            }

            // Agregar funcionalidad a cada th automáticamente
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('table.requerimientos thead th').forEach((th, index) => {
                    th.style.cursor = 'pointer';
                    th.addEventListener('click', function() {
                        ordenarTabla(th, index);
                    });
                });
            });
        </script>
        <!--capturamos los checkbox seleccionados c:-->
        {{-- VERIFICAMOS QUE, en caso de seleccionar mas de un checkbox, sean del mismo TIPO y CALIBRE --}}
        <script>
            // Selección múltiple
            function toggleTodos(masterCheckbox) {
                const checkboxes = document.querySelectorAll('.fila-check');
                checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
            }

            // Antes de enviar el form, recoger los IDs seleccionados
            document.getElementById('seleccionForm').addEventListener('submit', function(e) {
                const seleccionados = Array.from(document.querySelectorAll('.fila-check:checked'))
                    .map(cb => cb.value);

                if (seleccionados.length === 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: '!ATENCIÓN!',
                        text: 'Por favor marque al menos un cuadro (lado izquierdo) para continuar con la programación.',
                        confirmButtonColor: '#3085d6',
                    });

                    return;
                }

                if (seleccionados.length > 1) {
                    // Obtenemos los <tr> correspondientes a los seleccionados
                    const filasSeleccionadas = seleccionados.map(id =>
                        document.querySelector(`.fila-check[value="${id}"]`).closest('tr')
                    );

                    // Sacamos los valores tipo y cuenta de esas filas
                    const tipos = filasSeleccionadas.map(fila => fila.getAttribute('data-tipo'));
                    const calibres = filasSeleccionadas.map(fila => fila.getAttribute('data-calibre'));

                    // Validamos que todos los tipos sean iguales
                    const todosTiposIguales = tipos.every(tipo => tipo === tipos[0]);
                    const todasCalibreIguales = calibres.every(calibre => calibre === calibres[0]);

                    if (!todosTiposIguales || !todasCalibreIguales) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Los registros seleccionados deben tener el mismo Calibre y Tipo (Rizo o Pie).',
                            confirmButtonColor: '#d33',
                            confirmButtonText: 'Entendido',
                        });

                        return;
                    }
                }

                document.getElementById('idsSeleccionados').value = JSON.stringify(seleccionados);

            });
        </script>

        @push('styles')
            <style>
                /* Marco con borde "metálico" (usa como wrapper opcional) */
                .metal-border {
                    background: linear-gradient(135deg, #e5e7eb, #cbd5e1 40%, #e5e7eb);
                    padding: 2px;
                    /* grosor del borde */
                    border-radius: 14px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
                }

                /* Scrollbar sobrio y metálico */
                .custom-scroll::-webkit-scrollbar {
                    width: 10px;
                    height: 10px;
                }

                .custom-scroll::-webkit-scrollbar-track {
                    background: linear-gradient(180deg, #f3f4f6, #e5e7eb);
                    border-radius: 10px;
                }

                .custom-scroll::-webkit-scrollbar-thumb {
                    background: linear-gradient(180deg, #d1d5db, #cbd5e1);
                    border-radius: 10px;
                    border: 2px solid #f3f4f6;
                }

                .custom-scroll {
                    scrollbar-color: #cbd5e1 #f3f4f6;
                    scrollbar-width: thin;
                }
            </style>
        @endpush
    @endsection

    <!--
    SELECT *
FROM Produccion.dbo.TWDISPONIBLEURDENG2 AS d
JOIN Produccion.dbo.requerimiento AS r
    ON d.reqid = r.id;-->


    <!--
--creacion  de tabla COPIA
CREATE TABLE TWDISPONIBLEURDENG2 (
    ITEMID            VARCHAR(30),
    CONFIGID          VARCHAR(20),
    INVENTSIZEID      VARCHAR(20),
    INVENTCOLORID     VARCHAR(20),
    INVENTLOCATIONID  VARCHAR(20),
    INVENTBATCHID     VARCHAR(30),
    WMSLOCATIONID     VARCHAR(30),
    INVENTSERIALID    VARCHAR(30),
    QTY               INT,
    METROS            DECIMAL(18, 2),
    TIPO              VARCHAR(20),
    ITEMNAME          VARCHAR(100),
    COLORNAME         VARCHAR(50),
    FECHAINGRESO      DATETIME,
    DATAAREAID        VARCHAR(10),
    RECVERSION        INT,
    RECID             BIGINT
);
-->
