# Frontend — JS compartido, Layouts y Componentes Blade

> Generado automáticamente — documentación detallada del módulo

---

## 1. Propósito del módulo

Este ámbito documenta la **infraestructura de frontend compartida** del proyecto Towell: los puntos de entrada JavaScript de Vite, las utilidades globales (`window.http`, `window.notify`, `window.showToast`, `window.Swal`, `window.Chart`, etc.), los **layouts Blade** raíz, y la biblioteca de **componentes Blade reutilizables** (`resources/views/components/**`, 34 archivos) que sostienen visualmente todos los módulos de negocio (planeación, tejido, urdido, engomado, atadores, tejedores, mantenimiento, configuración).

No es un módulo de negocio propiamente dicho (no tiene un controlador de dominio único ni tablas propias), sino la **capa transversal de presentación e interacción** sobre la que se montan los demás módulos. Su rol dentro del flujo productivo textil es:

- Estandarizar el chrome de la aplicación (navbar fijo, botón atrás jerárquico, avatar/menú de usuario, loader global, gestión PWA).
- Proveer un **cliente HTTP único** con CSRF automático y normalización de errores (sustituyendo `fetch()` crudo).
- Centralizar las **notificaciones** (Toastr + SweetAlert2) con escape de HTML anti-XSS, sustituyendo las múltiples copias históricas de `showToast()`.
- Ofrecer componentes de botones con **verificación de permisos por módulo** (`userCan`) integrada en el propio Blade.
- Encapsular UI específica de alto valor textil: secciones de telar, calendario semanal de requerimientos por turno, navbar de salto entre telares, modales de notificación (atado de julio, cortado de rollo).

Submódulos / piezas que abarca:

| Pieza | Archivos |
|---|---|
| Entradas Vite JS | `resources/js/app.js`, `app-core.js`, `app-filters.js`, `bootstrap.js` |
| Utilidades ESM | `resources/js/utils/http.js`, `resources/js/utils/notifications.js` |
| Layouts | `layouts/app.blade.php`, `layouts/simple.blade.php`, `layouts/globalLoader.blade.php` |
| Componentes de layout/head/scripts | `layout-head`, `layout-styles`, `layout-scripts`, `layout/*` |
| Componentes navbar | `navbar/navbar`, `navbar/button-*`, `navbar/sections/*` |
| Componentes UI | `ui/alert`, `ui/button`, `ui/modal-base`, `ui/toast-notification` |
| Componentes de catálogos/botones | `catalogs/*`, `buttons/*` |
| Componentes telares / requerimientos | `telares/*` |
| Componentes modales / tablas / estados vacíos | `modals/tejedores/*`, `tables/*`, `empty/*`, `programa-tejido/*`, `auth/*` |

---

## 2. Rutas

Este ámbito **no define rutas propias**. Los layouts y componentes solo *consumen* endpoints de otros módulos. La tabla siguiente lista los endpoints que el JS inline de los layouts/componentes de este ámbito invoca, con su declaración real (ver `routes/modules/*`). Todas las rutas están bajo middleware `auth` salvo indicación.

| Método | URI | Controller@método | Middleware | Permiso requerido | Consumido por |
|---|---|---|---|---|---|
| GET | `/api/modulo-padre?ruta=` | `UsuarioController@getModuloPadre` (`api.modulo.padre`) | `auth` | — | `app-core.js` botón atrás (`#btn-back`) |
| GET | `/atadodejulio?listado=1` / `?no_telar=&tipo=` | `Tejedores\NotificarMontadoJulioController@index` (`notificar.atado.julio`) | `auth` | módulo Atado de Julio | `layouts/app.blade.php` modal telares |
| POST | `/atadodejulio/notificar` | `NotificarMontadoJulioController@notificar` (`notificar.atado.julio.notificar`) | `auth` | — | `layouts/app.blade.php` `notificarTelares()` |
| GET | `/cortadoderollo` | `Tejedores\NotificarMontRollosController@index` (`notificar.cortado.rollo`) | `auth` | módulo Cortado de Rollo | `module-grid` (enlace directo) |
| GET | `/cortadoderollo/telares` | `NotificarMontRollosController@telares` (`notificar.mont.rollos.telares`*) | `auth` | — | `modals/tejedores/notificar-rollos` |
| GET | `/cortadoderollo/detalle?no_telar=` | `NotificarMontRollosController@detalle` (`notificar.mont.rollos.detalle`*) | `auth` | — | `modals/tejedores/notificar-rollos` |
| POST | `/cortadoderollo/notificar` | `NotificarMontRollosController@notificar` (`notificar.mont.rollos.notificar`*) | `auth` | — | `modals/tejedores/notificar-rollos` |
| GET | `/inventario-telares` | `ProgramaUrdEng\InventarioTelaresController@getInventarioTelares` | `auth` | — | `telares/telar-requerimiento` (carga/caché) |
| POST | `/inventario-telares/guardar` | `InventarioTelaresController@guardar` | `auth` | crear Requerimientos (idrol 21) | `telar-requerimiento` `handleRequerimientoChange()` |
| GET | `/simulacion/req-programa-tejido-line` | `ReqProgramaTejidoLineController@index` (variante simulación) | `auth` | — | `tables/simulacion/...-line-table` |
| GET | `/planeacion/req-programa-tejido-line` | `Planeacion\ReqProgramaTejidoLineController@index` (`planeacion.req-programa-tejido-line`) | `auth` | — | `programa-tejido/req-programa-tejido-line-table` |
| POST | `/planeacion/catalogos/{route}-modelos/excel` | controlador de catálogo según ruta | `auth` | crear (módulo) | `buttons/catalog-actions` subida Excel |

