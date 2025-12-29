{{-- Funciones compartidas para duplicar/vincular y dividir --}}
{{-- NOTA: Este archivo se incluye dentro de un bloque <script>, NO agregar etiquetas <script> aquí --}}

// Variable global para almacenar registros existentes de OrdCompartida
let registrosOrdCompartidaExistentes = [];
let ordCompartidaActual = null;

// Función global para obtener el modo actual (para compatibilidad con radio buttons)
function getModoActual() {
	if (document.getElementById('modo-duplicar')?.checked) return 'duplicar';
	if (document.getElementById('modo-dividir')?.checked) return 'dividir';
	return 'duplicar'; // por defecto
}

// Función para verificar si el checkbox de vincular está activo
function estaVincularActivado() {
	const checkbox = document.getElementById('checkbox-vincular');
	return checkbox && checkbox.checked;
}

// Helpers para obtener datos de la fila
function getRowCellText(row, column, fallback = '') {
	if (!row) return fallback;
	const cell = row.querySelector(`[data-column="${column}"]`);
	const text = cell?.textContent?.trim();
	return text || fallback;
}

function getRowTelar(row) {
	return getRowCellText(row, 'NoTelarId', null);
}

function getRowSalon(row) {
	return getRowCellText(row, 'SalonTejidoId', null);
}

function getCsrfToken() {
	return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function buildCalendarErrorHtml(data) {
	const calendarioHtml = (data.calendario_id && data.fecha_inicio && data.fecha_fin)
		? `<div class="mt-3 text-xs text-red-600"><p><strong>Calendario:</strong> ${data.calendario_id}</p></div>`
		: '';

	return `
		<div class="text-left">
			<div class="bg-red-50 border-l-4 border-red-400 p-4 mb-3">
				<p class="font-semibold text-red-800 mb-2">No se puede duplicar</p>
				<p class="text-sm text-red-700 mb-2">${data.message}</p>
				${calendarioHtml}
			</div>
			<p class="text-xs text-gray-600 mt-3">Por favor, agregue fechas al calendario en el catalogo de calendarios antes de intentar duplicar nuevamente.</p>
		</div>
	`;
}

function buildCalendarWarningHtml(message, advertencias) {
	const detalles = Array.isArray(advertencias?.detalles) ? advertencias.detalles : [];
	const detallesHtml = detalles.length > 0
		? `<ul class="list-disc list-inside text-xs text-yellow-700 space-y-1">${detalles.slice(0, 5).map(detalle => (
			`<li>Calendario '<strong>${detalle.calendario_id}</strong>': ${detalle.mensaje}</li>`
		)).join('')}${detalles.length > 5 ? `<li>... y ${detalles.length - 5} mas</li>` : ''}</ul>`
		: '';

	return `
		<div class="text-left">
			<p class="mb-3 text-sm text-gray-700">${message}</p>
			<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-3">
				<p class="font-semibold text-yellow-800 mb-2">Advertencia: Problemas con calendarios</p>
				<p class="text-sm text-yellow-700 mb-2">${advertencias.total_errores} programa(s) no pudieron generar lineas diarias porque no hay fechas disponibles en el calendario.</p>
				${detallesHtml}
			</div>
			<p class="text-xs text-gray-600">Los programas se crearon correctamente, pero necesitas agregar fechas al calendario para generar las lineas diarias.</p>
		</div>
	`;
}

function redirectToRegistro(data) {
	if (data?.salon_destino && data?.telar_destino) {
		const url = new URL(window.location.href);
		url.searchParams.set('salon', data.salon_destino);
		url.searchParams.set('telar', data.telar_destino);
		if (data.registro_id) {
			url.searchParams.set('registro_id', data.registro_id);
		}
		window.location.href = url.toString();
	} else {
		window.location.reload();
	}
}

// Funciones helper para mostrar/ocultar loading
function showLoading() {
	if (window.PT && window.PT.loader) {
		window.PT.loader.show();
	} else if (typeof Swal !== 'undefined') {
		Swal.showLoading();
	}
}

function hideLoading() {
	if (window.PT && window.PT.loader) {
		window.PT.loader.hide();
	} else if (typeof Swal !== 'undefined') {
		Swal.hideLoading();
	}
}

// Función helper para obtener datos de una fila
function getRowInputs(row) {
	return {
		pedidoTempoInput: row.querySelector('input[name="pedido-tempo-destino[]"]'),
		porcentajeSegundosInput: row.querySelector('input[name="porcentaje-segundos-destino[]"]'),
		totalInput: row.querySelector('input[name="pedido-destino[]"]')
	};
}

function getProduccionInputFromRow(row) {
	const produccionCell = row?.querySelector('.produccion-cell');
	if (!produccionCell) {
		return null;
	}
	return produccionCell.querySelector('input[readonly]') ||
		produccionCell.querySelector('input:not([name])') ||
		produccionCell.querySelector('input[type="hidden"]') ||
		produccionCell.querySelector('input');
}
