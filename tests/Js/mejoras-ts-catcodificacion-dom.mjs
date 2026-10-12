// DOM falso un poco más completo que utils-fake-dom.mjs para las pruebas de Codificación
// (mejoras-ts-catcodificacion-*): dataset, classList completo, selectores compuestos con
// atributos y descendientes, fragmentos e innerHTML (se guarda como texto, no se parsea).
//
// También instala los módulos falsos (combobox, notificaciones, modal L.Mat) con
// module.registerHooks, y un adaptador de axios que contesta por ruta.

import { registerHooks } from 'node:module'

export class FakeNode {
    constructor() {
        this.listeners = new Map()
        this.parentNode = null
    }

    addEventListener(type, fn, options) {
        if (!this.listeners.has(type)) this.listeners.set(type, [])
        this.listeners.get(type).push({ fn, once: Boolean(options && options.once) })
    }

    removeEventListener(type, fn) {
        const lista = this.listeners.get(type)
        if (!lista) return
        this.listeners.set(type, lista.filter((l) => l.fn !== fn))
    }

    dispatchEvent(event) {
        // Event/CustomEvent nativos (los que emite http.ts) tienen target de solo lectura.
        if (!(event instanceof Event)) {
            if (!event.target) event.target = this
            event.currentTarget = this
        }
        for (const l of [...(this.listeners.get(event.type) ?? [])]) {
            if (l.once) this.removeEventListener(event.type, l.fn)
            l.fn.call(this, event)
        }
        if (event.bubbles && !event._stop && this.parentNode) this.parentNode.dispatchEvent(event)

        return !event.defaultPrevented
    }
}

/** Evento plano que burbujea, con preventDefault/stopPropagation. */
export function evento(type, extra = {}) {
    return {
        type,
        bubbles: true,
        defaultPrevented: false,
        _stop: false,
        preventDefault() {
            this.defaultPrevented = true
        },
        stopPropagation() {
            this._stop = true
        },
        ...extra,
    }
}

const kebab = (camel) => camel.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase())

// ── Selectores: grupos por coma, descendientes por espacio, compuestos tag#id.cls[attr="v"]:checked ──
function parseCompuesto(texto) {
    const c = { tag: null, id: null, clases: [], attrs: [], checked: false }
    const re = /^([a-zA-Z][\w-]*|\*)|#([\w-]+)|\.([\w-]+)|\[([\w-]+)(?:="([^"]*)")?\]|:checked/g
    let m
    let consumido = 0
    while ((m = re.exec(texto)) !== null) {
        if (m.index !== consumido) throw new Error('Selector no soportado: ' + texto)
        consumido = re.lastIndex
        if (m[1]) c.tag = m[1] === '*' ? null : m[1].toUpperCase()
        else if (m[2]) c.id = m[2]
        else if (m[3]) c.clases.push(m[3])
        else if (m[4]) c.attrs.push([m[4], m[5]])
        else if (m[0] === ':checked') c.checked = true
    }
    if (consumido !== texto.length) throw new Error('Selector no soportado: ' + texto)

    return c
}

function cumple(el, c) {
    if (!(el instanceof FakeElement)) return false
    if (c.tag && el.tagName !== c.tag) return false
    if (c.id && el.id !== c.id) return false
    for (const cls of c.clases) if (!el.classList.contains(cls)) return false
    for (const [nombre, valor] of c.attrs) {
        const actual = el.getAttribute(nombre)
        if (actual === null) return false
        if (valor !== undefined && actual !== valor) return false
    }
    if (c.checked && !el.checked) return false

    return true
}

function cumpleGrupo(el, partes) {
    if (!cumple(el, partes[partes.length - 1])) return false
    let i = partes.length - 2
    for (let n = el.parentNode; n && i >= 0; n = n.parentNode) {
        if (cumple(n, partes[i])) i--
    }

    return i < 0
}

function compilar(selector) {
    return selector.split(',').map((g) => g.trim().split(/\s+/).map(parseCompuesto))
}

