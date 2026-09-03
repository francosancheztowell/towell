import {
  auditBelongsToScope,
  isCurrentAuditHistoryRequest,
  shouldSkipAuditHistoryLoad,
} from './audit-history-state'
import { AuditHistoryRequestCoordinator } from './audit-history-request'
import { withBusyRetry } from './busy-retry'
import { auditActionAvailability } from './audit-form-state'
import { hidePendingDetail, isIntentionalMachineActivation } from './pending-detail'

type Machine = {
  telar: string
  name: string
  salon: string
  group: string
  sequence: number | null
  captureCount: number
  pieces: number
  seconds: number
  kilos: number
  qualityPercent: number
  secondsPercent: number
  efficiencyPercent: number
  efficiencyObs: string
  rpm?: number | null
  expectedKilos: number
  state: string
  stateLabel: string
  stateIcon: string
  paro: Record<string, string | null> | null
  programa: Record<string, string | number | null> | null
}

type QualityDefectCatalogItem = {
  Id?: unknown
  Falla?: unknown
  Descripcion?: unknown
  Departamento?: unknown
}

type QualityDefectsResponse = {
  success?: boolean
  data?: QualityDefectCatalogItem[]
  error?: string
}

type QualityDefectOption = {
  value: string
  label: string
}

type AuditChecklistKey = 'alineacion_orden' | 'dibujo_jacquard' | 'identificacion_julio'

type AuditPayload = {
  no_telar_id: string
  salon: string
  orden_trabajo: string | null
  checklist: Record<AuditChecklistKey, boolean | null>
  marbetes: number | null
  observaciones: string | null
  defectos: Array<{
    defecto_id: number
    piezas: number
  }>
}

type AuditSaveResponse = {
  success?: boolean
  message?: string
  error?: string
  errors?: Record<string, string[]>
}

type AuditHistoryItem = {
  id: number
  fecha: string | null
  hora: string | null
  telar: string
  orden: string | null
  turno: number
  auditor: string
  checklist: Array<{
    key: string
    label: string
    resultado: 'bien' | 'mal' | 'sin_evaluar'
  }>
  observaciones: string | null
  defectos: Array<{
    id: number
    falla: string
    descripcion: string | null
    piezas: number
    principal: boolean
  }>
  paro: {
    id: number
    folio: string | null
    falla: string | null
    estatus: string | null
  } | null
}

type AuditHistoryResponse = {
  data?: AuditHistoryItem[]
  meta?: {
    fecha?: string
    total?: number
  }
  message?: string
}

type LivewireRequestHook = (request: {
  url: string
  succeed?: (callback: () => void) => void
  fail: (callback: (failure: {
    status: number
    content: unknown
    preventDefault: () => void
  }) => void) => void
}) => void

type HttpError = Error & { status?: number, data?: unknown, errors?: Record<string, string[]> | null }

type CrudoHttpConfig = { signal?: AbortSignal, responseType?: 'blob' }

type CrudoHttp = {
  get: <T = unknown>(url: string, config?: CrudoHttpConfig) => Promise<T>
  post: <T = unknown>(url: string, data?: unknown, config?: CrudoHttpConfig) => Promise<T>
}

type CrudoWindow = Window & typeof globalThis & {
  Livewire?: {
    dispatch: (event: string) => void
    hook?: (name: 'request', callback: LivewireRequestHook) => void
  }
  http?: CrudoHttp
  notify?: CrudoNotify
}

type CrudoNotify = {
  loading: (title?: string) => void
  close: () => void
  success: (message: string) => void
  error: (message: string) => void
}

const DASHBOARD_SELECTOR = '[data-crudo-dashboard]'
const CRUDO_ROOT_SELECTOR = '[data-crudo-root]'
const MACHINE_DATA_SELECTOR = '[data-crudo-machines-data]'
const MACHINE_SELECTOR = '[data-crudo-machine]'
const RELATIVE_TIME_SELECTOR = '[data-crudo-relative-time]'
const AUDIT_DEFECT_EDITOR_SELECTOR = '[data-crudo-audit-defects]'
const AUDIT_CONTENT_SELECTOR = '[data-crudo-audit-content]'
const AUDIT_FORM_SELECTOR = '[data-crudo-audit-form]'
const AUDIT_FEEDBACK_SELECTOR = '[data-crudo-audit-feedback]'
const AUDIT_HISTORY_SELECTOR = '[data-crudo-audit-history]'
const AUDIT_HISTORY_LIST_SELECTOR = '[data-crudo-audit-history-list]'
const AUDIT_HISTORY_COUNT_SELECTOR = '[data-crudo-audit-history-count]'
const QUALITY_DEFECT_SELECT_SELECTOR = '[data-crudo-quality-defect-select]'
const PENDING_DETAIL_SELECTOR = '[data-crudo-detail-pending]'
const CRUDO_MODAL_SELECTOR = '[data-crudo-modal]'
const FOCUSABLE_SELECTOR = 'a[href], button:not([disabled]), textarea:not([disabled]), '
  + 'input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
const LIVEWIRE_ERROR_SELECTOR = '[data-crudo-livewire-error]'

const formatInteger = (value: number): string => Math.round(value).toLocaleString('es-MX')

let machinesByTelar = new Map<string, Machine>()
let machineButtonsByTelar = new Map<string, HTMLElement>()
let pointerMachineTelar: string | null = null
let pendingMachineButton: HTMLElement | null = null
let pendingDetailTimer: number | null = null
let observedDashboard: HTMLElement | null = null
let mutationObserver: MutationObserver | null = null
let auditDefectObserver: MutationObserver | null = null
let observedAuditRoot: HTMLElement | null = null
let relativeTimeTimer: number | null = null
let livewireErrorHookInstalled = false
const qualityDefectRequests = new Map<string, Promise<QualityDefectOption[]>>()
const auditHistoryRequests = new AuditHistoryRequestCoordinator<HTMLElement>()

// Livewire pausa el poll en cuanto la directiva desaparece del elemento
// (`theDirectiveIsOffTheElement`). Sin esto, un endpoint muerto sigue
// disparando un 404 cada `poll_seconds` hasta que alguien recarga a mano.
const stopDashboardPolling = (): void => {
  const dashboard = document.querySelector<HTMLElement>(DASHBOARD_SELECTOR)
  dashboard
    ?.getAttributeNames()
    .filter((name) => name.startsWith('wire:poll'))
    .forEach((name) => dashboard.removeAttribute(name))
}

const LIVEWIRE_ENDPOINT = '/livewire/update'

