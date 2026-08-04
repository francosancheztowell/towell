import assert from 'node:assert/strict'
import test from 'node:test'

import { AuditHistoryRequestCoordinator } from '../../resources/js/crudo/audit-history-request.ts'

test('opening another detail aborts the previous audit history request', () => {
  const coordinator = new AuditHistoryRequestCoordinator()
  const firstOwner = { isConnected: true }
  const secondOwner = { isConnected: true }

  const firstSignal = coordinator.begin(firstOwner)
  const secondSignal = coordinator.begin(secondOwner)

  assert.equal(firstSignal.aborted, true)
  assert.equal(secondSignal.aborted, false)
  assert.equal(coordinator.hasActiveRequest(), true)
})

test('closing a detail aborts the request retained by its disconnected form', () => {
  const coordinator = new AuditHistoryRequestCoordinator()
  const owner = { isConnected: true }
  const signal = coordinator.begin(owner)

  owner.isConnected = false

  assert.equal(coordinator.abortDisconnected(), true)
  assert.equal(signal.aborted, true)
  assert.equal(coordinator.hasActiveRequest(), false)
})

test('a stale completion cannot clear the request for the latest detail', () => {
  const coordinator = new AuditHistoryRequestCoordinator()
  const firstOwner = { isConnected: true }
  const secondOwner = { isConnected: true }
  const firstSignal = coordinator.begin(firstOwner)
  const secondSignal = coordinator.begin(secondOwner)

  coordinator.finish(firstOwner, firstSignal)

  assert.equal(coordinator.hasActiveRequest(), true)
  assert.equal(secondSignal.aborted, false)

  coordinator.finish(secondOwner, secondSignal)

  assert.equal(coordinator.hasActiveRequest(), false)
})

test('many reopen cycles retain at most the latest request', () => {
  const coordinator = new AuditHistoryRequestCoordinator()
  const signals = []

  for (let cycle = 0; cycle < 50; cycle++) {
    signals.push(coordinator.begin({ isConnected: true }))
  }

  assert.equal(signals.filter((signal) => !signal.aborted).length, 1)
  assert.equal(coordinator.hasActiveRequest(), true)
})
