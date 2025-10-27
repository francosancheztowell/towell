@extends('layouts.app', ['ocultarBotones' => true])

@section('navbar-right')
<!-- Controles de prioridad de filas (se muestran solo cuando hay fila seleccionada) -->
<div id="rowPriorityControls" class="flex items-center gap-2 hidden">
	<button type="button" onclick="moveRowUp()"
			class="flex items-center gap-2 px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
		<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
		</svg>
		Subir Prioridad
	</button>

	<button type="button" onclick="moveRowDown()"
			class="flex items-center gap-2 px-3 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
		<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
		</svg>
		Bajar Prioridad
	</button>
</div>
@endsection

@section('menu-planeacion')
<!-- Botones específicos para Programa Tejido -->
<div class="flex items-center gap-2">
	<button type="button" onclick="abrirNuevo(); return false;"
			class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
		<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
		</svg>
		Nuevo
	</button>

	<a href="/submodulos-nivel3/104"
	   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
		<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
		</svg>
		Catálogos
	</a>

	<button type="button" onclick="resetFilters(); return false;"
			class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
		<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
		</svg>
		Restablecer
	</button>

	<button type="button" onclick="openFilterModal(); return false;"
			class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
		<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
		</svg>
		Filtros
		<span id="filterCount" class="ml-2 px-2 py-0.5 bg-white text-purple-600 rounded-full text-xs font-bold hidden">0</span>
	</button>
</div>
@endsection

