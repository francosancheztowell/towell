# Sistema, Autenticación y Configuración

> Generado automáticamente — documentación detallada del módulo

---

## 1. Propósito del módulo

Este ámbito agrupa la **infraestructura transversal** de la aplicación Towell (Laravel 12 + SQL Server, industria textil). No produce datos de planta, sino que habilita y gobierna el acceso al resto de módulos productivos (Planeación, Tejido, Urdido, Engomado, Atadores, Tejedores, Mantenimiento). Resuelve:

- **Autenticación** de empleados por número de empleado + contraseña numérica, con migración automática de contraseñas legacy en texto plano a `bcrypt`, y generación de **código QR** por usuario (que codifica el número de empleado para llenar el formulario de login al escanearlo).
- **Gestión de usuarios** (alta/baja/modificación, foto, departamento/área, turno) y su **matriz de permisos** por módulo.
- **Sistema de permisos granular** por módulo (`SYSRoles` ↔ `SYSUsuariosRoles`) con 5 tipos: `acceso`, `crear`, `modificar`, `eliminar`, `registrar`.
- **Gestión de módulos / menú** (`SYSRoles`): jerarquía de 3 niveles (`Dependencia`/`orden`), imágenes de módulo, y propagación automática de permisos a todos los usuarios al crear un módulo.
- **Navegación dinámica**: pantalla principal `/produccionProceso`, submódulos nivel 2 y nivel 3, y APIs de resolución de ruta padre.
- **Configuración del sistema**: catálogo de **Departamentos**, **Secuencia de Folios** (`SSYSFoliosSecuencias`), **Mensajes / destinatarios Telegram** (`SYSMensajes`), bandera **Productivo/Prueba** por usuario (Base de Datos), y **carga masiva de planeación** desde Excel.
- **Middleware de soporte**: contexto SQL Server para auditoría (`SetSqlContextInfo`), `ForceHttps` y `NoCacheHtmlResponses`.

**Submódulos que abarca** (rutas reales): `/configuracion` (índice), `configuracion/usuarios/*`, `configuracion/utileria/*` (incl. gestión de módulos y carga de planeación), `configuracion/basededatos`, `configuracion/departamentos`, `configuracion/secuencia-de-folios`, `configuracion/mensajes`, y la navegación pública/auth (`/produccionProceso`, `/submodulos/*`, login/logout).

> **NOTA SOBRE EL TYPO INTENCIONAL DEL PROYECTO**: en la tabla `SYSRoles` la columna del 5.º permiso se llama **`reigstrar`** (mal escrito). En `SYSUsuariosRoles` la columna análoga sí se llama correctamente **`registrar`**. El código convive con ambas grafías (ver §8). Igualmente, el directorio de vistas de catálogos del proyecto es `resources/views/catalagos/` (typo preservado).

---

## 2. Rutas

Las rutas de este ámbito están en `routes/public.php`, `routes/modules/navigation.php` y `routes/modules/configuracion.php`. El dispatcher `routes/web.php` incluye `public.php` (sin middleware) y, dentro del grupo `auth`, `navigation.php` y `configuracion.php`.

> **Permisos**: ninguna de estas rutas declara middleware de permiso a nivel de ruta. El control de acceso real se hace **dentro de los controllers/vistas** (p. ej. `showSubModulos` valida `acceso` en BD; las vistas usan `userCan(...)`). Por eso la columna "Permiso requerido" indica la verificación efectiva, no un middleware.

### 2.1 Públicas — `routes/public.php` (sin `auth`)

| Método | URI | Controller@método | Middleware | Permiso requerido |
|---|---|---|---|---|
| GET | `/` | `AuthController@showLoginForm` (name `home`) | `guest` | — |
| GET | `/login` | `AuthController@showLoginForm` (name `login`) | `guest` | — |
| POST | `/login` | `AuthController@login` | `guest` | — |
| POST | `/logout` | `AuthController@logout` (name `logout`) | `auth` | sesión activa |
| GET | `/obtener-empleados/{area}` | `UsuarioController@obtenerEmpleados` (name `usuarios.obtener-empleados`) | — | — |
| GET | `/test-404` | `SystemController@test404` (name `test-404`) | — | — |
| GET | `/offline` | vista `offline` (name `offline`) | — | — |
| GET | `/modulos-sin-auth` | `ModulosController@index` (name `modulos.sin.auth.index`) | — | — |
| GET | `/modulos-sin-auth/create` | `ModulosController@create` (name `modulos.sin.auth.create`) | — | — |
| POST | `/modulos-sin-auth` | `ModulosController@store` (name `modulos.sin.auth.store`) | — | — |
| GET | `/modulos-sin-auth/{id}/edit` | `ModulosController@edit` (name `modulos.sin.auth.edit`) | — | — |
| PUT | `/modulos-sin-auth/{id}` | `ModulosController@update` (name `modulos.sin.auth.update`) | — | — |
| DELETE | `/modulos-sin-auth/{id}` | `ModulosController@destroy` (name `modulos.sin.auth.destroy`) | — | — |

> El grupo `modulos-sin-auth` expone gestión de módulos **sin autenticación** (utilería/emergencia). `ModulosController::getModulosIndexRoute()` detecta el contexto `modulos.sin.auth.*` para redirigir de vuelta a ese índice.

### 2.2 Navegación — `routes/modules/navigation.php` (grupo `auth`)

| Método | URI | Controller@método | Middleware | Permiso requerido |
|---|---|---|---|---|
| GET | `/produccionProceso` | `UsuarioController@index` (name `produccion.index`) | `auth` | usuario autenticado |
| GET | `/submodulos/{modulo}` | `UsuarioController@showSubModulos` (name `submodulos.show`) | `auth` | `acceso` al módulo padre (validado en BD) |
| GET | `/submodulos-nivel3/{moduloPadre}` | `UsuarioController@showSubModulosNivel3` (name `submodulos.nivel3`) | `auth` | `acceso` (vía join en `ModuloService`) |
| GET | `/api/submodulos/{moduloPrincipal}` | `UsuarioController@getSubModulosAPI` (name `api.submodulos`) | `auth` | autenticado (401 si no) |
| GET | `/api/modulo-padre` | `UsuarioController@getModuloPadre` (name `api.modulo.padre`) | `auth` | — |
| GET | `/storage/usuarios/{filename}` | `StorageController@usuarioFoto` (name `storage.usuarios`) | `auth` | — |

### 2.3 Configuración — `routes/modules/configuracion.php` (grupo `auth`)

