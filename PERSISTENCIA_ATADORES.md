# Mejoras de Persistencia - Módulo Catalogos Atadores

## Resumen de Cambios

Se implementaron mejoras significativas en el sistema de persistencia de datos para el módulo de catalogos-atadores, garantizando que todos los datos ingresados (checks de máquinas y actividades, merma y comentarios) se mantengan guardados independientemente del usuario que acceda al sistema.

## Cambios Realizados

### 1. **Controlador: AtadoresController.php**

#### Mejora en `iniciarAtado()` - Líneas 52-93

**Problema anterior:** El método eliminaba TODOS los registros "En Proceso" al iniciar un nuevo atado, lo que podía causar pérdida de datos si múltiples usuarios trabajaban simultáneamente.

**Solución implementada:**

-   Antes de crear un nuevo atado, se verifica si ya existe uno en proceso para el mismo `NoJulio` y `NoProduccion`
-   Si existe, simplemente redirige sin crear duplicados
-   Solo se eliminan registros "En Proceso" de OTROS atados diferentes
-   Esto permite que múltiples usuarios vean y modifiquen el mismo atado sin perder datos

```php
// Verificar si ya existe un atado en proceso para este mismo NoJulio
$existente = AtaMontadoTelasModel::where('NoJulio', $item->no_julio)
    ->where('NoProduccion', $item->no_orden)
    ->where('Estatus', 'En Proceso')
    ->first();

if ($existente) {
    // Si ya existe, simplemente redirigir a calificar sin eliminar datos
    return redirect()->route('atadores.calificar')->with('info', 'Continuando con atado en proceso');
}
```

### 2. **Vista: calificar-atadores/index.blade.php**

#### A. Auto-guardado de Observaciones

**Característica:** Las observaciones se guardan automáticamente 2 segundos después de que el usuario deja de escribir.

**Indicadores visuales:**

-   Icono de "Guardando..." mientras se procesa
-   Icono de "✓ Guardado" cuando se completa exitosamente
-   Los indicadores desaparecen automáticamente después de 2 segundos

```javascript
function handleObservacionesChange() {
    // Auto-guarda después de 2 segundos de inactividad
    autoSaveTimeout = setTimeout(() => {
        guardarObservacionesAuto();
    }, 2000);
}
```

#### B. Auto-guardado de Merma Kg

**Característica:** El campo de merma se guarda automáticamente 1.5 segundos después de que el usuario deja de escribir.

**Indicadores visuales:**

-   Checkmark verde (✓) aparece brevemente cuando se guarda exitosamente
-   Borde verde temporal en el input para confirmar guardado

```javascript
function handleMergaChange(valor) {
    if (valor && valor !== "") {
        mergaSaveTimeout = setTimeout(() => {
            guardarMerga(valor);
        }, 1500);
    }
}
```

#### C. Mejoras en Checkboxes de Máquinas y Actividades

**Características:**

-   Se guardan instantáneamente al hacer clic
-   Si el guardado falla, el checkbox se revierte automáticamente
-   Logs en consola confirman cada guardado exitoso
-   Mensajes de error claros si algo falla

```javascript
function toggleMaquina(maquinaId, checked){
    // ... código de guardado ...
    .then(res => {
        if(res.ok){
            console.log(`Máquina ${maquinaId} ${checked ? 'activada' : 'desactivada'} - Guardado en BD`);
        } else {
            // Revertir checkbox si falló
            const checkbox = document.querySelector(`input[onchange*="toggleMaquina('${maquinaId}'"]`);
            if (checkbox) checkbox.checked = !checked;
        }
    })
}
```

## Flujo de Persistencia de Datos

### 1. **Inicio de Atado**

```
Usuario selecciona registro → Verifica si existe atado en proceso →
    Si existe: Redirige a calificar (datos preservados)
    Si no existe: Crea nuevo atado con datos base
```

### 2. **Modificación de Checkboxes (Máquinas/Actividades)**

```
Usuario marca/desmarca → Envío inmediato a servidor →
    Éxito: Log en consola + datos guardados
    Error: Revierte checkbox + muestra mensaje de error
```

### 3. **Modificación de Merma**

```
Usuario escribe → Espera 1.5s sin cambios → Auto-guardado →
    Éxito: Muestra ✓ verde + borde verde temporal
    Error: Mensaje de error
```

### 4. **Modificación de Observaciones**

