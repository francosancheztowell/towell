// DOM falso un poco más completo que utils-fake-dom.mjs para montar las páginas migradas
// en la rama claude/mejora-ts-inline-mecanicos-usuarios (selectores compuestos tipo
// `tr.actividad-row.selected`, `.js-total[data-cve="7"]`, dataset, tablas, select/option, form).
// No es un archivo de test: lo importan los mejoras-inline-mecanicos-usuarios-*.test.ts.

const camelAKebab = (k) => k.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase())

function parsearSelector(sel) {
    const s = sel.trim()
    const partes = { tag: null, id: null, clases: [], attrs: [] }
    const re = /^([a-zA-Z][\w-]*)|#([\w-]+)|\.([\w:/-]+)|\[([\w-]+)(?:="([^"]*)")?\]/g
    let m
    let consumido = 0
    while ((m = re.exec(s)) !== null) {
        if (m.index !== consumido) throw new Error(`selector no soportado: ${sel}`)
        consumido = re.lastIndex
        if (m[1]) partes.tag = m[1].toUpperCase()
        else if (m[2]) partes.id = m[2]
        else if (m[3]) partes.clases.push(m[3])
        else partes.attrs.push([m[4], m[5]])
    }
    if (consumido !== s.length) throw new Error(`selector no soportado: ${sel}`)

    return partes
}

class Nodo {
    constructor() {
        this.listeners = new Map()
        this.parentNode = null
    }

    addEventListener(type, fn) {
        if (!this.listeners.has(type)) this.listeners.set(type, new Set())
        this.listeners.get(type).add(fn)
    }

    removeEventListener(type, fn) {
        this.listeners.get(type)?.delete(fn)
    }

    dispatchEvent(event) {
        event.target ??= this
        if (!event.preventDefault) {
            event.defaultPrevented = false
            event.preventDefault = () => { event.defaultPrevented = true }
        }
        for (const fn of [...(this.listeners.get(event.type) ?? [])]) fn(event)
        if (event.bubbles && this.parentNode) this.parentNode.dispatchEvent(event)

        return !event.defaultPrevented
    }
}

export class El extends Nodo {
    constructor(tag, doc) {
        super()
        this.tagName = tag.toUpperCase()
        this.ownerDocument = doc
        this.children = []
        this.attributes = {}
        this.style = {}
        this._text = ''
        this.value = ''
        this.disabled = false
        this.submits = 0
        this.resets = 0
        const self = this
        this.dataset = new Proxy({}, {
            get: (_t, k) => (typeof k === 'string' ? self.attributes['data-' + camelAKebab(k)] : undefined),
            set: (_t, k, v) => { self.attributes['data-' + camelAKebab(String(k))] = String(v); return true },
            has: (_t, k) => ('data-' + camelAKebab(String(k))) in self.attributes,
        })
        this.classList = {
            contains: (c) => self.clases().includes(c),
            add: (...cs) => { self.className = [...new Set([...self.clases(), ...cs])].join(' ') },
            remove: (...cs) => { self.className = self.clases().filter((c) => !cs.includes(c)).join(' ') },
        }
        if (this.tagName === 'SELECT') {
            Object.defineProperty(this, 'value', {
                get: () => self.valorSelect,
                set: (v) => self.options.forEach((o) => { o.selected = o.value === String(v) }),
            })
        }
    }

    clases() {
        return (this.attributes.class ?? '').split(/\s+/).filter(Boolean)
    }

    get className() { return this.attributes.class ?? '' }
    set className(v) { this.attributes.class = String(v) }
    get id() { return this.attributes.id ?? '' }
    set id(v) { this.attributes.id = String(v) }
    get action() { return this.attributes.action ?? '' }
    set action(v) { this.attributes.action = String(v) }
    get colSpan() { return Number(this.attributes.colspan ?? 1) }
    set colSpan(v) { this.attributes.colspan = String(v) }

    setAttribute(n, v) { this.attributes[n] = String(v) }
    getAttribute(n) { return this.attributes[n] ?? null }
    hasAttribute(n) { return n in this.attributes }
    removeAttribute(n) { delete this.attributes[n] }

    get textContent() {
        return this.children.length ? this.children.map((c) => c.textContent).join('') : this._text
    }

    set textContent(v) {
        this.children.forEach((c) => { c.parentNode = null })
        this.children = []
        this._text = String(v)
    }

    get innerHTML() { return this._html ?? '' }
    set innerHTML(v) { this.textContent = ''; this._html = String(v) }

    appendChild(child) {
        child.parentNode?.children && (child.parentNode.children = child.parentNode.children.filter((c) => c !== child))
        child.parentNode = this
        this.children.push(child)

        return child
    }

