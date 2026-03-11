# Programa Tejido (ReqProgramaTejido) – Botones y Permisos

Lista de botones y acciones disponibles en el módulo **Programa Tejido** para definir permisos granulares.

---

## Permisos del sistema (SYSRoles / SYSUsuariosRoles)

| Permiso   | Descripción                          | Uso típico                         |
|----------|---------------------------------------|------------------------------------|
| `acceso` | Acceso al módulo (ver la pantalla)    | Obligatorio para todo uso          |
| `crear`  | Crear nuevos registros                | Duplicar, vincular, repaso, etc.   |
| `modificar` | Editar registros existentes        | Editar fila, drag-drop, balancear  |
| `eliminar` | Eliminar registros                  | Eliminar, eliminar en proceso      |
| `registrar` | Reportes, descargas, liberar órdenes | Descargar, liberar, reimprimir     |

---

## Navbar (barra superior)

| # | Botón / Elemento | ID / Referencia | Acción principal | Permiso recomendado |
|---|------------------|-----------------|------------------|----------------------|
| 1 | Ver líneas de detalle | `layoutBtnVerLineas`, `btn-ver-lineas` | Abre modal con líneas del registro | `acceso` |
| 2 | Activar Drag and Drop | `btnDragDrop` | Reordenar filas arrastrando | `modificar` |
| 3 | Descargar programa | — | Descarga Excel del programa | `registrar` |
| 4 | Liberar órdenes | — | Libera órdenes (abre modal) | `registrar` |
| 5 | Controles de columnas | `btnResetColumns` | Mostrar/ocultar/fijar columnas | `acceso` |
| 6 | Balancear | `btnBalancear` | Abre modal de balanceo | `modificar` |
| 7 | Vincular registros existentes | `btnVincularExistentes` | Vincular telares existentes | `crear` / `modificar` |
| 8 | Catálogos | Link | Ir a catálogos de planeación | `acceso` |
| 9 | Recalcular fechas | `btn-recalcular-fechas` | Recalcula fechas de producción | `modificar` |
| 10 | Actualizar (dropdown) | `btnActualizarDropdown` | Menú de acciones adicionales | — |
| 11 | Actualizar Calendarios | `menuActCalendarios` | Abre modal para actualizar calendarios | `modificar` |
| 12 | Filtros | `btnFilters` | Abre panel de filtros | `acceso` |

**Componentes de navbar con permiso explícito:**
- `button-create` → `crear` (Drag and Drop usa este)
- `button-edit` → `modificar` (Vincular existentes, Actualizar Calendarios)
- `button-report` → `registrar` (Descargar programa, Liberar órdenes)

---

## Panel de selección (al elegir una fila)

| # | Botón | ID | Acción | Permiso recomendado |
|---|-------|----|--------|----------------------|
| 13 | Editar | `btn-editar-programa`, `layoutBtnEditar` | Edición inline de la fila | `modificar` |
| 14 | Eliminar | `btn-eliminar-programa`, `layoutBtnEliminar` | Elimina registro (no en proceso) | `eliminar` |
| 15 | Ver líneas | `btn-ver-lineas` | Muestra líneas de detalle | `acceso` |

---

## Menú contextual (clic derecho sobre fila)

| # | Opción | ID | Acción | Permiso recomendado |
|---|--------|----|--------|----------------------|
| 16 | Crear | `contextMenuCrear` | Abre modal Duplicar/Vincular | `crear` |
| 17 | Crear Repaso | `contextMenuRepaso` | Abre modal Repaso | `crear` |
| 18 | Editar fila | `contextMenuEditar` | Activa edición inline | `modificar` |
| 19 | Eliminar | `contextMenuEliminar` | Elimina registro (Programado) | `eliminar` |
| 20 | Eliminar en proceso | `contextMenuEliminarEnProceso` | Elimina registro (En Proceso) | `eliminar` |
| 21 | Desvincular | `contextMenuDesvincular` | Quita vínculo OrdCompartida | `modificar` |
| 22 | Codificación | `contextMenuCodificacion` | Abre catálogo Codificación | `acceso` |
| 23 | Codificación Modelos | `contextMenuModelos` | Abre catálogo Codificación Modelos | `acceso` |