| Método | URI | Controller@método | Middleware | Permiso requerido |
|---|---|---|---|---|
| GET | `/configuracion` | `UsuarioController@showConfiguracion` (name `configuracion.index`) | `auth` | `acceso` a Configuración |
| GET | `/modulo-configuracion` | redirect 301 → `/configuracion` | `auth` | — |
| GET | `/configuracion/usuarios` y `/configuracion/usuarios/select` | `UsuarioController@select` (names `configuracion.usuarios.index` / `.select`) | `auth` | (vista usa `userCan`) |
| GET | `/configuracion/usuarios/create` | `UsuarioController@create` (name `configuracion.usuarios.create`) | `auth` | crear usuario |
| POST | `/configuracion/usuarios/store` | `UsuarioController@store` (name `configuracion.usuarios.store`) | `auth` | crear usuario |
| GET | `/configuracion/usuarios/{id}/qr` | `UsuarioController@showQR` (name `configuracion.usuarios.qr`) | `auth` | — |
| GET | `/configuracion/usuarios/{id}/edit` | `UsuarioController@edit` (name `configuracion.usuarios.edit`) | `auth` | modificar usuario |
| PUT | `/configuracion/usuarios/{id}` | `UsuarioController@update` (name `configuracion.usuarios.update`) | `auth` | modificar usuario |
| DELETE | `/configuracion/usuarios/{id}` | `UsuarioController@destroy` (name `configuracion.usuarios.destroy`) | `auth` | eliminar usuario |
| POST | `/configuracion/usuarios/{id}/permisos` | `UsuarioController@updatePermiso` (name `configuracion.usuarios.permisos.update`) | `auth` | modificar permisos |
| GET | `/configuracion/utileria` (`moduloPadre=909`) | `UsuarioController@showSubModulosNivel3` (name `configuracion.utileria.index`) | `auth` | `acceso` |
| GET | `/configuracion/utileria/modulos` | `ModulosController@index` (name `configuracion.utileria.modulos.index`) | `auth` | — |
| GET | `/configuracion/utileria/modulos/create` | `ModulosController@create` (name `...create`) | `auth` | — |
| POST | `/configuracion/utileria/modulos` | `ModulosController@store` (name `...store`) | `auth` | — |
| GET | `/configuracion/utileria/modulos/{id}/edit` | `ModulosController@edit` (name `...edit`) | `auth` | — |
| PUT | `/configuracion/utileria/modulos/{id}` | `ModulosController@update` (name `...update`) | `auth` | — |
| DELETE | `/configuracion/utileria/modulos/{id}` | `ModulosController@destroy` (name `...destroy`) | `auth` | — |
| POST | `/configuracion/utileria/modulos/{id}/toggle-acceso` | `ModulosController@toggleAcceso` (name `...toggle.acceso`) | `auth` | — |
| POST | `/configuracion/utileria/modulos/{id}/toggle-permiso` | `ModulosController@togglePermiso` (name `...toggle.permiso`) | `auth` | — |
| POST | `/configuracion/utileria/modulos/{id}/sincronizar-permisos` | `ModulosController@sincronizarPermisos` (name `...sincronizar.permisos`) | `auth` | — |
| GET | `/configuracion/utileria/modulos/{modulo}/duplicar` | `ModulosController@duplicar` (name `...duplicar`) | `auth` | — |
| GET | `/configuracion/utileria/api/modulos/nivel/{nivel}` | `ModulosController@getModulosPorNivel` (name `...api.modulos.nivel`) | `auth` | — |
| GET | `/configuracion/utileria/api/modulos/submodulos/{dependencia}` | `ModulosController@getSubmodulos` (name `...api.modulos.submodulos`) | `auth` | — |
| GET | `/configuracion/utileria/cargarcatalogos` | vista `modulos/cargar-catalogos` (name `...cargar-catalogos`) | `auth` | — |
| GET | `/configuracion/utileria/cargarplaneacion` | `ConfiguracionController@cargarPlaneacion` (name `...cargar-planeacion`) | `auth` | — |
| POST | `/configuracion/utileria/cargarplaneacion/upload` | `ConfiguracionController@procesarExcel` (name `...cargar-planeacion.upload`) | `auth` | — |
| POST | `/configuracion/utileria/cargarplaneacion/upload-update` | `ConfiguracionController@procesarExcelUpdate` (name `...cargar-planeacion.upload-update`) | `auth` | — |
| GET | `/configuracion/cargar-planeacion` | `ConfiguracionController@cargarPlaneacion` (name `configuracion.cargar.planeacion`) | `auth` | — |
| POST | `/configuracion/cargar-planeacion/upload` | `ConfiguracionController@procesarExcel` (name `configuracion.cargar.planeacion.upload`) | `auth` | — |
| GET | `/configuracion/modulos` | `ModulosController@index` (name `configuracion.modulos.index`) | `auth` | — |
| GET | `/configuracion/basededatos` | `BaseDeDatosController@index` (name `configuracion.basededatos`) | `auth` | — |
| POST | `/configuracion/basededatos/update-productivo` | `BaseDeDatosController@updateProductivo` (name `...update-productivo`) | `auth` | — |
| GET | `/configuracion/departamentos` | `DepartamentosController@index` (name `configuracion.departamentos`) | `auth` | — |
| POST | `/configuracion/departamentos` | `DepartamentosController@store` (name `...store`) | `auth` | — |
| PUT | `/configuracion/departamentos/{id}` | `DepartamentosController@update` (name `...update`) | `auth` | — |
| DELETE | `/configuracion/departamentos/{id}` | `DepartamentosController@destroy` (name `...destroy`) | `auth` | — |
| GET | `/configuracion/secuencia-de-folios` | `SecuenciaFoliosController@index` (name `configuracion.secuencia-folios`) | `auth` | — |
| POST | `/configuracion/secuencia-de-folios` | `SecuenciaFoliosController@store` (name `...store`) | `auth` | — |
| PUT | `/configuracion/secuencia-de-folios/{id}` | `SecuenciaFoliosController@update` (name `...update`) | `auth` | — |
| DELETE | `/configuracion/secuencia-de-folios/{id}` | `SecuenciaFoliosController@destroy` (name `...destroy`) | `auth` | — |
| GET | `/configuracion/mensajes` | `MensajesController@index` (name `configuracion.mensajes`) | `auth` | — |
| POST | `/configuracion/mensajes` | `MensajesController@store` (name `...store`) | `auth` | — |
| PUT | `/configuracion/mensajes/{id}` | `MensajesController@update` (name `...update`) | `auth` | — |
| DELETE | `/configuracion/mensajes/{id}` | `MensajesController@destroy` (name `...destroy`) | `auth` | — |
| GET | `/configuracion/mensajes/{id}/obtener-chat-ids` | `MensajesController@obtenerChatIds` (name `...obtener-chat-ids`) | `auth` | — |
| PUT | `/configuracion/mensajes/{id}/chat-id` | `MensajesController@actualizarChatId` (name `...actualizar-chat-id`) | `auth` | — |

**Rutas sueltas fuera del grupo `configuracion` (al final de `configuracion.php`, dentro de `auth`):**

| Método | URI | Controller@método | Middleware |
|---|---|---|---|
| GET | `/modulos/{modulo}/duplicar` | `ModulosController@duplicar` (name `modulos.duplicar`) | `auth` |
| POST | `/modulos/{modulo}/toggle-acceso` | `ModulosController@toggleAcceso` (name `modulos.toggle.acceso`) | `auth` |
| POST | `/modulos/{modulo}/toggle-permiso` | `ModulosController@togglePermiso` (name `modulos.toggle.permiso`) | `auth` |
| GET | `/api/modulos/nivel/{nivel}` | `ModulosController@getModulosPorNivel` (name `api.modulos.nivel`) | `auth` |
| GET | `/api/modulos/submodulos/{dependencia}` | `ModulosController@getSubmodulos` (name `api.modulos.submodulos`) | `auth` |

---

## 3. Controllers

### 3.1 `AuthController` — `app/Http/Controllers/AuthController.php`

- **`showLoginForm()`** (`AuthController.php:13`) — Si ya hay sesión (`Auth::check()`), redirige a `/produccionProceso`; si no, devuelve la vista `login`. Sin parámetros. Respuesta: vista o redirect.
- **`login(LoginRequest $request)`** (`AuthController.php:22`) — Autentica por número de empleado + contraseña.
  - **Request validado** (`LoginRequest`): `numero_empleado` (required, string, max:30), `contrasenia` (required, string).
  - **Tablas/queries**: lee `Usuario` (tabla `dbo.SYSUsuario`, conexión `sqlsrv`) seleccionando `idusuario, numero_empleado, nombre, contrasenia` por `numero_empleado`.
  - **Lógica de contraseña**: si la almacenada empieza con `$2y$` usa `Hash::check`; si `needsRehash`, la re-hashea y guarda. Si es **texto plano legacy**, compara con `hash_equals` y, si coincide, la re-hashea a bcrypt (`$empleado->save()`). El mutador `setContraseniaAttribute` del modelo evita doble-hash.
  - **Éxito**: `Auth::login`, `session()->regenerate()`, flash `bienvenida=true`, redirect `intended('/produccionProceso')`.
  - **Fallo**: `back()->with('error', ...)`.
- **`logout(Request $request)`** (`AuthController.php:66`) — `Auth::logout`, invalida sesión, regenera token CSRF, redirige a `route('login')`.

### 3.2 `UsuarioController` — `app/Http/Controllers/UsuarioController.php`

Constructor inyecta `UsuarioRepository`, `UsuarioService`, `ModuloService`, `PermissionService`.

