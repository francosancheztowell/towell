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
    <!-- Back Button -->



	<!-- Tabla en el orden del Excel -->
	<div class="bg-white rounded-lg shadow overflow-hidden w-full mx-auto" style="max-width: 100%;">


		@php
		$columns = [
			// Orden según el Excel
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
			if (is_null($value) || $value === '') return '';

			// 1) Porcentaje para Ef Std (0.77 -> 77%)
			if ($field === 'EficienciaSTD' && is_numeric($value)) {
				return rtrim(rtrim(number_format(((float)$value) * 100, 0), '0'), '.') . '%';
			}

			// 2) Formateo de Fechas estilo "19-sep" para campos fecha conocidos
			$fechaCampos = ['Programado','ProgramarProd','FechaInicio','FechaFinal','EntregaProduc','EntregaPT','EntregaCte','Día Scheduling'];
			if (in_array($field, $fechaCampos, true)) {
				try {
					// Si es Carbon
					if ($value instanceof \Carbon\Carbon) {
						return $value->year > 1970 ? strtolower($value->format('d-M')) : '';
					}
					// Si viene como string: intentar parsear
					$dt = \Carbon\Carbon::parse($value);
					return $dt->year > 1970 ? strtolower($dt->format('d-M')) : '';
				} catch (\Exception $e) {
					return '';
				}
			}

			// 3) Números con decimales -> 2 decimales (mantener comportamiento previo)
			if (is_numeric($value) && !preg_match('/^\d+$/', (string)$value)) return number_format((float)$value, 2);

			// 4) Campos de texto que eran boolean
			if (in_array($field, ['Ultimo','CambioHilo'], true)) {
				return $value;
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
									<th class="px-4 py-2 text-left text-xs font-semibold text-white uppercase whitespace-nowrap column-{{ $index }}" style="position: sticky; top: 0; z-index: 30; background-color: #3b82f6;" data-column="{{ $col['field'] }}" data-index="{{ $index }}">
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
									<tr class="hover:bg-blue-50 cursor-pointer selectable-row"
										data-row-index="{{ $index }}">
										@foreach($columns as $colIndex => $col)
											<td class="px-3 py-2 text-sm text-gray-700 whitespace-nowrap column-{{ $colIndex }}" data-column="{{ $col['field'] }}">{{ $formatValue($registro, $col['field']) }}</td>
								@endforeach
							</tr>
						@endforeach
					</tbody>
				</table>
					</div>
				</div>
			</div>

			<!-- Sin paginación: se muestran todos los registros -->
		@else
			<div class="px-6 py-12 text-center">
				<svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.29-1.009-5.824-2.709" />
				</svg>
				<h3 class="mt-2 text-sm font-medium text-gray-900">No hay registros</h3>
				<p class="mt-1 text-sm text-gray-500">No se han importado registros aún. Carga un archivo Excel para comenzar.</p>
				<div class="mt-6">
					<a href="{{ route('catalogos.index') }}"
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


<script>
// Estado global
let filters = [];
let hiddenColumns = [];
let pinnedColumns = [];
let columnsData = @json(array_map(function($col) { return ['field' => $col['field'], 'label' => $col['label']]; }, $columns));
let allRows = [];
let selectedFilterIndex = -1;
let selectedRowIndex = -1;

// ===== FUNCIONES DE FILTROS =====
function openFilterModal() {
    // Generar lista de filtros activos
    let filtrosActivosHTML = '';
    if (filters.length > 0) {
        filtrosActivosHTML = `
            <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                <div class="space-y-1">
                    ${filters.map((filtro, index) => `
                        <div class="flex items-center justify-between bg-white p-2 rounded border">
                            <span class="text-xs">${filtro.column}: ${filtro.value}</span>
                            <button onclick="removeFilter(${index})" class="text-red-500 hover:text-red-700 text-xs">×</button>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    Swal.fire({
        title: 'Filtrar por Columna',
        html: `
            ${filtrosActivosHTML}
            <div class="text-left space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Columna</label>
                    <select id="filtro-columna" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecciona una columna...</option>
                        ${columnsData.map(col => `<option value="${col.field}">${col.label}</option>`).join('')}
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
        `,
        showCancelButton: true,
        confirmButtonText: 'Agregar Filtro',
        cancelButtonText: 'Cerrar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        width: '450px',
        preConfirm: () => {
            const columna = document.getElementById('filtro-columna').value;
            const valor = document.getElementById('filtro-valor').value;

            if (!columna || !valor) {
                Swal.showValidationMessage('Por favor selecciona una columna e ingresa un valor');
                return false;
            }

            // Verificar si ya existe este filtro
            const existeFiltro = filters.some(f => f.column === columna && f.value === valor);
            if (existeFiltro) {
                Swal.showValidationMessage('Este filtro ya está activo');
                return false;
            }

            return { column: columna, value: valor };
        },
        didOpen: () => {
            // Agregar event listener al botón "Agregar Otro Filtro"
            document.getElementById('btn-agregar-otro').addEventListener('click', () => {
                const columna = document.getElementById('filtro-columna').value;
                const valor = document.getElementById('filtro-valor').value;

                if (!columna || !valor) {
                    Swal.showValidationMessage('Por favor selecciona una columna e ingresa un valor');
                    return;
                }

                // Verificar si ya existe este filtro
                const existeFiltro = filters.some(f => f.column === columna && f.value === valor);
                if (existeFiltro) {
                    Swal.showValidationMessage('Este filtro ya está activo');
                    return;
                }

                // Agregar filtro y limpiar campos
                filters.push({ column: columna, value: valor });
                applyFilters();
                showToast('Filtro agregado correctamente', 'success');

                // Limpiar campos para el siguiente filtro
                document.getElementById('filtro-valor').value = '';

                // Actualizar la vista del modal con los nuevos filtros activos
                updateFilterModal();
            });
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Agregar nuevo filtro
            filters.push(result.value);

            // Aplicar filtros
            applyFilters();

            showToast('Filtro agregado correctamente', 'success');
        }
    });
}


function removeFilter(index) {
    filters.splice(index, 1);
    applyFilters();
    showToast('Filtro eliminado', 'info');
    updateFilterModal();
}

function updateFilterModal() {
    // Generar nueva lista de filtros activos
    let filtrosActivosHTML = '';
    if (filters.length > 0) {
        filtrosActivosHTML = `
            <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Filtros Activos:</h4>
                <div class="space-y-1">
                    ${filters.map((filtro, index) => `
                        <div class="flex items-center justify-between bg-white p-2 rounded border">
                            <span class="text-xs">${filtro.column}: ${filtro.value}</span>
                            <button onclick="removeFilter(${index})" class="text-red-500 hover:text-red-700 text-xs">×</button>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    // Actualizar el contenido del modal
    const modalContent = document.querySelector('.swal2-html-container');
    if (modalContent) {
        modalContent.innerHTML = `
            ${filtrosActivosHTML}
            <div class="text-left space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Columna</label>
                    <select id="filtro-columna" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecciona una columna...</option>
                        ${columnsData.map(col => `<option value="${col.field}">${col.label}</option>`).join('')}
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

        // Reagregar event listener al botón
        document.getElementById('btn-agregar-otro').addEventListener('click', () => {
            const columna = document.getElementById('filtro-columna').value;
            const valor = document.getElementById('filtro-valor').value;

            if (!columna || !valor) {
                Swal.showValidationMessage('Por favor selecciona una columna e ingresa un valor');
                return;
            }

            // Verificar si ya existe este filtro
            const existeFiltro = filters.some(f => f.column === columna && f.value === valor);
            if (existeFiltro) {
                Swal.showValidationMessage('Este filtro ya está activo');
                return;
            }

            // Agregar filtro y limpiar campos
            filters.push({ column: columna, value: valor });
            applyFilters();
            showToast('Filtro agregado correctamente', 'success');

            // Limpiar campos para el siguiente filtro
            document.getElementById('filtro-valor').value = '';

            // Actualizar la vista del modal con los nuevos filtros activos
            updateFilterModal();
        });
    }
}

function resetFilters() {
    // Restaurar todas las filas
    const tbody = document.querySelector('#mainTable tbody');
    tbody.innerHTML = '';
    allRows.forEach((row, index) => {
        // Re-agregar event listeners para selección de filas
        row.addEventListener('click', function() {
            selectRow(this, index);
        });
        tbody.appendChild(row);
    });

    // Mostrar todas las columnas
    hiddenColumns.forEach(index => {
        const elements = document.querySelectorAll(`.column-${index}`);
        const hideBtn = document.querySelector(`.column-${index} .hide-btn`);

        elements.forEach(el => {
            el.style.display = '';
        });

        // Restaurar apariencia del botón ocultar
        if (hideBtn) {
            hideBtn.classList.remove('bg-red-600');
            hideBtn.classList.add('bg-red-500');
            hideBtn.title = 'Ocultar columna';
        }
    });

    // Quitar todas las columnas fijadas
    pinnedColumns.forEach(index => {
        const elements = document.querySelectorAll(`.column-${index}`);
        const pinBtn = document.querySelector(`.column-${index} .pin-btn`);

        elements.forEach(el => {
            el.classList.remove('pinned-column');
            const isHeader = el.tagName === 'TH';

            if (isHeader) {
                // Restaurar estado original del header (sticky top solamente)
                el.style.position = 'sticky';
                el.style.left = '';
                el.style.top = '0';
                el.style.zIndex = '30';
                el.style.backgroundColor = '#3b82f6';
                el.style.color = 'white';
            } else {
                // Las celdas normales no tienen sticky
                el.style.position = '';
                el.style.left = '';
                el.style.top = '';
                el.style.zIndex = '';
                el.style.backgroundColor = '';
                el.style.color = '';
            }
        });

        // Restaurar apariencia del botón fijar
        if (pinBtn) {
            pinBtn.classList.remove('bg-yellow-600');
            pinBtn.classList.add('bg-yellow-500');
            pinBtn.title = 'Fijar columna';
        }
    });

    // Limpiar estado
    filters = [];
    hiddenColumns = [];
    pinnedColumns = [];
    selectedFilterIndex = -1;

    // Actualizar UI
    updateFilterCount();

    // Ocultar badge de filtros
    const filterBadge = document.getElementById('filterCount');
    if (filterBadge) {
        filterBadge.classList.add('hidden');
        filterBadge.textContent = '0';
    }

    showToast('Restablecido<br>Todos los filtros y configuraciones han sido eliminados', 'success');
}

// ===== FUNCIONES DE COLUMNAS =====
function hideColumn(index) {
    const elements = document.querySelectorAll(`.column-${index}`);
    const hideBtn = document.querySelector(`.column-${index} .hide-btn`);

    elements.forEach(el => {
        el.style.display = 'none';
    });

    // Cambiar apariencia del botón
    if (hideBtn) {
        hideBtn.classList.remove('bg-red-500');
        hideBtn.classList.add('bg-red-600');
        hideBtn.title = 'Columna oculta';
    }

    if (!hiddenColumns.includes(index)) {
        hiddenColumns.push(index);
    }

    showColumnVisibilityAlert();
}

function togglePinColumn(index) {
    const elements = document.querySelectorAll(`.column-${index}`);
    const isPinned = pinnedColumns.includes(index);
    const pinBtn = document.querySelector(`.column-${index} .pin-btn`);

    if (isPinned) {
        // Desfijar columna
        elements.forEach(el => {
            el.classList.remove('pinned-column');
            const isHeader = el.tagName === 'TH';

            if (isHeader) {
                // Restaurar estado original del header (sticky top solamente)
                el.style.position = 'sticky';
                el.style.left = '';
                el.style.top = '0';
                el.style.zIndex = '30';
                el.style.backgroundColor = '#3b82f6';
                el.style.color = 'white';
            } else {
                // Las celdas normales no tienen sticky
                el.style.position = '';
                el.style.left = '';
                el.style.top = '';
                el.style.zIndex = '';
                el.style.backgroundColor = '';
                el.style.color = '';
            }
        });

        pinnedColumns = pinnedColumns.filter(i => i !== index);

        // Cambiar apariencia del botón
        if (pinBtn) {
            pinBtn.classList.remove('bg-yellow-600');
            pinBtn.classList.add('bg-yellow-500');
            pinBtn.title = 'Fijar columna';
        }
    } else {
        // Fijar columna
        pinnedColumns.push(index);
        pinnedColumns.sort((a, b) => a - b); // Ordenar por índice

        // Cambiar apariencia del botón
        if (pinBtn) {
            pinBtn.classList.remove('bg-yellow-500');
            pinBtn.classList.add('bg-yellow-600');
            pinBtn.title = 'Desfijar columna';
        }
    }

    // Actualizar todas las columnas fijadas con sus posiciones correctas
    updatePinnedColumnsPositions();
}

function updatePinnedColumnsPositions() {
    let leftOffset = 0;

    pinnedColumns.forEach((colIndex, orderIndex) => {
        const elements = document.querySelectorAll(`.column-${colIndex}`);

        // Obtener el ancho de la primera celda (th) para calcular el offset
        const firstElement = elements[0];
        const columnWidth = firstElement ? firstElement.offsetWidth : 0;

        elements.forEach(el => {
            el.classList.add('pinned-column');
            el.style.position = 'sticky';
            el.style.left = `${leftOffset}px`;

            // Z-index más alto para columnas fijadas
            // Para headers (th): también sticky top para scroll vertical
            const isHeader = el.tagName === 'TH';
            if (isHeader) {
                el.style.top = '0';
                el.style.zIndex = `${40 + orderIndex}`;
            } else {
                el.style.zIndex = `${35 + orderIndex}`;
            }

            el.style.backgroundColor = '#3b82f6';
            el.style.color = 'white';
        });

        leftOffset += columnWidth;
    });
}

function showColumnVisibilityAlert() {
    if (hiddenColumns.length > 0) {
        showToast(`Columnas ocultas<br>Tienes ${hiddenColumns.length} columna(s) oculta(s). Usa "Restablecer" para mostrarlas de nuevo.`, 'info');
    }
}

// ===== FUNCIONES DE FILTROS =====
function applyFilters() {
    // Aplicar filtros a la tabla usando el array filters
    let visibleRows = allRows;

    if (filters.length > 0) {
        filters.forEach(filter => {
        visibleRows = visibleRows.filter(row => {
            const cell = row.querySelector(`[data-column="${filter.column}"]`);
            if (cell) {
                const cellValue = cell.textContent.toLowerCase();
                const filterValue = filter.value.toLowerCase();
                return cellValue.includes(filterValue);
            }
            return false;
        });
    });
    }

    // Actualizar tabla
    const tbody = document.querySelector('#mainTable tbody');
    tbody.innerHTML = '';
    visibleRows.forEach((row, index) => {
        // Re-agregar event listeners para selección de filas
        row.addEventListener('click', function() {
            selectRow(this, index);
        });
        tbody.appendChild(row);
    });

    // Actualizar UI
    updateFilterCount();

    if (filters.length > 0) {
        showToast(`Filtros aplicados<br>Se aplicaron ${filters.length} filtro(s) - ${visibleRows.length} resultado(s) encontrado(s)`, 'success');
    }
}

function updateActiveFiltersDisplay() {
    // Función simplificada - ya no se necesita
    updateFilterCount();
}




function updateFilterCount() {
    const badge = document.getElementById('filterCount');
    if (filters.length > 0) {
        badge.textContent = filters.length;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}


// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    // Guardar todas las filas originales
    const tbody = document.querySelector('#mainTable tbody');
    if (tbody) {
        allRows = Array.from(tbody.querySelectorAll('tr'));

        // Agregar event listeners iniciales a todas las filas
        allRows.forEach((row, index) => {
            row.addEventListener('click', function() {
                selectRow(this, index);
            });
        });
    }

    // Inicializar contador de filtros
    const badge = document.getElementById('filterCount');
    if (badge) {
        badge.classList.add('hidden');
        badge.textContent = '0';
    }

    // Agregar primer filtro automáticamente
    addFilterRow();
});

// ===== FUNCIONES DE SELECCIÓN DE FILAS =====
function selectRow(rowElement, rowIndex) {
    // Si la fila ya está seleccionada, deseleccionarla
    if (selectedRowIndex === rowIndex && rowElement.classList.contains('bg-blue-500')) {
        deselectRow();
        return;
    }

    // Remover selección anterior
    const allSelectableRows = document.querySelectorAll('.selectable-row');
    allSelectableRows.forEach(row => {
        row.classList.remove('bg-blue-500', 'text-white');
        row.classList.add('hover:bg-blue-50');

        // Restaurar color de texto de las celdas
        const cells = row.querySelectorAll('td');
        cells.forEach(cell => {
            cell.classList.remove('text-white');
            cell.classList.add('text-gray-700');
        });
    });

    // Seleccionar nueva fila
    rowElement.classList.add('bg-blue-500', 'text-white');
    rowElement.classList.remove('hover:bg-blue-50');

    // Cambiar color de texto de las celdas
    const cells = rowElement.querySelectorAll('td');
    cells.forEach(cell => {
        cell.classList.add('text-white');
        cell.classList.remove('text-gray-700');
    });

    // Actualizar índice seleccionado
    selectedRowIndex = rowIndex;

    // Mostrar controles de prioridad de filas
    const rowPriorityControls = document.getElementById('rowPriorityControls');
    if (rowPriorityControls) {
        rowPriorityControls.classList.remove('hidden');
    }

    console.log('Fila seleccionada:', rowIndex); // Debug
}

function deselectRow() {
    // Remover selección de todas las filas
    const allSelectableRows = document.querySelectorAll('.selectable-row');
    allSelectableRows.forEach(row => {
        row.classList.remove('bg-blue-500', 'text-white');
        row.classList.add('hover:bg-blue-50');

        // Restaurar color de texto de las celdas
        const cells = row.querySelectorAll('td');
        cells.forEach(cell => {
            cell.classList.remove('text-white');
            cell.classList.add('text-gray-700');
        });
    });

    // Actualizar índice seleccionado
    selectedRowIndex = -1;

    // Ocultar controles de prioridad de filas
    const rowPriorityControls = document.getElementById('rowPriorityControls');
    if (rowPriorityControls) {
        rowPriorityControls.classList.add('hidden');
    }

    console.log('Fila deseleccionada'); // Debug
}

function moveRowUp() {
    if (selectedRowIndex > 0) {
        const tbody = document.querySelector('#mainTable tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        // Intercambiar filas
        const currentRow = rows[selectedRowIndex];
        const previousRow = rows[selectedRowIndex - 1];

        tbody.insertBefore(currentRow, previousRow);

        // Actualizar índice seleccionado
        selectedRowIndex--;

        // Re-aplicar selección visual
        const newSelectedRow = rows[selectedRowIndex];
        selectRow(newSelectedRow, selectedRowIndex);

        showToast('Fila movida<br>La fila se ha movido hacia arriba', 'success');
    }
}

function moveRowDown() {
    const tbody = document.querySelector('#mainTable tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    if (selectedRowIndex < rows.length - 1) {
        // Intercambiar filas
        const currentRow = rows[selectedRowIndex];
        const nextRow = rows[selectedRowIndex + 1];

        tbody.insertBefore(nextRow, currentRow);

        // Actualizar índice seleccionado
        selectedRowIndex++;

        // Re-aplicar selección visual
        const newSelectedRow = rows[selectedRowIndex];
        selectRow(newSelectedRow, selectedRowIndex);

        showToast('Fila movida<br>La fila se ha movido hacia abajo', 'success');
    }
}

</script>

@include('components.toast-notification')
@endsection


