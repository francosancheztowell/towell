type MachineSnapshot = Map<string, string>

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
const MACHINE_GRID_SELECTOR = '[data-crudo-machine-grid]'
const MACHINE_SELECTOR = '[data-crudo-machine]'
const RELATIVE_TIME_SELECTOR = '[data-crudo-relative-time]'
const AUDIT_DEFECT_EDITOR_SELECTOR = '[data-crudo-audit-defects]'
const AUDIT_CONTENT_SELECTOR = '[data-crudo-audit-content]'
const QUALITY_DEFECT_SELECT_SELECTOR = '[data-crudo-quality-defect-select]'

const formatInteger = (value: number): string => Math.round(value).toLocaleString('es-MX')
const formatDecimal = (value: number, decimals: number = 1): string => {
  const factor = 10 ** decimals
  return (Math.round(value * factor) / factor).toLocaleString('es-MX', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })
}

let previousMachines: MachineSnapshot = new Map()
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

const machineSnapshot = (machines: Machine[]): MachineSnapshot => {
  const snapshot: MachineSnapshot = new Map()

  machines.forEach((machine) => {
    const signature = `${machine.state}:${machine.pieces}:${machine.seconds}:${machine.kilos}`
    snapshot.set(machine.telar, signature)
  })

  return snapshot
}

const findMachineButton = (telar: string): HTMLElement | null => {
  return document.querySelector<HTMLElement>(`${MACHINE_SELECTOR}[data-telar="${CSS.escape(telar)}"]`)
}

const updateMachineCard = (machine: Machine): void => {
  const button = findMachineButton(machine.telar)
  if (!button) {
    return
  }

  const signature = `${machine.state}:${machine.pieces}:${machine.seconds}:${machine.kilos}`
  const previousSignature = button.dataset.signature

  button.dataset.state = machine.state
  button.dataset.signature = signature
  button.setAttribute('aria-label', `Abrir detalle del telar ${machine.telar}, estado ${machine.stateLabel}`)

  const quality = button.querySelector<HTMLElement>('[data-crudo-quality]')
  if (quality) {
    quality.textContent = `${formatInteger(machine.qualityPercent)}%`
  }

  const kilos = button.querySelector<HTMLElement>('[data-crudo-kilos]')
  if (kilos) {
    kilos.textContent = `${formatDecimal(machine.kilos)} kg`
  }

  const name = button.querySelector<HTMLElement>('[data-crudo-name]')
  if (name) {
    name.textContent = machine.name
  }

  const tooltipMetrics = button.querySelector<HTMLElement>('[data-crudo-tooltip-metrics]')
  if (tooltipMetrics) {
    tooltipMetrics.textContent = `${formatInteger(machine.pieces)} piezas · ${formatInteger(machine.seconds)} segundas`
  }

  if (previousSignature !== signature) {
    button.classList.remove('is-changed')
    window.requestAnimationFrame(() => {
      button.classList.add('is-changed')
      window.setTimeout(() => button.classList.remove('is-changed'), 1000)
    })
  }
}

const updateDashboardCards = (): void => {
  const machines = parseMachinesJson()
  const current = machineSnapshot(machines)

  machines.forEach((machine) => {
    updateMachineCard(machine)
  })

  previousMachines = current
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

  const machineGrid = document.querySelector<HTMLElement>(MACHINE_GRID_SELECTOR)
  if (machineGrid) {
    mutationObserver.observe(machineGrid, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['data-telar'],
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

  if (auditDefectObserver || !document.body) {
    return
  }

  auditDefectObserver = new MutationObserver((mutations) => {
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

document.addEventListener('click', (event) => {
  const target = event.target
  if (!(target instanceof Element)) {
    return
  }

  const machineButton = target.closest<HTMLElement>(MACHINE_SELECTOR)
  if (machineButton) {
    const telar = machineButton.dataset.telar
    if (!telar) {
      return
    }

    const machines = parseMachinesJson()
    const machine = machines.find((candidate) => candidate.telar === telar)
    if (machine) {
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
  mutationObserver?.disconnect()
  auditDefectObserver?.disconnect()
})
