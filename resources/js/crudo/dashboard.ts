import {
  auditBelongsToScope,
  isCurrentAuditHistoryRequest,
  shouldSkipAuditHistoryLoad,
} from './audit-history-state'
import { AuditHistoryRequestCoordinator } from './audit-history-request'
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
  expectedKilos: number
  state: string
  stateLabel: string
  stateIcon: string
  paro: Record<string, string | null> | null
  programa: Record<string, string | null> | null
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

type CrudoWindow = Window & typeof globalThis & {
  Livewire?: {
    dispatch: (event: string) => void
    hook?: (
      name: 'request',
      callback: (request: {
        url: string
        fail: (callback: (failure: {
          status: number
          content: unknown
          preventDefault: () => void
        }) => void) => void
      }) => void,
    ) => void
  }
  Swal?: {
    fire: (options: Record<string, unknown>) => Promise<unknown>
  }
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

const showLivewireError = (status: number, url: string): void => {
  // Un 404 en /livewire-<hash>/update significa endpoint muerto: el snapshot
  // de esta pestaña ya no es válido y solo se recupera recargando.
  if (status === 404) {
    stopDashboardPolling()
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
  livewire.hook('request', ({ url, fail }) => {
    if (!document.querySelector(DASHBOARD_SELECTOR)) {
      return
    }

    fail(({ status, preventDefault }) => {
      if (status < 400 || status === 419) {
        return
      }

      preventDefault()
      console.error(`[Crudo] Livewire request failed with status ${status}: ${url}`)
      showLivewireError(status, url)
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

const updateMachineCard = (machine: Machine): void => {
  const button = findMachineButton(machine.telar)
  if (!button) {
    return
  }

  const signature = `${machine.state}:${machine.pieces}:${machine.seconds}:${machine.kilos}`
  if (button.dataset.signature === signature) {
    return
  }

  button.dataset.state = machine.state
  button.dataset.signature = signature
  button.setAttribute('aria-label', `Abrir detalle del telar ${machine.telar}, estado ${machine.stateLabel}`)

  const quality = button.querySelector<HTMLElement>('[data-crudo-quality]')
  if (quality) {
    quality.textContent = `${formatInteger(machine.qualityPercent)}%`
  }

  const kilos = button.querySelector<HTMLElement>('[data-crudo-kilos]')
  if (kilos) {
    kilos.textContent = `${formatInteger(machine.kilos)} kg`
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
  if (document.fullscreenElement) {
    await document.exitFullscreen()
    return
  }

  if (document.fullscreenEnabled && document.documentElement.requestFullscreen) {
    await document.documentElement.requestFullscreen()
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

  const request = fetch(url, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
    },
  })
    .then(async (response): Promise<QualityDefectOption[]> => {
      const payload = (await response.json()) as QualityDefectsResponse
      if (!response.ok || payload.success === false) {
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

  try {
    const response = await fetch(url, {
      signal: requestSignal,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
    const payload = await response.json().catch((): AuditHistoryResponse => ({})) as AuditHistoryResponse

    if (requestSignal.aborted || !history.isConnected) {
      return
    }

    if (!response.ok) {
      throw new Error(payload.message || 'No fue posible consultar las auditorías de hoy.')
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

  const crudoRoot = document.querySelector<HTMLElement>(CRUDO_ROOT_SELECTOR)
  if (!crudoRoot || (auditDefectObserver && observedAuditRoot === crudoRoot)) {
    return
  }

  auditDefectObserver?.disconnect()
  observedAuditRoot = crudoRoot

  auditDefectObserver = new MutationObserver((mutations) => {
    auditHistoryRequests.abortDisconnected()
    syncPendingDetail()
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

  form.querySelectorAll<HTMLInputElement>('[data-crudo-audit-result-input]').forEach((input) => {
    const key = input.dataset.questionKey as AuditChecklistKey | undefined
    if (key && key in checklist) {
      checklist[key] = auditAnswer(input.value)
    }
  })

  const defects: AuditPayload['defectos'] = []
  const selectedDefects = new Set<number>()

  form.querySelectorAll<HTMLElement>('[data-crudo-audit-defect-row]').forEach((row, index) => {
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

  const observations = form.querySelector<HTMLTextAreaElement>('textarea[name="crudo-audit-observations"]')
    ?.value.trim() ?? ''
  const order = form.dataset.crudoAuditOrder?.trim() ?? ''

  return {
    no_telar_id: telar,
    salon,
    orden_trabajo: order === '' ? null : order,
    checklist,
    observaciones: observations === '' ? null : observations,
    defectos: defects,
  }
}

const validationMessage = (payload: AuditSaveResponse, fallback: string): string => {
  const messages = Object.values(payload.errors ?? {}).flat().filter((message) => message.trim() !== '')

  return messages[0] ?? payload.error ?? payload.message ?? fallback
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

const showAuditSuccess = (message: string): void => {
  const swal = (window as CrudoWindow).Swal
  if (swal) {
    void swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: message,
      showConfirmButton: false,
      timer: 2800,
      timerProgressBar: true,
    })

    return
  }

  window.setTimeout(() => window.alert(message), 0)
}

const syncAuditActionState = (form: HTMLElement): void => {
  const checklistValues = Array.from(
    form.querySelectorAll<HTMLInputElement>('[data-crudo-audit-result-input]'),
  ).map((input) => input.value)
  const observations = form.querySelector<HTMLTextAreaElement>('textarea[name="crudo-audit-observations"]')
    ?.value ?? ''
  const defects = Array.from(form.querySelectorAll<HTMLElement>('[data-crudo-audit-defect-row]'))
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

  const observations = form.querySelector<HTMLTextAreaElement>('textarea[name="crudo-audit-observations"]')
  if (observations) {
    observations.value = ''
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

  const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
  setAuditSubmitting(form, true)
  showAuditFeedback(
    form,
    withStop ? 'Guardando auditoría y generando paro…' : 'Guardando auditoría…',
    'loading',
  )

  try {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
    })
    const responsePayload = await response.json().catch((): AuditSaveResponse => ({})) as AuditSaveResponse

    if (!response.ok || responsePayload.success === false) {
      throw new Error(validationMessage(responsePayload, 'No fue posible guardar la auditoría.'))
    }

    const successMessage = responsePayload.message ?? (withStop
      ? 'Auditoría y paro guardados correctamente.'
      : 'Auditoría guardada correctamente.')

    resetAuditForm(form)
    showAuditSuccess(successMessage)
    ;(window as CrudoWindow).Livewire?.dispatch('crudo-auditoria-guardada')

    if (withStop) {
      ;(window as CrudoWindow).Livewire?.dispatch('crudo-paro-guardado')
    }
  } catch (error) {
    showAuditFeedback(
      form,
      error instanceof Error ? error.message : 'No fue posible guardar la auditoría.',
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

  const closeButton = document.querySelector<HTMLButtonElement>('[data-crudo-modal-close]')
  closeButton?.click()
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
