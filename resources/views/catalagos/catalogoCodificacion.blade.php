@extends('layouts.app')

@section('page-title', 'Catálogo de Codificación')

@section('navbar-right')
<x-action-buttons route="codificacion" :showFilters="true" />
@endsection

@section('content')
@php
use Carbon\Carbon;

if (!function_exists('fmtDateDMY')) {
    function fmtDateDMY($v): string {
        try {
            if ($v instanceof Carbon) {
                return $v->format('d/m/Y');
            }
            if (is_string($v) && trim($v) !== '') {
                return Carbon::parse($v)->format('d/m/Y');
            }
        } catch (\Throwable $e) {
            // Si no se puede parsear, devolver vacío
        }
        return '';
    }
}
@endphp

<div class="container-fluid px-4 py-6 -mt-6">

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto h-[600px]">
			<table id="mainTable" class=" border-collapse">
                <thead>
					<tr class="border border-gray-300 px-2 py-2 text-center font-light text-white text-sm bg-blue-500">
						<th>Clave mod.</th>
						<th>NoProduccion</th>
						<th>Fecha Orden</th>
						<th>Fecha Cumplimiento</th>
						<th>Departamento</th>
						<th>Telar Actual</th>
						<th>Prioridad</th>
						<th>Modelo</th>
						<th>Clave Modelo</th>
						<th>Clave AX</th>
						<th>Tamaño</th>
						<th>Tolerancia</th>
						<th>Codigo Dibujo</th>
						<th>Fecha Compromiso</th>
						<th>Id Flog.</th>
						<th>Nombre de Formato Logistico</th>
						<th>Clave</th>
						<th>Cantidad a Producir</th>
						<th>Peine</th>
						<th>Ancho</th>
						<th>Largo</th>
						<th>P_crudo</th>
						<th>Luchaje</th>
						<th>Tra</th>
						<th>Hilo</th>
						<th>Codigo Color Trama</th>
						<th>Nombre Color Trama</th>
						<th>OBS.</th>
						<th>Tipo plano</th>
						<th>Med plano</th>
						<th>Tipo de Rizo</th>
						<th>Altura de Rizo</th>
						<th>OBS</th>
						<th>Veloc. Mínima</th>
						<th>Rizo</th>
						<th>Hilo</th>

                        <!-- Sección CUENTA -->
						<th>Cuenta</th>
						<th>OBS.</th>
						<th>Pie</th>
						<th>Hilo</th>
						<th>Cuenta</th>
						<th>OBS</th>
						<th>C1</th>
						<th>OBS</th>
						<th>C2</th>
						<th>OBS</th>
						<th>C3</th>
						<th>OBS</th>
						<th>C4</th>
						<th>OBS</th>
						<th>Med. de Cenefa</th>
						<th>Med de inicio de rizo a cenefa</th>
						<th>Rasurada</th>
						<th>Tiras</th>
						<th>Repeticiones p/corte</th>

                        <!-- Sección media -->
						<th>No. De Marbetes</th>
						<th>Cambio de repaso</th>
						<th>Vendedor</th>
						<th>No. Orden</th>
						<th>Observaciones</th>
						<th>TRAMA (Ancho Peine)</th>
						<th>Log. de Lucha Total</th>
						<th>C1 trama de Fondo</th>
						<th>Hilo</th>

						<!-- PASADAS - C1 -->
						<th>OBS</th>
						<th>Pasadas</th>
						<th>C1</th>
						<th>Hilo</th>
						<th>OBS.</th>
						<th>Cod Color</th>
						<th>Nombre Color</th>

						<!-- PASADAS - C2 -->
						<th>Pasadas</th>
						<th>C2</th>
						<th>Hilo</th>
						<th>OBS.</th>
						<th>Cod Color</th>
						<th>Nombre Color</th>

						<!-- PASADAS - C3 -->
						<th>Pasadas</th>
						<th>C3</th>
						<th>Hilo</th>
						<th>OBS.</th>
						<th>Cod Color</th>
						<th>Nombre Color</th>

						<!-- PASADAS - C4 -->
						<th>Pasadas</th>
						<th>C4</th>
						<th>Hilo</th>
						<th>OBS.</th>
						<th>Cod Color</th>
						<th>Nombre Color</th>

						<!-- PASADAS - C5 -->
						<th>Pasadas</th>
						<th>CS</th>
						<th>Hilo</th>
						<th>OBS.</th>
						<th>Cod Color</th>
						<th>Nombre Color</th>
						<th>Pasadas</th>

						<!-- Final -->
						<th>Total</th>
						<th>Pasadas Dibujo</th>
						<th>Contraccion</th>
						<th>Tramas cm/Tejido</th>
						<th>Contrac Rizo</th>
						<th>Clasificación(KG)</th>
						<th>KG/Dia</th>
						<th>Densidad</th>
						<th>Pzas/Día/pasadas</th>
						<th>Pzas/Día/formula</th>
						<th>Dif</th>
						<th>Efic</th>
						<th>Rev</th>
						<th>Tiras</th>
						<th>Pasadas</th>
						<th>ColumCT</th>
						<th>ColumCU</th>
						<th>ColumCV</th>
                        <th>ComprobarModDup</th>
                    </tr>
                </thead>

                <tbody id="codificacion-body">
                    @forelse($codificaciones as $cod)
                        <tr class="hover:bg-gray-50 border px-2 py-2 text-center text-sm cursor-pointer"
                            onclick="selectRow(this, {{ $cod->Id }})"
                            data-id="{{ $cod->Id }}"
                            data-tamano-clave="{{ $cod->TamanoClave }}"
                            data-orden-tejido="{{ $cod->OrdenTejido }}"
                            data-nombre="{{ $cod->Nombre }}">
                            <td class="column-0">{{ $cod->TamanoClave }}</td>
                            <td class="column-1">{{ $cod->OrdenTejido }}</td>
                            <td class="column-2">{{ fmtDateDMY($cod->FechaTejido) }}</td>
                            <td class="column-3">{{ fmtDateDMY($cod->FechaCumplimiento) }}</td>
                            <td class="column-4">{{ $cod->SalonTejidoId }}</td>
                            <td class="column-5">{{ $cod->NoTelarId }}</td>
                            <td class="column-6">{{ $cod->Prioridad }}</td>
                            <td class="column-7">{{ $cod->Nombre }}</td>
                            <td class="column-8">{{ $cod->ClaveModelo }}</td>
                            <td class="column-9">{{ $cod->ItemId }}</td>
                            <td class="column-10">{{ $cod->InventSizeId }}</td>
                            <td class="column-11">{{ $cod->Tolerancia }}</td>
                            <td class="column-12">{{ $cod->CodigoDibujo }}</td>
                            <td class="column-13">{{ fmtDateDMY($cod->FechaCompromiso) }}</td>
                            <td class="column-14">{{ $cod->FlogsId }}</td>
                            <td class="column-15">{{ $cod->NombreProyecto }}</td>
                            <td class="column-16">{{ $cod->Clave }}</td>
                            <td class="column-17">{{ $cod->Pedido == 0 ? '' : $cod->Pedido }}</td>
                            <td class="column-18">{{ $cod->Peine == 0 ? '' : $cod->Peine }}</td>
                            <td class="column-19">{{ $cod->AnchoToalla == 0 ? '' : $cod->AnchoToalla }}</td>
                            <td class="column-20">{{ $cod->LargoToalla == 0 ? '' : $cod->LargoToalla }}</td>
                            <td class="column-21">{{ $cod->PesoCrudo == 0 ? '' : $cod->PesoCrudo }}</td>
                            <td class="column-22">{{ $cod->Luchaje == 0 ? '' : $cod->Luchaje }}</td>
                            <td class="column-23">{{ $cod->CalibreTrama == 0 ? '' : $cod->CalibreTrama }}</td>
                            <td class="column-24">{{ $cod->CalibreTrama2 == 0 ? '' : $cod->CalibreTrama2 }}</td>
                            <td class="column-25">{{ $cod->CodColorTrama }}</td>
                            <td class="column-26">{{ $cod->ColorTrama }}</td>
                            <td class="column-27">{{ $cod->FibraId }}</td>
                            <td class="column-28">{{ $cod->DobladilloId }}</td>
                            <td class="column-29">{{ $cod->MedidaPlano == 0 ? '' : $cod->MedidaPlano }}</td>
                            <td class="column-30">{{ $cod->TipoRizo }}</td>
                            <td class="column-31">{{ $cod->AlturaRizo == 0 ? '' : $cod->AlturaRizo }}</td>
                            <td class="column-32">{{ $cod->Obs }}</td>
                            <td class="column-33">{{ $cod->VelocidadSTD == 0 ? '' : $cod->VelocidadSTD }}</td>
                            <td class="column-34">{{ $cod->CalibreRizo }}</td>
                            <td class="column-35">{{ $cod->CalibreRizo2 }}</td>
                            <td class="column-36">{{ $cod->CuentaRizo == 0 ? '' : $cod->CuentaRizo }}</td>
                            <td class="column-37">{{ $cod->FibraRizo }}</td>
                            <td class="column-38">{{ $cod->CalibrePie }}</td>
                            <td class="column-39">{{ $cod->CalibrePie2 }}</td>
                            <td class="column-40">{{ $cod->CuentaPie == 0 ? '' : $cod->CuentaPie }}</td>
                            <td class="column-41">{{ $cod->FibraPie }}</td>
                            <td class="column-42">{{ $cod->Comb1 }}</td>
                            <td class="column-43">{{ $cod->Obs1 }}</td>
                            <td class="column-44">{{ $cod->Comb2 }}</td>
                            <td class="column-45">{{ $cod->Obs2 }}</td>
                            <td class="column-46">{{ $cod->Comb3 }}</td>
                            <td class="column-47">{{ $cod->Obs3 }}</td>
                            <td class="column-48">{{ $cod->Comb4 }}</td>
                            <td class="column-49">{{ $cod->Obs4 }}</td>
                            <td class="column-50">{{ $cod->MedidaCenefa }}</td>
                            <td class="column-51">{{ $cod->MedIniRizoCenefa }}</td>
                            <td class="column-52">{{ $cod->Rasurado }}</td>
                            <td class="column-53">{{ $cod->NoTiras == 0 ? '' : $cod->NoTiras }}</td>
                            <td class="column-54">{{ $cod->Repeticiones == 0 ? '' : $cod->Repeticiones }}</td>
                            <td class="column-55">{{ $cod->TotalMarbetes == 0 ? '' : $cod->TotalMarbetes }}</td>
                            <td class="column-56">{{ $cod->CambioRepaso }}</td>
                            <td class="column-57">{{ $cod->Vendedor }}</td>
                            <td class="column-58">{{ $cod->CatCalidad }}</td>
                            <td class="column-59">{{ $cod->Obs5 }}</td>
                            <td class="column-60">{{ $cod->AnchoPeineTrama == 0 ? '' : $cod->AnchoPeineTrama }}</td>
                            <td class="column-61">{{ $cod->LogLuchaTotal == 0 ? '' : $cod->LogLuchaTotal }}</td>
                            <td class="column-62">{{ $cod->CalTramaFondoC1 }}</td>
                            <td class="column-63">{{ $cod->CalTramaFondoC12 }}</td>
                            <td class="column-64">{{ $cod->FibraTramaFondoC1 }}</td>
                            <td class="column-65">{{ $cod->PasadasTramaFondoC1 == 0 ? '' : $cod->PasadasTramaFondoC1 }}</td>
                            <td class="column-66">{{ $cod->CalibreComb1 }}</td>
                            <td class="column-67">{{ $cod->CalibreComb12 }}</td>
                            <td class="column-68">{{ $cod->FibraComb1 }}</td>
                            <td class="column-69">{{ $cod->CodColorC1 }}</td>
                            <td class="column-70">{{ $cod->NomColorC1 }}</td>
                            <td class="column-71">{{ $cod->PasadasComb1 == 0 ? '' : $cod->PasadasComb1 }}</td>
                            <td class="column-72">{{ $cod->CalibreComb2 }}</td>
                            <td class="column-73">{{ $cod->CalibreComb22 }}</td>
                            <td class="column-74">{{ $cod->FibraComb2 }}</td>
                            <td class="column-75">{{ $cod->CodColorC2 }}</td>
                            <td class="column-76">{{ $cod->NomColorC2 }}</td>
                            <td class="column-77">{{ $cod->PasadasComb2 == 0 ? '' : $cod->PasadasComb2 }}</td>
                            <td class="column-78">{{ $cod->CalibreComb3 }}</td>
                            <td class="column-79">{{ $cod->CalibreComb32 }}</td>
                            <td class="column-80">{{ $cod->FibraComb3 }}</td>
                            <td class="column-81">{{ $cod->CodColorC3 }}</td>
                            <td class="column-82">{{ $cod->NomColorC3 }}</td>
                            <td class="column-83">{{ $cod->PasadasComb3 == 0 ? '' : $cod->PasadasComb3 }}</td>
                            <td class="column-84">{{ $cod->CalibreComb4 }}</td>
                            <td class="column-85">{{ $cod->CalibreComb42 }}</td>
                            <td class="column-86">{{ $cod->FibraComb4 }}</td>
                            <td class="column-87">{{ $cod->CodColorC4 }}</td>
                            <td class="column-88">{{ $cod->NomColorC4 }}</td>
                            <td class="column-89">{{ $cod->PasadasComb4 == 0 ? '' : $cod->PasadasComb4 }}</td>
                            <td class="column-90">{{ $cod->CalibreComb5 }}</td>
                            <td class="column-91">{{ $cod->CalibreComb52 }}</td>
                            <td class="column-92">{{ $cod->FibraComb5 }}</td>
                            <td class="column-93">{{ $cod->CodColorC5 }}</td>
                            <td class="column-94">{{ $cod->NomColorC5 }}</td>
                            <td class="column-95">{{ $cod->PasadasComb5 == 0 ? '' : $cod->PasadasComb5 }}</td>
                            <td class="column-96">{{ $cod->Total == 0 ? '' : $cod->Total }}</td>
                            <td class="column-97">{{ $cod->PasadasDibujo }}</td>
                            <td class="column-98">{{ $cod->Contraccion }}</td>
                            <td class="column-99">{{ $cod->TramasCMTejido }}</td>
                            <td class="column-100">{{ $cod->ContracRizo }}</td>
                            <td class="column-101">{{ $cod->ClasificacionKG }}</td>
                            <td class="column-102">{{ $cod->KGDia }}</td>
                            <td class="column-103">{{ $cod->Densidad }}</td>
                            <td class="column-104">{{ $cod->PzasDiaPasadas }}</td>
                            <td class="column-105">{{ $cod->PzasDiaFormula }}</td>
                            <td class="column-106">{{ $cod->DIF }}</td>
                            <td class="column-107">{{ $cod->EFIC }}</td>
                            <td class="column-108">{{ $cod->Rev }}</td>
                            <td class="column-109">{{ $cod->TIRAS == 0 ? '' : $cod->TIRAS }}</td>
                            <td class="column-110">{{ $cod->PASADAS == 0 ? '' : $cod->PASADAS }}</td>
                            <td class="column-111">{{ $cod->ColumCT }}</td>
                            <td class="column-112">{{ $cod->ColumCU }}</td>
                            <td class="column-113">{{ $cod->ColumCV }}</td>
                            <td class="column-114">{{ $cod->ComprobarModDup }}</td>
                            <!-- Más columnas según necesidad -->
                        </tr>
                    @empty
                        <tr>
                            <td colspan="100" class="text-center py-8 text-gray-500">
                                @if(isset($error))
                                    <div class="text-red-600 font-semibold mb-2">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Error al cargar los datos
                                    </div>
                                    <div class="text-sm text-gray-600">{{ $error }}</div>
                                @elseif(isset($mensaje))
                                    <div class="text-blue-600 font-semibold mb-2">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        {{ $mensaje }}
                                    </div>
                                @else
                                    No hay registros de codificación disponibles
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>


