import assert from 'node:assert/strict'
import test from 'node:test'

import { hidePendingDetail } from '../../resources/js/crudo/pending-detail.ts'

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
