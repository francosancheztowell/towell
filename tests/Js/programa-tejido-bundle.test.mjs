/**
 * Evalua el bundle de Programa Tejido de arriba abajo con un DOM de mentira.
 *
 * Es la unica red que caza los fallos que solo aparecen al cargar la pagina, y que
 * ni `node --check` ni los tests de strings ven. Los dos que se colaron hoy:
 *   - `const data` declarado dos veces en el mismo scope -> SyntaxError
 *   - `buildBaseInfoCells is not defined`, por publicar los globals al final del
 *     archivo, donde los nombres de un bloque anidado no estan en scope
 * En los dos casos el modulo entero se queda sin cargar y no abre ningun modal.
 */
import assert from 'node:assert/strict'
import test from 'node:test'

const noop = () => {}

function instalarDomDeMentira() {
	const el = new Proxy({}, {
		get: (_t, k) => {
			if (k === 'style' || k === 'dataset' || k === 'classList') {
				return new Proxy({}, { get: () => noop })
			}
			if (k === 'children' || k === 'childNodes') return []
			if (k === 'nodeType') return 1
			return typeof k === 'string' && k.startsWith('on') ? null : noop
		},
		set: () => true,
	})

	const store = { getItem: () => null, setItem: noop, removeItem: noop, clear: noop }

	globalThis.document = {
		addEventListener: noop, removeEventListener: noop,
		querySelector: () => null, querySelectorAll: () => [],
		getElementById: () => null, createElement: () => el,
		createTextNode: () => el,
		body: el, documentElement: el, head: el, readyState: 'loading', cookie: '',
	}
	globalThis.localStorage = store
	globalThis.sessionStorage = store
	globalThis.Swal = { fire: noop, close: noop, isVisible: () => false }
	globalThis.showToast = noop
	globalThis.notify = { success: noop, error: noop, warning: noop, info: noop }
	globalThis.http = { get: noop, post: noop }
	globalThis.toast = noop
	globalThis.$ = () => ({ on: noop, off: noop, val: noop, each: noop, length: 0, select2: noop })
	globalThis.jQuery = globalThis.$
	globalThis.requestAnimationFrame = () => 0
	globalThis.matchMedia = () => ({ matches: false, addEventListener: noop })
	globalThis.getComputedStyle = () => new Proxy({}, { get: () => '' })

	// navigator y location son solo-lectura en node
	Object.defineProperty(globalThis, 'navigator', {
		configurable: true, value: { userAgent: 'node' },
	})
	Object.defineProperty(globalThis, 'location', {
		configurable: true,
		value: {
			href: 'http://localhost/planeacion/programa-tejido',
			origin: 'http://localhost', search: '', pathname: '/planeacion/programa-tejido',
		},
	})

	// Lo que el Blade imprime en #pt-boot (aqui no hay nodo: boot.ts cae a window.PT_BOOT).
	globalThis.PT_BOOT = {
		basePath: '/planeacion/programa-tejido',
		apiPath: '/programa-tejido',
		linePath: '/planeacion/req-programa-tejido-line',
		columns: [{ field: 'NoTelarId', label: 'Telar' }],
		hiddenFields: [],
		routes: {
			codificacion: '/a', codificacionModelos: '/b', vincularRegistros: '/c',
			marbetes: '/d', marbetesGuardar: '/e', recalcularFechas: '/f',
		},
	}

	globalThis.window = globalThis
}

test('el bundle se evalua sin errores y publica su superficie', async () => {
	instalarDomDeMentira()

	// Si el bundle lanza al evaluarse, esto rechaza y el test falla con el error real.
	await import('../../resources/js/programa-tejido/index.js')

	// Lo que la pagina necesita encontrar en window despues de la carga.
	for (const nombre of [
		'duplicarTelar',
		'selectRow',
		'deselectRow',
		'updateTotales',
		'loadPersistedHiddenColumns',
		'updatePinnedColumnsPositions',
		'openProgramaTejidoFilterModal',
		'toggleInlineEditMode',
		// Los que vivian en <script> inline de la vista (04-perf, corte 5).
		'abrirBalancearDesdeSeleccion',
		'verDetallesGrupoBalanceo',
		'aplicarBalanceoAutomatico',
		'abrirModalActCalendarios',
		'guardarCalendariosSeleccionados',
		'abrirModalRepaso',
		'crearRepasoEnviar',
		'abrirModalMarbetes',
		'guardarMarbetesEnviar',
		// Tabla y modal de líneas (PT-05, HANDOFF B1).
		'openLinesModal',
		'loadReqProgramaTejidoLines',
	]) {
		assert.equal(typeof globalThis[nombre], 'function', `window.${nombre} no quedo publicado`)
	}
})
