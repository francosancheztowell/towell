import { notify } from '../utils/notifications.ts'

type NotificationDetail = {
  type?: 'success' | 'warning' | 'error'
  message?: string
}

const avisar = (detail: NotificationDetail): void => {
  const message = detail.message ?? 'Operación completada.'
  const type = detail.type ?? 'success'
  notify[type](message)
}

const toggleScrollLock = (open: boolean): void => {
  document.body.classList.toggle('program-board-modal-open', open)
}

export const initializeFeedback = (): void => {
  window.addEventListener('program-board-notify', (event) => {
    if (event instanceof CustomEvent) {
      avisar(event.detail as NotificationDetail)
    }
  })

  window.addEventListener('program-board-modal', (event) => {
    if (event instanceof CustomEvent) {
      toggleScrollLock(Boolean(event.detail?.open))
    }
  })

  window.addEventListener('beforeunload', () => toggleScrollLock(false))
}