export class FakeElement extends FakeNode {
    constructor(tagName, ownerDocument) {
        super()
        this.tagName = tagName.toUpperCase()
        this.nodeType = 1
        this.ownerDocument = ownerDocument
        this.children = []
        this._attrs = new Map()
        this._text = ''
        this._html = ''
        this.style = {}
        this.value = ''
        this.disabled = false
        this.checked = false
        this.title = ''
        this.selectedIndex = 0
        const self = this
        this.dataset = new Proxy(
            {},
            {
                get: (_t, k) => (typeof k === 'string' ? (self.getAttribute('data-' + kebab(k)) ?? undefined) : undefined),
                set: (_t, k, v) => {
                    self.setAttribute('data-' + kebab(String(k)), String(v))

                    return true
                },
                has: (_t, k) => self.hasAttribute('data-' + kebab(String(k))),
                deleteProperty: (_t, k) => {
                    self.removeAttribute('data-' + kebab(String(k)))

                    return true
                },
            },
        )
    }

    get id() {
        return this._attrs.get('id') ?? ''
    }

    set id(v) {
        this._attrs.set('id', String(v))
    }

    get className() {
        return this._attrs.get('class') ?? ''
    }

    set className(v) {
        this._attrs.set('class', String(v))
    }

    get classList() {
        const el = this
        const lista = () => el.className.split(/\s+/).filter(Boolean)
        const guardar = (arr) => {
            el.className = [...new Set(arr)].join(' ')
        }

        return {
            contains: (n) => lista().includes(n),
            add: (...n) => guardar([...lista(), ...n]),
            remove: (...n) => guardar(lista().filter((c) => !n.includes(c))),
            toggle: (n, force) => {
                const poner = force === undefined ? !lista().includes(n) : Boolean(force)
                if (poner) guardar([...lista(), n])
                else guardar(lista().filter((c) => c !== n))

                return poner
            },
        }
    }

    get textContent() {
        return this._text + this.children.map((c) => c.textContent).join('')
    }

    set textContent(v) {
        this._text = String(v)
        this._html = ''
        this.children = []
    }

    get innerHTML() {
        return this._html
    }

    set innerHTML(v) {
        this._html = String(v)
        this._text = ''
        this.children = []
    }

    get options() {
        return this.querySelectorAll('option')
    }

    get firstElementChild() {
        return this.children[0] ?? null
    }

    setAttribute(n, v) {
        this._attrs.set(n, String(v))
    }

    getAttribute(n) {
        return this._attrs.has(n) ? this._attrs.get(n) : null
    }

    hasAttribute(n) {
        return this._attrs.has(n)
    }

    removeAttribute(n) {
        this._attrs.delete(n)
    }

    appendChild(child) {
        if (child instanceof FakeFragment) {
            for (const c of [...child.children]) this.appendChild(c)

            return child
        }
        child.remove()
        child.parentNode = this
        this.children.push(child)

        return child
    }

    add(option) {
        return this.appendChild(option)
    }

    removeChild(child) {
        child.remove()

        return child
    }

    remove() {
        if (!this.parentNode) return
        const hermanos = this.parentNode.children
        hermanos.splice(hermanos.indexOf(this), 1)
        this.parentNode = null
    }

    contains(node) {
        for (let n = node; n; n = n.parentNode) if (n === this) return true

        return false
    }

    matches(selector) {
        return compilar(selector).some((partes) => cumpleGrupo(this, partes))
    }

    closest(selector) {
        const grupos = compilar(selector)
        for (let n = this; n instanceof FakeElement; n = n.parentNode) {
            if (grupos.some((p) => cumpleGrupo(n, p))) return n
        }

        return null
    }

    *descendientes() {
        for (const c of this.children) {
            yield c
            yield* c.descendientes()
        }
    }

    querySelectorAll(selector) {
        const grupos = compilar(selector)

        return [...this.descendientes()].filter((el) => grupos.some((p) => cumpleGrupo(el, p)))
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] ?? null
    }

    click() {
        this.dispatchEvent(evento('click'))
    }

    focus() {}
}

class FakeFragment extends FakeElement {
    constructor(doc) {
        super('#fragment', doc)
    }
}

export class FakeDocument extends FakeNode {
    constructor() {
        super()
        this.readyState = 'complete'
        this.documentElement = new FakeElement('html', this)
        this.documentElement.parentNode = this
        this.head = this.documentElement.appendChild(new FakeElement('head', this))
        this.body = this.documentElement.appendChild(new FakeElement('body', this))
    }