```
Usuario escribe → Espera 2s sin cambios → Auto-guardado →
    Éxito: Muestra "✓ Guardado"
    Error: Mensaje de error
```

## Garantías de Persistencia

### ✅ Multi-usuario

-   Varios usuarios pueden ver el mismo atado en proceso
-   Los cambios de un usuario son visibles para otros al recargar
-   No se pierden datos cuando diferentes usuarios acceden

### ✅ Recuperación ante errores

-   Si un guardado falla, el usuario es notificado
-   Los checkboxes se revierten si no se pueden guardar
-   Los datos no quedan en estado inconsistente

### ✅ Independencia de sesión

-   Los datos se guardan en base de datos SQL Server (`AtaMontadoTelas`, `AtaMontadoMaquinas`, `AtaMontadoActividades`)
-   No dependen de la sesión del navegador
-   Persisten incluso si el usuario cierra el navegador

### ✅ Auditoría

-   Cada actividad registra qué usuario la completó (`CveEmpl`, `NomEmpl`)
-   Se registran supervisores y tejedores
-   Timestamps de autorización (`FechaSupervisor`)

## Tablas de Base de Datos

### AtaMontadoTelas

Almacena el registro principal del atado con:

-   `Estatus`: "En Proceso" o "Autorizado"
-   `MergaKg`: Merma en kilogramos
-   `Obs`: Observaciones
-   `Calidad`, `Limpieza`: Calificaciones
-   `CveTejedor`, `NomTejedor`: Operador
-   `CveSupervisor`, `NomSupervisor`: Supervisor autorizador

### AtaMontadoMaquinas

Registra qué máquinas se usaron:

-   `MaquinaId`: Identificador de máquina
-   `Estado`: 1 (activa) o 0 (inactiva)

### AtaMontadoActividades

Registra las actividades completadas:

-   `ActividadId`: Identificador de actividad
-   `Estado`: 1 (completada) o 0 (pendiente)
-   `CveEmpl`, `NomEmpl`: Usuario que completó la actividad
-   `Porcentaje`: Porcentaje de la actividad

## Casos de Uso

### Caso 1: Usuario A inicia un atado

1. Usuario A selecciona un registro del programa de atadores
2. Se crea el atado con `Estatus = 'En Proceso'`
3. Usuario A marca algunas máquinas → Se guardan inmediatamente
4. Usuario A escribe merma: 2.5 → Se guarda automáticamente después de 1.5s

### Caso 2: Usuario B accede al mismo atado

1. Usuario B navega a calificar-atadores
2. Ve los datos que guardó Usuario A (checkboxes marcados, merma 2.5)
3. Usuario B marca actividades adicionales → Se guardan con su nombre
4. Usuario B escribe observaciones → Se auto-guardan

### Caso 3: Usuario A regresa después de salir

1. Usuario A cierra navegador
2. Usuario A vuelve a abrir y navega a calificar-atadores
3. Ve TODOS los datos: los suyos + los de Usuario B
4. Puede continuar trabajando sin pérdida de información

## Beneficios

1. **Sin pérdida de datos**: Auto-guardado continuo
2. **Feedback visual**: El usuario sabe cuándo se guardan los datos
3. **Multi-usuario**: Varios usuarios pueden colaborar en el mismo atado
4. **Recuperación de errores**: Los fallos no causan inconsistencias
5. **Auditoría completa**: Se registra quién hizo qué cambio
6. **Experiencia fluida**: No requiere clics manuales de "guardar" constantemente

## Notas Técnicas

-   **Conexión de BD**: SQL Server (`sqlsrv`)
-   **Framework**: Laravel con Eloquent ORM
-   **Frontend**: Blade templates con JavaScript vanilla
-   **AJAX**: Fetch API para comunicación asíncrona
-   **Notificaciones**: SweetAlert2 para mensajes al usuario

## Mantenimiento Futuro

Si se necesitan cambios adicionales:

1. **Agregar nuevos campos persistentes**:

    - Añadir a `$fillable` en el modelo correspondiente
    - Crear función de guardado similar a `guardarMerga()`
    - Agregar ruta en `save()` del controlador

2. **Modificar tiempos de auto-guardado**:

    - Cambiar valores de timeout (actualmente 2000ms y 1500ms)
    - Ubicados en `handleObservacionesChange()` y `handleMergaChange()`

3. **Agregar validaciones**:
    - Modificar el método `save()` en `AtadoresController.php`
    - Agregar reglas de validación con `$request->validate()`