let fetchRetryInstalled = false

const installBusyServerRetry = (): void => {
  if (fetchRetryInstalled || typeof window.fetch !== 'function') {
    return
  }

  // El reintento asume que un 503 significa "el servidor no ejecutó nada",
  // cierto solo con el servidor de desarrollo (`artisan serve`, un solo
  // worker). En producción (Apache/PHP-FPM) un 503 puede llegar después de
  // que la acción sí corrió, y reintentar la reenviaría por segunda vez.
  const root = document.querySelector<HTMLElement>(CRUDO_ROOT_SELECTOR)
  if (!root || !('crudoRetryBusy' in root.dataset)) {
    return
  }

  fetchRetryInstalled = true
  window.fetch = withBusyRetry(
    window.fetch.bind(window),
    (url) => url.includes(LIVEWIRE_ENDPOINT),
  )
}

/*
 * Modo kiosco: el andón corre en una pantalla que nadie toca, así que ningún
 * fallo puede quedarse esperando un clic en "Recargar". Ante un error fatal
 * (419 sesión/CSRF, 404 snapshot muerto) o varios fallos seguidos, la página
 * se recarga sola y se recupera sin intervención.
 */
let kioskReloadTimer: number | null = null
let consecutiveFailures = 0

// Recargar sin red dejaría la página de error del navegador, sin JS que la
// reviva jamás. Si no hay conexión, la recarga espera al evento `online`.
const safeReload = (): void => {
  if (navigator.onLine) {
    window.location.reload()
    return
  }

  window.addEventListener('online', () => window.location.reload(), { once: true })
}

const scheduleKioskReload = (delayMs: number): void => {
  if (kioskReloadTimer !== null) {
    return
  }

  kioskReloadTimer = window.setTimeout(safeReload, delayMs)
}

const showLivewireError = (status: number, url: string): void => {
  // Un 404 en /livewire-<hash>/update significa endpoint muerto: el snapshot
  // de esta pestaña ya no es válido y solo se recupera recargando.
  if (status === 404) {
    stopDashboardPolling()
    scheduleKioskReload(8_000)
  }

  const alert = document.querySelector<HTMLElement>(LIVEWIRE_ERROR_SELECTOR)
  if (!alert) {
    return
  }

  const message = alert.querySelector<HTMLElement>('[data-crudo-livewire-error-message]')
  if (message) {
    message.textContent = status === 404
      ? 'La conexión de Livewire cambió. Recarga esta pantalla para continuar.'
      : 'No fue posible actualizar el tablero. Conservamos la información visible.'
  }

  alert.dataset.status = String(status)
  alert.dataset.url = url
  alert.hidden = false
}

const installLivewireErrorHandler = (): void => {
  if (livewireErrorHookInstalled || !document.querySelector(DASHBOARD_SELECTOR)) {
    return
  }

  const livewire = (window as CrudoWindow).Livewire
  if (typeof livewire?.hook !== 'function') {
    return
  }

  livewireErrorHookInstalled = true
  livewire.hook('request', ({ url, succeed, fail }) => {
    if (!document.querySelector(DASHBOARD_SELECTOR)) {
      return
    }

    succeed?.(() => {
      consecutiveFailures = 0
    })

    fail(({ status, preventDefault }) => {
      // El backdrop se marca "is-closing" al pedir el cierre de un modal y se
      // limpia cuando Livewire quita el nodo del DOM. Si esa petición falla,
      // el nodo se queda y el backdrop se veía "cursor: wait" para siempre.
      document.querySelectorAll('.is-closing').forEach((element) => element.classList.remove('is-closing'))

      if (status < 400) {
        return
      }

      // Sesión o CSRF caducados: el diálogo "page expired" de Livewire
      // bloquearía el kiosco para siempre. Recargar restablece todo.
      if (status === 419) {
        preventDefault()
        safeReload()
        return
      }

      preventDefault()

      // 503 = el servidor estaba ocupado y no ejecutó nada (withBusyRetry ya
      // reintentó). No se registra en consola: el logger de Boost reenvía cada
      // console.* como POST, que vuelve a competir por el worker y realimenta
      // el propio 503. Tampoco cuenta como fallo para el recargado del kiosco.
      if (status === 503) {
        return
      }

      console.error(`[Crudo] Livewire request failed with status ${status}: ${url}`)
      showLivewireError(status, url)

      // Errores transitorios (500, red caída del backend): tras varios polls
      // fallidos seguidos, recargar es la única vía de recuperación sin manos.
      consecutiveFailures += 1
      if (consecutiveFailures >= 5) {
        scheduleKioskReload(10_000)
      }
    })
  })
}

