// ===== Estado =====
let filters = [];
let hiddenColumns = [];
let pinnedColumns = [];
let allRows = [];
let selectedRowIndex = -1;
let dragDropMode = false;
let draggedRow = null;
let draggedRowIndex = -1;
let draggedRowTelar = null;
let draggedRowSalon = null;
let draggedRowCambioHilo = null;

// Cache para optimización de drag and drop
let rowCache = new Map(); // Cache de datos de filas (telar, salon, etc)
let dragOverThrottle = null;
let lastDragOverTime = 0;

let inlineEditMode = false;

const normalizeDateValue = (value) => {
	if (value === undefined || value === null) return '';
	const str = String(value).trim();
	if (!str || str.toLowerCase() === 'null') return '';
	if (str.includes('T')) return str.split('T')[0];
	if (str.includes(' ')) return str.split(' ')[0];
	return str;
};

const normalizeDateTimeValue = (value) => {
	if (value === undefined || value === null) return '';
	let str = String(value).trim();
	if (!str || str.toLowerCase() === 'null') return '';
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

const inlineEditableFields = {
	Ancho: { type: 'number', step: '0.01', min: 0 },
	EficienciaSTD: {
		type: 'number',
		step: '0.1',
		min: 0,
		max: 100,
		inputFormatter: (value) => (value === null || value === '' ? '' : (parseFloat(value) * 100).toFixed(2)),
		toPayload: (value) => {
			if (value === '') return null;
			const num = parseFloat(value);
			if (isNaN(num)) return null;
			return num / 100;
		},
		displayFormatter: (value) => {
			if (value === null || value === '' || isNaN(value)) return '';
			return `${Math.round(parseFloat(value) * 100)}%`;
		},
		compareFormatter: (value) => {
			if (value === null || value === '' || isNaN(value)) return null;
			return parseFloat(value);
		}
	},
	VelocidadSTD: { type: 'number', step: '0.01', min: 0 },
	FibraRizo: { type: 'text', maxLength: 120 },
	CalibrePie2: { type: 'number', step: '0.01', min: 0 },
	TotalPedido: { type: 'number', step: '0.01', min: 0 },
	CalendarioId: { type: 'text', maxLength: 30 },
	TamanoClave: { type: 'text', maxLength: 40 },
	NoExisteBase: { type: 'text', maxLength: 20 },
	Rasurado: { type: 'text', maxLength: 2 },
	ProgramarProd: createDateFieldConfig(),
	AplicacionId: { type: 'text', maxLength: 10 },
	Observaciones: { type: 'text', maxLength: 100 },
	NoTiras: { type: 'number', step: '1', min: 0 },
	Peine: { type: 'number', step: '1', min: 0 },
	LargoCrudo: { type: 'number', step: '0.01', min: 0 },
	Luchaje: { type: 'number', step: '0.01', min: 0 },
	PesoCrudo: { type: 'number', step: '0.01', min: 0 },
	CalibreTrama2: { type: 'number', step: '0.01', min: 0 },
	FibraTrama: { type: 'text', maxLength: 15 },
	DobladilloId: { type: 'text', maxLength: 20 },
	PasadasComb1: { type: 'number', step: '1', min: 0 },
	PasadasComb2: { type: 'number', step: '1', min: 0 },
	PasadasComb3: { type: 'number', step: '1', min: 0 },
	PasadasComb4: { type: 'number', step: '1', min: 0 },
	PasadasComb5: { type: 'number', step: '1', min: 0 },
	AnchoToalla: { type: 'number', step: '0.01', min: 0 },
	CodColorTrama: { type: 'text', maxLength: 20 },
	FibraComb1: { type: 'text', maxLength: 15 },
	CodColorComb1: { type: 'text', maxLength: 10 },
	NombreCC1: { type: 'text', maxLength: 60 },
	FibraComb2: { type: 'text', maxLength: 15 },
	CodColorComb2: { type: 'text', maxLength: 10 },
	NombreCC2: { type: 'text', maxLength: 60 },
	FibraComb3: { type: 'text', maxLength: 15 },
	CodColorComb3: { type: 'text', maxLength: 10 },
	NombreCC3: { type: 'text', maxLength: 60 },
	FibraComb4: { type: 'text', maxLength: 15 },
	CodColorComb4: { type: 'text', maxLength: 10 },
	NombreCC4: { type: 'text', maxLength: 60 },
	FibraComb5: { type: 'text', maxLength: 15 },
	CodColorComb5: { type: 'text', maxLength: 10 },
	NombreCC5: { type: 'text', maxLength: 60 },
	MedidaPlano: { type: 'number', step: '1', min: 0 },
	CuentaPie: { type: 'text', maxLength: 10 },
	CodColorCtaPie: { type: 'text', maxLength: 10 },
	NombreCPie: { type: 'text', maxLength: 60 },
	FechaInicio: createDateTimeFieldConfig(),
	FechaFinal: createDateTimeFieldConfig(),
	EntregaProduc: createDateFieldConfig(),
	EntregaPT: createDateFieldConfig(),
	EntregaCte: createDateTimeFieldConfig(),
	PTvsCte: { type: 'number', step: '1' }
};

const inlineFieldPayloadMap = {
	Ancho: 'Ancho',
	EficienciaSTD: 'eficiencia_std',
	VelocidadSTD: 'velocidad_std',
	FibraRizo: 'FibraRizo',
	CalibrePie2: 'CalibrePie2',
	TotalPedido: 'TotalPedido',
	CalendarioId: 'calendario_id',
	TamanoClave: 'tamano_clave',
	NoExisteBase: 'no_existe_base',
	Rasurado: 'rasurado',
	ProgramarProd: 'programar_prod',
	AplicacionId: 'aplicacion_id',
	Observaciones: 'observaciones',
	NoTiras: 'no_tiras',
	Peine: 'peine',
	LargoCrudo: 'largo_crudo',
	Luchaje: 'luchaje',
	PesoCrudo: 'peso_crudo',
	CalibreTrama2: 'calibre_trama2',
	FibraTrama: 'fibra_trama',
	DobladilloId: 'dobladillo_id',
	PasadasComb1: 'pasadas_comb1',
	PasadasComb2: 'pasadas_comb2',
	PasadasComb3: 'pasadas_comb3',
	PasadasComb4: 'pasadas_comb4',
	PasadasComb5: 'pasadas_comb5',
	AnchoToalla: 'ancho_toalla',
	CodColorTrama: 'cod_color_trama',
	FibraComb1: 'fibra_c1',
	CodColorComb1: 'cod_color_comb1',
	NombreCC1: 'nombre_cc1',
	FibraComb2: 'fibra_c2',
	CodColorComb2: 'cod_color_comb2',
	NombreCC2: 'nombre_cc2',
	FibraComb3: 'fibra_c3',
	CodColorComb3: 'cod_color_comb3',
	NombreCC3: 'nombre_cc3',
	FibraComb4: 'fibra_c4',
	CodColorComb4: 'cod_color_comb4',
	NombreCC4: 'nombre_cc4',
	FibraComb5: 'fibra_c5',
	CodColorComb5: 'cod_color_comb5',
	NombreCC5: 'nombre_cc5',
	MedidaPlano: 'medida_plano',
	CuentaPie: 'cuenta_pie',
	CodColorCtaPie: 'cod_color_cta_pie',
	NombreCPie: 'nombre_c_pie',
	FechaInicio: 'fecha_inicio',
	FechaFinal: 'fecha_fin',
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

