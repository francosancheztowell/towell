export type AuditDefectDraft = {
  defectId: string
  pieces: string
}

export type AuditFormDraft = {
  checklistValues: string[]
  observations: string
  defects: AuditDefectDraft[]
}

export type AuditActionAvailability = {
  canSaveAudit: boolean
  canSaveStop: boolean
}

export const auditActionAvailability = (draft: AuditFormDraft): AuditActionAvailability => {
  const hasChecklistAnswer = draft.checklistValues.some((value) => value.trim() !== '')
  const hasObservations = draft.observations.trim() !== ''
  const hasCompleteDefect = draft.defects.some((defect) => {
    const defectId = defect.defectId.trim()
    const pieces = Number.parseInt(defect.pieces.trim(), 10)

    return defectId !== '' && Number.isInteger(pieces) && pieces > 0
  })

  return {
    canSaveAudit: hasChecklistAnswer || hasObservations || hasCompleteDefect,
    canSaveStop: hasCompleteDefect,
  }
}
