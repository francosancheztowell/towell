import { programBoardWindow } from './types'

type NotificationDetail = {
  type?: 'success' | 'warning' | 'error'
  message?: string
}

const notify = (detail: NotificationDetail): void => {
  const message = detail.message ?? 'Operación completada.'
  const type = detail.type ?? 'success'

  if (programBoardWindow.Swal) {
    void programBoardWindow.Swal.fire({
      toast: true,
      position: 'top-end',
      icon: type,
      title: message,
      showConfirmButton: false,
      timer: type === 'error' ? 4200 : 2200,
      timerProgressBar: true,
    })
    return
  }

  const client = programBoardWindow.notify
  if (client) {
    client[type](message)
    return
  }

  if (type === 'error') {
    window.alert(message)
  }
}

const toggleScrollLock = (open: boolean): void => {
  document.body.classList.toggle('program-board-modal-open', open)
}

export const initializeFeedback = (): void => {
  window.addEventListener('program-board-notify', (event) => {
    if (event instanceof CustomEvent) {
      notify(event.detail as NotificationDetail)
    }
  })

  window.addEventListener('program-board-modal', (event) => {
    if (event instanceof CustomEvent) {
      toggleScrollLock(Boolean(event.detail?.open))
    }
  })

  window.addEventListener('beforeunload', () => toggleScrollLock(false))
}
