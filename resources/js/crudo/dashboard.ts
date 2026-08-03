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

const DASHBOARD_SELECTOR = '[data-crudo-dashboard]'
const MACHINE_DATA_SELECTOR = '[data-crudo-machines-data]'
const MACHINE_GRID_SELECTOR = '[data-crudo-machine-grid]'
const MACHINE_SELECTOR = '[data-crudo-machine]'
const RELATIVE_TIME_SELECTOR = '[data-crudo-relative-time]'

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
let relativeTimeTimer: number | null = null

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

relativeTimeTimer = window.setInterval(updateRelativeTimes, 5000)

window.addEventListener('beforeunload', () => {
  if (relativeTimeTimer !== null) {
    window.clearInterval(relativeTimeTimer)
  }
  mutationObserver?.disconnect()
})
