# 🧪 Guía de Pruebas - Persistencia de Datos en Catalogos-Atadores

## Objetivo

Verificar que todos los datos ingresados (checkboxes, merma, observaciones) se guardan correctamente en la base de datos y son accesibles por todos los usuarios.

---

## Prerrequisitos

1. ✅ Base de datos SQL Server configurada y funcionando
2. ✅ Tablas necesarias existentes:
    - `AtaMontadoTelas`
    - `AtaMontadoMaquinas`
    - `AtaMontadoActividades`
    - `AtaMaquinas` (catálogo)
    - `AtaActividades` (catálogo)
3. ✅ Usuario autenticado en el sistema

---

## 🧪 Prueba 1: Persistencia de Checkboxes de Máquinas

### Pasos:

1. Navegar a "Programa de Atadores"
2. Seleccionar un registro y hacer clic en "Iniciar Atado"
3. En la vista "Calificar Atadores", marcar 2-3 checkboxes de máquinas
4. Abrir la consola del navegador (F12)
5. Verificar que aparecen mensajes como: `"Máquina XXX activada - Guardado en BD"`

### Resultado esperado:

-   ✅ Checkboxes quedan marcados
-   ✅ Mensajes en consola confirman guardado
-   ✅ Si hay error, checkbox se revierte automáticamente

### Verificación en Base de Datos:

```sql
SELECT * FROM AtaMontadoMaquinas
WHERE NoJulio = '[NoJulio del atado]'
  AND NoProduccion = '[NoProduccion del atado]'
  AND Estado = 1
```

**Debe mostrar:** Las máquinas marcadas con `Estado = 1`

---

## 🧪 Prueba 2: Persistencia de Checkboxes de Actividades

### Pasos:

1. En la misma vista, marcar 2-3 checkboxes de actividades
2. Observar que la columna "Operador" se actualiza automáticamente
3. Verificar mensajes en consola: `"Actividad YYY completada - Guardado en BD"`

### Resultado esperado:

-   ✅ Checkboxes quedan marcados
-   ✅ Columna "Operador" muestra el usuario que marcó cada actividad
-   ✅ Mensajes en consola confirman guardado

### Verificación en Base de Datos:

```sql
SELECT ActividadId, Estado, CveEmpl, NomEmpl
FROM AtaMontadoActividades
WHERE NoJulio = '[NoJulio del atado]'
  AND NoProduccion = '[NoProduccion del atado]'
  AND Estado = 1
```

**Debe mostrar:** Las actividades marcadas con `Estado = 1` y el usuario que las marcó

---

## 🧪 Prueba 3: Auto-guardado de Merma Kg

### Pasos:

1. En el campo "Merma Kg", escribir un valor: `2.5`
2. **No presionar Enter ni hacer clic fuera**
3. Esperar 2 segundos
4. Observar que aparece un checkmark verde (✓) brevemente

### Resultado esperado:

-   ✅ Aparece checkmark verde en el campo
-   ✅ El borde del input se pone verde brevemente
-   ✅ Los indicadores desaparecen después de 1 segundo

### Verificación en Base de Datos:

```sql
SELECT MergaKg
FROM AtaMontadoTelas
WHERE NoJulio = '[NoJulio del atado]'
  AND NoProduccion = '[NoProduccion del atado]'
```

**Debe mostrar:** `MergaKg = 2.5`

---

## 🧪 Prueba 4: Auto-guardado de Observaciones

### Pasos:

1. En el textarea "Observaciones", escribir: `"Esta es una prueba de auto-guardado"`
2. **No hacer clic en el botón "Guardar Observaciones"**
3. Observar el área del título:
    - Debe aparecer: `⟳ Guardando...`
    - Después de 2 segundos: `✓ Guardado`
4. Esperar 2 segundos más (el indicador "✓ Guardado" debe desaparecer)

### Resultado esperado:

-   ✅ Aparece indicador "Guardando..." mientras se espera
-   ✅ Aparece indicador "✓ Guardado" cuando se completa
-   ✅ El indicador desaparece automáticamente

