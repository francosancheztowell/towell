import assert from 'node:assert/strict'
import test from 'node:test'

import {
  hidePendingDetail,
  isIntentionalMachineActivation,
} from '../../resources/js/crudo/pending-detail.ts'

test('the pending overlay does not keep its hidden observer alive', () => {
  const mutationQueue = []
  let hidden = false

  const pending = {}
  Object.defineProperty(pending, 'hidden', {
    get: () => hidden,
    set: (value) => {
      hidden = value
      mutationQueue.push('hidden')
    },
  })

  assert.equal(hidePendingDetail(pending), true)

  let observerCallbacks = 0
  while (mutationQueue.length > 0) {
    mutationQueue.shift()
    observerCallbacks++

    assert.ok(observerCallbacks < 10, 'the hidden mutation observer did not terminate')
    hidePendingDetail(pending)
  }

  assert.equal(observerCallbacks, 1)
  assert.equal(hidden, true)
})

test('a pointer click only opens the machine where the gesture started', () => {
  assert.equal(isIntentionalMachineActivation('201', '201', 1), true)
  assert.equal(isIntentionalMachineActivation(null, '201', 1), false)
  assert.equal(isIntentionalMachineActivation('202', '201', 1), false)
})

test('keyboard activation still opens the focused machine', () => {
  assert.equal(isIntentionalMachineActivation(null, '201', 0), true)
})