<style>
	/* Mantiene encabezados pegados arriba y columnas fijadas a la izquierda */
	#mainTable thead th { position: sticky; top: 0; z-index: 30; background-color: #3b82f6; }
	.pinned-column { position: sticky !important; background: #3b82f6 !important; color: #fff !important; }
	.pinned-column.is-header { background: #3b82f6 !important; color: #fff !important; }
	/* Botón minimal en headers */
	.th-action {
		@apply p-1.5 rounded-md transition-shadow shadow-sm hover:shadow-md;
	}
	/* Oculta texto que se desborde en headers muy largos */
	th .th-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>

<script>
/** =========================
 *  Estado global
 *  ========================= */
let hiddenColumns = new Set();
let pinnedColumns = new Set(); // Usar Set para evitar duplicados
let currentSort = { index: null, dir: null }; // dir: 'asc' | 'desc'

/** =========================
 *  Utilidades
 *  ========================= */
const $ = (sel, ctx=document) => ctx.querySelector(sel);
const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

function parseDateDDMMYYYY(str) {
	if (!str) return null;
	const m = str.trim().match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
	if (!m) return null;
	let [_, d, mo, y] = m;
	if (y.length === 2) y = +y >= 70 ? '19'+y : '20'+y;
	const dt = new Date(+y, +mo-1, +d);
	return isNaN(dt.getTime()) ? null : dt;
}
function isNumericLike(v) {
	return /^-?\d+(\.\d+)?$/.test(String(v).replace(/,/g,'').trim());
}
function cmp(a, b) { return a < b ? -1 : a > b ? 1 : 0; }

function getCellValue(row, colIndex) {
	const cell = row.children[colIndex];
	if (!cell) return '';
	return cell.textContent.trim();
}

/** =========================
 *  Inyección de controles en TODOS los TH
 *  ========================= */
function enhanceHeaders() {
	const headerRow = $('#mainTable thead tr');
	const ths = $$('th', headerRow);
	ths.forEach((th, i) => {
		// Asignar data-index y clase a todas las columnas
		th.classList.add(`column-${i}`, 'text-left', 'px-3', 'py-2', 'whitespace-nowrap');
		th.dataset.index = i;

		const label = th.textContent.trim();
		th.textContent = '';

		// Controles: Fijar, Ocultar, Orden ASC, Orden DESC
		th.innerHTML = `
			<div class="flex items-center justify-between gap-2">
				<span class="th-label flex-1">${label}</span>
				<div class="flex items-center gap-1">
					<!-- Orden Toggle (ASC/DESC) -->
					<button type="button" class="sort-btn th-action bg-blue-600 hover:bg-blue-700 text-white rounded-md" title="Ordenar ascendente" data-sort="asc" data-current="asc">
						<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 14l5-5 5 5M7 20h10" />
						</svg>
					</button>
					<!-- Fijar -->
					<button type="button" class="pin-btn th-action bg-yellow-500 hover:bg-yellow-600 text-white rounded-md" title="Fijar columna" data-pin="toggle">
						<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
						</svg>
					</button>
					<!-- Ocultar -->
					<button type="button" class="hide-btn th-action bg-red-500 hover:bg-red-600 text-white rounded-md" title="Ocultar columna" data-hide="true">
						<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
						</svg>
					</button>
				</div>
			</div>
		`;

		// Eventos
		const sortBtn = th.querySelector('.sort-btn');
		const pinBtn = th.querySelector('[data-pin="toggle"]');
		const hideBtn = th.querySelector('[data-hide]');

		sortBtn.addEventListener('click', e => {
			e.stopPropagation();
			const current = sortBtn.dataset.current;
			const newSort = current === 'asc' ? 'desc' : 'asc';
			sortBtn.dataset.current = newSort;
			sortBtn.dataset.sort = newSort;
			sortBtn.title = newSort === 'asc' ? 'Ordenar ascendente' : 'Ordenar descendente';

			// Cambiar icono
			const svg = sortBtn.querySelector('svg path');
			if (newSort === 'asc') {
				svg.setAttribute('d', 'M7 14l5-5 5 5M7 20h10');
			} else {
				svg.setAttribute('d', 'M7 10l5 5 5-5M7 4h10');
			}

			sortColumn(i, newSort);
		});
		pinBtn.addEventListener('click', e => { e.stopPropagation(); togglePinColumn(i); });
		hideBtn.addEventListener('click', e => { e.stopPropagation(); hideColumn(i); });
	});
}

/** =========================
 *  Ocultar / Mostrar / Reset
 *  ========================= */
function hideColumn(index) {
	hiddenColumns.add(index);
	// Ocultar TH y TDs
	$$(`#mainTable thead th.column-${index}, #mainTable tbody td.column-${index}`).forEach(el => {
		el.style.display = 'none';
	});
	// Si estaba fijada, desfijar
	if (pinnedColumns.has(index)) {
		pinnedColumns.delete(index);
		updatePinnedPositions();
	}
	showToast('Columna ocultada correctamente', 'info');
}

function resetColumns() {
	// Mostrar todas
	hiddenColumns.forEach(index => {
		$$(`#mainTable thead th.column-${index}, #mainTable tbody td.column-${index}`).forEach(el => {
			el.style.display = '';
		});
	});
	hiddenColumns.clear();

	// Desfijar todas
	Array.from(pinnedColumns).forEach(idx => togglePinColumn(idx, true /*forceUnpin*/));

	showToast('Restablecido correctamente ', 'success');
}

/** =========================
 *  Fijar columnas
 *  ========================= */
function togglePinColumn(index, forceUnpin = false) {
	const alreadyPinned = pinnedColumns.has(index);
	const header = $(`#mainTable thead th.column-${index}`);
	const cells = $$(`#mainTable tbody td.column-${index}`);

	if (alreadyPinned || forceUnpin) {
		[header, ...cells].forEach(el => {
			if (!el) return;
			el.classList.remove('pinned-column');
			el.classList.remove('is-header');
			el.style.left = '';
			el.style.zIndex = '';
		});
		pinnedColumns.delete(index);
		showToast('Columna desfijada correctamente', 'info');
	} else {
		if (hiddenColumns.has(index)) {
			hiddenColumns.delete(index);
			[header, ...cells].forEach(el => { if (el) el.style.display = ''; });
		}
		pinnedColumns.add(index);
		showToast('Columna fijada correctamente', 'success');
	}
	updatePinnedPositions();
}

function updatePinnedPositions() {
	let left = 0;
	let order = 0;
	Array.from(pinnedColumns).sort((a, b) => a - b).forEach(colIndex => {
		const header = $(`#mainTable thead th.column-${colIndex}`);
		const cells = $$(`#mainTable tbody td.column-${colIndex}`);
		if (!header) return;

		const width = header.offsetWidth;
		[header, ...cells].forEach(el => {
			if (!el) return;
			el.classList.add('pinned-column');
			if (el.tagName === 'TH') el.classList.add('is-header');
			el.style.left = `${left}px`;
			el.style.zIndex = String(50 + order);
		});
		left += width;
		order++;
	});
}

/** =========================
 *  Ordenamiento
 *  ========================= */
function sortColumn(index, dir) {
	const tbody = $('#mainTable tbody');
	const rows = $$('#mainTable tbody tr');

	// Detectar tipo de dato predominante
	let numericCount = 0, dateCount = 0, total = 0;
	rows.forEach(r => {
		const v = getCellValue(r, index);
		if (!v) return;
		total++;
		if (parseDateDDMMYYYY(v)) dateCount++;
		else if (isNumericLike(v)) numericCount++;
	});

	let type = 'text';
	if (dateCount / Math.max(total,1) > 0.5) type = 'date';
	else if (numericCount / Math.max(total,1) > 0.5) type = 'number';

	const parseVal = (v) => {
		if (type === 'date') {
			const dt = parseDateDDMMYYYY(v);
			return dt ? dt.getTime() : -Infinity;
		}
		if (type === 'number') {
			return parseFloat(String(v).replace(/,/g,'').trim()) || 0;
		}
		return v.toString().toLowerCase();
	};

	const sorted = rows.slice().sort((a,b) => {
		const va = parseVal(getCellValue(a, index));
		const vb = parseVal(getCellValue(b, index));
		return dir === 'asc' ? cmp(va, vb) : cmp(vb, va);
	});

	tbody.innerHTML = '';
	sorted.forEach(r => tbody.appendChild(r));

	currentSort = { index, dir };
}

window.addEventListener('resize', () => {
	updatePinnedPositions();
});

/** =========================
 *  Toast minimal
 *  ========================= */
function showToast(message, type='info') {
	let toast = document.getElementById('toast-notification');
	if (!toast) {
		toast = document.createElement('div');
		toast.id = 'toast-notification';
		toast.className = 'fixed top-4 right-4 z-50 max-w-sm w-full';
		document.body.appendChild(toast);
	}
	const colors = { success:'bg-green-600', error:'bg-red-600', warning:'bg-yellow-600', info:'bg-blue-600' };
	toast.innerHTML = `
		<div class="${colors[type]||colors.info} text-white px-4 py-3 rounded-md shadow-lg transition-all" id="toast-content">
			<div class="flex items-center justify-between gap-4">
				<div class="text-sm">${message}</div>
				<button onclick="document.getElementById('toast-notification').remove()" class="opacity-80 hover:opacity-100">
					<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
					</svg>
				</button>
			</div>
		</div>`;
	setTimeout(()=>{ const t = document.getElementById('toast-notification'); if(t) t.remove(); }, 3500);
}

/** =========================
 *  Estado global para selección
 *  ========================= */
let selectedRow = null;
let selectedId = null;

/** =========================
 *  Funciones de botones (compatibles con action-buttons component)
 *  ========================= */
function agregarCodificacion() {
	window.location.href = '/planeacion/catalogos/codificacion-modelos/create';
}

function editarCodificacion() {
	if (!selectedId) {
		showToast('Por favor selecciona un registro para editar', 'warning');
		return;
	}
	window.location.href = `/planeacion/catalogos/codificacion-modelos/${selectedId}/edit`;
}

function eliminarCodificacion() {
	if (!selectedId) {
		showToast('Por favor selecciona un registro para eliminar', 'warning');
		return;
	}

	Swal.fire({
		title: '¿Estás seguro?',
		text: 'Esta acción no se puede deshacer',
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#d33',
		cancelButtonColor: '#3085d6',
		confirmButtonText: 'Sí, eliminar',
		cancelButtonText: 'Cancelar'
	}).then((result) => {
		if (result.isConfirmed) {
			fetch(`/planeacion/catalogos/codificacion-modelos/${selectedId}`, {
				method: 'DELETE',
				headers: {
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
				}
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					showToast('Registro eliminado exitosamente', 'success');
					setTimeout(() => location.reload(), 1000);
				} else {
					showToast('Error al eliminar: ' + data.message, 'error');
				}
			})
			.catch(error => {
				showToast('Error al eliminar: ' + error.message, 'error');
			});
		}
	});
}

function subirExcelCodificacion() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.xlsx,.xls';
    input.style.display = 'none';

    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const allowedTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel'
        ];

        if (!allowedTypes.includes(file.type)) {
            showToast('Por favor selecciona un archivo Excel válido (.xlsx o .xls)', 'error');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            showToast('El archivo es demasiado grande. Máximo 10MB', 'error');
            return;
        }

        Swal.fire({
            title: '¿Procesar archivo Excel?',
            text: `Archivo: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, procesar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                procesarExcel(file);
            }
        });
    });

    document.body.appendChild(input);
    input.click();
    document.body.removeChild(input);
}

function procesarExcel(file) {
    const formData = new FormData();
    formData.append('archivo_excel', file);

    Swal.fire({
        title: 'Procesando archivo...',
        text: 'Por favor espera mientras se procesa el Excel',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('/planeacion/catalogos/codificacion-modelos/excel', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();

        if (data.success) {
            const { registros_procesados, registros_creados, registros_actualizados, errores } = data.data;

            let message = `Archivo procesado exitosamente!\n\n`;
            message += `• Registros procesados: ${registros_procesados}\n`;
            message += `• Registros creados: ${registros_creados}\n`;
            message += `• Registros actualizados: ${registros_actualizados}`;

            if (errores && errores.length > 0) {
                message += `\n• Errores: ${errores.length}`;
            }

            Swal.fire({
                title: '¡Éxito!',
                text: message,
                icon: 'success',
                confirmButtonText: 'Aceptar'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message || 'Error al procesar el archivo',
                icon: 'error',
                confirmButtonText: 'Aceptar'
            });
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire({
            title: 'Error',
            text: 'Error de conexión: ' + error.message,
            icon: 'error',
            confirmButtonText: 'Aceptar'
        });
    });
}

// Variables globales para filtros
let filtrosActuales = {
    tamanoClave: '',
    ordenTejido: '',
    nombre: '',
    salonTejidoId: '',
    noTelarId: '',
    prioridad: '',
    claveModelo: '',
    flogsId: '',
    nombreProyecto: ''
};

// Variables globales para filtros dinámicos
let filtrosDinamicos = [];

function filtrarCodificacion() {
    // Definir todas las columnas disponibles
    const columnasDisponibles = [
        { index: 0, nombre: 'Clave mod.' },
        { index: 1, nombre: 'NoProduccion' },
        { index: 2, nombre: 'Fecha Orden' },
        { index: 3, nombre: 'Fecha Cumplimiento' },
        { index: 4, nombre: 'Departamento' },
        { index: 5, nombre: 'Telar Actual' },
        { index: 6, nombre: 'Prioridad' },
        { index: 7, nombre: 'Modelo' },
        { index: 8, nombre: 'Clave Modelo' },
        { index: 9, nombre: 'Clave AX' },
        { index: 10, nombre: 'Tamaño' },
        { index: 11, nombre: 'Tolerancia' },
        { index: 12, nombre: 'Codigo Dibujo' },
        { index: 13, nombre: 'Fecha Compromiso' },
        { index: 14, nombre: 'Id Flog' },
        { index: 15, nombre: 'Nombre de Formato Logístico' },
        { index: 16, nombre: 'Clave' },
        { index: 17, nombre: 'Cantidad a Producir' },
        { index: 18, nombre: 'Peine' },
        { index: 19, nombre: 'Ancho' },
        { index: 20, nombre: 'Largo' },
        { index: 21, nombre: 'P_crudo' },
        { index: 22, nombre: 'Luchaje' },
        { index: 23, nombre: 'Tra' },
        { index: 24, nombre: 'Hilo' },
        { index: 25, nombre: 'Codigo Color Trama' },
        { index: 26, nombre: 'Nombre Color Trama' },
        { index: 27, nombre: 'OBS.' },
        { index: 28, nombre: 'Tipo plano' },
        { index: 29, nombre: 'Med plano' },
        { index: 30, nombre: 'Tipo de Rizo' },
        { index: 31, nombre: 'Altura de Rizo' },
        { index: 32, nombre: 'OBS' },
        { index: 33, nombre: 'Veloc. Mínima' },
        { index: 34, nombre: 'Rizo' },
        { index: 35, nombre: 'Hilo' },
        { index: 36, nombre: 'Cuenta' },
        { index: 37, nombre: 'OBS.' },
        { index: 38, nombre: 'Pie' },
        { index: 39, nombre: 'Hilo' },
        { index: 40, nombre: 'Cuenta' },
        { index: 41, nombre: 'OBS' },
        { index: 42, nombre: 'C1' },
        { index: 43, nombre: 'OBS' },
        { index: 44, nombre: 'C2' },
        { index: 45, nombre: 'OBS' },
        { index: 46, nombre: 'C3' },
        { index: 47, nombre: 'OBS' },
        { index: 48, nombre: 'C4' },
        { index: 49, nombre: 'OBS' },
        { index: 50, nombre: 'Med. de Cenefa' },
        { index: 51, nombre: 'Med de inicio de rizo a cenefa' },
        { index: 52, nombre: 'Rasurada' },
        { index: 53, nombre: 'Tiras' },
        { index: 54, nombre: 'Repeticiones p/corte' },
        { index: 55, nombre: 'No. De Marbetes' },
        { index: 56, nombre: 'Cambio de repaso' },
        { index: 57, nombre: 'Vendedor' },
        { index: 58, nombre: 'No. Orden' },
        { index: 59, nombre: 'Observaciones' },
        { index: 60, nombre: 'TRAMA (Ancho Peine)' },
        { index: 61, nombre: 'Log. de Lucha Total' },
        { index: 62, nombre: 'C1 trama de Fondo' },
        { index: 63, nombre: 'Hilo' },
        { index: 64, nombre: 'OBS' },
        { index: 65, nombre: 'Pasadas' },
        { index: 66, nombre: 'C1' },
        { index: 67, nombre: 'Hilo' },
        { index: 68, nombre: 'OBS.' },
        { index: 69, nombre: 'Cod Color' },
        { index: 70, nombre: 'Nombre Color' },
        { index: 71, nombre: 'Pasadas' },
        { index: 72, nombre: 'C2' },
        { index: 73, nombre: 'Hilo' },
        { index: 74, nombre: 'OBS.' },
        { index: 75, nombre: 'Cod Color' },
        { index: 76, nombre: 'Nombre Color' },
        { index: 77, nombre: 'Pasadas' },
        { index: 78, nombre: 'C3' },
        { index: 79, nombre: 'Hilo' },
        { index: 80, nombre: 'OBS.' },
        { index: 81, nombre: 'Cod Color' },
        { index: 82, nombre: 'Nombre Color' },
        { index: 83, nombre: 'Pasadas' },
        { index: 84, nombre: 'C4' },
        { index: 85, nombre: 'Hilo' },
        { index: 86, nombre: 'OBS.' },
        { index: 87, nombre: 'Cod Color' },
        { index: 88, nombre: 'Nombre Color' },
        { index: 89, nombre: 'Pasadas' },
        { index: 90, nombre: 'CS' },
        { index: 91, nombre: 'Hilo' },
        { index: 92, nombre: 'OBS.' },
        { index: 93, nombre: 'Cod Color' },
        { index: 94, nombre: 'Nombre Color' },
        { index: 95, nombre: 'Pasadas' },
        { index: 96, nombre: 'Total' },
        { index: 97, nombre: 'Pasadas Dibujo' },
        { index: 98, nombre: 'Contraccion' },
        { index: 99, nombre: 'Tramas cm/Tejido' },
        { index: 100, nombre: 'Contrac Rizo' },
        { index: 101, nombre: 'Clasificación(KG)' },
        { index: 102, nombre: 'KG/Dia' },
        { index: 103, nombre: 'Densidad' },
        { index: 104, nombre: 'Pzas/Día/pasadas' },
        { index: 105, nombre: 'Pzas/Día/formula' },
        { index: 106, nombre: 'Dif' },
        { index: 107, nombre: 'Efic' },
        { index: 108, nombre: 'Rev' },
        { index: 109, nombre: 'Tiras' },
        { index: 110, nombre: 'Pasadas' },
        { index: 111, nombre: 'ColumCT' },
        { index: 112, nombre: 'ColumCU' },
        { index: 113, nombre: 'ColumCV' },
        { index: 114, nombre: 'ComprobarModDup' }
    ];

    // Inicializar filtros si están vacíos
    if (filtrosDinamicos.length === 0) {
        filtrosDinamicos = [{ columna: '', valor: '' }];
    }

    const html = `
        <div id="filtros-container" class="space-y-3">
            ${filtrosDinamicos.map((filtro, index) => `
                <div class="filtro-item flex items-center gap-2 p-3 border rounded-lg bg-gray-50">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Columna</label>
                        <select class="w-full px-2 py-1 border rounded text-sm" data-filtro-index="${index}" data-field="columna">
                            <option value="">Selecciona una columna...</option>
                            ${columnasDisponibles.map(col => `
                                <option value="${col.index}" ${filtro.columna == col.index ? 'selected' : ''}>${col.nombre}</option>
                            `).join('')}
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Valor a buscar</label>
                        <input type="text" class="w-full px-2 py-1 border rounded text-sm"
                               placeholder="Ingresa el valor a buscar..."
                               data-filtro-index="${index}"
                               data-field="valor"
                               value="${filtro.valor}">
                    </div>
                    <div class="flex flex-col gap-1">
                        <button type="button" class="btn-remove-filtro px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600"
                                data-index="${index}" ${filtrosDinamicos.length === 1 ? 'style="display:none"' : ''}>
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('')}
        </div>
        <div class="mt-3 flex justify-between">
            <button type="button" id="btn-agregar-filtro" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                <i class="fas fa-plus mr-1"></i> Agregar Otro Filtro
            </button>
        </div>
        <div class="mt-3 text-xs text-gray-500 bg-blue-50 p-2 rounded">
            <i class="fas fa-info-circle mr-1"></i> Los filtros son case-insensitive y se aplican con lógica AND.
        </div>
    `;

    Swal.fire({
        title: 'Filtrar por Columna',
        html: html,
        width: '700px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-filter mr-2"></i>Agregar Filtro',
        cancelButtonText: '<i class="fas fa-times mr-2"></i>Cerrar',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        didOpen: () => {
            // Event listeners para botones dinámicos
            document.getElementById('btn-agregar-filtro').addEventListener('click', agregarFiltro);

            // Event listeners para botones de eliminar
            document.querySelectorAll('.btn-remove-filtro').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const index = parseInt(e.target.dataset.index);
                    eliminarFiltro(index);
                });
            });
        },
        preConfirm: () => {
            const filtros = [];
            document.querySelectorAll('.filtro-item').forEach((item, index) => {
                const columna = item.querySelector('[data-field="columna"]').value;
                const valor = item.querySelector('[data-field="valor"]').value.trim();

                if (columna && valor) {
                    filtros.push({ columna: parseInt(columna), valor: valor });
                }
            });

            if (filtros.length === 0) {
                Swal.showValidationMessage('Debes agregar al menos un filtro válido');
                return false;
            }

            return filtros;
        }
    }).then(res => {
        if (res.isConfirmed && res.value) {
            aplicarFiltrosDinamicos(res.value);
        }
    });
}