- **`index()`** (`:30`) — Pantalla principal `/produccionProceso`. Si no hay usuario válido redirige a `login`. Obtiene módulos nivel 1 vía `ModuloService::getModulosPrincipalesPorUsuario`. Opcionalmente "calienta" caché de submódulos si `config('app.modules_warmup_on_login')`. Calcula `tieneConfiguracion`. Respuesta: vista `produccionProceso` con `modulos`, `tieneConfiguracion`, `pageTitle`.
- **`create()`** (`:77`) — Formulario de alta. Carga `getAllModulos()`, `SysDepartamentos` (orden por `Depto`), y precomputa `modulosDescendientes` (cascada de permisos vía `obtenerDescendientesPorIdrolRaiz`). Vista `modulos.usuarios.form_usuario` (`isEdit=false`).
- **`store(StoreUsuarioRequest $request)`** (`:97`) — Crea usuario.
  - **Request validado** (`StoreUsuarioRequest`): `numero_empleado` (required, unique en `SYSUsuario.numero_empleado`), `nombre`, `contrasenia` (required min:4 en alta), `area`, `telefono`, `turno`, `foto` (image), `puesto`, `correo` (email).
  - Extrae permisos del request (claves que empiezan con `modulo_`). Delega a `UsuarioService::create` (hashea contraseña, guarda foto, guarda permisos). Redirige a `configuracion.usuarios.select` con `success`. En error: `back()` con `error` (log).
- **`select(Request $request)`** (`:124`) — Listado de todos los usuarios (`UsuarioRepository::getAllForSelect`). Vista `modulos.usuarios.select`.
- **`obtenerEmpleados(string $area)`** (`:136`) — API: devuelve `Usuario::where('area', $area)->get()` (JSON implícito). Tolerante a excepciones (devuelve `[]`).
- **`showQR(int $idusuario)`** (`:148`) — Muestra QR del usuario. Si no existe, redirige a `select` con `error`. Vista `modulos.usuarios.qr`.
- **`edit(int $id)`** (`:163`) — Formulario de edición. Carga usuario, `getAllModulos`, permisos del usuario (`PermissionService::getAllPermisosUsuario`), departamentos y descendientes. Vista `form_usuario` (`isEdit=true`).
- **`update(StoreUsuarioRequest $request, int $id)`** (`:191`) — Actualiza usuario + permisos vía `UsuarioService::update`. Tras actualizar, llama `ModuloService::limpiarCacheUsuario($id)`. Redirige a `select` con `success`; en error `back()`.
- **`destroy(int $id)`** (`:230`) — Elimina usuario vía `UsuarioService::delete` (borra permisos primero). Redirige a `select`.
- **`updatePermiso(Request $request, int $id)`** (`:261`) — **API JSON**. Actualiza/crea un permiso individual.
  - **Request**: `idrol`, `campo` (`acceso|crear|modificar|eliminar|registrar`), `valor` (truthy→1).
  - **Tabla**: `SYSUsuariosRoles` (conexión `sqlsrv`). Si existe la fila (idusuario,idrol) hace `DB::connection('sqlsrv')->table('SYSUsuariosRoles')->update([$campo => $valor, 'assigned_at' => now()])`; si no, la crea inicializando los 5 permisos (incluye `registrar`). Respuesta JSON `success`.
- **`showConfiguracion()`** (`:320`) — Índice `/configuracion`. Resuelve el módulo principal "configuracion" (`ModuloService::buscarModuloPrincipal`); si falta, redirige a `/produccionProceso`. Carga submódulos nivel 2 (`getSubmodulosPorModuloPrincipal`). Vista `modulos.configuracion`.
- **`showSubModulosConfiguracion(string $serie)`** (`:345`) — Submódulos nivel 3 dentro de configuración. Busca el padre en `SYSRoles` por `orden=$serie`. Carga nivel 3 (`getSubmodulosNivel3`). Vista `modulos.submodulos`.
- **`showTejedoresConfiguracion()`** (`:370`) — Atajo: busca en `SYSRoles` el módulo `Nivel=2, Dependencia=600, modulo='Configurar'` y delega a `showSubModulosConfiguracion($orden)`; fallback `'605'`.
- **`showSubModulos(string $moduloPrincipal)`** (`:389`) — Submódulos nivel 2 de un módulo principal.
  - Resuelve el padre por nombre/ruta (`buscarModuloPrincipal`) o por `orden` numérico o por `Ruta` exacta (`SYSRoles`).
  - **Verifica acceso**: `SYSUsuariosRoles::where('idrol',...)->where('idusuario',...)->where('acceso', true)->exists()`; si no, redirige a `/produccionProceso` con error y registra `Log::warning`.
  - Carga submódulos (`getSubmodulosPorModuloPrincipal`). Vista `modulos.submodulos`.
- **`showSubModulosNivel3(string $moduloPadre = '104')`** (`:460`) — Submódulos nivel 3 por orden padre. Carga `getSubmodulosNivel3` + info del padre (`SYSRoles` por `orden`). Vista `modulos.submodulos`. En error redirige a `/produccionProceso`.
- **`getSubModulosAPI(string $moduloPrincipal)`** (`:496`) — **API JSON** (precarga). 401 si no autenticado. Devuelve submódulos nivel 2 como JSON. 500 en error.
- **`getModuloPadre(Request $request)`** (`:523`) — **API JSON**. Dada una `ruta` (o `request()->path()`), busca en `SYSRoles` el módulo por `Ruta` exacta, luego por `LIKE 'ruta%'`, luego por `LIKE '%ultimaParte%'`. Devuelve `rutaPadre`: `/produccionProceso` si es nivel 1 o no resuelve; si tiene `Dependencia`, busca el padre por `orden` y devuelve su `Ruta`.
- **(privado) `obtenerDescendientesPorIdrolRaiz($modulos)`** (`:618`) — Para cada módulo nivel 1 arma el listado de `idrol` de sus descendientes (niveles 2 y 3) recorriendo `Dependencia/orden`. Alimenta la cascada de permisos del formulario de usuario.

### 3.3 `ModulosController` — `app/Http/Controllers/ModulosController.php`

Constructor inyecta `ModuloService`.

- **`index()`** (`:27`) — Lista todos los `SYSRoles` (orden ASC) + módulos principales (`Nivel=1`, `Dependencia=NULL`). Vista `modulos.gestion-modulos.index`.
- **`create()`** (`:47`) — Devuelve la vista `modulos.gestion-modulos.create` con módulos principales para usar como dependencia. **NOTA**: la vista `create.blade.php` no existe en el repo (ver §6/Notas).
- **`store(Request $request)`** (`:96`) — Crea un módulo.
  - **Validación** (`Validator::make`): `orden` (required, max:50), `modulo` (required, max:255), `acceso/crear/modificar/eliminar/`**`reigstrar`** (boolean), `imagen_archivo` (image jpeg/png/jpg/gif max:2048), `Dependencia` (nullable max:50), `Nivel` (required int 1–3).
  - **Reglas de jerarquía**: `orden` único; nivel 1 ⇒ sin dependencia; nivel >1 ⇒ dependencia obligatoria y existente; el `Nivel` del hijo debe ser mayor que el del padre.
  - Convierte checkboxes a 0/1 (incluida la columna **`reigstrar`**). Guarda imagen optimizada en `public/images/fotos_modulos/` (`ImageOptimizer::optimizeAndSave`, fallback a `move`).
  - Crea en `SYSRoles`, llama `actualizarPermisosNuevoModulo($modulo)` (propaga a todos los usuarios) y `limpiarCacheTodosUsuarios()`. Redirige al índice contextual (`getModulosIndexRoute`).
