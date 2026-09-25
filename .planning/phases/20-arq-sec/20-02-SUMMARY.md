# 20-02 — Errores JSON 5xx sin detalle y con trace_id (SUMMARY)

**Rama:** `claude/20-02-03-errores-authz` (base `claude/friendly-hopper-506bg9`) · **ID:** SEC-04 · **Plan:** `20-02-PLAN.md`. El owner lo aprobó con D1 = A (Livewire sigue con la página HTML 500) y D2 = sí (`apiErrorResponse` usa el evento como trace_id).

## Commits

| Commit | Qué |
|---|---|
| `seguridad: plan 20-02 (errores JSON 5xx)` | Plan. |
| `seguridad: render central de 5xx en JSON sin detalle y con trace_id (SEC-04)` | Render central, `serverErrorResponse()` en el trait y tests. |
| `seguridad: trace_id del evento de la propia excepcion, no del ultimo de la request` | Hallazgo de code-review, con su test de regresión. |

## Qué cambió

- **`bootstrap/app.php`**
  - Nuevo `render(Throwable)`. Actúa solo con `APP_DEBUG=false`, cuando la request espera JSON (`expectsJson()`: `window.http`, axios, fetch con `Accept: application/json`) y cuando el status es 500 o mayor.
  - No toca `ValidationException`, `AuthenticationException`, `HttpResponseException` ni las `HttpException` menores de 500.
  - Responde `{success:false, message: MENSAJE_ERROR_SERVIDOR, trace_id}` con el mismo status y los mismos headers de la `HttpException` (p. ej. `Retry-After` en un 503).
  - El callback de `report()` ahora guarda `excepción => Id de evento` en un `WeakMap` del contenedor (`monitoreo.eventos_por_excepcion`).
- **`HandlesApiErrors`**
  - Nuevo `MENSAJE_ERROR_SERVIDOR`: "Ocurrió un error en el servidor. Si continúa, comparte el código de referencia con Sistemas."
  - Nuevos `serverErrorResponse()` y `traceIdDeError($e)`.
  - `trace_id` = Id de `SYSMonErrorEvento` registrado **para esa excepción** (o su `getPrevious()`). Si no hay evento, un uuid que se escribe en el log con la clase y el mensaje, para poder buscarlo.
  - `apiErrorResponse()` conserva su firma y su respuesta; solo su `trace_id` pasa de uuid suelto a Id del evento.

## Qué no cambió (verificado con tests)

- Con `APP_DEBUG=true`, el JSON trae el detalle de Laravel (`message`, `exception`, `file`, `line`, `trace`), como antes.
- 422, 403, 404 y 419 no cambian. Tampoco la página HTML 500, que sigue mostrando `Código de referencia: #<evento>`.
- **Livewire (D1):** no manda `Accept: application/json`, así que sigue recibiendo la página 500 en su modal: sin detalle y con el mismo código.
- No se tocaron los catch de los controllers (son SEC-07 de cada 19-xx), `ErrorRecorder` ni `resources/js`.

## Qué se filtraba antes

- Con `APP_DEBUG=false`, Laravel devolvía `getMessage()` de las `HttpException` 5xx: `abort(500, $e->getMessage())`, `abort(503, …)`.
- Las demás excepciones daban un `{"message":"Server Error"}` en inglés y sin código.

## Evidencia

`tests/Feature/Seguridad/ErroresJsonServidorTest.php` tiene 10 tests. Antes de implementar fallaban 6, y los de 4xx ya pasaban:

- **RuntimeException:** el JSON trae exactamente `success`, `message` y `trace_id`, sin rastro del mensaje ni de la ruta.
- **QueryException real con binding secreto:** la respuesta no contiene ni el SQL, ni el binding, ni `SQLSTATE`.
- **`abort(500)` y `abort(503)`:** mensaje genérico, sin el detalle del abort y conservando `Retry-After`.
- **Kill switch de monitoreo:** el `trace_id` es un uuid.
- **Dos errores en la misma request** (el primero registrado, el segundo ignorado): el `trace_id` no es el del primero. Este test falla contra el commit anterior.
- `APP_DEBUG=true` conserva el detalle.
- 422, 403, 404 y 419 intactos.
- La página HTML 500 no cambia.
- Livewire recibe la página 500 sin detalle.
- `apiErrorResponse` devuelve como `trace_id` el Id del evento.

| Check | Resultado |
|---|---|
| `php artisan test` | 1594 passed (al cierre de 20-03) |
| `phpstan analyse` | `[OK] No errors` |
| `pint --test` (PHP cambiados) | pass |
| `npm run build` / `npm run ratchet` | OK / "ninguna subió" |
| security-review | Sin hallazgos |
| code-review | 1 hallazgo (trace_id de otro error de la misma request), corregido |

## Despliegue

`php artisan optimize:clear` y después `optimize`. No hay migraciones, variables `.env` ni SQL.

## Pendientes

- **SEC-07 (19-xx):** los ~401 catch con `getMessage()` siguen devolviendo detalle con status 500 **sin pasar por el handler**. El render central no los alcanza, porque no son excepciones. Cada 19-xx los cambia por dejar burbujear la excepción o por `apiErrorResponse()`.
- **HANDOFF §5:** la página HTML 500 lee `EstadoRequest::eventoId` y puede mostrar el código de otro error de la misma request.
