@extends('layouts.app')

@section('content')
    @php
        $step1 = collect(session('urdido.step1', [])); // id => { destino, metros, urdido, ... }

        // Paleta
        $rowPalette = [
            'bg-[#93C5FD]', // blue-300
            'bg-[#7DD3FC]', // sky-300
            'bg-[#5EEAD4]', // teal-300
            'bg-[#6EE7B7]', // emerald-300
            'bg-[#FCD34D]', // amber-300
            'bg-[#FDA4AF]', // rose-300
            'bg-[#C4B5FD]', // violet-300
            'bg-[#A5B4FC]', // indigo-300
        ];
    @endphp

    <div class="space-y-1">
        {{-- ===================== SEGUNDA PÁGINA (AGRUPADOS) ===================== --}}
        <div class="flex gap-4">
            <!-- Columna izquierda: TABLA -->
            <div class="flex-1"> <!-- límite de ancho para que no se vea enorme -->
                <div class="rounded-2xl border border-blue-200 shadow-xl bg-white/90 overflow-hidden">
                    <table id="agrupados-table"
                        class="w-full text-xs border-separate border-spacing-0 border border-gray-300 ml-1 pr-2">
                        <thead class="h-8">
                            <tr class="text-left text-white">
                                <th class="border px-0.5 w-[130px]">Telar</th>
                                <th class="border px-0.5 w-[70px]">Fec Req</th>
                                <th class="border px-0.5 w-[50px]">Cuenta</th>
                                <th class="border px-0.5 w-[50px]">Calibre</th>
                                <th class="border px-0.5 w-[50px]">Hilo</th>
                                <th class="border px-0.5 w-[70px]">Urdido</th>
                                <th class="border px-0.5 w-[50px]">Tipo</th>
                                <th class="border px-0.5 w-[70px]">Destino</th>
                                <th class="border px-0.5 w-[70px]">Metros</th>
                                <th class="border px-0.5 w-[180px]">L.Mat Urdido</th>
                            </tr>
                        </thead>

                        <tbody>
                            @php
                                // 1) Tomar $agrupados si trae datos
                                $rows = collect($agrupados ?? [])
                                    ->filter()
                                    ->values();

                                // 2) Si no hay $agrupados, mapear $uePorFolio -> mismo shape que la tabla
                                if ($rows->isEmpty()) {
                                    $rows = collect($ue ?? [])
                                        ->filter() // quita nulls
                                        ->map(function ($ue, $folio) {
                                            // Soporta array u objeto
                                            $get = function ($obj, $k) {
                                                if (is_array($obj)) {
                                                    return $obj[$k] ?? null;
                                                }
                                                if (is_object($obj)) {
                                                    return $obj->{$k} ?? null;
                                                }
                                                return null;
                                            };
                                            return (object) [
                                                'ids' => [],
                                                'folio' => $folio,
                                                'telar_str' => $get($ue, 'telar') ?? '',
                                                'fecha_requerida' => $get($ue, 'fecha_req'), // puede venir null
                                                'cuenta' => $get($ue, 'cuenta'),
                                                'calibre' => $get($ue, 'calibre'),
                                                'hilo' => $get($ue, 'hilo'),
                                                'urdido' => $get($ue, 'urdido'),
                                                'tipo' => $get($ue, 'tipo'),
                                                'destino' => $get($ue, 'destino'),
                                                'metros' => $get($ue, 'metros'),
                                                // En UE normalmente es "lmaturdido"; lo usamos como id/texto preseleccionado
                                                'lmaturdido_id' => $get($ue, 'lmaturdido'),
                                                'lmaturdido_text' => $get($ue, 'lmaturdido'),
                                            ];
                                        })
                                        ->values();
                                }
                            @endphp

                            @if ($rows->isEmpty())
                                <tr>
                                    <td colspan="10" class="text-center py-2 text-gray-500">Sin datos</td>
                                </tr>
                            @else
                                @foreach ($rows as $i => $g)
                                    @php $rowClass = $rowPalette[$i % count($rowPalette)]; @endphp
                                    <tr class="{{ $rowClass }} agr-row cursor-pointer hover:bg-yellow-100"
                                        data-ids="{{ is_array($g->ids ?? null) ? implode(',', $g->ids) : $g->ids ?? '' }}"
                                        data-folio="{{ $g->folio ?? '' }}" data-cuenta="{{ $g->cuenta ?? '' }}"
                                        data-tipo="{{ $g->tipo ?? '' }}" data-destino="{{ $g->destino ?? '' }}"
                                        data-metros="{{ $g->metros ?? '' }}" data-urdido="{{ $g->urdido ?? '' }}">
                                        <td class="border px-0.5">{{ $g->telar_str ?? '' }}</td>
                                        <td class="border px-0.5">
                                            {{ !empty($g->fecha_requerida) ? \Carbon\Carbon::parse($g->fecha_requerida)->format('d/m/Y') : '' }}
                                        </td>
                                        <td class="border px-0.5">{{ isset($g->cuenta) ? decimales($g->cuenta) : '' }}</td>
                                        <td class="border px-0.5">{{ $g->calibre ?? '' }}</td>
                                        <td class="border px-0.5">{{ $g->hilo ?? '' }}</td>
                                        <td class="border px-0.5">{{ $g->urdido ?? '' }}</td>
                                        <td class="border px-0.5">{{ $g->tipo ?? '' }}</td>
                                        <td class="border px-0.5">{{ $g->destino ?? '' }}</td>
                                        <td class="border px-0.5 text-right">
                                            {{ isset($g->metros) ? decimales($g->metros) : '' }}</td>
                                        <td class="border px-0.5">
                                            @php
                                                $preId = old("agrupados.$i.lmaturdido", $g->lmaturdido_id ?? null);
                                                $preText =
                                                    old("agrupados.$i.lmaturdido_text", $g->lmaturdido_text ?? null) ??
                                                    $preId;

                                                // Opciones disponibles según folio
                                                $opciones = collect($lmaturdidos[$g->folio] ?? [])
                                                    ->filter()
                                                    ->unique()
                                                    ->values();
                                            @endphp

                                            <select name="agrupados[{{ $i }}][lmaturdido]"
                                                class="js-bom-select w-full" data-selected-id="{{ $preId ?? '' }}"
                                                data-selected-text="{{ $preText ?? '' }}">
                                                <option value=""></option>
                                                @foreach ($opciones as $op)
                                                    <option value="{{ $op }}"
                                                        {{ $op == $preId ? 'selected' : '' }}>
                                                        {{ $op }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Columna derecha: BOTONES -->
        <section class="ml-[620px] w-[600px]">
            <div class="rounded-3xl bg-blue-600/90 p-1 sm:p-8 shadow-2xl">
                <div class="flex flex-wrap justify-end gap-2 ">

                    <!-- VOLVER -->
                    <a href="{{ url()->previous() }}" class="btn-candy btn-gray btn-left">
                        <span class="btn-bubble" aria-hidden="true">
                            <!-- Flecha hacia la izquierda -->
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M15 6l-6 6 6 6" />
                            </svg>
                        </span>
                        <span class="btn-text">VOLVER</span>
                    </a>

                    <!-- Reservar inventario -->
                    <form id="formReservar" method="POST" action="{{ route('urdido.step3') }}">{{-- reservar.inventario - RUTA ANTERIOR --}}
                        @csrf
                        <button type="submit" class="btn-candy btn-teal">
                            <span class="btn-text">RESERVAR INVENTARIO</span>
                            <span class="btn-bubble" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M9 6l6 6-6 6" />
                                </svg>
                            </span>
                        </button>
                    </form>

                    <!-- Crear órdenes -->
                    <form id="formOrdenes" method="POST" action="{{ route('crear.ordenes.lanzador') }}">
                        @csrf
                        {{-- @foreach ($requerimientos as $req)
                            <input type="hidden" name="ids[]" value="{{ $req->id }}">
                        @endforeach --}}
                        <button type="submit" class="btn-candy btn-blue">
                            <span class="btn-text">CREAR ÓRDENES</span>
                            <span class="btn-bubble" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M9 6l6 6-6 6" />
                                </svg>
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- ====== Bloques inferiores  CONS URDIDO Y ENGOMADO ====== --}}
        <div class="flex space-x-1">
            <div class="w-1/6 p-1">
                <div class="rounded-2xl border border-blue-200 shadow-xl bg-white/90 overflow-hidden">
                    <div class="flex">
                        <table id="tbl-urdido"
                            class="modern-table w-full text-xs border-collapse border border-gray-300 mb-4">
                            <thead class="h-10">
                                <tr class="text-center text-white"
                                    style="background:linear-gradient(90deg,#6683f7,#104f97,#60a5fa,#3b82f6,#2563eb,#1d4ed8);">
                                    <th colspan="2" class="th">CONTRUCCIÓN URDIDO</th>
                                </tr>
                                <tr class="text-center text-white"
                                    style="background:linear-gradient(90deg,#6683f7,#104f97,#60a5fa,#3b82f6,#2563eb,#1d4ed8);">
                                    <th class="th border px-1 py-0.5">No. Julios</th>
                                    <th class="th border px-1 py-0.5">Hilos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for ($i = 0; $i < 4; $i++)
                                    <tr>
                                        <td class="td border px-1 py-0.5">
                                            <input type="text" inputmode="numeric" pattern="[0-9]*"
                                                name="no_julios[]"
                                                class="inpt form-input px-1 py-0.5 text-[10px] rounded w-full"
                                                value="{{ old('no_julios.' . $i) }}">
                                        </td>
                                        <td class="td border px-1 py-0.5">
                                            <input type="text" inputmode="numeric" pattern="[0-9]*" name="hilos[]"
                                                class="inpt form-input px-1 py-0.5 text-[10px] rounded w-full"
                                                value="{{ old('hilos.' . $i) }}">
                                        </td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="w-5/6 p-1">
                <div class="rounded-2xl border border-blue-200 shadow-xl bg-white/90 overflow-hidden">
                    <table id="tbl-engomado" class="modern-table w-full text-xs border-collapse border border-gray-300">
                        <thead class="h-4">
                            <tr class="text-center text-white"
                                style="background:linear-gradient(90deg,#6683f7,#104f97,#60a5fa,#3b82f6,#2563eb,#1d4ed8);">
                                <th class="th" colspan="8">DATOS DE ENGOMADO</th>
                            </tr>
                            <tr class="text-left text-white"
                                style="background:linear-gradient(90deg,#6683f7,#104f97,#60a5fa,#3b82f6,#2563eb,#1d4ed8);">
                                <th class="th border px-1">Núcleo</th>
                                <th class="th border px-1">No. de Telas</th>
                                <th class="th border px-1">Ancho Balonas</th>
                                <th class="th border px-1">Metraje de Telas</th>
                                <th class="th border px-1">Cuendeados Mín. por Tela</th>
                                <th class="th border px-1">Máquina Engomado</th>
                                <th class="th border px-1">L Mat Engomado</th>
                                <th class="th border px-1 w-1/4">Observaciones</th>
                            </tr>
                        </thead>
                        <tbody class="h-24">
                            <tr>
                                <td class="td border px-3 py-1">
                                    <select name="nucleo" class="inpt w-[150px]] py-1 text-xs rounded">
                                        <option value="" disabled {{ old('nucleo') ? '' : 'selected' }}></option>
                                        <option value="Itema" {{ old('nucleo') == 'Itema' ? 'selected' : '' }}>Itema
                                        </option>
                                        <option value="Smit" {{ old('nucleo') == 'Smit' ? 'selected' : '' }}>Smit
                                        </option>
                                        <option value="Jacquard"{{ old('nucleo') == 'Jacquard' ? 'selected' : '' }}>
                                            Jacquard</option>
                                    </select>
                                </td>
                                <td class="td border px-1 py-0.5">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="no_telas"
                                        class="inpt form-input w-full px-1 py-1 text-xs rounded"
                                        value="{{ old('no_telas') }}">
                                </td>
                                <td class="td border px-1 py-0.5">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="balonas"
                                        class="inpt form-input w-full px-1 py-1 text-xs rounded"
                                        value="{{ old('balonas') }}">
                                </td>
                                <td class="td border px-1 py-0.5">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="metros_tela"
                                        class="inpt form-input w-full px-1 py-1 text-xs rounded"
                                        value="{{ old('metros_tela') }}">
                                </td>
                                <td class="td border px-1 py-0.5">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="cuendados_mini"
                                        class="inpt form-input w-full px-1 py-1 text-xs rounded"
                                        value="{{ old('cuendados_mini') }}">
                                </td>
                                @php
                                    $sel = old('maquinaEngomado', $registro->maquinaEngomado ?? '');
                                @endphp

                                <td class="td border px-1 py-0.5">
                                    <select name="maquinaEngomado"
                                        class="form-select w-full px-1 py-1 text-xs border border-gray-300 rounded"
                                        required>
                                        <option value="" disabled @selected($sel === '')>Selecciona</option>
                                        <option value="West Point 2" @selected($sel === 'West Point 2')>West Point 2</option>
                                        <option value="West Point 3" @selected($sel === 'West Point 3')>West Point 3</option>
                                    </select>
                                </td>

                                <td class="td px-4 py-0.5">
                                    <select id="bomSelect2" name="lmatengomado"
                                        class="inpt py-1 text-xs w-[200px] rounded" required>
                                        <option value="" disabled selected>Selecciona una lista</option>
                                    </select>
                                </td>
                                <td class="td border px-4 py-1">
                                    <textarea name="observaciones" class="inpt form-textarea w-[220px] py-1 text-xs rounded h-16">{{ old('observaciones') }}</textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlBoms = @json(route('bomids.api'));

            $('.js-bom-select').each(function() {
                const $el = $(this);

                const selId = $el.data('selected-id');
                const selText = $el.data('selected-text');

                if (selId && !$el.find('option[value="' + selId + '"]').length) {
                    const opt = new Option(selText || selId, selId, true, true);
                    $el.append(opt);
                }

                $el.select2({
                    placeholder: 'Buscar Lista...',
                    allowClear: false,
                    ajax: {
                        url: urlBoms,
                        dataType: 'json',
                        delay: 250,
                        data: params => ({
                            q: params.term
                        }),
                        processResults: data => ({
                            results: data.map(item => ({
                                id: item.BOMID,
                                text: item.BOMID
                            }))
                        }),
                        cache: true
                    },
                    minimumInputLength: 1,
                    dropdownParent: $el.parent(),
                    width: '100%',
                    templateSelection: function(data, container) {
                        if (!data.id) return 'Buscar BOM...';
                        return data.text || data.id;
                    }
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

            async function persistLmaturdido(folio, valor) {
                const r = await fetch(`{{ route('urdido.autosave.lmaturdido') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        folio,
                        lmaturdido: valor || null
                    })
                });
                if (!r.ok) throw new Error('autosave lmaturdido failed');
                return r.json();
            }

            async function ensureFolioForTr(tr) {
                let folio = (tr.dataset.folio || '').trim();
                if (!folio) {
                    const ids = (tr.dataset.ids || '').split(',').map(s => s.trim()).filter(Boolean);
                    const url = new URL(`{{ route('prog.init.resolveFolio') }}`);
                    ids.forEach(id => url.searchParams.append('ids[]', id));
                    const r = await fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    if (!r.ok) return null;
                    const j = await r.json();
                    folio = j.folio || '';
                    if (folio) tr.dataset.folio = folio;
                }
                return folio || null;
            }

            $('.js-bom-select').each(function() {
                const $sel = $(this);

                $sel.on('select2:select select2:clear change', async function() {
                    try {
                        const tr = this.closest('tr');
                        if (!tr) return;

                        const folio = await ensureFolioForTr(tr);
                        if (!folio) {
                            console.warn('No se pudo resolver folio para la fila');
                            if (window.Swal) Swal.fire('Sin folio',
                                'No se pudo resolver el folio.', 'warning');
                            return;
                        }

                        let val = '';
                        if ($sel.hasClass('select2-hidden-accessible')) {
                            val = $sel.val() || '';
                        } else {
                            val = this.value || '';
                        }

                        await persistLmaturdido(folio, val);
                        console.log(`L.Mat Urdido guardado. Folio=${folio}, LMA=${val}`);
                    } catch (err) {
                        console.error(err);
                        if (window.Swal) Swal.fire('Error',
                            'No se pudo guardar L.Mat Urdido. Por favor de un clic en el registro para generar un Folio válido',
                            'error');
                    }
                });
            });
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
                            q: params.term,
                            tipo: '{{ $g->tipo ?? '' }}'
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

    {{-- ASIGNAR EL FOLIO DEL REGISTRO DE REQUERIMIENTO (con LOADER integrado) --}}
    <script>
        // Helpers loader global
        (function() {
            window.showLoader = function(msg = 'Cargando…') {
                const o = document.getElementById('pageLoader');
                if (!o) return;
                o.classList.add('show');
                const t = o.querySelector('#loaderText');
                if (t) t.textContent = msg;
            };
            window.hideLoader = function() {
                const o = document.getElementById('pageLoader');
                if (!o) return;
                o.classList.remove('show');
            };
        })();
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const tbody = document.querySelector('#agrupados-table tbody');
            let CURRENT_FOLIO = null;
            let CURRENT_TR = null;
            let ROW_LOADING = false; // 👈 candado para evitar doble click mientras carga

            if (!tbody) return;

            // Utilidades
            const toNumber = (txt) => {
                if (txt == null) return null;
                const s = String(txt).replace(/\s+/g, '').replace(/,/g, '');
                const n = Number(s);
                return Number.isFinite(n) ? n : null;
            };
            const debounce = (fn, ms = 450) => {
                let t;
                return (...a) => {
                    clearTimeout(t);
                    t = setTimeout(() => fn(...a), ms);
                };
            };
            const warnSelectRow = () => {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'info',
                        title: 'ATENCIÓN',
                        text: 'Por favor selecciona una lista de materiales de Urdido.',
                        confirmButtonText: 'ENTENDIDO'
                    });
                } else {
                    alert('Por favor seleccione una fila');
                }
            };

            // =============== CLICK EN FILA: marcar + resolver/crear folio + upsert/fetch + LOADER ==========
            tbody.addEventListener('click', async (e) => {
                if (e.target.closest(
                        'select, .select2, .select2-container, input, textarea, button, a')) return;
                if (ROW_LOADING) return;

                const tr = e.target.closest('tr.agr-row') || e.target.closest('tr');
                if (!tr) return;

                ROW_LOADING = true;
                showLoader('Cargando datos del folio…');

                // 1) Marcar visualmente la fila seleccionada
                if (CURRENT_TR) CURRENT_TR.classList.remove('row-selected');
                tr.classList.add('row-selected');
                CURRENT_TR = tr;

                // 2) Datos de la fila
                const ids = (tr.dataset.ids || '').split(',').map(s => s.trim()).filter(Boolean);
                const folioAttr = (tr.dataset.folio || '').trim();
                const filaData = {
                    cuenta: tr.dataset.cuenta || '',
                    tipo: tr.dataset.tipo || '',
                    destino: tr.dataset.destino || '',
                    metros: toNumber(tr.dataset.metros),
                    urdido: tr.dataset.urdido || '',
                    lmaturdido: (() => {
                        const sel = tr.querySelector('select.js-bom-select');
                        return sel ? (sel.value || '') : '';
                    })(),
                };

                try {
                    // 3) Resolver/crear FOLIO
                    const folio = await resolveOrCreateFolio(folioAttr, ids);
                    if (!folio) {
                        console.warn('No se pudo resolver folio');
                        if (window.Swal) await Swal.fire('Sin folio', 'No se pudo resolver el folio.',
                            'warning');
                        return;
                    }
                    CURRENT_FOLIO = folio;
                    tr.dataset.folio = folio;

                    // 4) Upsert + fetch inicial (garantiza registros base)
                    const r = await fetch(`{{ route('prog.init.upsertFetch') }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            folio,
                            ...filaData
                        })
                    });
                    if (!r.ok) throw new Error('upsertFetch failed');
                    const data = await r.json();

                    // 5) Log
                    console.log('FOLIO:', folio);
                    console.log('URDIDO_ENGOMADO:', data.engo || {});
                    console.log('CONSTRUCCION_URDIDO (filas):', data.construccion || []);

                    // 6) HIDRATAR las tablas inferiores
                    hydrateConstruccion(data.construccion || []);
                    hydrateEngomado(data.engo || {});

                    // --- Helpers de hidratación ---
                    function hydrateConstruccion(filas) {
                        const trs = document.querySelectorAll('#tbl-urdido tbody tr');
                        for (let i = 0; i < trs.length; i++) {
                            const tr = trs[i];
                            const nj = tr.querySelector('input[name="no_julios[]"]');
                            const hi = tr.querySelector('input[name="hilos[]"]');

                            nj && (nj.value = (filas[i]?.no_julios ?? ''));
                            hi && (hi.value = (filas[i]?.hilos ?? ''));
                        }
                    }

                    function hydrateEngomado(engo) {
                        const $nucleo = document.querySelector('select[name="nucleo"]');
                        const $no_telas = document.querySelector('input[name="no_telas"]');
                        const $balonas = document.querySelector('input[name="balonas"]');
                        const $metros_tela = document.querySelector('input[name="metros_tela"]');
                        const $cuend_min = document.querySelector('input[name="cuendados_mini"]');
                        const $maquinaEngomado = document.querySelector(
                            'select[name="maquinaEngomado"]');
                        const $obs = document.querySelector('textarea[name="observaciones"]');

                        function valueOrEmptyIfZero(val) {
                            if (val == null) return '';
                            const str = String(val).trim();
                            const asNum = Number(str.replace(',', '.'));
                            if (!Number.isFinite(asNum)) return str;
                            const zeroLike = /^-?\s*0+(?:[.,]0+)?\s*$/;
                            return (asNum === 0 && zeroLike.test(str)) ? '' : str;
                        }

                        function setInput(el, val) {
                            if (!el) return;
                            el.value = valueOrEmptyIfZero(val);
                        }

                        if ($nucleo) $nucleo.value = (engo.nucleo ?? '');
                        setInput($no_telas, engo.no_telas);
                        setInput($balonas, engo.balonas);
                        setInput($metros_tela, engo.metros_tela);
                        setInput($cuend_min, engo.cuendados_mini);
                        if ($maquinaEngomado) $maquinaEngomado.value = (engo.maquinaEngomado ?? '');
                        if ($obs) $obs.value = (engo.observaciones ?? '');

                        const lme = (engo.lmatengomado ?? '');
                        if (window.$ && $('#bomSelect2').length) {
                            if (lme) {
                                if (!$('#bomSelect2').find(`option[value="${lme}"]`).length) {
                                    $('#bomSelect2').append(new Option(lme, lme, true, true));
                                }
                                $('#bomSelect2').val(lme).trigger('change');
                            } else {
                                $('#bomSelect2').val(null).trigger('change');
                            }
                        } else {
                            const el = document.getElementById('bomSelect2');
                            if (el) el.value = lme || '';
                        }
                    }

                } catch (err) {
                    console.error(err);
                    if (window.Swal) await Swal.fire('Error', 'Ocurrió un problema al cargar la fila.',
                        'error');
                } finally {
                    hideLoader();
                    ROW_LOADING = false;
                }
            }, {
                passive: true
            });

            async function resolveOrCreateFolio(existingFolio, ids) {
                const url = new URL(`{{ route('prog.init.resolveFolio') }}`);
                if (existingFolio) url.searchParams.set('folio', existingFolio);
                ids.forEach(id => url.searchParams.append('ids[]', id));
                const r = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                if (!r.ok) return null;
                const j = await r.json();
                return j.folio || null;
            }

            // =============================== AUTOSAVE: CONSTRUCCIÓN URDIDO ==================================
            const saveConstruccion = debounce(async () => {
                if (!CURRENT_FOLIO) {
                    warnSelectRow();
                    return;
                }

                const rows = [];
                document.querySelectorAll('#tbl-urdido tbody tr').forEach(tr => {
                    rows.push({
                        no_julios: tr.querySelector('input[name="no_julios[]"]')
                            ?.value || '',
                        hilos: tr.querySelector('input[name="hilos[]"]')?.value || '',
                    });
                });

                try {
                    const res = await fetch(`{{ route('urdido.autosave.construccion') }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            folio: CURRENT_FOLIO,
                            filas: rows
                        })
                    });
                    if (!res.ok) throw new Error('autosaveConstruccion failed');
                    console.log('Construcción guardada (autosave)');
                } catch (e) {
                    console.error(e);
                }
            }, 450);

            document.querySelectorAll('#tbl-urdido input[name="no_julios[]"], #tbl-urdido input[name="hilos[]"]')
                .forEach(inp => {
                    inp.addEventListener('input', saveConstruccion);
                    inp.addEventListener('change', saveConstruccion);
                });

            // =============================== AUTOSAVE: DATOS DE ENGOMADO ====================================
            const collectEngomadoForm = () => ({
                nucleo: document.querySelector('select[name="nucleo"]')?.value || null,
                no_telas: document.querySelector('input[name="no_telas"]')?.value || null,
                balonas: document.querySelector('input[name="balonas"]')?.value || null,
                metros_tela: document.querySelector('input[name="metros_tela"]')?.value || null,
                cuendados_mini: document.querySelector('input[name="cuendados_mini"]')?.value || null,
                maquinaEngomado: document.querySelector('select[name="maquinaEngomado"]')?.value || null,
                lmatengomado: (window.$ ? $('#bomSelect2').val() : document.querySelector('#bomSelect2')
                    ?.value) || null,
                observaciones: document.querySelector('textarea[name="observaciones"]')?.value || null,
            });

            const saveEngomado = debounce(async () => {
                if (!CURRENT_FOLIO) {
                    warnSelectRow();
                    return;
                }

                const engo = collectEngomadoForm();

                try {
                    const res = await fetch(`{{ route('urdido.autosave.engomado') }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            folio: CURRENT_FOLIO,
                            ...engo
                        })
                    });
                    if (!res.ok) throw new Error('autosaveEngomado failed');
                    console.log('Engomado guardado (autosave)');
                } catch (e) {
                    console.error(e);
                }
            }, 450);

            const engSelectors = [
                'select[name="nucleo"]',
                'input[name="no_telas"]',
                'input[name="balonas"]',
                'input[name="metros_tela"]',
                'input[name="cuendados_mini"]',
                'select[name="maquinaEngomado"]',
                'textarea[name="observaciones"]'
            ];
            engSelectors.forEach(sel => {
                const el = document.querySelector(sel);
                if (!el) return;
                el.addEventListener('input', saveEngomado);
                el.addEventListener('change', saveEngomado);
            });

            if (window.$ && $('#bomSelect2').length) {
                $('#bomSelect2').on('select2:select select2:clear change', saveEngomado);
            } else {
                const el = document.getElementById('bomSelect2');
                if (el) el.addEventListener('change', saveEngomado);
            }

            // ====== Validación/inyectar folios para botones ======
            async function collectAllFoliosEnsured() {
                const trs = Array.from(document.querySelectorAll('#agrupados-table tbody tr'));
                const folios = [];

                for (const tr of trs) {
                    let folio = (tr.dataset.folio || '').trim();
                    if (!folio) {
                        const ids = (tr.dataset.ids || '').split(',').map(s => s.trim()).filter(Boolean);
                        folio = await resolveOrCreateFolio('', ids);
                        if (folio) tr.dataset.folio = folio;
                    }
                    if (folio) folios.push(folio);
                }
                return [...new Set(folios)];
            }

            async function validateFoliosOnServer(folios) {
                const r = await fetch(`{{ route('prog.validar.folios') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.content ||
                            ''),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        folios
                    })
                });
                const j = await r.json();
                return {
                    ok: r.ok,
                    data: j
                };
            }

            function injectFoliosIntoForm(form, folios) {
                form.querySelectorAll('input[name="folios[]"]').forEach(e => e.remove());
                folios.forEach(f => {
                    const h = document.createElement('input');
                    h.type = 'hidden';
                    h.name = 'folios[]';
                    h.value = f;
                    form.appendChild(h);
                });
            }

            async function ensureFoliosAndValidateBeforeSubmit(form) {
                const folios = await collectAllFoliosEnsured();
                if (!folios.length) {
                    if (window.Swal) Swal.fire('Sin folios', 'No hay registros con folio en la tabla.',
                        'warning');
                    else alert('No hay registros con folio en la tabla.');
                    return false;
                }

                const {
                    ok,
                    data
                } = await validateFoliosOnServer(folios);
                if (!ok) {
                    let html = '<ul style="text-align:left">';
                    Object.entries(data.errors || {}).forEach(([folio, arr]) => {
                        html += `<li><b>Folio ${folio}</b><ul>`;
                        arr.forEach(m => {
                            html += `<li>• ${m}</li>`;
                        });
                        html += '</ul></li>';
                    });
                    html += '</ul>';

                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'error',
                            title: 'Faltan datos obligatorios',
                            html,
                            confirmButtonText: 'Entendido'
                        });
                    } else {
                        console.error('Validación de folios falló:', data);
                        alert('Faltan datos en algunos folios. Revisa consola para detalle.');
                    }
                    return false;
                }

                injectFoliosIntoForm(form, folios);
                return true;
            }

            const formOrdenes = document.getElementById('formOrdenes');
            if (formOrdenes) {
                formOrdenes.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const okFolios = await ensureFoliosAndValidateBeforeSubmit(formOrdenes);
                    if (!okFolios) return;
                    formOrdenes.submit();
                });
            }

            // Exponemos ensureFolios... para el script de Reservar
            window.ensureFoliosAndValidateBeforeSubmit = ensureFoliosAndValidateBeforeSubmit;
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('formReservar');
            if (!form) return;

            form.addEventListener('submit', e => {
                const $ = window.$;
                const selects = [...document.querySelectorAll(
                    '#agrupados-table tbody select.js-bom-select')];
                const val = el => ($ && $(el).hasClass('select2-hidden-accessible') ? String($(el).val() ||
                    '') : String(el?.value || '')).trim();
                const vacios = selects.filter(s => val(s) === '');
                if (vacios.length) {
                    e.preventDefault();
                    if (window.Swal) Swal.fire({
                        icon: 'info',
                        title: 'Dato requerido',
                        text: `Faltan ${vacios.length} “L.Mat Urdido”.`
                    });
                    else alert(`Faltan ${vacios.length} “L.Mat Urdido”.`);
                    const f = vacios[0];
                    f?.closest('td')?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    if ($ && $(f).hasClass('select2-hidden-accessible')) $(f).select2('open');
                    else f?.focus();
                    return;
                }

                const rows = [...document.querySelectorAll('#agrupados-table tbody tr')].map((tr, i) => {
                    const td = [...tr.children].map(c => c.textContent.trim());
                    const sel = tr.querySelector('select.js-bom-select');
                    const v = val(sel);
                    const t = ($ && sel && $(sel).find(':selected').text()) || (sel?.options[sel
                        .selectedIndex]?.text || '');
                    return {
                        i,
                        // texto columnas
                        telar: td[0] || '',
                        fecha_req: td[1] || '',
                        cuenta: td[2] || '',
                        calibre: td[3] || '',
                        hilo: td[4] || '',
                        urdido_txt: td[5] || '',
                        tipo_txt: td[6] || '',
                        destino_txt: td[7] || '',
                        metros_txt: td[8] || '',
                        // select
                        lmaturdido_id: v,
                        lmaturdido_text: t,
                        // data-* crudos
                        ids: tr.dataset.ids || '',
                        folio: tr.dataset.folio || '',
                        cuenta_raw: tr.dataset.cuenta || '',
                        tipo_raw: tr.dataset.tipo || '',
                        destino_raw: tr.dataset.destino || '',
                        metros_raw: tr.dataset.metros || '',
                        urdido_raw: tr.dataset.urdido || ''
                    };
                });

                // inyecta hidden con todo el payload
                form.querySelectorAll('input[name="agrupados"]').forEach(n => n.remove());
                const h = document.createElement('input');
                h.type = 'hidden';
                h.name = 'agrupados';
                h.value = JSON.stringify(rows);
                form.appendChild(h);
            });
        });
    </script>

    {{-- ====== LOADER OVERLAY (HTML) ====== --}}
    <div id="pageLoader" class="loader-overlay" aria-hidden="true">
        <div class="loader-card">
            <div class="spinner" aria-hidden="true"></div>
            <div class="loader-copy">
                <div class="loader-title">Cargando…</div>
                <div id="loaderText" class="loader-sub">Preparando datos de la fila seleccionada</div>
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            /* Botón “píldora” con burbuja blanca a la derecha */
            .btn-candy {
                --from: #60a5fa;
                --to: #2563eb;
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: .75rem;
                padding: .1rem 1.7rem .1rem 1rem;
                border-radius: 9999px;
                color: #fff;
                font-weight: 600;
                letter-spacing: .2px;
                background: linear-gradient(145deg, var(--from), var(--to));
                box-shadow: 0 10px 20px rgba(0, 0, 0, .18), inset 0 1px 0 rgba(255, 255, 255, .25);
                transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
                outline: none;
            }

            .btn-candy .btn-text {
                white-space: nowrap;
                text-shadow: 0 1px 0 rgba(0, 0, 0, .15);
            }

            .btn-candy .btn-bubble {
                position: absolute;
                right: .1rem;
                top: 50%;
                transform: translateY(-50%);
                width: 1.5rem;
                height: 1.5rem;
                border-radius: 9999px;
                background: #fff;
                color: #ff3d7b;
                display: grid;
                place-items: center;
                box-shadow: 0 8px 16px rgba(0, 0, 0, .22);
                transition: transform .18s ease;
            }

            .btn-candy:hover,
            .btn-candy:focus-visible {
                transform: translateY(-2px);
                box-shadow: 0 14px 28px rgba(0, 0, 0, .22), inset 0 1px 0 rgba(255, 255, 255, .3);
                filter: saturate(1.05);
            }

            .btn-candy:hover .btn-bubble,
            .btn-candy:focus-visible .btn-bubble {
                transform: translateY(-50%) scale(1.06);
            }

            .btn-candy .btn-bubble svg {
                transition: transform .18s ease;
            }

            .btn-candy:hover .btn-bubble svg {
                transform: translateX(2px);
            }

            .btn-teal {
                --from: #2fd5d3;
                --to: #0ea5a6;
            }

            .btn-blue {
                --from: #4facfe;
                --to: #2563eb;
            }

            .btn-red {
                --from: #ff5e62;
                --to: #d00000;
            }

            .btn-yellow {
                --from: #f6d365;
                --to: #f7b733;
            }

            .btn-gray {
                --from: #e5e7eb;
                --to: #9ca3af;
                color: #111827;
            }

            .btn-gray .btn-text {
                text-shadow: none;
            }

            .btn-gray .btn-bubble {
                color: #6b7280;
            }

            .btn-left {
                padding: .1rem 1.2rem .1rem 3.4rem;
            }

            .btn-left .btn-bubble {
                left: .35rem;
                right: auto;
            }

            .btn-left:hover .btn-bubble svg {
                transform: translateX(-2px);
            }

            #agrupados-table {
                border-color: #bfdbfe;
            }

            #agrupados-table thead tr {
                background: linear-gradient(90deg, #6683f7, #104f97, #60a5fa, #3b82f6, #2563eb, #1d4ed8);
            }

            #agrupados-table thead th {
                color: #fff;
                font-weight: 800;
                letter-spacing: .02em;
                white-space: nowrap;
                border-color: rgba(255, 255, 255, .25);
                padding-top: .45rem;
                padding-bottom: .45rem;
            }

            #agrupados-table thead th:first-child {
                border-top-left-radius: 16px;
            }

            #agrupados-table thead th:last-child {
                border-top-right-radius: 16px;
            }

            #agrupados-table tbody td {
                background: rgba(255, 255, 255, .98);
                border-color: #bfdbfe;
                color: #0f172a;
            }

            #agrupados-table tbody tr:hover td {
                background: #eef6ff;
                transition: background-color .15s ease;
            }

            .max-w-\[980px] table {
                font-size: 0.75rem;
            }

            .row-selected>td {
                background: linear-gradient(90deg, #fde047, #facc15) !important;
                transition: background-color .15s ease;
            }

            .row-selected {
                outline: 2px solid #f59e0b;
                outline-offset: -2px;
            }

            .cell-error {
                outline: 2px solid #ef4444;
                outline-offset: -2px;
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25);
                transition: box-shadow .15s ease, outline-color .15s ease;
            }

            /* === Loader overlay === */
            .loader-overlay {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: none;
                align-items: center;
                justify-content: center;
                background: rgba(255, 255, 255, .6);
                backdrop-filter: blur(2px);
            }

            .loader-overlay.show {
                display: flex;
            }

            .loader-card {
                display: flex;
                align-items: center;
                gap: .75rem;
                padding: .85rem 1rem;
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 10px 20px rgba(0, 0, 0, .15);
                border: 1px solid rgba(99, 102, 241, .15);
            }

            .spinner {
                width: 26px;
                height: 26px;
                border-radius: 50%;
                border: 3px solid #93c5fd;
                border-top-color: transparent;
                animation: spin 1s linear infinite;
            }

            .loader-title {
                font-weight: 800;
                font-size: 12px;
                color: #1e293b;
                line-height: 1.1;
            }

            .loader-sub {
                font-size: 11px;
                color: #475569;
                margin-top: 2px;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }
        </style>
    @endpush
@endsection