- **`edit($id)`** (`:232`) — Carga `SYSRoles::findOrFail` + módulos principales (excluyendo el actual). Vista `modulos.gestion-modulos.edit` (no presente en repo).
- **`editSimple($id)`** (`:256`) — Igual que `edit` pero vista `modulos.gestion-modulos.edit-simple` (no presente en repo). **No tiene ruta declarada** en los archivos del ámbito.
- **`update(Request $request, $id)`** (`:287`) — Actualiza módulo. Misma validación que `store` (incluye **`reigstrar`**). Verifica `orden` único excluyendo el propio. Reemplaza imagen (borra la anterior). Convierte checkboxes a booleanos. Guarda y limpia caché de todos los usuarios.
- **`destroy($id)`** (`:370`) — Elimina módulo si **no** tiene submódulos dependientes (`SYSRoles::where('Dependencia', $modulo->orden)`). Borra primero las filas relacionadas en `SYSUsuariosRoles` y luego el módulo. Limpia caché.
- **`sincronizarPermisos($id)`** (`:404`) — **API JSON (AJAX)**. Re-propaga permisos del módulo a todos los usuarios (`actualizarPermisosNuevoModulo`). Devuelve nº de registros.
- **`getModulosPorNivel($nivel)`** (`:427`) — **API JSON**. `SYSRoles::where('Nivel', $nivel)->orderBy('orden')`.
- **`getSubmodulos($dependencia)`** (`:449`) — **API JSON**. `SYSRoles::where('Dependencia', $dependencia)` ordenado por `Nivel`,`orden`.
- **`duplicar($id)`** (`:472`) — Replica un `SYSRoles` añadiendo `_copia` al `orden` y `(Copia)` al nombre. Redirige al índice.
- **`toggleAcceso($id)`** (`:495`) — **API JSON**. Invierte el campo `acceso` del propio `SYSRoles` (a nivel definición de módulo, no por usuario).
- **`togglePermiso(Request $request, $id)`** (`:522`) — **API JSON**. Cambia un permiso del `SYSRoles`; valida que `campo` ∈ `{crear, modificar, eliminar, `**`reigstrar`**`}` (400 si inválido).
- **(privado) `getModulosIndexRoute()`** (`:572`) — Devuelve la ruta de índice según el contexto (`modulos.sin.auth.*`, `configuracion.utileria.modulos.*`, `configuracion.modulos.*`/`modulos.*`); por defecto `configuracion.utileria.modulos.index`.
- **(privado) `actualizarPermisosNuevoModulo(SYSRoles $modulo)`** (`:597`) — Para cada usuario (`SYSUsuario`), crea/actualiza **solo** la fila `(idusuario, idrol=este módulo)` en `SYSUsuariosRoles` con los permisos por defecto del módulo. **Mapea el typo**: `registrar => (int)($modulo->reigstrar ?? $modulo->registrar ?? 0)` — lee de la columna `reigstrar` de `SYSRoles` y escribe en la columna `registrar` de `SYSUsuariosRoles`. Devuelve nº de filas afectadas.
- **(privado) `limpiarCacheTodosUsuarios()`** (`:645`) — Recorre todos los `SYSUsuario` y llama `ModuloService::limpiarCacheUsuario` para cada uno.

### 3.4 `SystemController` — `app/Http/Controllers/SystemController.php`

- **`test404()`** (`:7`) — `abort(404)`. Sirve para probar la página de error 404.

### 3.5 `StorageController` — `app/Http/Controllers/StorageController.php`

- **`usuarioFoto(string $filename)`** (`:7`) — Sirve un archivo desde `storage/app/public/usuarios/{filename}`; `abort(404)` si no existe. **NOTA**: el upload de fotos en `UsuarioService::guardarFoto` y `ModulosController` guarda en `public/images/fotos_usuarios` y `public/images/fotos_modulos`, no en `storage/app/public/usuarios`.

### 3.6 `Configuracion\BaseDeDatosController` — `app/Http/Controllers/Configuracion/BaseDeDatosController.php`

- **`index()`** (`:11`) — Si la columna `Productivo` no existe en `SYSUsuario` (conexión por defecto/`sqlsrv`) la crea (`Schema::table`, tinyInteger default 0). Lista usuarios (`idusuario, nombre, area, puesto, Productivo`). Vista `modulos.configuracion.basededatos`.
- **`updateProductivo()`** (`:29`) — **API JSON**. Valida `user_id` (exists `SYSUsuario,idusuario`) y `productivo` (in 0,1). Actualiza `SYSUsuario.Productivo`. Devuelve estado `Productivo`/`Prueba`.

### 3.7 `Configuracion\DepartamentosController` — `app/Http/Controllers/Configuracion/DepartamentosController.php`

CRUD del catálogo `dbo.SysDepartamentos` (modelo `SysDepartamento`, PK `id`).

- **`index()`** (`:17`) — Lista `id, Depto, Descripcion` ordenado por `id`. Vista `modulos.configuracion.departamentos`.
- **`store(Request $request)`** (`:29`) — Valida `Depto` (required max:100), `Descripcion` (nullable max:255). Crea. Responde JSON (`item`) si `expectsJson`, si no redirige con `success`.
- **`update(Request $request, int $id)`** (`:54`) — `findOrFail`, misma validación, actualiza. JSON o redirect.
- **`destroy(int $id)`** (`:81`) — `findOrFail`, borra. JSON o redirect.

### 3.8 `Configuracion\SecuenciaFoliosController` — `app/Http/Controllers/Configuracion/SecuenciaFoliosController.php`

CRUD de `dbo.SSYSFoliosSecuencias` (modelo `SSYSFoliosSecuencia`, PK `Id`).

- **`index()`** (`:17`) — Lista todas las secuencias (orden por `Id`). Vista `modulos.configuracion.secuencia-de-folios`.
- **`store(Request $request)`** (`:29`) — Valida `Modulo` (nullable max:100), `Prefijo` (nullable max:20), `Consecutivo` (required int min:0). Crea (mapea a columnas `modulo/prefijo/consecutivo`). JSON o redirect.
- **`update(Request $request, int $id)`** (`:64`) — `findOrFail`, actualiza `modulo/prefijo/consecutivo`. JSON o redirect.
- **`destroy(int $id)`** (`:100`) — `findOrFail`, borra. JSON o redirect.

### 3.9 `Configuracion\MensajesController` — `app/Http/Controllers/Configuracion/MensajesController.php`

CRUD de `dbo.SYSMensajes` (destinatarios y banderas de notificación Telegram).

- **(privado) `obtenerChatIdsDesdeTelegram()`** (`:21`) — Llama `getUpdates` de la API de Telegram con `config('services.telegram.bot_token')` y extrae `chat_id` únicos de los mensajes recibidos (con `first_name`, `username`, `type`). Devuelve `chat_ids` + `ultimo_chat_id`.
- **`index()`** (`:62`) — Lista mensajes con `departamento` (eager load) + catálogo de departamentos. Vista `modulos.configuracion.mensajes`.
- **`store(Request $request)`** (`:76`) — Valida `DepartamentoId` (required, integer, debe existir en `SysDepartamentos`), `Telefono` (required max:20), `Token` (required max:255), `Nombre` (nullable max:150) y un conjunto de banderas booleanas: `Activo`, `Desarrolladores`, `DesarrolladoresPrue`, `NotificarAtadoJulio`, `CorteSEF`, `MarcasFinales`, `ReporteElectrico`, `ReporteMecanico`, `ReporteTiempoMuerto`, `Atadores`, `InvTrama`, `UrdidoCalidad`, `Calidad`. Normaliza cada bandera con `request->boolean()`. Crea. JSON (`itemToArray`) o redirect.
- **`update(Request $request, int $id)`** (`:137`) — `findOrFail`, misma validación/normalización que `store`, actualiza. JSON o redirect.
- **`destroy(int $id)`** (`:200`) — `findOrFail`, borra. JSON o redirect.
- **`obtenerChatIds(int $id)`** (`:215`) — **API JSON**. Verifica que el mensaje exista y devuelve los `chat_ids` detectados por Telegram + instrucciones de uso.
- **`actualizarChatId(Request $request, int $id)`** (`:235`) — **API JSON**. Valida `ChatId` (required max:50) y lo guarda en `SYSMensajes.Token`. Devuelve el item actualizado.
- **(privado) `itemToArray(SYSMensaje $mensaje)`** (`:253`) — Serializa un mensaje a array para respuestas JSON (incluye `DepartamentoNombre`, banderas y `FechaRegistro` formateada).

### 3.10 `Configuracion\ConfiguracionController` — `app/Http/Controllers/Configuracion/ConfiguracionController.php`

