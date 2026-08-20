# Diseño: puente PWA + FastAPI → Towell (SQL Server intranet)

## Objetivo

Permitir que una PWA (auth en Supabase) consuma scrapes de portales (Soriana, HEB, etc. vía FastAPI), analice esos datos en el cliente, y persista el resultado en SQL Server de planta **sin** abrir el puerto 1433 a internet y **sin** que la PWA o FastAPI hablen TDS con ProdTowel.

## Decisión

Laravel/Towell es el único proceso que lee/escribe SQL Server de producción. FastAPI solo scrapea y entrega JSON a la PWA. La PWA nunca recibe host, usuario ni password de SQL.

## Por qué no hay conexión directa

Towell usa SQL Server en IPs privadas (`192.168.2.28` ProdTowel, `192.168.2.24` AX/TI). Esas direcciones no existen en internet. El navegador de una PWA no habla el protocolo de SQL Server. Supabase es Postgres en la nube: tampoco enruta a la LAN de planta.

Publicar `:1433` para “que Supabase o FastAPI lleguen” queda fuera de alcance: es un agujero de seguridad sobre la base de producción.

## Actores

| Pieza | Dónde | Responsabilidad |
| --- | --- | --- |
| PWA | Navegador / celular | Auth Supabase, lee scrape, analiza, POST del resultado |
| Supabase | Nube | Login de usuarios de la PWA (JWT). No es la BD de Towell |
| FastAPI | Laptop de desarrollo hoy; equipo de planta en producción | Scraping de portales; API de lectura del scrape |
| Laravel (Towell) | Ya en la LAN (Laragon / servidor de planta) | API JSON autenticada; reglas, auditoría, SQL |
| SQL Server | Intranet `:1433` | Fuente de verdad de Towell. Solo Laravel |

## Flujo acordado

1. FastAPI entra a los portales de proveedores y obtiene datos crudos.
2. La PWA llama a FastAPI y lee ese scrape (staging en FastAPI, no en SQL).
3. La PWA hace el análisis que el usuario quiera (tratado o crudo).
4. La PWA envía el payload a Laravel (`POST /api/v1/...`).
5. Laravel valida, aplica permisos/auditoría y escribe en ProdTowel.

Lecturas de catálogos u órdenes ya existentes en Towell, si se necesitan después, también van por Laravel. No forman parte del primer corte salvo que se pida explícitamente.

```
[Soriana/HEB] --> FastAPI (scrape)
                      |
                      v
                    PWA (análisis) --JWT--> Laravel --TDS--> SQL Server
                      ^
                      |
                 Supabase Auth
```

## Autenticación

- **PWA → FastAPI:** Bearer JWT de Supabase. FastAPI verifica la firma contra el JWKS del proyecto. Sin JWT no hay scrape.
- **PWA → Laravel:** el mismo JWT. Middleware nuevo en Towell (no hay Sanctum hoy) valida la firma contra el JWKS de Supabase. El usuario de Towell se resuelve por `SYSUsuario.correo` = email del JWT, o por un claim/tabla de enlace a `numero_empleado`. Sin fila, `401`.
- **No** reutilizar el API key de Redbooth (`AuthenticateRedboothApiKey` en `routes/api.php`) para la PWA: esa clave es de servicio y no debe ir al navegador.
- FastAPI **no** lleva credenciales SQL. Laravel sigue usando su conexión `sqlsrv` actual.

CORS: Laravel y FastAPI permiten el origen de la PWA. Credenciales SQL jamás en variables `VITE_` / `NEXT_PUBLIC_`.

## Superficie de API (Laravel)

Patrón existente a copiar: `routes/api.php` + prefijo `v1` + throttle.

Nuevo grupo, separado de Redbooth, por ejemplo:

- Primer corte: `POST /api/v1/portales/resultados` — persiste el análisis enviado por la PWA. No hay GET en este corte.

El esquema exacto de tablas/columnas **no** se fija aquí: depende de qué entidad de negocio represente el análisis (pedido, saldo, OC, etc.). El primer PR de implementación debe nombrar esa entidad antes de migrar o insertar en tablas de producción existentes.

Errores:

- `401` JWT ausente o inválido
- `403` usuario sin permiso de módulo
- `422` payload inválido (Form Request)
- `503` SQL inalcanzable; la PWA muestra error, no reintenta en silencio contra otro backend

`SetSqlContextInfo` debe aplicarse también a estas rutas API para que los triggers de auditoría vean al usuario, igual que en `web`.

## FastAPI (fuera de este repo)

Contrato mínimo para la PWA:

- `POST /scrape/{proveedor}` o job equivalente — dispara o reutiliza scrape
- `GET /scrape/{job_id}` — JSON crudo para análisis
- Staging local (archivo, SQLite o memoria). No ProdTowel.

Desarrollo: proceso en la laptop de planta (ya alcanza `192.168.2.x` para **otras** herramientas, pero FastAPI no usa SQL). Chrome en esa laptop llama `http://127.0.0.1:<puerto>`.

Producción: mismo binario en un equipo de planta. Publicar **solo HTTP(S)** con túnel (Cloudflare Tunnel / Tailscale) o reverse proxy interno. El scrape necesita salida a internet hacia los portales; SQL sigue inalcanzable desde fuera.

## Alcance / no alcance

**En alcance**

- Topología y reglas de quién habla con quién
- Auth JWT Supabase en el puente Laravel
- Un endpoint de persistencia del resultado de la PWA
- Prototipo en la laptop de planta + Laragon

**Fuera de alcance (este spec)**

- Abrir SQL Server a internet o conectar Supabase Postgres con linked server
- Que FastAPI inserte en ProdTowel
- UI de la PWA y reglas de análisis
- Mapeo campo a campo Soriana/HEB → tablas Towell
- Lectura masiva de AX (`sqlsrv_ti`) desde la PWA

## Verificación

- Desde la PWA en la laptop de planta: scrape FastAPI OK; persistir Laravel OK; fila visible en SQL vía Towell/SSMS.
- FastAPI sin variables de conexión `sqlsrv` sigue funcionando.
- Request a Laravel sin JWT → 401.
- Request con JWT de un usuario sin permiso → 403.
- Puerto 1433 no aparece en firewall público ni en el túnel.
- Un celular en 4G **no** llega a FastAPI/Laravel de la laptop hasta que exista túnel HTTPS; eso es esperado en el prototipo.

## Riesgos

- Credenciales SQL hoy viven en `config/database.php`. El puente no las copia a FastAPI ni a la PWA; conviene pasarlas a `.env` en un cambio aparte.
- Dos orígenes (FastAPI + Laravel) complican CORS y cookies; la PWA usa Bearer, no sesión Blade.
- Si el análisis debe cruzarse con órdenes reales de Towell, hará falta un GET Laravel adicional (siguiente spec).
- Confirmar que los usuarios de la PWA tienen `correo` poblado en `SYSUsuario` (o definir tabla de enlace).
