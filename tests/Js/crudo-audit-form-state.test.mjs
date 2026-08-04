import assert from 'node:assert/strict'
import test from 'node:test'

import { auditActionAvailability } from '../../resources/js/crudo/audit-form-state.ts'

const emptyDraft = () => ({
  checklistValues: ['', '', ''],
  observations: '',
  defects: Array.from({ length: 5 }, () => ({ defectId: '', pieces: '0' })),
})

test('both audit actions start blocked when the form has no data', () => {
  assert.deepEqual(auditActionAvailability(emptyDraft()), {
    canSaveAudit: false,
    canSaveStop: false,
  })
})

test('a checklist answer or observations enable only saving the audit', () => {
  const checklistDraft = emptyDraft()
  checklistDraft.checklistValues[0] = 'bien'

  assert.deepEqual(auditActionAvailability(checklistDraft), {
    canSaveAudit: true,
    canSaveStop: false,
  })

  const observationsDraft = emptyDraft()
  observationsDraft.observations = 'Revisar alineación'

  assert.deepEqual(auditActionAvailability(observationsDraft), {
    canSaveAudit: true,
    canSaveStop: false,
  })
})

test('a complete defect enables both actions', () => {
  const draft = emptyDraft()
  draft.defects[0] = { defectId: '15', pieces: '3' }

  assert.deepEqual(auditActionAvailability(draft), {
    canSaveAudit: true,
    canSaveStop: true,
  })
})

test('an incomplete defect keeps both actions blocked', () => {
  const missingPieces = emptyDraft()
  missingPieces.defects[0] = { defectId: '15', pieces: '0' }

  const missingDefect = emptyDraft()
  missingDefect.defects[0] = { defectId: '', pieces: '3' }

  assert.deepEqual(auditActionAvailability(missingPieces), {
    canSaveAudit: false,
    canSaveStop: false,
  })
  assert.deepEqual(auditActionAvailability(missingDefect), {
    canSaveAudit: false,
    canSaveStop: false,
  })
})
