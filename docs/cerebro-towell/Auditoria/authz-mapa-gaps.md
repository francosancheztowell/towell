# AuthZ — mapa de gaps (menú vs rutas)

**Fecha:** 2026-09-18 ~11:05 CT.  
**Evidencia:** `routes/web.php`, `routes/public.php`, `app/Services/ModuloService.php`, helpers permisos, auditoría §4.3, LA-GTECLAVE `userCan|can:` en `routes/` = **0**.

---

## Cómo cree el negocio que funciona

```
Usuario login → SYSUsuariosRoles (acceso/crear/modificar/eliminar/registrar)
             → ModuloService arma menú (caché modulos_v3)
             → Usuario solo ve módulos permitidos
             → Por tanto “no puede” pegarle a otras APIs
```

**Eso es falso en el runtime.** El menú es **navegación**, no **autorización HTTP**.

---

## Cómo funciona de verdad

### 1) Dispatcher

```text
routes/web.php
  require public.php          ← SIN auth (guest + agujeros)
  Route::middleware(['auth']) ← ÚNICO candado de grupo
    require modules/*.php     ← planeacion, tejido, urdido, engomado, …
```

- `rg userCan|can:` en `routes/` (LA-GTECLAVE) = **0**.  
- No hay policies Laravel cableadas en el router.  
- Cualquier sesión autenticada puede **HTTP** a casi todas las APIs de módulos.

### 2) Menú = `ModuloService` + `SYSRoles`

- Prefijo caché: **`modulos_v3`** (+ `APP_ENV`). TTL 3600s.  
- Join `SYSRoles` ⟷ `SYSUsuariosRoles` donde `acceso = true`.  
- Flags por usuario: `acceso`, `crear`, `modificar`, `eliminar`, `registrar`.  
- Typo histórico en plantilla módulo: `SYSRoles.reigstrar` vs columna usuario `registrar` (`userCan('registrar')` lee la correcta).  
- Docs/CLAUDE a veces dicen `modulos_v2` — **mentira** (BUG-019).  
- Si no llaman `limpiarCacheUsuario()` tras cambiar permisos → menú stale.

### 3) `userCan` en vistas (parcial)

- Aparece en ~10–14 blades (auditoría).  
- El resto **muestra** botones que el API no 403-ea.  
- Algunos controllers puntuales sí llaman `userCan` (ej. un `MecActividadesController` en `Catalogos/`); el gemelo en `mecanicos/` **no** — trampa si se re-enlaza (BUG-021).

### 4) Agujeros **sin** sesión (P0)

| Ruta | Qué hace | Evidencia |
|------|----------|-----------|
| `GET/POST/PUT/DELETE /modulos-sin-auth…` | CRUD módulos / plantillas permiso | `public.php` L25–30 → `ModulosController` |
| `GET /obtener-empleados/{area}` | Lista empleados por área | `public.php` L19–20 |

CSRF del stack `web` aplica a POST, pero **GET anónimo** ya entrega superficie de administración de menú.

---

## Grupos de rutas de alto riesgo (cualquier login)

Todas bajo `middleware(['auth'])` **sin** `userCan` de ruta. Prioridad por daño:

### P0 / P1 — mutan planta o AX

| Grupo (module file) | Ejemplos de superficie | Por qué duele |
|---------------------|------------------------|---------------|
| `planeacion.php` | Liberar órdenes, mover, finalizar, L.Mat guardar, codificación, calendario | Re-encolar AX (`CreaProd`), borrar `FechaFinaliza`, L.Mat inválida |
| `urdido.php` | `POST …/actualizar-status`, prioridades, calidad | Estado urdido + cascada engomado |
| `engomado.php` | `POST …/actualizar-status`, verificar-en-proceso, producción | Engomar sin urdido finalizado (legacy) |
| `programa-urd-eng.php` | `POST` crear órdenes, reservar, liberar telar, Karl Mayer | Reserva inventario / órdenes falsas |
| `mantenimiento.php` | `POST` paros store/update, APIs máquinas/fallas; `Route::view` solicitudes | Paros fantasma; userId=6 en departamentos |
| `mecanicos.php` | OT mecánicos, actividades | Órdenes de trabajo planta |
| `atadores.php` | Ciclo montado / autorizar | Inventario telares |
| `tejido.php` | Secuencias / cortes eficiencia | Datos eficiencia / inventario |
| `tejedores.php` | Desarrolladores / captura | CatCodificados FLOAT |
| `crudo.php` / `trazabilidad.php` | Mutaciones piso / flogs | Menos AuthZ fina; flogs→TI |
| `configuracion.php` | Usuarios, módulos, permisos (el de **dentro** de auth) | Quien tenga login admin-ish pisa roles; menú no basta |
| `telegram.php` / `redbooth.php` / `producto-terminado.php` | Sends / bridges | Spam / fuga si hay tokens |

### Especial: vistas sin controller

`mantenimiento.php`: `Route::view` solicitudes + reporte-fallos — **misma Blade, dos URLs, cero permiso de módulo en ruta**.

---

## Diagrama mental

```
                 ┌──────────── public.php ────────────┐
Anónimo ────────►│ login │ empleados │ modulos-sin-auth│  ← P0
                 └────────────────────────────────────┘
Session ────────► middleware auth ONLY
                      │
                      ├─ Menú (ModuloService / SYSRoles)  ← cosmética
                      │
                      └─ POST liberar / status eng / paros / reservar …
                            sin userCan en route            ← gap real
```

---

## Remediación (orden, no Livewire)

1. **Eliminar o autenticar** `/modulos-sin-auth` + empleados. Tratar hits históricos como incidente.  
2. Middleware por módulo en cada `routes/modules/*.php`: `userCan('acceso', '…')` denegar default.  
3. Mutaciones sensibles: exigir `crear`/`modificar`/`registrar` según verbo.  
4. Quitar hardcode `userId === 6`.  
5. Tests Feature: usuario sin rol → 403 en liberar, status engomado, paros store, crear órdenes UrdEng.  
6. Documentar `modulos_v3`; invalidar caché en cambios de permisos.

**No** “arreglar AuthZ” metiendo checks solo en Livewire: los POST legacy seguirían abiertos.