> *Los nombres de ruta de `notificar-rollos.blade.php` se resuelven vía `route('notificar.mont.rollos.*')`; en `routes/modules/tejedores.php` aparecen declarados como `notificar.cortado.rollo.*`. Si un alias `notificar.mont.rollos.*` no existiera, el componente fallaría al renderizar; revisar el archivo de rutas de tejedores antes de usarlo en producción.

---

## 3. Controllers

**Este ámbito no contiene controladores.** Toda la lógica vive en JS (Vite/inline) y Blade. Los controladores que sirven los endpoints consumidos pertenecen a otros módulos (Tejedores, ProgramaUrdEng, Planeación, Sistema/UsuarioController) y se documentan en sus respectivos módulos. Se referencian aquí solo como destino de las peticiones (sección 2).

---

## 4. Services y Helpers del ámbito

No hay services PHP propios del ámbito. Las utilidades equivalentes son **módulos JS** (ver sección 7) y los **helpers globales** que los componentes invocan en tiempo de render:

- `userCan('crear'|'modificar'|'eliminar'|'registrar'|'acceso', $moduloIdONombre)` — usado en `navbar/button-*`, `buttons/catalog-actions`, `buttons/inventory-sequence-actions`, `telar-requerimiento`. (Helper de `app/Helpers/permission-helpers.php`.)
- `getFotoUsuarioUrl($foto)` — usado en navbar (avatar/modal usuario).
- `getDeviceInfo()`, `getDeviceIdentifier()`, `getClientIpv4()` — usados en `navbar/sections/user-modal` (helpers de `device_helpers.php`).
- `ModuloService::getModulosPrincipalesPorUsuario(id)` — usado en `navbar.blade.php` para decidir si mostrar el botón Configuración.

---

## 5. Modelos y tablas

El ámbito **no posee modelos propios**. Los componentes Blade leen estos modelos en tiempo de render (para verificación de permisos y datos de usuario):

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave | Usado en |
|---|---|---|---|---|---|
| `App\Models\Sistema\SYSRoles` | `dbo.SYSRoles` | `sqlsrv` | `idrol` | `modulo`, `reigstrar` *(typo intencional)* | `navbar/button-*` (resolver idrol por nombre) |
| `App\Models\Sistema\SYSUsuariosRoles` | `dbo.SYSUsuariosRoles` | `sqlsrv` | — | `idusuario`, `idrol`, `acceso`, `crear`, `modificar`, `eliminar`, `registrar` | `button-edit` (acceso=1), `telar-requerimiento` (idrol 21) |
| `App\Models\Sistema\Usuario` | (tabla de usuarios) | `sqlsrv` | `idusuario` | `nombre`, `foto`, `puesto`, `area`, `turno`, `numero_empleado` | navbar (avatar, modal usuario) |

> Nota typo: el campo de permiso de registro en `SYSRoles` es `reigstrar`, mientras que en `SYSUsuariosRoles` es `registrar` (correcto). El componente `button-report` verifica `userCan('registrar', ...)` que internamente mapea ambos.

---

## 6. Layouts y Vistas Blade

### 6.1 `layouts/app.blade.php` — Layout principal

Layout raíz autenticado. Estructura: `<x-layout-head/>` + `<x-layout-styles/>` + `<x-layout-scripts/>` en `<head>`; cuerpo con `<x-layout.global-loader/>`, `<x-navbar.navbar/>`, formulario de logout oculto, `<main>` con `@yield('content')`, y el **modal "Atado de Julio"** embebido.

- **Estilos inline**: animación `fa-spin`, fondo gradiente azul compatible iPad/Safari (`-webkit-fill-available`), fondo de `main.app-main`.
- **Scripts cargados al final**: `@vite(['resources/js/app-core.js', 'resources/js/app-filters.js'])`, `@stack('scripts')`, condicionalmente `js/programa-tejido-menu.js` (en rutas de programa tejido/muestras) y `js/app-pwa.js` (si PWA habilitada).
- **Polyfill** de `CSS.escape` para navegadores antiguos.
- **Secciones/slots**: `@yield('content')`, `@yield('page-title')` (vía navbar), `@yield('navbar-right')`, `@yield('menu-planeacion')`, `@stack('scripts')`, `@stack('styles')`.

Funciones JS inline del modal "Atado de Julio":

