# 20-03 — Mapa AuthZ: rutas de escritura fuera de Planeación (SEC-05)

**Fecha:** 2026-09-25 · **Rama:** `claude/20-02-03-errores-authz` · **Test guardián:** `tests/Feature/Seguridad/EscrituraFueraDePlaneacionTest.php`

Inventario sacado con `php artisan route:list --json`: escrituras (POST/PUT/PATCH/DELETE) de controllers `App\` sin `module.permission`, sin contar las URIs del snapshot de Programa Tejido (Planeación la cerró PT 01.1) ni los redirects 301. Salieron **87 rutas**:

| Resultado | Rutas |
|---|---|
| `module.permission:<acción>,<idrol>,auditar` | **63** |
| Excepción del owner, sin gate: alta y finalizar de paros | 2 |
| Pendiente de idrol: no hay módulo identificable en el código | 4 |
| Pendiente de idrol: solo se conoce el nombre del módulo | 18 |

Además, fuera de las 87 y ya justificadas en el test: `login`, `logout` y los 5 POST de `telemetria/*`.

## Reglas que se siguieron

- **Siempre por idrol, nunca por nombre.** Lo exige `tests/Feature/RutasDestructivasPermisoTest::test_todo_gate_se_referencia_por_idrol`: hay 5 nombres repetidos en `SYSRoles` y `userPermissions()` resuelve un nombre repetido a una fila arbitraria (el incidente de "Utilería", 67 contra 188).
- **Acción por verbo y según lo que ya usa la vista:**
  - alta → `crear`
  - editar, toggle, terminar, estado, prioridades → `modificar`
  - "Editar (Supervisor)" (`x-buttons.button-report`) → `registrar`
  - exportes y Telegram que no escriben en BD → `acceso`
- **Si el controller ya valida con `userCan`**, el auditar repite la misma acción: no cambia nada y aporta el dato para `/admin/accesos`.

## Fuente de cada idrol

| idrol | Módulo (SYSRoles.modulo) | Fuente |
|---|---|---|
| 21 | Inv Telas | `tejedores.php` `eliminar,21`; `telar-requerimiento.blade.php` usa `userCan('crear', 21)` |
| 27 | Producción Reenconado Cabezuela | `tejido.php` `eliminar,27`; la vista usa `button-create`/`button-edit` del módulo |
| 35 | BPM (Buenas Practicas Manufactura) Urd | `urdido.php` `eliminar,35` / `registrar,35` |
| 41 | BPM (Buenas Practicas Manufactura) Eng | `engomado.php` `eliminar,41` / `registrar,41` |
| 43 | Producción Engomado | `engomado.php` `eliminar,43` / `modificar,43`; `ProduccionTrait::ensureUserCanEdit()` con `userCan('modificar','Producción Engomado')` |
| 45 | Programa Atadores | `atadores.php` `crear,45` / `modificar,45` (devoluciones en la misma pantalla) |
| 47 | BPM Tejedores | `tejedores.php` `eliminar,47` / `registrar,47` |
| 48 | Desarrolladores | `TelDesarrolladoresController::MODULO` "(idrol 48)", el constructor exige `acceso` |
| 105 | Cortes de Eficiencia | `tejido.php` `modificar,105` (finalizar) |
| 154 | Producción Urdido | `urdido.php` `eliminar,154` / `modificar,154`; `ProduccionTrait` con `userCan('modificar','Producción Urdido')` |
| 168 | Captura de Formula | `engomado.php` `eliminar,168` |
| 177 | Marcas Finales | `tejido.php` `modificar,177` (finalizar/reabrir) |
| 181 | Mensajes | `configuracion.php` `acceso,181` / `modificar,181` |
| 189 | Desarrolladores Muestras | `TelDesarrolladoresMuestrasController` "(idrol 189)" |
| 193 | Ordenes de Trabajo | `mecanicos.php` `eliminar,193` / `modificar,193` / `registrar,193`; `OrdenesTrabajoMecaController::MODULO_PERMISO` |

## Casos a propósito

- **BPM Urdido, Engomado y Tejedores (crear/editar, toggle, terminar):** hoy están abiertos porque 12 personas capturan con `acceso` y sin `crear` (comentario en `urdido.php`). Se audita `crear`/`modificar` para medir antes de decidir en la 19-xx.
- **Desarrolladores (48/189):** 9 usuarios capturan solo con `acceso`, que es lo que se audita, igual que el constructor del controller.
- **`PUT mecanicos/ordenes-trabajo/{folio}/lineas/{linea}`:** los tejedores (por área) califican líneas sin `modificar`, así que se audita `acceso,193`. Con `modificar` el enforce los rompería.
- **`inventario-telares/actualizar-fecha`:** se audita `crear,21`, igual que el checkbox de la vista. Con `modificar` bloquearía a quien solo tiene `crear`.
- **`modulo-cortes-de-eficiencia` (store) y `guardar-hora`:** son upsert y se usan también al editar. Se audita `crear` y `modificar` respectivamente; conviene revisarlo con los datos de `/admin/accesos` antes del enforce.

## Ruta por ruta

| Ruta | Controller | Gate |
|---|---|---|
| POST `/Crudo/auditorias` | `Crudo\CrudoAuditController@store` | — |
| POST `/Crudo/auditorias/paro` | `Crudo\CrudoAuditController@storeWithStop` | — |
| POST `/api/mantenimiento/paros` | `Mantenimiento\MantenimientoParosController@store` | — |
| PUT `/api/mantenimiento/paros/{id}/finalizar` | `Mantenimiento\MantenimientoParosController@finalizar` | — |
| POST `/atadores/reportes-atadores/oee/despachar` | `Atadores\Reportes\ReportesAtadoresController@despacharOeeAtadores` | — |
| POST `/atadores/save` | `Atadores\ProgramaAtadores\AtadoresController@save` | `modificar,45,auditar` |
| POST `/desarrolladores` | `Tejedores\Desarrolladores\TelDesarrolladoresController@store` | `acceso,48,auditar` |
| POST `/desarrolladores-muestras` | `Tejedores\Desarrolladores\TelDesarrolladoresMuestrasController@store` | `acceso,189,auditar` |
| POST `/eng-bpm` | `Engomado\BPMEngomado\EngBpmController@store` | `crear,41,auditar` |
| PATCH `/eng-bpm-line/{folio}/terminar` | `Engomado\BPMEngomado\EngBpmLineController@terminar` | `modificar,41,auditar` |
| POST `/eng-bpm-line/{folio}/toggle` | `Engomado\BPMEngomado\EngBpmLineController@toggleActividad` | `modificar,41,auditar` |
| PUT|PATCH `/eng-bpm/{id}` | `Engomado\BPMEngomado\EngBpmController@update` | `modificar,41,auditar` |
| POST `/eng-formulacion` | `Engomado\CapturaFormulas\EngProduccionFormulacionController@store` | `crear,168,auditar` |
| PUT|PATCH `/eng-formulacion/{folio}` | `Engomado\CapturaFormulas\EngProduccionFormulacionController@update` | `modificar,168,auditar` |
| POST `/engomado/modulo-produccion-engomado/actualizar-campo-orden` | `Engomado\Produccion\ModuloProduccionEngomadoController@actualizarCampoOrden` | `modificar,43,auditar` |
| POST `/engomado/modulo-produccion-engomado/actualizar-campos-produccion` | `Engomado\Produccion\ModuloProduccionEngomadoController@actualizarCamposProduccion` | `modificar,43,auditar` |
| POST `/engomado/modulo-produccion-engomado/actualizar-fecha` | `Engomado\Produccion\ModuloProduccionEngomadoController@actualizarFecha` | `modificar,43,auditar` |
| POST `/engomado/modulo-produccion-engomado/actualizar-horas` | `Engomado\Produccion\ModuloProduccionEngomadoController@actualizarHoras` | `modificar,43,auditar` |
| POST `/engomado/modulo-produccion-engomado/actualizar-julio-tara` | `Engomado\Produccion\ModuloProduccionEngomadoController@actualizarJulioTara` | `modificar,43,auditar` |
| POST `/engomado/modulo-produccion-engomado/actualizar-kg-bruto` | `Engomado\Produccion\ModuloProduccionEngomadoController@actualizarKgBruto` | `modificar,43,auditar` |
| POST `/engomado/modulo-produccion-engomado/actualizar-turno-oficial` | `Engomado\Produccion\ModuloProduccionEngomadoController@actualizarTurnoOficial` | `modificar,43,auditar` |
| POST `/engomado/modulo-produccion-engomado/calificar-julios-eng/calificar` | `Engomado\Produccion\CalificarJuliosController@calificarEng` | `modificar,43,auditar` |
| POST `/engomado/modulo-produccion-engomado/calificar-julios/calificar` | `Engomado\Produccion\CalificarJuliosController@calificar` | `modificar,43,auditar` |
| POST `/engomado/modulo-produccion-engomado/guardar-oficial` | `Engomado\Produccion\ModuloProduccionEngomadoController@guardarOficial` | `modificar,43,auditar` |
| POST `/engomado/modulo-produccion-engomado/marcar-listo` | `Engomado\Produccion\ModuloProduccionEngomadoController@marcarListo` | `modificar,43,auditar` |
| POST `/engomado/programar-engomado/actualizar-prioridades` | `Engomado\ProgramaEngomado\ProgramarEngomadoController@actualizarPrioridades` | — |
| POST `/engomado/programar-engomado/actualizar-status` | `Engomado\ProgramaEngomado\ProgramarEngomadoController@actualizarStatus` | — |
| POST `/engomado/programar-engomado/guardar-observaciones` | `Engomado\ProgramaEngomado\ProgramarEngomadoController@guardarObservaciones` | — |
| POST `/engomado/programar-engomado/intercambiar-prioridad` | `Engomado\ProgramaEngomado\ProgramarEngomadoController@intercambiarPrioridad` | — |
| POST `/inventario-telares/actualizar-fecha` | `Tejedores\InventarioTelaresController@updateFecha` | `crear,21,auditar` |
| POST `/inventario-telares/guardar` | `Tejedores\InventarioTelaresController@store` | `crear,21,auditar` |
| POST `/mecanicos/ordenes-trabajo` | `mecanicos\OrdenesTrabajoMecaController@store` | `crear,193,auditar` |
| PUT `/mecanicos/ordenes-trabajo/{folio}` | `mecanicos\OrdenesTrabajoMecaController@update` | `modificar,193,auditar` |
| POST `/mecanicos/ordenes-trabajo/{folio}/lineas` | `mecanicos\OrdenesTrabajoMecaController@storeLinea` | `crear,193,auditar` |
| PUT `/mecanicos/ordenes-trabajo/{folio}/lineas/{linea}` | `mecanicos\OrdenesTrabajoMecaController@updateLinea` | `acceso,193,auditar` |
| POST `/mecanicos/reportes/estado-maquina/excel` | `mecanicos\MecReportesController@exportarExcelEstadoMaquina` | — |
| POST `/mecanicos/reportes/estado-maquina/pdf` | `mecanicos\MecReportesController@exportarPdfEstadoMaquina` | — |
| POST `/mecanicos/reportes/estado-maquina/telegram-imagen` | `mecanicos\MecReportesController@telegramImagenEstadoMaquina` | — |
| POST `/mecanicos/reportes/ot-diarias/excel` | `mecanicos\MecReportesController@exportarExcelOtDiarias` | — |
| POST `/mecanicos/reportes/ot-diarias/pdf` | `mecanicos\MecReportesController@exportarPdfOtDiarias` | — |
| POST `/mecanicos/reportes/ot-diarias/telegram-imagen` | `mecanicos\MecReportesController@telegramImagenOtDiarias` | — |
| POST `/modulo-cortes-de-eficiencia` | `Tejido\CortesEficiencia\CortesEficienciaController@store` | `crear,105,auditar` |
| POST `/modulo-cortes-de-eficiencia/guardar-hora` | `Tejido\CortesEficiencia\CortesEficienciaController@guardarHora` | `modificar,105,auditar` |
| POST `/modulo-cortes-de-eficiencia/visualizar/descargar-pdf` | `Tejido\CortesEficiencia\CortesEficienciaController@descargarVisualizacionPDF` | `acceso,105,auditar` |
| POST `/modulo-cortes-de-eficiencia/visualizar/exportar-excel` | `Tejido\CortesEficiencia\CortesEficienciaController@exportarVisualizacionExcel` | `acceso,105,auditar` |
| POST `/modulo-cortes-de-eficiencia/visualizar/notificar-telegram` | `Tejido\CortesEficiencia\CortesEficienciaController@notificarTelegram` | `acceso,105,auditar` |
| POST `/modulo-cortes-de-eficiencia/visualizar/notificar-telegram-imagen` | `Tejido\CortesEficiencia\CortesEficienciaController@notificarTelegramImagen` | `acceso,105,auditar` |
| PUT `/modulo-cortes-de-eficiencia/{id}` | `Tejido\CortesEficiencia\CortesEficienciaController@update` | `modificar,105,auditar` |
| PUT `/modulo-cortes-de-eficiencia/{id}/actualizar-registro` | `Tejido\CortesEficiencia\CortesEficienciaController@actualizarRegistro` | `registrar,105,auditar` |
| POST `/modulo-marcas/generar-folio` | `Tejido\MarcasFinales\MarcasController@generarFolio` | `crear,177,auditar` |
| POST `/modulo-marcas/reporte/descargar-pdf` | `Tejido\MarcasFinales\MarcasController@descargarPDF` | `acceso,177,auditar` |
| POST `/modulo-marcas/reporte/exportar-excel` | `Tejido\MarcasFinales\MarcasController@exportarExcel` | `acceso,177,auditar` |
| POST `/modulo-marcas/reporte/notificar-telegram` | `Tejido\MarcasFinales\MarcasController@notificarTelegram` | `acceso,177,auditar` |
| POST `/modulo-marcas/store` | `Tejido\MarcasFinales\MarcasController@store` | `crear,177,auditar` |
| PUT `/modulo-marcas/{folio}` | `Tejido\MarcasFinales\MarcasController@update` | `modificar,177,auditar` |
| PUT `/modulo-marcas/{folio}/actualizar-registro` | `Tejido\MarcasFinales\MarcasController@actualizarRegistro` | `registrar,177,auditar` |
| POST `/produccion/reenconado-cabezuela` | `Tejido\ProduccionReenconado\ProduccionReenconadoCabezuelaController@store` | `crear,27,auditar` |
| POST `/tejedores/atadodejulio/notificar` | `Tejedores\NotificarMontadoJulios\NotificarMontadoJulioController@notificar` | — |
| POST `/tejedores/cortadoderollo/insertar` | `Tejedores\NotificarMontadoRollo\NotificarMontRollosController@insertarMarbetes` | — |
| POST `/tejedores/cortadoderollo/notificar` | `Tejedores\NotificarMontadoRollo\NotificarMontRollosController@notificar` | — |
| POST `/tejido/produccion-reenconado` | `Tejido\ProduccionReenconado\ProduccionReenconadoCabezuelaController@store` | `crear,27,auditar` |
| POST `/tejido/produccion-reenconado/generar-folio` | `Tejido\ProduccionReenconado\ProduccionReenconadoCabezuelaController@generarFolio` | `crear,27,auditar` |
| PUT `/tejido/produccion-reenconado/{folio}` | `Tejido\ProduccionReenconado\ProduccionReenconadoCabezuelaController@update` | `modificar,27,auditar` |
| POST `/tel-bpm` | `Tejedores\BPMTejedores\TelBpmController@store` | `crear,47,auditar` |
| PUT|PATCH `/tel-bpm/{folio}` | `Tejedores\BPMTejedores\TelBpmController@update` | `modificar,47,auditar` |
| POST `/tel-bpm/{folio}/lineas/comentarios` | `Tejedores\BPMTejedores\TelBpmLineController@updateComentarios` | `modificar,47,auditar` |
| POST `/tel-bpm/{folio}/lineas/toggle` | `Tejedores\BPMTejedores\TelBpmLineController@toggle` | `modificar,47,auditar` |
| PATCH `/tel-bpm/{folio}/terminar` | `Tejedores\BPMTejedores\TelBpmLineController@finish` | `modificar,47,auditar` |
| POST `/telegram/send` | `Telegram\TelegramController@sendMessage` | `acceso,181,auditar` |
| POST `/urd-bpm` | `Urdido\BPMUrdido\UrdBpmController@store` | `crear,35,auditar` |
| PATCH `/urd-bpm-line/{folio}/terminar` | `Urdido\BPMUrdido\UrdBpmLineController@terminar` | `modificar,35,auditar` |
| POST `/urd-bpm-line/{folio}/toggle` | `Urdido\BPMUrdido\UrdBpmLineController@toggleActividad` | `modificar,35,auditar` |
| PUT|PATCH `/urd-bpm/{id}` | `Urdido\BPMUrdido\UrdBpmController@update` | `modificar,35,auditar` |
| POST `/urdido/modulo-produccion-urdido/actualizar-campos-produccion` | `Urdido\Configuracion\ModuloProduccionUrdidoController@actualizarCamposProduccion` | `modificar,154,auditar` |
| POST `/urdido/modulo-produccion-urdido/actualizar-fecha` | `Urdido\Configuracion\ModuloProduccionUrdidoController@actualizarFecha` | `modificar,154,auditar` |
| POST `/urdido/modulo-produccion-urdido/actualizar-horas` | `Urdido\Configuracion\ModuloProduccionUrdidoController@actualizarHoras` | `modificar,154,auditar` |
| POST `/urdido/modulo-produccion-urdido/actualizar-julio-tara` | `Urdido\Configuracion\ModuloProduccionUrdidoController@actualizarJulioTara` | `modificar,154,auditar` |
| POST `/urdido/modulo-produccion-urdido/actualizar-kg-bruto` | `Urdido\Configuracion\ModuloProduccionUrdidoController@actualizarKgBruto` | `modificar,154,auditar` |
| POST `/urdido/modulo-produccion-urdido/actualizar-turno-oficial` | `Urdido\Configuracion\ModuloProduccionUrdidoController@actualizarTurnoOficial` | `modificar,154,auditar` |
| POST `/urdido/modulo-produccion-urdido/guardar-oficial` | `Urdido\Configuracion\ModuloProduccionUrdidoController@guardarOficial` | `modificar,154,auditar` |
| POST `/urdido/modulo-produccion-urdido/marcar-listo` | `Urdido\Configuracion\ModuloProduccionUrdidoController@marcarListo` | `modificar,154,auditar` |
| POST `/urdido/programar-urdido/actualizar-calidad` | `Urdido\ProgramaUrdido\ProgramarUrdidoController@actualizarCalidad` | — |
| POST `/urdido/programar-urdido/actualizar-prioridades` | `Urdido\ProgramaUrdido\ProgramarUrdidoController@actualizarPrioridades` | — |
| POST `/urdido/programar-urdido/actualizar-status` | `Urdido\ProgramaUrdido\ProgramarUrdidoController@actualizarStatus` | — |
| POST `/urdido/programar-urdido/guardar-observaciones` | `Urdido\ProgramaUrdido\ProgramarUrdidoController@guardarObservaciones` | — |
| POST `/urdido/programar-urdido/intercambiar-prioridad` | `Urdido\ProgramaUrdido\ProgramarUrdidoController@intercambiarPrioridad` | — |
| POST `/urdido/programar-urdido/marcar-incorrecto` | `Urdido\ProgramaUrdido\ProgramarUrdidoController@marcarIncorrecto` | — |

`—` = sin gate: excepción del owner (paros) o pendiente (sección siguiente).

## Pendientes de idrol (22 rutas)

No hay base de datos en este entorno, así que no se inventa ningún idrol. Consulta para correr en Laragon (compatible con SQL Server 2008 R2):

```sql
SELECT idrol, modulo, Ruta, Dependencia
FROM dbo.SYSRoles
WHERE modulo IN ('Programa Urdido', 'Programa Engomado', 'Andon', 'OT Diarias',
                 'Reporte Estado de Maquina', 'Atado de Julio', 'Cortado de Rollo', 'Reportes Atadores')
   OR Ruta LIKE '%programar-urdido%' OR Ruta LIKE '%programar-engomado%'
   OR Ruta LIKE '%Crudo%' OR Ruta LIKE '%reportes/ot-diarias%' OR Ruta LIKE '%estado-maquina%'
   OR Ruta LIKE '%atadodejulio%' OR Ruta LIKE '%notificarmontadodejulio%'
   OR Ruta LIKE '%cortadoderollo%' OR Ruta LIKE '%reportes-atadores%'
ORDER BY modulo, idrol;
```

Si un nombre sale repetido, el idrol correcto es el que tiene la `Ruta` de la pantalla.

| Rutas | Módulo | Acción a auditar | Por qué falta |
|---|---|---|---|
| `urdido/programar-urdido/{intercambiar-prioridad, actualizar-prioridades, guardar-observaciones, marcar-incorrecto, actualizar-status}` | Programa Urdido | modificar | Solo el nombre (`ProgramarUrdidoController` usa `userCan('modificar','Programa Urdido')`) |
| `urdido/programar-urdido/actualizar-calidad` | Programa Urdido | registrar | Igual |
| `engomado/programar-engomado/{intercambiar-prioridad, guardar-observaciones, actualizar-prioridades, actualizar-status}` | Programa Engomado | modificar | Solo el nombre (`ProgramaModulo::permissionModule()`) |
| `Crudo/auditorias`, `Crudo/auditorias/paro` | Andon (`CRUDO_PERMISSION_MODULE`) | registrar | Solo el nombre (`CrudoAccess`); el controller ya lo exige |
| `mecanicos/reportes/ot-diarias/{excel, pdf, telegram-imagen}` | OT Diarias (`moduleNameForRoute`) | acceso | El nombre se resuelve por ruta en runtime; el controller ya lo exige |
| `mecanicos/reportes/estado-maquina/{excel, pdf, telegram-imagen}` | Reporte Estado de Maquina | acceso | Solo el nombre; el controller ya lo exige |
| `tejedores/atadodejulio/notificar` | ¿Atado de Julio? | acceso | No hay ningún `userCan` en el controller ni en la vista |
| `tejedores/cortadoderollo/{notificar, insertar}` | ¿Cortado de Rollo? | acceso / crear | Igual |
| `atadores/reportes-atadores/oee/despachar` | ¿Reportes Atadores? | registrar (reescribe el Excel OEE compartido) | Igual |

Cuando llegue el idrol: agregar `->middleware('module.permission:<acción>,<idrol>,auditar')` y quitar la ruta de `SIN_PERMISO_DE_MODULO` en el test guardián.

## Huecos que el inventario hizo visibles (van para las 19-xx; no se tocaron)

| Ruta | Hueco |
|---|---|
| `POST atadores/save` con `action=supervisor` | Autoriza un atado sin revisar permiso; debería exigir `registrar,45` |
| `POST engomado/modulo-produccion-engomado/actualizar-campo-orden` | Es la única de Producción Engomado que no valida nada (escribe merma con y sin goma) |
| `POST engomado/programar-engomado/actualizar-prioridades` | "Habilitado para todos" en el controller |
| `POST telegram/send` | Relay genérico sin llamadores (no hay hits en `resources/`, `public/js`, `app/` ni `tests/`); propuesta: borrarlo |
| `PUT modulo-cortes-de-eficiencia/{id}` | Stub que no escribe nada y no tiene llamadores |
| `POST produccion/reenconado-cabezuela` | Duplicado legacy de `tejido/produccion-reenconado` sin llamadores |
