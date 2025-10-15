@extends('layouts.app')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <!-- Header usando componente reutilizable -->
        <x-produccion-proceso-header titulo="Engomado" />
    </div>

    <div class="container mx-auto p-2 bg-white shadow-lg rounded-lg mt-1 overflow-y-auto md:h-[650px]">
        <div id="finalizadoOverlay">FINALIZADO</div>
        <!-- Formulario -->
        <form class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
            <!-- Primera columna -->
            <div class="text-sm">
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">FOLIO:</label>
                    <input id="folio" name="folio" type="text" class="w-2/6 border rounded p-1 text-xs font-bold"
                        value="{{ explode('-', $requerimiento->folio)[0] ?? '' }}" readonly />
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Cuenta:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold"
                        value="{{ $engomadoUrd->cuenta ?? '' }}" readonly />
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Urdido:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold"
                        value="{{ $engomadoUrd->urdido ?? '' }}" readonly />
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Destino:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold"
                        value="{{ $engomadoUrd->destino . ' ' . $requerimiento->telar ?? '' }}" readonly />
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Proveedor:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold"
                        value="{{ $engomadoUrd->proveedor ?? '' }}"readonly>
                </div>
            </div>

            <div class="text-sm">
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Engomado:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold" name="engomado"
                        value="{{ $engomadoUrd->maquinaEngomado ?? '' }}" required>
                </div>
                <label class="w-1/4 text-sm">Tipo:</label>
                <label class="text-sm text-black font-bold"><input type="radio" name="tipo" value="Rizo"
                        {{ $engomadoUrd->tipo === 'Rizo' ? 'checked' : '' }} disabled> Rizo</label>
                <label class="text-sm text-black font-bold ml-4"><input type="radio" name="tipo" value="Pie"
                        {{ $engomadoUrd->tipo === 'Pie' ? 'checked' : '' }} disabled> Pie</label>
                <div class="flex items-center mb-1 mt-2">
                    <label class="w-1/4 text-sm">Núcleo:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold"
                        value="{{ $engomadoUrd->nucleo ?? '' }}"readonly />
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">No. De Telas:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold"
                        value="{{ $engomadoUrd->no_telas ?? '' }}"readonly>
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Ancho Balonas:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold"
                        value="{{ $engomadoUrd->balonas ?? '' }}"readonly>
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Mts. De Telas:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold"
                        value="{{ floor($engomadoUrd->metros_tela) == $engomadoUrd->metros_tela ? intval($engomadoUrd->metros_tela) : $engomadoUrd->metros_tela }}