### Verificación en Base de Datos:

```sql
SELECT Obs
FROM AtaMontadoTelas
WHERE NoJulio = '[NoJulio del atado]'
  AND NoProduccion = '[NoProduccion del atado]'
```

**Debe mostrar:** `Obs = "Esta es una prueba de auto-guardado"`

---

## 🧪 Prueba 5: Persistencia Multi-Usuario

### Pasos:

1. **Usuario A:** Marcar 2 máquinas y 1 actividad
2. **Usuario A:** Escribir merma: `3.5`
3. **Usuario A:** Escribir observación: `"Usuario A estuvo aquí"`
4. **Usuario B:** Abrir la misma vista en otro navegador o sesión
5. **Usuario B:** Verificar que ve todos los datos de Usuario A
6. **Usuario B:** Marcar 2 actividades adicionales
7. **Usuario B:** Agregar a observaciones: `"\nUsuario B también"`
8. **Usuario A:** Recargar página (F5)

### Resultado esperado:

-   ✅ Usuario B ve todos los datos de Usuario A
-   ✅ Usuario A ve los datos agregados por Usuario B
-   ✅ Las actividades muestran diferentes operadores
-   ✅ Las observaciones contienen texto de ambos usuarios
-   ✅ La merma sigue siendo `3.5` (no se sobrescribió)

### Verificación en Base de Datos:

```sql
-- Ver actividades con diferentes operadores
SELECT ActividadId, CveEmpl, NomEmpl
FROM AtaMontadoActividades
WHERE NoJulio = '[NoJulio del atado]'
  AND Estado = 1
ORDER BY ActividadId

-- Debe mostrar actividades con diferentes NomEmpl
```

---

## 🧪 Prueba 6: Recuperación ante Cierre de Navegador

### Pasos:

1. Marcar 3 checkboxes de máquinas
2. Marcar 2 checkboxes de actividades
3. Escribir merma: `1.75`
4. Escribir observaciones: `"Prueba de persistencia"`
5. **Cerrar completamente el navegador** (no solo la pestaña)
6. Abrir nuevamente el navegador
7. Autenticarse
8. Navegar a "Calificar Atadores"

### Resultado esperado:

-   ✅ TODOS los checkboxes siguen marcados
-   ✅ La merma sigue siendo `1.75`
-   ✅ Las observaciones siguen siendo `"Prueba de persistencia"`
-   ✅ Los operadores siguen asignados

---

## 🧪 Prueba 7: Manejo de Errores de Red

### Pasos:

1. Abrir DevTools (F12) → Pestaña "Network"
2. Cambiar a "Offline" (simular pérdida de conexión)
3. Intentar marcar un checkbox de máquina
4. Observar el comportamiento

### Resultado esperado:

-   ✅ Aparece un mensaje de error: "Error de red"
-   ✅ El checkbox se revierte automáticamente (queda desmarcado)
-   ✅ El usuario puede intentar nuevamente

### Prueba con conexión restaurada:

1. Cambiar de "Offline" a "Online"
2. Marcar el mismo checkbox nuevamente
3. Verificar que ahora sí se guarda correctamente

---

## 🧪 Prueba 8: Prevención de Sobrescritura

### Pasos:

1. **Usuario A:** Iniciar atado para registro X
2. **Usuario A:** Marcar 2 actividades
3. **Usuario A:** Dejar la vista abierta sin autorizar
4. **Usuario B:** Intentar iniciar atado para el MISMO registro X
5. **Usuario B:** Verificar que es redirigido a "Calificar Atadores"
6. **Usuario B:** Confirmar que ve las 2 actividades marcadas por Usuario A

### Resultado esperado:

-   ✅ Usuario B NO crea un nuevo atado
-   ✅ Usuario B es redirigido al atado existente
-   ✅ No se pierden los datos de Usuario A
-   ✅ Ambos usuarios pueden colaborar en el mismo atado

---

