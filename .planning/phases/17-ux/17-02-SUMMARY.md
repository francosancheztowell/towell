# 17-02 — UX global · SUMMARY

**Rama:** `claude/17-02-ux-global` (base `claude/friendly-hopper-506bg9` @ `98e6e5e`) · **Plan:** `17-02-PLAN.md`
**IDs cubiertos:** UX-01..09, UX-11..16, UX-18 (checklist). **Fuera:** UX-10 (contraseñas, decisión del owner). UX-17 = la auditoría de 17-01; aquí no aplica.
**HANDOFFs cerrados:** 20-02 #5 (código de referencia de la 500), 16 A3 (loader único), 16 A4 (toasts bajo el navbar), 18-01 #1 (`towell-ruta` con `uri()`), PT B2 (modal de días fuera del navbar).

> El plan se escribió y se ejecutó en la misma sesión: la herramienta de aprobación de plan no estaba activa (la sesión no arrancó en modo plan), así que no hubo una aprobación explícita del owner antes de ejecutar. El plan está en su propio commit (`dc45f66`) para revisarlo por separado.

## Qué se hizo

| ID | Cambio | Dónde |
|---|---|---|
| UX-01 | El layout ya montaba `x-ui.flash` (DS-09). Queda cubierto con test en el home: `redirect()->with('error', 'No tienes acceso a este módulo.')` se ve. El home tiene estado vacío ("No tienes módulos asignados") con `x-empty.empty-state`. | `produccionProceso.blade.php` |
| UX-02 | `<title>` por página: `@section('title')` › texto de `@section('page-title')` › módulo de SYSRoles de la ruta (solo rutas sin parámetros) › "Producción Towell"; siempre con "· Towell". Antes eran 9 vistas; ahora todas las que tienen `page-title` (la mayoría). | `layout-head.blade.php` |
| UX-03 | El navbar envuelve el título en `<h1>` solo si el contenido no trae ya uno (`x-layout.page-title`); si lo trae, `<div>`. Home y submódulos pasan de 2 a 1 `<h1>`. Los `<h1>` propios dentro del contenido de ~20 vistas van a HANDOFF C2. | `navbar.blade.php` |
| UX-04 | Pinch-zoom habilitado (sin `maximum-scale=1, user-scalable=no`) en layout y login. Andón lo bloquea: `crudo.index` por nombre de ruta o `@section('viewport-fijo', '1')`. | `layout-head`, `login` |
| UX-05 | El `<body>` ya no lleva `user-select:none`. Solo el chrome (`nav`, `button`, `th`, `[role=button]`, banner) no se selecciona (app.css). Folios, celdas e inputs se copian. Se conserva `-webkit-touch-callout:none` en el body: no impide seleccionar y los long-press que ya existen (Mecánicos, etc.) dependen de él. | `layouts/app`, `app.css` |
| UX-06 | `resources/js/utils/acciones-tactiles.ts`: `accionesTactiles(root, selector, abrir)` (clic derecho + long-press de 500 ms, delegado) y `botonAcciones(el, abrir)` (botón "⋮" de 44 px con `aria-label`). Descarta el `contextmenu` nativo de Android y el click al soltar el dedo (en todo el documento, para que no active el menú recién abierto). `window.accionesTactiles.{enlazar,boton}` para inline. **No se aplicó a ningún módulo** (HANDOFF C1). | utils + `componentes/index.ts` |
| UX-07 | `text-[10px]` → `text-xs` en el modal de usuario del navbar. Layout, errores, componentes `ui/*` ya cumplían. Las 40 vistas de módulos + `telares/telar-requerimiento` van a HANDOFF C3. | `navbar/sections/user-modal` |
| UX-08 | `lang/es/{validation,auth,pagination}.php` + `lang/es.json` (paginación, títulos de error, resumen de validación). `config/app.php` locale `es` por defecto y `.env.example` `APP_LOCALE=es` (**falta la `.env` de producción**, ver Despliegue). Acentos en `layout-head`, login (título, descripción, `alt`) y `aria-label` del ojo de la contraseña. **Cero cambios** en `name`, `pattern`, `inputmode`, `oninput` ni en `AuthController`. | `lang/**`, `config/app.php`, `login`, `components/auth` |
| UX-09 | `errors/layout.blade.php` propio: carga `app.css` de Vite (con `rescue`: si falta el manifiesto, sale con el estilo mínimo), un `<h1>`, "Volver al inicio" siempre a `/produccionProceso` (sin sesión, el middleware lleva al login) y "Regresar" solo si la página anterior es del mismo sitio. 403/404/500 conservan imagen y color; **nuevas** 419 (sesión expirada → Iniciar sesión) y 503 (mantenimiento → Reintentar). 404 decía "en construcción": ahora "Página no encontrada". 403 muestra el mensaje del `abort(403, '…')` si es propio. Sin `onclick` ("Reintentar" es un enlace GET). | `errors/**` |
| 20-02 #5 | La 500 busca `$exception` y toda su cadena `getPrevious()` en el `WeakMap` `monitoreo.eventos_por_excepcion` (Laravel envuelve lo que no es HttpException). `EstadoRequest::eventoId` solo si la vista se pinta sin excepción. Test: con otro error previo en la request (111) se muestra el de la excepción (222), y sin evento propio no se muestra el ajeno. | `errors/500` |
| UX-11 | `utils/sesion.ts`: `sesionExpirada()` (un toast y recarga en 2.5 s, una vez por página) compartido por `window.http` y por un hook `request → fail` de Livewire que reemplaza su `confirm()` en inglés para 419/401. Los formularios HTML siguen yendo al login con flash (bootstrap/app.php). | `utils/{sesion,http}.ts`, `componentes/index.ts` |
| UX-12 | `componentes/conexion.ts`: banner amarillo bajo el navbar (`role=status`) con `towell:conexion` (telemetría) y `online`/`offline` del navegador. Dos fuentes: el aviso sigue si vuelve el Wi-Fi pero el servidor no responde. Se vuelve a pintar tras `wire:navigate`. | `componentes/conexion.ts`, `app.css` |
| UX-13 | `TOAST_DURATION = 5000` para todos los tipos (antes 2.5–6 s) y la pila debajo del navbar: `top: calc(var(--pt-navbar-height, 72px) + .75rem)` (HANDOFF 16 A4; 72 px es la altura real del navbar en 1280 y 768). | `utils/notifications.ts` |
| UX-14 | `aria-label` en engranaje de Configuración, avatar (`aria-haspopup`, `aria-controls`), lápiz de "Editar nombre", ojo de la contraseña; íconos con `aria-hidden`. Los `ui/*` ya los tenían. | navbar, `components/auth` |
| UX-15 | `:focus-visible` con anillo de 2 px `blue-600` en toda la app (capa base: un `focus:outline-none` + `ring` de un componente sigue mandando). No se pinta con ratón ni dedo. | `app.css` |
| UX-16 | Mojibake de `ModulosController`: 38 líneas, solo caracteres no ASCII (verificado: el diff sin no ASCII es idéntico). | `ModulosController.php` |
| 18-01 #1 | `towell-ruta` = `getName() ?? uri()` (plantilla `tejido/{id}`, sin IDs). | `layout-head` |
| PT B2 | `mostrarModalDiasLiberar` sale del navbar a `componentes/dias-liberar.ts` (mismo Swal y validación; ahora también el tope 999.999 del input, que dentro de Swal no se validaba). Datos en `#navbar-dias-liberar` (`data-dias`, `data-base`). Puente `window.mostrarModalDiasLiberar` porque el `onclick` vive en `navbar/sections/programa-tejido.blade.php` (PT, HANDOFF B1). | navbar, `componentes/dias-liberar.ts` |
| 16 A3 | `app-core.js` usa `window.loader.show(150)/hide()`: un solo temporizador sobre `#globalLoader`. | `app-core.js` |
| UX-18 | `17-02-CHECKLIST.md`: checklist por pantalla (estructura, tablet, accesibilidad, mensajes, estados, evidencia) para las 19-xx. | `.planning/phases/17-ux/` |