const parseMachinesJson = (): Machine[] => {
  const element = document.querySelector<HTMLScriptElement>(MACHINE_DATA_SELECTOR)
  if (!element?.textContent) {
    return []
  }

  try {
    const parsed = JSON.parse(element.textContent) as Machine[]
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

const findMachineButton = (telar: string): HTMLElement | null => {
  return machineButtonsByTelar.get(telar)
    ?? document.querySelector<HTMLElement>(`${MACHINE_SELECTOR}[data-telar="${CSS.escape(telar)}"]`)
}

/**
 * Alerta visual al entrar en paro (y solo al entrar: si ya estaba rojo no
 * vuelve a sonar). Parpadea unos segundos y se calma sola, para que un paro
 * largo no deje media pantalla destellando toda la jornada.
 */
const PARO_ALERT_MS = 20_000
const paroAlertTimers = new Map<string, number>()

const flashParoAlert = (button: HTMLElement): void => {
  const telar = button.dataset.telar ?? ''
  window.clearTimeout(paroAlertTimers.get(telar))

  button.classList.remove('is-paro-alert')
  void button.offsetWidth // reinicia la animación si ya venía corriendo
  button.classList.add('is-paro-alert')

  paroAlertTimers.set(
    telar,
    window.setTimeout(() => {
      button.classList.remove('is-paro-alert')
      paroAlertTimers.delete(telar)
    }, PARO_ALERT_MS),
  )
}

const updateMachineCard = (machine: Machine): void => {
  const button = findMachineButton(machine.telar)
  if (!button) {
    return
  }

  const saldoPedido = machine.programa?.saldoPedido
  const saldoNegativo = typeof saldoPedido === 'number' && saldoPedido < 0

  const signature = `${machine.state}:${machine.pieces}:${machine.seconds}:${machine.kilos}:${machine.efficiencyPercent}:${machine.rpm ?? 0}:${saldoPedido ?? 0}:${saldoNegativo ? '1' : '0'}`
  if (button.dataset.signature === signature) {
    return
  }

  const previousState = button.dataset.state
  button.dataset.state = machine.state
  if (previousState !== undefined && previousState !== 'paro' && machine.state === 'paro') {
    flashParoAlert(button)
  }
  button.dataset.signature = signature
  button.toggleAttribute('data-saldo-negativo', saldoNegativo)
  button.setAttribute(
    'aria-label',
    `Abrir detalle del telar ${machine.telar}, estado ${machine.stateLabel}${saldoNegativo ? ', saldo negativo' : ''}`,
  )

  const tooltipSaldo = button.querySelector<HTMLElement>('[data-crudo-tooltip-saldo]')
  if (tooltipSaldo) {
    tooltipSaldo.hidden = !saldoNegativo
    const tooltipSaldoValue = tooltipSaldo.querySelector<HTMLElement>('[data-crudo-tooltip-saldo-value]')
    if (tooltipSaldoValue && saldoNegativo) {
      tooltipSaldoValue.textContent = `Saldo ${formatInteger(saldoPedido)}`
    }
  }

  const efficiency = button.querySelector<HTMLElement>('[data-crudo-efficiency]')
  if (efficiency) {
    efficiency.textContent = `${formatInteger(machine.efficiencyPercent ?? 0)}%`
  }

  const rpm = button.querySelector<HTMLElement>('[data-crudo-rpm]')
  if (rpm) {
    rpm.textContent = machine.rpm != null ? `${formatInteger(machine.rpm)}` : '--'
  }

  const kilos = button.querySelector<HTMLElement>('[data-crudo-kilos]')
  if (kilos) {
    kilos.textContent = `${formatInteger(machine.kilos)} kg`
  }

  const saldo = button.querySelector<HTMLElement>('[data-crudo-saldo]')
  if (saldo) {
    saldo.textContent = saldoPedido != null ? `${formatInteger(saldoPedido)}` : '--'
    saldo.classList.toggle('crudo-saldo-negativo', saldoNegativo)
  }

  const name = button.querySelector<HTMLElement>('[data-crudo-name]')
  if (name) {
    name.textContent = machine.name
  }

  const tooltipMetrics = button.querySelector<HTMLElement>('[data-crudo-tooltip-metrics]')
  if (tooltipMetrics) {
    tooltipMetrics.textContent = `${formatInteger(machine.pieces)} piezas · ${formatInteger(machine.seconds)} segundas`
  }
}

/**
 * El plano de salones va en wire:ignore (nunca se re-renderiza por Livewire),
 * así que el promedio de eficiencia del encabezado de cada salón se recalcula
 * aquí en cada pulso, igual que ya hace updateMachineCard con cada tarjeta.
 * Los telares fuera de operación no traen tarjeta con [data-crudo-efficiency]
 * (machine-card.blade.php los omite), así que quedan fuera del promedio sin
 * duplicar la lista de config('crudo.telares_fuera') en el cliente.
 */
const updateSalonEfficiencies = (machines: Machine[]): void => {
  const bySalon = new Map<string, number[]>()

  machines.forEach((machine) => {
    const button = findMachineButton(machine.telar)
    if (!button || !button.querySelector('[data-crudo-efficiency]')) {
      return
    }

    const values = bySalon.get(machine.salon) ?? []
    values.push(machine.efficiencyPercent ?? 0)
    bySalon.set(machine.salon, values)
  })

  document.querySelectorAll<HTMLElement>('[data-crudo-salon-efficiency]').forEach((element) => {
    const salon = element.dataset.crudoSalonEfficiency ?? ''
    const values = bySalon.get(salon) ?? []
    const average = values.length > 0 ? values.reduce((sum, value) => sum + value, 0) / values.length : 0
    element.textContent = `${average.toFixed(1)}%`
  })
}

const updateDashboardCards = (): void => {
  const machines = parseMachinesJson()
  machinesByTelar = new Map(machines.map((machine) => [machine.telar, machine]))
  machineButtonsByTelar = new Map(
    Array.from(document.querySelectorAll<HTMLElement>(MACHINE_SELECTOR))
      .map((button): [string, HTMLElement] => [button.dataset.telar ?? '', button])
      .filter(([telar]) => telar !== ''),
  )

  machines.forEach((machine) => {
    updateMachineCard(machine)
  })
  updateSalonEfficiencies(machines)
}

const showPendingDetail = (machine: Machine, button: HTMLElement): void => {
  const pending = document.querySelector<HTMLElement>(PENDING_DETAIL_SELECTOR)
  if (!pending) {
    return
  }

  pendingMachineButton?.removeAttribute('aria-busy')
  pendingMachineButton = button
  button.setAttribute('aria-busy', 'true')

  const name = pending.querySelector<HTMLElement>('[data-crudo-detail-pending-name]')
  if (name) {
    name.textContent = `${machine.name} · ${machine.stateLabel}`
  }

  pending.hidden = false

  if (pendingDetailTimer !== null) {
    window.clearTimeout(pendingDetailTimer)
  }

  pendingDetailTimer = window.setTimeout(() => {
    hidePendingDetail(pending)
    pendingMachineButton?.removeAttribute('aria-busy')
    pendingMachineButton = null
    pendingDetailTimer = null
  }, 10000)
}

const syncPendingDetail = (): void => {
  const pending = document.querySelector<HTMLElement>(PENDING_DETAIL_SELECTOR)
  if (!pending || !document.querySelector('[data-crudo-modal]')) {
    return
  }

  hidePendingDetail(pending)
  pendingMachineButton?.removeAttribute('aria-busy')
  pendingMachineButton = null

  if (pendingDetailTimer !== null) {
    window.clearTimeout(pendingDetailTimer)
    pendingDetailTimer = null
  }
}

let modalFocusTarget: HTMLElement | null = null
let modalFocusTrigger: HTMLElement | null = null

const trapModalTab = (event: KeyboardEvent): void => {
  if (event.key !== 'Tab' || !modalFocusTarget) {
    return
  }

  const focusable = Array.from(modalFocusTarget.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR))
    .filter((element) => element.offsetParent !== null)

  if (focusable.length === 0) {
    event.preventDefault()
    return
  }

  const first = focusable[0] as HTMLElement
  const last = focusable[focusable.length - 1] as HTMLElement

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

/**
 * Mueve el foco al modal recién abierto (los tres llevan [data-crudo-modal])
 * y lo regresa al disparador al cerrarse. Sin esto el foco de teclado se
 * quedaba detrás de un aria-modal, inalcanzable con Tab.
 */
const syncModalFocus = (): void => {
  const modal = document.querySelector<HTMLElement>(CRUDO_MODAL_SELECTOR)

  if (modal && modal !== modalFocusTarget) {
    modalFocusTarget = modal
    modalFocusTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null
    document.addEventListener('keydown', trapModalTab)

    const firstFocusable = modal.querySelector<HTMLElement>(FOCUSABLE_SELECTOR)
    window.requestAnimationFrame(() => (firstFocusable ?? modal).focus({ preventScroll: true }))
  }

  if (!modal && modalFocusTarget) {
    modalFocusTarget = null
    document.removeEventListener('keydown', trapModalTab)
    const trigger = modalFocusTrigger
    modalFocusTrigger = null
    if (trigger?.isConnected) {
      trigger.focus({ preventScroll: true })
    }
  }
}

const updateRelativeTimes = (): void => {
  document.querySelectorAll<HTMLTimeElement>(RELATIVE_TIME_SELECTOR).forEach((element) => {
    const timestamp = Date.parse(element.dateTime)
    if (!Number.isFinite(timestamp)) {
      element.textContent = 'sin hora disponible'
      return
    }

    const seconds = Math.max(0, Math.round((Date.now() - timestamp) / 1000))
    if (seconds < 10) {
      element.textContent = 'ahora'
    } else if (seconds < 60) {
      element.textContent = `hace ${seconds} s`
    } else if (seconds < 3600) {
      element.textContent = `hace ${Math.floor(seconds / 60)} min`
    } else {
      element.textContent = `hace ${Math.floor(seconds / 3600)} h`
    }
  })
}

const syncRelativeTimeTimer = (): void => {
  const hasRelativeTimes = document.querySelector(RELATIVE_TIME_SELECTOR) !== null

  if (hasRelativeTimes && relativeTimeTimer === null) {
    relativeTimeTimer = window.setInterval(updateRelativeTimes, 5000)
  } else if (!hasRelativeTimes && relativeTimeTimer !== null) {
    window.clearInterval(relativeTimeTimer)
    relativeTimeTimer = null
  }
}

const observeDashboard = (): void => {
  const dashboard = document.querySelector<HTMLElement>(DASHBOARD_SELECTOR)
  if (!dashboard || dashboard === observedDashboard) {
    updateRelativeTimes()
    syncRelativeTimeTimer()
    return
  }

  mutationObserver?.disconnect()
  observedDashboard = dashboard

  const dataElement = document.querySelector<HTMLScriptElement>(MACHINE_DATA_SELECTOR)

  let scheduled = false
  mutationObserver = new MutationObserver(() => {
    if (scheduled || !observedDashboard) {
      return
    }

    scheduled = true
    window.requestAnimationFrame(() => {
      scheduled = false
      if (observedDashboard) {
        updateDashboardCards()
        updateRelativeTimes()
        syncRelativeTimeTimer()
      }
    })
  })

  if (dataElement) {
    mutationObserver.observe(dataElement, {
      characterData: true,
      childList: true,
      subtree: true,
    })
  }

  updateDashboardCards()
  updateRelativeTimes()
  syncRelativeTimeTimer()
}

const toggleFullscreen = async (): Promise<void> => {
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen()
      return
    }

    if (document.fullscreenEnabled && document.documentElement.requestFullscreen) {
      await document.documentElement.requestFullscreen()
    }
  } catch {
    // Permiso denegado, iframe sin allow="fullscreen", etc.: no hay nada que
    // el usuario deba hacer distinto, así que no se muestra ningún error.
  }
}

