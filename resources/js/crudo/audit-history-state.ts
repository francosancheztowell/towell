export const shouldSkipAuditHistoryLoad = (
  state: string | undefined,
  stateUrl: string | undefined,
  currentUrl: string,
  force: boolean,
): boolean => !force
  && stateUrl === currentUrl
  && (state === 'loading' || state === 'loaded')

export const isCurrentAuditHistoryRequest = (
  requestUrl: string,
  currentUrl: string | undefined,
): boolean => requestUrl !== '' && requestUrl === currentUrl

export const auditBelongsToScope = (
  auditTelar: string | null | undefined,
  auditDate: string | null | undefined,
  expectedTelar: string,
  expectedDate: string,
): boolean => String(auditTelar ?? '').trim() === expectedTelar.trim()
  && String(auditDate ?? '').trim() === expectedDate.trim()