function agregarFiltro() {
    const container = document.getElementById('filtros-container');
    const index = filtrosDinamicos.length;

    filtrosDinamicos.push({ columna: '', valor: '' });

    const nuevoFiltro = document.createElement('div');
    nuevoFiltro.className = 'filtro-item flex items-center gap-2 p-3 border rounded-lg bg-gray-50';
    nuevoFiltro.innerHTML = `
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-700 mb-1">Columna</label>
            <select class="w-full px-2 py-1 border rounded text-sm" data-filtro-index="${index}" data-field="columna">
                <option value="">Selecciona una columna...</option>
                ${getColumnasDisponibles().map(col => `
                    <option value="${col.index}">${col.nombre}</option>
                `).join('')}
            </select>
        </div>
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-700 mb-1">Valor a buscar</label>
            <input type="text" class="w-full px-2 py-1 border rounded text-sm"
                   placeholder="Ingresa el valor a buscar..."
                   data-filtro-index="${index}"
                   data-field="valor">
        </div>
        <div class="flex flex-col gap-1">
            <button type="button" class="btn-remove-filtro px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600"
                    data-index="${index}">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;

    container.appendChild(nuevoFiltro);

    // Actualizar visibilidad de botones de eliminar
    document.querySelectorAll('.btn-remove-filtro').forEach(btn => {
        btn.style.display = filtrosDinamicos.length > 1 ? 'block' : 'none';
        btn.addEventListener('click', (e) => {
            const index = parseInt(e.target.dataset.index);
            eliminarFiltro(index);
        });
    });
}

function eliminarFiltro(index) {
    filtrosDinamicos.splice(index, 1);

    // Reconstruir el HTML
    const container = document.getElementById('filtros-container');
    container.innerHTML = filtrosDinamicos.map((filtro, idx) => `
        <div class="filtro-item flex items-center gap-2 p-3 border rounded-lg bg-gray-50">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">Columna</label>
                <select class="w-full px-2 py-1 border rounded text-sm" data-filtro-index="${idx}" data-field="columna">
                    <option value="">Selecciona una columna...</option>
                    ${getColumnasDisponibles().map(col => `
                        <option value="${col.index}" ${filtro.columna == col.index ? 'selected' : ''}>${col.nombre}</option>
                    `).join('')}
                </select>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-700 mb-1">Valor a buscar</label>
                <input type="text" class="w-full px-2 py-1 border rounded text-sm"
                       placeholder="Ingresa el valor a buscar..."
                       data-filtro-index="${idx}"
                       data-field="valor"
                       value="${filtro.valor}">
            </div>
            <div class="flex flex-col gap-1">
                <button type="button" class="btn-remove-filtro px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600"
                        data-index="${idx}" ${filtrosDinamicos.length === 1 ? 'style="display:none"' : ''}>
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');

    // Reagregar event listeners
    document.querySelectorAll('.btn-remove-filtro').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const index = parseInt(e.target.dataset.index);
            eliminarFiltro(index);
        });
    });
}

