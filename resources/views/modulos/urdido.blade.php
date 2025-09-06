@extends('layouts.app')

@section('content')
    <div class="container mx-auto p-2 bg-white shadow-lg rounded-lg mt-[1px] overflow-y-auto md:h-[600px]">
        <div id="finalizadoOverlay">FINALIZADO CORRECTAMENTE</div>

        <!-- Formulario -->
        <form class="grid grid-cols-1 md:grid-cols-4 gap-2">
            <!-- Primera columna -->
            <div class="text-sm ">
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">FOLIO:</label>
                    <input id="folio" type="text" class="w-2/4 border rounded p-0.5 text-xs font-bold"
                        value="{{ explode('-', $requerimiento->folio)[0] ?? '' }}" readonly>
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">CUENTA:</label>
                    <input type="text" class="w-2/4 border rounded p-0.5 text-xs font-bold"
                        value="{{ isset($urdido->cuenta) ? (intval($urdido->cuenta) == $urdido->cuenta ? intval($urdido->cuenta) : $urdido->cuenta) : '' }}"
                        readonly>
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">URDIDO:</label>
                    <input type="text" class="w-2/4 border rounded p-0.5 text-xs font-bold"
                        value="{{ $urdido->urdido ?? '' }}" readonly>
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm ">METROS:</label>
                    <input type="text" class="w-3/6 border rounded p-0.5 text-xs font-bold"
                        value="{{ rtrim(rtrim($urdido->metros, '0'), '.') }}" readonly>
                </div>
            </div>

            <!-- Segunda columna -->
            <div class="text-sm">
                <div class="flex items-center">
                    <label class="w-1/3 text-sm">TIPO:</label>
                    <div class="flex items-center">
                        <label class="text-sm text-black font-semibold">
                            <input type="radio" name="tipo" value="Rizo"
                                {{ $urdido->tipo === 'Rizo' ? 'checked' : '' }} disabled> Rizo
                        </label>
                        <label class="text-sm text-black font-semibold ml-4">
                            <input type="radio" name="tipo" value="Pie"
                                {{ $urdido->tipo === 'Pie' ? 'checked' : '' }} disabled> Pie
                        </label>
                    </div>
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 text-sm ">DESTINO:</label>
                    <input type="text" class="w-3/6 border rounded text-xs font-bold p-0.5"
                        value="{{ $urdido->destino . ' ' . $requerimiento->telar ?? '' }}" readonly>
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 text-sm">ORDENADO POR:</label>
                    <input type="text" class="w-3/6 border rounded p-0.5 text-xs font-bold" value="pending">
                </div>

                {{-- NUEVOS LABELS --}}
                <div class="flex items-center">
                    <label class="w-1/3 text-sm">HILO:</label>
                    <input type="text" class="w-3/6 border rounded text-xs font-bold p-0.5" value="pending">
                </div>
                <div class="flex items-center">
                    <label class="w-1/3 text-sm">TIPO ATADO:</label>
                    <input type="text" class="w-3/6 border rounded text-xs font-bold p-0.5" value="pending">
                </div>

            </div>

            <!-- (Tercera / Cuarta según tu layout) -->
            <div>
                <!-- Tabla de Construcción -->
                <table class="w-full border-collapse border border-gray-300 font-bold">
                    <thead>
                        <tr class="bg-gray-200 text-xs">
                            <th class="border p-1 text-center font-bold">No. JULIO</th>
                            <th class="border p-1 text-center font-bold">HILOS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($construccion as $registroConstruccion)
                            <tr class="text-xs">
                                <td class="border p-0.5 text-center">{{ $registroConstruccion->no_julios ?? '' }}</td>
                                <td class="border p-0.5 text-center">{{ $registroConstruccion->hilos ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- OBSERVACIONES A TODO ANCHO -->
            <div>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-200 text-xs">
                            <th class="border p-1 text-center">OBSERVACIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="1" class="border p-0.5">
                                <textarea class="text-xs w-full border rounded resize-y font-bold min-h-[95px]" readonly maxlength="300">{{ trim($urdido->observaciones ?? '') }}</textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Tabla de Datos -->
        <table class="w-full border-collapse border border-gray-300 mt-2 text-center">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border pt-0.5" colspan="11"></th>
                    <th class="fs-10 text-center border-2 border-black" colspan="4">ROTURAS</th>
                </tr>
                <tr class="bg-gray-200 fs-10">
                    <th class="border">FECHA</th>
                    <th class="border">OFICIAL</th>
                    <th class="border">TURNO</th>

                    <th class="border">H. INIC.</th>
                    <th class="border">H. FIN</th>

                    <th class="border">No. JULIO</th>
                    <th class="border">HILOS</th>
                    <th class="border">Kg. BRUTO</th>
                    <th class="border">TARA</th>
                    <th class="border W-30">Kg. NETO</th>
                    <th class="border">METROS</th>
                    <th class="border">HILAT.</th>
                    <th class="border">MÁQ.</th>
                    <th class="border">OPERAC.</th>
                    <th class="border">TRANSF.</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $registroIndex = 0;
                @endphp
                @foreach ($construccion as $registroConstruccion)
                    @for ($i = 0; $i < $registroConstruccion->no_julios; $i++)
                        @php
                            $orden = $ordenUrdido[$registroIndex] ?? null;
                            $oficial = $oficiales[$registroIndex] ?? null;
                            $registroIndex++;
                        @endphp
                        <tr class="text-xs">
                            <input type="hidden" name="datos[{{ $registroIndex }}][id2]" value="{{ $registroIndex }}">
                            <input type="hidden" name="datos[{{ $registroIndex }}][folio]"
                                value="{{ $registroConstruccion->folio ?? '' }}">
                            <td class="border p-1">
                                <input class="w-[70px] p-1" type="date" name="datos[{{ $registroIndex }}][fecha]"
                                    value="{{ $orden && $orden->fecha ? \Carbon\Carbon::parse($orden->fecha)->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d') }}">
                            </td>
                            <!--OFICIAL, select dinámico-->
                            <td class="border p-1 w-[75px]">
                                <select class="w-[75px] border rounded p-1 text-xs"
                                    name="datos[{{ $registroIndex }}][oficial]" id="oficial_{{ $registroIndex }}"
                                    onchange="updateOficialTipo({{ $registroIndex }})">
                                    <option value="{{ Auth::user()->nombre }}">{{ Auth::user()->nombre }}</option>
                                    @foreach ($oficiales as $of)
                                        <option value="{{ $of->oficial }}" data-tipo="{{ $of->tipo }}"
                                            @if (!empty($orden) && $of->oficial == $orden->oficial) selected @endif>
                                            {{ $of->oficial }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="border "><input type="text" inputmode="numeric" pattern="[0-9]*"
                                    name="datos[{{ $registroIndex }}][turno]"
                                    class="w-5 font-bold border rounded p-1 text-xs text-center"
                                    value="{{ $turnoActual ?? '' }}">
                            </td>
                            {{-- HORAS INICIAL Y FINAL --}}
                            @php
                                // Solo para ejemplo: crea un id único por fila para manipular los campos
                                $horaInicioId = 'hora_inicio_' . $registroIndex;
                                $horaFinId = 'hora_fin_' . $registroIndex;
                            @endphp
                            <td class="border p-0.5">
                                <div style="display: flex; align-items: center; gap: 3px;">
                                    <input type="time" id="{{ $horaInicioId }}"
                                        name="datos[{{ $registroIndex }}][hora_inicio]"
                                        class="w-[90px] border rounded p-1 text-xs"
                                        value="{{ isset($orden->hora_inicio) ? \Illuminate\Support\Str::limit($orden->hora_inicio, 5, '') : '' }}">
                                    <button type="button"
                                        class="text-xs w-8 px-1 py-0.5 border rounded bg-gray-100 hover:bg-blue-100"
                                        onclick="setTimeNow('{{ $horaInicioId }}')">🕒</button>
                                </div>
                            </td>
                            <td class="border p-0.5">
                                <div style="display: flex; align-items: center; gap: 3px;">
                                    <input type="time" id="{{ $horaFinId }}"
                                        name="datos[{{ $registroIndex }}][hora_fin]"
                                        class="w-[90px] border rounded p-1 text-xs"
                                        value="{{ isset($orden->hora_fin) ? \Illuminate\Support\Str::limit($orden->hora_fin, 5, '') : '' }}">
                                    <button type="button"
                                        class="text-xs w-8 px-1 py-0.5 border rounded bg-gray-100 hover:bg-red-100"
                                        onclick="setTimeNowAndSync('{{ $horaFinId }}', '{{ 'hora_inicio_' . ($registroIndex + 1) }}')">🕒</button>
                                </div>
                            </td>

                            <td class="border p-1 w-30">
                                <select class="w-10 border rounded p-1 text-xs text-center no-arrow"
                                    name="datos[{{ $registroIndex }}][no_julio]" id="no_julio_{{ $registroIndex }}"
                                    onchange="updateValues({{ $registroIndex }})">
                                    <option value="">🧵</option>
                                    @foreach ($julios as $julio)
                                        <option value="{{ $julio->no_julio }}" data-tara="{{ $julio->tara }}"
                                            data-tipo="{{ $julio->tipo }}"
                                            @if (!is_null($orden) && $julio->no_julio == $orden->no_julio) selected @endif>
                                            {{ $julio->no_julio }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="border p-1">{{ $registroConstruccion->hilos ?? '' }}
                                <input type="hidden" name="datos[{{ $registroIndex }}][hilos]"
                                    value="{{ $registroConstruccion->hilos ?? '' }}">
                            </td>
                            <td class="border">
                                <input class="w-14 border rounded text-xs text-center" type="text" inputmode="numeric"
                                    pattern="[0-9]*" name="datos[{{ $registroIndex }}][peso_bruto]"
                                    value="{{ $orden->peso_bruto ?? '' }}" id="peso_bruto_{{ $registroIndex }}"
                                    onchange="updatePesoNeto({{ $registroIndex }})">
                            </td>

                            <td class="border">
                                <input class="w-10 text-center text-xs" type="text"
                                    name="datos[{{ $registroIndex }}][tara]" id="tara_{{ $registroIndex }}"
                                    value="{{ $orden->tara ?? '' }}" readonly>
                            </td>
                            <td class="border">
                                <input class="w-12 text-center  text-xs" type="text"
                                    name="datos[{{ $registroIndex }}][peso_neto]" id="peso_neto_{{ $registroIndex }}"
                                    value="{{ $orden->peso_neto ?? '' }}" readonly>
                            </td>

                            <td class="border p-1">{{ rtrim(rtrim($urdido->metros ?? '', '0'), '.') }}
                                <input type="hidden" name="datos[{{ $registroIndex }}][metros]"
                                    value="{{ rtrim(rtrim($urdido->metros ?? '', '0'), '.') }}">
                            </td>
                            <td class="border p-1"><input type="text" inputmode="numeric" pattern="[0-9]*"
                                    name="datos[{{ $registroIndex }}][hilatura]" class="w-10 border rounded p-1 text-xs"
                                    value="{{ $orden->hilatura ?? '' }}"></td>
                            <td class="border p-1"><input type="text" inputmode="numeric" pattern="[0-9]*"
                                    name="datos[{{ $registroIndex }}][maquina]" class="w-10 border rounded p-1 text-xs"
                                    value="{{ $orden->maquina ?? '' }}"></td>
                            <td class="border p-1">
                                <input type="text" inputmode="numeric" pattern="[0-9]*"
                                    name="datos[{{ $registroIndex }}][operacion]" class="w-10 border rounded p-1 text-xs"
                                    value="{{ $orden->operacion ?? '' }}">
                            </td>
                            <td class="border p-1"><input type="text" inputmode="numeric" pattern="[0-9]*"
                                    name="datos[{{ $registroIndex }}][transferencia]"
                                    class="w-10 border rounded p-1 text-xs" value="{{ $orden->transferencia ?? '' }}">
                            </td>
                        </tr>
                    @endfor
                @endforeach
            </tbody>
        </table>
        <div class="mt-4 text-right">
            @if ($urdido->estatus_urdido == 'en_proceso')
                <button id="Finalizar"
                    class="ml-10 btn bg-blue-600 text-white w-40 h-12 hover:bg-blue-400">Finalizar</button>
            @endif
            @if ($urdido->estatus_urdido == 'finalizado')
                <button onclick="reimprimir()" class="w-1/5 px-4 py-2 bg-green-600 text-white rounded">
                    🔁 Reimprimir
                </button>
            @endif
        </div>
    </div>

    <!-- Modal de error -->
    <div id="modalErrores" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
        <div class="bg-white rounded-lg shadow-lg w-96 p-6">
            <h2 class="text-lg font-semibold mb-4 text-red-600">Campos faltantes</h2>
            <div id="contenidoErrores" class="text-sm text-gray-700 whitespace-pre-line"></div>
            <div class="mt-6 text-right">
                <button onclick="cerrarModalErrores()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Aceptar
                </button>
            </div>
        </div>
    </div>


    <script>
        function updateOficialTipo(index) {
            const select = document.getElementById('oficial_' + index);
            const tipo = select.options[select.selectedIndex].getAttribute('data-tipo');

            // Si quieres llenar otro input con ese tipo, podrías hacer algo como:
            const tipoInput = document.getElementById('tipo_oficial_' + index);
            if (tipoInput) {
                tipoInput.value = tipo;
            }
        }
    </script>

    <script>
        function mostrarModalErrores(mensaje) {
            document.getElementById("contenidoErrores").innerHTML = mensaje;
            document.getElementById("modalErrores").classList.remove("hidden");
        }

        function cerrarModalErrores() {
            document.getElementById("modalErrores").classList.add("hidden");
        }
    </script>

    <!--script para actualizar datos en tiempo real en 2 campos de la 2da tabla (tara y peso neto)-->
    <script>
        function updateValues(registroIndex) {
            // Obtener el select y la opción seleccionada
            let select = document.getElementById('no_julio_' + registroIndex);
            let selectedOption = select.options[select.selectedIndex];

            // Obtener la tara de los atributos data-tara
            let tara = selectedOption.getAttribute('data-tara');

            // Asignar la tara al input correspondiente
            document.getElementById('tara_' + registroIndex).value = tara;

            // Llamar a la función para actualizar el peso neto
            updatePesoNeto(registroIndex);
        }

        function updatePesoNeto(registroIndex) {
            // Obtener el valor del peso bruto y la tara
            let pesoBruto = parseFloat(document.getElementById('peso_bruto_' + registroIndex).value) || 0;
            let tara = parseFloat(document.getElementById('tara_' + registroIndex).value) || 0;

            // Calcular el peso neto
            let pesoNeto = pesoBruto - tara;

            // Asignar el valor calculado al input de peso neto
            document.getElementById('peso_neto_' + registroIndex).value = pesoNeto.toFixed(2); // Mostrar con 2 decimales
        }
    </script>

    <script>
        function reimprimir() {
            const folio = document.getElementById('folio').value;
            const url = "{{ url('/imprimir-orden-llena-urd') }}/" + folio;
            const papeletas = "{{ url('/imprimir-papeletas-pequenias') }}/" + folio;
            window.open(url, '_blank');
            window.open(papeletas, '_blank');
        }
    </script>

    {{-- SCRIPT para funcion de colocar hora en automatico, y le da función a los botones de relojitos --}}
    <script>
        // Coloca la hora del sistema por defecto en todos los inputs de tipo 'time' vacíos en hora_inicio
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[type="time"]').forEach(function(input) {
                if (!input.value && input.name.includes('[hora_inicio]')) {
                    input.value = getCurrentTime();
                }
            });
        });

        function getCurrentTime() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            return h + ":" + m;
        }


        // Botón para poner la hora actual en cualquier input de time
        function setTimeNow(inputId) {
            const input = document.getElementById(inputId);
            if (input) {
                const now = new Date();
                const h = String(now.getHours()).padStart(2, '0');
                const m = String(now.getMinutes()).padStart(2, '0');
                input.value = h + ":" + m;
                // 🔥 Dispara el evento 'change'
                input.dispatchEvent(new Event('change'));
            }
        }

        // Botón para poner la hora actual en hora_fin Y sincronizar como inicio en la siguiente fila
        function setTimeNowAndSync(finId, nextInicioId) {
            const time = getCurrentTime();
            const finInput = document.getElementById(finId);
            const nextInput = document.getElementById(nextInicioId);

            if (finInput) {
                finInput.value = time;
                finInput.dispatchEvent(new Event('change')); // ¡Aquí sí!
            }
            if (nextInput) {
                nextInput.value = time;
                nextInput.dispatchEvent(new Event('change')); // ¡Y aquí también!
            }
        }
    </script>

    <script>
        document.querySelectorAll('input[name^="datos"], select[name^="datos"]').forEach(el => {
            el.addEventListener('change', function() {
                const match = this.name.match(/datos\[(\d+)\]\[(\w+)\]/);
                if (!match) return;
                const index = match[1];

                // Encuentra la fila de la tabla
                const row = this.closest('tr');
                // Recolecta TODOS los campos de la fila con el mismo índice
                const inputs = row.querySelectorAll('input[name^="datos[' + index +
                    ']"], select[name^="datos[' + index + ']"]');

                let registro = {};
                inputs.forEach(input => {
                    const matchInput = input.name.match(/datos\[\d+\]\[(\w+)\]/);
                    if (matchInput) {
                        let key = matchInput[1];
                        let value = input.value;
                        if (input.tagName.toLowerCase() === "select") {
                            value = input.options[input.selectedIndex].value;
                        }
                        registro[key] = value;
                    }
                });

                // Asegúrate que folio y id2 estén incluidos (puede ser redundante)
                registro['folio'] = document.getElementById("folio").value;
                if (!registro['id2']) {
                    registro['id2'] = row.querySelector('input[name$="[id2]"]').value;
                }

                // AJAX call por registro completo
                fetch("{{ route('urdido.autoguardar') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(registro)
                    })
                    .then(resp => resp.json())
                    .then(data => {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Guardado',
                            showConfirmButton: false,
                            timer: 1000,
                            timerProgressBar: true,
                            background: '#ecfdf5',
                            customClass: {
                                title: 'text-green-800 text-xs font-semibold'
                            }
                        });
                    })
                    .catch(e => {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            title: 'No se pudo guardar',
                            showConfirmButton: false,
                            timer: 1500,
                            timerProgressBar: true,
                            background: '#fee2e2',
                            customClass: {
                                title: 'text-red-800 text-xs font-semibold'
                            }
                        });
                    });
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const finalizarBtn = document.getElementById("Finalizar");
            if (finalizarBtn) {
                finalizarBtn.addEventListener("click", function(e) {
                    e.preventDefault();
                    // Confirma con SweetAlert2
                    Swal.fire({
                        title: '¿Seguro que deseas finalizar?',
                        text: "Ya no podrás editar los datos después.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, finalizar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Obtén el folio (ajusta el selector según tu HTML)
                            const folio = document.getElementById("folio").value;
                            fetch("{{ route('urdido.finalizar') }}", {
                                    method: "POST",
                                    headers: {
                                        "Content-Type": "application/json",
                                        "X-CSRF-TOKEN": document.querySelector(
                                            'meta[name="csrf-token"]').content,
                                    },
                                    body: JSON.stringify({
                                        folio: folio
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    // Muestra mensaje de éxito
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Finalizado!',
                                        text: 'Los datos han sido finalizados y bloqueados.',
                                        timer: 1800,
                                        showConfirmButton: false
                                    });

                                    // Deshabilita todos los inputs y selects de la tabla
                                    document.querySelectorAll(
                                        'table input, table select, table button').forEach(
                                        el => {
                                            el.disabled = true;
                                            el.classList.add('bg-gray-100',
                                                'text-gray-400');
                                        });

                                    // Opcional: deshabilita el botón "Finalizar"
                                    finalizarBtn.disabled = true;
                                    finalizarBtn.innerText = "Finalizado";
                                    finalizarBtn.classList.add('bg-gray-400',
                                        'hover:bg-gray-400');

                                })
                                .catch(error => {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: 'No se pudo finalizar. Intenta nuevamente.',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                });
                        }
                    });
                });
            }
        });
    </script>

    @push('styles')
        <style>
            #finalizadoOverlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.8);
                color: white;
                display: flex;
                justify-content: center;
                align-items: center;
                font-size: 4rem;
                font-weight: bold;
                z-index: 9999;
                opacity: 0;
                transition: opacity 0.5s ease;
                pointer-events: none;
            }

            #finalizadoOverlay.active {
                opacity: 1;
                pointer-events: auto;
            }

            /* Quita la flechita de todos los select */
            .no-arrow {
                appearance: none;
                /* Estándar */
                -webkit-appearance: none;
                /* Safari/Chrome */
                -moz-appearance: none;
                /* Firefox */
                background: none;
                /* Opcional: elimina fondo por defecto */
                padding-right: 0.5rem;
                /* Ajusta si ves raro el texto */
            }
        </style>
    @endpush

@endsection