## Archivos

- **Nuevos:** `lang/es/{validation,auth,pagination}.php`, `lang/es.json`, `resources/views/errors/{layout,419,503}.blade.php`, `resources/js/utils/{sesion,acciones-tactiles}.ts`, `resources/js/componentes/{conexion,dias-liberar}.ts`, `tests/Feature/Ux/{LayoutGlobalTest,PaginasErrorTest,IdiomaTest}.php` (+ `vistas/*.blade.php`), `tests/Js/utils-{acciones-tactiles,ux-globales}.test.mjs`, `17-02-{PLAN,CHECKLIST,SUMMARY}.md`, `HANDOFF.md`, `evidencia/*.png`.
- **Modificados:** `layouts/app`, `components/{layout-head,navbar/navbar,navbar/sections/user-avatar,navbar/sections/user-modal,layout/global-loader,auth/login-form}`, `login`, `produccionProceso`, `errors/{403,404,500}`, `resources/css/app.css`, `resources/js/{app-core.js,componentes/index.ts,componentes/loader.ts,utils/http.ts,utils/notifications.ts}`, `config/app.php`, `.env.example`, `ModulosController.php`, `tests/Js/{utils-notifications.test.mjs,utils-fake-dom.mjs}`, `scripts/ratchet-baseline.json`.
- **Fuera de la fila 17-02 de SESIONES-OLA-3 (autorizados por el prompt o solo a11y):** `ModulosController` (UX-16), `app-core.js` (16 A3), `.env.example` (1 línea, UX-08; ver HANDOFF A1), `navbar/sections/{user-avatar,user-modal}` (aria-label/texto 12 px, sin lógica), `layout/global-loader` y `componentes/loader.ts` (solo comentarios), `tests/Js/utils-fake-dom.mjs` (el documento recibe los eventos que burbujean).