function getColumnasDisponibles() {
    return [
        { index: 0, nombre: 'Clave mod.' },
        { index: 1, nombre: 'NoProduccion' },
        { index: 2, nombre: 'Fecha Orden' },
        { index: 3, nombre: 'Fecha Cumplimiento' },
        { index: 4, nombre: 'Departamento' },
        { index: 5, nombre: 'Telar Actual' },
        { index: 6, nombre: 'Prioridad' },
        { index: 7, nombre: 'Modelo' },
        { index: 8, nombre: 'Clave Modelo' },
        { index: 9, nombre: 'Clave AX' },
        { index: 10, nombre: 'Tamaño' },
        { index: 11, nombre: 'Tolerancia' },
        { index: 12, nombre: 'Codigo Dibujo' },
        { index: 13, nombre: 'Fecha Compromiso' },
        { index: 14, nombre: 'Id Flog' },
        { index: 15, nombre: 'Nombre de Formato Logístico' },
        { index: 16, nombre: 'Clave' },
        { index: 17, nombre: 'Cantidad a Producir' },
        { index: 18, nombre: 'Peine' },
        { index: 19, nombre: 'Ancho' },
        { index: 20, nombre: 'Largo' },
        { index: 21, nombre: 'P_crudo' },
        { index: 22, nombre: 'Luchaje' },
        { index: 23, nombre: 'Tra' },
        { index: 24, nombre: 'Hilo' },
        { index: 25, nombre: 'Codigo Color Trama' },
        { index: 26, nombre: 'Nombre Color Trama' },
        { index: 27, nombre: 'OBS.' },
        { index: 28, nombre: 'Tipo plano' },
        { index: 29, nombre: 'Med plano' },
        { index: 30, nombre: 'Tipo de Rizo' },
        { index: 31, nombre: 'Altura de Rizo' },
        { index: 32, nombre: 'OBS' },
        { index: 33, nombre: 'Veloc. Mínima' },
        { index: 34, nombre: 'Rizo' },
        { index: 35, nombre: 'Hilo' },
        { index: 36, nombre: 'Cuenta' },
        { index: 37, nombre: 'OBS.' },
        { index: 38, nombre: 'Pie' },
        { index: 39, nombre: 'Hilo' },
        { index: 40, nombre: 'Cuenta' },
        { index: 41, nombre: 'OBS' },
        { index: 42, nombre: 'C1' },
        { index: 43, nombre: 'OBS' },
        { index: 44, nombre: 'C2' },
        { index: 45, nombre: 'OBS' },
        { index: 46, nombre: 'C3' },
        { index: 47, nombre: 'OBS' },
        { index: 48, nombre: 'C4' },
        { index: 49, nombre: 'OBS' },
        { index: 50, nombre: 'Med. de Cenefa' },
        { index: 51, nombre: 'Med de inicio de rizo a cenefa' },
        { index: 52, nombre: 'Rasurada' },
        { index: 53, nombre: 'Tiras' },
        { index: 54, nombre: 'Repeticiones p/corte' },
        { index: 55, nombre: 'No. De Marbetes' },
        { index: 56, nombre: 'Cambio de repaso' },
        { index: 57, nombre: 'Vendedor' },
        { index: 58, nombre: 'No. Orden' },
        { index: 59, nombre: 'Observaciones' },
        { index: 60, nombre: 'TRAMA (Ancho Peine)' },
        { index: 61, nombre: 'Log. de Lucha Total' },
        { index: 62, nombre: 'C1 trama de Fondo' },
        { index: 63, nombre: 'Hilo' },
        { index: 64, nombre: 'OBS' },
        { index: 65, nombre: 'Pasadas' },
        { index: 66, nombre: 'C1' },
        { index: 67, nombre: 'Hilo' },
        { index: 68, nombre: 'OBS.' },
        { index: 69, nombre: 'Cod Color' },
        { index: 70, nombre: 'Nombre Color' },
        { index: 71, nombre: 'Pasadas' },
        { index: 72, nombre: 'C2' },
        { index: 73, nombre: 'Hilo' },
        { index: 74, nombre: 'OBS.' },
        { index: 75, nombre: 'Cod Color' },
        { index: 76, nombre: 'Nombre Color' },
        { index: 77, nombre: 'Pasadas' },
        { index: 78, nombre: 'C3' },
        { index: 79, nombre: 'Hilo' },
        { index: 80, nombre: 'OBS.' },
        { index: 81, nombre: 'Cod Color' },
        { index: 82, nombre: 'Nombre Color' },
        { index: 83, nombre: 'Pasadas' },
        { index: 84, nombre: 'C4' },
        { index: 85, nombre: 'Hilo' },
        { index: 86, nombre: 'OBS.' },
        { index: 87, nombre: 'Cod Color' },
        { index: 88, nombre: 'Nombre Color' },
        { index: 89, nombre: 'Pasadas' },
        { index: 90, nombre: 'CS' },
        { index: 91, nombre: 'Hilo' },
        { index: 92, nombre: 'OBS.' },
        { index: 93, nombre: 'Cod Color' },
        { index: 94, nombre: 'Nombre Color' },
        { index: 95, nombre: 'Pasadas' },
        { index: 96, nombre: 'Total' },
        { index: 97, nombre: 'Pasadas Dibujo' },
        { index: 98, nombre: 'Contraccion' },
        { index: 99, nombre: 'Tramas cm/Tejido' },
        { index: 100, nombre: 'Contrac Rizo' },
        { index: 101, nombre: 'Clasificación(KG)' },
        { index: 102, nombre: 'KG/Dia' },
        { index: 103, nombre: 'Densidad' },
        { index: 104, nombre: 'Pzas/Día/pasadas' },
        { index: 105, nombre: 'Pzas/Día/formula' },
        { index: 106, nombre: 'Dif' },
        { index: 107, nombre: 'Efic' },
        { index: 108, nombre: 'Rev' },
        { index: 109, nombre: 'Tiras' },
        { index: 110, nombre: 'Pasadas' },
        { index: 111, nombre: 'ColumCT' },
        { index: 112, nombre: 'ColumCU' },
        { index: 113, nombre: 'ColumCV' },
        { index: 114, nombre: 'ComprobarModDup' }
    ];
}

