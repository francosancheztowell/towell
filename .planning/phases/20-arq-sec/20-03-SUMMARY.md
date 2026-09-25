# 20-03 — AuthZ en modo auditar (SUMMARY)

**Rama:** `claude/20-02-03-errores-authz` · **ID:** SEC-05 · **Plan:** `20-03-PLAN.md` · **Mapa:** `20-03-MAPA-AUTHZ.md`. El owner lo aprobó con D3 = A (pendientes + SQL) y D4 = A (auditar también lo que ya valida el controller). Durante la ejecución aprobó además el ajuste por idrol, que se describe abajo.

## Commits

| Commit | Qué |
|---|---|
| `seguridad: plan 20-03 (AuthZ en modo auditar)` | Plan. |
| `seguridad: module.permission en modo auditar para escrituras fuera de Planeacion (SEC-05)` | Middleware, rutas, tests, mapa y ajuste del parser de `RutasDestructivasPermisoTest`. |

## Qué cambió

- **`EnsureModulePermission`**
  - Acepta un tercer parámetro: `module.permission:<acción>,<idrol>,auditar`.
  - Con permiso, pasa y no registra nada.
  - Sin permiso, registra una fila en `SYSMonAcceso` con `AccesoService::registrar('authz_denegaria', …)`:
    - `UsuarioId` y `NumeroEmpleado`;
    - `Motivo` = `crear · 45 · POST atadores.save` (acción, idrol, método y nombre de la ruta, o la URI si no tiene nombre).
    - Después deja pasar la request.
  - Deduplicación: `Cache::add` con TTL de 3600 s sobre usuario + método + ruta + acción + módulo. Deja como máximo una fila por hora.
  - Si fallan la caché o el registro, la request pasa igual.
  - Sin tercer parámetro, el comportamiento es idéntico al de antes (403 en JSON o HTML). Un modo desconocido también da 403.
- **Rutas:** 63 escrituras en modo auditar, siempre por idrol, en `urdido` (14), `engomado` (17), `tejido` (19), `tejedores` (9), `mecanicos` (4), `atadores` (1) y `telegram` (1).
  - Solo se agregó middleware; no se movió ni se renombró ninguna ruta.
  - `planeacion.php` no se tocó y el snapshot de PT no cambió.
- **Excepciones del owner:** el alta de paros y el finalizar paro quedan sin gate. Finalizar ya estaba exento en `RutasDestructivasPermisoTest::EXENTAS`.
- **22 rutas pendientes de idrol** (detalle en el mapa, con la consulta SQL):
  - 4 sin módulo identificable en el código;
  - 18 de las que solo se conoce el nombre (Programa Urdido, Programa Engomado, Andon, OT Diarias, Reporte Estado de Maquina). En todas ellas, salvo 2, el controller ya valida por nombre.

### Ajuste aprobado durante la ejecución

`tests/Feature/RutasDestructivasPermisoTest` (de otro track) exige gates **por idrol**, porque hay 5 nombres repetidos en SYSRoles (incidente "Utilería"). Su parser `gatesDe()` hacía `explode(',', $args, 2)` y leía `45,auditar` como módulo. Se cambió **una línea**, `explode(',', $args)`: la regla del test sigue igual. El owner aprobó el cambio.

## Evidencia

- `tests/Feature/Seguridad/ModuloPermisoAuditarTest.php` (7 tests):
  - sin `auditar`, el comportamiento no cambia (JSON 403, HTML 403 y pasa con permiso);
  - un modo desconocido da 403;
  - con permiso no registra;
  - sin permiso registra la fila exacta y deja pasar;
  - 3 requests dejan 1 fila; otra acción u otro usuario cuentan aparte; +61 min da una fila nueva;
  - con el kill switch de monitoreo no registra;
  - si la caché lanza, deja pasar.
- `tests/Feature/Seguridad/EscrituraFueraDePlaneacionTest.php` (guardián, 5 tests):
  - Toma toda escritura de un controller `App\` o de un closure de `routes/` que no esté en el snapshot de PT ni sea un redirect.
  - Exige `module.permission` o que esté en la lista justificada. Lo comprobé quitando el middleware de `telegram/send`: el test falla y nombra la ruta.
  - Además valida los parámetros (acción entre las 5, modo vacío o `auditar`), exige que el modo auditar vaya por idrol numérico y revisa la exención de paros.

| Check | Resultado |
|---|---|
| `php artisan test` | 1594 passed, 20955 assertions (incluye el snapshot de PT y `RutasDestructivasPermisoTest`) |
| `phpstan analyse` | `[OK] No errors` |
| `pint --test` | pass |
| `npm run build` / `npm run ratchet` | OK / "ninguna subió" |
| security-review | Sin hallazgos: ninguna ruta perdió enforce, el modo no depende del input y un modo desconocido da 403 |
| code-review | Sin hallazgos en 20-03 |

## Despliegue

- No hay migraciones: `SYSMonAcceso.Tipo` ya admite `authz_denegaria` (`database/sql/sysmon_tablas.sql`).
- `php artisan route:clear && php artisan optimize`.
- La deduplicación usa la caché por defecto (Redis en producción).
- Para revisar: `/admin/accesos` con filtro de tipo `authz_denegaria`, o

```sql
SELECT Motivo, COUNT(*) AS Filas, COUNT(DISTINCT UsuarioId) AS Usuarios
FROM dbo.SYSMonAcceso
WHERE Tipo = 'authz_denegaria' AND Fecha >= DATEADD(day, -14, GETDATE())
GROUP BY Motivo
ORDER BY Filas DESC;
```

## Pendientes

1. **Owner:** correr la consulta de §Pendientes del mapa en Laragon y pasar los idrol de las 22 rutas para agregarles `auditar`.
2. **SEC-06 (cada 19-xx):** después de 2 semanas sin falsos positivos, quitar `,auditar` para pasar a enforce, con tests de 403. Revisar primero las rutas marcadas en "Casos a propósito" del mapa: BPM, Desarrolladores, calificar línea de OT, upsert de cortes.
3. **Huecos para las 19-xx** (tabla al final del mapa): `atadores/save` con `supervisor`, `actualizar-campo-orden` de engomado, prioridades de engomado "para todos", `telegram/send` sin llamadores, el stub de cortes y el reenconado duplicado.