const normalizeCatalogText = (value: unknown): string => {
  if (value === null || value === undefined) {
    return ''
  }

  return String(value).trim()
}

const loadQualityDefectOptions = (url: string): Promise<QualityDefectOption[]> => {
  const existingRequest = qualityDefectRequests.get(url)
  if (existingRequest) {
    return existingRequest
  }

  const http = (window as CrudoWindow).http

  const request = (http ? http.get<QualityDefectsResponse>(url) : Promise.reject(new Error('http no disponible')))
    .then((payload): QualityDefectOption[] => {
      if (payload.success === false) {
        throw new Error(payload.error || 'No fue posible consultar el catálogo de defectos.')
      }

      const options = new Map<string, QualityDefectOption>()
      ;(Array.isArray(payload.data) ? payload.data : []).forEach((item) => {
        const id = normalizeCatalogText(item.Id)
        const department = normalizeCatalogText(item.Departamento)
        const defect = normalizeCatalogText(item.Falla)
        const description = normalizeCatalogText(item.Descripcion)

        if (department.toLocaleUpperCase('es-MX') !== 'CALIDAD' || id === '' || defect === '') {
          return
        }

        options.set(id, {
          value: id,
          label: description !== '' && description.toLocaleUpperCase('es-MX') !== defect.toLocaleUpperCase('es-MX')
            ? `${defect} — ${description}`
            : defect,
        })
      })

      return Array.from(options.values()).sort((left, right) => left.label.localeCompare(right.label, 'es-MX'))
    })
    .catch((error: unknown) => {
      qualityDefectRequests.delete(url)
      throw error
    })

  qualityDefectRequests.set(url, request)

  return request
}

const replaceSelectOptions = (
  select: HTMLSelectElement,
  options: QualityDefectOption[],
): void => {
  const currentValue = select.value
  const placeholder = new Option('Seleccione un defecto', '')
  select.replaceChildren(placeholder)

  options.forEach((option) => {
    select.add(new Option(option.label, option.value))
  })

  if (options.some((option) => option.value === currentValue)) {
    select.value = currentValue
  }

  select.disabled = false
}

const showQualityDefectLoadError = (editor: HTMLElement, message: string): void => {
  editor.dataset.qualityDefectsState = 'error'
  editor.querySelectorAll<HTMLSelectElement>(QUALITY_DEFECT_SELECT_SELECTOR).forEach((select) => {
    select.replaceChildren(new Option(message, ''))
    select.disabled = true
  })
}

