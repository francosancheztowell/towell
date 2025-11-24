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
const inlineEditableFields = {
	Ancho: { type: 'number', step: '0.01', min: 0 },
	EficienciaSTD: {
		type: 'number',
		step: '0.1',
		min: 0,
		max: 100,
		inputFormatter: (value) => value === null || value === '' ? '' : (parseFloat(value) * 100).toFixed(2),
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
	TotalPedido: { type: 'number', step: '0.01', min: 0 }
};
const inlineFieldPayloadMap = {
	Ancho: 'Ancho',
	EficienciaSTD: 'eficiencia_std',
	VelocidadSTD: 'velocidad_std',
	FibraRizo: 'FibraRizo',
	CalibrePie2: 'CalibrePie2',
	TotalPedido: 'TotalPedido'
};

// ===== Helpers DOM =====
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const tbodyEl = () => $('#mainTable tbody');

// ===== Columnas desde PHP =====
const columnsData = @json($columns ?? []);