- `abrirModalTelares()` — `async`. GET `notificar.atado.julio?listado=1`, llena `#selectTelar` con los telares del usuario y muestra el modal. Endpoint: `notificar.atado.julio`.
- `cerrarModalTelares()` — Oculta el modal y resetea select, radios, detalles y variables de estado (`registroActualId`, `telarActual`, `registroCompleto`).
- `buscarDetallesTelar()` — `async`. Si hay telar + tipo seleccionados, GET `notificar.atado.julio?no_telar=&tipo=`; muestra detalles o el mensaje "sin datos". Endpoint: `notificar.atado.julio`.
- `notificarTelares()` — `async`. Valida telar/hora y POST `notificar.atado.julio.notificar` con `{id, horaParo, no_telar, tipo}`; muestra éxito/error con Swal. Endpoint: `notificar.atado.julio.notificar`.
- `mostrarDetallesTelar(detalles)` — Pinta los inputs readonly del detalle e inserta la hora actual (`toLocaleTimeString es-MX`).
- Listeners `DOMContentLoaded`: cerrar al clic exterior, autobúsqueda al cambiar `#selectTelar` y radios `tipoTelar`.

### 6.2 `layouts/simple.blade.php` — Layout simple

Layout ligero (sin navbar completo, sin PWA agresiva). Usado en login y vistas autónomas. Pasa `:simple="true"` a los componentes de head/styles/scripts. Navbar minimalista con solo el logo y `@yield('menu-planeacion')`. Slots: `@yield('content')`, `@yield('menu-planeacion')`, `@yield('scripts')`.

### 6.3 `layouts/globalLoader.blade.php`

Wrapper de una sola línea que renderiza `<x-layout.global-loader/>`. Sirve como punto de inclusión independiente del loader global.

---

## 7. JS dedicado (entradas Vite y utilidades)

### 7.1 `resources/js/app.js` — entrada Vite principal

Importa `bootstrap.js` y el CSS de la app + librerías (FontAwesome, Select2, Toastr). Expone:
- `window.PTFilterEngine = { checkFilterMatch, groupFiltersByColumn, rowMatchesCustomFilters, dateInRange }` — motor de filtros del Programa Tejido (de `programa-tejido/filter-engine.js`).
- `window.Swal` — SweetAlert2.

### 7.2 `resources/js/bootstrap.js` — configuración global de librerías

Configura y expone en `window`:
- `window.axios` con defaults `X-Requested-With: XMLHttpRequest` y `X-CSRF-TOKEN` (leído de `<meta name="csrf-token">`).
- `window.$` / `window.jQuery` (jQuery v4), `window.Swal`, Select2 (efecto lateral), `window.toastr`, `window.Chart` (chart.js/auto).
- `window.http` (cliente HTTP unificado), `window.notify` (notificaciones), `window.showToast` (shim global con firma `(message, type)`).

### 7.3 `resources/js/utils/http.js` — cliente HTTP unificado (`window.http`)

Envuelve axios para **devolver directamente el cuerpo JSON** (`response.data`) y **normalizar errores**. Convención del proyecto: **NO usar `fetch(...).then(r => r.json())` nuevo**; usar `http.*`.

Contrato:

| Método | Firma | Qué hace |
|---|---|---|
| `http.get` | `(url, config?) → Promise<json>` | GET con CSRF + XHR header reaplicados en cada llamada. |
| `http.post` | `(url, data?, config?) → Promise<json>` | POST. |
| `http.put` | `(url, data?, config?) → Promise<json>` | PUT. |
| `http.patch` | `(url, data?, config?) → Promise<json>` | PATCH. |
| `http.delete` | `(url, config?) → Promise<json>` | DELETE. |
| `http.upload` | `(url, formData, config?) → Promise<json>` | POST multipart; NO fija `Content-Type` (el navegador añade el boundary). |
| `http.csrfToken` | `() → string` | Lee el token CSRF fresco de la meta. |

**Manejo de errores normalizado** (`normalizeError`): ante un error HTTP, `http.*` **lanza** un `Error` con:
- `err.message` — `data.message` ?? string del body ?? mensaje de axios ?? "Error de comunicación con el servidor".
- `err.status` — código HTTP (`0` si no hubo respuesta).
- `err.data` — cuerpo de la respuesta (o `null`).
- `err.errors` — `data.errors` (errores de validación **422** de Laravel) o `null`.
- `err.original` — el error de axios original.

Funciones internas: `csrfToken()`, `withCsrf(config)` (inyecta headers CSRF/XHR fusionando los del caller), `request(promise)` (await + retorna `.data` o lanza normalizado).

### 7.4 `resources/js/utils/notifications.js` — notificaciones unificadas (`window.notify`)

Centraliza Toastr (toasts) y SweetAlert2 (modales). Convención: **NO redefinir `showToast()` en cada vista**; usar `notify.*`. Todos los mensajes pasan por `escapeHtml()` (anti-XSS: usa `div.textContent` → `div.innerHTML`).

Contrato de `notify`:

| Método | Firma | Qué hace |
|---|---|---|
| `notify.success` | `(msg)` | Toast verde (toastr, HTML-escapado). |
| `notify.error` | `(msg)` | Toast rojo. |
| `notify.warning` | `(msg)` | Toast naranja. |
| `notify.info` | `(msg)` | Toast azul. |
| `notify.alert` | `(message, title='Aviso', icon='info')` | Modal Swal bloqueante. |
| `notify.validation` | `(errors, title='Revisa los datos')` | Lista los errores 422 (`Object.values(errors).flat()`) en un Swal con `<ul>` HTML-escapado. |
| `notify.confirm` | `({title, text, html, icon, confirmText, cancelText, confirmColor}) → Promise<boolean>` | Confirmación; resuelve `true` si el usuario confirma. |
| `notify.loading` | `(title='Cargando...') → Swal` | Loader modal bloqueante (sin cerrar por clic/ESC). |
| `notify.close` | `()` | Cierra el Swal abierto. |