@section('content')
<div class="container mx-auto px-2 py-8 max-w-full -mt-6">
	<div class="bg-white rounded-lg shadow overflow-hidden w-full mx-auto" style="max-width: 100%;">

		@php
		$columns = [
            	['field' => 'EnProceso', 'label' => 'Estado'],
			['field' => 'CuentaRizo', 'label' => 'Cuenta'],
			['field' => 'CalibreRizo', 'label' => 'Calibre Rizo'],
			['field' => 'SalonTejidoId', 'label' => 'Salón'],
			['field' => 'NoTelarId', 'label' => 'Telar'],
			['field' => 'Ultimo', 'label' => 'Último'],
			['field' => 'CambioHilo', 'label' => 'Cambios Hilo'],
			['field' => 'Maquina', 'label' => 'Maq'],
			['field' => 'Ancho', 'label' => 'Ancho'],
			['field' => 'EficienciaSTD', 'label' => 'Ef Std'],
			['field' => 'VelocidadSTD', 'label' => 'Vel'],
			['field' => 'FibraRizo', 'label' => 'Hilo'],
			['field' => 'CalibrePie', 'label' => 'Calibre Pie'],
			['field' => 'CalendarioId', 'label' => 'Jornada'],
			['field' => 'TamanoClave', 'label' => 'Clave Mod.'],
			['field' => 'NoExisteBase', 'label' => 'Usar cuando no existe en base'],
			['field' => 'ItemId', 'label' => 'Clave AX'],
			['field' => 'InventSizeId', 'label' => 'Tamaño AX'],
			['field' => 'Rasurado', 'label' => 'Rasurado'],
			['field' => 'NombreProducto', 'label' => 'Producto'],
			['field' => 'TotalPedido', 'label' => 'Pedido'],
			['field' => 'Produccion', 'label' => 'Producción'],
			['field' => 'SaldoPedido', 'label' => 'Saldos'],
			['field' => 'SaldoMarbete', 'label' => 'Saldo Marbetes'],
			['field' => 'ProgramarProd', 'label' => 'Día Scheduling'],
			['field' => 'NoProduccion', 'label' => 'Orden Prod.'],
			['field' => 'Programado', 'label' => 'INN'],
			['field' => 'FlogsId', 'label' => 'Id Flog'],
			['field' => 'NombreProyecto', 'label' => 'Descrip.'],
			['field' => 'CustName', 'label' => 'Nombre Cliente'],
			['field' => 'AplicacionId', 'label' => 'Aplic.'],
			['field' => 'Observaciones', 'label' => 'Obs'],
			['field' => 'TipoPedido', 'label' => 'Tipo Ped.'],
			['field' => 'NoTiras', 'label' => 'Tiras'],
			['field' => 'Peine', 'label' => 'Pei.'],
			['field' => 'Luchaje', 'label' => 'Lcr'],
			['field' => 'PesoCrudo', 'label' => 'Pcr'],
			['field' => 'CalibreTrama', 'label' => 'Calibre Tra'],
			['field' => 'FibraTrama', 'label' => 'Fibra Trama'],
			['field' => 'DobladilloId', 'label' => 'Dob'],
			['field' => 'PasadasTrama', 'label' => 'Pasadas Tra'],
			['field' => 'PasadasComb1', 'label' => 'Pasadas C1'],
			['field' => 'PasadasComb2', 'label' => 'Pasadas C2'],
			['field' => 'PasadasComb3', 'label' => 'Pasadas C3'],
			['field' => 'PasadasComb4', 'label' => 'Pasadas C4'],
			['field' => 'PasadasComb5', 'label' => 'Pasadas C5'],
			['field' => 'AnchoToalla', 'label' => 'Ancho por Toalla'],
			['field' => 'CodColorTrama', 'label' => 'Código Color Tra'],
			['field' => 'ColorTrama', 'label' => 'Color Tra'],
			['field' => 'CalibreComb12', 'label' => 'Calibre C1'],
			['field' => 'FibraComb1', 'label' => 'Fibra C1'],
			['field' => 'CodColorComb1', 'label' => 'Código Color C1'],
			['field' => 'NombreCC1', 'label' => 'Color C1'],
			['field' => 'CalibreComb22', 'label' => 'Calibre C2'],
			['field' => 'FibraComb2', 'label' => 'Fibra C2'],
			['field' => 'CodColorComb2', 'label' => 'Código Color C2'],
			['field' => 'NombreCC2', 'label' => 'Color C2'],
			['field' => 'CalibreComb32', 'label' => 'Calibre C3'],
			['field' => 'FibraComb3', 'label' => 'Fibra C3'],
			['field' => 'CodColorComb3', 'label' => 'Código Color C3'],
			['field' => 'NombreCC3', 'label' => 'Color C3'],
			['field' => 'CalibreComb42', 'label' => 'Calibre C4'],
			['field' => 'FibraComb4', 'label' => 'Fibra C4'],
			['field' => 'CodColorComb4', 'label' => 'Código Color C4'],
			['field' => 'NombreCC4', 'label' => 'Color C4'],
			['field' => 'CalibreComb52', 'label' => 'Calibre C5'],
			['field' => 'FibraComb5', 'label' => 'Fibra C5'],
			['field' => 'CodColorComb5', 'label' => 'Código Color C5'],
			['field' => 'NombreCC5', 'label' => 'Color C5'],
			['field' => 'MedidaPlano', 'label' => 'Plano'],
			['field' => 'CuentaPie', 'label' => 'Cuenta Pie'],
			['field' => 'CodColorCtaPie', 'label' => 'Código Color Pie'],
			['field' => 'NombreCPie', 'label' => 'Color Pie'],
			['field' => 'PesoGRM2', 'label' => 'Peso (gr/m²)'],
			['field' => 'DiasEficiencia', 'label' => 'Días Ef.'],
			['field' => 'ProdKgDia', 'label' => 'Prod (Kg)/Día'],
			['field' => 'StdDia', 'label' => 'Std/Día'],
			['field' => 'ProdKgDia2', 'label' => 'Prod (Kg)/Día 2'],
			['field' => 'StdToaHra', 'label' => 'Std (Toa/Hr) 100%'],
			['field' => 'DiasJornada', 'label' => 'Días Jornada'],
			['field' => 'HorasProd', 'label' => 'Horas'],
			['field' => 'StdHrsEfect', 'label' => 'Std/Hr Efectivo'],
			['field' => 'FechaInicio', 'label' => 'Inicio'],
			['field' => 'Calc4', 'label' => 'Calc4'],
			['field' => 'Calc5', 'label' => 'Calc5'],
			['field' => 'Calc6', 'label' => 'Calc6'],
			['field' => 'FechaFinal', 'label' => 'Fin'],
			['field' => 'EntregaProduc', 'label' => 'Fecha Compromiso Prod.'],
			['field' => 'EntregaPT', 'label' => 'Fecha Compromiso PT'],
			['field' => 'EntregaCte', 'label' => 'Entrega'],
			['field' => 'PTvsCte', 'label' => 'Dif vs Compromiso'],
		];

		$formatValue = function($registro, $field) {
			$value = $registro->{$field} ?? null;
			if ($value === null || $value === '') return '';

			if ($field === 'EficienciaSTD' && is_numeric($value)) {
				return rtrim(rtrim(number_format(((float)$value) * 100, 0), '0'), '.') . '%';
			}

			// Campos fecha conocidos (usa claves, no labels)
			$fechaCampos = [
				'Programado','ProgramarProd','FechaInicio','FechaFinal',
				'EntregaProduc','EntregaPT','EntregaCte'
			];

			if (in_array($field, $fechaCampos, true)) {
				try {
					if ($value instanceof \Carbon\Carbon) {
						return $value->year > 1970 ? strtolower($value->format('d-M')) : '';
					}
					$dt = \Carbon\Carbon::parse($value);
					return $dt->year > 1970 ? strtolower($dt->format('d-M')) : '';
				} catch (\Exception $e) {
					return '';
				}
			}

			// Números con decimales
			if (is_numeric($value) && !preg_match('/^\d+$/', (string)$value)) {
				return number_format((float)$value, 2);
			}

			return $value;
		};
		@endphp

		@if($registros->count() > 0)
			<div class="border border-gray-200 rounded-lg overflow-hidden">
			<div class="overflow-x-auto md:overflow-x-scroll lg:overflow-x-auto">
					<div class="overflow-y-auto" style="max-height: 320px;">
						<table id="mainTable" class="min-w-full divide-y divide-gray-200">
							<thead class="bg-blue-500 text-white">
								<tr>
									@foreach($columns as $index => $col)
									<th class="px-4 py-2 text-left text-xs font-semibold text-white whitespace-nowrap column-{{ $index }}"
										style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6;"
										data-column="{{ $col['field'] }}" data-index="{{ $index }}">
										<div class="flex items-center justify-between gap-2">
											<span class="flex-1">{{ $col['label'] }}</span>
											<div class="flex items-center gap-2">
												<button type="button" onclick="togglePinColumn({{ $index }})"
														class="pin-btn bg-yellow-500 hover:bg-yellow-600 text-white p-1.5 rounded-md transition-all duration-200 shadow-sm hover:shadow-md"
														title="Fijar columna">
													<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
													</svg>
												</button>
												<button type="button" onclick="hideColumn({{ $index }})"
														class="hide-btn bg-red-500 hover:bg-red-600 text-white p-1.5 rounded-md transition-all duration-200 shadow-sm hover:shadow-md"
														title="Ocultar columna">
													<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
													</svg>
												</button>
											</div>
										</div>
									</th>
							@endforeach
						</tr>
					</thead>
					<tbody class="bg-white divide-y divide-gray-100">
								@foreach($registros as $index => $registro)
								<tr class="hover:bg-blue-50 cursor-pointer selectable-row" data-row-index="{{ $index }}">
										@foreach($columns as $colIndex => $col)
										<td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap column-{{ $colIndex }}"
											data-column="{{ $col['field'] }}">
											{{ $formatValue($registro, $col['field']) }}
										</td>
								@endforeach
							</tr>
						@endforeach
					</tbody>
				</table>
					</div>
				</div>
			</div>
		@else
			<div class="px-6 py-12 text-center">
				<svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
				</svg>
				<h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros</h3>
				<p class="mt-1 text-sm text-gray-500">No se han importado registros aún. Carga un archivo Excel para comenzar.</p>
				<div class="mt-6">
					<a href="{{ route('configuracion.utileria.cargar.catalogos') }}"
					   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
						<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
						</svg>
						Cargar Archivo Excel
					</a>
				</div>
			</div>
		@endif
	</div>