const hydrateQualityDefectEditors = (): void => {
  document.querySelectorAll<HTMLElement>(AUDIT_DEFECT_EDITOR_SELECTOR).forEach((editor) => {
    if (editor.closest<HTMLElement>(AUDIT_CONTENT_SELECTOR)?.hidden) {
      return
    }

    if (editor.dataset.qualityDefectsState === 'loading' || editor.dataset.qualityDefectsState === 'loaded') {
      return
    }

    const url = editor.dataset.crudoQualityDefectsUrl
    if (!url) {
      showQualityDefectLoadError(editor, 'Catálogo no configurado')
      return
    }

    editor.dataset.qualityDefectsState = 'loading'
    const selects = editor.querySelectorAll<HTMLSelectElement>(QUALITY_DEFECT_SELECT_SELECTOR)
    selects.forEach((select) => {
      select.disabled = true
    })

    void loadQualityDefectOptions(url)
      .then((options) => {
        if (options.length === 0) {
          showQualityDefectLoadError(editor, 'Sin defectos de Calidad')
          return
        }

        selects.forEach((select) => replaceSelectOptions(select, options))
        editor.dataset.qualityDefectsState = 'loaded'
      })
      .catch(() => {
        showQualityDefectLoadError(editor, 'Error al cargar catálogo')
      })
  })
}

const appendTextElement = <K extends keyof HTMLElementTagNameMap>(
  parent: HTMLElement,
  tag: K,
  className: string,
  text: string,
): HTMLElementTagNameMap[K] => {
  const element = document.createElement(tag)
  element.className = className
  element.textContent = text
  parent.append(element)

  return element
}

const renderAuditHistory = (history: HTMLElement, audits: AuditHistoryItem[]): void => {
  const list = history.querySelector<HTMLElement>(AUDIT_HISTORY_LIST_SELECTOR)
  const count = history.querySelector<HTMLElement>(AUDIT_HISTORY_COUNT_SELECTOR)
  if (!list || !count) {
    return
  }

  count.textContent = `${audits.length}`
  list.replaceChildren()

  if (audits.length === 0) {
    appendTextElement(list, 'p', 'crudo-audit-history-state', 'Sin auditorías registradas hoy para este telar.')
    return
  }

  audits.forEach((audit) => {
    const card = document.createElement('article')
    card.className = `crudo-audit-history-card${audit.paro ? ' has-stop' : ''}`

    const head = document.createElement('div')
    head.className = 'crudo-audit-history-head'
    appendTextElement(head, 'strong', '', `#${audit.id} · ${audit.hora || 'Sin hora'}`)
    appendTextElement(
      head,
      'span',
      `crudo-audit-history-kind${audit.paro ? ' has-stop' : ''}`,
      audit.paro ? `Paro ${audit.paro.folio || ''}`.trim() : 'Auditoría',
    )
    card.append(head)

    const meta = document.createElement('div')
    meta.className = 'crudo-audit-history-meta'
    const metaParts = [`T${audit.turno}`, audit.auditor]
    if (audit.orden) {
      metaParts.push(audit.orden)
    }
    appendTextElement(meta, 'span', '', metaParts.join(' · '))
    card.append(meta)

    const checks = document.createElement('div')
    checks.className = 'crudo-audit-history-checks'
    audit.checklist.forEach((check) => {
      const icon = check.resultado === 'bien' ? '✓' : check.resultado === 'mal' ? '✕' : '—'
      const badge = appendTextElement(
        checks,
        'span',
        'crudo-audit-history-check',
        `${icon} ${check.label}`,
      )
      badge.dataset.result = check.resultado
    })
    card.append(checks)

    const defects = document.createElement('div')
    defects.className = 'crudo-audit-history-defects'
    if (audit.defectos.length === 0) {
      appendTextElement(defects, 'span', 'crudo-audit-history-defect', 'Sin defectos capturados')
    } else {
      audit.defectos.forEach((defect) => {
        const concept = defect.descripcion || defect.falla
        appendTextElement(
          defects,
          'span',
          `crudo-audit-history-defect${defect.principal ? ' is-principal' : ''}`,
          `${defect.principal ? '★ ' : ''}${concept}: ${defect.piezas} pzas`,
        )
      })
    }
    card.append(defects)

    if (audit.observaciones) {
      appendTextElement(
        card,
        'p',
        'crudo-audit-history-observations',
        `Obs: ${audit.observaciones}`,
      )
    }

    list.append(card)
  })
}

const loadAuditHistory = async (history: HTMLElement, force = false): Promise<void> => {
  const url = history.dataset.crudoAuditHistoryUrl
  if (!url) {
    return
  }

  const state = history.dataset.auditHistoryState
  if (shouldSkipAuditHistoryLoad(state, history.dataset.auditHistoryStateUrl, url, force)) {
    return
  }

  const list = history.querySelector<HTMLElement>(AUDIT_HISTORY_LIST_SELECTOR)
  if (!list) {
    return
  }

  history.dataset.auditHistoryState = 'loading'
  history.dataset.auditHistoryStateUrl = url
  list.replaceChildren()
  appendTextElement(list, 'p', 'crudo-audit-history-state', 'Cargando auditorías…')

  const requestSignal = auditHistoryRequests.begin(history)
  const http = (window as CrudoWindow).http

  try {
    if (!http) {
      throw new Error('http no disponible')
    }

    const payload = await http.get<AuditHistoryResponse>(url, { signal: requestSignal })

    if (requestSignal.aborted || !history.isConnected) {
      return
    }

    if (!isCurrentAuditHistoryRequest(url, history.dataset.crudoAuditHistoryUrl)) {
      if (history.isConnected) {
        void loadAuditHistory(history)
      }

      return
    }

    const expectedTelar = history.dataset.crudoAuditTelar ?? ''
    const expectedDate = payload.meta?.fecha ?? ''
    const audits = (Array.isArray(payload.data) ? payload.data : []).filter((audit) => (
      auditBelongsToScope(audit.telar, audit.fecha, expectedTelar, expectedDate)
    ))

    renderAuditHistory(history, audits)
    history.dataset.auditHistoryState = 'loaded'
  } catch {
    if (requestSignal.aborted || !history.isConnected) {
      return
    }

    if (!isCurrentAuditHistoryRequest(url, history.dataset.crudoAuditHistoryUrl)) {
      if (history.isConnected) {
        void loadAuditHistory(history)
      }

      return
    }

    list.replaceChildren()
    appendTextElement(
      list,
      'p',
      'crudo-audit-history-state',
      'No fue posible cargar las auditorías. Puedes intentar guardar o volver a abrir el detalle.',
    )
    history.dataset.auditHistoryState = 'error'
  } finally {
    auditHistoryRequests.finish(history, requestSignal)
  }
}

const hydrateAuditHistories = (): void => {
  document.querySelectorAll<HTMLElement>(AUDIT_HISTORY_SELECTOR).forEach((history) => {
    void loadAuditHistory(history)
  })
}