function aplicarFiltrosDinamicos(filtros) {
    const filas = document.querySelectorAll('#codificacion-body tr');
    let filasVisibles = 0;

    filas.forEach(fila => {
        const celdas = fila.querySelectorAll('td');
        let mostrarFila = true;

        // Aplicar todos los filtros (lógica AND)
        filtros.forEach(filtro => {
            const celda = celdas[filtro.columna];
            if (celda && filtro.valor) {
                const valor = celda.textContent.toLowerCase();
                if (!valor.includes(filtro.valor.toLowerCase())) {
                    mostrarFila = false;
                }
            }
        });

        if (mostrarFila) {
            fila.style.display = '';
            filasVisibles++;
        } else {
            fila.style.display = 'none';
        }
    });

    showToast(`Filtros aplicados: ${filtros.length} criterios. Mostrando ${filasVisibles} de ${filas.length} registros`, 'success');
}


function limpiarFiltrosCodificacion() {
    // Limpiar filtros de búsqueda
    filtrosActuales = {
        tamanoClave: '',
        ordenTejido: '',
        nombre: '',
        salonTejidoId: '',
        noTelarId: '',
        prioridad: '',
        claveModelo: '',
        flogsId: '',
        nombreProyecto: ''
    };

    // Limpiar filtros dinámicos
    filtrosDinamicos = [];

    // Mostrar todas las filas
    const filas = document.querySelectorAll('#codificacion-body tr');
    filas.forEach(fila => {
        fila.style.display = '';
    });

    showToast('Filtros limpiados. Mostrando todos los registros', 'info');
}

