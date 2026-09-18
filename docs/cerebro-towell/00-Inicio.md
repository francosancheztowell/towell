# Cerebro Towell

Fuente de verdad: **este vault Obsidian** (`docs/cerebro-towell`). Sin Notion. Sin conector.

Abrir: Obsidian → Open folder as vault → `C:\xampp\htdocs\Towell\docs\cerebro-towell` → [[00-Inicio]] (esta nota).

## Mapa
- [[Auditoria/authz-mapa-gaps|AuthZ menu vs rutas]]
- [[Auditoria/js-y-frontend-deuda|Deuda JS/frontend]]
- [[Orquestacion/criterio-grupo-agentes|Criterio grupo agentes]]
- [[Arquitectura/livewire-cuando-si-cuando-no|Livewire si/no por modulo]]
- [[Auditoria/inventario-bugs|Inventario vivo bugs]]
- [[Auditoria/00-Indice-auditoria|Auditoría]]
- [[Auditoria/auditoria-critica-towell|Informe crítico PR #32]]
- [[Auditoria/corte-ti-y-docs-vs-codigo|Corte TI + docs vs código]]
- [[Auditoria/sesion-notion-migrada|Notas de sesión (migradas de Notion)]]
- [[SQL/00-Indice-SQL|SQL]]
- [[SQL/mapa-y-conflictos|SQL mapa y conflictos]]
- [[Modulos/00-Indice-modulos|Módulos]]
- [[Orquestacion/00-Orquestacion|Orquestación]]
- [[Runbooks/00-Indice-runbooks|Runbooks]]

## Proyecto
- Path: `C:\xampp\htdocs\Towell` (LA-GTECLAVE)
- Repo: https://github.com/francosancheztowell/towell
- PR auditoría: https://github.com/francosancheztowell/towell/pull/32

## Qué es Towell
ERP / intranet Laravel textil: planeación, tejido, urdido, engomado, atadores, tejedores, mantenimiento, configuración. Auth empleado/QR. Permisos por módulo. SQL Server ProdTowel + AX TI_PRO (`sqlsrv_ti`).

## Stack
PHP 8.2+, Laravel 12, Livewire, Vite 6, Tailwind 4, Maatwebsite Excel, Dompdf, Predis, Telegram, Redbooth.

## Organización código
Controllers/Services/Models por dominio; `routes/modules/*`; views en `resources/views/modulos/` (+ `planeacion/`, typo `catalagos`).

## Flujo
Planeación → Programa Urd/Eng → Urdido/Engomado → Atadores; Planeación → Tejido/Tejedores → Crudo/Trazabilidad; Mantto/Mecánicos cruzan piso.

## Reglas para towellin
1. Leer este vault antes de cambiar código.
2. Si AGENTS.md ≠ código → gana el código hasta corregir la nota.
3. Hallazgos nuevos → markdown aquí, nunca Notion.