Carga masiva del **Programa de Tejido** desde Excel.

- **`cargarPlaneacion()`** (`:25`) — Devuelve la vista `modulos.configuracion.cargar-planeacion`.
- **`procesarExcel(Request $request)`** (`:33`) — **API JSON**. Importación **destructiva** (reemplazo total).
  - **Validación**: `excel_file` (required, file, mimes `xlsx,xls`, max 10240 KB). `set_time_limit(600)`.
  - **Flujo**: transacción; `TRUNCATE` (o `DELETE`+`DBCC CHECKIDENT RESEED`) de `ReqProgramaTejidoLine` y `ReqProgramaTejido`; desactiva el observer y el query log; `Excel::import(ReqProgramaTejidoSimpleImport)`; reactiva `ReqProgramaTejidoObserver`. Luego **regenera líneas** por batch (chunks de 100) validando fechas (año ≥ 2000 y `FechaFinal > FechaInicio`) vía `ReqProgramaTejido::regenerarLineas`. Llama `Artisan::call('programa-tejido:actualizar-estado-proceso')`. Devuelve estadísticas (`deleted`, `created`, `skipped`, totales). Rollback + re-observe en error.
- **`procesarExcelUpdate(Request $request)`** (`:218`) — **API JSON**. Igual que `procesarExcel` pero **NO elimina** existentes (usa `ReqProgramaTejidoUpdateImport`, modo upsert). Regenera líneas igual y actualiza estado EnProceso. Devuelve `updated`/`created`.

> Estos dos métodos pertenecen al dominio de Planeación (Programa Tejido) pero se exponen desde el módulo de Configuración → Utilería → Cargar Planeación.

---

## 4. Services y Helpers del ámbito

### 4.1 `ModuloService` — `app/Services/ModuloService.php`

Caché de menús/permisos. `CACHE_TTL = 3600` (1 h), `CACHE_PREFIX = 'modulos_v2'`. El prefijo real incluye `APP_ENV` (`getCachePrefix()`) para que local/producción no compartan caché.

- **(privado) `getModulosPorNivelYUsuario($idusuario, $nivel, $dependencia=null, $cacheKey=null)`** — Núcleo: `Cache::remember` con JOIN `SYSRoles` ↔ `SYSUsuariosRoles` filtrando por `idusuario` y `acceso=true`. Nivel 1 ⇒ `Dependencia NULL`; otros ⇒ `Dependencia=$dependencia`. Mapea cada módulo a array con `nombre, imagen, ruta (normalizada), orden, nivel, dependencia` y los 5 permisos (`acceso, crear, modificar, eliminar, registrar`). Devuelve `Collection`.
- **`getModulosPrincipalesPorUsuario($idusuario)`** — Nivel 1; ordena con **Configuración primero**.
- **`getSubmodulosPorModuloPrincipal($moduloPrincipal, $idusuario, ?SYSRoles $moduloPadre=null)`** — Resuelve el padre (si no se pasa) y obtiene nivel 2 por `orden` del padre.
- **`getSubmodulosNivel3($ordenPadre, $idusuario)`** — Nivel 3 por orden padre.
- **`getAllModulos()`** — Todos los `SYSRoles` (incluye la columna **`reigstrar`** en el select), orden por `orden`.
- **`buscarModuloPrincipal($moduloPrincipal)`** — Resuelve un módulo nivel 1 por: nombre exacto → ruta exacta (si empieza con `/`) → `LIKE` en nombre → `LIKE` en ruta (slug). Cacheado.
- **`limpiarCacheUsuario($idusuario)`** — Olvida caché de principales y de submódulos para una lista fija de módulos (`planeacion, tejido, urdido, engomado, atadores, tejedores, mantenimiento, programa-urd-eng, programaurdeng, configuracion`). **Llamar siempre tras cambios de permisos.**
- **(privado) `generarRutaFallback($modulo)`** / **`normalizarRuta($rutaDb, $modulo)`** — Construyen/normalizan rutas (slash inicial, slug del padre) cuando `Ruta` viene vacía.
- **(privado) `getCachePrefix()`** — `'modulos_v2_' . app()->environment()`.

### 4.2 `PermissionService` — `app/Services/PermissionService.php`

- **`guardarPermisos(array $permisos, int $idusuario)`** — **Reemplazo total** en transacción: borra todos los `SYSUsuariosRoles` del usuario y recrea uno por cada `SYSRoles`, leyendo del array de formulario las claves `modulo_{idrol}_{acceso|crear|modificar|eliminar|registrar}`. Escribe en la columna **`registrar`** de `SYSUsuariosRoles`.
- **`getPermisosUsuario(int $idusuario, int $idrol)`** — Permiso de un usuario para un rol (scopes `porUsuario`/`porRol`).
- **`getAllPermisosUsuario(int $idusuario)`** — Todos los permisos del usuario con relación `rol`, indexados por `idrol`.

### 4.3 `UsuarioService` — `app/Services/UsuarioService.php`

Inyecta `UsuarioRepository` y `PermissionService`.

- **`create(array $data, ?UploadedFile $foto, array $permisos)`** — Guarda foto (si hay), hashea `contrasenia` con `Hash::make`, genera `remember_token`, crea el usuario (repositorio) y guarda permisos (`PermissionService::guardarPermisos`).
- **`update(int $id, array $data, ?UploadedFile $foto, array $permisos)`** — Guarda foto si hay; hashea contraseña solo si viene no vacía (si no, la quita del `$data`). Actualiza vía repositorio y **siempre** regraba permisos desde el formulario.
- **`delete(int $id)`** — Borra primero `SYSUsuariosRoles` del usuario y luego el usuario.
- **(privado) `guardarFoto(UploadedFile $foto)`** — Mueve la foto a `public_path('images/fotos_usuarios')` con nombre `time()_nombreOriginal`.

### 4.4 Helper `permission-helpers.php` — `app/Helpers/permission-helpers.php`

Auto-cargado por Composer. Funciones globales:

- **`userCan(string $action, $module): bool`** — Verifica un permiso del usuario autenticado. `$module` puede ser `idrol` numérico o nombre de módulo (`SYSRoles.modulo`). Lee `SYSUsuariosRoles` por `(idusuario, idrol)` y comprueba `$permission->$action == 1`. Devuelve `false` ante cualquier error. **`$action` válido**: `acceso, crear, modificar, eliminar, registrar` (la grafía correcta de `SYSUsuariosRoles`).
- **`moduleNameForRoute(?string $path = null): ?string`** — Dado un path (o el actual), devuelve el `modulo` de `SYSRoles` cuya `Ruta` coincide exacta → prefijo (`LIKE 'ruta%'`) → última parte (`LIKE '%parte%'`). Útil para validar permisos en pantallas con módulo propio.
- **`userPermissions($module): ?object`** — Devuelve el registro completo de `SYSUsuariosRoles` del usuario para un módulo/rol (o `null`).

### 4.5 Trait `HasUserPermissions` — `app/Traits/HasUserPermissions.php`

Versión orientada a controllers, con **caché estático en memoria** (`$permissionsCache`).

- **`userCan(string $action, $module, ?int $userId = null): bool`** — Como el helper pero cacheado por clave `perm_{userId}_{action}_{module}` y con `$userId` opcional.
- **`getUserPermissions($module, ?int $userId = null)`** — Registro `SYSUsuariosRoles` para módulo/rol.
- **`userCanAll(array $permissions, ?int $userId = null): bool`** — AND lógico sobre `['action' => 'module']`.
- **`userCanAny(array $permissions, ?int $userId = null): bool`** — OR lógico.
- **`clearPermissionsCache(): void`** — Vacía el caché estático.

### 4.6 `FolioHelper` y `SSYSFoliosSecuencia` (folios)

El modelo `SSYSFoliosSecuencia` (ver §5) expone los métodos transaccionales con bloqueo (`lockForUpdate`) `nextFolio($modulo, $pad=5)`, `nextFolioByPrefijo($prefijo, $pad=4)` y `nextFolioById($id, $pad=5)`. Cada uno incrementa el `consecutivo` y devuelve `['folio','prefijo','consecutivo']` (folio = `prefijo` + consecutivo con padding de ceros). `getColumnMap()` tolera variantes mal escritas de columnas (`moulo`, `conseutivo`). `nextFolioByPrefijo` puede **autocrear** la configuración inicializando el consecutivo a partir del máximo `Folio` existente en `TelBPM`. (El `FolioHelper` global descrito en CLAUDE.md envuelve esta lógica para preview vs. commit.)

