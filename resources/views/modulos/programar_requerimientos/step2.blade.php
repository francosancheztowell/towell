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
                            @foreach ($agrupados as $i => $g)
                                @php $rowClass = $rowPalette[$i % count($rowPalette)]; @endphp
                                <tr class="{{ $rowClass }} agr-row cursor-pointer hover:bg-yellow-100"
                                    data-ids="{{ implode(',', $g->ids ?? []) }}" data-folio="{{ $g->folio ?? '' }}"
                                    data-cuenta="{{ $g->cuenta ?? '' }}" data-tipo="{{ $g->tipo ?? '' }}"
                                    data-destino="{{ $g->destino ?? '' }}" data-metros="{{ $g->metros ?? '' }}"
                                    data-urdido="{{ $g->urdido ?? '' }}">
                                    <td class="border px-0.5">{{ $g->telar_str }}</td>
                                    <td class="border px-0.5">
                                        {{ $g->fecha_requerida ? \Carbon\Carbon::parse($g->fecha_requerida)->format('d/m/Y') : '' }}
                                    </td>
                                    <td class="border px-0.5">{{ decimales($g->cuenta) }}</td>
                                    <td class="border px-0.5">{{ $g->calibre }}</td>
                                    <td class="border px-0.5">{{ $g->hilo }}</td>
                                    <td class="border px-0.5">{{ $g->urdido }}</td>
                                    <td class="border px-0.5">{{ $g->tipo }}</td>
                                    <td class="border px-0.5">{{ $g->destino }}</td>
                                    <td class="border px-0.5 text-right">{{ decimales($g->metros) }}</td>
                                    <td class="border px-0.5">
                                        @php
                                            $preId = old("agrupados.$i.lmaturdido", $g->lmaturdido_id ?? null);
                                            $preText =
                                                old("agrupados.$i.lmaturdido_text", $g->lmaturdido_text ?? null) ??
                                                $preId;
                                        @endphp
                                        <select name="agrupados[{{ $i }}][lmaturdido]" class="js-bom-select"
                                            data-selected-id="{{ $preId ?? '' }}"
                                            data-selected-text="{{ $preText ?? '' }}">
                                            <option value=""></option>
                                            @if ($preId)
                                                <option value="{{ $preId }}" selected>{{ $preText }}</option>
                                            @endif
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
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
                        @foreach ($requerimientos as $req)
                            <input type="hidden" name="ids[]" value="{{ $req->id }}">
                        @endforeach
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
                    <form id="formOrdenes" method="POST" action="{{ route('orden.produccion.store') }}">
                        @csrf
                        @foreach ($requerimientos as $req)
                            <input type="hidden" name="ids[]" value="{{ $req->id }}">
                        @endforeach
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

                    <!-- (Opcional) Más botones con colores de muestra similares a la imagen -->
                    {{-- 
                    <button class="btn-candy btn-red"><span class="btn-text">ACCION ROJA</span><span class="btn-bubble"
                            aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path d="M9 6l6 6-6 6" />
                            </svg></span></button>
                    <button class="btn-candy btn-yellow"><span class="btn-text">ACCION AMARILLA</span><span
                            class="btn-bubble" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 6l6 6-6 6" />
                            </svg></span></button>
                    --}}
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
                                    // Ajusta $registro por la variable que uses en tu vista (ej. $ordenCompleta, $engo, etc.)
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

                // Si trae data-selected-id pero no existe un <option selected> con texto,
                // podríamos cargarlo por AJAX (opcional). Si no tienes endpoint por id, omite este bloque.
                const selId = $el.data('selected-id');
                const selText = $el.data('selected-text');

                if (selId && !$el.find('option[value="' + selId + '"]').length) {
                    const opt = new Option(selText || selId, selId, true, true);
                    $el.append(opt);
                }

                $el.select2({
                    placeholder: 'Buscar BOM...',
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
                            })) // usa item.DESCRIPCION si la tienes
                        }),
                        cache: true
                    },
                    minimumInputLength: 1,
                    dropdownParent: $el.parent(), // estable dentro de celdas/scroll
                    width: '100%', // para este SELECT estamos modificando el ancho desde JS (ATENCIÓN)
                    // Si por cualquier motivo viene sin "text", mostramos el id
                    templateSelection: function(data, container) {
                        if (!data.id) return 'Buscar BOM...';
                        return data.text || data.id;
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
                            q: params.term, // texto del buscador
                            tipo: '{{ $g->tipo }}' // aquí se envía "Pie" o "Rizo" desde Blade
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

    {{-- ASIGNAR EL FOLIO DEL REGISTRO DE REQUERIMIENTO --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const tbody = document.querySelector('#agrupados-table tbody');
            let CURRENT_FOLIO = null;
            let CURRENT_TR = null;

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

            // =============== CLICK EN FILA: marcar + resolver/crear folio + upsert inicial y fetch ==========
            tbody.addEventListener('click', async (e) => {
                if (e.target.closest(
                        'select, .select2, .select2-container, input, textarea, button, a')) return;

                const tr = e.target.closest('tr.agr-row') || e.target.closest('tr');
                if (!tr) return;

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
                    // 3) Resolver/crear folio
                    const folio = await resolveOrCreateFolio(folioAttr, ids);
                    if (!folio) {
                        console.warn('No se pudo resolver folio');
                        return;
                    }
                    CURRENT_FOLIO = folio;

                    // Guárdalo en el data-* del TR por si vuelves a hacer click
                    tr.dataset.folio = folio;

                    // 4) Upsert + fetch inicial (garantiza registros base en urdido_engomado y construccion_urdido)
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

                    // 5) Log como pediste
                    console.log('FOLIO:', folio);
                    console.log('URDIDO_ENGOMADO:', data.engo || {});
                    console.log('CONSTRUCCION_URDIDO (filas):', data.construccion || []);
                    // 6) HIDRATAR las tablas inferiores con lo recuperado
                    hydrateConstruccion(data.construccion || []);
                    hydrateEngomado(data.engo || {});

                    // =============================== HIDRATAMOS LAS 2DAS TABLAS ==================================
                    function hydrateConstruccion(filas) {
                        // Recorre las 4 filas de #tbl-urdido y coloca no_julios / hilos
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
                        // Campos simples
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
                            // Si no es numérico, déjalo tal cual
                            const asNum = Number(str.replace(',', '.'));
                            if (!Number.isFinite(asNum)) return str;

                            // ¿Es numéricamente 0 y además está formado solo por ceros y/o decimales de ceros?
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

                        // Select2: L Mat Engomado (#bomSelect2)
                        const lme = (engo.lmatengomado ?? '');
                        if (window.$ && $('#bomSelect2').length) {
                            if (lme) {
                                // si no existe esa opción en el dropdown, la inyectamos y seleccionamos
                                if (!$('#bomSelect2').find(`option[value="${lme}"]`).length) {
                                    $('#bomSelect2').append(new Option(lme, lme, true, true));
                                }
                                $('#bomSelect2').val(lme).trigger('change');
                            } else {
                                $('#bomSelect2').val(null).trigger('change');
                            }
                        } else {
                            // fallback sin jQuery
                            const el = document.getElementById('bomSelect2');
                            if (el) el.value = lme || '';
                        }
                    }
                } catch (err) {
                    console.error(err);
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
                    // Opcional: toast o console
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

            // Bind a inputs/textarea/select
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

            // select2 de L Mat Engomado
            if (window.$ && $('#bomSelect2').length) {
                $('#bomSelect2').on('select2:select select2:clear change', saveEngomado);
            } else {
                const el = document.getElementById('bomSelect2');
                if (el) el.addEventListener('change', saveEngomado);
            }

            const formReservar = document.getElementById('formReservar');
            if (formReservar) {
                formReservar.addEventListener('submit', async (e) => {
                    const okFolios = await ensureFoliosAndValidateBeforeSubmit(formReservar);
                    if (!okFolios) return;
                    // Debe existir una fila seleccionada
                    if (!window.CURRENT_TR && typeof CURRENT_TR === 'undefined') {
                        // por si el scope cambia, intenta usar global; si no, bloquea
                        e.preventDefault();
                        alert('Por favor seleccione una fila');
                        return;
                    }
                    const trSel = (typeof CURRENT_TR !== 'undefined' && CURRENT_TR) ? CURRENT_TR :
                        window
                        .CURRENT_TR;
                    if (!trSel) {
                        e.preventDefault();
                        if (typeof warnSelectRow === 'function') warnSelectRow();
                        else alert('Por favor seleccione una fila');
                        return;
                    }

                    // Ubicar el select de L.Mat Urdido dentro de la fila
                    const sel = trSel.querySelector('select.js-bom-select');
                    let lmaturdidoVal = '';
                    if (sel) {
                        // Soporta Select2 o nativo
                        if (window.$ && $(sel).hasClass('select2-hidden-accessible')) {
                            lmaturdidoVal = $(sel).val() || '';
                        } else {
                            lmaturdidoVal = sel.value || '';
                        }
                    }

                    // Insertar/actualizar el hidden en el form
                    let hidden = formReservar.querySelector('input[name="lmaturdido"]');
                    if (!hidden) {
                        hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'lmaturdido';
                        formReservar.appendChild(hidden);
                    }
                    hidden.value = lmaturdidoVal;
                    // Listo: continúa el submit
                    formReservar.submit();
                });
            }
            //CREAR ÓRDENES
            // 1) Recolecta TODOS los folios de #agrupados-table, resolviendo los que falten
            async function collectAllFoliosEnsured() {
                const trs = Array.from(document.querySelectorAll('#agrupados-table tbody tr'));
                const folios = [];

                for (const tr of trs) {
                    let folio = (tr.dataset.folio || '').trim();
                    if (!folio) {
                        // resuelve/crea con sus ids
                        const ids = (tr.dataset.ids || '').split(',').map(s => s.trim()).filter(Boolean);
                        folio = await resolveOrCreateFolio('', ids);
                        if (folio) {
                            tr.dataset.folio = folio;
                        }
                    }
                    if (folio) folios.push(folio);
                }
                // únicos
                return [...new Set(folios)];
            }

            // 2) Llama al backend para validar campos por folio
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

            // 3) Inyecta inputs hidden folios[] en el form destino
            function injectFoliosIntoForm(form, folios) {
                // limpia previos
                form.querySelectorAll('input[name="folios[]"]').forEach(e => e.remove());
                folios.forEach(f => {
                    const h = document.createElement('input');
                    h.type = 'hidden';
                    h.name = 'folios[]';
                    h.value = f;
                    form.appendChild(h);
                });
            }

            // 4) Flujo completo: asegurar/validar/inyectar, y continuar submit
            async function ensureFoliosAndValidateBeforeSubmit(form) {
                // recolecta/asegura folios
                const folios = await collectAllFoliosEnsured();
                if (!folios.length) {
                    if (window.Swal) Swal.fire('Sin folios', 'No hay registros con folio en la tabla.',
                        'warning');
                    else alert('No hay registros con folio en la tabla.');
                    return false;
                }

                // valida en el back
                const {
                    ok,
                    data
                } = await validateFoliosOnServer(folios);
                if (!ok) {
                    // arma mensaje bonito
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

                // ok: inyecta y sigue
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
        });
    </script>

    @push('styles')
        <style>
            /* Botón “píldora” con burbuja blanca a la derecha */
            .btn-candy {
                --from: #60a5fa;
                /* fallback */
                --to: #2563eb;
                /* fallback */
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: .75rem;
                padding: .1rem 1.7rem .1rem 1rem;
                /* margen para la burbuja derecha */
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
                /* tono como en ejemplo rosa/fucsia */
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

            /* Paletas tipo “candy” (muy similares a las de tus imágenes) */
            .btn-teal {
                --from: #2fd5d3;
                --to: #0ea5a6;
            }

            /* verde/agua */
            .btn-blue {
                --from: #4facfe;
                --to: #2563eb;
            }

            /* azul */
            .btn-red {
                --from: #ff5e62;
                --to: #d00000;
            }

            /* rojo */
            .btn-yellow {
                --from: #f6d365;
                --to: #f7b733;
            }

            /* Variante gris + burbuja a la izquierda para VOLVER */
            .btn-gray {
                --from: #e5e7eb;
                /* gris claro */
                --to: #9ca3af;
                /* gris medio */
                color: #111827;
                /* texto oscuro para mejor contraste */
            }

            .btn-gray .btn-text {
                text-shadow: none;
            }

            .btn-gray .btn-bubble {
                color: #6b7280;
            }

            /* flecha gris */

            .btn-left {
                padding: .1rem 1.2rem .1rem 3.4rem;
                /* espacio a la izquierda para la burbuja */
            }

            .btn-left .btn-bubble {
                left: .35rem;
                right: auto;
                /* mueve la burbuja a la izquierda */
            }

            .btn-left:hover .btn-bubble svg {
                transform: translateX(-2px);
            }

            /* === Skin azul como la tabla anterior (sin tocar tu estructura) === */
            #agrupados-table {
                border-color: #bfdbfe;
            }

            /* blue-200 */
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
                /* respeta tu px-0.5 de lados */
            }

            /* esquinas redondeadas arriba */
            #agrupados-table thead th:first-child {
                border-top-left-radius: 16px;
            }

            #agrupados-table thead th:last-child {
                border-top-right-radius: 16px;
            }

            /* Celdas cuerpo */
            #agrupados-table tbody td {
                background: rgba(255, 255, 255, .98);
                border-color: #bfdbfe;
                color: #0f172a;
                /* slate-900 */
            }

            /* Hover suave como la otra tabla */
            #agrupados-table tbody tr:hover td {
                background: #eef6ff;
                transition: background-color .15s ease;
            }

            /* Limitar tamaño general (que no se vea gigantesca) */
            .max-w-\[980px] table {
                font-size: 0.75rem;
            }

            /* ya usas text-xs, reforzamos proporción */
            /* Amarillo fuertecito + borde */
            .row-selected>td {
                background: linear-gradient(90deg, #fde047, #facc15) !important;
                /* amber-300 → amber-400 */
                transition: background-color .15s ease;
            }

            .row-selected {
                outline: 2px solid #f59e0b;
                /* amber-500 */
                outline-offset: -2px;
            }
        </style>
    @endpush
@endsection
