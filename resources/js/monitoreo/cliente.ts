/*
 * Telemetría cliente (fase 12, contrato 11-CONTRACT.md §4): latido, vistas con
 * tiempos de carga, errores del navegador y estado de conexión.
 *
 * Reglas: nunca rompe la página (todo en try/catch), nunca parchea window.fetch,
 * nunca se reporta a sí misma (los fallos de /telemetria/* se tragan) y sin el
 * meta `towell-telemetria` no hace ninguna request (kill switch del servidor).
 *
 * Sin imports a propósito: los tests node importan este .ts directo.
 */

export type Post = (url: string, body: unknown) => Promise<unknown>

type Fallo = { status: number, url?: string, method?: string }

type LivewireHook = (name: 'request', callback: (request: {
  url?: string
  fail: (callback: (failure: { status: number }) => void) => void
}) => void) => void

type Win = Window & typeof globalThis & {
  Livewire?: { hook?: LivewireHook }
  __towellTelemetria?: boolean
}

export type Opciones = {
  post: Post
  win?: Win
  recargar?: () => void
}

export type Telemetria = {
  latir: () => Promise<void>
  vista: () => string
  detener: () => void
}

const BASE = '/telemetria'
const MAX_ERRORES = 10
const STACK_MAX = 8000
const INTERACCIONES = ['pointerdown', 'keydown', 'touchstart', 'scroll']

const entero = (n: unknown): number | undefined =>
  typeof n === 'number' && Number.isFinite(n) && n >= 0 ? Math.round(n) : undefined