const observeQualityDefectEditors = (): void => {
  hydrateQualityDefectEditors()
  hydrateAuditHistories()
  syncAuditActionStates()
  syncPendingDetail()
  syncModalFocus()

  const crudoRoot = document.querySelector<HTMLElement>(CRUDO_ROOT_SELECTOR)
  if (!crudoRoot || (auditDefectObserver && observedAuditRoot === crudoRoot)) {
    return
  }

  auditDefectObserver?.disconnect()
  observedAuditRoot = crudoRoot

  auditDefectObserver = new MutationObserver((mutations) => {
    auditHistoryRequests.abortDisconnected()
    syncPendingDetail()
    syncModalFocus()
    if (mutations.some((mutation) => mutation.addedNodes.length > 0 || mutation.attributeName === 'hidden')) {
      hydrateQualityDefectEditors()
      hydrateAuditHistories()
    }
  })

  auditDefectObserver.observe(crudoRoot, {
    attributes: true,
    attributeFilter: ['hidden'],
    childList: true,
    subtree: true,
  })
}

const cycleAuditResult = (button: HTMLElement): void => {
  const currentState = button.dataset.state
  const nextState = currentState === 'empty'
    ? 'good'
    : currentState === 'good'
      ? 'bad'
      : 'empty'
  const value = nextState === 'good' ? 'bien' : nextState === 'bad' ? 'mal' : ''
  const label = nextState === 'good' ? 'Bien' : nextState === 'bad' ? 'Mal' : 'Sin evaluar'
  const questionNumber = button.dataset.questionNumber || ''

  button.dataset.state = nextState
  button.setAttribute('aria-label', `Pregunta ${questionNumber}: ${label}`)

  const resultLabel = button.querySelector<HTMLElement>('[data-crudo-audit-result-label]')
  if (resultLabel) {
    resultLabel.textContent = label
  }

  const resultInput = button.parentElement?.querySelector<HTMLInputElement>('[data-crudo-audit-result-input]')
  if (resultInput) {
    resultInput.value = value
  }

  const form = button.closest<HTMLElement>(AUDIT_FORM_SELECTOR)
  if (form) {
    syncAuditActionState(form)
  }
}

const auditAnswer = (value: string): boolean | null => {
  if (value === 'bien') {
    return true
  }

  if (value === 'mal') {
    return false
  }

  return null
}

// Los tres selectores que collectAuditPayload (valida y arma el payload) y
// syncAuditActionState (solo mira si hay algo capturado) leen del mismo
// formulario, para no recorrerlo dos veces con selectores que podrían divergir.
const auditChecklistInputs = (form: HTMLElement): HTMLInputElement[] =>
  Array.from(form.querySelectorAll<HTMLInputElement>('[data-crudo-audit-result-input]'))

const auditDefectRows = (form: HTMLElement): HTMLElement[] =>
  Array.from(form.querySelectorAll<HTMLElement>('[data-crudo-audit-defect-row]'))

const auditObservationsField = (form: HTMLElement): HTMLTextAreaElement | null =>
  form.querySelector<HTMLTextAreaElement>('textarea[name="crudo-audit-observations"]')

const collectAuditPayload = (form: HTMLElement): AuditPayload => {
  const telar = form.dataset.crudoAuditTelar?.trim() ?? ''
  const salon = form.dataset.crudoAuditSalon?.trim() ?? ''

  if (telar === '' || salon === '') {
    throw new Error('No se pudo identificar el telar o su salón. Cierra el detalle e inténtalo nuevamente.')
  }

  const checklist: Record<AuditChecklistKey, boolean | null> = {
    alineacion_orden: null,
    dibujo_jacquard: null,
    identificacion_julio: null,
  }

  auditChecklistInputs(form).forEach((input) => {
    const key = input.dataset.questionKey as AuditChecklistKey | undefined
    if (key && key in checklist) {
      checklist[key] = auditAnswer(input.value)
    }
  })

  const defects: AuditPayload['defectos'] = []
  const selectedDefects = new Set<number>()

  auditDefectRows(form).forEach((row, index) => {
    const select = row.querySelector<HTMLSelectElement>(QUALITY_DEFECT_SELECT_SELECTOR)
    const quantity = row.querySelector<HTMLInputElement>('input[name="crudo-audit-defect-pieces[]"]')
    const selectedId = Number.parseInt(select?.value ?? '', 10)
    const pieces = Number.parseInt(quantity?.value ?? '0', 10)
    const hasDefect = Number.isInteger(selectedId) && selectedId > 0
    const hasPieces = Number.isInteger(pieces) && pieces > 0

    if (!hasDefect && !hasPieces) {
      return
    }

    if (!hasDefect) {
      throw new Error(`Selecciona el defecto ${index + 1}.`)
    }

    if (!hasPieces) {
      throw new Error(`Las piezas del defecto ${index + 1} deben ser mayores a cero.`)
    }

    if (selectedDefects.has(selectedId)) {
      throw new Error(`El defecto ${index + 1} está repetido.`)
    }

    selectedDefects.add(selectedId)
    defects.push({ defecto_id: selectedId, piezas: pieces })
  })

  const observations = auditObservationsField(form)?.value.trim() ?? ''
  const order = form.dataset.crudoAuditOrder?.trim() ?? ''

  const rawTags = form.querySelector<HTMLInputElement>('[data-crudo-audit-marbetes]')?.value.trim() ?? ''
  const tags = Number.parseInt(rawTags, 10)

  if (rawTags !== '' && (!Number.isInteger(tags) || tags < 0)) {
    throw new Error('Los marbetes deben ser un número entero mayor o igual a cero.')
  }

  return {
    no_telar_id: telar,
    salon,
    orden_trabajo: order === '' ? null : order,
    checklist,
    marbetes: rawTags === '' ? null : tags,
    observaciones: observations === '' ? null : observations,
    defectos: defects,
  }
}

const validationMessage = (payload: AuditSaveResponse, fallback: string): string => {
  const messages = Object.values(payload.errors ?? {}).flat().filter((message) => message.trim() !== '')

  return messages[0] ?? payload.error ?? payload.message ?? fallback
}

/**
 * http.post lanza con el cuerpo de la respuesta en .data para cualquier
 * status fuera de 2xx (422 de validación incluido); el success:false con
 * 200 se sigue lanzando a mano como Error simple. Unifica ambos casos.
 */
const messageFromCrudoError = (error: unknown, fallback: string): string => {
  const httpError = error as HttpError
  if (httpError?.data && typeof httpError.data === 'object') {
    return validationMessage(httpError.data as AuditSaveResponse, fallback)
  }

  return error instanceof Error ? error.message : fallback
}