## Evidencia

```
php artisan test                       1648 passed (21159 assertions)  [25 en tests/Feature/Ux]
vendor/bin/phpstan analyse             [OK] No errors
npm run typecheck                      ok
npm run test:js                        148 pass, 0 fail
npm run build                          ok
npm run ratchet                        ok; onclick= 372→371, <script> inline en blade 161→159 (fijado con --update)
vendor/bin/pint --test (PHP tocados)   pass
```

- Un test de otra fase se cayó a mitad del trabajo y se resolvió **sin tocar el test**: `Monitoreo/ErroresTest::test_la_pagina_500_muestra_el_codigo_de_referencia` fija el markup `<strong class="text-gray-700">#id</strong>`. Se conservó esa clase. El test pasa por el camino real (`ErrorRecorder` → `WeakMap` → vista).
- **Navegador (skill `run`):** app real con `php -S` y un router en el scratchpad que apunta todas las conexiones a un sqlite en archivo con el esquema `dbo` adjunto e inyecta un usuario con 8 módulos. Chromium headless 1280×800 y 768×1024 (táctil). "Antes" = el árbol base con su build; "después" = esta rama con su build.

| Pantalla | `<title>` antes → después | `<h1>` | Zoom | CSS |
|---|---|---|---|---|
| Login | Login - Towell → Iniciar sesión · Towell | 1 → 1 | no → sí | ok |
| Home | Produccion Towell → Producción en Proceso · Towell | **2 → 1** | no → sí | ok |
| Planeación / Alineación | → Alineación · Towell | 1 → 1 | no → sí | ok |
| Tejido / Inventario de telas | → Inventario de Telas · Towell | 1 → 1 | no → sí | ok |
| Urdido (submódulos) | → Urdido · Towell | **2 → 1** | no → sí | ok |
| Engomado / Captura de fórmula | → Captura de Fórmulas · Towell | 1 → 1 | no → sí | ok |
| Atadores / Reportes | → Reportes Atadores · Towell | 2 → 2 (h1 propio de la vista, HANDOFF C2) | no → sí | ok |
| Mantenimiento / Reportar paro | → Reportar Paro · Towell | 1 → 1 | no → sí | ok |
| 403 / 404 / 500 | títulos en mayúsculas → "Acceso denegado · Towell"… | 1 | sí | **sin CSS → con CSS** |
| 419 / 503 | "Page Expired" / "Service Unavailable" (Laravel, inglés) → páginas propias | 1 | sí | **sin CSS → con CSS** |

  Errores de consola: **ninguno nuevo**. Salen igual antes y después el 500 de `departamentos` en Reportar paro (tabla que el sqlite del arnés no tiene) y el estado HTTP de las propias páginas de error.

  Interacción (768×1024 táctil): `notify.info` queda en y = 76 px con el navbar terminando en 72; con la red cortada (`context.setOffline`) aparece el banner justo debajo del navbar y desaparece al volver; `user-select` es `auto` en `body`/`main` y `none` en `nav`; Tab lleva el foco al logo, Configuración, Paro y Salir con anillo de 2 px; long-press real (CDP, 700 ms) sobre una fila de prueba abre el menú (`largo`) y el click al soltar no llega a la celda; el botón ⋮ abre con `boton`; en `/planeacion/programa-tejido` `mostrarModalDiasLiberar()` abre "Rango de días a considerar" con 10.999.

  Capturas en `evidencia/`: `antes-despues-*.png` (izquierda antes, derecha después; login, home, urdido, alineación, inventario de telas, captura de fórmula, reportes atadores, reportar paro, 403/404/419/500/503) y `despues-{sin-conexion,foco-visible,dias-liberar}-*.png`.

