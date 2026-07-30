const BOARD_SELECTOR = '[data-program-board-shell]'
const BUTTON_SELECTOR = '[data-program-fullscreen]'

const toggleFullscreen = async (): Promise<void> => {
  if (document.fullscreenElement) {
    await document.exitFullscreen()
    return
  }

  const board = document.querySelector<HTMLElement>(BOARD_SELECTOR)
  if (board && document.fullscreenEnabled) {
    await board.requestFullscreen()
  }
}

const syncFullscreenButton = (): void => {
  const button = document.querySelector<HTMLButtonElement>(BUTTON_SELECTOR)
  const icon = button?.querySelector<HTMLElement>('i')
  if (!button || !icon) {
    return
  }

  const fullscreen = Boolean(document.fullscreenElement)
  icon.classList.toggle('fa-expand', !fullscreen)
  icon.classList.toggle('fa-compress', fullscreen)
  button.title = fullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'
  button.setAttribute(
    'aria-label',
    fullscreen ? 'Salir de pantalla completa' : 'Mostrar tablero en pantalla completa',
  )
}

export const initializeFullscreen = (): void => {
  document.addEventListener('click', (event) => {
    const target = event.target
    if (target instanceof Element && target.closest(BUTTON_SELECTOR)) {
      void toggleFullscreen()
    }
  })

  document.addEventListener('fullscreenchange', syncFullscreenButton)
}