"readonly><!--Si es entero, lo convierte a entero con intval() (quita el .0), y si no es entero, lo deja como está (mantiene decimales como .5, .25, etc.).-->
                </div>
            </div>
            <div class="text-sm">
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Cuendeados Mín.:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold"
                        value="{{ $engomadoUrd->cuendados_mini ?? '' }}" />
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Observaciones:</label>
                    <textarea class="w-2/6 border rounded p-1 text-xs h-20 font-bold" name="observaciones">{{ $engomadoUrd->observaciones ?? '' }}</textarea>
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Color:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold" name="color"
                        value="{{ $engomadoUrd->color ?? '' }}" required>
                </div>
                <div class="flex items-center mb-1">
                    <label class="w-1/4 text-sm">Sólidos:</label>
                    <input type="text" class="w-2/6 border rounded p-1 text-xs font-bold" name="solidos"
                        value="{{ $engomadoUrd->solidos ?? '' }}" required>
                </div>
            </div>
        </form>

        <!-- Tabla de Datos -->
        <h2 class="text-sm font-bold mt-2">Registro de Producción</h2>
        <table class="w-full border-collapse border border-gray-300 mt-2">
            <thead>
                <tr class="bg-gray-200 text-xs">
                    <th class="border p-1">Fecha</th>
                    <th class="border p-1">Oficial</th>
                    <th class="border p-1">Turno</th>
                    <th class="border p-1">H. Inic.</th>
                    <th class="border p-1">H. Final</th>
                    <th class="border p-1">Tiempo</th>
                    <th class="border p-1">N° Julio</th>
                    <th class="border p-1">Kg. Bruto</th>
                    <th class="border p-1">Tara</th>
                    <th class="border p-1">Kg. Neto</th>
                    <th class="border p-1">Metros</th>
                    <th class="border p-1">Temp Canoa 1</th>
                    <th class="border p-1">Temp Canoa 2</th>
                    <th class="border p-1">Temp Canoa 3</th>
                    <th class="border p-1">Humedad</th>
                    <th class="border p-1">Roturas</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $registroIndex = 0;
                @endphp
                @for ($i = 0; $i < $engomadoUrd->no_telas; $i++)
                    @php
                        $orden = $engomado[$registroIndex] ?? null;
                        $registroIndex++;
                    @endphp
                    <tr class="text-xs">
                        <input type="hidden" name="datos[{{ $registroIndex }}][id2]" value="{{ $registroIndex }}">
                        <input type="hidden" name="datos[{{ $registroIndex }}][folio]"
                            value="{{ $requerimiento->orden_prod ?? '' }}">
                        <td class="border p-1">
                            <input class="w-24 p-1" type="date" name="datos[{{ $registroIndex }}][fecha]"
                                value="{{ $orden && $orden->fecha ? \Carbon\Carbon::parse($orden->fecha)->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d') }}">
                        </td>

                        <td class="border p-1 w-30">
                            <select class="w-24 border rounded p-1 text-xs" name="datos[{{ $registroIndex }}][oficial]"
                                id="oficial_{{ $registroIndex }}" onchange="updateOficialTipo({{ $registroIndex }})">
                                <option value="{{ Auth::user()->nombre }}">{{ Auth::user()->nombre }}</option>
                                @foreach ($oficiales as $of)
                                    <option value="{{ $of->oficial }}" data-tipo="{{ $of->tipo }}"
                                        @if (!empty($orden) && $of->oficial == $orden->oficial) selected @endif>
                                        {{ $of->oficial }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="border p-1"><input type="text" inputmode="numeric" pattern="[0-9]*"
                                name="datos[{{ $registroIndex }}][turno]" class="w-10 border rounded p-1 text-xs"
                                value="{{ $orden->turno ?? '' }}"></td>
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

                        <td class="border p-1"><input type="text" inputmode="numeric" pattern="[0-9]*"
                                name="datos[{{ $registroIndex }}][tiempo]" class="w-10 border rounded p-1 text-xs"
                                value="{{ $orden->tiempo ?? '' }}"></td>

                        <td class="border p-1 w-30">
                            <select class="w-24 border rounded p-1 text-xs" name="datos[{{ $registroIndex }}][no_julio]"
                                id="no_julio_{{ $registroIndex }}" onchange="updateValues({{ $registroIndex }})">
                                <option value="">Seleccionar</option>
                                @foreach ($julios as $julio)
                                    <option value="{{ $julio->no_julio }}" data-tara="{{ $julio->tara }}"
                                        data-tipo="{{ $julio->tipo }}" @if (!is_null($orden) && $julio->no_julio == $orden->no_julio) selected @endif>
                                        {{ $julio->no_julio }}
                                    </option>
                                @endforeach
                            </select>
                        </td>

                        <td class="border p-1">
                            <input class="w-10 border rounded p-1 text-xs" type="text" inputmode="numeric"
                                pattern="[0-9]*" name="datos[{{ $registroIndex }}][peso_bruto]"
                                value="{{ $orden->peso_bruto ?? '' }}" id="peso_bruto_{{ $registroIndex }}"
                                onchange="updatePesoNeto({{ $registroIndex }})">
                        </td>

                        <td class="border p-1">
                            <input class="w-14 p-1 text-xs" type="text" name="datos[{{ $registroIndex }}][tara]"
                                id="tara_{{ $registroIndex }}" value="{{ $orden->tara ?? '' }}" readonly>
                        </td>
                        <td class="border p-1">
                            <input class="w-14 p-1 text-xs" type="text" name="datos[{{ $registroIndex }}][peso_neto]"
                                id="peso_neto_{{ $registroIndex }}" value="{{ $orden->peso_neto ?? '' }}" readonly>
                        </td>

                        <td class="border p-1">
                            {{ floor($engomadoUrd->metros_tela) == $engomadoUrd->metros_tela ? intval($engomadoUrd->metros_tela) : $engomadoUrd->metros_tela }}
                            <input type="hidden" name="datos[{{ $registroIndex }}][metros]"
                                value="{{ floor($engomadoUrd->metros_tela) == $engomadoUrd->metros_tela ? intval($engomadoUrd->metros_tela) : $engomadoUrd->metros_tela }}">
                        </td>

                        <td class="border p-1"><input type="text" inputmode="numeric" pattern="[0-9]*"
                                name="datos[{{ $registroIndex }}][temp_canoa_1]" class="w-10 border rounded p-1 text-xs"
                                value="{{ $orden->temp_canoa_1 ?? '' }}"></td>
                        <td class="border p-1"><input type="text" inputmode="numeric" pattern="[0-9]*"
                                name="datos[{{ $registroIndex }}][temp_canoa_2]" class="w-10 border rounded p-1 text-xs"
                                value="{{ $orden->temp_canoa_2 ?? '' }}"></td>
                        <td class="border p-1"><input type="text" inputmode="numeric" pattern="[0-9]*"
                                name="datos[{{ $registroIndex }}][temp_canoa_3]" class="w-10 border rounded p-1 text-xs"
                                value="{{ $orden->temp_canoa_3 ?? '' }}"></td>
                        <td class="border p-1"><input type="text" inputmode="numeric" pattern="[0-9]*"
                                name="datos[{{ $registroIndex }}][humedad]" class="w-10 border rounded p-1 text-xs"
                                value="{{ $orden->humedad ?? '' }}"></td>
                        <td class="border p-1"><input type="text" inputmode="numeric" pattern="[0-9]*"
                                name="datos[{{ $registroIndex }}][roturas]" class="w-10 border rounded p-1 text-xs"
                                value="{{ $orden->roturas ?? '' }}"></td>
                    </tr>
                @endfor
            </tbody>
        </table>
        <div class="mt-4 text-right">
            @if ($engomadoUrd->estatus_engomado == 'en_proceso')
                <button id="Finalizar"
                    class="ml-10 btn bg-blue-600 text-white w-40 h-12 hover:bg-blue-400">Finalizar</button>
            @endif
            @if ($engomadoUrd->estatus_engomado == 'finalizado')
                <button onclick="reimprimir()" class="w-1/5 px-4 py-2 bg-green-600 text-white rounded">
                    🔁 Reimprimir
                </button>
            @endif
        </div>
    </div>

    <script>
        function reimprimir() {
            const folio = document.getElementById('folio').value;
            const url = "{{ url('/imprimir-orden/') }}/" + folio;
            const papeletas = "{{ url('/imprimir-papeletas-llenas/') }}/" + folio;
            window.open(url, '_blank');
            window.open(papeletas, '_blank');
        }
    </script>

    {{-- AUTOGUARDADO y FINALIZAR (como en URDIDO)  --}}
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
                fetch("{{ route('engomado.autoguardar') }}", {
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
                            fetch("{{ route('engomado.finalizar') }}", {
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
        </style>
    @endpush
@endsection
