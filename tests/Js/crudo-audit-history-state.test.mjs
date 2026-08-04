import assert from 'node:assert/strict'
import test from 'node:test'

import {
  auditBelongsToScope,
  isCurrentAuditHistoryRequest,
  shouldSkipAuditHistoryLoad,
} from '../../resources/js/crudo/audit-history-state.ts'

test('loaded history is reusable only for the same URL', () => {
  assert.equal(shouldSkipAuditHistoryLoad('loaded', '/telar/204/hoy', '/telar/204/hoy', false), true)
  assert.equal(shouldSkipAuditHistoryLoad('loaded', '/telar/204/hoy', '/telar/205/hoy', false), false)
  assert.equal(shouldSkipAuditHistoryLoad('loading', '/telar/204/hoy', '/telar/205/hoy', false), false)
  assert.equal(shouldSkipAuditHistoryLoad('loaded', '/telar/204/hoy', '/telar/204/hoy', true), false)
})

test('a response can render only while its request URL remains current', () => {
  assert.equal(isCurrentAuditHistoryRequest('/telar/204/hoy', '/telar/204/hoy'), true)
  assert.equal(isCurrentAuditHistoryRequest('/telar/204/hoy', '/telar/205/hoy'), false)
})

test('history items must match both the open loom and today', () => {
  assert.equal(auditBelongsToScope('204', '2026-08-04', '204', '2026-08-04'), true)
  assert.equal(auditBelongsToScope('205', '2026-08-04', '204', '2026-08-04'), false)
  assert.equal(auditBelongsToScope('204', '2026-08-03', '204', '2026-08-04'), false)
})
