# Cómo contribuir

1. Crea una rama desde `main`: `feat/<modulo>-<tema>`, `fix/<modulo>-<tema>` o `docs/<tema>`.
2. Antes de subir, corre `composer quality`, `php artisan test` y `npm run typecheck`.
3. Abre un PR usando la plantilla; el CI (`frontend-checks.yml`) tiene que quedar en verde.

## Commits

Formato `tipo(módulo): descripción en español`, en imperativo y en una línea de menos de 72 caracteres.
Tipos: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `chore`.

```
fix(engomado): la merma ya no suma dos veces el julio devuelto
```

## Reglas del código

Las convenciones completas (SQL Server 2008 R2, permisos, folios, `window.http`/`window.notify`, componentes `x-ui.*`) están en el [README](../README.md#convenciones) y en [`CLAUDE.md`](../CLAUDE.md).
La deuda solo baja: si el ratchet falla, arregla el patrón en lugar de subir el techo.