---

## Menú contextual de encabezados (clic derecho en columna)

| # | Opción | ID | Acción | Permiso recomendado |
|---|--------|----|--------|----------------------|
| 24 | Filtrar | `contextMenuHeaderFiltrar` | Filtrar por valor de columna | `acceso` |
| 25 | Fijar | `contextMenuHeaderFijar` | Fijar columna al scroll | `acceso` |
| 26 | Ocultar | `contextMenuHeaderOcultar` | Ocultar columna | `acceso` |

---

## Modales y subpantallas

| # | Modal / pantalla | Ruta / Acción | Permiso recomendado |
|---|------------------|---------------|----------------------|
| 27 | Duplicar telar | `duplicarTelar()` → POST `/duplicar-telar` | `crear` |
| 28 | Dividir telar | `dividirTelar()` → POST `/dividir-telar` | `crear` |
| 29 | Dividir saldo | `dividirSaldo()` → POST `/dividir-saldo` | `crear` |
| 30 | Vincular telar | `vincularTelar()` → POST `/vincular-telar` | `crear` |
| 31 | Vincular existentes | `vincularRegistrosExistentes()` | `crear` |
| 32 | Desvincular | `desvincularRegistro()` → POST `/{id}/desvincular` | `modificar` |
| 33 | Cambiar telar | `cambiarTelar()` | `modificar` |
| 34 | Balancear | Pantalla `/balancear` | `modificar` |
| 35 | Liberar órdenes | Pantalla `/liberar-ordenes` | `registrar` |
| 36 | Reimprimir órdenes | GET `/reimprimir-ordenes/{id}` | `registrar` |
| 37 | Actualizar Calendarios | Modal act-calendarios | `modificar` |
| 38 | Crear Repaso | Modal repaso → POST `/crear-repaso` | `crear` |
| 39 | Edición inline (guardar) | PUT `/{id}` | `modificar` |
| 40 | Eliminar (Programado) | DELETE `/{id}` | `eliminar` |
| 41 | Eliminar en proceso | DELETE `/{id}/en-proceso` | `eliminar` |
| 42 | Reprogramar (checkbox) | POST `/{id}/reprogramar` | `modificar` |
| 43 | Recalcular fechas | POST `/recalcular-fechas` | `modificar` |
| 44 | Descargar programa | POST `/descargar-programa` | `registrar` |
| 45 | Guardar columnas visibles | POST `/columnas` | `acceso` |

---

## Resumen: mapeo permiso → botones/acciones

| Permiso   | Botones / acciones asociados |
|-----------|------------------------------|
| **acceso** | Ver pantalla, Ver líneas, Filtros, Columnas (mostrar/ocultar/fijar), Codificación, Catálogos |
| **crear** | Drag and Drop, Duplicar, Dividir, Vincular, Repaso |
| **modificar** | Editar fila, Balancear, Desvincular, Cambiar telar, Actualizar Calendarios, Recalcular fechas, Reprogramar |
| **eliminar** | Eliminar, Eliminar en proceso |
| **registrar** | Descargar programa, Liberar órdenes, Reimprimir órdenes |

---

## Módulo en SYSRoles

El módulo se identifica como **`Programa Tejido`** (o `ProgramaTejido` según la convención del sistema). Verificar en `SYSRoles` la columna `modulo` para el idrol correspondiente.

---

## Notas para implementar permisos

1. Los componentes `x-navbar.button-create`, `button-edit`, `button-report` ya validan permisos con `userCan('crear'|'modificar'|'registrar', 'Programa Tejido')`.
2. El menú contextual y el panel de selección **no** validan permisos actualmente; habría que envolver cada opción con `@if(userCan(...))`.
3. Los controladores (rutas) **no** validan permisos explícitamente; conviene añadir middleware o `userCan()` en cada acción para garantizar seguridad en backend.
4. `button-delete` usa `eliminar`; en la navbar de programa-tejido no hay botón delete explícito, pero los botones del panel de selección y del context menu sí ejecutan eliminar.