const showAuditFeedback = (
  form: HTMLElement,
  message: string,
  state: 'loading' | 'success' | 'error',
): void => {
  const feedback = form.querySelector<HTMLElement>(AUDIT_FEEDBACK_SELECTOR)
  if (!feedback) {
    return
  }

  feedback.hidden = false
  feedback.dataset.state = state
  feedback.textContent = message
}

const syncAuditActionState = (form: HTMLElement): void => {
  const checklistValues = auditChecklistInputs(form).map((input) => input.value)
  const observations = auditObservationsField(form)?.value ?? ''
  const defects = auditDefectRows(form)
    .map((row) => ({
      defectId: row.querySelector<HTMLSelectElement>(QUALITY_DEFECT_SELECT_SELECTOR)?.value ?? '',
      pieces: row.querySelector<HTMLInputElement>('input[name="crudo-audit-defect-pieces[]"]')?.value ?? '',
    }))
  const availability = auditActionAvailability({ checklistValues, observations, defects })
  const submitting = form.dataset.submitting === 'true'

  const auditButton = form.querySelector<HTMLButtonElement>('[data-crudo-save-audit]')
  const stopButton = form.querySelector<HTMLButtonElement>('[data-crudo-save-stop]')

  if (auditButton) {
    auditButton.disabled = submitting || !availability.canSaveAudit
    auditButton.setAttribute('aria-disabled', auditButton.disabled ? 'true' : 'false')
  }

  if (stopButton) {
    stopButton.disabled = submitting || !availability.canSaveStop
    stopButton.setAttribute('aria-disabled', stopButton.disabled ? 'true' : 'false')
  }

  ;[auditButton, stopButton].forEach((button) => {
    if (!button) {
      return
    }

    if (submitting) {
      button.setAttribute('aria-busy', 'true')
    } else {
      button.removeAttribute('aria-busy')
    }
  })
}

const syncAuditActionStates = (): void => {
  document.querySelectorAll<HTMLElement>(AUDIT_FORM_SELECTOR).forEach(syncAuditActionState)
}

const setAuditSubmitting = (form: HTMLElement, submitting: boolean): void => {
  form.dataset.submitting = submitting ? 'true' : 'false'
  syncAuditActionState(form)
}

const resetAuditForm = (form: HTMLElement): void => {
  form.querySelectorAll<HTMLElement>('[data-crudo-audit-result]').forEach((button) => {
    const questionNumber = button.dataset.questionNumber || ''
    button.dataset.state = 'empty'
    button.setAttribute('aria-label', `Pregunta ${questionNumber}: Sin evaluar`)

    const label = button.querySelector<HTMLElement>('[data-crudo-audit-result-label]')
    if (label) {
      label.textContent = 'Sin evaluar'
    }
  })

  form.querySelectorAll<HTMLInputElement>('[data-crudo-audit-result-input]').forEach((input) => {
    input.value = ''
  })

  form.querySelectorAll<HTMLSelectElement>(QUALITY_DEFECT_SELECT_SELECTOR).forEach((select) => {
    select.value = ''
  })

  form.querySelectorAll<HTMLInputElement>('input[name="crudo-audit-defect-pieces[]"]').forEach((input) => {
    input.value = '0'
  })

  const observations = auditObservationsField(form)
  if (observations) {
    observations.value = ''
  }

  const tags = form.querySelector<HTMLInputElement>('[data-crudo-audit-marbetes]')
  if (tags) {
    tags.value = ''
  }

  syncAuditActionState(form)
}

const submitAudit = async (button: HTMLElement, withStop: boolean): Promise<void> => {
  const form = button.closest<HTMLElement>(AUDIT_FORM_SELECTOR)
  if (!form || form.dataset.submitting === 'true') {
    return
  }

  const url = withStop ? form.dataset.crudoAuditStopUrl : form.dataset.crudoAuditUrl
  if (!url) {
    showAuditFeedback(form, 'La ruta para guardar la auditoría no está configurada.', 'error')
    return
  }

  let payload: AuditPayload
  try {
    payload = collectAuditPayload(form)
  } catch (error) {
    showAuditFeedback(
      form,
      error instanceof Error ? error.message : 'Revisa los datos de la auditoría.',
      'error',
    )
    return
  }

  if (withStop && payload.defectos.length === 0) {
    showAuditFeedback(form, 'Captura al menos un defecto con piezas para generar el paro.', 'error')
    return
  }

  setAuditSubmitting(form, true)
  showAuditFeedback(
    form,
    withStop ? 'Guardando auditoría y generando paro…' : 'Guardando auditoría…',
    'loading',
  )

  try {
    const http = (window as CrudoWindow).http
    if (!http) {
      throw new Error('http no disponible')
    }

    const responsePayload = await http.post<AuditSaveResponse>(url, payload)

    if (responsePayload.success === false) {
      throw new Error(validationMessage(responsePayload, 'No fue posible guardar la auditoría.'))
    }

    const successMessage = responsePayload.message ?? (withStop
      ? 'Auditoría y paro guardados correctamente.'
      : 'Auditoría guardada correctamente.')

    resetAuditForm(form)
    ;(window as CrudoWindow).notify?.success(successMessage)
    ;(window as CrudoWindow).Livewire?.dispatch('crudo-auditoria-guardada')

    if (withStop) {
      ;(window as CrudoWindow).Livewire?.dispatch('crudo-paro-guardado')
    }
  } catch (error) {
    showAuditFeedback(
      form,
      messageFromCrudoError(error, 'No fue posible guardar la auditoría.'),
      'error',
    )
  } finally {
    setAuditSubmitting(form, false)
  }
}

