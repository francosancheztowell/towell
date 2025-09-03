@extends('layouts.app', ['ocultarBotones' => true])

@section('content')
    <!-- Vista del formulario para registrar datos de URDIDO y ENGOMADO, además Construcción JULIOS -->
    <div class="mt-3 mb-20 p-1 overflow-y-auto max-h-[550px] ">

        {{-- Mostrar errores de validación --}}
        <form id="formStep1" method="POST" action="{{ route('urdido.step2') }}" method="POST"> {{-- ANTES:  action="{{ route('orden.produccion.store') }}" --}}
            @csrf
            {{-- Mostrar errores de validación --}}
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <strong class="font-bold">Lo sentimos, ocurrió un problema:</strong>
                    <ul class="list-disc pl-5 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Mostrar mensaje de error personalizado --}}
            @if (session('error'))
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4">
                    <strong class="font-bold">Advertencia:</strong> {{ session('error') }}
                </div>
            @endif

            {{-- Mostrar mensaje de éxito --}}
            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    <strong class="font-bold">¡Operación exitosa!</strong> {{ session('success') }}
                </div>
            @endif
            @php
                $tipo = '';
                $cuenta = '';

                if (($requerimiento->rizo ?? null) == 1) {
                    $tipo = 'Rizo';
                    $cuenta = $requerimiento->cuenta_rizo ?? '';
                } elseif (($requerimiento->pie ?? null) == 1) {
                    $tipo = 'Pie';
                    $cuenta = $requerimiento->cuenta_pie ?? '';
                }

                // Paleta PRO (colores más vivos y corporativos)
                $rowPalette = [
                    'bg-[#5EEAD4]', // teal-300
                    'bg-[#FCD34D]', // amber-300
                    'bg-[#6EE7B7]', // emerald-300
                    'bg-[#FDA4AF]', // rose-300
                    'bg-[#C4B5FD]', // violet-300
                    'bg-[#A5B4FC]', // indigo-300
                    'bg-[#7DD3FC]', // sky-300
                    'bg-[#93C5FD]', // blue-300
                ];
            @endphp
            {{-- === CONTENEDOR/TARJETA === --}}
            <section class="">
                <div class="rounded-[26px] overflow-hidden border border-blue-200 shadow-2xl bg-white/90 backdrop-blur">

                    {{-- Título / banda superior --}}
                    <div class="px-3 py-1 text-white font-extrabold tracking-wide"
                        style="background:linear-gradient(90deg,#6683f7,#104f97,#60a5fa,#3b82f6,#2563eb,#1d4ed8);">
                        PLANEACIÓN DE REQUERIMIENTOS
                    </div>

                    {{-- Tabla --}}
                    <div class="  overflow-auto">
                        <table id="tabla-requerimientos" class=" w-full text-xs">
                            <thead>
                                <tr class="text-white uppercase text-[11px] tracking-wider"
                                    style="background:linear-gradient(90deg,#6683f7,#104f97,#60a5fa,#3b82f6,#2563eb,#1d4ed8);">
                                    <th class="th w-12">Telar</th>
                                    <th class="th w-24">Fecha Req</th>
                                    <th class="th w-16">Cuenta</th>
                                    <th class="th w-16">Calibre</th>
                                    <th class="th w-16">Hilo</th>
                                    <th class="th w-24">Urdido</th>
                                    <th class="th w-16">Tipo</th>
                                    <th class="th w-24">Destino</th>
                                    <th class="th w-24">Tipo Atado</th>
                                    <th class="th w-16 text-right">Metros</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-blue-100/70">
                                @foreach ($requerimientos as $index => $req)
                                    {{-- Fila --}}
                                    <tr class="tr-row">
                                        {{-- Telar --}}
                                        <td class="td text-center">
                                            <input type="hidden" name="registros[{{ $index }}][folio]"
                                                value="{{ $req->folio }}">
                                            <input type="text" name="registros[{{ $index }}][telar]"
                                                value="{{ $req->telar ?? '' }}" class="inpt w-full">
                                        </td>

                                        {{-- Fecha requerida --}}
                                        <td class="td text-center">
                                            <input type="date" name="registros[{{ $index }}][fecha_requerida]"
                                                value="{{ $req->fecha ? \Carbon\Carbon::parse($req->fecha)->format('Y-m-d') : '' }}"
                                                class="inpt w-full">
                                        </td>

                                        {{-- Cuenta --}}
                                        <td class="td text-center">
                                            <input type="text" name="registros[{{ $index }}][cuenta]"
                                                value="{{ $req->rizo == 1 ? decimales($req->cuenta_rizo) : decimales($req->cuenta_pie) }}"
                                                class="inpt w-full">
                                        </td>

                                        {{-- Calibre --}}
                                        <td class="td text-center">
                                            {{ $req->rizo ? $req->calibre_rizo : $req->calibre_pie }}
                                        </td>

                                        {{-- Hilo --}}
                                        <td class="td text-center">
                                            {{ $req->hilo ?? '-' }}
                                        </td>

                                        {{-- Urdido (select) --}}
                                        <td class="td">
                                            @php
                                                // primero lo que venga de old(), si no, lo del back
                                                $selUrdido = old("registros.$index.urdido", $req->urdido ?? '');
                                            @endphp
                                            <select name="registros[{{ $index }}][urdido]" class="inpt w-full"
                                                required>
                                                <option value="" disabled {{ $selUrdido === '' ? 'selected' : '' }}>
                                                </option>
                                                <option value="Mc Coy 1" {{ $selUrdido === 'Mc Coy 1' ? 'selected' : '' }}>
                                                    Mc Coy 1</option>
                                                <option value="Mc Coy 2" {{ $selUrdido === 'Mc Coy 2' ? 'selected' : '' }}>
                                                    Mc Coy 2</option>
                                                <option value="Mc Coy 3" {{ $selUrdido === 'Mc Coy 3' ? 'selected' : '' }}>
                                                    Mc Coy 3</option>
                                            </select>
                                        </td>

                                        {{-- Tipo --}}
                                        <td class="td text-center">
                                            {{ $req->rizo ? 'Rizo' : ($req->pie ? 'Pie' : '-') }}
                                        </td>

                                        {{-- Destino --}}
                                        <td class="td text-center">
                                            <input type="text" name="registros[{{ $index }}][destino]"
                                                value="{{ $datos->salon ?? '' }}" class="inpt w-full">
                                        </td>

                                        {{-- Tipo Atado --}}
                                        <td class="td text-center">
                                            @php
                                                $selAtado = old("registros.$index.tipo_atado", $req->tipo_atado ?? '');
                                            @endphp
                                            <select name="registros[{{ $index }}][tipo_atado]" class="inpt w-full"
                                                required>
                                                <option value="" disabled {{ $selAtado === '' ? 'selected' : '' }}>
                                                </option>
                                                <option value="Normal" {{ $selAtado === 'Normal' ? 'selected' : '' }}>
                                                    Normal</option>
                                                <option value="Especial" {{ $selAtado === 'Especial' ? 'selected' : '' }}>
                                                    Especial</option>
                                            </select>
                                        </td>

                                        {{-- Metros --}}
                                        <td class="td text-right">
                                            <input type="number" name="registros[{{ $index }}][metros]"
                                                value="{{ decimales($req->metros) ?? '' }}" class="inpt w-full text-right"
                                                min="0" step="1" placeholder="0">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        {{-- Botón SIGUIENTE (píldora degradada) --}}
                        <div class="mt-5 flex justify-end">
                            <button type="submit"
                                onclick="this.disabled=true; this.innerText='Enviando...'; this.form.submit();"
                                class="btn-candy btn-indigo">
                                <span class="btn-text">SIGUIENTE</span>
                                <span class="btn-bubble" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path d="M9 6l6 6-6 6" />
                                    </svg>
                                </span>
                            </button>
                        </div>

                    </div>
                </div>
            </section>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const form = document.querySelector(
                        'form[action="{{ route('orden.produccion.store') }}"]'); // BOTON VOLVEEEEEEEER

                    form.addEventListener('submit', function(e) {
                        // Opcional: evitar envío para probar
                        e.preventDefault();
                        const formData = new FormData(form);
                        const data = {};
                        for (let [key, value] of formData.entries()) {
                            data[key] = value;
                        }
                        console.log("Datos enviados:", data);
                    });
                });
            </script>

            {{-- También mostrar alertas como pop-up en pantalla --}}
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    @if (session('error'))
                        alert("⚠️ {{ session('error') }}");
                    @endif

                    @if (session('success'))
                        alert("✅ {{ session('success') }}");
                    @endif

                    @if ($errors->any())
                        Swal.fire({
                            icon: 'warning',
                            title: '!ATENCIÓN!',
                            text: 'Por favor, revisa los errores del formulario.',
                            confirmButtonColor: '#f59e0b', // color ámbar
                            confirmButtonText: '  Entendido  '
                        });
                    @endif
                });
            </script>
            <!--busca BOMIDs para select2 de URDIDO-->
            <script>
                $('#bomSelect').select2({
                    placeholder: "Buscar BOM...",
                    ajax: {
                        url: '{{ route('bomids.api') }}',
                        dataType: 'json',
                        delay: 250,
                        processResults: function(data) {
                            return {
                                results: data.map(item => ({
                                    id: item.BOMID,
                                    text: item.BOMID
                                }))
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 1,
                    width: 'resolve'
                });
            </script>

            <!--busca BOMIDs para select2 de ENGOMADO-->
            <script>
                $(document).ready(function() {
                    $('#bomSelect2').select2({
                        placeholder: "Buscar lista...",
                        ajax: {
                            url: '{{ route('bomids.api2') }}',
                            dataType: 'json',
                            delay: 250,
                            data: function(params) {
                                return {
                                    q: params.term, // texto del buscador
                                    tipo: '{{ $tipo }}' // aquí se envía "Pie" o "Rizo" desde Blade
                                };
                            },
                            processResults: function(data) {
                                return {
                                    results: data.map(item => ({
                                        id: item.BOMID,
                                        text: item.BOMID
                                    }))
                                };
                            },
                            cache: true
                        },
                        minimumInputLength: 1,
                        width: 'resolve'
                    });
                });
            </script>

            {{-- ======= ESTILOS (mismo look que la otra vista) ======= --}}
            <style>
                /* Header redondeado */
                .modern-table thead th:first-child {
                    border-top-left-radius: 18px;
                }

                .modern-table thead th:last-child {
                    border-top-right-radius: 18px;
                }

                .th {
                    padding: .25rem .6rem;
                    white-space: nowrap;
                    font-weight: 800;
                    border-right: 1px solid rgba(78, 72, 72, 0.25);
                }

                .th:last-child {
                    border-right: none;
                }

                .td {
                    padding: .55rem .5rem;
                    background: rgba(255, 255, 255, .98);
                    border-left: 1px solid rgba(191, 219, 254, .6);
                    color: #0f172a;
                }

                .tr-row:hover .td {
                    background: #eef6ff;
                    transition: background-color .15s ease;
                }

                .modern-table tbody tr .td:last-child {
                    border-right: 1px solid rgba(191, 219, 254, .6);
                }

                /* Inputs/selects “glass” discretos */
                .inpt {
                    border: 1px solid #c7d2fe;
                    /* indigo-200 */
                    background: #ffffff;
                    border-radius: .5rem;
                    /* 8px */
                    padding: .38rem .45rem;
                    outline: none;
                    transition: border-color .15s ease, box-shadow .15s ease;
                }

                .inpt:focus {
                    border-color: #60a5fa;
                    /* sky-400 */
                    box-shadow: 0 0 0 3px rgba(96, 165, 250, .25);
                }

                /* Botón “píldora” degradado (igual que el otro) */
                .btn-candy {
                    --from: #4f46e5;
                    --to: #1d4ed8;
                    /* fallback — se sobreescribe por variante */
                    width: 200px;
                    position: relative;
                    display: inline-flex;
                    padding: .45rem 3.2rem .45rem 1.2rem;
                    border-radius: 9999px;
                    color: #fff;
                    font-weight: 800;
                    letter-spacing: .2px;
                    background: linear-gradient(145deg, var(--from), var(--to));
                    box-shadow: 0 10px 20px rgba(2, 6, 23, .18), inset 0 1px 0 rgba(255, 255, 255, .25);
                    transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
                }

                .btn-candy .btn-bubble {
                    position: absolute;
                    right: .35rem;
                    top: 50%;
                    transform: translateY(-50%);
                    width: 2.25rem;
                    height: 2.25rem;
                    border-radius: 9999px;
                    background: #fff;
                    color: #1d4ed8;
                    display: grid;
                    place-items: center;
                    box-shadow: 0 8px 16px rgba(0, 0, 0, .22);
                }

                .btn-candy:hover {
                    transform: translateY(-2px);
                    filter: saturate(1.05);
                }

                .btn-candy:hover .btn-bubble svg {
                    transform: translateX(2px);
                    transition: transform .18s ease;
                }

                /* Variantes */
                .btn-indigo {
                    --from: #6366f1;
                    --to: #1d4ed8;
                }

                /* degradado azul/índigo */
            </style>
        @endsection