Export adicional: `showToast(message, type='success')` — shim de compatibilidad histórico; redirige a `notify[type] || notify.info`. **Nota crítica**: existen variantes locales con OTRA firma (`(icon, title)` en engomado/urdido, `(options)` en cortes-eficiencia) que se auto-sombrean en su scope y **NO deben tocarse**.

### 7.5 `resources/js/app-core.js` — scripts de chrome de la app (IIFE)

Importa `programa-tejido/modal-cache-bootstrap.js`. Constantes: `NAV_STACK_KEY='nav_stack'`, `MAX_STACK_SIZE=10`, `HOME_PATH='/produccionProceso'`, `CLICK_DEBOUNCE=500`.

Funciones:
- `normalizeUrl(url)` — quita trailing slashes (salvo root).
- `getBasePath(url)` — devuelve solo el pathname normalizado (sin querystring).
- `NavStack` (objeto): `get()`, `save(stack)`, `push(url)` (evita duplicados y bucles ida/vuelta; limita a 10), `pop()` (filtra la página actual y devuelve la anterior o `HOME_PATH`), `clear()`. Persiste en `sessionStorage`.
- `initLogout()` — `#logout-btn` abre Swal de confirmación y hace submit de `#logout-form`.
- `initUserMenu()` — toggle del modal de usuario (`#user-modal` / `#btn-user-avatar`), cierre por clic exterior y Escape.
- `initNavigation()` — `replaceState` para evitar entradas espurias, registra la página en el stack, marca `data-navbar-loaded`, limpia el stack en `HOME_PATHS`, y enlaza `#btn-back`: si existe `window.volverAlIndice` lo usa; si no, GET `/api/modulo-padre?ruta=` y redirige a `rutaPadre`; fallback al stack. Debounce de doble clic en enlaces internos.
- `initPageShowHandler()` — recarga si la página viene del bfcache (`event.persisted`) o si hay flag `forceReload`.
- `initToastr()` — opciones globales de Toastr (top-right, progressBar, timeout 5s, etc.).
- `initAppScripts()` — orquesta todo lo anterior en `DOMContentLoaded`.

### 7.6 `resources/js/app-filters.js` — motor de filtros de tabla genérico

Estado global: `activeFilters` (objeto col→valor), `allTableRows`. Expone `window.TableFilters` y aliases globales.

Funciones:
- `init()` — captura filas de `#mainTable tbody tr.selectable-row`, enlaza botones (`#btnFilters`, `#f_add_btn`, aplicar, cancelar, `#btnResetFilters`), Enter en `#f_col_value`. Si existe `window.openProgramaTejidoFilterModal`, el botón de filtros lo prioriza.
- `openModal()` — llena `#f_col_select` con las columnas visibles (`th[data-column]`), renderiza chips, muestra `#filtersModal`.
- `closeModal()` — oculta el modal.
- `addFilter()` — valida columna/valor (feedback visual), agrega/actualiza `activeFilters[column]`, re-renderiza chips.
- `renderFilterChips()` — pinta los chips en `#f_list` con botón de borrado.
- `removeFilter(column)` — elimina un filtro y re-renderiza.
- `getColumnLabel(column)` — etiqueta legible desde el `<th>`.
- `apply()` — relee chips, filtra `allTableRows` por `includes` case-insensitive sobre `td[data-column]`, re-renderiza el tbody (re-enlaza `selectRow` si existe), actualiza badge, cierra modal, dispara `window.showToast` y `window.onFiltersApplied`.
- `reset()` — limpia filtros, restaura todas las filas, anima `#iconReset`, actualiza badge, notifica.
- `updateBadge()` — actualiza `#filterCount` y resalta `#btnFilters`.
- `resetAndReload()` — limpia filtros y recarga la página.

Aliases globales: `openFilterModal`, `closeFilterModal`, `addFilter`, `removeFilter`, `applyFilters`, `confirmFilters` (=apply), `resetFilters`, `resetFiltersSpin`, `resetFiltersAndReload`, y `resetColumnsSpin` (delega en `window.resetColumnVisibility` o fallback que muestra todas las columnas `[class*="column-"]`).

---

## 8. Componentes Blade

### Componentes de layout/cabecera

**`layout-head.blade.php`** — Props: `title`, `description`, `simple`. Emite charset, `csrf-token`, meta description, manifest PWA (si no `simple`), metas viewport/theme-color/apple, y `<title>`. Usado por `layouts/app` y `layouts/simple`.

**`layout-styles.blade.php`** — Props: `simple`. Carga `@vite(['resources/css/app.css'])` (Tailwind v4) y estilos inline: skeleton de imágenes lazy, fixes PWA/safe-area iOS, animaciones comunes (`ripple`, `spin360`, `animate-fade-in`). Variante `simple` con gradiente animado de fondo. Push `@stack('styles')` indirecto vía scripts.