/** UUID v4. `crypto.randomUUID` solo existe en contexto seguro y la LAN sirve HTTP. */
export const uuid = (c: Partial<Crypto> | undefined = globalThis.crypto): string => {
  if (typeof c?.randomUUID === 'function') {
    return c.randomUUID()
  }

  const b = new Uint8Array(16)
  if (typeof c?.getRandomValues === 'function') {
    c.getRandomValues(b)
  } else {
    for (let i = 0; i < 16; i++) b[i] = Math.floor(Math.random() * 256)
  }
  b[6] = (b[6]! & 0x0f) | 0x40
  b[8] = (b[8]! & 0x3f) | 0x80
  const h = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('')

  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`
}

/** Navigation Timing L2 + Server-Timing (`app;dur=…, db;dur=…;desc="<n> q"`). */
export const tiempos = (e: PerformanceNavigationTiming | undefined) => {
  if (!e) {
    return {}
  }

  const st: Record<string, number | undefined> = {}
  for (const s of e.serverTiming ?? []) {
    if (s.name === 'app') st.app = entero(s.duration)
    if (s.name === 'db') {
      st.db = entero(s.duration)
      st.q = entero(Number.parseInt(s.description, 10))
    }
  }

  return {
    nav: {
      ttfb: entero(e.responseStart),
      dom: entero(e.domContentLoadedEventEnd),
      carga: entero(e.loadEventEnd),
      kb: entero((e.transferSize ?? 0) / 1024),
    },
    st,
  }
}

/** Ruido que no es del ERP: extensiones, scripts de otro origen y el aviso de ResizeObserver. */
export const ignorable = (mensaje: string, fuente = ''): boolean =>
  /^Script error\.?$/.test(mensaje)
  || mensaje.includes('ResizeObserver loop')
  || /^(chrome|moz|safari(-web)?)-extension:/.test(fuente)

/** Status de un error de axios o de utils/http; undefined si no es HTTP. */
const statusDe = (x: unknown): number | undefined => {
  const o = x as { status?: unknown, response?: { status?: unknown } } | null
  const s = o?.response?.status ?? o?.status

  return typeof s === 'number' ? s : undefined
}

export function iniciar(op: Opciones): Telemetria | null {
  const win = op.win ?? (window as Win)
  const doc = win.document
  const meta = (nombre: string): string =>
    doc.querySelector<HTMLMetaElement>(`meta[name="${nombre}"]`)?.content ?? ''

  if (meta('towell-telemetria') !== '1' || win.__towellTelemetria) {
    return null
  }
  win.__towellTelemetria = true

  const nav = win.navigator
  const recargar = op.recargar ?? (() => win.location.reload())
  const ahora = () => Date.now()
  const visible = () => doc.visibilityState !== 'hidden'
  const path = (url: string): string => {
    try {
      return new URL(url, win.location.href).pathname
    } catch {
      return ''
    }
  }
  const enviar = (ruta: string, body: unknown): Promise<unknown> => op.post(BASE + ruta, body)

  // --- Conexión (MON-18) ---
  let online = nav.onLine !== false
  const conexion = (on: boolean): void => {
    if (on === online) return
    online = on
    win.dispatchEvent(new CustomEvent('towell:conexion', { detail: { online: on } }))
  }

  // --- Vistas (MON-16) ---
  let vista = ''
  let visibleMs = 0
  let visibleDesde: number | null = null

  const msVisibles = (): number => visibleMs + (visibleDesde === null ? 0 : ahora() - visibleDesde)

  const abrirVista = (tipo: 'carga' | 'suave'): void => {
    vista = uuid(win.crypto)
    visibleMs = 0
    visibleDesde = visible() ? ahora() : null
    const body = {
      uuid: vista,
      tipo,
      ruta: meta('towell-ruta'),
      url: win.location.pathname,
      ...(tipo === 'carga' ? tiempos(win.performance.getEntriesByType?.('navigation')[0] as PerformanceNavigationTiming | undefined) : {}),
    }
    enviar('/vista', body).catch(() => {})
  }

  const cerrarVista = (): void => {
    if (!vista) return
    const fd = new FormData()
    fd.append('_token', meta('csrf-token'))
    fd.append('visibleMs', String(msVisibles()))
    try {
      nav.sendBeacon?.(`${BASE}/vista/${vista}/fin`, fd)
    } catch {
      // sendBeacon puede lanzar si el navegador rechaza el tamaño: nada que hacer.
    }
    vista = ''
  }

  // --- Latido (MON-15) ---
  let ultimaInteraccion = ahora()
  let intervaloSeg = 60
  let timer: number | undefined
  let detenido = false
  let saliendo = false

  const programar = (): void => {
    win.clearTimeout(timer)
    if (detenido) return
    timer = win.setTimeout(() => void latir(), Math.min(900, Math.max(15, intervaloSeg)) * 1000)
  }

  const latir = async (): Promise<void> => {
    programar()
    try {
      const r = await enviar('/latido', {
        vista: vista || undefined,
        ruta: meta('towell-ruta'),
        visible: visible(),
        inactivoSeg: Math.floor((ahora() - ultimaInteraccion) / 1000),
        version: meta('towell-version') || undefined,
        pantalla: `${win.screen?.width ?? 0}x${win.screen?.height ?? 0}`,
      }) as { cerrar?: boolean, intervalo?: number } | null
      conexion(true)
      if (r?.cerrar) {
        recargar()
        return
      }
      if (typeof r?.intervalo === 'number' && r.intervalo > 0) {
        intervaloSeg = r.intervalo
        programar()
      }
    } catch (e) {
      const status = statusDe(e)
      if (status === undefined || status === 0) {
        conexion(false)
      } else if (status === 401 || status === 419) {
        // Cierre remoto (401, contrato §5) o sesión vencida (419 de CSRF): la recarga cae en el login.
        recargar()
      }
    }
  }

  // --- Errores (MON-17) ---
  const vistos = new Set<string>()

  const reportar = (datos: {
    origen: 'js' | 'livewire' | 'red'
    mensaje: string
    fuente?: string | undefined
    linea?: number | undefined
    col?: number | undefined
    stack?: string | undefined
    status?: number | undefined
    metodo?: string | undefined
  }): void => {
    const clave = `${datos.mensaje}|${datos.fuente ?? ''}`
    if (!datos.mensaje || vistos.size >= MAX_ERRORES || vistos.has(clave)) return
    vistos.add(clave)
    enviar('/error', {
      ...datos,
      stack: datos.stack?.slice(0, STACK_MAX),
      url: win.location.pathname,
      // Se lee al reportar: la navegación suave de Livewire cambia los <meta> antes de livewire:navigated.
      ruta: meta('towell-ruta') || undefined,
      vista: vista || undefined,
      version: meta('towell-version') || undefined,
    }).catch(() => {})
  }

  /** De HTTP solo interesa red caída (0, con navegador en línea) y 5xx; los 4xx son flujo normal. */
  const reportarFallo = (origen: 'red' | 'livewire', f: Fallo): void => {
    const url = path(f.url ?? '')
    const status = Number(f.status) || 0
    if (url.startsWith(BASE + '/') || (status > 0 && status < 500) || (status === 0 && nav.onLine === false)) return
    const metodo = f.method?.toUpperCase()
    reportar({
      origen,
      mensaje: `${origen === 'livewire' ? 'Livewire' : 'HTTP'} ${status}${metodo ? ` ${metodo}` : ''} ${url}`.trim(),
      fuente: url || undefined,
      status,
      metodo,
    })
  }

  const alError = (e: ErrorEvent): void => {
    const mensaje = e.message || String(e.error ?? '')
    const fuente = e.filename || ''
    if (ignorable(mensaje, fuente)) return
    reportar({
      origen: 'js',
      mensaje,
      fuente: fuente || undefined,
      linea: e.lineno || undefined,
      col: e.colno || undefined,
      stack: e.error instanceof Error ? e.error.stack : undefined,
    })
  }

  const alRechazo = (e: PromiseRejectionEvent): void => {
    const r: unknown = e.reason
    const status = statusDe(r)
    // Errores HTTP ya los cubre towell:http-error / Livewire; aquí solo excepciones de JS.
    if (status !== undefined) return
    const mensaje = r instanceof Error ? r.message || r.name : String(r)
    if (ignorable(mensaje)) return
    reportar({ origen: 'js', mensaje, stack: r instanceof Error ? r.stack : undefined })
  }

  let livewireInstalado = false
  const instalarLivewire = (): void => {
    const lw = win.Livewire
    if (livewireInstalado || typeof lw?.hook !== 'function') return
    livewireInstalado = true
    lw.hook('request', ({ url, fail }) => {
      fail(({ status }) => reportarFallo('livewire', { status, url: url ?? '', method: 'POST' }))
    })
  }

  // --- Listeners ---
  const alInteractuar = (): void => {
    ultimaInteraccion = ahora()
  }
  const alVisibilidad = (): void => {
    if (visible()) {
      visibleDesde ??= ahora()
    } else if (visibleDesde !== null) {
      visibleMs += ahora() - visibleDesde
      visibleDesde = null
    }
    // Al navegar, el `hidden` llega después del pagehide: la página ya se fue.
    if (!saliendo) void latir()
  }
  const alSalir = (): void => {
    saliendo = true
    cerrarVista()
  }
  const alNavegar = (): void => {
    // El primer livewire:navigated llega con la carga inicial, antes del `load`.
    if (!vista) return
    cerrarVista()
    abrirVista('suave')
  }
  const alMostrar = (e: PageTransitionEvent): void => {
    // Vuelta desde bfcache: la página no se recargó, pero es una vista nueva.
    if (!e.persisted) return
    saliendo = false
    abrirVista('suave')
    void latir()
  }
  const alHttpError = (e: Event): void => reportarFallo('red', (e as CustomEvent<Fallo>).detail ?? { status: 0 })
  const alOnline = (): void => void latir()
  const alOffline = (): void => conexion(false)

  const opt = { passive: true, capture: true }
  for (const t of INTERACCIONES) win.addEventListener(t, alInteractuar, opt)
  doc.addEventListener('visibilitychange', alVisibilidad)
  doc.addEventListener('livewire:navigated', alNavegar)
  doc.addEventListener('livewire:init', instalarLivewire)
  win.addEventListener('pagehide', alSalir)
  win.addEventListener('pageshow', alMostrar as EventListener)
  win.addEventListener('error', alError)
  win.addEventListener('unhandledrejection', alRechazo)
  win.addEventListener('towell:http-error', alHttpError)
  win.addEventListener('online', alOnline)
  win.addEventListener('offline', alOffline)
  instalarLivewire()

  const arrancar = (): void => {
    // Un tick después del `load` para que loadEventEnd ya tenga valor.
    win.setTimeout(() => {
      if (detenido) return
      abrirVista('carga')
      void latir()
    }, 0)
  }
  if (doc.readyState === 'complete') {
    arrancar()
  } else {
    win.addEventListener('load', arrancar, { once: true })
  }

  return {
    latir,
    vista: () => vista,
    detener: () => {
      detenido = true
      win.clearTimeout(timer)
      for (const t of INTERACCIONES) win.removeEventListener(t, alInteractuar, opt)
      doc.removeEventListener('visibilitychange', alVisibilidad)
      doc.removeEventListener('livewire:navigated', alNavegar)
      doc.removeEventListener('livewire:init', instalarLivewire)
      win.removeEventListener('pagehide', alSalir)
      win.removeEventListener('pageshow', alMostrar as EventListener)
      win.removeEventListener('error', alError)
      win.removeEventListener('unhandledrejection', alRechazo)
      win.removeEventListener('towell:http-error', alHttpError)
      win.removeEventListener('online', alOnline)
      win.removeEventListener('offline', alOffline)
      win.__towellTelemetria = false
    },
  }
}
