/**
 * Mejora ts-catcodificacion: resources/js/catcodificacion/index.js → index.ts (pantalla
 * principal de Codificación) y lmat-modal.js → lmat-modal.ts.
 *
 * Lo que la migración cambió y aquí se fija:
 *  - fetch crudo → window.http (errores HttpError con { s:false, e } / { errors } de Laravel).
 *  - onclick="removeFilterFromModal(i)" / "loadData()" → data-quitar-filtro / data-reintentar
 *    con delegate (las funciones ya no son globales).
 *  - window.* que siguen usando los onclick del Blade y excel-upload (window.loadData(true)).
 *  - reimpresión: con responseType blob el JSON de error llega como Blob.
 *  - modal L.Mat: usa showToast importado (ya no recibe fallbackToast/showToast por contexto).
 */
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'
import axios, { AxiosError } from 'axios'

import { el, esperar, evento, instalarEntorno, instalarStubs } from './mejoras-ts-catcodificacion-dom.mjs'

instalarStubs()
const { document, window, registro } = instalarEntorno()
// Los catch de la pantalla registran con console.error: aquí solo ensucian la salida.
console.error = () => {}

const peticiones = []
let responder = () => ({ status: 200, data: {} })
axios.defaults.adapter = async (config) => {
    peticiones.push(config)
    const { status, data } = responder(config)
    const response = { data, status, statusText: '', headers: {}, config, request: {} }
    if (status >= 200 && status < 300) return response
    throw new AxiosError(`Request failed with status code ${status}`, 'ERR_BAD_RESPONSE', config, {}, response)
}

const COLUMNAS = ['Id', 'OrdenTejido', 'TelarId', 'Departamento', 'UsuarioCrea', 'OrdCompartida']
// all-fast: c = columnas (puede traer más que las visibles), d = filas como arreglos.
const DATOS = {
    s: true,
    c: [...COLUMNAS, 'TieneLMat'],
    d: [
        [1, 'OT-1', '', 'JACQUARD', 'ana', null, 0],
        [3, 'OT-3', '201', 'JACQUARD', 'luis', null, 1],
        [2, 'OT-2', '305', 'SMITH', '', 'OC-9', '0'],
    ],
    t: 3,
}
const rutaDatos = (config) => (config.url.startsWith('/api/cat') ? { status: 200, data: DATOS } : null)

// Markup que lee el script (catcodificacion/index.blade.php).
const d = document
const config = el(d, 'script', { id: 'catcodificacion-config', type: 'application/json' })
config.textContent = JSON.stringify({
    apiUrl: '/api/cat',
    columnas: COLUMNAS,
    columnLabels: { OrdenTejido: 'Orden' },
    columnSegmentClass: {},
    totalRegistros: 3,
})
const ths = COLUMNAS.map((col, i) =>
    el(d, 'th', { class: 'column-' + i, 'data-index': String(i), 'data-column': col }, [
        el(d, 'span', { class: 'codificacion-header-icons' }),
    ]),
)
const tbody = el(d, 'tbody', { id: 'catcodificacion-body' })
d.body.appendChild(config)
d.body.appendChild(el(d, 'table', { id: 'mainTable' }, [el(d, 'thead', {}, [el(d, 'tr', {}, ths)]), tbody]))

responder = (c) => rutaDatos(c) ?? { status: 404, data: {} }
await import('../../resources/js/catcodificacion/index.ts')
await esperar()

const filasTabla = () => tbody.querySelectorAll('tr')
const ultimaUrl = () => peticiones[peticiones.length - 1]?.url
const fila = (id) => filasTabla().find((tr) => tr.dataset.catId === String(id))
const clic = (e) => e.dispatchEvent(evento('click'))

test('carga inicial: filas ordenadas por Id descendente, con data-index/data-cat-id y badge LMat', () => {
    assert.equal(peticiones[0].url, '/api/cat')
    assert.equal(peticiones[0].headers.Accept ?? peticiones[0].headers.get?.('Accept'), 'application/json')
    const filas = filasTabla()
    assert.deepEqual(filas.map((tr) => tr.dataset.catId), ['3', '2', '1'])
    assert.deepEqual(filas.map((tr) => tr.dataset.index), ['0', '1', '2'])
    // TieneLMat === 1 (número) pinta el badge; '0' no.
    const celdaOrden = (tr) => tr.querySelector('td[data-column="OrdenTejido"]')
    assert.ok(celdaOrden(filas[0]).querySelector('.lmat-badge'), 'Id 3 tiene LMat')
    assert.equal(celdaOrden(filas[1]).querySelector('.lmat-badge'), null)
    assert.equal(celdaOrden(filas[0]).textContent, 'OT-3LMat')
})