**`layout-scripts.blade.php`** — Props: `simple`. Carga `@vite(['resources/js/app.js'])` (que importa `bootstrap.js` y todas las librerías). Incluye limpieza de Service Worker si PWA está deshabilitada. Emite `@stack('styles')`.

**`layout/global-loader.blade.php`** — Loader de pantalla completa (`#globalLoader`, spinner azul). Se auto-oculta en `DOMContentLoaded`. Sin props.

**`layout/page-header.blade.php`** — Header con gradiente. Props: `title` (req), `subtitle`, `badge`, `gradient` (blue/yellow/green/red/purple/indigo), `size` (sm/md/lg/xl), `centered`, `rounded`, `containerClass`, `headerClass`. Slots: default + `actions`. Calcula color de texto según gradiente. Usado en headers de módulos y formularios.

**`layout/page-title.blade.php`** — Título simple (`<h1>` azul con `animate-fade-in`). Props: `title`, `icon`, `subtitle`, `badge`, `color` (solo `title` se renderiza actualmente).

**`layout/module-grid.blade.php`** — Grid de tarjetas de módulos. Props: `modulos`, `columns`, `filterConfig`, `imageFolder` (default `fotos_modulos`), `isSubmodulos`. Filtra "Configuración" en vista principal, calcula columnas según cantidad (1/2/3/+3), genera `<picture>` WebP con fallback PNG y versionado por `filemtime`, marca la primera imagen como LCP (`eager`/`fetchpriority=high`). Casos especiales: "Atado de Julio" → `onclick="abrirModalTelares()"`; "Cortado de Rollo" → enlace a `notificar.cortado.rollo`. Usado en `produccionProceso.blade.php` y vistas de submódulos.

### Componentes navbar

**`navbar/navbar.blade.php`** — Navbar fijo principal. Calcula flags de ruta (`isProduccionIndex`, `isMuestras`, `isProgramaTejido`, `showParoButton`), datos de usuario, acceso a Configuración (vía `ModuloService`), y `diasLiberarOrdenes` de sesión. Incluye secciones `left`, `programa-tejido` (condicional), `user-avatar`, `user-modal`. Botones: Configuración, Paro (`mantenimiento/nuevo-paro`), Salir (solo en index). Push de script `mostrarModalDiasLiberar()` — abre Swal con input numérico (máx 3 decimales) y redirige a `…/liberar-ordenes?dias=`.

**`navbar/sections/left.blade.php`** — Botón atrás (`#btn-back`, deshabilitado en `produccion.index`) + logo Towell (`<picture>` WebP).

**`navbar/sections/user-avatar.blade.php`** — Botón avatar (`#btn-user-avatar`); muestra foto o inicial del nombre. Datos vía `getFotoUsuarioUrl`.

**`navbar/sections/user-modal.blade.php`** — Modal de usuario (`#user-modal`): foto/nombre/puesto/área/turno/núm. empleado, info de dispositivo (`getDeviceInfo`), nombre de dispositivo editable y IP. Script inline (IIFE):
- `loadDeviceName()` — carga el nombre desde `localStorage` (`device_name_<DEVICE_ID>`).
- `saveDeviceName()` — guarda/borra el nombre en `localStorage`.
- `openEditor()` / `closeEditor()` — modal de edición de nombre.
- `updateDeviceIpDisplay()` — si el servidor reporta localhost, obtiene la IP LAN vía WebRTC (`RTCPeerConnection`/ICE candidates).

**`navbar/sections/column-controls.blade.php`** — 3 botones: fijar columnas (`openPinColumnsModal()`), ocultar (`openHideColumnsModal()`), restablecer (`#btnResetColumns`/`#iconResetColumns`). Props: `resetId`, `resetIconId`.

**`navbar/sections/programa-tejido.blade.php`** — Barra de controles del Programa Tejido. Botones: ver líneas (`#layoutBtnVerLineas`), drag&drop (`toggleDragDropMode()`), descargar (`descargarPrograma()`), liberar órdenes (`mostrarModalDiasLiberar()`), control de columnas, balancear (`abrirBalancearDesdeSeleccion()`), vincular (`vincularRegistrosExistentes()`), catálogos, recalcular fechas (`#btn-recalcular-fechas`), dropdown actualizar calendarios, filtros (`#btnFilters` + `#filterCount`). Props: `moduleLabel`, `modulePermission`. Usa `<x-navbar.button-*>` con verificación de permisos.

**`navbar/button-create.blade.php`** — Botón crear genérico con permisos. Props: `onclick`, `title`, `id`, `disabled`, `module`/`moduleId`, `checkPermission`, `icon` (def `fa-plus`), `iconColor`, `hoverBg`, `text`, `bg`. Verifica `userCan('crear', …)` (resolviendo `idrol` por nombre vía `SYSRoles`); si no tiene permiso, **no renderiza**. Normaliza icono y padding según haya texto.

**`navbar/button-edit.blade.php`** — Botón editar. Props como create + `type` (button/submit), `class`. Verifica `userCan('modificar', …)` **Y** que `SYSUsuariosRoles.acceso == 1`. Si tiene permiso y se pasó módulo, habilita automáticamente el botón. Default `bg-purple-500`, icono `fa-pen-to-square`.

