let headerObserver: ResizeObserver | null = null
let restoreLayout: (() => void) | null = null

export const initializeResponsiveBoard = (): void => {
  headerObserver?.disconnect()
  restoreLayout?.()
  headerObserver = null
  restoreLayout = null

  const controls = document.getElementById('program-board-navbar-controls')
    ?? document.querySelector<HTMLElement>('[data-edicion-ordenes-actions]')
  const header = controls?.closest('nav')
  const main = document.querySelector<HTMLElement>('main.app-main')
  if (!header || !main) return

  const originalPadding = main.style.paddingTop
  const update = (): void => {
    const height = `${Math.ceil(header.getBoundingClientRect().height)}px`
    main.style.paddingTop = height
    main.style.setProperty('--program-board-header-height', height)
    const edition = main.querySelector<HTMLElement>('.edicion-ordenes')
    if (edition) {
      const style = getComputedStyle(edition)
      const controlsHeight = Array.from(edition.children)
        .filter((child) => !child.classList.contains('edicion-machine-grid'))
        .reduce((total, child) => total + child.getBoundingClientRect().height, 0)
      main.style.setProperty('--edicion-controls-height', `${Math.ceil(controlsHeight + parseFloat(style.paddingTop) + parseFloat(style.paddingBottom))}px`)
    }
  }

  restoreLayout = () => {
    main.style.paddingTop = originalPadding
    main.style.removeProperty('--program-board-header-height')
    main.style.removeProperty('--edicion-controls-height')
  }
  update()
  headerObserver = new ResizeObserver(update)
  headerObserver.observe(header)
  main.querySelectorAll('.edicion-filters, .edicion-summary').forEach((element) => headerObserver?.observe(element))
}
