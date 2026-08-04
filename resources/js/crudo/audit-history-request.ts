type ConnectedOwner = {
  isConnected: boolean
}

type ActiveRequest<Owner extends ConnectedOwner> = {
  owner: Owner
  controller: AbortController
}

export class AuditHistoryRequestCoordinator<Owner extends ConnectedOwner = ConnectedOwner> {
  private activeRequest: ActiveRequest<Owner> | null = null

  public begin(owner: Owner): AbortSignal {
    this.abortActive()

    const controller = new AbortController()
    this.activeRequest = { owner, controller }

    return controller.signal
  }

  public finish(owner: Owner, signal: AbortSignal): void {
    if (
      this.activeRequest?.owner === owner
      && this.activeRequest.controller.signal === signal
    ) {
      this.activeRequest = null
    }
  }

  public abortDisconnected(): boolean {
    if (!this.activeRequest || this.activeRequest.owner.isConnected) {
      return false
    }

    this.abortActive()

    return true
  }

  public abortAll(): void {
    this.abortActive()
  }

  public hasActiveRequest(): boolean {
    return this.activeRequest !== null
  }

  private abortActive(): void {
    this.activeRequest?.controller.abort()
    this.activeRequest = null
  }
}
