# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Planta (mayoría):** planeación, tejido, urdido, engomado, atadores, tejedores y mantenimiento de una textilera. Capturan y consultan producción en tablets de piso (1280×665 y 1006×427 apaisadas) y en PCs de oficina.
- **Sistemas (1–3 personas):** administran usuarios, módulos y permisos, y vigilan la salud de la aplicación desde el panel `/admin`, en PC, varias veces al día o cuando algo falla.

## Product Purpose

Towell es el ERP interno de planeación y producción de la planta: programa telares, urdido y engomado, registra producción, paros, mantenimiento y calidad. El éxito es que la planta capture y consulte sin fricción, y que Sistemas detecte y resuelva fallas antes de que la planta las reporte.

## Operating Context

- Una sola app Laravel 12 servida en la red de la planta (Laragon en 192.168.2.15, certificado autofirmado). Producción sobre SQL Server 2008 R2.
- Turnos de 6:30–14:30, 14:30–22:30 y 22:30–6:30 (America/Mexico_City); hay un turno 4 comodín.
- Monitoreo propio (tablas `SYSMon*`): dispositivos, sesiones, vistas con tiempos, errores agrupados por huella con eventos, accesos. Alertas por correo. Laravel Pulse en `/admin/pulse` como complemento.

## Capabilities and Constraints

- Permisos por módulo (`acceso`, `crear`, `modificar`, `eliminar`, `registrar`) en `SYSRoles` / `SYSUsuariosRoles`.
- `/admin` es solo para el área Sistemas (Gate `admin`). En el panel conviven dos trabajos con el mismo peso: **¿se está rompiendo algo?** (errores nuevos o en aumento, regresiones, páginas lentas) y **¿quién está conectado?** (dispositivos y usuarios en línea, sesiones).
- El panel `/admin` puede tener su propia estructura (barra lateral propia), separada de la navbar de la app, con regreso a Towell; mismo login y misma app.
- Solo TypeScript para código nuevo de front; Livewire en el panel; Tailwind v4, Flux, Font Awesome. Sin SQL de 2012+.

## Brand Commitments

- Nombre "Towell" y su logo. Idioma de la interfaz: español (México).
- Referencias explícitas del dueño para el panel `/admin`: Sentry y Laravel Nightwatch.

## Evidence on Hand

- Datos reales de monitoreo en `SYSMon*`; no inventar métricas, clientes ni cifras en la interfaz.

## Product Principles

1. La planta no se detiene: nada del monitoreo puede romper ni frenar la request del usuario.
2. Lo que necesita atención va primero; lo que está bien no compite por la vista.
3. Datos reales o nada: un panel vacío dice que está vacío, no rellena con ejemplos.
4. Permisos explícitos por módulo y por id, nunca por nombre.

## Accessibility & Inclusion

Tablets en piso con guantes y luz variable: objetivos táctiles de 44 px y texto mínimo de 12 px en la app de planta.
