import { programBoardWindow } from './types'

// Arrastrar filas en el dialogo de prioridad. Delegado en document porque el
// dialogo vive en un @teleport que Livewire vuelve a pintar.
const BODY_SELECTOR = '#priority-sort-body'

const targetElement = (event: Event): Element | null =>
  event.target instanceof Element ? event.target : null

export const initializePrioritySort = (): void => {
  document.addEventListener('dragstart', (event) => {
    const target = targetElement(event)
    if (!target || target.closest('select, option')) {
      return
    }
    const row = target.closest<HTMLTableRowElement>(`${BODY_SELECTOR} tr[data-priority-id]`)
    if (!row) {
      return
    }
    row.classList.add('is-dragging')
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move'
      event.dataTransfer.setData('text/plain', row.dataset.priorityId ?? '')
    }
  })

  document.addEventListener('dragover', (event) => {
    if (targetElement(event)?.closest(BODY_SELECTOR)) {
      event.preventDefault()
    }
  })

  document.addEventListener('drop', (event) => {
    const target = targetElement(event)
    const body = target?.closest<HTMLElement>(BODY_SELECTOR)
    if (!target || !body) {
      return
    }
    event.preventDefault()
    const dragging = body.querySelector<HTMLTableRowElement>('tr.is-dragging')
    const over = target.closest<HTMLTableRowElement>('tr[data-priority-id]')
    if (!dragging || !over || dragging === over) {
      return
    }
    const rect = over.getBoundingClientRect()
    const after = event.clientY > rect.top + rect.height / 2
    body.insertBefore(dragging, after ? over.nextSibling : over)
    const ids = Array.from(body.querySelectorAll<HTMLTableRowElement>('tr[data-priority-id]')).map(
      (row) => row.dataset.priorityId,
    )
    const boardId = body.closest<HTMLElement>('[data-board-id]')?.dataset.boardId
    if (boardId) {
      void programBoardWindow.Livewire?.find?.(boardId)?.call('reorderPriorities', ids)
    }
  })

  document.addEventListener('dragend', (event) => {
    targetElement(event)?.closest('tr')?.classList.remove('is-dragging')
  })
}
