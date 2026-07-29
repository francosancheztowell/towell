type MachineSnapshot = Map<string, string>

const DASHBOARD_SELECTOR = '[data-crudo-dashboard]'
const MACHINE_SELECTOR = '[data-crudo-machine]'
const RELATIVE_TIME_SELECTOR = '[data-crudo-relative-time]'

let previousMachines: MachineSnapshot = new Map()
let observedDashboard: HTMLElement | null = null
let mutationObserver: MutationObserver | null = null
let relativeTimeTimer: number | null = null

const machineSnapshot = (root: ParentNode): MachineSnapshot => {
  const snapshot: MachineSnapshot = new Map()

  root.querySelectorAll<HTMLElement>(MACHINE_SELECTOR).forEach((machine) => {
    const telar = machine.dataset.telar
    const signature = machine.dataset.signature

    if (telar && signature) {
      snapshot.set(telar, signature)
    }
  })

  return snapshot
}

const highlightChanges = (root: HTMLElement): void => {
  const current = machineSnapshot(root)

  if (previousMachines.size > 0) {
    current.forEach((signature, telar) => {
      if (previousMachines.get(telar) === signature) {
        return
      }

      const machine = Array.from(root.querySelectorAll<HTMLElement>(MACHINE_SELECTOR))
        .find((candidate) => candidate.dataset.telar === telar)

      if (!machine) {
        return
      }

      machine.classList.remove('is-changed')
      window.requestAnimationFrame(() => {
        machine.classList.add('is-changed')
        window.setTimeout(() => machine.classList.remove('is-changed'), 1000)
      })
    })
  }

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
  previousMachines = machineSnapshot(dashboard)

  let scheduled = false
  mutationObserver = new MutationObserver(() => {
    if (scheduled || !observedDashboard) {
      return
    }

    scheduled = true
    window.requestAnimationFrame(() => {
      scheduled = false
      if (observedDashboard) {
        highlightChanges(observedDashboard)
        updateRelativeTimes()
      }
    })
  })

  mutationObserver.observe(dashboard, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['data-signature', 'data-state'],
  })

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