    createElement(tag) {
        return new FakeElement(tag, this)
    }

    createDocumentFragment() {
        return new FakeFragment(this)
    }

    getElementById(id) {
        return [...this.documentElement.descendientes()].find((el) => el.id === id) ?? null
    }

    querySelector(selector) {
        return this.documentElement.querySelector(selector)
    }

    querySelectorAll(selector) {
        return this.documentElement.querySelectorAll(selector)
    }

    contains(node) {
        return this.documentElement.contains(node)
    }
}

/** Crea un elemento: el('tr', { class: 'x', 'data-orden': '1' }, [hijos]). */
export function el(doc, tag, attrs = {}, hijos = []) {
    const e = doc.createElement(tag)
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'value' || k === 'checked' || k === 'disabled' || k === 'textContent') e[k] = v
        else e.setAttribute(k, v)
    }
    for (const h of hijos) e.appendChild(h)

    return e
}

/** Instala document y window falsos. Devuelve { document, window, registro }. */
export function instalarEntorno() {
    const document = new FakeDocument()
    const window = new FakeNode()
    const registro = { toasts: [], recargas: 0, combobox: [], dialogos: [], lmat: [], urls: [], loading: 0, cerrar: 0 }
    window.location = { reload: () => registro.recargas++ }
    window.URL = { createObjectURL: () => 'blob:fake', revokeObjectURL: () => {} }
    globalThis.document = document
    globalThis.window = window
    globalThis.__registroCat = registro
    // Respuestas de diálogos: cola de valores que devolverá notify.dialog.
    registro.respuestasDialogo = []

    return { document, window, registro }
}

// ── Módulos falsos (combobox con Tom Select y toasts necesitan un navegador real) ──
const STUB_COMBOBOX = `
export function combobox(select, opciones) { globalThis.__registroCat.combobox.push([select, opciones]); }
export function destruirCombobox() {}
export function refrescarCombobox() {}
`

const STUB_NOTIFICACIONES = `
const r = () => globalThis.__registroCat;
export function showToast(message, type = 'success') { r().toasts.push([String(message), type]); }
const aviso = (tipo) => (msg) => r().toasts.push([String(msg), tipo]);
export const notify = {
    success: aviso('success'), error: aviso('error'), warning: aviso('warning'), info: aviso('info'),
    loading() { r().loading++; }, update() {}, close() { r().cerrar++; },
    dialog(opciones) { r().dialogos.push(opciones); return Promise.resolve(r().respuestasDialogo.shift() ?? null); },
    form(opciones) { r().dialogos.push(opciones); return Promise.resolve(null); },
    html(html, titulo) { r().dialogos.push({ html, titulo }); return Promise.resolve(); },
    alert(texto, titulo) { r().dialogos.push({ texto, titulo }); return Promise.resolve(); },
    confirm(opciones) { r().dialogos.push(opciones); return Promise.resolve(false); },
    validation() {},
};
export default notify;
`

const STUB_LMAT_MODAL = `
export async function openLMatModal(contexto) { globalThis.__registroCat.lmat.push(contexto); }
`

/** Redirige los módulos indicados (combobox, notifications, lmat-modal) a los falsos. */
export function instalarStubs({ lmatModal = false } = {}) {
    const stubs = [
        ['/resources/js/utils/combobox.ts', STUB_COMBOBOX],
        ['/resources/js/utils/notifications.ts', STUB_NOTIFICACIONES],
    ]
    if (lmatModal) stubs.push(['/resources/js/catcodificacion/lmat-modal.ts', STUB_LMAT_MODAL])
    registerHooks({
        resolve(specifier, context, next) {
            const resuelto = next(specifier, context)
            const stub = stubs.find(([fin]) => resuelto.url.endsWith(fin))
            if (stub) return { url: 'data:text/javascript,' + encodeURIComponent(stub[1]), format: 'module', shortCircuit: true }

            return resuelto
        },
    })
}

/** Deja que corran promesas y timers 0 pendientes. */
export async function esperar(veces = 5) {
    for (let i = 0; i < veces; i++) await new Promise((r) => setImmediate(r))
}