test('los onclick del Blade y excel-upload encuentran sus funciones en window', () => {
    const blade = readFileSync(new URL('../../resources/views/catcodificacion/index.blade.php', import.meta.url), 'utf8')
    const nombres = [...blade.matchAll(/onclick="(\w+)\(/g)].map((m) => m[1])
        // subirExcelCatCodificacion lo define el partial excel-upload, no este módulo.
        .filter((n) => n !== 'subirExcelCatCodificacion')
    assert.ok(nombres.length >= 6, 'el Blade sigue teniendo los botones de la navbar')
    for (const nombre of [...nombres, 'loadData', 'reimprimirOrden', 'limpiarFiltrosCodificacion']) {
        assert.equal(typeof window[nombre], 'function', `window.${nombre}`)
    }
    const excel = readFileSync(new URL('../../resources/views/catcodificacion/partials/excel-upload.blade.php', import.meta.url), 'utf8')
    assert.match(excel, /window\.loadData\(true\)/, 'excel-upload sigue refrescando con window.loadData(true)')
})

test('window.loadData(true) (excel-upload) recarga sin caché', async () => {
    responder = (c) => rutaDatos(c) ?? { status: 404, data: {} }
    await window.loadData(true)
    assert.equal(ultimaUrl(), '/api/cat?nocache=1')
    assert.equal(filasTabla().length, 3)
})

test('filtro del modal: chips sin onclick, escapados, y la × quita el filtro por delegación', async () => {
    responder = (c) => rutaDatos(c) ?? { status: 404, data: {} }
    registro.dialogos.length = 0
    await window.filtrarCodificacion()
    assert.match(ultimaUrl(), /todo=1/, 'al filtrar carga el catálogo completo')
    const opciones = registro.dialogos.at(-1)
    assert.equal(opciones.titulo, 'Filtrar datos')
    assert.doesNotMatch(opciones.html, /onclick=/)

    // Lo que el <dialog> pondría en el DOM (el fake no parsea HTML).
    const colSelect = el(d, 'select', { id: 'filtro-columna', value: '' })
    const valor = el(d, 'input', { id: 'filtro-valor', value: '' })
    const agregar = el(d, 'button', { id: 'btn-add-filter' })
    const lista = el(d, 'div', { id: 'modal-filters-list' })
    const activos = el(d, 'div', { id: 'modal-active-filters', class: 'hidden' }, [lista])
    const limpiar = el(d, 'div', { id: 'btn-clear-container', class: 'hidden' })
    const dialogo = el(d, 'dialog', {}, [colSelect, valor, agregar, activos, limpiar])
    d.body.appendChild(dialogo)
    opciones.alAbrir()
    assert.equal(colSelect.querySelectorAll('option').length, COLUMNAS.length, 'columnas en el select')

    registro.toasts.length = 0
    colSelect.value = '1' // OrdenTejido
    valor.value = '<b>"OT-3"</b>'
    clic(agregar)
    assert.deepEqual(registro.toasts.at(-1), ['No se encontraron resultados', 'warning'])
    assert.equal(activos.classList.contains('hidden'), false)
    assert.match(lista.innerHTML, /data-quitar-filtro="0"/)
    assert.doesNotMatch(lista.innerHTML, /onclick=/)
    assert.match(lista.innerHTML, /&lt;b&gt;&quot;OT-3&quot;&lt;\/b&gt;/, 'el valor se escapa')
    assert.doesNotMatch(lista.innerHTML, /<b>/)

    colSelect.value = '1'
    valor.value = 'ot-3'
    clic(agregar)
    assert.match(lista.innerHTML, /data-quitar-filtro="1"/)

    // La × del primer chip (innerHTML no se parsea: se agrega el botón a mano).
    const equis = el(d, 'button', { 'data-quitar-filtro': '0' }, [el(d, 'i')])
    lista.appendChild(equis)
    clic(equis.children[0])
    assert.deepEqual(registro.toasts.at(-1), ['Filtro eliminado', 'info'])
    assert.match(lista.innerHTML, /ot-3/)
    assert.doesNotMatch(lista.innerHTML, /OT-3&quot;/, 'se quitó el filtro 0, no el 1')
    assert.match(lista.innerHTML, /data-quitar-filtro="0"/, 'el que queda se reindexa a 0')

    const equis2 = el(d, 'button', { 'data-quitar-filtro': '0' })
    lista.appendChild(equis2)
    clic(equis2)
    assert.equal(activos.classList.contains('hidden'), true, 'sin filtros se oculta la lista')
    assert.equal(filasTabla().length, 3)
    dialogo.remove()
})

test('revivir: 422 de Laravel muestra el primer error de validación y manda cat_id numérico', async () => {
    clic(fila(3))
    registro.respuestasDialogo.push('final')
    registro.toasts.length = 0
    responder = (c) =>
        c.url === '/planeacion/codificacion/api/revivir-programa'
            ? { status: 422, data: { s: false, e: 'Validación fallida', errors: { cat_id: ['El cat id no es válido.'] } } }
            : rutaDatos(c)
    await window.revivirOrdenAlPrograma()
    const post = peticiones.at(-1)
    assert.equal(post.method, 'post')
    assert.deepEqual(JSON.parse(post.data), { cat_id: 3, en_proceso: false })
    assert.deepEqual(registro.toasts, [['El cat id no es válido.', 'error']])
})

test('revivir: error del servicio { s:false, e } con 500 muestra e', async () => {
    registro.respuestasDialogo.push('en_proceso')
    registro.toasts.length = 0
    responder = () => ({ status: 500, data: { s: false, e: 'La orden ya está en el programa' } })
    await window.revivirOrdenAlPrograma()
    assert.deepEqual(JSON.parse(peticiones.at(-1).data), { cat_id: 3, en_proceso: true })
    assert.deepEqual(registro.toasts, [['La orden ya está en el programa', 'error']])
})

test('revivir: éxito muestra el Id del programa', async () => {
    registro.respuestasDialogo.push('final')
    registro.toasts.length = 0
    responder = () => ({ status: 200, data: { s: true, d: { programa_id: 987 } } })
    await window.revivirOrdenAlPrograma()
    assert.deepEqual(registro.toasts, [['Orden creada en programa (Id 987)', 'success']])
})

test('reimprimir: pide blob con Accept xlsx y descarga', async () => {
    registro.toasts.length = 0
    responder = () => ({ status: 200, data: new Blob(['xlsx']) })
    await window.reimprimirOrden(7)
    const get = peticiones.at(-1)
    assert.equal(get.url, '/planeacion/programa-tejido/reimprimir-ordenes/7')
    assert.equal(get.responseType, 'blob')
    const accept = get.headers.Accept ?? get.headers.get?.('Accept')
    assert.equal(accept, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    assert.deepEqual(registro.toasts.at(-1), ['Orden reimpresa correctamente', 'success'])
})

test('reimprimir: el JSON de error llega como Blob y su message llega al toast', async () => {
    registro.toasts.length = 0
    responder = () => ({
        status: 404,
        data: new Blob([JSON.stringify({ message: 'No se encontró la orden 7' })], { type: 'application/json' }),
    })
    await window.reimprimirOrden(7)
    assert.deepEqual(registro.toasts, [['No se encontró la orden 7', 'error']])
})

test('reimprimir seleccionada: sin UsuarioCrea no pide nada', async () => {
    clic(fila(2)) // UsuarioCrea ''
    registro.toasts.length = 0
    const antes = peticiones.length
    window.reimprimirOrdenSeleccionada()
    await esperar()
    assert.equal(peticiones.length, antes)
    assert.equal(registro.toasts[0][1], 'warning')
})

test('balancear: error 404 con message del servidor', async () => {
    // Fila 2 tiene OrdCompartida OC-9 y sigue seleccionada.
    registro.toasts.length = 0
    responder = () => ({ status: 404, data: { success: false, message: 'Sin registros para OC-9' } })
    await window.abrirModalBalancear()
    assert.equal(peticiones.at(-1).url, '/planeacion/codificacion/api/registros-ord-compartida/OC-9')
    assert.deepEqual(registro.toasts, [['Sin registros para OC-9', 'error']])
})

test('modal L.Mat: sin telar avisa con el showToast importado y no consulta nada', async () => {
    clic(fila(1)) // TelarId ''
    registro.toasts.length = 0
    const antes = peticiones.length
    await window.mostrarModalLMat()
    assert.deepEqual(registro.toasts, [['La fila seleccionada no tiene telar.', 'warning']])
    assert.equal(peticiones.length, antes)
})

test('modal L.Mat: sin fila seleccionada avisa', async () => {
    clic(fila(1)) // deselecciona
    registro.toasts.length = 0
    await window.mostrarModalLMat()
    assert.deepEqual(registro.toasts, [['Selecciona primero una fila.', 'warning']])
})

test('error al cargar: mensaje del servidor escapado, Reintentar sin onclick', async () => {
    registro.toasts.length = 0
    responder = () => ({ status: 500, data: { s: false, e: '<img src=x onerror=alert(1)>' } })
    await window.loadData()
    assert.match(tbody.innerHTML, /&lt;img src=x onerror=alert\(1\)&gt;/)
    assert.doesNotMatch(tbody.innerHTML, /<img/)
    assert.match(tbody.innerHTML, /data-reintentar/)
    assert.doesNotMatch(tbody.innerHTML, /onclick=/)
    assert.deepEqual(registro.toasts, [['Error al cargar datos: <img src=x onerror=alert(1)>', 'error']])
})

test('respuesta 200 con s:false también cae en el estado de error', async () => {
    registro.toasts.length = 0
    responder = () => ({ status: 200, data: { s: false, e: 'Timeout de BD' } })
    await window.loadData()
    assert.match(tbody.innerHTML, /Timeout de BD/)
    assert.deepEqual(registro.toasts, [['Error al cargar datos: Timeout de BD', 'error']])
})
