# Spec: Feature de Calidad en Programa Urdido

**Fecha:** 2026-03-25
**Proyecto:** Towell (Laravel 12)
**Módulo:** Urdido > Programa Urdido

---

## 1. Objetivo

Agregar la funcionalidad de evaluación de calidad a cada orden en el programa de urdido. Permite registrar si una orden fue aprobada, rechazada, o tiene observaciones, con un campo de comentarios opcional. El resultado se muestra como un indicador visual en la tabla.

---

## 2. Campos en BD

### Tabla: `UrdProgramaUrdido`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `calidad` | CHAR(1) | Estado: 'A' (aprobado), 'R' (rechazado), 'O' (observaciones) |
| `calidadcomentario` | VARCHAR(60) | Comentarios u observaciones |

Estos campos ya existen en la tabla. El modelo `UrdProgramaUrdido` ya los tiene en `$fillable`.

---

## 3. Backend

### 3.1 Nueva Ruta

```
POST /urdido/programar-urdido/actualizar-calidad
```

### 3.2 Controlador: `ProgramarUrdidoController`

**Nuevo método `actualizarCalidad(Request $request): JsonResponse`**

- Valida: id (exists), calidad (in A,R,O), calidadcomentario (nullable, max 60)
- Actualiza campos en el modelo
- Retorna JSON con success y datos actualizados

### 3.3 Modelo: `UrdProgramaUrdido`

Agregar cast: `'calidad' => 'string'`

### 3.4 Actualizar `getOrdenes()`

Agregar `calidad` y `calidadcomentario` al select.

---

## 4. Frontend

### 4.1 Nueva columna en tabla

Header: "Calidad" (después de Observaciones)

Celdas con indicador visual:
- Aprobado (A): ✓ verde
- Rechazado (R): ✗ rojo  
- Observaciones (O): ! amarillo
- Vacío: — gris

Tooltip con `calidadcomentario` en hover.

### 4.2 Botón "Calidad"

- `<x-navbar.button-report>` con icono `fa-clipboard-check`
- Valida que haya exactamente 1 registro seleccionado
-bg `bg-amber-500`

### 4.3 Modal de Calidad

- Vanilla JS + Tailwind (sigue patrón del modal "Editar Prioridad" existente)
- Muestra Folio seleccionado
- 3 botones radio estilizados: ✓ (verde), ✗ (rojo), ! (amarillo)
- Textarea para observaciones (max 60 chars)
- Botones Cancelar / Guardar

### 4.4 JavaScript

Funciones:
- `abrirModalCalidad()` - verifica selección, fetch datos actuales, abre modal
- `cerrarModalCalidad()` - cierra modal y limpia estado
- `guardarCalidad()` - POST al backend, actualiza celda en tabla

---

## 5. Indicadores Visuales

| Valor | Símbolo | Color |
|-------|----------|-------|
| A | ✓ | verde |
| R | ✗ | rojo |
| O | ! | amarillo |
| null | — | gris |

---

## 6. Dependencias

- Ninguna nueva
