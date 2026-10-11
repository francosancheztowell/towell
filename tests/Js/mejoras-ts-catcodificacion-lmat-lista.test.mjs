/**
 * Mejora ts-catcodificacion: resources/js/lmat-lista/index.js → index.ts.
 * Fija la conducta de la pantalla "Listas de materiales" (catalagos/lmat-lista.blade.php):
 * selección de fila → botón "Ver lista", GET catcodificados-por-orden con window.http,
 * mensajes de error del servidor (404 {success:false,message}) y filtros select-buscables.
 */
import assert from 'node:assert/strict'
import test from 'node:test'
import axios, { AxiosError } from 'axios'

import { el, esperar, evento, instalarEntorno, instalarStubs } from './mejoras-ts-catcodificacion-dom.mjs'

instalarStubs({ lmatModal: true })
console.error = () => {}
const { document, registro } = instalarEntorno()

// Respuestas por URL del adaptador de axios.
let responder = () => ({ status: 200, data: {} })
axios.defaults.adapter = async (config) => {
    registro.urls.push(config.url)
    const { status, data } = responder(config)
    const response = { data, status, statusText: '', headers: {}, config, request: {} }
    if (status >= 200 && status < 300) return response
    throw new AxiosError(`Request failed with status code ${status}`, 'ERR_BAD_RESPONSE', config, {}, response)
}

// Markup equivalente a catalagos/lmat-lista.blade.php (lo que lee el script).
const d = document
const boton = el(d, 'button', { id: 'btn-ver-lista', disabled: true })
const contador = el(d, 'span', { id: 'lmat-contador', textContent: '3 lista(s)' })
const filtros = ['f-orden', 'f-nombre', 'f-clave', 'f-tamano', 'f-salon'].map((id) =>
    el(d, 'select', { id, class: 'lmat-filtro-select', value: '' }),
)
const fila = (orden, nombre, salon) =>
    el(d, 'tr', {
        class: 'lmat-row',
        'data-orden': orden,
        'data-f-orden': orden.toLowerCase(),
        'data-f-nombre': nombre,
        'data-f-clave': '',
        'data-f-tamano': '',
        'data-f-salon': salon,
    })
const filas = [fila('OT 1/2', 'toalla azul', 'jacquard'), fila('OT-2', 'toalla roja', 'smith'), fila('OT-3', 'sabana azul', 'smith')]
const tbody = el(d, 'tbody', {}, filas)
d.body.appendChild(boton)
d.body.appendChild(contador)
filtros.forEach((f) => d.body.appendChild(f))
d.body.appendChild(el(d, 'table', {}, [tbody]))

await import('../../resources/js/lmat-lista/index.ts')

const clic = (e) => e.dispatchEvent(evento('click'))
const seleccionadas = () => filas.filter((f) => f.classList.contains('lmat-row-selected'))

test('seleccionar fila habilita "Ver lista"; volver a hacer clic la deselecciona', () => {
    clic(filas[1])
    assert.equal(boton.disabled, false)
    assert.deepEqual(seleccionadas(), [filas[1]])

    clic(filas[2])
    assert.deepEqual(seleccionadas(), [filas[2]], 'solo una fila seleccionada a la vez')

    clic(filas[2])
    assert.deepEqual(seleccionadas(), [])
    assert.equal(boton.disabled, true)
})

test('"Ver lista" pide la orden codificada en la URL y abre el modal L.Mat con data', async () => {
    const registroCat = { OrdenTejido: 'OT 1/2', TelarId: '201' }
    responder = () => ({ status: 200, data: { success: true, data: registroCat } })
    registro.urls.length = 0
    registro.lmat.length = 0

    clic(filas[0])
    clic(boton)
    await esperar()

    assert.deepEqual(registro.urls, ['/planeacion/lmat/api/catcodificados-por-orden/OT%201%2F2'])
    assert.equal(registro.lmat.length, 1)
    assert.equal(registro.lmat[0].getSelectedRecord(), registroCat)
    registro.lmat[0].onSaved()
    assert.equal(registro.recargas, 1, 'al guardar recarga la página')
    clic(filas[0])
})

test('404 del servidor: toast con el message de CatLMatController y sin abrir el modal', async () => {
    responder = () => ({
        status: 404,
        data: { success: false, message: 'No existe registro en CatCodificados para esta orden.' },
    })
    registro.toasts.length = 0
    registro.lmat.length = 0

    clic(filas[1])
    clic(boton)
    await esperar()

    assert.equal(registro.lmat.length, 0)
    assert.deepEqual(registro.toasts, [['No existe registro en CatCodificados para esta orden.', 'error']])
    clic(filas[1])
})

test('200 con success:false y sin message: aviso genérico, sin modal', async () => {
    responder = () => ({ status: 200, data: { success: false } })
    registro.toasts.length = 0
    registro.lmat.length = 0

    clic(filas[2])
    clic(boton)
    await esperar()

    assert.equal(registro.lmat.length, 0)
    assert.equal(registro.toasts.length, 1)
    assert.equal(registro.toasts[0][1], 'error')
    assert.match(registro.toasts[0][0], /Error al cargar el registro/)
    clic(filas[2])
})

test('sin fila seleccionada, "Ver lista" no hace petición', async () => {
    registro.urls.length = 0
    clic(boton)
    await esperar()
    assert.deepEqual(registro.urls, [])
})

test('cada filtro se vuelve combobox con permitirVacio', () => {
    assert.equal(registro.combobox.length, 5)
    assert.deepEqual(registro.combobox.map(([s]) => s.id), ['f-orden', 'f-nombre', 'f-clave', 'f-tamano', 'f-salon'])
    for (const [, opciones] of registro.combobox) assert.deepEqual(opciones, { permitirVacio: true })
})

test('filtros AND, sin distinguir mayúsculas ni espacios, y contador de visibles', () => {
    const [fOrden, fNombre, , , fSalon] = filtros
    fNombre.value = '  AZUL '
    fNombre.dispatchEvent(evento('change', { bubbles: false }))
    assert.deepEqual(filas.map((f) => f.classList.contains('hidden')), [false, true, false])
    assert.equal(contador.textContent, '2 lista(s)')

    fSalon.value = 'Smith'
    fSalon.dispatchEvent(evento('change', { bubbles: false }))
    assert.deepEqual(filas.map((f) => f.classList.contains('hidden')), [true, true, false])
    assert.equal(contador.textContent, '1 lista(s)')

    fOrden.value = 'ot-9'
    fOrden.dispatchEvent(evento('change', { bubbles: false }))
    assert.equal(contador.textContent, '0 lista(s)')

    for (const f of filtros) f.value = ''
    fOrden.dispatchEvent(evento('change', { bubbles: false }))
    assert.deepEqual(filas.map((f) => f.classList.contains('hidden')), [false, false, false])
    assert.equal(contador.textContent, '3 lista(s)')
})