**`navbar/button-delete.blade.php`** — Botón eliminar. Props como create + `class`. Verifica `userCan('eliminar', …)`. Default `disabled=true`, `bg-red-500`, icono `fa-trash`. Extrae `hover:` del `class` si está presente.

**`navbar/button-report.blade.php`** — Botón reporte/registrar. Props como create + `class`. Verifica **solo** `userCan('registrar', …)`. Default `fa-file`, `text-purple-600`. Útil para exportaciones/registros.

### Componentes UI

**`ui/alert.blade.php`** — Alerta con tipos. Props: `type` (error/success/warning/info), `title`, `message`, `items[]`, `dismissible`. Renderiza icono SVG según tipo, lista de bullets, slot, y botón cerrar (remueve el nodo). Usado en `auth/login-form` y vistas con errores de validación.

**`ui/button.blade.php`** — Botón de UI con variantes. Props: `variant` (primary/success/danger/warning/secondary), `size` (sm/md/lg), `type`, `icon` (check/plus/trash/edit/save), `loading`, `fullWidth`. Spinner cuando `loading`. Usado en `auth/login-form` (botón "Iniciar Sesión").

**`ui/modal-base.blade.php`** — Modal base reutilizable (sin Alpine). Props: `id`, `title`, `size` (sm/md/lg/xl), `onclose`. Toggle por `classList` (`hidden`). Header con título y botón cerrar; body = slot. Posiciona top según `--pt-navbar-height`.

**`ui/toast-notification.blade.php`** — Componente vacío conservado por compatibilidad. **Ya no redefine `showToast`** (ahora global vía `bootstrap.js` → `notifications.js`). Solo documentación.

### Componentes de catálogos y botones

**`catalogs/catalog-form-field.blade.php`** — Campo de formulario para modales de catálogo. Props: `name`, `label`, `type` (text/number/select/textarea/…), `value`, `required`, `placeholder`, `options[]`, `prefix` (`swal-`/`swal-edit-`), `maxlength`, `step`, `min`, `max`. Genera input/select/textarea con id `prefix+name`.

**`catalogs/catalog-table.blade.php`** — Tabla de catálogo con selección. Props: `items`, `columns` (`field`/`label`/`format`/`decimals`/`class`), `tableBodyId`, `idField`, `selectable`, `height`. Filas con `onclick=window.catalogManager.selectRow(...)` y `ondblclick=deselectRow(...)`; formatea number/date/percentage. Estilos de scrollbar inline.

**`buttons/catalog-actions.blade.php`** — Barra de acciones de catálogo (crear/editar/eliminar/Excel/filtrar). Props: `route` (telares/eficiencia/calendarios/…), `showFilters`, `showExcel`. Mapea `route` → nombre de módulo (`$rutaToNombreModulo`) y verifica `userCan(crear/modificar/eliminar/acceso)`. Genera funciones JS dinámicas por ruta:
- `window.subirExcel<Route>()` — Swal con drag&drop de Excel; valida tipo/tamaño (≤10MB); POST a `/planeacion/catalogos/{route}-modelos/excel` con FormData; muestra resumen (procesados/creados/actualizados/errores). Endpoint variable por ruta.
- `formatFileSize(bytes)` — formatea tamaño legible.
- `window.handleFileSelect(event)` — muestra info del archivo seleccionado.
- `window.animarRestablecer<Route>()` — anima icono de reset.
- `window.actualizarContadorFiltros<Route>(count)` — actualiza `#filter-count`.
- `window.actualizarBotonesAccion<Route>(habilitar)` — habilita/deshabilita editar/eliminar.

Botones especiales para `calendarios`: recalcular (`recalcularProgramasCalendarioNavbar()`), subir maestro/líneas, eliminar por rango.

**`buttons/inventory-sequence-actions.blade.php`** — Acciones para módulos de secuencia de inventario. Props: `modulo`, `onCreate`, `onEdit`, `onDelete`. Verifica `userCan(crear/modificar/eliminar, $modulo)` y habilita/deshabilita botones según permiso.

### Componentes de auth y estados vacíos

**`auth/login-form.blade.php`** — Formulario de login. Props: `action` (def `/login`), `method`, `successMessage`. Campos: número de empleado, contraseña numérica con toggle ver/ocultar. Usa `<x-ui.alert>` y `<x-ui.button>`. Script inline: toggle de visibilidad de contraseña (`#togglePassword`).

**`empty/empty-state.blade.php`** — Estado vacío genérico. Props: `title`, `message`, `icon` (config/default). SVG según icono.

**`programa-tejido/empty-state.blade.php`** — Estado vacío específico de importación: enlaza a `configuracion.cargar.planeacion` para subir Excel. Sin props.

### Componentes de telares y tablas

**`telares/telar-navbar.blade.php`** — Navbar fijo de salto rápido entre telares. Props: `telares[]`, `id`, `autoShow`, `showThreshold`. Clase JS `TelarNavbar` (constructor + `init`, `setupScrollListener`, `setupClickListeners`, `scrollToTelar`, `updateActiveButton`, `updateActiveOnScroll`, `show`, `hide`). Detecta el telar activo según scroll y resalta su botón. Se instancia en `DOMContentLoaded` como `window.telarNavbar`.