document.addEventListener('click', (event) => {
  const target = event.target
  if (!(target instanceof Element)) {
    return
  }

  const pointerTelar = pointerMachineTelar
  pointerMachineTelar = null

  const auditResultButton = target.closest<HTMLElement>('[data-crudo-audit-result]')
  if (auditResultButton) {
    cycleAuditResult(auditResultButton)
    return
  }

  const saveAuditButton = target.closest<HTMLElement>('[data-crudo-save-audit]')
  if (saveAuditButton) {
    void submitAudit(saveAuditButton, false)
    return
  }

  const saveStopButton = target.closest<HTMLElement>('[data-crudo-save-stop]')
  if (saveStopButton) {
    void submitAudit(saveStopButton, true)
    return
  }

  const machineButton = target.closest<HTMLElement>(MACHINE_SELECTOR)
  if (machineButton) {
    if (machineButton.getAttribute('aria-busy') === 'true') {
      return
    }

    const telar = machineButton.dataset.telar
    if (!telar) {
      return
    }

    if (!isIntentionalMachineActivation(pointerTelar, telar, event.detail)) {
      return
    }

    const machine = machinesByTelar.get(telar)
    if (machine) {
      const dashboard = document.querySelector<HTMLElement>(DASHBOARD_SELECTOR)
      showPendingDetail(machine, machineButton)
      window.dispatchEvent(
        new CustomEvent('open-crudo-detail', {
          detail: {
            telar,
            machine,
            fecha: dashboard?.dataset.crudoFecha ?? null,
            fechaInicio: dashboard?.dataset.crudoFechaInicio ?? null,
            fechaFin: dashboard?.dataset.crudoFechaFin ?? null,
            modo: dashboard?.dataset.crudoModo ?? null,
          },
          bubbles: true,
        }),
      )
    }

    return
  }

  const fullscreenButton = target.closest<HTMLElement>('[data-crudo-fullscreen]')
  if (fullscreenButton) {
    const dashboard = document.querySelector<HTMLElement>(DASHBOARD_SELECTOR)
    if (dashboard) {
      void toggleFullscreen()
    }
  }
})

const syncAuditFormFromFieldEvent = (event: Event): void => {
  const target = event.target
  if (!(target instanceof Element)) {
    return
  }

  const form = target.closest<HTMLElement>(AUDIT_FORM_SELECTOR)
  if (form) {
    syncAuditActionState(form)
  }
}

document.addEventListener('input', syncAuditFormFromFieldEvent)
document.addEventListener('change', syncAuditFormFromFieldEvent)

document.addEventListener('click', (event) => {
  const target = event.target
  if (!(target instanceof Element)) {
    return
  }

  if (target.closest('[data-crudo-livewire-error-reload]')) {
    window.location.reload()
  }
})

document.addEventListener('pointerdown', (event) => {
  const target = event.target
  if (!(target instanceof Element)) {
    return
  }

  pointerMachineTelar = target.closest<HTMLElement>(MACHINE_SELECTOR)?.dataset.telar ?? null

  const backdrop = target.closest<HTMLElement>('[data-crudo-modal]')
  const closesFromButton = Boolean(target.closest('[data-crudo-modal-close]'))
  const closesFromBackdrop = backdrop !== null && target === backdrop

  if (backdrop && (closesFromButton || closesFromBackdrop)) {
    backdrop.classList.add('is-closing')
  }
}, { capture: true })

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') {
    return
  }

  // Un <dialog> nativo abierto (paro, flog) ya cierra con Escape por su
  // cuenta; si lo dejamos pasar aquí también cerraríamos el modal que lo
  // contiene con la misma tecla.
  if (document.querySelector('dialog[open]')) {
    return
  }

  const closeButton = document.querySelector<HTMLButtonElement>('[data-crudo-modal-close]')
  closeButton?.click()
})

/**
 * El reporte se descarga con fetch en vez de dejar navegar el enlace: una
 * navegación normal no avisa cuándo empezó ni cuándo terminó el archivo, y el
 * Excel tarda lo que tarde la agregación de producción.
 */
const descargarReporte = async (link: HTMLAnchorElement): Promise<void> => {
  const notify = (window as CrudoWindow).notify
  const icon = link.querySelector('i')
  const iconClass = icon?.className ?? ''

  link.dataset.crudoDescargando = 'true'
  link.setAttribute('aria-busy', 'true')
  icon?.setAttribute('class', 'fa-solid fa-circle-notch fa-spin')
  notify?.loading('Generando reporte…')

  try {
    const http = (window as CrudoWindow).http
    if (!http) {
      throw new Error('http no disponible')
    }

    const blob = await http.get<Blob>(link.href, { responseType: 'blob' })

    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = link.dataset.crudoNombreArchivo || 'reporte_telares.xlsx'
    anchor.click()
    URL.revokeObjectURL(url)

    notify?.close()
    notify?.success('Reporte descargado')
  } catch (error) {
    notify?.close()
    const httpError = error as HttpError
    notify?.error(
      httpError?.status
        ? `No fue posible generar el reporte (${httpError.status}).`
        : (error instanceof Error ? error.message : 'No fue posible generar el reporte.'),
    )
  } finally {
    delete link.dataset.crudoDescargando
    link.removeAttribute('aria-busy')
    icon?.setAttribute('class', iconClass)
  }
}

document.addEventListener('click', (event) => {
  const link = (event.target as HTMLElement | null)?.closest?.('[data-crudo-reporte]')
  if (!(link instanceof HTMLAnchorElement)) {
    return
  }

  event.preventDefault()
  if (link.dataset.crudoDescargando !== 'true') {
    void descargarReporte(link)
  }
})

document.addEventListener('fullscreenchange', () => {
  const button = document.querySelector<HTMLButtonElement>('[data-crudo-fullscreen]')
  const icon = document.querySelector<HTMLElement>('[data-crudo-fullscreen] i')
  if (!button || !icon) {
    return
  }

  const isFullscreen = Boolean(document.fullscreenElement)
  icon.classList.toggle('fa-expand', !isFullscreen)
  icon.classList.toggle('fa-compress', isFullscreen)
  button.title = isFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'
  button.setAttribute(
    'aria-label',
    isFullscreen ? 'Salir de pantalla completa' : 'Mostrar tablero en pantalla completa',
  )
})

document.addEventListener('livewire:init', observeDashboard)
document.addEventListener('livewire:navigated', observeDashboard)
document.addEventListener('DOMContentLoaded', observeDashboard)
installBusyServerRetry()
document.addEventListener('livewire:init', installLivewireErrorHandler)
document.addEventListener('livewire:navigated', installLivewireErrorHandler)
document.addEventListener('DOMContentLoaded', installLivewireErrorHandler)
document.addEventListener('livewire:init', observeQualityDefectEditors)
document.addEventListener('livewire:navigated', observeQualityDefectEditors)
document.addEventListener('DOMContentLoaded', observeQualityDefectEditors)

window.addEventListener('beforeunload', () => {
  if (relativeTimeTimer !== null) {
    window.clearInterval(relativeTimeTimer)
  }
  if (pendingDetailTimer !== null) {
    window.clearTimeout(pendingDetailTimer)
  }
  mutationObserver?.disconnect()
  auditDefectObserver?.disconnect()
  auditHistoryRequests.abortAll()
})
