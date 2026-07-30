import Sortable, { type SortableEvent } from 'sortablejs'
import { programBoardWindow } from './types'

const BOARD_SELECTOR = '[data-program-board]'
const LIST_SELECTOR = '[data-program-lane-list]'
const ORDER_SELECTOR = '[data-program-order]'

let instances: Sortable[] = []
let scheduled = false

const componentFor = (element: Element) => {
  const wireRoot = element.closest<HTMLElement>('[wire\\:id]')
  const componentId = wireRoot?.getAttribute('wire:id')

  return componentId ? programBoardWindow.Livewire?.find?.(componentId) : null
}

const orderIds = (list: HTMLElement): string[] => Array
  .from(list.querySelectorAll<HTMLElement>(`:scope > ${ORDER_SELECTOR}`))
  .map((order) => order.dataset.orderId)
  .filter((id): id is string => Boolean(id))

const initializeList = (list: HTMLElement): Sortable => {
  let originalIds: string[] = []
  let sourceId: number | null = null
  let sortable: Sortable

  sortable = new Sortable(list, {
    animation: 150,
    chosenClass: 'sortable-chosen',
    dataIdAttr: 'data-order-id',
    direction: 'vertical',
    fallbackOnBody: true,
    ghostClass: 'sortable-ghost',
    handle: '[data-drag-handle]',
    swapThreshold: 0.65,
    onStart: (event: SortableEvent) => {
      originalIds = orderIds(list)
      sourceId = Number(event.item.dataset.orderId)
      void componentFor(list)?.call('setInteractionPaused', true)
    },
    onEnd: (event: SortableEvent) => {
      const targetId = event.newIndex === undefined
        ? null
        : Number(originalIds[event.newIndex])
      const component = componentFor(list)

      sortable.sort(originalIds, false)

      if (
        !component
        || !sourceId
        || !targetId
        || sourceId === targetId
        || event.from !== event.to
      ) {
        void component?.call('setInteractionPaused', false)
        return
      }

      void component.call('reorder', sourceId, targetId)
    },
  })

  return sortable
}

export const initializeSortableBoard = (): void => {
  instances.forEach((instance) => instance.destroy())
  instances = []

  const board = document.querySelector<HTMLElement>(BOARD_SELECTOR)
  if (!board) {
    return
  }

  board.querySelectorAll<HTMLElement>(LIST_SELECTOR).forEach((list) => {
    if (list.querySelector(ORDER_SELECTOR)) {
      instances.push(initializeList(list))
    }
  })
}

export const scheduleSortableBoard = (): void => {
  if (scheduled) {
    return
  }

  scheduled = true
  window.requestAnimationFrame(() => {
    scheduled = false
    initializeSortableBoard()
  })
}

export const destroySortableBoard = (): void => {
  instances.forEach((instance) => instance.destroy())
  instances = []
}
