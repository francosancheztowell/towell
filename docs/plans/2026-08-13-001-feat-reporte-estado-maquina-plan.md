---
title: "Reporte Estado de Maquina - Plan"
date: 2026-08-13
type: feat
topic: reporte-estado-maquina
artifact_contract: ce-unified-plan/v1
artifact_readiness: requirements-only
product_contract_source: ce-brainstorm
execution: code
---

# Reporte Estado de Maquina - Plan

## Goal Capsule

Objective: En `/mecanicos/reportes/estado-maquina` el usuario elige mes y semana, consulta una hoja de verificación (actividades × telares) y la descarga en Excel, PDF o imagen, con aviso a Telegram al descargar.

Product authority: Mecánicos / Reportes. El reporte de Órdenes de Trabajo Diarias no es alcance de este plan.

Open blockers: ninguno.

## Product Contract

### Summary

Reporte semanal de calificación de estado de máquina.
La matriz no se arma hasta que el usuario elige mes, semana y pulsa Consultar.
Cada celda es el promedio de todas las capturas de esa semana recortada al mes, redondeado a 1 / 2 / 3.

### Key Decisions

- Consultar bajo demanda, no cargar la semana actual al entrar. (session-settled: user-directed — chosen over semana actual o recarga al filtrar: primero hay que elegir periodo.) Governs R1, R2
- Promedio de todos los folios, incluido Activo. (session-settled: user-directed — chosen over solo Autorizado o Terminado+Autorizado.) Governs R5
- Semana lunes–domingo; el promedio usa solo días del mes elegido. (session-settled: user-directed — chosen over semana completa que cruza meses o bloques 1–7 del mes.) Governs R3, R4
- Prioridad editable 1–3 solo en pantalla, no se guarda. (session-settled: user-directed — chosen over catálogo o persistir por semana.) Governs R8
- Telares agrupados por salón. (session-settled: user-directed — chosen over lista plana u omitir KM.) Governs R7
- Sin captura se muestra 0 sin color. (session-settled: user-directed — chosen over celda vacía.) Governs R6
- Redondeo estándar (≥ 0.5 sube). (session-settled: user-directed — chosen over truncar o ceil.) Governs R5
- Al descargar se envía el mismo archivo a ReporteMecanico; si Telegram falla, la descarga sigue. (session-settled: user-approved — chosen over otro canal o bloquear la descarga.) Governs R9, R10

### Requirements

**Consulta**

- R1. Al entrar, solo se muestran mes, semana y Consultar. No hay matriz.
- R2. Consultar está deshabilitado hasta que haya mes y semana. Tras consultar, se muestra la hoja.
- R3. La semana es calendario lunes–domingo que toca el mes elegido.
- R4. El promedio de cada celda usa solo fechas entre el máximo de (lunes, día 1 del mes) y el mínimo de (domingo, último día del mes).

**Matriz**

- R5. Cada celda es el promedio de `Valor` 1–3 de todos los folios en ese rango (cualquier estatus), redondeado al entero más cercano con 0.5 hacia arriba, y se pinta 1 rojo, 2 naranja, 3 verde.
- R6. Si no hay capturas para esa actividad y telar, la celda muestra 0 sin color de calificación.
- R7. Las columnas de telares se agrupan por `SalonTejidoId` (Jacquard, Smith, KM) con color de encabezado distinto.
- R8. Cada fila de actividad tiene un input Prioridad 1–3 que viaja a Excel/PDF/imagen de esa consulta y no se persiste.

**Descarga y aviso**

- R9. Excel, PDF e imagen solo están disponibles después de Consultar. El PDF va apaisado. La imagen es captura de la matriz consultada.
- R10. Al descargar cualquiera de los tres se envía ese archivo por Telegram a suscriptores de ReporteMecanico. Falla de Telegram o falta de destinatarios no impide la descarga.

### Key Flows

F1. Elegir mes → se listan las semanas que tocan ese mes → elegir semana → Consultar → ver hoja.

F2. Con la hoja visible, ajustar prioridades si hace falta → Excel / PDF / Imagen → el archivo se descarga y se manda por Telegram.

### Acceptance Examples

- AE1. Covers R4. Mes agosto y semana 27 jul–02 ago: el promedio usa solo 01 ago y 02 ago.
- AE2. Covers R5, R6. Telar 201 / Templeros con valores 3 y 2 en el rango: celda 3 (promedio 2.5). Sin capturas: 0 gris.
- AE3. Covers R1, R9. Sin Consultar no hay botones de descarga ni matriz.
- AE4. Covers R10. Telegram caído: el Excel igual se descarga.

### Scope Boundaries

In: pantalla, cálculo semanal, Excel, PDF, imagen, Telegram ReporteMecanico.

Out: reporte Órdenes de Trabajo Diarias; guardar prioridad; cambiar la captura de Estado de Máquina; nueva columna en SYSMensajes.

<!-- ce-section: work-relationships -->
### How This Work Fits Together

This plan owns the weekly machine-state verification sheet.

- Órdenes de Trabajo Diarias — Can proceed independently of this report.
