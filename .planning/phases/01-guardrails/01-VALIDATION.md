# Estrategia de validación y rollback

## Línea base automatizada

Comando ejecutado el 2026-07-22:

```bash
/mnt/c/xampp/php/php.exe artisan test \
  tests/Feature/PlaneacionProgramaMuestrasRouteParityTest.php \
  tests/Feature/ProgramaTejidoRouteContractTest.php \
  tests/Feature/PlaneacionUtileriaRouteContractTest.php \
  tests/Feature/ProgramaTejidoObserverGuardTest.php \
  tests/Feature/ProgramaTejidoFormRequestsTest.php \
  tests/Feature/ProgramaTejidoBalanceoTest.php \
  tests/Feature/ProgramaTejidoOperacionesTest.php \
  tests/Feature/ProgramaTejidoUpdateTest.php
```

Resultado: **24 tests aprobados, 150 assertions, 3.24 s**.

Limitación: gran parte de la cobertura actual comprueba rutas, firmas, substrings o contratos estructurales. No demuestra todavía paridad de datos, aislamiento Programa/Muestras, comportamiento del browser ni integridad transaccional de cada mutación.

## Comandos base por fase

```bash
/mnt/c/xampp/php/php.exe artisan optimize:clear
/mnt/c/xampp/php/php.exe artisan route:list --path=planeacion --json
/mnt/c/xampp/php/php.exe artisan test --filter=PlaneacionProgramaMuestrasRouteParityTest
/mnt/c/xampp/php/php.exe artisan test --filter=ProgramaTejido
npm run build
```

No ejecutar `php artisan migrate` como gate hasta que el plan 01 documente y resuelva la deriva entre migraciones y esquema live.

## Pirámide de pruebas propuesta

### Caracterización de contrato

- Snapshot completo de método, URI, nombre, middleware y action para Programa y Muestras.
- Snapshot de payload/respuesta de lectura y mutaciones actuales.
- Matriz de columnas, tipos, longitudes, defaults, índices y FK por superficie.
- Tests que demuestren capacidades exclusivas y respuestas cuando una operación no es soportada.

### Dominio y transacciones

- Posición, `EnProceso`, `Ultimo`, fechas y regeneración de líneas.
- Líder/membros/saldos de `OrdCompartida`.
- Fórmulas con casos límite y reglas de redondeo/truncamiento actuales.
- CatCodificados y ReqModelosCodificados donde aplique.
- Falla inyectada en derivados: la operación debe hacer rollback o emitir un estado explícito; nunca éxito silencioso.
- Aislamiento: mutar Muestras no modifica Programa/líneas y viceversa.

### Read seam

- Misma fila/semántica legacy vs v2 para una matriz de fixtures.
- Filtros, orden, nulls, columnas calculadas y paginación.
- Proyección de columnas y autorización.
- Query count y tiempo de respuesta bajo un volumen representativo.

### Frontend

- Unit tests de store, filtros, selección y formateadores puros con `node:test`.
- Contract tests para manifest de rutas: ningún módulo arma URLs por reemplazo textual.
- UAT en browser para foco, teclado, modales, error/empty/loading, persistencia de columnas y selección a través de paginación.
- Verificación visual de Programa y Muestras con permisos/capacidades distintas.

## Invariantes SQL posteriores a cada mutación de riesgo

1. Cero duplicados por salón/telar/posición.
2. Cero telares con más de un `EnProceso`.
3. Cero posiciones nulas salvo que el contrato documentado lo permita.
4. Cero líneas huérfanas y separación correcta de tabla de líneas.
5. Rango y suma de líneas consistentes con fechas/cabecera.
6. Grupos compartidos con líder válido y saldos conservados.
7. CatCodificados sincronizado en flujos aplicables.
8. `FechaFinaliza` intacta en movimientos.

Las consultas exactas se versionarán como command/test de salud en el plan 01; no dependerán de una revisión manual informal.

## UAT por superficie

### Lectura/UX

- Abrir Programa y Muestras sin excepciones ni rutas reescritas globalmente.
- Distinguir carga, error real, vacío y filtros sin coincidencias.
- Aplicar/restablecer filtros y columnas; preferencias aisladas por superficie.
- Navegar toolbar, fila, selección y modales con teclado.
- Confirmar que 460 preferencias existentes conservan su significado.

### Operaciones

- Detalles, edición, calendario y reprogramación.
- Drag/drop, cambio de telar y mover órdenes.
- Duplicar, dividir, vincular/desvincular y balancear.
- Liberar/finalizar solo en ambientes o fixtures controlados.
- Descarga UNC, AX/TI y Redbooth con stubs/ambiente autorizado; nunca como parte de un smoke local destructivo.

## Métricas de éxito

- HTML inicial y número de celdas reducidos por paginación/proyección.
- Tiempo p50/p95 de read v2 registrado y comparado con legacy.
- Chunk inicial de Programa Tejido medido; modales pesados fuera del entry inicial.
- Ningún `window.fetch` patch ni nuevas asignaciones `window.*` en features v2.
- Blade principal convertido en shell/composición; ningún nuevo archivo monolítico.
- Errores observables con correlation/contexto de operación.
- Cero diferencias de invariantes durante canary.

## Feature flags

Desactivados por defecto y configurados en `config/planeacion.php`:

```dotenv
PLANEACION_PROGRAMA_UI_V2=false
PLANEACION_PROGRAMA_READ_V2=false
PLANEACION_PROGRAMA_MUTATIONS_V2=false
PLANEACION_PROGRAMA_SEQUENCE_V2=false
PLANEACION_PROGRAMA_GROUPS_V2=false
PLANEACION_PROGRAMA_BALANCE_V2=false
```

El contexto debe permitir flags independientes para Programa y Muestras aunque inicialmente compartan default. `?ui=v2` solo habilita el canary si el flag de disponibilidad y la autorización del usuario lo permiten.

## Rollback

- Legacy Blade y endpoints permanecen disponibles durante todo el canary.
- UI v2 comienza usando mutaciones legacy.
- Read v2 es side-effect free y puede compararse en shadow; volver a legacy no requiere revertir datos.
- Cada familia de mutaciones v2 tiene flag propio y adaptador al comportamiento legacy.
- No dual-write entre Programa/Muestras.
- Esquema: solo cambios aditivos y reversibles, precedidos por backup y script de preflight. El rollback no debe reconstruir datos borrados.
- Una migración v2 se habilita globalmente solo después de probar el apagado de su flag.
- Retiro legacy: cero llamadas registradas, ciclo operativo estable acordado y aprobación explícita del dueño.

## Stop conditions

Detener rollout y volver al flag legacy si aparece cualquiera de estos eventos:

- diferencia en posición, `EnProceso`, fechas, líneas, grupo o CatCodificados;
- escritura cruzada Programa/Muestras;
- payload incompatible con un consumidor existente;
- incremento de errores o pérdida de preferencias;
- operación que aparenta éxito con derivados incompletos;
- dependencia AX/TI/UNC ejecutada fuera del entorno controlado.

