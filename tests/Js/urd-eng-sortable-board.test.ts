import assert from 'node:assert/strict'
import test from 'node:test'

// DOM mínimo (sin jsdom en el proyecto). Debe existir antes de importar SortableJS: decide
// en carga si hay drag nativo, que es el camino del error de producción.
/* eslint-disable @typescript-eslint/no-explicit-any */
class Nodo {
  [prop: string]: any
  constructor(attrs: Record<string, string> = {}) {
    this.nodeType = 1
    this.attrs = attrs
    this.children = []
    this.parentNode = null
    this.style = {}
    this.dataset = {}
    this.listeners = new Map<string, Set<unknown>>()
    this.ownerDocument = (globalThis as any).document
  }
  addEventListener(tipo: string, fn: unknown) {
    if (!this.listeners.has(tipo)) this.listeners.set(tipo, new Set())
    this.listeners.get(tipo).add(fn)
  }
  removeEventListener(tipo: string, fn: unknown) { this.listeners.get(tipo)?.delete(fn) }
  getAttribute(n: string) { return this.attrs[n] ?? null }
  closest() { return null }
  querySelector(sel: string) { return this.querySelectorAll(sel)[0] ?? null }
  querySelectorAll(sel: string): Nodo[] {
    const atributo = sel.match(/\[([\w-]+)\]$/)?.[1]
    const todos: Nodo[] = []
    const recorrer = (n: Nodo) => n.children.forEach((h: Nodo) => { todos.push(h); recorrer(h) })
    recorrer(this)
    return atributo ? todos.filter((n) => atributo in n.attrs) : []
  }
  agregar(hijo: Nodo) { hijo.parentNode = this; this.children.push(hijo); return hijo }
}

const documento = new Nodo()
documento.createElement = () => ({ draggable: false, style: {} })
documento.body = new Nodo()
;(globalThis as any).document = documento
;(globalThis as any).window = Object.assign(globalThis, {
  requestAnimationFrame: (fn: () => void) => { pendientes.push(fn); return 0 },
})
const pendientes: Array<() => void> = []
const correrFrames = () => pendientes.splice(0).forEach((fn) => fn())

const tablero = documento.agregar(new Nodo({ 'data-program-board': '' }))
const lista = tablero.agregar(new Nodo({ 'data-program-lane-list': '' }))
lista.agregar(new Nodo({ 'data-program-order': '' }))
documento.querySelector = (sel: string) => (sel === '[data-program-board]' ? tablero : null)

const { initializeSortableBoard, scheduleSortableBoard } = await import('../../resources/js/urd-eng/sortable-board.ts')

const instanciaDe = (el: Nodo): any => Object.values(el).find((v: any) => v?.options?.onStart)

test('un morph a media arrastre no destruye el Sortable; soltar no truena', () => {
  initializeSortableBoard()
  const sortable = instanciaDe(lista)
  assert.ok(sortable?.nativeDraggable, 'el doble debe ir por el camino de drag nativo')

  sortable.options.onStart({ item: { dataset: { orderId: '7' } } })
  // La respuesta de setInteractionPaused(true) dispara morph.updated → scheduleSortableBoard.
  scheduleSortableBoard()
  correrFrames()

  assert.equal(sortable.el, lista, 'la instancia sigue viva durante el arrastre')
  // Soltar: el 'dragend' nativo llega al Sortable. Antes: TypeError reading 'removeEventListener'.
  assert.doesNotThrow(() => sortable.handleEvent({ type: 'dragend' }))
})

test('al terminar el arrastre se hace la reinicialización que quedó pendiente', () => {
  const sortable = instanciaDe(lista)
  sortable.options.onStart({ item: { dataset: { orderId: '7' } } })
  initializeSortableBoard()
  assert.equal(sortable.el, lista)

  sortable.options.onEnd({ newIndex: undefined, from: lista, to: lista })
  correrFrames()

  assert.equal(sortable.el, null, 'la instancia vieja se destruyó después de soltar')
  assert.notEqual(instanciaDe(lista), sortable, 'y la lista tiene una instancia nueva')
})
