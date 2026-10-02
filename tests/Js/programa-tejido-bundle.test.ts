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
// Globales de mentira para evaluar el bundle en node: sin tipos del DOM a propósito.
const g = globalThis as any

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

	g.document = {
		addEventListener: noop, removeEventListener: noop,
		querySelector: () => null, querySelectorAll: () => [],
		getElementById: () => null, createElement: () => el,
		createTextNode: () => el,
		body: el, documentElement: el, head: el, readyState: 'loading', cookie: '',
	}
	g.addEventListener = noop
	g.removeEventListener = noop
	g.localStorage = store
	g.sessionStorage = store
	g.showToast = noop
	g.notify = { success: noop, error: noop, warning: noop, info: noop }
	g.http = { get: noop, post: noop }
	g.toast = noop
	g.$ = () => ({ on: noop, off: noop, val: noop, each: noop, length: 0, select2: noop })
	g.jQuery = g.$
	g.requestAnimationFrame = () => 0
	g.matchMedia = () => ({ matches: false, addEventListener: noop })
	g.getComputedStyle = () => new Proxy({}, { get: () => '' })

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
	g.PT_BOOT = {
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

	g.window = globalThis
}

test('el bundle se evalua sin errores y publica su superficie', async () => {
	instalarDomDeMentira()

	// Si el bundle lanza al evaluarse, esto rechaza y el test falla con el error real.
	// Ruta en variable: index.js es JS (PT-TS 2) y el typecheck de los tests no lo resuelve.
	const bundle = '../../resources/js/programa-tejido/index.js'
	await import(bundle)

	// Lo que la pagina necesita encontrar en window despues de la carga.
	for (const nombre of [
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
		// Puentes de los modales (PT-TS 1): abrir lo llama index.js; cerrar, el onclose de x-ui.modal-base.
		// Guardar/Crear ya no son globales: los botones se atienden por id dentro de cada modal.
		'abrirModalActCalendarios',
		'cerrarModalActCalendarios',
		'abrirModalRepaso',
		'cerrarModalRepaso',
		'abrirModalMarbetes',
		'cerrarModalMarbetes',
		// Tabla y modal de líneas (PT-05, HANDOFF B1).
		'openLinesModal',
		'loadReqProgramaTejidoLines',
	]) {
		assert.equal(typeof g[nombre], 'function', `window.${nombre} no quedo publicado`)
	}
})