---

## 5. Modelos y tablas

| Modelo | Tabla SQL Server | Conexión | PK | Campos clave |
|---|---|---|---|---|
| `Usuario` (`app/Models/Sistema/Usuario.php`) | `dbo.SYSUsuario` | `sqlsrv` | `idusuario` | `numero_empleado`, `nombre`, `contrasenia` (hidden, mutador bcrypt), `area`, `turno`, `foto`, `puesto`, `correo`, `remember_token`. `Authenticatable`; `getAuthPassword()→contrasenia` |
| `SYSUsuario` (`SYSUsuario.php`) | `SYSUsuario` | `sqlsrv` | `idusuario` | Modelo "plano" de usuario (sin timestamps) usado en propagación de permisos y bandera `Productivo`; `enviarMensaje`, `departamento` |
| `User` (`User.php`) | — | — | — | Modelo Laravel por defecto (no usado por la auth real, que usa `Usuario`) |
| `SYSRoles` (`SYSRoles.php`) | `SYSRoles` | `sqlsrv` | `idrol` | `orden`, `modulo`, `Nivel`, `Dependencia`, `Ruta`, `imagen`, permisos `acceso/crear/modificar/eliminar/`**`reigstrar`** (typo). Scopes `modulosPrincipales`, `submodulosDe`, `conAcceso` |
| `SYSUsuariosRoles` (`SYSUsuariosRoles.php`) | `SYSUsuariosRoles` | `sqlsrv` | (compuesta `idusuario`+`idrol`, sin PK Eloquent) | `acceso, crear, modificar, eliminar, `**`registrar`** (correcto), `assigned_at`. Scopes `porUsuario`, `porRol`, `conAcceso` |
| `SSYSFoliosSecuencia` (`SSYSFoliosSecuencia.php`) | `dbo.SSYSFoliosSecuencias` | `sqlsrv` | `Id` | `modulo`, `prefijo`, `consecutivo`. Métodos `nextFolio*` con `lockForUpdate` |
| `SYSMensaje` (`SYSMensaje.php`) | `dbo.SYSMensajes` | `sqlsrv` | `Id` | `DepartamentoId`, `Telefono`, `Token` (chat_id Telegram), `Activo`, banderas por módulo (`InvTrama`, `Desarrolladores`, `CorteSEF`, …). `getChatIdsPorModulo($columna)` |
| `SysDepartamento` (`SysDepartamento.php`) | `dbo.SysDepartamentos` | `sqlsrv` | `id` | `Depto`, `Descripcion` (usado por Departamentos y Mensajes) |
| `SysDepartamentos` (`SysDepartamentos.php`) | `dbo.SysDepartamentos` | `sqlsrv` | `Depto` (string, no incrementing) | `Depto`, `Descripcion` (variante con PK por nombre; usada en `UsuarioController::create/edit`) |

> **Discrepancia de permisos documentada**: el permiso de "registro" se almacena en `SYSRoles.reigstrar` (definición del módulo) y en `SYSUsuariosRoles.registrar` (permiso por usuario). El puente entre ambas grafías está en `ModulosController::actualizarPermisosNuevoModulo` (`registrar => $modulo->reigstrar ?? $modulo->registrar ?? 0`).

> Existen **dos modelos** apuntando a `dbo.SysDepartamentos` con PK distinta. Los controllers de Configuración usan `SysDepartamento` (PK `id`); `UsuarioController` usa `SysDepartamentos` (PK `Depto`).

---

## 6. Vistas Blade

### 6.1 `resources/views/login.blade.php`
Página de login standalone (no extiende layout). Branding a la izquierda, formulario `<x-auth.login-form />` a la derecha.
- **Script 1 (`pageshow`)**: si la página viene del historial (`event.persisted`) fuerza `window.location.reload()` (evita formularios cacheados).
- **Script 2 `scrollButtonIntoView()`**: en móvil/tablet, al enfocar un input hace scroll suave hasta el botón "Iniciar sesión".

### 6.2 `resources/views/components/auth/login-form.blade.php`
Componente del formulario de login. Campos `numero_empleado` (number) y `contrasenia` (password, solo numérico, `oninput` filtra no-dígitos). POST a `/login` con `@csrf`.
- **Script (`DOMContentLoaded`)**: el botón `#togglePassword` alterna `password`/`text` y los iconos ojo abierto/cerrado.

### 6.3 `resources/views/produccionProceso.blade.php`
Extiende `layouts.app`. Renderiza el grid de módulos nivel 1 con `<x-layout.module-grid>` (4 columnas, filtros activados). Sin JS inline.

### 6.4 `resources/views/modulos/submodulos.blade.php`
Grid de submódulos (`<x-layout.module-grid :isSubmodulos="true">`); muestra estado vacío si no hay submódulos. Sin JS inline.

### 6.5 `resources/views/modulos/configuracion.blade.php`
Grid de los submódulos de Configuración; estado vacío con `<x-empty.empty-state>`. Sin JS inline.

### 6.6 `resources/views/modulos/usuarios/form_usuario.blade.php`
Formulario de alta/edición de usuario + **matriz de permisos en cascada** (nivel 1 → descendientes). Secciones: datos del usuario (con foto/preview), selección de área/departamento, y tabla de permisos por módulo con checkboxes (`acceso/crear/modificar/eliminar/registrar`) y cabeceras "seleccionar todo".
Funciones JS inline:
- **`togglePassword` (arrow)** — Muestra/oculta el campo de contraseña.
- **`previewImage(evt)`** — Previsualiza la foto seleccionada antes de subir (FileReader).
- **`updateHeaderCheckbox(headerId, selector)`** — Sincroniza el checkbox de cabecera según si todos los de la columna están marcados.
- **`updateAllHeaders()`** — Recalcula todas las cabeceras de columna.
- **`setTodosLosPermisos(checked)`** — Marca/desmarca todos los permisos de la tabla.
- **`seleccionarTodos()` / `deseleccionarTodos()`** — Atajos de selección masiva.
- **`toggleAllPermiso(checkbox, permiso)`** — Marca/desmarca una columna de permiso completa.
- **`toggleAllAcceso/Crear/Modificar/Eliminar/Registrar(checkbox)`** — Wrappers de `toggleAllPermiso` por columna.
- **`sincronizarCheckboxPorIdrolPermiso(idrol, permiso, checked)`** — Sincroniza el checkbox de un módulo/permiso concreto.
- **`moduloTienePermisoMarcado(idrol, permiso)`** — Devuelve si un permiso está marcado para un módulo.
- **`limpiarPermisosSinAcceso(idrol)`** — Si se desactiva `acceso`, limpia los demás permisos de ese módulo.
- **`aplicarReglaAcceso(idrol, permisoCambiado, checked)`** — Regla: marcar cualquier permiso fuerza `acceso`; desmarcar `acceso` limpia el resto.
- **`aplicarCascadaNivel1(idrolPadre, permiso, checked)`** — Propaga un permiso del módulo nivel 1 a todos sus descendientes (usa `modulosDescendientes`).
- **`normalizarPermisosIniciales()`** — Coherencia inicial de checkboxes al cargar.
- **`sincronizarCheckboxDuplicado(checkboxCambiado)`** — Mantiene sincronizados checkboxes duplicados del mismo permiso.
- **`gestionarBloquesVisibles()`** — Muestra/oculta bloques de submódulos según el estado del padre.
- **`agregarListenerConSincronizacion(selector)`** — Adjunta listeners de cambio con la lógica de sincronización.

Envía vía POST/PUT a `configuracion.usuarios.store` / `.update` (campos `modulo_{idrol}_{permiso}`).