    append(...nodos) { nodos.forEach((n) => this.appendChild(n)) }

    replaceChildren(...nodos) {
        this.textContent = ''
        this.append(...nodos)
    }

    remove() {
        if (!this.parentNode) return
        this.parentNode.children = this.parentNode.children.filter((c) => c !== this)
        this.parentNode = null
    }

    contains(n) {
        for (let x = n; x; x = x.parentNode) if (x === this) return true

        return false
    }

    matches(selector) {
        return selector.split(',').some((parte) => {
            const p = parsearSelector(parte)
            if (p.tag && p.tag !== this.tagName) return false
            if (p.id && p.id !== this.id) return false
            if (!p.clases.every((c) => this.classList.contains(c))) return false

            return p.attrs.every(([n, v]) => (v === undefined ? n in this.attributes : this.attributes[n] === v))
        })
    }

    closest(selector) {
        for (let n = this; n instanceof El; n = n.parentNode) if (n.matches(selector)) return n

        return null
    }

    *descendants() {
        for (const c of this.children) {
            yield c
            yield* c.descendants()
        }
    }

    querySelectorAll(selector) { return [...this.descendants()].filter((e) => e.matches(selector)) }
    querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null }

    focus() { this.ownerDocument.activeElement = this }

    // <form>
    submit() { this.submits++ }
    reset() {
        this.resets++
        // Como el nativo: vuelve al valor por defecto; un hidden no cambia (su .value ES el atributo).
        this.querySelectorAll('input').forEach((i) => {
            if (i.getAttribute('type') !== 'hidden') i.value = i.defaultValue ?? ''
        })
    }

    // <table>/<tbody>/<tr>
    get rows() { return this.children.filter((c) => c.tagName === 'TR') }
    get cells() { return this.children.filter((c) => c.tagName === 'TD') }
    insertRow() { return this.appendChild(new El('tr', this.ownerDocument)) }
    insertCell() { return this.appendChild(new El('td', this.ownerDocument)) }

    // <select>
    get options() { return this.children.filter((c) => c.tagName === 'OPTION') }
    add(opcion) { this.appendChild(opcion) }
}

// value de un <select> = el de la opción seleccionada (o la primera).
Object.defineProperty(El.prototype, 'valorSelect', {
    get() {
        const ops = this.options
        return (ops.find((o) => o.selected) ?? ops[0])?.value ?? ''
    },
})

export class Doc extends Nodo {
    constructor() {
        super()
        this.readyState = 'complete'
        this.documentElement = new El('html', this)
        this.documentElement.parentNode = this
        this.head = this.documentElement.appendChild(new El('head', this))
        this.body = this.documentElement.appendChild(new El('body', this))
    }

    createElement(tag) { return new El(tag, this) }
    createElementNS(_ns, tag) { return new El(tag, this) }
    getElementById(id) { return this.querySelector('#' + id) }
    querySelector(s) { return this.documentElement.querySelector(s) }
    querySelectorAll(s) { return this.documentElement.querySelectorAll(s) }
    contains(n) { return this.documentElement.contains(n) }
}

/**
 * Instala document/window/Option/FormData falsos. `h(tag, attrs, ...hijos)` arma el árbol.
 */
export function instalar() {
    const doc = new Doc()
    globalThis.document = doc
    globalThis.window = Object.assign(new EventTarget(), { location: { reload() {} } })

    globalThis.Option = function Option(texto = '', valor = '', _def = false, sel = false) {
        const o = new El('option', globalThis.document)
        o.textContent = texto
        o.value = valor
        o.selected = Boolean(sel)
        return o
    }

    // new FormData(form): recoge los [name] con value, como el nativo (sin checkboxes).
    const FormDataNativo = globalThis.FormData
    globalThis.FormData = class extends FormDataNativo {
        constructor(form) {
            super()
            if (form instanceof El) {
                form.querySelectorAll('[name]').forEach((campo) => {
                    const v = campo.tagName === 'SELECT' ? campo.valorSelect : campo.value
                    this.append(campo.getAttribute('name'), v)
                })
            }
        }
    }

    const h = (tag, attrs = {}, ...hijos) => {
        const el = new El(tag, doc)
        for (const [k, v] of Object.entries(attrs)) {
            if (k === 'value') { el.value = v; el.defaultValue = v }
            else if (k === 'disabled' || k === 'selected') el[k] = v
            else el.setAttribute(k, v)
        }
        hijos.forEach((x) => (typeof x === 'string' ? (el.textContent = x) : el.appendChild(x)))

        return el
    }

    return { doc, h }
}

export const click = (el) => el.dispatchEvent({ type: 'click', bubbles: true })
export const tick = () => new Promise((r) => setTimeout(r, 0))
