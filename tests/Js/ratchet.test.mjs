import assert from 'node:assert/strict'
import { test } from 'node:test'

import { METRICS, compare } from '../../scripts/ratchet.mjs'

test('compare marca como subida cualquier conteo mayor o metrica nueva', () => {
  const { up, down } = compare({ a: 5, b: 3 }, { a: 6, b: 2, c: 1 })
  assert.deepEqual(up.map((u) => u.name), ['a', 'c'])
  assert.deepEqual(down, [{ name: 'b', before: 3, now: 2 }])
})

test('compare no marca nada si todo queda igual', () => {
  assert.deepEqual(compare({ a: 1 }, { a: 1 }), { up: [], down: [] })
})

test('<script> inline no cuenta los que tienen src', () => {
  const count = METRICS['<script> inline en blade'].count
  const blade = `
    <script src="/js/a.js"></script>
    <script>console.log(1)</script>
    <script type="module">x()</script>
    @vite('resources/js/app.js')`
  assert.equal(count(blade), 2)
})

test('getMessage() solo cuenta dentro del argumento de response()->json', () => {
  const count = METRICS['getMessage() en response()->json'].count
  const php = `
    Log::error($e->getMessage());
    return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
    return response()->json([
        'error' => 'Fallo (ver log)',
        'detalle' => $ex->getMessage(),
    ]);
    return response()->json(['ok' => true]);`
  assert.equal(count(php), 2)
})

test('innerHTML cuenta asignaciones, no comparaciones', () => {
  const count = METRICS['innerHTML ='].count
  assert.equal(count('el.innerHTML = x; el.innerHTML += y; if (el.innerHTML === z) {}'), 2)
})

test('SQL crudo con $interpolado cuenta comillas dobles con variable, no bindings', () => {
  const count = METRICS['SQL crudo con $interpolado'].count
  const php = `
    DB::select("SELECT * FROM T WHERE Id = $id");
    $q->whereRaw("{$expr} IS NOT NULL");
    $q->whereRaw('Id = ?', [$id]);
    $q->selectRaw("
        SUM($columna) as total
    ");
    DB::select("SELECT 1");`
  assert.equal(count(php), 3)
})