### 6.7 `resources/views/modulos/usuarios/select.blade.php`
Listado de usuarios con tarjetas/tabla, modal de creación rápida, y modal de filtros.
- **`iniciales($nombre)`** (PHP, no JS) — Iniciales para avatar.
- **`confirmarEliminacion(e)`** — SweetAlert de confirmación antes de borrar (DELETE `configuracion.usuarios.destroy`).
- **`abrirModalCrearUsuario()` / `cerrarModalCrearUsuario()`** — Abre/cierra el modal de alta rápida.
- **`cargarDepartamentos()`** — Carga el catálogo de departamentos/áreas para el modal.
- **`obtenerAreasDisponibles()`** — Extrae las áreas únicas de los usuarios listados.
- **`aplicarFiltros(filtros)`** — Filtra las filas según los criterios seleccionados.
- **`actualizarBadgeFiltros()`** — Muestra el contador de filtros activos.
- **`abrirModalFiltros()` (async)** — Abre el modal de filtros (carga opciones).

### 6.8 `resources/views/modulos/usuarios/qr.blade.php`
Tarjeta con foto/iniciales del usuario y su código QR (genera el QR con la librería `QRCode` a partir de `numero_empleado`; logo TOWELLIN al centro).
- **`iniciales($nombre)`** (PHP) — Iniciales para el avatar.
- **`downloadQR()`** — Renderiza el QR + logo en un canvas 300×300 y descarga un PNG (`QR_{numero}_{nombre}.png`). **El QR codifica el número de empleado** para escanearlo en el login (login por QR).

### 6.9 `resources/views/modulos/gestion-modulos/index.blade.php`
Tabla de gestión de módulos (`SYSRoles`) con modales de crear/editar y barra superior de acciones (editar/eliminar/sincronizar). Maneja jerarquía nivel/dependencia.
- **`poblarDependencias(selectId, nivel, helpId)`** — Llena el select de dependencias válidas según el nivel elegido (consume `api/modulos/nivel/{nivel}` / lógica local).
- **`calcularOrden(nivel, dependencia)`** — Sugiere el siguiente `orden` según nivel/dependencia.
- **`updateTopButtonsState()`** — Habilita/deshabilita los botones superiores según haya selección.
- **`clearSelection()` / `selectRow(row)`** — Manejan la fila seleccionada de la tabla.
- **`openModuloModal(modalId)` / `closeModuloModal(modalId)`** — Abren/cierran modales (crear/editar).
- **`handleTopEdit()`** — Rellena el modal de edición con los `data-*` de la fila y fija el `action` a `update` (PLACEHOLDER→id).
- **`handleTopDelete()`** — Confirma y envía el form de borrado (`destroy`).
- **`handleSyncPermisos()`** — Confirma y hace `fetch POST` a `configuracion/utileria/modulos/{id}/sincronizar-permisos` (con `X-CSRF-TOKEN`), muestra resultado con SweetAlert.

### 6.10 `resources/views/modulos/configuracion/departamentos.blade.php`
CRUD de departamentos con tabla seleccionable y modal.
- **`openModal()` / `closeModal()`** — Abren/cierran el modal de alta/edición.
- **`setActionsState(hasSelection)`** — Habilita acciones según selección.
- **`clearSelection()` / `selectRow(tr)`** — Selección de fila.
- **submit handler (async)** — Envía el form vía `http`/fetch a `configuracion.departamentos.store|update`.

### 6.11 `resources/views/modulos/configuracion/secuencia-de-folios.blade.php`
CRUD de secuencias de folios. Estructura análoga a departamentos.
- **`openModal()` / `closeModal()` / `setActionsState()` / `clearSelection()` / `selectRow(tr)`** — Manejo de modal y selección.
- **then handler (async) sobre `fetch`** — Procesa la respuesta de borrado (`destroy`).
- **submit handler (async)** — Crea/actualiza secuencia (`secuencia-folios.store|update`).

### 6.12 `resources/views/modulos/configuracion/mensajes.blade.php`
CRUD de mensajes/destinatarios Telegram + modal "Obtener Chat ID".
- **`openModal()` / `closeModal()` / `setActionsState()` / `clearSelection()` / `selectRow(tr)`** — Modal y selección de la tabla.
- **`openModalChatId()` / `closeModalChatId()`** — Abren/cierran el modal de Chat ID.
- **`cargarChatIds(id)` (async)** — `fetch GET` a `mensajes/{id}/obtener-chat-ids`; lista los chat_id detectados; cada botón asigna el chat_id (PUT `mensajes/{id}/chat-id`).
- **`siNo(v)`** — Helper para mostrar "Sí"/"No" de banderas booleanas.
- **submit handler (async)** — Crea/actualiza mensaje (`mensajes.store|update`).

### 6.13 `resources/views/modulos/configuracion/basededatos.blade.php`
Tabla de usuarios con toggle Productivo/Prueba y modal de filtros.
- **`openFilterModal()`** — Abre el modal de filtros.
- **`updateSelectAllState()`** — Sincroniza el "seleccionar todo" de los filtros.
- **`getUniqueValues(column)`** — Valores únicos de una columna para filtrar.
- **`applyFilters()` / `clearFilters()`** — Aplican/limpian los filtros sobre la tabla.
- (Toggle Productivo) hace POST a `configuracion.basededatos.update-productivo`.

### 6.14 `resources/views/modulos/configuracion/cargar-planeacion.blade.php`
Subida del Excel de Programa Tejido con barra de progreso y opción "modo actualización".
- **submit handler (sobre el form)** — Valida archivo (tipo `xlsx/xls`, ≤10MB), arma `FormData` con `excel_file` + `_token`, simula progreso, y hace `fetch POST` a `/configuracion/utileria/cargarplaneacion/upload-update` (modo actualización) o `/configuracion/cargar-planeacion/upload` (modo destructivo). Parsea respuesta tolerando texto no-JSON y muestra estadísticas con SweetAlert; al terminar redirige a `/planeacion/programa-tejido`. **Mantiene `fetch` deliberadamente** (no `window.http`) para tolerar respuestas no-JSON.

### 6.15 `resources/views/modulos/cargar-catalogos.blade.php`
Subida del Excel de **catálogos** desde Configuración → Utilería → Cargar Catálogos. Vista casi estática (extiende `layouts.app` con `ocultarBotones=true`): zona de carga con `<input type="file" name="excel_file" accept=".xlsx,.xls" required>` (límite indicado 10MB), botón "Ver Registros Importados" (link a `planeacion.catalogos.index`), botón "Cargar Catálogos", y una sección de progreso oculta por defecto (`#progressSection` con barra `#progressBar`). El form hace POST a `configuracion.utileria.cargar.catalogos.upload` con `@csrf`.
- **submit handler (`DOMContentLoaded` → `form.submit`)** — `preventDefault`; valida que haya archivo (si no, `showToast('Por favor selecciona un archivo Excel','error')`); muestra la sección de progreso, deshabilita el botón y simula avance (incrementos aleatorios hasta 90%); arma `FormData` y hace `fetch POST` a `form.action` con header `X-CSRF-TOKEN` (de `<meta name="csrf-token">`). Parsea la respuesta como JSON: si `data.success` muestra éxito (`showToast(..., 'success')`) y resetea el form, si no muestra error; en `catch` muestra "Error de conexión"; en `finally` rehabilita el botón.
- **`showToast(message, type)`** — Helper global (de `resources/js/utils/notifications.js` → toastr); no se define en la vista.

> **NOTA**: la ruta `GET /configuracion/utileria/cargarcatalogos` se registra como `Route::view` (sin controller PHP — ver §2.3), por lo que esta vista no tiene método de controller asociado; solo el endpoint de subida (`...cargar.catalogos.upload`) ejecuta lógica PHP en su controller correspondiente.

### 6.16 `resources/views/modulos/configuracion/ambiente.blade.php`
Vista placeholder ("Hola desde ambiente"). Sin JS.

---

## 7. JS dedicado

Este ámbito **no tiene archivos `.js` dedicados propios**: toda la lógica de cliente vive en los bloques `<script>` inline de los blades (documentados en §6). Las utilidades globales que estos scripts usan provienen del núcleo del proyecto (`resources/js/utils/http.js` → `window.http`, `resources/js/utils/notifications.js` → `window.notify`, además de SweetAlert2 `Swal`, jQuery, Select2). La carga de planeación mantiene `fetch` crudo a propósito (ver §6.14).