**`telares/telar-section.blade.php`** — Sección de tarjeta de telar (en proceso / siguiente orden / requerimiento). Props: `telar`, `ordenSig`, `tipo` (jacquard/itema/smith), `showRequerimiento`, `showSiguienteOrden`. Closures PHP de formato: `$formatSpec` (cuenta-calibre-fibra), `$formatTrama`, `$formatDate` (oculta `1900-01-01`), `$formatPedido`. Renderiza datos del proceso activo (orden, flog, cliente, rizo/pie/trama, fechas, último julio rizo/pie) o estado "sin proceso activo". Incluye `<x-telares.telar-requerimiento>` cuando corresponde.

**`telares/telar-requerimiento.blade.php`** — Componente más complejo del ámbito: calendario semanal de requerimientos por turno (rizo/pie) con guardado/eliminación contra `inventario-telares`. Props: `telar`, `ordenSig`, `salon`, `dias` (def 7), `turnos` (def 3). Verifica permiso de crear (idrol 21 Requerimientos) sobre los checkboxes. Incluye 3 modales: selección de cuenta, tela reservada, calendario semanal.

Funciones JS inline (las más relevantes):
- `inicializarFechasTablas()` — fija `data-fecha-completa` en cada tabla (hoy+índice).
- `inicializarTelar()` — configura listeners, carga requerimientos (con/ sin filtro de fibra según selección guardada), precarga inventario con caché.
- `setupRequerimientoCheckboxes(telarId, …)` — enlaza `change` de los checkboxes.
- `handleRequerimientoChange(checkbox, telarId, telarData, ordenSigData, salon)` — al marcar: POST `/inventario-telares/guardar` con `{no_telar, tipo, cuenta, calibre, fecha, turno, salon, hilo, no_orden}`; al desmarcar: verifica estado y elimina. Usa datos del modal de selección si existen.
- `obtenerInventarioConCache(filtros)` — `async`. GET `/inventario-telares` (con filtros opcionales `hilo/no_telar/tipo/salon`), caché de 5s y deduplicación de peticiones concurrentes.
- `invalidarCacheInventario()` — limpia todas las claves de caché tras guardar/eliminar.
- `loadRequerimientos(telarId, salon, tipo, fibraFiltro)` / `loadRequerimientosConFiltro(...)` — marcan los checkboxes según los registros del inventario, calculando columnas del calendario (lookback de 30 días para fechas pasadas con registros, hasta 7 columnas), preservando cambios recientes/eliminados del usuario. Endpoint: `/inventario-telares`.
- `abrirModalSeleccion(telarId, tipo, cuenta, calibre, fibra)` / `cerrarModalSeleccion()` / `confirmarSeleccion()` — modal de elección entre cuenta "en proceso" y "siguiente orden".
- `obtenerDatosProcesoActual(telarId)`, `obtenerDatosSiguienteOrden(telarId, fibra)`, `obtenerInventarioTelares()` — `async`, alimentan el modal de selección.
- `verificarEstadoTelarAntesDeEliminar(...)`, `eliminarRegistro(datosEliminar, checkbox)`, `confirmarEliminarConReserva()` — flujo de borrado con manejo de tela reservada.
- `mostrarModalTelaReservada()`, `cerrarModalTelaReservada()`, `mostrarCalendarioParaActualizar()` — modal de tela reservada (eliminar/actualizar/cancelar).
- `mostrarModalCalendarioSemanal()`, `cerrarModalCalendarioSemanal()`, `seleccionarTurno(turno, btn)`, `manejarCancelarModal2()`, `actualizarRegistroConNuevaFecha(...)` — reprogramación de fecha/turno.
- Scroll automático al telar tras reload (`sessionStorage.scrollToTelar`).

**`programa-tejido/req-programa-tejido-line-table.blade.php`** — Tabla de líneas de detalle del Programa Tejido (fecha, piezas, kilos, aplicación, trama, combinaciones 1-5, rizo, pie, mts/pie, mts/rizo). Cabecera azul, body `#reqpt-line-body`. El llenado lo realiza JS de la vista del Programa Tejido vía `planeacion.req-programa-tejido-line`.

**`tables/simulacion/simulacion-programa-tejido-line-table.blade.php`** — Variante de simulación (cabecera `bg-stone-700`, body `#simpt-line-body`). Función JS inline `loadSimulacionProgramaTejidoLines(params)` — `async`: GET `/simulacion/req-programa-tejido-line?…`, soporta paginate o arreglo simple, pinta filas o mensajes de error/vacío.

### Componentes de modales

**`modals/tejedores/notificar-rollos.blade.php`** — Modal de notificación de cortado de rollos. Funciones JS:
- `window.abrirModalNotificarRollos()` — carga telares y muestra el modal.
- `window.cerrarModalNotificarRollos()` — oculta.
- `cargarTelaresRollos()` — `async`, GET `notificar.mont.rollos.telares`, llena el select.
- `cargarDetalleRollos(noTelar)` — `async`, GET `notificar.mont.rollos.detalle?no_telar=`, pinta cuenta/calibre/metros.
- `enviarNotificacionRollos()` — `async`, POST `notificar.mont.rollos.notificar` con `{id}`.

