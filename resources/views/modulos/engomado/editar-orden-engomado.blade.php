@extends('layouts.app')

@section('page-title', 'Editar Orden Engomado')

@section('navbar-right')
    @php
        $statusClass = match($orden->Status ?? '') {
            'Finalizado' => 'bg-green-100 text-green-800',
            'En Proceso' => 'bg-yellow-100 text-yellow-800',
            'Programado' => 'bg-blue-100 text-blue-800',
            'Parcial' => 'bg-amber-100 text-amber-800',
            'Cancelado' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    @endphp
    <div class="flex items-center gap-2">
        <span class="px-3 py-2 text-md font-bold rounded-full {{ $statusClass }}">
            {{ $orden->Status ?? '-' }}
        </span>
    </div>
@endsection

@section('content')
    <div class="w-full">
        @php
            $statusActual = trim($orden->Status ?? '');
            $mostrarTablaProduccion = in_array($statusActual, ['Finalizado', 'En Proceso', 'Parcial'], true);
            $permiteEditarNoTelas = in_array($statusActual, ['En Proceso', 'Programado', 'Parcial'], true);
        @endphp

        <div class="bg-white p-3 mb-4">
            {{-- Fila 1 --}}
            <div class="grid gap-1.5 mb-1.5" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr));">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Folio <span class="text-red-500">*</span></label>
                    <input type="text" value="{{ $orden->Folio }}" readonly disabled
                        class="w-full px-1.5 py-1 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">No. Telar</label>
                    <input type="text" id="campo_NoTelarId" data-campo="NoTelarId" value="{{ $orden->NoTelarId ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">No. de Telas</label>
                    <input type="number" min="0" id="campo_NoTelas" data-campo="NoTelas" value="{{ $orden->NoTelas ?? '' }}"
                        {{ $permiteEditarNoTelas ? '' : 'disabled' }}
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 {{ $permiteEditarNoTelas ? '' : 'bg-gray-100 text-gray-600 cursor-not-allowed' }}">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tipo</label>
                    <select id="campo_RizoPie" data-campo="RizoPie" class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Seleccionar...</option>
                        <option value="Rizo" {{ $orden->RizoPie === 'Rizo' ? 'selected' : '' }}>Rizo</option>
                        <option value="Pie" {{ $orden->RizoPie === 'Pie' ? 'selected' : '' }}>Pie</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Cuenta</label>
                    <input type="number" id="campo_Cuenta" data-campo="Cuenta" value="{{ $orden->Cuenta ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Calibre</label>
                    <input type="number" step="0.01" id="campo_Calibre" data-campo="Calibre" value="{{ $orden->Calibre ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Metros</label>
                    <input type="number" step="0.01" id="campo_Metros" data-campo="Metros" value="{{ $orden->Metros ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            {{-- Fila 2 --}}
            <div class="grid gap-1.5 mb-1.5" style="display: grid; grid-template-columns: repeat(8, minmax(0, 1fr));">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fibra</label>
                    <select id="campo_Fibra" data-campo="Fibra" data-valor-actual="{{ $orden->Fibra ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Seleccionar...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Salón Tejido</label>
                    <select id="campo_SalonTejidoId" data-campo="SalonTejidoId" class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Seleccionar...</option>
                        <option value="JACQUARD" {{ ($orden->SalonTejidoId ?? '') === 'JACQUARD' ? 'selected' : '' }}>JACQUARD</option>
                        <option value="SMIT" {{ ($orden->SalonTejidoId ?? '') === 'SMIT' ? 'selected' : '' }}>SMIT</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Máquina</label>
                    <select id="campo_MaquinaEng" data-campo="MaquinaEng" class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Seleccionar...</option>
                        @foreach($maquinas as $maquina)
                            @php
                                $maquinaValor = $orden->MaquinaEng ?? '';
                                $esSeleccionada = ($maquinaValor === $maquina->MaquinaId) || ($maquinaValor === ($maquina->Nombre ?? ''));
                            @endphp
                            <option value="{{ $maquina->MaquinaId ?? $maquina->Nombre }}" {{ $esSeleccionada ? 'selected' : '' }}>
                                {{ $maquina->Nombre ?? $maquina->MaquinaId }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tipo Atado</label>
                    <select id="campo_TipoAtado" data-campo="TipoAtado" class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Seleccionar...</option>
                        <option value="Normal" {{ strtolower(trim($orden->TipoAtado ?? '')) === 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="Especial" {{ strtolower(trim($orden->TipoAtado ?? '')) === 'especial' ? 'selected' : '' }}>Especial</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Lote Proveedor</label>
                    <input type="text" id="campo_LoteProveedor" data-campo="LoteProveedor" value="{{ $orden->LoteProveedor ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Bom Engomado</label>
                    <input type="text" id="campo_BomEng" data-campo="BomEng" value="{{ $orden->BomEng ?? '' }}" autocomplete="off"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Bom Fórmula</label>
                    <input type="text" id="campo_BomFormula" data-campo="BomFormula" value="{{ $orden->BomFormula ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tamaño</label>
                    <input type="text" id="campo_InventSizeId" data-campo="InventSizeId" data-valor-actual="{{ $orden->InventSizeId ?? '' }}" value="{{ $orden->InventSizeId ?? '' }}" list="listaTamanos"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <datalist id="listaTamanos"></datalist>

            {{-- Fila 3 --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-0.5">Observaciones</label>
                <textarea id="campo_Observaciones" data-campo="Observaciones" rows="3"
                    class="campo-editable w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500" style="resize: none;">{{ $orden->Observaciones ?? '' }}</textarea>
            </div>

            @if($mostrarTablaProduccion)
                <div id="tabla-produccion-container" class="mt-2 overflow-x-auto border border-gray-200 rounded">
                    <table class="min-w-full text-sm" id="tabla-produccion">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                <th class="px-1 py-1 text-left font-semibold text-sm">No. Empleado</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">H. Inicio</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">H. Fin</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">No. Julio</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">Kg. Bruto</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">Tara</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">Kg. Neto</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm">Metros</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm bg-blue-700">Canoa1</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm bg-blue-700">Canoa2</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm bg-blue-700">Sólidos</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm bg-blue-700">Roturas</th>
                                <th class="px-1 py-1 text-center font-semibold text-sm bg-blue-700">Ubicación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @php $registrosProduccion = $registrosProduccion ?? collect(); @endphp
                            @forelse($registrosProduccion as $r)
                                @php
                                    $reg = optional($r);
                                    $metros = (float)($reg->Metros1 ?? 0) + (float)($reg->Metros2 ?? 0) + (float)($reg->Metros3 ?? 0);
                                    $empleados = array_filter([trim($reg->CveEmpl1 ?? ''), trim($reg->CveEmpl2 ?? ''), trim($reg->CveEmpl3 ?? '')]);
                                    $empleadoTexto = count($empleados) > 0 ? implode(', ', $empleados) : '-';
                                    $infoOficial = [];
                                    if (trim($reg->NomEmpl1 ?? '')) $infoOficial[] = ['nombre' => trim($reg->NomEmpl1 ?? ''), 'turno' => $reg->Turno1 ?? null];
                                    if (trim($reg->NomEmpl2 ?? '')) $infoOficial[] = ['nombre' => trim($reg->NomEmpl2 ?? ''), 'turno' => $reg->Turno2 ?? null];
                                    if (trim($reg->NomEmpl3 ?? '')) $infoOficial[] = ['nombre' => trim($reg->NomEmpl3 ?? ''), 'turno' => $reg->Turno3 ?? null];
                                    $oficialesEdicion = [];
                                    for ($i = 1; $i <= 3; $i++) {
                                        $cve = trim((string) ($reg->{"CveEmpl{$i}"} ?? ''));
                                        $nom = trim((string) ($reg->{"NomEmpl{$i}"} ?? ''));
                                        $turnoVal = $reg->{"Turno{$i}"} ?? null;
                                        $metrosVal = $reg->{"Metros{$i}"} ?? null;
                                        if ($cve !== '' || $nom !== '' || $turnoVal !== null || $metrosVal !== null) {
                                            $oficialesEdicion[] = ['numero' => $i, 'cve' => $cve ?: null, 'nombre' => $nom ?: null, 'turno' => $turnoVal !== null && $turnoVal !== '' ? (int)$turnoVal : null, 'metros' => $metrosVal !== null && $metrosVal !== '' ? (float)$metrosVal : null];
                                        }
                                    }
                                    $horaInicio = $reg->HoraInicial ? substr((string)$reg->HoraInicial, 0, 5) : '';
                                    $horaFin = $reg->HoraFinal ? substr((string)$reg->HoraFinal, 0, 5) : '';
                                @endphp
                                <tr class="hover:bg-gray-50" data-registro-id="{{ $reg->Id ?? '' }}">
                                    <td class="px-1 py-0.5 text-left align-top max-w-[180px]">
                                        <div class="flex items-start justify-between gap-1">
                                            <div class="text-sm leading-tight flex-1 min-w-0" data-empleados-info>
                                                <div class="text-gray-800 font-semibold">{{ $empleadoTexto }}</div>
                                                @foreach($infoOficial as $of)
                                                    <div class="text-sm text-gray-600">{{ $of['nombre'] }} <span class="text-amber-600">(T{{ $of['turno'] ?? '-' }})</span></div>
                                                @endforeach
                                            </div>
                                            <button type="button" class="btn-editar-empleados shrink-0 p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded" data-registro-id="{{ $reg->Id ?? '' }}" data-oficiales='@json($oficialesEdicion)' title="Editar empleados">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-0.5 py-0.5 text-center">
                                        <input type="time" data-field="h_inicio" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $horaInicio }}" class="produccion-input w-14 px-0.5 py-0 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-0.5 py-0.5 text-center">
                                        <input type="time" data-field="h_fin" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $horaFin }}" class="produccion-input w-14 px-0.5 py-0 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1 py-1 text-center">{{ $reg->NoJulio ?? '-' }}</td>
                                    <td class="px-1 py-1 text-center">{{ ($reg->KgBruto ?? null) !== null && ($reg->KgBruto ?? '') !== '' ? number_format((float)($reg->KgBruto ?? 0), 2) : '-' }}</td>
                                    <td class="px-1 py-1 text-center">{{ ($reg->Tara ?? null) !== null && ($reg->Tara ?? '') !== '' ? number_format((float)($reg->Tara ?? 0), 2) : '-' }}</td>
                                    <td class="px-1 py-1 text-center">{{ ($reg->KgNeto ?? null) !== null && ($reg->KgNeto ?? '') !== '' ? number_format((float)($reg->KgNeto ?? 0), 2) : '-' }}</td>
                                    <td class="px-1 py-1 text-center" data-cell="metros">{{ $metros > 0 ? number_format($metros, 0) : '-' }}</td>
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        <input type="number" min="0" data-field="canoa1" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $reg->Canoa1 ?? 0 }}" class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        <input type="number" min="0" data-field="canoa2" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $reg->Canoa2 ?? 0 }}" class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        <input type="number" step="0.01" data-field="solidos" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $reg->Solidos ?? '' }}" class="produccion-input w-14 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        <input type="number" min="0" data-field="roturas" data-registro-id="{{ $reg->Id ?? '' }}" value="{{ $reg->Roturas ?? '' }}" class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="px-1 py-1 text-center bg-blue-50">
                                        @php $ubicaciones = $ubicaciones ?? collect(); @endphp
                                        <select data-field="ubicacion" data-registro-id="{{ $reg->Id ?? '' }}" class="produccion-input w-24 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                                            <option value="">-</option>
                                            @foreach($ubicaciones as $ub)
                                                <option value="{{ $ub->Codigo ?? '' }}" {{ (($reg->Ubicacion ?? '') === ($ub->Codigo ?? '')) ? 'selected' : '' }}>{{ $ub->Codigo ?? '' }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="px-2 py-3 text-center text-gray-500 italic">No hay registros de producción</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <script>
        (() => {
            const ordenId = {{ $orden->Id }};
            const puedeEditar = {{ $puedeEditar ? 'true' : 'false' }};
            const csrfToken = '{{ csrf_token() }}';
            const routeActualizar = '{{ route('engomado.editar.ordenes.programadas.actualizar') }}';
            const RUTA_USUARIOS_ENGOMADO = '{{ route('engomado.modulo.produccion.engomado.usuarios.engomado') }}';
            const RUTA_GUARDAR_OFICIAL = '{{ route('engomado.modulo.produccion.engomado.guardar.oficial') }}';
            const RUTA_ELIMINAR_OFICIAL = '{{ route('engomado.modulo.produccion.engomado.eliminar.oficial') }}';
            const RUTA_HILOS = '{{ route("programa.urd.eng.hilos") }}';
            const RUTA_TAMANOS = '{{ route("programa.urd.eng.tamanos") }}';
            const RUTA_BOM_ENGOMADO = '{{ route("programa.urd.eng.buscar.bom.engomado") }}';
            const permiteEditarPorStatus = {{ ($permiteEditarPorStatus ?? false) ? 'true' : 'false' }};
            const permiteEditarNoTelas = {{ ($permiteEditarNoTelas ?? false) ? 'true' : 'false' }};
            const mostrarTablaProduccion = {{ ($mostrarTablaProduccion ?? false) ? 'true' : 'false' }};
            const statusOrden = @json(trim($orden->Status ?? ''));
            const ACCION_METROS_SOLO_CAMPO = 'solo_campo';
            const ACCION_METROS_ACTUALIZAR_TODA = 'actualizar_produccion_toda';
            const ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO = 'actualizar_produccion_sin_hora_inicio';

            const cambiosPendientes = new Map();
            let timeoutGuardado = null;
            let opcionesHilos = [];
            let opcionesTamanos = [];
            let usuariosEngomado = null;

            const showToast = (icon, title) => {
                if (typeof Swal === 'undefined') { alert(title); return; }
                Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2000 });
            };

            const showError = (message, title = 'Error') => {
                if (typeof Swal === 'undefined') { alert(`${title}: ${message}`); return; }
                Swal.fire({ icon: 'error', title, html: `<p class="text-gray-700">${message}</p>`, confirmButtonColor: '#2563eb', confirmButtonText: 'Aceptar', width: '500px' });
            };

            const escapeHtml = (v) => String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');

            const normalizarOficiales = (of) => {
                if (!Array.isArray(of)) return [];
                return of.map(o => ({
                    numero: parseInt(o?.numero, 10),
                    cve: (o?.cve ?? '').toString().trim() || null,
                    nombre: (o?.nombre ?? '').toString().trim() || null,
                    turno: o?.turno != null && o?.turno !== '' ? parseInt(o.turno, 10) : null,
                    metros: o?.metros != null && o?.metros !== '' ? parseFloat(o.metros) : null,
                })).filter(o => Number.isInteger(o.numero) && o.numero >= 1 && o.numero <= 3);
            };

            const obtenerUsuariosEngomado = async () => {
                if (Array.isArray(usuariosEngomado)) return usuariosEngomado;
                try {
                    const r = await fetch(RUTA_USUARIOS_ENGOMADO, { headers: { 'Accept': 'application/json' } });
                    const j = await r.json();
                    usuariosEngomado = (j?.success && Array.isArray(j?.data)) ? j.data : [];
                } catch (e) { usuariosEngomado = []; }
                return usuariosEngomado;
            };

            const renderizarInfoEmpleadosFila = (registroId, oficiales) => {
                const row = document.querySelector(`#tabla-produccion tbody tr[data-registro-id="${registroId}"]`);
                if (!row) return;
                const info = row.querySelector('[data-empleados-info]');
                const btn = row.querySelector('.btn-editar-empleados');
                if (!info) return;
                const ordenados = normalizarOficiales(oficiales).sort((a,b)=>a.numero-b.numero);
                const codigos = ordenados.map(o=>o.cve).filter(Boolean);
                let html = `<div class="text-gray-800 font-semibold">${escapeHtml(codigos.length?codigos.join(', '):'-')}</div>`;
                ordenados.forEach(of => {
                    if (of.nombre) html += `<div class="text-sm text-gray-600">${escapeHtml(of.nombre)} <span class="text-amber-600">(T${escapeHtml(of.turno??'-')})</span></div>`;
                });
                info.innerHTML = html;
                if (btn) btn.dataset.oficiales = JSON.stringify(ordenados);
            };

            const guardarEmpleadosRegistro = async (registroId, actuales, nuevos) => {
                const actualesMap = new Map(normalizarOficiales(actuales).map(o=>[o.numero,o]));
                const guardados = [];
                for (let i = 1; i <= 3; i++) {
                    const actual = actualesMap.get(i);
                    const nuevo = nuevos.find(o=>o.numero===i)||{numero:i,cve:null,nombre:null,metros:null,turno:null};
                    const sinDatos = !nuevo.cve && !nuevo.nombre;
                    if (sinDatos) {
                        if (actual && (actual.cve||actual.nombre)) {
                            const rd = await fetch(RUTA_ELIMINAR_OFICIAL, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken}, body: JSON.stringify({registro_id:registroId, numero_oficial:i}) });
                            const jd = await rd.json();
                            if (!jd.success) throw new Error(jd.error||`No se pudo eliminar empleado ${i}`);
                        }
                        continue;
                    }
                    const payload = { registro_id:registroId, numero_oficial:i, cve_empl:nuevo.cve, nom_empl:nuevo.nombre, metros:nuevo.metros, turno:nuevo.turno };
                    const rs = await fetch(RUTA_GUARDAR_OFICIAL, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken}, body: JSON.stringify(payload) });
                    const js = await rs.json();
                    if (!js.success) throw new Error(js.error||`No se pudo guardar empleado ${i}`);
                    guardados.push({ numero:i, cve:nuevo.cve||null, nombre:nuevo.nombre||null, metros:js?.data?.metros??nuevo.metros??null, turno:nuevo.turno||null });
                }
                return guardados;
            };

            const abrirModalEditarEmpleados = async (btn) => {
                if (!btn || typeof Swal === 'undefined') return;
                const registroId = parseInt(btn.dataset.registroId||'', 10);
                if (!registroId) return;
                const usuarios = await obtenerUsuariosEngomado();
                const actuales = normalizarOficiales(JSON.parse(btn.dataset.oficiales||'[]'));
                const mapActuales = new Map(actuales.map(o=>[o.numero,o]));

                const filasHtml = [1,2,3].map(i=>{
                    const of = mapActuales.get(i)||{};
                    const turno = of.turno??'';
                    const metros = (of.metros != null && of.metros !== '') ? of.metros : '';
                    const cveActual = String(of.cve||'').trim();
                    const opciones = usuarios.map(u=>{
                        const cve = String(u.numero_empleado||'').trim();
                        const nom = String(u.nombre||'').trim();
                        if (!cve) return '';
                        const sel = cveActual && cve===cveActual ? 'selected' : '';
                        return `<option value="${escapeHtml(cve)}" data-nombre="${escapeHtml(nom)}" data-turno="${escapeHtml(u.turno??'')}" ${sel}>${escapeHtml(cve)}</option>`;
                    }).join('');
                    const existe = cveActual && usuarios.some(u=>String(u.numero_empleado||'').trim()===cveActual);
                    const extra = (!existe && cveActual) ? `<option value="${escapeHtml(cveActual)}" data-nombre="${escapeHtml(of.nombre||'')}" data-turno="${escapeHtml(of.turno??'')}" selected>${escapeHtml(cveActual)}</option>` : '';
                    return `<div style="display:grid;grid-template-columns:minmax(140px,1.15fr) minmax(160px,1.2fr) minmax(92px,0.7fr) minmax(58px,0.45fr);column-gap:8px;align-items:center;margin-bottom:8px;">
                        <select id="swal_emp_${i}" class="px-2 py-1 border rounded text-sm w-full"><option value="">No. Empleado</option>${opciones}${extra}</select>
                        <input id="swal_nom_${i}" class="px-2 py-1 border rounded text-sm bg-gray-100 w-full" value="${escapeHtml(of.nombre||'')}" readonly placeholder="Nombre">
                        <input id="swal_metros_${i}" type="number" min="0" step="0.01" class="px-2 py-1 border rounded text-sm w-full" value="${escapeHtml(metros)}" placeholder="Metros">
                        <select id="swal_turno_${i}" class="px-2 py-1 border rounded text-sm w-full">
                            <option value="" ${turno===''?'selected':''}>Turno</option>
                            <option value="1" ${String(turno)==='1'?'selected':''}>1</option>
                            <option value="2" ${String(turno)==='2'?'selected':''}>2</option>
                            <option value="3" ${String(turno)==='3'?'selected':''}>3</option>
                        </select>
                    </div>`;
                }).join('');

                const resultado = await Swal.fire({
                    title: 'Editar Empleados',
                    html: `<div class="text-left w-full"><p class="text-sm text-gray-600 mb-2">Hasta 3 empleados.</p>
                        <div style="display:grid;grid-template-columns:minmax(140px,1.15fr) minmax(160px,1.2fr) minmax(92px,0.7fr) minmax(58px,0.45fr);column-gap:8px;margin-bottom:6px;">
                            <div class="text-xs font-semibold text-gray-600">No. Empleado</div><div class="text-xs font-semibold text-gray-600">Nombre</div><div class="text-xs font-semibold text-gray-600">Metros</div><div class="text-xs font-semibold text-gray-600">Turno</div>
                        </div>${filasHtml}</div>`,
                    width: 920,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    didOpen: () => {
                        for (let i = 1; i <= 3; i++) {
                            const sel = document.getElementById(`swal_emp_${i}`);
                            const nom = document.getElementById(`swal_nom_${i}`);
                            if (!sel || !nom) continue;
                            const sync = () => {
                                const opt = sel.options[sel.selectedIndex];
                                nom.value = opt?.dataset?.nombre || '';
                            };
                            sel.addEventListener('change', sync);
                            sync();
                        }
                    },
                    preConfirm: () => {
                        const nuevos = [];
                        const claves = new Set();
                        const turnosAsignados = new Map();
                        for (let i = 1; i <= 3; i++) {
                            const sel = document.getElementById(`swal_emp_${i}`);
                            const nom = document.getElementById(`swal_nom_${i}`);
                            const metros = document.getElementById(`swal_metros_${i}`);
                            const turno = document.getElementById(`swal_turno_${i}`);
                            const cve = String(sel?.value||'').trim();
                            const nombre = String(nom?.value||'').trim();
                            const metrosTxt = String(metros?.value||'').trim();
                            const turnoTxt = String(turno?.value||'').trim();
                            if (!cve) { nuevos.push({numero:i,cve:null,nombre:null,metros:null,turno:null}); continue; }
                            if (claves.has(cve)) { Swal.showValidationMessage(`No. Empleado ${cve} repetido.`); return false; }
                            claves.add(cve);
                            const t = parseInt(turnoTxt, 10);
                            if (!Number.isInteger(t) || ![1,2,3].includes(t)) { Swal.showValidationMessage(`Turno válido (1-3) para Empleado ${i}.`); return false; }
                            if (turnosAsignados.has(t)) { Swal.showValidationMessage(`No puede haber dos oficiales en turno ${t}.`); return false; }
                            turnosAsignados.set(t, i);
                            nuevos.push({numero:i, cve, nombre: nombre||null, metros: metrosTxt ? parseFloat(metrosTxt) : null, turno: t});
                        }
                        return nuevos;
                    },
                });

                if (!resultado.isConfirmed || !Array.isArray(resultado.value)) return;
                try {
                    const guardados = await guardarEmpleadosRegistro(registroId, actuales, resultado.value);
                    renderizarInfoEmpleadosFila(registroId, guardados);
                    showToast('success', 'Empleados actualizados');
                } catch (e) {
                    showError(e.message || 'No se pudieron guardar');
                }
            };

            const actualizarCampo = async (campo, valor, opciones = {}) => {
                if (campo === 'Folio') { showError('El folio no se puede editar.'); return; }
                try {
                    const payload = { orden_id: ordenId, campo, valor };
                    if (campo === 'Metros' && opciones.accionMetros) payload.accion_metros = opciones.accionMetros;
                    const r = await fetch(routeActualizar, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken}, body: JSON.stringify(payload) });
                    const j = await r.json();
                    if (!j.success) throw new Error(j.error || 'Error');
                    cambiosPendientes.delete(campo);
                    if (opciones.mostrarToast !== false) showToast('success', j.message || 'Actualizado');
                    return j;
                } catch (e) {
                    showError(`Error: ${e.message}`);
                    if (opciones.lanzarError) throw e;
                    return null;
                }
            };

            const solicitarAccionMetros = async () => {
                if (typeof Swal === 'undefined') return ACCION_METROS_SOLO_CAMPO;
                const status = String(statusOrden||'').trim();
                if (status === 'Finalizado') {
                    const {value} = await Swal.fire({
                        icon:'question', title:'Actualizar metros', text:'¿Cómo aplicar el cambio?',
                        input:'radio', inputOptions: { [ACCION_METROS_ACTUALIZAR_TODA]:'Actualizar producción finalizada', [ACCION_METROS_SOLO_CAMPO]:'Solo campo Metros' },
                        inputValue: ACCION_METROS_SOLO_CAMPO, showCancelButton: true, confirmButtonText:'Continuar', inputValidator: v=>!v?'Selecciona una opción':null
                    });
                    return value || null;
                }
                if (status === 'En Proceso') {
                    const {value} = await Swal.fire({
                        icon:'question', title:'Actualizar metros', text:'¿Cómo aplicar el cambio?',
                        input:'radio', inputOptions: { [ACCION_METROS_ACTUALIZAR_TODA]:'Toda la producción', [ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO]:'Solo sin hora inicio', [ACCION_METROS_SOLO_CAMPO]:'Solo campo Metros' },
                        inputValue: ACCION_METROS_SOLO_CAMPO, showCancelButton: true, confirmButtonText:'Continuar', inputValidator: v=>!v?'Selecciona':null
                    });
                    return value || null;
                }
                return ACCION_METROS_SOLO_CAMPO;
            };

            const solicitarConfirmacionNoTelas = async (anterior, nuevo) => {
                if (anterior === nuevo) return true;

                let mensaje = 'Esto puede impactar registros de produccion ya iniciados por un empleado.';
                if (nuevo > anterior) {
                    mensaje = `Se agregaran ${nuevo - anterior} registro(s) de produccion. ${mensaje}`;
                } else if (nuevo < anterior) {
                    mensaje = `Se eliminaran ${anterior - nuevo} registro(s) de produccion. ${mensaje}`;
                }

                if (typeof Swal === 'undefined') {
                    return window.confirm(mensaje);
                }

                const r = await Swal.fire({
                    icon: 'warning',
                    title: 'Confirmar cambio de No. de Telas',
                    text: mensaje,
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#2563eb',
                });

                return !!r.isConfirmed;
            };

            const formatearMetrosTabla = (v) => { const n = parseFloat(v); return (!Number.isFinite(n) || n<=0) ? '-' : Math.round(n).toLocaleString('en-US'); };

            const sincronizarMetrosTablaPantalla = (accionMetros, metrosNuevo) => {
                if (!mostrarTablaProduccion) return;
                document.querySelectorAll('#tabla-produccion tbody tr[data-registro-id]').forEach(tr => {
                    if (accionMetros === ACCION_METROS_ACTUALIZAR_SIN_HORA_INICIO) {
                        const hi = tr.querySelector('input[data-field="h_inicio"]');
                        if (hi && String(hi.value||'').trim() !== '') return;
                    }
                    const celda = tr.querySelector('[data-cell="metros"]');
                    if (celda) celda.textContent = formatearMetrosTabla(metrosNuevo);
                });
            };

            const eliminarRegistrosProduccionDeTabla = (ids = []) => {
                if (!mostrarTablaProduccion || !Array.isArray(ids) || ids.length === 0) return;
                const tbody = document.querySelector('#tabla-produccion tbody');
                if (!tbody) return;

                ids.forEach((id) => {
                    const rid = parseInt(id, 10);
                    if (!Number.isInteger(rid) || rid <= 0) return;
                    const tr = tbody.querySelector(`tr[data-registro-id="${rid}"]`);
                    if (tr) tr.remove();
                });

                const restantes = tbody.querySelectorAll('tr[data-registro-id]').length;
                if (restantes === 0) {
                    const trVacio = document.createElement('tr');
                    trVacio.innerHTML = '<td colspan="13" class="px-2 py-3 text-center text-gray-500 italic">No hay registros de produccion</td>';
                    tbody.appendChild(trVacio);
                }
            };

            const agregarRegistrosProduccionATabla = (registros = []) => {
                if (!mostrarTablaProduccion || !Array.isArray(registros) || registros.length === 0) return;

                const tbody = document.querySelector('#tabla-produccion tbody');
                if (!tbody) return;

                const filaVacia = tbody.querySelector('tr td[colspan="13"]');
                if (filaVacia) filaVacia.closest('tr')?.remove();

                const ubicacionOptions = (() => {
                    const selectRef = document.querySelector('#tabla-produccion tbody select[data-field="ubicacion"]');
                    return selectRef ? selectRef.innerHTML : '<option value="">-</option>';
                })();

                registros.forEach((r) => {
                    const rid = parseInt(r?.id, 10);
                    if (!Number.isInteger(rid) || rid <= 0) return;

                    const metrosTxt = formatearMetrosTabla(r?.metros ?? 0);
                    const solidosVal = r?.solidos !== null && r?.solidos !== undefined ? r.solidos : '';

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-gray-50';
                    tr.dataset.registroId = String(rid);
                    tr.innerHTML = `
                        <td class="px-1 py-0.5 text-left align-top max-w-[180px]">
                            <div class="flex items-start justify-between gap-1">
                                <div class="text-sm leading-tight flex-1 min-w-0" data-empleados-info>
                                    <div class="text-gray-800 font-semibold">-</div>
                                </div>
                                <button type="button" class="btn-editar-empleados shrink-0 p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded" data-registro-id="${rid}" data-oficiales="[]" title="Editar empleados">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            </div>
                        </td>
                        <td class="px-0.5 py-0.5 text-center">
                            <input type="time" data-field="h_inicio" data-registro-id="${rid}" value="" class="produccion-input w-14 px-0.5 py-0 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-0.5 py-0.5 text-center">
                            <input type="time" data-field="h_fin" data-registro-id="${rid}" value="" class="produccion-input w-14 px-0.5 py-0 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-1 py-1 text-center">-</td>
                        <td class="px-1 py-1 text-center">-</td>
                        <td class="px-1 py-1 text-center">-</td>
                        <td class="px-1 py-1 text-center">-</td>
                        <td class="px-1 py-1 text-center" data-cell="metros">${metrosTxt}</td>
                        <td class="px-1 py-1 text-center bg-blue-50">
                            <input type="number" min="0" data-field="canoa1" data-registro-id="${rid}" value="0" class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-1 py-1 text-center bg-blue-50">
                            <input type="number" min="0" data-field="canoa2" data-registro-id="${rid}" value="0" class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-1 py-1 text-center bg-blue-50">
                            <input type="number" step="0.01" data-field="solidos" data-registro-id="${rid}" value="${solidosVal}" class="produccion-input w-14 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-1 py-1 text-center bg-blue-50">
                            <input type="number" min="0" data-field="roturas" data-registro-id="${rid}" value="" class="produccion-input w-12 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">
                        </td>
                        <td class="px-1 py-1 text-center bg-blue-50">
                            <select data-field="ubicacion" data-registro-id="${rid}" class="produccion-input w-24 px-1 py-0.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500">${ubicacionOptions}</select>
                        </td>
                    `;
                    tbody.appendChild(tr);
                    tr.querySelectorAll('.produccion-input').forEach(bindProduccionInput);
                });
            };

            const bindProduccionInput = (input) => {
                if (!input || input.dataset.bound === '1') return;
                input.dataset.bound = '1';
                let valorEnviado = input.value;
                const enviar = function() {
                    const regId = parseInt(this.dataset.registroId, 10);
                    const field = this.dataset.field;
                    const val = this.value === '' ? null : this.value;
                    if (val === valorEnviado) return;
                    valorEnviado = this.value;

                    if (field === 'h_inicio' || field === 'h_fin') {
                        fetch('{{ route('engomado.modulo.produccion.engomado.actualizar.horas') }}', {
                            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
                            body: JSON.stringify({ registro_id: regId, campo: field==='h_inicio'?'HoraInicial':'HoraFinal', valor: val })
                        }).then(r=>r.json()).then(j=>{ if(j.success) showToast('success','Actualizado'); else showError(j.error||'Error'); }).catch(e=>showError(e.message));
                    } else {
                        const campoMap = { canoa1:'Canoa1', canoa2:'Canoa2', solidos:'Solidos', roturas:'Roturas', ubicacion:'Ubicacion' };
                        const c = campoMap[field];
                        if (!c) return;
                        let v = val !== null && val !== '' ? val : null;
                        if (c === 'Solidos' && v !== null) v = parseFloat(v);
                        else if (c === 'Roturas' && v !== null) v = parseInt(v, 10);
                        else if (c === 'Ubicacion') v = (v !== null && String(v).trim() !== '') ? String(v).trim() : null;
                        fetch('{{ route('engomado.modulo.produccion.engomado.actualizar.campos.produccion') }}', {
                            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},
                            body: JSON.stringify({ registro_id: regId, campo: c, valor: v })
                        }).then(r=>r.json()).then(j=>{ if(j.success) showToast('success','Actualizado'); else showError(j.error||'Error'); }).catch(e=>showError(e.message));
                    }
                };
                input.addEventListener('change', enviar);
                input.addEventListener('blur', enviar);
            };

            document.addEventListener('DOMContentLoaded', () => {
                const camposBloqueados = ['campo_Cuenta','campo_Calibre','campo_Fibra','campo_MaquinaEng','campo_BomEng','campo_BomFormula'];
                if (!permiteEditarPorStatus) {
                    camposBloqueados.forEach(id => {
                        const el = document.getElementById(id);
                        if (el) { el.disabled = true; el.classList.add('bg-gray-100','text-gray-600','cursor-not-allowed'); }
                    });
                }
                if (!permiteEditarNoTelas) {
                    const noTelas = document.getElementById('campo_NoTelas');
                    if (noTelas) {
                        noTelas.disabled = true;
                        noTelas.classList.add('bg-gray-100', 'text-gray-600', 'cursor-not-allowed');
                    }
                }

                const camposEditables = document.querySelectorAll('.campo-editable');
                camposEditables.forEach(campo => {
                    const campoNombre = campo.dataset.campo;
                    let valorAnterior = campo.value;
                    let timeoutMetros = null;
                    let guardandoMetros = false;
                    let timeoutNoTelas = null;
                    let guardandoNoTelas = false;

                    if ((campo.tagName === 'INPUT' || campo.tagName === 'TEXTAREA') && campoNombre === 'Metros') {
                        const guardarMetros = async () => {
                            const v = campo.value;
                            if (v === valorAnterior || guardandoMetros) return;
                            guardandoMetros = true;
                            try {
                                const accion = await solicitarAccionMetros();
                                if (!accion) { campo.value = valorAnterior; return; }
                                const res = await actualizarCampo(campoNombre, v, { accionMetros: accion, mostrarToast: false, lanzarError: true });
                                if (!res || !res.success) { campo.value = valorAnterior; return; }
                                valorAnterior = v;
                                const actualizados = parseInt(res?.data?.registros_produccion_actualizados ?? 0, 10) || 0;
                                if (accion !== ACCION_METROS_SOLO_CAMPO && actualizados > 0)
                                    sincronizarMetrosTablaPantalla(accion, res?.data?.valor ?? v);
                                showToast('success', res.message || 'Metros actualizado');
                            } catch (e) { campo.value = valorAnterior; }
                            finally { guardandoMetros = false; }
                        };
                        campo.addEventListener('change', () => { if (campo.value !== valorAnterior && timeoutMetros) { clearTimeout(timeoutMetros); } timeoutMetros = setTimeout(guardarMetros, 400); });
                        campo.addEventListener('blur', () => { if (timeoutMetros) { clearTimeout(timeoutMetros); timeoutMetros = null; } guardarMetros(); });
                        return;
                    }

                    if ((campo.tagName === 'INPUT' || campo.tagName === 'TEXTAREA') && campoNombre === 'NoTelas') {
                        if (!permiteEditarNoTelas) return;
                        const guardarNoTelas = async () => {
                            const v = campo.value;
                            if (v === valorAnterior || guardandoNoTelas) return;
                            guardandoNoTelas = true;
                            try {
                                const anterior = parseInt(valorAnterior || '0', 10) || 0;
                                const nuevo = parseInt(v || '0', 10) || 0;
                                const confirmado = await solicitarConfirmacionNoTelas(anterior, nuevo);
                                if (!confirmado) { campo.value = valorAnterior; return; }

                                const res = await actualizarCampo(campoNombre, v, { mostrarToast: false, lanzarError: true });
                                if (!res || !res.success) { campo.value = valorAnterior; return; }
                                valorAnterior = v;

                                const creados = Array.isArray(res?.data?.registros_produccion_creados) ? res.data.registros_produccion_creados : [];
                                const eliminados = Array.isArray(res?.data?.registros_produccion_eliminados_ids) ? res.data.registros_produccion_eliminados_ids : [];
                                if (creados.length > 0) agregarRegistrosProduccionATabla(creados);
                                if (eliminados.length > 0) eliminarRegistrosProduccionDeTabla(eliminados);

                                showToast('success', res.message || 'No. de telas actualizado');
                            } catch (e) {
                                campo.value = valorAnterior;
                            } finally {
                                guardandoNoTelas = false;
                            }
                        };

                        campo.addEventListener('change', () => {
                            if (campo.value === valorAnterior) return;
                            if (timeoutNoTelas) clearTimeout(timeoutNoTelas);
                            timeoutNoTelas = setTimeout(guardarNoTelas, 400);
                        });
                        campo.addEventListener('blur', () => {
                            if (timeoutNoTelas) { clearTimeout(timeoutNoTelas); timeoutNoTelas = null; }
                            guardarNoTelas();
                        });
                        return;
                    }

                    if (campo.tagName === 'INPUT' || campo.tagName === 'TEXTAREA') {
                        campo.addEventListener('change', () => {
                            if (campo.value !== valorAnterior) {
                                cambiosPendientes.set(campoNombre, campo.value);
                                valorAnterior = campo.value;
                                if (timeoutGuardado) clearTimeout(timeoutGuardado);
                                timeoutGuardado = setTimeout(() => actualizarCampo(campoNombre, campo.value), 1000);
                            }
                        });
                        campo.addEventListener('blur', () => { if (cambiosPendientes.has(campoNombre)) actualizarCampo(campoNombre, campo.value); });
                    }
                    if (campo.tagName === 'SELECT') {
                        campo.addEventListener('change', () => {
                            if (campo.value !== valorAnterior) { valorAnterior = campo.value; actualizarCampo(campoNombre, campo.value); }
                        });
                    }
                });

                Promise.all([
                    fetch(RUTA_HILOS, {headers:{'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ opcionesHilos = (j?.success && Array.isArray(j?.data)) ? j.data.map(i=>i.ConfigId||'').filter(Boolean) : []; }),
                    fetch(RUTA_TAMANOS, {headers:{'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ opcionesTamanos = (j?.success && Array.isArray(j?.data)) ? j.data.map(i=>i.InventSizeId||'').filter(Boolean) : []; })
                ]).then(() => {
                    const fibraSelect = document.getElementById('campo_Fibra');
                    if (fibraSelect) {
                        const val = (fibraSelect.dataset.valorActual || fibraSelect.value || '').trim();
                        const opts = [...opcionesHilos];
                        if (val && !opts.includes(val)) opts.unshift(val);
                        fibraSelect.innerHTML = '<option value="">Seleccionar...</option>' + opts.map(h=>`<option value="${h}" ${h===val?'selected':''}>${h}</option>`).join('');
                    }
                    const datalist = document.getElementById('listaTamanos');
                    if (datalist) {
                        datalist.innerHTML = opcionesTamanos.map(t=>`<option value="${t}"></option>`).join('');
                        const tam = document.getElementById('campo_InventSizeId');
                        const tv = (tam?.dataset?.valorActual || tam?.value || '').trim();
                        if (tv && !opcionesTamanos.includes(tv)) { const o = document.createElement('option'); o.value = tv; datalist.appendChild(o); }
                    }
                });

                document.addEventListener('click', e => {
                    const btn = e.target.closest('.btn-editar-empleados');
                    if (btn) { e.preventDefault(); abrirModalEditarEmpleados(btn); }
                });

                document.querySelectorAll('.produccion-input').forEach(bindProduccionInput);
            });
        })();
    </script>
@endsection