## 🧪 Prueba 9: Completar Flujo Completo

### Pasos:

1. Iniciar atado desde Programa de Atadores
2. Marcar TODAS las máquinas necesarias
3. Marcar TODAS las actividades (requerido para terminar)
4. Escribir merma y observaciones
5. Hacer clic en "Terminar Atado"
6. Calificar Tejedor (ingresar Calidad y Limpieza)
7. Autorizar Supervisor

### Resultado esperado:

-   ✅ El atado cambia de `Estatus = 'En Proceso'` a `Estatus = 'Autorizado'`
-   ✅ Se guarda en `TejHistorialInventarioTelares`
-   ✅ Se elimina de `tej_inventario_telares`
-   ✅ Los registros en `AtaMontadoTelas`, `AtaMontadoMaquinas` y `AtaMontadoActividades` se conservan como historial
-   ✅ El usuario es redirigido a "Programa de Atadores"

### Verificación en Base de Datos:

```sql
-- Verificar atado autorizado
SELECT Estatus, Calidad, Limpieza, CveSupervisor, MergaKg, Obs
FROM AtaMontadoTelas
WHERE NoJulio = '[NoJulio]'
  AND NoProduccion = '[NoProduccion]'
  AND Estatus = 'Autorizado'

-- Verificar registro en historial
SELECT * FROM TejHistorialInventarioTelares
WHERE NoJulio = '[NoJulio]'
```

---

## 📊 Checklist de Validación Final

Marcar cada item después de probarlo:

-   [ ] ✅ Checkboxes de máquinas se guardan inmediatamente
-   [ ] ✅ Checkboxes de actividades se guardan inmediatamente
-   [ ] ✅ Campo de merma se auto-guarda después de 1.5s
-   [ ] ✅ Observaciones se auto-guardan después de 2s
-   [ ] ✅ Indicadores visuales funcionan correctamente
-   [ ] ✅ Logs en consola confirman cada guardado
-   [ ] ✅ Checkboxes se revierten si el guardado falla
-   [ ] ✅ Múltiples usuarios ven los mismos datos
-   [ ] ✅ Datos persisten al cerrar/reabrir navegador
-   [ ] ✅ No se pierden datos al recargar página
-   [ ] ✅ Sistema previene sobrescritura accidental
-   [ ] ✅ Flujo completo funciona de inicio a fin

---

## 🐛 Qué hacer si algo falla

### Problema: Los checkboxes no se guardan

**Solución:**

1. Verificar conexión a SQL Server
2. Revisar logs de Laravel: `storage/logs/laravel.log`
3. Verificar permisos de usuario en base de datos
4. Comprobar que la ruta `/atadores/save` está accesible

### Problema: Auto-guardado no funciona

**Solución:**

1. Abrir consola del navegador (F12) y buscar errores JavaScript
2. Verificar que existe el CSRF token en la página
3. Comprobar que las funciones `handleObservacionesChange()` y `handleMergaChange()` están definidas

### Problema: "No autenticado" al guardar

**Solución:**

1. Verificar que el usuario sigue autenticado
2. Revisar configuración de sesiones en `config/session.php`
3. Aumentar tiempo de expiración de sesión si es necesario

### Problema: Datos no persisten entre usuarios

**Solución:**

1. Verificar que ambos usuarios están viendo el mismo `NoJulio` y `NoProduccion`
2. Comprobar que el método `calificarAtadores()` obtiene datos con `Estatus = 'En Proceso'`
3. Verificar que no hay cache activo que esté mostrando datos antiguos

---

## 📞 Soporte

Si encuentras algún problema no listado aquí:

1. Revisar `storage/logs/laravel.log` para errores del servidor
2. Revisar consola del navegador (F12) para errores de JavaScript
3. Verificar que todas las migraciones de base de datos están ejecutadas
4. Comprobar que los modelos tienen los campos en `$fillable`

---

**Fecha de última actualización:** Noviembre 2025
**Versión:** 1.0
**Estado:** Listo para producción
