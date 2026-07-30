export interface ProgramBoardComponent {
  call(method: string, ...params: unknown[]): Promise<unknown>
}

export interface ProgramBoardLivewire {
  find?(id: string): ProgramBoardComponent | null
  hook?(name: string, callback: (...params: unknown[]) => void): void
}

export interface ProgramBoardNotifications {
  success(message: string): void
  warning(message: string): void
  error(message: string): void
}

export type ProgramBoardWindow = Window & {
  Livewire?: ProgramBoardLivewire
  notify?: ProgramBoardNotifications
  Swal?: {
    fire(options: Record<string, unknown>): Promise<unknown>
  }
}

export const programBoardWindow = window as ProgramBoardWindow