---

## 8. Lógica de negocio y reglas

### 8.1 Autenticación y migración de contraseñas
- El guard de auth usa el modelo **`Usuario`** (no `User`), con `numero_empleado` + `contrasenia`.
- **Migración legacy automática**: si la contraseña almacenada no empieza con `$2y$` se trata como texto plano; al hacer login correcto (`hash_equals`) se re-hashea a bcrypt y se guarda. Si ya es bcrypt y `needsRehash`, también se actualiza. El mutador `setContraseniaAttribute` evita doble-hash al persistir.
- Tras login: `session()->regenerate()`, flash `bienvenida`, y redirect a `/produccionProceso`.
- **Login por QR**: la vista `qr.blade.php` genera un QR que codifica el `numero_empleado`; al escanearlo se rellena el campo del login (no hay endpoint dedicado de "login por QR"; reutiliza el POST `/login`).

### 8.2 Modelo de permisos (2 niveles)
1. **Definición del módulo** (`SYSRoles`): cada módulo trae permisos "plantilla" `acceso/crear/modificar/eliminar/`**`reigstrar`** (typo). Al **crear un módulo**, esos defaults se propagan a **todos** los usuarios.
2. **Permiso por usuario** (`SYSUsuariosRoles`): columnas `acceso/crear/modificar/eliminar/`**`registrar`** (correcto) por `(idusuario, idrol)`.
- **El puente entre grafías** está en `ModulosController::actualizarPermisosNuevoModulo`: `registrar => $modulo->reigstrar ?? $modulo->registrar ?? 0`. Olvidar esto produce permisos de registro perdidos.
- **Verificación en runtime**: helper `userCan('accion','Modulo')` y trait `HasUserPermissions::userCan(...)` (este último con caché en memoria). Ambos resuelven `$module` por `idrol` numérico o por nombre (`SYSRoles.modulo`).
- **Cascada de permisos (UI)**: el formulario de usuario propaga el permiso de un módulo nivel 1 a sus descendientes (niveles 2 y 3) según `modulosDescendientes` calculado en el controller; marcar cualquier permiso fuerza `acceso`, y desmarcar `acceso` limpia el resto del módulo.

### 8.3 Jerarquía de módulos (menú dinámico)
- 3 niveles vía `Nivel` + `Dependencia` (que referencia el `orden` del padre): Nivel 1 (`Dependencia=NULL`) → Nivel 2 (`Dependencia`=orden de un nivel 1) → Nivel 3 (`Dependencia`=orden de un nivel 2).
- **Reglas al crear/editar módulo** (`ModulosController::store/update`): `orden` único; nivel 1 sin dependencia; nivel >1 con dependencia existente; `Nivel` del hijo > `Nivel` del padre. No se puede **eliminar** un módulo con submódulos dependientes.
- **Resolución de rutas**: `ModuloService::buscarModuloPrincipal` y `UsuarioController::getModuloPadre` resuelven por nombre/ruta/orden con varios fallbacks (`LIKE`), y `ModuloService::normalizarRuta`/`generarRutaFallback` evitan links muertos.

### 8.4 Caché de menús (`modulos_v2`)
- TTL 1 h; prefijo incluye `APP_ENV`. Toda la construcción del menú y permisos por módulo pasa por `Cache::remember`.
- **Efecto colateral crítico**: tras cualquier cambio de permisos o de módulos hay que **limpiar caché**:
  - `UsuarioController::update` → `ModuloService::limpiarCacheUsuario($id)`.
  - `ModulosController::store/update/destroy` → `limpiarCacheTodosUsuarios()` (itera todos los usuarios).
  - Si en producción no aparece el menú: `php artisan cache:clear && php artisan config:clear`.

### 8.5 Folios (`SSYSFoliosSecuencias`)
- Generación segura concurrente con `DB::transaction` + `lockForUpdate`; folio = `prefijo` + consecutivo con padding. `getColumnMap()` tolera columnas mal escritas (`moulo`, `conseutivo`). `nextFolioByPrefijo` autocrea config inicializando desde el máximo `Folio` de `TelBPM`. El CRUD de Configuración solo edita los registros base (modulo/prefijo/consecutivo).

### 8.6 Notificaciones Telegram (`SYSMensajes`)
- `MensajesController` administra destinatarios y banderas por módulo. El `Token` de cada registro es el **chat_id** de Telegram. `obtenerChatIds` consulta `getUpdates` del bot global (`config('services.telegram.bot_token')`) para descubrir chat_ids; `actualizarChatId` los persiste.
- En runtime, `SYSMensaje::getChatIdsPorModulo($columna)` devuelve los `Token` de registros `Activo=1` con la bandera de módulo activa (columnas permitidas: `InvTrama, Desarrolladores, DesarrolladoresPrue, NotificarAtadoJulio, CorteSEF, MarcasFinales, ReporteElectrico, ReporteMecanico, ReporteTiempoMuerto, Atadores, UrdidoCalidad, Calidad`). Es el puente que consume el módulo Telegram.

### 8.7 Carga de planeación (Excel)
- `procesarExcel` es **destructivo** (TRUNCATE + reimport con `ReqProgramaTejidoSimpleImport`); `procesarExcelUpdate` es **incremental** (`ReqProgramaTejidoUpdateImport`).
- Ambos desactivan el `ReqProgramaTejidoObserver` durante la importación masiva (rendimiento) y lo reactivan después, regeneran líneas por batch validando fechas (año ≥ 2000, `FechaFinal > FechaInicio`) y disparan `Artisan::call('programa-tejido:actualizar-estado-proceso')`. Efecto colateral cross-módulo: tras la carga, el flujo redirige a `/planeacion/programa-tejido`.

### 8.8 Middleware de soporte
- **`SetSqlContextInfo`**: si hay sesión y la conexión es `sqlsrv`, ejecuta `EXEC dbo.sp_SetAppContext ?, ?, ?` (uid, nombre/numero, ip) para que los **triggers de auditoría** capturen al usuario. Silencioso ante error (log warning). En tests con sqlite, no ejecuta el EXEC.
- **`ForceHttps`**: según `config('force_https')` y entorno, redirige a HTTPS (301) y agrega headers de seguridad configurables.
- **`NoCacheHtmlResponses`**: en respuestas HTML GET/HEAD fuerza `Cache-Control`. En login/home usa `no-store` estricto (evita persistir credenciales); en el resto `private, no-cache` (permite bfcache).

### 8.9 Efectos colaterales / integraciones
- **Fotos**: usuarios → `public/images/fotos_usuarios`; módulos → `public/images/fotos_modulos` (optimizadas con `ImageOptimizer`). `StorageController::usuarioFoto` sirve desde `storage/app/public/usuarios` (ruta distinta a donde se guardan los uploads → ver Notas).
- **Bandera `Productivo`**: `BaseDeDatosController::index` puede **alterar el esquema** en caliente (agrega la columna `Productivo` a `SYSUsuario` si falta).

---

### Notas / limitaciones de esta documentación
- Las vistas `gestion-modulos/create.blade.php`, `edit.blade.php` y `edit-simple.blade.php` referenciadas por `ModulosController::create/edit/editSimple` **no existen** en el repositorio (solo `index.blade.php`); en la práctica la creación/edición se hace mediante los modales de `index.blade.php`. `editSimple` no tiene ruta declarada en los archivos del ámbito.
- `StorageController::usuarioFoto` lee de `storage/app/public/usuarios/`, mientras que los uploads se guardan en `public/images/fotos_usuarios/`: posible inconsistencia (no se sirven por esa ruta).
- Las descripciones de las funciones JS inline se basan en su nombre, firma y contexto de uso; no se desglosó línea a línea cada handler de los blades más extensos (form_usuario 787 líneas, select 586, basededatos 606).
- Los permisos "requeridos" en las tablas de rutas son la verificación **efectiva** (en controller/vista), ya que ninguna ruta declara middleware de permiso explícito.
