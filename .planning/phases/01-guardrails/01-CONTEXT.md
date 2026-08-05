# Reestructuración quirúrgica de Planeación / ReqProgramaTejido

## Estado del documento

- Alcance: planeación vinculada directa o indirectamente con `ReqProgramaTejido`.
- Repositorio: únicamente `/mnt/c/xampp/htdocs/Towell`.
- Tipo de trabajo actual: análisis y planificación; no implementa cambios funcionales.
- Estrategia: migración incremental con convivencia legacy/v2 y rollback por feature flag.
- Fecha de la línea base: 2026-07-22.

## Objetivo

Reducir el tamaño, acoplamiento y dificultad de mantenimiento de Programa Tejido; mejorar legibilidad y UX; y crear fronteras claras entre presentación, lectura, mutaciones y reglas de dominio sin alterar las operaciones que Planeación y otros módulos consumen hoy.

La reestructuración no es un rediseño aislado del Blade. `ReqProgramaTejido` participa en secuencias de telar, fechas, líneas diarias, CatCodificados, órdenes compartidas, liberación, finalización, balanceo, muestras, desarrolladores, trazabilidad, mantenimiento, reportes e integraciones. Cada fase debe conservar esos contratos de manera demostrable.

## Decisiones bloqueadas

1. No habrá reemplazo masivo ni reescritura completa.
2. La primera UI v2 será Blade delgado + módulos ES cargados por Vite; React/TanStack queda fuera hasta demostrar que aporta una ventaja que no se obtiene con esta separación.
3. Las rutas, nombres, payloads y mutaciones legacy se conservan durante las fases de lectura y UI.
4. Programa y Muestras serán superficies explícitas con capacidades declaradas; no se asumirá que sus tablas son equivalentes.
5. La tabla, el DOM y variables globales no seguirán siendo fuentes paralelas de estado en la UI v2.
6. La lectura se separará antes que las mutaciones.
7. El observer se mantiene como adaptador hasta que existan pruebas de caracterización y servicios puros equivalentes.
8. Liberar, Finalizar, Mover, Balancear, imports, export UNC, Redbooth y sincronización con CatCodificados no se mezclarán en una sola fase ni PR.
9. No se ejecutarán migraciones pendientes hasta reconciliar historial de migraciones y esquema físico live.
10. No se editará manualmente `public/build`.

## Invariantes que no se pueden romper

- Cero posiciones duplicadas por `(SalonTejidoId, NoTelarId, Posicion)`.
- Como máximo un registro `EnProceso` por telar y conservación de la semántica de `Ultimo`.
- Ninguna línea diaria huérfana; generación de líneas consistente con cabecera, calendario y fechas.
- Separación total entre las filas/líneas de Programa y Muestras.
- Conservación de líder, miembros y saldos de `OrdCompartida`.
- Fórmulas actuales y sus reglas de redondeo/truncamiento no cambian sin una decisión de negocio explícita.
- `FechaFinaliza` solo se establece al finalizar; mover órdenes no la modifica.
- `Prioridad` permanece como texto.
- `NoProduccion` y `CatCodificados.OrdenTejido` permanecen sincronizados en los flujos donde hoy aplica.
- Los consumidores downstream conservan nombres, tipos y semántica de los campos.
- Una falla de derivados deja de ocultarse como éxito aparente o tabla vacía: debe ser observable y recuperable.

## Superficies incluidas

### Núcleo

- Modelo `ReqProgramaTejido`, modelo de líneas y observer.
- Programa Tejido y Muestras: rutas, controladores, vistas, scripts, CSS y preferencias de columnas.
- Helpers y servicios de secuencia, fechas, orden compartida, actualización, duplicación, división, vínculo y balanceo.

### Escrituras vinculadas

- Edición inline, reprogramación, calendario, drag/drop y cambio de telar.
- Utilería: mover y finalizar órdenes.
- Liberar órdenes y sincronizaciones asociadas.
- Codificación, desarrolladores, revivir desde CatCodificados.
- Imports, exports y recalculado por comando.
- Redbooth y catálogos AX/TI cuando escriben o recalculan programa.

### Consumidores que deben protegerse

- Alineación, Tejedores/Desarrolladores, Mantenimiento, Inventario, Saldos, Trazabilidad, semanas/reportes y cualquier consulta que dependa de `ReqProgramaTejido` o de sus líneas.

## Fuera de alcance inicial

- Normalizar de una vez la tabla de más de 140 columnas.
- Rediseñar todos los módulos de Planeación que solo sean consumidores de lectura.
- Convertir toda Planeación a React.
- Unificar por fuerza Programa y Muestras.
- Cambiar fórmulas o reglas de negocio por motivos estéticos o de limpieza.
- Borrar métodos, rutas o assets aparentemente muertos sin caracterización y telemetría.

## Decisiones que requieren checkpoint del dueño

1. **Muestras:** alinear físicamente las seis columnas faltantes y once longitudes distintas, o declarar capacidades exclusivas y bloquear las operaciones incompatibles. La fase 01 recopila evidencia y propone la decisión; no la asume.
2. **Presets de columnas:** validar los conjuntos iniciales de Operación, Planeación, Materiales y Comercial. Las 460 preferencias existentes se preservan.
3. **Canary:** definir usuarios/roles autorizados para `?ui=v2` y duración mínima del ciclo operativo estable.
4. **Descarga UNC:** confirmar si Muestras debe tener archivo independiente, nombre parametrizado o no soportar la operación.
5. **Cobertura frontend:** iniciar con `node:test` para módulos puros; instalar Vitest/jsdom solo si la interacción no se puede caracterizar adecuadamente sin esa dependencia.

## Skills y herramientas elegidas

- `gsd-plan-phase`: estructura de fases, waves, dependencias, must-haves y gates.
- `laravel-specialist`: Requests, DTO/Resources, servicios por caso de uso, transacciones, Eloquent/SQL Server y pruebas Feature/Unit.
- `gsd-add-tests`: ampliar caracterización al cerrar cada familia funcional.
- `browser-use:browser`: UAT de localhost para teclado, selección, modales, filtros y ambas superficies.
- `gsd-validate-phase` + `gsd-verify-work`: cierre técnico y validación conversacional antes de retirar legacy.
- `gsd-execute-phase`: solo cuando se autorice implementar una fase.
- `php-pro`: opcional para DTO/value objects tipados; no se usará como skill principal ni se impondrá PHPStan como gate sin instalarlo deliberadamente.

No aplican Figma, React, `security-best-practices` ni plugins externos en esta etapa. El skill de seguridad disponible no cubre PHP y el objetivo actual no requiere contenido de correo, documentos o gestión externa.

