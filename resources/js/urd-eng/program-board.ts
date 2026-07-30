import { initializeFeedback } from './feedback'
import { initializeFullscreen } from './fullscreen'
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

  if (!listenersInitialized) {
    listenersInitialized = true
    initializeFeedback()
    initializeFullscreen()

    window.addEventListener('program-board-updated', scheduleSortableBoard)
    document.addEventListener('livewire:navigated', scheduleSortableBoard)
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
