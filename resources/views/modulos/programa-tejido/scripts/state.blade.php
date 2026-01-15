// ===== Estado =====
let filters = [];
let hiddenColumns = [];
let pinnedColumns = [];
let allRows = [];
let selectedRowIndex = -1;
window.selectedRowIndex = selectedRowIndex; // Exponer globalmente
let inlineEditMode = false;
window.inlineEditMode = inlineEditMode; // Exponer globalmente

const normalizeInputValue = (value) => {
	if (value === undefined || value === null) return '';
	const str = String(value).trim();
	if (!str || str.toLowerCase() === 'null') return '';
	return str;
};

const normalizeDateValue = (value) => {
	const str = normalizeInputValue(value);
	if (!str) return '';
	if (str.includes('T')) return str.split('T')[0];
	if (str.includes(' ')) return str.split(' ')[0];
	return str;
};

const normalizeDateTimeValue = (value) => {
	let str = normalizeInputValue(value);
	if (!str) return '';
	str = str.replace('T', ' ').replace('Z', '');
	if (str.includes('.')) str = str.split('.')[0];
	const [datePart, rawTime = ''] = str.split(' ');
	if (!rawTime) return `${datePart} 00:00:00`.trim();
	const [hour = '00', minute = '00', second = '00'] = rawTime.split(':');
	return `${datePart} ${hour.padStart(2, '0')}:${minute.padStart(2, '0')}:${second.padStart(2, '0')}`;
};

const formatDateInputValue = (value) => normalizeDateValue(value);

const formatDateDisplayValue = (value) => {
	const normalized = normalizeDateValue(value);
	if (!normalized) return '';
	const [year, month, day] = normalized.split('-');
	if (!year || !month || !day) return normalized;
	return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year}`;
};

const formatDateTimeInputValue = (value) => {
	const normalized = normalizeDateTimeValue(value);
	if (!normalized) return '';
	const [datePart, timePart = ''] = normalized.split(' ');
	if (!datePart) return '';
	const [hour = '00', minute = '00'] = timePart.split(':');
	return `${datePart}T${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
};

const formatDateTimeDisplayValue = (value) => {
	const normalized = normalizeDateTimeValue(value);
	if (!normalized) return '';
	const [datePart, timePart = ''] = normalized.split(' ');
	if (!datePart) return normalized;
	const [year, month, day] = datePart.split('-');
	if (!year || !month || !day) return normalized;
	if (!timePart) return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year}`;
	const [hour = '00', minute = '00'] = timePart.split(':');
	return `${day.padStart(2, '0')}/${month.padStart(2, '0')}/${year} ${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
};

const datetimeLocalToSql = (value) => {
	if (!value) return null;
	const [datePart, timePart = ''] = value.split('T');
	if (!datePart) return null;
	const [hour = '00', minute = '00'] = timePart.split(':');
	return `${datePart} ${hour.padStart(2, '0')}:${minute.padStart(2, '0')}:00`;
};

const dateCompareValue = (value) => {
	const normalized = formatDateInputValue(value);
	return normalized === '' ? null : normalized;
};

const dateTimeCompareValue = (value) => {
	const normalized = normalizeDateTimeValue(value);
	return normalized === '' ? null : normalized;
};

const createDateFieldConfig = () => ({
	type: 'date',
	inputFormatter: formatDateInputValue,
	displayFormatter: formatDateDisplayValue,
	toPayload: (value) => (value === '' ? null : value),
	compareFormatter: dateCompareValue
});

const createDateTimeFieldConfig = () => ({
	type: 'datetime-local',
	inputFormatter: formatDateTimeInputValue,
	displayFormatter: formatDateTimeDisplayValue,
	toPayload: (value) => (value ? datetimeLocalToSql(value) : null),
	compareFormatter: dateTimeCompareValue
});

// Campos editables permitidos segÃºn especificaciÃ³n:
// - Hilo (FibraRizo) - SELECT con catÃ¡logo
// - Jornada (CalendarioId) - SELECT con catÃ¡logo
// - Clave Modelo (TamanoClave)
// - Rasurado
// - Pedido (TotalPedido)
// - Dia Scheduling (ProgramarProd)
// - Id Flog (FlogsId)
// - DescripciÃ³n (NombreProyecto)
// - Aplicaciones (AplicacionId) - SELECT con catÃ¡logo
// - Tiras (NoTiras)
// - Pei (Peine)
// - Lcr (LargoCrudo)
// - Luc (Luchaje)
// - Pcr (PesoCrudo)
// - Fecha Compromiso Prod (EntregaProduc)
// - Fecha Compromiso Pt (EntregaPT)
// - Entrega (EntregaCte)
// - Dif vs Compromiso (PTvsCte)
const inlineEditableFields = {
	FibraRizo: { type: 'select', catalog: 'hilos' }, // Select con catÃ¡logo de hilos
	CalendarioId: { type: 'select', catalog: 'calendarios' }, // Select con catÃ¡logo de calendarios (Jornada)
	TamanoClave: { type: 'text', maxLength: 40 },
	Rasurado: { type: 'text', maxLength: 2 },
	TotalPedido: { type: 'number', step: '0.01', min: 0 },
	ProgramarProd: createDateFieldConfig(),
	FlogsId: { type: 'text', maxLength: 20 },
	NombreProyecto: { type: 'text', maxLength: 150 },
	AplicacionId: { type: 'select', catalog: 'aplicaciones' }, // Select con catÃ¡logo de aplicaciones
	NoTiras: { type: 'number', step: '1', min: 0 },
	Peine: { type: 'number', step: '1', min: 0 },
	LargoCrudo: { type: 'number', step: '0.01', min: 0 },
	Luchaje: { type: 'number', step: '0.01', min: 0 },
	PesoCrudo: { type: 'number', step: '0.01', min: 0 },
	EntregaProduc: createDateFieldConfig(),
	EntregaPT: createDateFieldConfig(),
	EntregaCte: createDateTimeFieldConfig(),
	PTvsCte: { type: 'number', step: '1' }
};

// Cache de catÃ¡logos cargados
let catalogosCache = {
	hilos: null,
	aplicaciones: null,
	calendarios: null
};

// Mapeo de campos editables a nombres de payload para el backend
const inlineFieldPayloadMap = {
	FibraRizo: 'hilo',
	CalendarioId: 'calendario_id',
	TamanoClave: 'tamano_clave',
	Rasurado: 'rasurado',
	TotalPedido: 'pedido',
	ProgramarProd: 'programar_prod',
	FlogsId: 'idflog',
	NombreProyecto: 'descripcion',
	AplicacionId: 'aplicacion_id',
	NoTiras: 'no_tiras',
	Peine: 'peine',
	LargoCrudo: 'largo_crudo',
	Luchaje: 'luchaje',
	PesoCrudo: 'peso_crudo',
	EntregaProduc: 'entrega_produc',
	EntregaPT: 'entrega_pt',
	EntregaCte: 'entrega_cte',
	PTvsCte: 'pt_vs_cte'
};

// ===== Helpers DOM =====
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const tbodyEl = () => $('#mainTable tbody');

// ===== Columnas desde PHP =====
const columnsData = @json($columns ?? []);

