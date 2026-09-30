// DOM mínimo para probar resources/js/utils en node (no hay jsdom en el proyecto).
// Solo implementa lo que usan utils/dom.ts y utils/notifications.ts.

// Eventos propios (no EventTarget de node): así el burbujeo conserva event.target.
class FakeNode {
    constructor() {
        this.listeners = new Map()
    }

    addEventListener(type, fn) {
        if (!this.listeners.has(type)) this.listeners.set(type, new Set())
        this.listeners.get(type).add(fn)
    }

    removeEventListener(type, fn) {
        this.listeners.get(type)?.delete(fn)
    }

    listenerCount(type) {
        return this.listeners.get(type)?.size ?? 0
    }

    /** `event` es un objeto plano { type, bubbles? }; se le asigna target. */
    dispatchEvent(event) {
        event.target ??= this
        for (const fn of [...(this.listeners.get(event.type) ?? [])]) fn(event)
        if (event.bubbles && this.parentNode) this.parentNode.dispatchEvent(event)

        return true
    }
}

class FakeElement extends FakeNode {
    constructor(tagName, ownerDocument) {
        super()
        this.tagName = tagName.toUpperCase()
        this.ownerDocument = ownerDocument
        this.parentNode = null
        this.children = []
        this.attributes = {}
        this.className = ''
        this.id = ''
        this.type = ''
        this._text = ''
    }

    get textContent() {
        return this._text + this.children.map((c) => c.textContent).join('')
    }

    set textContent(value) {
        this._text = String(value)
        this.children = []
    }

    get firstElementChild() {
        return this.children[0] ?? null
    }

    get classList() {
        return { contains: (name) => this.className.split(/\s+/).includes(name) }
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value)
        if (name === 'id') this.id = String(value)
        if (name === 'class') this.className = String(value)
    }

    getAttribute(name) {
        return name in this.attributes ? this.attributes[name] : null
    }

    appendChild(child) {
        child.remove()
        child.parentNode = this
        this.children.push(child)

        return child
    }

    remove() {
        if (!this.parentNode) return
        const siblings = this.parentNode.children
        siblings.splice(siblings.indexOf(this), 1)
        this.parentNode = null
    }

    contains(node) {
        for (let n = node; n; n = n.parentNode) if (n === this) return true

        return false
    }

    matches(selector) {
        return selector.split(',').some((part) => {
            const s = part.trim()
            if (s.startsWith('.')) return this.classList.contains(s.slice(1))
            if (s.startsWith('#')) return this.id === s.slice(1)
            const attr = /^\[([\w-]+)\]$/.exec(s)
            if (attr) return attr[1] in this.attributes

            return this.tagName === s.toUpperCase()
        })
    }

    closest(selector) {
        for (let n = this; n instanceof FakeElement; n = n.parentNode) if (n.matches(selector)) return n

        return null
    }

    *descendants() {
        for (const child of this.children) {
            yield child
            yield* child.descendants()
        }
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] ?? null
    }

    querySelectorAll(selector) {
        return [...this.descendants()].filter((el) => el.matches(selector))
    }
}

export class FakeDocument extends FakeNode {
    constructor() {
        super()
        this.parentNode = null
        this.readyState = 'complete'
        this.documentElement = new FakeElement('html', this)
        // Los eventos que burbujean llegan hasta el documento, como en el navegador.
        this.documentElement.parentNode = this
        this.head = this.documentElement.appendChild(new FakeElement('head', this))
        this.body = this.documentElement.appendChild(new FakeElement('body', this))
    }

    createElement(tag) {
        return new FakeElement(tag, this)
    }

    getElementById(id) {
        return [...this.documentElement.descendants()].find((el) => el.id === id) ?? null
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

/** Instala un documento falso en globalThis. Eventos: `el.dispatchEvent({ type: 'click', bubbles: true })`. */
export function installFakeDom() {
    const document = new FakeDocument()
    globalThis.document = document

    return document
}