---

## 9. Lógica de negocio y reglas

### Verificación de permisos en la capa de presentación
Los componentes `navbar/button-*`, `buttons/catalog-actions` y `buttons/inventory-sequence-actions` **incrustan la verificación de permisos en el render**: si el usuario no tiene el permiso correspondiente, el botón **no se emite** en el HTML (no solo se deshabilita). Reglas por componente:
- `button-create` → `userCan('crear', …)`.
- `button-edit` → `userCan('modificar', …)` **AND** `SYSUsuariosRoles.acceso == 1` (acceso activo al módulo). Solo permiso `modificar`, sin fallback a `crear`.
- `button-delete` → `userCan('eliminar', …)`.
- `button-report` → **solo** `userCan('registrar', …)` (independiente de otros permisos).
- `telar-requerimiento` → permiso de **crear** sobre el idrol **21** (Requerimientos); si no, los checkboxes se renderizan `disabled`.

Resolución de módulo: si se pasa `module` (nombre), el componente busca `SYSRoles::where('modulo', …)->first()->idrol`; con fallback al nombre si no se encuentra o hay excepción. Recordar el **typo intencional** `reigstrar` en `SYSRoles` (vs `registrar` correcto en `SYSUsuariosRoles`).

### Cliente HTTP y notificaciones (convención obligatoria)
- **No** escribir `fetch(...).then(r => r.json())` nuevo: usar `window.http.*`, que añade CSRF automáticamente y **lanza** errores normalizados (`err.status`/`err.data`/`err.errors`). Para errores 422 usar `notify.validation(err.errors)`.
- **No** redefinir `showToast()` por vista: usar `window.notify.*` o el shim global `window.showToast(message, type)`. Todos los mensajes se **escapan a HTML** (anti-XSS). Excepción: variantes locales con otra firma en engomado/urdido/cortes-eficiencia que se auto-sombrean y no deben tocarse. Módulo piloto ya migrado: `resources/views/catalagos/calendarios/`.

### Navegación jerárquica (botón atrás)
El botón atrás (`#btn-back`) no usa el historial del navegador directamente. Prioriza, en orden: (1) `window.volverAlIndice()` si la vista la define; (2) GET `/api/modulo-padre?ruta=` para subir al módulo padre según la jerarquía de `SYSRoles` (Nivel 1/2/3 vía `Dependencia`); (3) fallback al **stack de navegación** en `sessionStorage` (`NavStack`), que evita duplicados y bucles ida/vuelta y se limpia al llegar a `/produccionProceso`.

### Flujo de requerimientos por telar (calendario semanal)
Al marcar un checkbox de turno (rizo/pie/día), `handleRequerimientoChange` resuelve cuenta/calibre/fibra/orden (usando la selección guardada del modal o los datos del proceso) y hace **POST `/inventario-telares/guardar`**; al desmarcar, verifica el estado en backend (no confía en el caché del frontend) y elimina, gestionando el caso de **tela reservada** (eliminar reserva, reprogramar fecha/turno, o cancelar). El render del calendario hace **lookback de 30 días** para mostrar registros con fecha pasada y limita a 7 columnas (rellenando con días futuros), preservando los cambios recientes (≤10 s) y los eliminados del usuario durante recargas. El inventario se cachea 5 s con deduplicación de peticiones concurrentes (`obtenerInventarioConCache`).

### Importación Excel (catálogos)
`catalog-actions` genera por ruta un flujo de subida con SweetAlert2: drag&drop, validación de tipo (`.xlsx`/`.xls`) y tamaño (≤10 MB), POST multipart a `/planeacion/catalogos/{route}-modelos/excel`, y resumen detallado (procesados/creados/actualizados/errores con fila y mensaje). El backend usa `maatwebsite/excel` (clases en `app/Imports/`).

### Integraciones desde el ámbito
- **Telegram**: indirecta. Los modales "Atado de Julio" y "Cortado de Rollo" (POST a `notificar.*`) disparan, en sus controladores, notificaciones Telegram por módulo (`SYSMensaje::getChatIdsPorModulo`).
- **Excel**: subida desde `catalog-actions` (import) y exportaciones vía `button-report`.
- **PWA / Chart.js**: el layout principal carga el manifest y `app-pwa.js` (si habilitado) y expone `window.Chart` para reportes con canvas (engomado, promedio de paros).
- **Vite**: tres entradas — `app.js` (carga toda la librería vía `bootstrap.js`; lo carga `<x-layout-scripts>`), y `app-core.js` + `app-filters.js` (los carga `layouts/app.blade.php` al final del body).

### Efectos colaterales y notas
- `module-grid` versiona las imágenes por `filemtime` (cache-busting) y prefiere WebP con fallback PNG; marca la primera tarjeta como imagen LCP.
- `user-modal` obtiene la IP LAN por WebRTC solo cuando el servidor reporta localhost; persiste el nombre de dispositivo en `localStorage`.
- `app-core.js` fuerza recarga al volver del bfcache (`pageshow.persisted`) para evitar estados obsoletos.
