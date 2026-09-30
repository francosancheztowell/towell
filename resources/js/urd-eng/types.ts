export interface ProgramBoardComponent {
  call(method: string, ...params: unknown[]): Promise<unknown>
}

export interface ProgramBoardLivewire {
  find?(id: string): ProgramBoardComponent | null
  hook?(name: string, callback: (...params: unknown[]) => void): void
}

export type ProgramBoardWindow = Window & {
  Livewire?: ProgramBoardLivewire
}

export const programBoardWindow = window as ProgramBoardWindow