/** =========================
 *  Funciones de selección
 *  ========================= */
function selectRow(row, id) {
	document.querySelectorAll('#codificacion-body tr').forEach(r => {
		r.classList.remove('bg-blue-100', 'border-blue-300');
		r.classList.add('hover:bg-gray-50');
	});

	row.classList.remove('hover:bg-gray-50');
	row.classList.add('bg-blue-100', 'border-blue-300');

	selectedRow = row;
	selectedId = id;

	enableButtons();
}

function enableButtons() {
	const editBtn = document.getElementById('btn-editar');
	const deleteBtn = document.getElementById('btn-eliminar');

	if (editBtn) {
		editBtn.disabled = false;
		editBtn.className = 'p-2 text-blue-600 hover:text-blue-800 rounded-md transition-colors';
	}

	if (deleteBtn) {
		deleteBtn.disabled = false;
		deleteBtn.className = 'p-2 text-red-600 hover:text-red-800 rounded-md transition-colors';
	}
}

/** =========================
 *  Init
 *  ========================= */
document.addEventListener('DOMContentLoaded', () => {
	// Asegura que todos los TDs tengan class column-X
	$('#mainTable tbody').querySelectorAll('tr').forEach(tr => {
		tr.querySelectorAll('td').forEach((td, i) => td.classList.add(`column-${i}`));
	});
	enhanceHeaders();
});
</script>

{{-- SweetAlert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection
