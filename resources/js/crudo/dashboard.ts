import { hidePendingDetail } from './pending-detail'

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
  orders: string[]
  operators: string[]
  defects: Array<Record<string, unknown>>
  captures: Array<Record<string, unknown>>
  lastUpdatedAt: string | null
  paro: Record<string, string | null> | null
  programa: Record<string, string | null> | null
}

type QualityDefectCatalogItem = {
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

const DASHBOARD_SELECTOR = '[data-crudo-dashboard]'
const MACHINE_DATA_SELECTOR = '[data-crudo-machines-data]'
const MACHINE_SELECTOR = '[data-crudo-machine]'
const RELATIVE_TIME_SELECTOR = '[data-crudo-relative-time]'
const AUDIT_DEFECT_EDITOR_SELECTOR = '[data-crudo-audit-defects]'
const AUDIT_CONTENT_SELECTOR = '[data-crudo-audit-content]'
const QUALITY_DEFECT_SELECT_SELECTOR = '[data-crudo-quality-defect-select]'
const PENDING_DETAIL_SELECTOR = '[data-crudo-detail-pending]'

const formatInteger = (value: number): string => Math.round(value).toLocaleString('es-MX')

let machinesByTelar = new Map<string, Machine>()
let machineButtonsByTelar = new Map<string, HTMLElement>()
let pendingMachineButton: HTMLElement | null = null
let pendingDetailTimer: number | null = null
let observedDashboard: HTMLElement | null = null
let mutationObserver: MutationObserver | null = null
let auditDefectObserver: MutationObserver | null = null
let relativeTimeTimer: number | null = null
const qualityDefectRequests = new Map<string, Promise<QualityDefectOption[]>>()

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

const observeDashboard = (): void => {
  const dashboard = document.querySelector<HTMLElement>(DASHBOARD_SELECTOR)
  if (!dashboard || dashboard === observedDashboard) {
    updateRelativeTimes()
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
        const department = normalizeCatalogText(item.Departamento)
        const defect = normalizeCatalogText(item.Falla)
        const description = normalizeCatalogText(item.Descripcion)

        if (department.toLocaleUpperCase('es-MX') !== 'CALIDAD' || defect === '') {
          return
        }

        const key = `${defect}|${description}`.toLocaleUpperCase('es-MX')
        options.set(key, {
          value: defect,
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

const observeQualityDefectEditors = (): void => {
  hydrateQualityDefectEditors()
  syncPendingDetail()

  if (auditDefectObserver || !document.body) {
    return
  }

  auditDefectObserver = new MutationObserver((mutations) => {
    syncPendingDetail()
    if (mutations.some((mutation) => mutation.addedNodes.length > 0 || mutation.attributeName === 'hidden')) {
      hydrateQualityDefectEditors()
    }
  })

  auditDefectObserver.observe(document.body, {
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
}

document.addEventListener('click', (event) => {
  const target = event.target
  if (!(target instanceof Element)) {
    return
  }

  const auditResultButton = target.closest<HTMLElement>('[data-crudo-audit-result]')
  if (auditResultButton) {
    cycleAuditResult(auditResultButton)
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

    const machine = machinesByTelar.get(telar)
    if (machine) {
      showPendingDetail(machine, machineButton)
      window.dispatchEvent(
        new CustomEvent('open-crudo-detail', {
          detail: { telar, machine },
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

document.addEventListener('pointerdown', (event) => {
  const target = event.target
  if (!(target instanceof Element)) {
    return
  }

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
document.addEventListener('livewire:init', observeQualityDefectEditors)
document.addEventListener('livewire:navigated', observeQualityDefectEditors)
document.addEventListener('DOMContentLoaded', observeQualityDefectEditors)

relativeTimeTimer = window.setInterval(updateRelativeTimes, 5000)

window.addEventListener('beforeunload', () => {
  if (relativeTimeTimer !== null) {
    window.clearInterval(relativeTimeTimer)
  }
  if (pendingDetailTimer !== null) {
    window.clearTimeout(pendingDetailTimer)
  }
  mutationObserver?.disconnect()
  auditDefectObserver?.disconnect()
})
