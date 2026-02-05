@extends('layouts.app')

@section('page-title', 'Editar Orden Programada')

@section('navbar-right')
    @php
        $statusClass = match($orden->Status ?? '') {
            'Finalizado' => 'bg-green-100 text-green-800',
            'En Proceso' => 'bg-yellow-100 text-yellow-800',
            'Programado' => 'bg-blue-100 text-blue-800 ',
            'Cancelado' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    @endphp
    <div class="flex items-center gap-2">
        <span class="px-3 py-2 text-md font-bold rounded-full {{ $statusClass }}">
            {{ $orden->Status ?? '-' }}
        </span>
        @if(($axUrdido ?? 0) === 1)
            <span class="px-2 py-1 text-xs font-bold rounded-full bg-red-600 text-white">AX Urdido</span>
        @endif
        @if(($axEngomado ?? 0) === 1)
            <span class="px-2 py-1 text-xs font-bold rounded-full bg-red-600 text-white">AX Engomado</span>
        @endif
    </div>
@endsection



@section('content')
    <div class="w-full">
        @if($bloqueaUrdido || $bloqueaEngomado)
            <div class="flex flex-wrap items-center gap-2 bg-red-50 border border-red-200 text-red-700 px-2 py-1 mb-2 rounded text-xs">
                @if($bloqueaUrdido)
                    <span class="inline-flex items-center gap-1 font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>
                        Urdido en AX
                    </span>
                @endif
                @if($bloqueaEngomado)
                    <span class="inline-flex items-center gap-1 font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>
                        Engomado en AX
                    </span>
                @endif
                <span class="text-red-600">Campos bloqueados.</span>
            </div>
        @endif
        <!-- Información de la Orden -->
        <div class="bg-white  p-3 mb-4">

            <div class="grid gap-1.5" style="display: grid; grid-template-columns: 0.7fr 1.6fr 0.7fr 0.7fr 0.7fr 0.7fr;">
                <!-- Folio (solo lectura - NO EDITABLE) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Folio <span class="text-red-500">*</span></label>
                    <input
                        type="text"
                        value="{{ $orden->Folio }}"
                        readonly
                        disabled
                        class="w-full px-1.5 py-1 text-sm border border-gray-300 rounded bg-gray-100 text-gray-600 cursor-not-allowed"
                        title="El folio no se puede editar"
                    >
                </div>

                <!-- Folio Consumo -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Folio Consumo</label>
                    <input
                        type="text"
                        id="campo_FolioConsumo"
                        data-campo="FolioConsumo"
                        value="{{ $orden->FolioConsumo ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Rizo/Pie -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tipo</label>
                    <select
                        id="campo_RizoPie"
                        data-campo="RizoPie"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                        <option value="">Seleccionar...</option>
                        <option value="Rizo" {{ $orden->RizoPie === 'Rizo' ? 'selected' : '' }}>Rizo</option>
                        <option value="Pie" {{ $orden->RizoPie === 'Pie' ? 'selected' : '' }}>Pie</option>
                    </select>
                </div>

                <!-- Cuenta -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Cuenta</label>
                    <input
                        type="number"
                        id="campo_Cuenta"
                        data-campo="Cuenta"
                        value="{{ $orden->Cuenta ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Calibre -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Calibre</label>
                    <input
                        type="number"
                        step="0.01"
                        id="campo_Calibre"
                        data-campo="Calibre"
                        value="{{ $orden->Calibre ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Metros -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Metros</label>
                    <input
                        type="number"
                        step="0.01"
                        id="campo_Metros"
                        data-campo="Metros"
                        value="{{ $orden->Metros ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>
            </div>

            <div class="mt-1.5 grid gap-1.5" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr));">

                <!-- Kilos -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Kilos</label>
                    <input
                        type="number"
                        step="0.01"
                        id="campo_Kilos"
                        data-campo="Kilos"
                        value="{{ $orden->Kilos ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Fibra -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fibra</label>
                    <select
                        id="campo_Fibra"
                        data-campo="Fibra"
                        data-valor-actual="{{ $orden->Fibra ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                        <option value="">Seleccionar...</option>
                        @foreach($fibras as $item)
                            @php
                                $fibraValor = trim($orden->Fibra ?? '');
                                $hiloValor = trim($item->Hilo ?? '');
                                $fibraNombre = trim($item->Fibra ?? '');

                                // Comparar con Hilo (identificador principal) o con Fibra si coincide
                                $esSeleccionada = ($fibraValor === $hiloValor) ||
                                                  ($fibraValor === $fibraNombre && !empty($fibraNombre));

                                // Usar Hilo como valor a guardar (es el identificador principal)
                                $valorGuardar = $hiloValor;

                                // Mostrar Hilo - Fibra si existe Fibra, o solo Hilo
                                if (!empty($fibraNombre) && $fibraNombre !== $hiloValor) {
                                    $valorMostrar = $hiloValor . ' - ' . $fibraNombre;
                                } else {
                                    $valorMostrar = $hiloValor;
                                }
                            @endphp
                            <option value="{{ $valorGuardar }}"
                                    {{ $esSeleccionada ? 'selected' : '' }}>
                                {{ $valorMostrar }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Salón de Tejido -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Salón de Tejido</label>
                    <select
                        id="campo_SalonTejidoId"
                        data-campo="SalonTejidoId"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                        <option value="">Seleccionar...</option>
                        <option value="JACQUARD" {{ ($orden->SalonTejidoId ?? '') === 'JACQUARD' ? 'selected' : '' }}>JACQUARD</option>
                        <option value="SMIT" {{ ($orden->SalonTejidoId ?? '') === 'SMIT' ? 'selected' : '' }}>SMIT</option>
                    </select>
                </div>

                <!-- Máquina -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Máquina</label>
                    <select
                        id="campo_MaquinaId"
                        data-campo="MaquinaId"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                        <option value="">Seleccionar...</option>
                        @foreach($maquinas as $maquina)
                            @php
                                $maquinaValor = $orden->MaquinaId ?? '';
                                $esSeleccionada = ($maquinaValor === $maquina->MaquinaId) ||
                                                  ($maquinaValor === ($maquina->Nombre ?? ''));
                            @endphp
                            <option value="{{ $maquina->MaquinaId }}"
                                    {{ $esSeleccionada ? 'selected' : '' }}>
                                {{ $maquina->Nombre ?? $maquina->MaquinaId }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Fecha Programada -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Fecha Programada</label>
                    <input
                        type="date"
                        id="campo_FechaProg"
                        data-campo="FechaProg"
                        value="{{ $orden->FechaProg ? $orden->FechaProg->format('Y-m-d') : '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Tipo Atado -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tipo Atado</label>
                    <select
                        id="campo_TipoAtado"
                        data-campo="TipoAtado"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                        <option value="">Seleccionar...</option>
                        @php
                            $tipoAtadoValor = strtolower(trim($orden->TipoAtado ?? ''));
                        @endphp
                        <option value="Normal" {{ $tipoAtadoValor === 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="Especial" {{ $tipoAtadoValor === 'especial' ? 'selected' : '' }}>Especial</option>
                    </select>
                </div>

                <!-- Lote Proveedor -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Lote Proveedor</label>
                    <input
                        type="text"
                        id="campo_LoteProveedor"
                        data-campo="LoteProveedor"
                        value="{{ $orden->LoteProveedor ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>
            </div>

            <div class="mt-1.5 grid gap-1.5" style="display: grid; grid-template-columns: repeat(9, minmax(0, 1fr));">
                <!-- Ancho Balonas -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Ancho Balonas</label>
                    <input
                        type="number"
                        id="campo_AnchoBalonas"
                        data-campo="AnchoBalonas"
                        value="{{ $engomado->AnchoBalonas ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Bom Urdido (Urdido) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Bom Urdido</label>
                    <input
                        type="text"
                        id="campo_BomId"
                        data-campo="BomId"
                        value="{{ $orden->BomId ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Tamaño -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Tamaño</label>
                    <input
                        type="text"
                        id="campo_InventSizeId"
                        data-campo="InventSizeId"
                        data-valor-actual="{{ $orden->InventSizeId ?? '' }}"
                        value="{{ $orden->InventSizeId ?? '' }}"
                        list="listaTamanos"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Metraje Telas -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Metraje Telas</label>
                    <input
                        type="number"
                        step="0.01"
                        id="campo_MetrajeTelas"
                        data-campo="MetrajeTelas"
                        value="{{ $engomado->MetrajeTelas ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- Cuentados -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Cuentados</label>
                    <input
                        type="number"
                        id="campo_Cuentados"
                        data-campo="Cuentados"
                        value="{{ $engomado->Cuentados ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>

                <!-- No. Telas -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">No. Telas</label>
                    <input
                        type="number"
                        id="campo_NoTelas"
                        data-campo="NoTelas"
                        value="{{ $engomado->NoTelas ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Bom Eng -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Bom Engomado</label>
                    <input
                        type="text"
                        id="campo_BomEng"
                        data-campo="BomEng"
                        value="{{ $engomado->BomEng ?? '' }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>

                <!-- Maquina Eng -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Maquina Engomado</label>
                    <select
                        id="campo_MaquinaEng"
                        data-campo="MaquinaEng"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                        <option value="">Seleccionar...</option>
                    @foreach($maquinasEngomado as $maquina)
                        @php
                            $maquinaEngValor = $engomado->MaquinaEng ?? '';
                            $esSeleccionada = ($maquinaEngValor === $maquina->MaquinaId) ||
                                              ($maquinaEngValor === ($maquina->Nombre ?? ''));
                        @endphp
                            <option value="{{ $maquina->MaquinaId }}" {{ $esSeleccionada ? 'selected' : '' }}>
                                {{ $maquina->Nombre ?? $maquina->MaquinaId }}
                            </option>
                    @endforeach
                    </select>
                </div>

                <!-- Bom Formula -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-0.5">Bom Formula</label>
                    <input
                        type="text"
                        id="campo_BomFormula"
                        data-campo="BomFormula"
                        value="{{ $orden->BomFormula ?? ($engomado->BomFormula ?? '') }}"
                        class="campo-editable w-full px-1.5 py-1 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"

                    >
                </div>
                <datalist id="listaTamanos"></datalist>
            </div>

            <div class="mt-2 grid gap-2" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));">
                <!-- Julios y Hilos -->
                <div>
                    <div class="overflow-x-auto border border-gray-200 rounded">
                        <table class="min-w-full text-sm">
                            <thead class="bg-blue-500 text-white">
                                <tr>
                                    <th class="px-2 py-1.5 text-center font-semibold">No. Julio</th>
                                    <th class="px-2 py-1.5 text-center font-semibold">Hilos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @php $juliosRows = $julios->values(); @endphp
                                @for ($i = 0; $i < 4; $i++)
                                    @php
                                        $row = $juliosRows[$i] ?? null;
                                    @endphp
                                    <tr data-julio-row="{{ $i }}" data-julio-id="{{ $row->Id ?? '' }}">
                                        <td class="px-2 py-1.5 text-center">
                                            <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                data-field="no_julio"
                                                value="{{ $row->Julios ?? '' }}"
                                                class="campo-julio w-full px-2 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"

                                            >
                                        </td>
                                        <td class="px-2 py-1.5 text-center">
                                            <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                data-field="hilos"
                                                value="{{ $row->Hilos ?? '' }}"
                                                class="campo-julio w-full px-2 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"

                                            >
                                        </td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Observaciones -->
                <div style="height: 100%; display: flex; flex-direction: column;">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Observaciones</label>
                    <textarea
                        id="campo_Observaciones"
                        data-campo="Observaciones"
                        rows="3"
                        class="campo-editable w-full px-2 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        style="flex: 1 1 auto; resize: none;"

                    >{{ $orden->Observaciones ?? '' }}</textarea>
                </div>
            </div>
        </div>


    </div>

    <script>
        (() => {
            const ordenId = {{ $orden->Id }};
            const puedeEditar = {{ $puedeEditar ? 'true' : 'false' }};
            const csrfToken = '{{ csrf_token() }}';
            const routeActualizar = '{{ route('urdido.editar.ordenes.programadas.actualizar') }}';
            const routeActualizarJulios = '{{ route('urdido.editar.ordenes.programadas.actualizar.julios') }}';
            const RUTA_HILOS = '{{ route("programa.urd.eng.hilos") }}';
            const RUTA_TAMANOS = '{{ route("programa.urd.eng.tamanos") }}';
            const bloqueaUrdido = {{ $bloqueaUrdido ? 'true' : 'false' }};
            const bloqueaEngomado = {{ $bloqueaEngomado ? 'true' : 'false' }};

            const cambiosPendientes = new Map();
            let timeoutGuardado = null;
            let opcionesHilos = [];
            let opcionesTamanos = [];

            const showToast = (icon, title) => {
                if (typeof Swal === 'undefined') {
                    alert(title);
                    return;
                }
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon,
                    title,
                    showConfirmButton: false,
                    timer: 2000,
                });
            };

            const showError = (message, title = 'Error') => {
                if (typeof Swal === 'undefined') {
                    alert(`${title}: ${message}`);
                    return;
                }
                Swal.fire({
                    icon: 'error',
                    title,
                    html: `<p class="text-gray-700">${message}</p>`,
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'Aceptar',
                    width: '500px',
                });
            };

            const showWarning = (message, title = 'Advertencia') => {
                if (typeof Swal === 'undefined') {
                    alert(`${title}: ${message}`);
                    return;
                }
                Swal.fire({
                    icon: 'warning',
                    title,
                    html: `<p class="text-gray-700">${message}</p>`,
                    confirmButtonColor: '#f59e0b',
                    confirmButtonText: 'Aceptar',
                    width: '500px',
                });
            };

            const showSuccess = (message, title = 'Exito') => {
                if (typeof Swal === 'undefined') {
                    alert(`${title}: ${message}`);
                    return;
                }
                Swal.fire({
                    icon: 'success',
                    title,
                    html: `<p class="text-gray-700">${message}</p>`,
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'Aceptar',
                    timer: 2000,
                    timerProgressBar: true,
                    width: '500px',
                });
            };

            const cargarHilos = async () => {
                try {
                    const response = await fetch(RUTA_HILOS, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const result = await response.json();
                    if (result && result.success && Array.isArray(result.data)) {
                        opcionesHilos = result.data.map(item => item.ConfigId || '').filter(Boolean);
                    } else {
                        opcionesHilos = [];
                    }
                } catch (error) {
                    console.error('Error al cargar hilos:', error);
                    opcionesHilos = [];
                }
            };

            const cargarTamanos = async () => {
                try {
                    const response = await fetch(RUTA_TAMANOS, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const result = await response.json();
                    if (result && result.success && Array.isArray(result.data)) {
                        opcionesTamanos = result.data.map(item => item.InventSizeId || '').filter(Boolean);
                    } else {
                        opcionesTamanos = [];
                    }
                } catch (error) {
                    console.error('Error al cargar tamaños:', error);
                    opcionesTamanos = [];
                }
            };

            const actualizarSelectHilos = () => {
                const select = document.getElementById('campo_Fibra');
                if (!select) return;
                const valorActual = (select.dataset.valorActual || select.value || '').trim();
                const opciones = opcionesHilos.slice();
                if (valorActual && !opciones.includes(valorActual)) {
                    opciones.unshift(valorActual);
                }
                select.innerHTML = `<option value="">Seleccionar...</option>` + opciones
                    .map(hilo => `<option value="${hilo}" ${hilo === valorActual ? 'selected' : ''}>${hilo}</option>`)
                    .join('');
                if (valorActual) {
                    select.value = valorActual;
                }
            };

            const actualizarListaTamanos = () => {
                const datalist = document.getElementById('listaTamanos');
                if (!datalist) return;
                datalist.innerHTML = opcionesTamanos.map(t => `<option value="${t}"></option>`).join('');
                const tamanoInput = document.getElementById('campo_InventSizeId');
                const valorActual = (tamanoInput?.dataset.valorActual || tamanoInput?.value || '').trim();
                if (valorActual && !Array.from(datalist.options).some(opt => opt.value === valorActual)) {
                    const opt = document.createElement('option');
                    opt.value = valorActual;
                    datalist.appendChild(opt);
                }
            };

            const autocompletarTamano = () => {
                const cuentaInput = document.getElementById('campo_Cuenta');
                const calibreInput = document.getElementById('campo_Calibre');
                const tamanoInput = document.getElementById('campo_InventSizeId');
                if (!cuentaInput || !calibreInput || !tamanoInput) return;

                const cuenta = String(cuentaInput.value || '').trim();
                const calibreRaw = String(calibreInput.value || '').trim();
                if (!cuenta || !calibreRaw) return;

                const calibreNum = parseFloat(calibreRaw);
                const calibreNorm = Number.isFinite(calibreNum)
                    ? calibreNum.toFixed(2).replace(/\.?0+$/, '')
                    : calibreRaw;
                const tamanoEsperado = `${cuenta}-${calibreNorm}/1`;
                const yaExiste = opcionesTamanos.includes(tamanoEsperado);

                // Solo autocompletar si existe en la lista de tamaños (evita valores inválidos/largos)
                if (yaExiste && tamanoInput.value !== tamanoEsperado) {
                    tamanoInput.value = tamanoEsperado;
                    tamanoInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            };

            const actualizarCampo = async (campo, valor) => {
                if (campo === 'Folio') {
                    showError('El folio no se puede editar. Es un campo de solo lectura.', 'Campo No Editable');
                    return;
                }

                try {
                    const response = await fetch(routeActualizar, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            orden_id: ordenId,
                            campo: campo,
                            valor: valor,
                        }),
                    });

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.error || 'Error al actualizar campo');
                    }

                    cambiosPendientes.delete(campo);
                    showToast('success', `${campo} actualizado correctamente`);
                } catch (error) {
                    console.error('Error al actualizar campo:', error);
                    showError(`Error al actualizar ${campo}: ${error.message}`, 'Error al Guardar');
                }
            };

            const actualizarJulioRow = async (row) => {
                const rowId = row.dataset.julioId || null;
                const noJulio = row.querySelector('[data-field="no_julio"]').value.trim();
                const hilos = row.querySelector('[data-field="hilos"]').value.trim();
                const noJulioVacio = noJulio === '';
                const hilosVacio = hilos === '';

                if ((noJulioVacio || hilosVacio) && !(noJulioVacio && hilosVacio && rowId)) {
                    return;
                }

                try {
                    const response = await fetch(routeActualizarJulios, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            orden_id: ordenId,
                            id: rowId || null,
                            no_julio: noJulio !== '' ? noJulio : null,
                            hilos: hilos !== '' ? hilos : null,
                        }),
                    });

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.error || 'Error al actualizar julio');
                    }

                    if (result.data?.deleted) {
                        row.dataset.julioId = '';
                    } else if (result.data?.id) {
                        row.dataset.julioId = String(result.data.id);
                    }

                    showToast('success', result.message || 'Julio actualizado correctamente');
                } catch (error) {
                    console.error('Error al actualizar julio:', error);
                    showError(`Error al actualizar julio: ${error.message}`, 'Error al Guardar');
                }
            };

            document.addEventListener('DOMContentLoaded', () => {
                const camposEditables = document.querySelectorAll('.campo-editable');
                const juliosRows = document.querySelectorAll('[data-julio-row]');
                const juliosTimeouts = new Map();
                const camposUrdidoIds = [
                    'campo_FolioConsumo',
                    'campo_RizoPie',
                    'campo_Cuenta',
                    'campo_Calibre',
                    'campo_Metros',
                    'campo_Kilos',
                    'campo_Fibra',
                    'campo_InventSizeId',
                    'campo_SalonTejidoId',
                    'campo_MaquinaId',
                    'campo_BomId',
                    'campo_FechaProg',
                    'campo_TipoAtado',
                    'campo_LoteProveedor',
                    'campo_Observaciones',
                ];
                const camposEngomadoIds = [
                    'campo_AnchoBalonas',
                    'campo_MetrajeTelas',
                    'campo_Cuentados',
                    'campo_NoTelas',
                    'campo_MaquinaEng',
                    'campo_BomEng',
                    'campo_BomFormula',
                ];

                const bloquearCampos = (ids) => {
                    ids.forEach(id => {
                        const el = document.getElementById(id);
                        if (!el) return;
                        el.disabled = true;
                        el.classList.add('bg-gray-100', 'text-gray-600', 'cursor-not-allowed');
                    });
                };

                camposEditables.forEach(campo => {
                    const campoNombre = campo.dataset.campo;
                    let valorAnterior = campo.value;

                    if (campo.tagName === 'INPUT' || campo.tagName === 'TEXTAREA') {
                        campo.addEventListener('change', () => {
                            if (campo.value !== valorAnterior) {
                                cambiosPendientes.set(campoNombre, campo.value);
                                valorAnterior = campo.value;

                                if (timeoutGuardado) {
                                    clearTimeout(timeoutGuardado);
                                }
                                timeoutGuardado = setTimeout(() => {
                                    actualizarCampo(campoNombre, campo.value);
                                }, 1000);
                            }
                        });

                        campo.addEventListener('blur', () => {
                            if (cambiosPendientes.has(campoNombre)) {
                                actualizarCampo(campoNombre, campo.value);
                            }
                        });
                    }

                    if (campo.tagName === 'SELECT') {
                        campo.addEventListener('change', () => {
                            if (campo.value !== valorAnterior) {
                                valorAnterior = campo.value;
                                actualizarCampo(campoNombre, campo.value);
                            }
                        });
                    }
                });

                juliosRows.forEach(row => {
                    const inputs = row.querySelectorAll('.campo-julio');
                    const rowKey = row.dataset.julioRow || '';

                    inputs.forEach(input => {
                        let valorAnterior = input.value;

                        const scheduleUpdate = () => {
                            if (juliosTimeouts.has(rowKey)) {
                                clearTimeout(juliosTimeouts.get(rowKey));
                            }
                            juliosTimeouts.set(rowKey, setTimeout(() => {
                                actualizarJulioRow(row);
                            }, 1000));
                        };

                        input.addEventListener('change', () => {
                            if (input.value !== valorAnterior) {
                                valorAnterior = input.value;
                                scheduleUpdate();
                            }
                        });

                        input.addEventListener('blur', () => {
                            if (juliosTimeouts.has(rowKey)) {
                                clearTimeout(juliosTimeouts.get(rowKey));
                                juliosTimeouts.delete(rowKey);
                            }
                            actualizarJulioRow(row);
                        });
                    });
                });

                // Cargar catálogos de hilos y tamaños y aplicar autocomplete
                Promise.all([cargarHilos(), cargarTamanos()]).then(() => {
                    actualizarSelectHilos();
                    actualizarListaTamanos();
                    autocompletarTamano();
                });

                const cuentaInput = document.getElementById('campo_Cuenta');
                const calibreInput = document.getElementById('campo_Calibre');
                if (cuentaInput) {
                    cuentaInput.addEventListener('change', autocompletarTamano);
                    cuentaInput.addEventListener('blur', autocompletarTamano);
                }
                if (calibreInput) {
                    calibreInput.addEventListener('change', autocompletarTamano);
                    calibreInput.addEventListener('blur', autocompletarTamano);
                }

                if (bloqueaUrdido) {
                    bloquearCampos(camposUrdidoIds);
                    document.querySelectorAll('.campo-julio').forEach(input => {
                        input.disabled = true;
                        input.classList.add('bg-gray-100', 'text-gray-600', 'cursor-not-allowed');
                    });
                }

                if (bloqueaEngomado) {
                    bloquearCampos(camposEngomadoIds);
                }
            });

            window.guardarCambios = async () => {
                if (cambiosPendientes.size === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin Cambios',
                        html: '<p class="text-gray-700">No hay cambios pendientes para guardar.</p>',
                        confirmButtonColor: '#2563eb',
                        confirmButtonText: 'Aceptar',
                        width: '400px',
                    });
                    return;
                }

                const resultado = await Swal.fire({
                    icon: 'question',
                    title: 'Guardar Cambios',
                    html: `<p class="text-gray-700">Deseas guardar ${cambiosPendientes.size} cambio(s) pendiente(s)?</p>`,
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Si, Guardar',
                    cancelButtonText: 'Cancelar',
                    width: '500px',
                });

                if (!resultado.isConfirmed) {
                    return;
                }

                try {
                    const promesas = Array.from(cambiosPendientes.entries()).map(([campo, valor]) => {
                        return actualizarCampo(campo, valor);
                    });

                    await Promise.all(promesas);
                    cambiosPendientes.clear();
                    showSuccess('Todos los cambios se guardaron correctamente.', 'Cambios Guardados');
                } catch (error) {
                    console.error('Error al guardar cambios:', error);
                    showError('Ocurrio un error al guardar algunos cambios. Por favor, intenta nuevamente.', 'Error al Guardar');
                }
            };
        })();
    </script>
@endsection