</div>

<style>
	/* Igual que tu diseño, con apoyo para “pinned” */
	.pinned-column { position: sticky !important; background-color: #3b82f6 !important; color: #fff !important; }
</style>

<script>
// ===== Estado =====
let filters = [];
let hiddenColumns = [];
let pinnedColumns = [];
let allRows = [];
let selectedRowIndex = -1;

// ===== Helpers DOM =====
const $ = (sel, ctx=document) => ctx.querySelector(sel);
const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));
const tbodyEl = () => $('#mainTable tbody');

// ===== Filtros =====
function renderFilterModalContent() {
	const options = @json(array_map(fn($c)=>['field'=>$c['field'],'label'=>$c['label']], $columns));
	const filtrosHTML = filters.length
		? `<div class="mb-4 p-3 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                <div class="space-y-1">
					${filters.map((f,i)=>`
                        <div class="flex items-center justify-between bg-white p-2 rounded border">
							<span class="text-xs">${f.column}: ${f.value}</span>
							<button onclick="removeFilter(${i})" class="text-red-500 hover:text-red-700 text-xs">×</button>
                        </div>
                    `).join('')}
                </div>
		   </div>`
		: '';

	return `
		${filtrosHTML}
            <div class="text-left space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Columna</label>
                    <select id="filtro-columna" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecciona una columna...</option>
					${options.map(col=>`<option value="${col.field}">${col.label}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valor a buscar</label>
                    <input type="text" id="filtro-valor" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ingresa el valor a buscar">
                </div>
                <div class="flex gap-2 pt-2">
                    <button type="button" id="btn-agregar-otro" class="flex-1 px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                        + Agregar Otro Filtro
                    </button>
                </div>
            </div>
	`;
}

function openFilterModal() {
	Swal.fire({
		title: 'Filtrar por Columna',
		html: renderFilterModalContent(),
        showCancelButton: true,
        confirmButtonText: 'Agregar Filtro',
        cancelButtonText: 'Cerrar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        width: '450px',
        didOpen: () => {
			$('#btn-agregar-otro').addEventListener('click', () => {
				const col = $('#filtro-columna').value;
				const val = $('#filtro-valor').value;
				if (!col || !val) return Swal.showValidationMessage('Selecciona columna y valor');
				if (filters.some(f => f.column===col && f.value===val)) return Swal.showValidationMessage('Este filtro ya está activo');

				filters.push({ column: col, value: val });
                applyFilters();
                showToast('Filtro agregado correctamente', 'success');
				Swal.update({ html: renderFilterModalContent() });
				openFilterModal(); // re-abre para re-inyectar listeners
			});
		},
		preConfirm: () => {
			const col = $('#filtro-columna').value;
			const val = $('#filtro-valor').value;
			if (!col || !val) { Swal.showValidationMessage('Selecciona columna y valor'); return false; }
			if (filters.some(f => f.column===col && f.value===val)) { Swal.showValidationMessage('Este filtro ya está activo'); return false; }
			return { column: col, value: val };
		}
	}).then(res => {
		if (res.isConfirmed) {
			filters.push(res.value);
            applyFilters();
            showToast('Filtro agregado correctamente', 'success');
        }
    });
}

function removeFilter(index) {
    filters.splice(index, 1);
    applyFilters();
    showToast('Filtro eliminado', 'info');
	Swal.update({ html: renderFilterModalContent() });
}

function applyFilters() {
	let rows = allRows.slice();
	if (filters.length) {
		rows = rows.filter(tr => {
			return filters.every(f => {
				const cell = tr.querySelector(`[data-column="${f.column}"]`);
				return cell ? cell.textContent.toLowerCase().includes(f.value.toLowerCase()) : false;
			});
		});
	}
	const tb = tbodyEl();
	tb.innerHTML = '';
	rows.forEach((r,i) => {
		r.onclick = () => selectRow(r, i);
		tb.appendChild(r);
	});
	updateFilterCount();
	if (filters.length) showToast(`Filtros aplicados<br>${filters.length} filtro(s) · ${rows.length} resultado(s)`, 'success');
}

function updateFilterCount() {
	const badge = $('#filterCount');
	if (!badge) return;
	if (filters.length > 0) {
		badge.textContent = filters.length;
		badge.classList.remove('hidden');
	} else {
		badge.classList.add('hidden');
    }
}

function resetFilters() {
	// Filas originales
	const tb = tbodyEl();
	tb.innerHTML = '';
	allRows.forEach((r,i) => {
		r.classList.remove('bg-blue-500','text-white','hover:bg-blue-50');
		r.classList.add('hover:bg-blue-50');
		$$('td', r).forEach(td => td.classList.remove('text-white','text-gray-700'));
		r.onclick = () => selectRow(r, i);
		tb.appendChild(r);
	});

	// Mostrar columnas ocultas
	hiddenColumns.forEach(idx => {
		$$(`.column-${idx}`).forEach(el => el.style.display = '');
		const hideBtn = $(`th.column-${idx} .hide-btn`);
        if (hideBtn) {
            hideBtn.classList.remove('bg-red-600');
            hideBtn.classList.add('bg-red-500');
            hideBtn.title = 'Ocultar columna';
        }
    });
    hiddenColumns = [];

	// Desfijar columnas
    pinnedColumns = [];
	updatePinnedColumnsPositions(); // limpiará estilos

	// UI filtros
	filters = [];
    updateFilterCount();

	// Ocultar controles de prioridad
	const rpc = $('#rowPriorityControls');
	if (rpc) rpc.classList.add('hidden');

	selectedRowIndex = -1;

	showToast('Restablecido<br>Se limpiaron filtros, fijados y columnas ocultas', 'success');
}

// ===== Columnas: ocultar / fijar =====
function hideColumn(index) {
	$$(`.column-${index}`).forEach(el => el.style.display = 'none');
	const hideBtn = $(`th.column-${index} .hide-btn`);
    if (hideBtn) {
        hideBtn.classList.remove('bg-red-500');
        hideBtn.classList.add('bg-red-600');
        hideBtn.title = 'Columna oculta';
    }
	if (!hiddenColumns.includes(index)) hiddenColumns.push(index);
	showToast(`Columna oculta`, 'info');
}

function togglePinColumn(index) {
	const exists = pinnedColumns.includes(index);
	if (exists) pinnedColumns = pinnedColumns.filter(i => i !== index);
	else pinnedColumns.push(index);
	pinnedColumns.sort((a,b)=>a-b);

	// Botón estado
	const pinBtn = $(`th.column-${index} .pin-btn`);
	if (pinBtn) {
		pinBtn.classList.toggle('bg-yellow-600', !exists);
		pinBtn.classList.toggle('bg-yellow-500', exists);
		pinBtn.title = exists ? 'Fijar columna' : 'Desfijar columna';
	}

	updatePinnedColumnsPositions();
}

function updatePinnedColumnsPositions() {
	// Limpia estilos de todas primero
	const allIdx = [...new Set($$('th[class*="column-"]').map(th => +th.dataset.index))];
	allIdx.forEach(idx => {
		$$(`.column-${idx}`).forEach(el => {
			// Mantén sticky top en TH, pero quita left/zIndex/background si no está fijada
			if (el.tagName === 'TH') {
				el.style.top = '0';
                el.style.position = 'sticky';
                el.style.zIndex = '30';
                el.style.backgroundColor = '#3b82f6';
				el.style.color = '#fff';
            } else {
                el.style.position = '';
                el.style.top = '';
                el.style.zIndex = '';
                el.style.backgroundColor = '';
                el.style.color = '';
            }
			el.style.left = '';
			el.classList.remove('pinned-column');
		});
	});

	// Aplica fijados en orden
	let left = 0;
	pinnedColumns.forEach((idx, order) => {
		const th = $(`th.column-${idx}`);
		if (!th || th.style.display === 'none') return;

		const width = th.offsetWidth;
		$$(`.column-${idx}`).forEach(el => {
            el.classList.add('pinned-column');
			el.style.left = left + 'px';
			if (el.tagName === 'TH') {
				el.style.top = '0';
				el.style.zIndex = String(40 + order);
            el.style.position = 'sticky';
            } else {
				el.style.zIndex = String(35 + order);
				el.style.position = 'sticky';
			}
		});
		left += width;
	});
}

// ===== Selección de filas / prioridad =====
function selectRow(rowElement, rowIndex) {
	// Toggle si ya estaba seleccionada
    if (selectedRowIndex === rowIndex && rowElement.classList.contains('bg-blue-500')) {
		return deselectRow();
	}

	// Limpiar selección previa
	$$('.selectable-row').forEach(row => {
		row.classList.remove('bg-blue-500','text-white');
        row.classList.add('hover:bg-blue-50');
		$$('td', row).forEach(td => {
			td.classList.remove('text-white');
			td.classList.add('text-gray-700');
        });
    });

	// Seleccionar actual
	rowElement.classList.add('bg-blue-500','text-white');
    rowElement.classList.remove('hover:bg-blue-50');
	$$('td', rowElement).forEach(td => {
		td.classList.add('text-white');
		td.classList.remove('text-gray-700');
	});

    selectedRowIndex = rowIndex;

	// Mostrar controles
	const rpc = $('#rowPriorityControls');
	if (rpc) rpc.classList.remove('hidden');
}

function deselectRow() {
	$$('.selectable-row').forEach(row => {
		row.classList.remove('bg-blue-500','text-white');
        row.classList.add('hover:bg-blue-50');
		$$('td', row).forEach(td => {
			td.classList.remove('text-white');
			td.classList.add('text-gray-700');
        });
    });
    selectedRowIndex = -1;
	const rpc = $('#rowPriorityControls');
	if (rpc) rpc.classList.add('hidden');
}

function moveRowUp() {
	const tb = tbodyEl();
	if (selectedRowIndex <= 0) return;
	const rows = $$('.selectable-row', tb);
	tb.insertBefore(rows[selectedRowIndex], rows[selectedRowIndex - 1]);
        selectedRowIndex--;
	selectRow($$('.selectable-row', tb)[selectedRowIndex], selectedRowIndex);
	showToast('Fila movida<br>Se movió hacia arriba', 'success');
}

function moveRowDown() {
	const tb = tbodyEl();
	const rows = $$('.selectable-row', tb);
	if (selectedRowIndex < 0 || selectedRowIndex >= rows.length - 1) return;
	tb.insertBefore(rows[selectedRowIndex + 1], rows[selectedRowIndex]);
        selectedRowIndex++;
	selectRow($$('.selectable-row', tb)[selectedRowIndex], selectedRowIndex);
	showToast('Fila movida<br>Se movió hacia abajo', 'success');
}

// ===== Función para abrir nuevo registro =====
function abrirNuevo() {
	window.location.href = '/planeacion/programa-tejido/nuevo';
}

// ===== Init =====
document.addEventListener('DOMContentLoaded', function() {
	const tb = tbodyEl();
	if (tb) {
		allRows = $$('.selectable-row', tb);
		allRows.forEach((row, i) => row.addEventListener('click', () => selectRow(row, i)));
	}
	updateFilterCount();
	window.addEventListener('resize', () => updatePinnedColumnsPositions());
});
</script>

@include('components.toast-notification')
@endsection
