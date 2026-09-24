import { initializeFeedback } from './feedback'
import { initializeFullscreen } from './fullscreen'
import { initializePrioritySort } from './priority-sort'
import { initializeResponsiveBoard } from './responsive'
import {
  destroySortableBoard,
  initializeSortableBoard,
  scheduleSortableBoard,
} from './sortable-board'
import { programBoardWindow } from './types'

let listenersInitialized = false
let livewireHookInitialized = false

const bootstrap = (): void => {
  initializeSortableBoard()
  initializeResponsiveBoard()

  if (!listenersInitialized) {
    listenersInitialized = true
    initializeFeedback()
    initializeFullscreen()
    initializePrioritySort()

    window.addEventListener('program-board-updated', scheduleSortableBoard)
    document.addEventListener('livewire:navigated', () => {
      scheduleSortableBoard()
      initializeResponsiveBoard()
    })
    window.addEventListener('beforeunload', destroySortableBoard)
  }

  if (!livewireHookInitialized && programBoardWindow.Livewire?.hook) {
    livewireHookInitialized = true
    programBoardWindow.Livewire.hook('morph.updated', scheduleSortableBoard)
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootstrap, { once: true })
} else {
  bootstrap()
}

document.addEventListener('livewire:init', bootstrap)