- **Code-review (skill, high) sobre `98e6e5e..HEAD`:** 10 hallazgos, 7 corregidos en `b2bad5e`:
  - El click al soltar tras un long-press solo se descartaba si caía en la fila: podía ejecutar el primer ítem del menú recién abierto bajo el dedo. Ahora se descarta en el documento, sea cual sea el destino, y un gesto nuevo lo reinicia. Tiene test.
  - Banner: el `online` del navegador lo ocultaba aunque el servidor siguiera caído (la telemetría no vuelve a emitir). Ahora el navegador y el servidor son dos fuentes separadas. Tiene test.
  - `<title>` por `moduleNameForRoute()` en URLs con IDs (LIKE sobre el path, una llave de caché por ID). Ahora solo en rutas sin parámetros. Tiene test.
  - Quitar `touch-callout` del body rompía los long-press existentes en iOS, y Shift+clic en los encabezados seleccionaba texto. Se conserva `touch-callout` y `th` pasa al chrome.
  - Toasts: el fallback de 64 px no era la altura real (72 px). Corregido.
  - Días: con `data-dias=""` se abría vacío; se validaba sin el tope 999.999. Corregido, con test.
  - `.env` de producción con `APP_LOCALE=en`: documentado (Despliegue y HANDOFF A1).
  - No se tocaron: reusar `delegate()` en acciones táctiles (el click va en fase de captura sobre el documento, que `delegate` no cubre), el filtro "Configuración" duplicado del home (vive en `module-grid`, que es de DS) y `TOAST_DURATIONS` con valores iguales (API exportada; `showToast` lo usa para validar el tipo).

## Decisiones

- **Andón = `crudo.index`.** Es la única pantalla titulada "ANDON". Otra se suma con `@section('viewport-fijo', '1')`, sin tocar el layout.
- **Un solo destino para "Volver al inicio":** `/produccionProceso`. No se decide por `auth()->check()` porque la 404 de una ruta que no existe se pinta sin sesión iniciada.
- **Toasts de 5 s para todos los tipos** (antes el error duraba 6 s). Se pausan con el puntero o el foco, y se cierran con ×.
- **Mismo diseño:** el navbar, el home y los módulos se ven igual en las capturas antes/después. Las páginas de error ya se ven como su HTML siempre pretendió (Tailwind cargado), con el mismo muñeco y los mismos colores.

## Pendientes / HANDOFF

Ver `HANDOFF.md`: A1 `.env` de producción, A2 acentos de `bootstrap/app.php`, A3 CLAUDE.md; B PT (onclick del botón Liberar, menús contextuales); C por módulo (clic derecho en 12 archivos, `<h1>` propios, texto < 12 px en 40 vistas, 33 toasts locales, `fetch` crudos sin 419).

## Despliegue

1. `git pull` + `composer install` (sin dependencias nuevas) + `npm run build` (CSS y JS cambian).
2. **`.env` de producción: `APP_LOCALE=es`** (y dejar `APP_FALLBACK_LOCALE=en`).
3. `php artisan optimize:clear && php artisan optimize` (vistas, config y el nuevo `lang/`).
4. Sin migraciones ni SQL.
