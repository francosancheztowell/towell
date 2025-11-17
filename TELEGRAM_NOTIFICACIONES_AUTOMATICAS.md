# 📱 Notificaciones Automáticas de Telegram

## Descripción
El sistema ahora envía automáticamente notificaciones a Telegram cuando se reporta una falla/paro y se marca el checkbox "Notificar a Supervisor".

## Funcionamiento

### 1. Cuándo se envía la notificación
- Cuando un usuario reporta una falla/paro en `/mantenimiento/nuevo-paro`
- Y marca el checkbox "Notificar a Supervisor"
- El sistema automáticamente envía un mensaje detallado a Telegram

### 2. Información incluida en el mensaje

El mensaje de Telegram incluye todos los datos del reporte:

```
🚨 NOTIFICACIÓN DE FALLA/PARO 🚨

📋 Folio: FP-00001
👤 Reportado por: Juan Pérez
📅 Fecha: 17/11/2025
🕐 Hora: 14:30
🏢 Departamento: URDIDO
🔧 Máquina: URD-01
⚠️ Tipo de Falla: ELECTRICO
❌ Falla: Falla del Motor
📝 Descripción: Motor no arranca
📋 Orden de Trabajo: OP-12345
💬 Observaciones: Se requiere atención urgente

✅ Estatus: Activo
🔄 Turno: 1
```

### 3. Configuración automática por tipo de falla

El sistema marca automáticamente el checkbox "Notificar a Supervisor" cuando se selecciona:
- **ELECTRICO**
- **MECANICO**

Para otros tipos de falla, el checkbox permanece desmarcado por defecto, pero el usuario puede marcarlo manualmente.

## Configuración Técnica

### Cambios realizados

#### 1. `MantenimientoParosController.php`
- Agregado método `enviarNotificacionTelegram()` privado
- Modificado método `store()` para detectar el checkbox y enviar notificación
- Formato del mensaje con emojis y Markdown para mejor legibilidad

#### 2. Campos incluidos automáticamente
- Folio del reporte
- Nombre del usuario que reporta
- Fecha y hora del reporte
- Departamento
- Máquina
- Tipo de falla
- Falla específica
- Descripción (si existe)
- Orden de trabajo (si existe)
- Observaciones (si existen)
- Estatus
- Turno

### Logs y Registro

El sistema registra en logs:
- ✅ Cuando se envía una notificación exitosamente
- ❌ Cuando hay errores al enviar
- ⚠️ Cuando las credenciales de Telegram no están configuradas

## Ventajas

1. **Notificación inmediata** al supervisor cuando hay una falla crítica
2. **Información completa** en un solo mensaje
3. **No interrumpe el flujo** - el reporte se guarda aunque falle el envío a Telegram
4. **Formato claro** con emojis y texto estructurado
5. **Trazabilidad** - todos los envíos quedan registrados en los logs

## Manejo de Errores

- Si Telegram no está configurado, el sistema solo registra un warning pero continúa
- Si falla el envío a Telegram, el reporte se guarda correctamente de todos modos
- Los errores se registran en `storage/logs/laravel.log` para diagnóstico

## Futuras Mejoras Posibles

- [ ] Enviar a múltiples chat_ids (varios supervisores)
- [ ] Diferentes mensajes según el tipo de falla
- [ ] Agregar botones inline para acciones rápidas
- [ ] Enviar foto o documento adjunto
- [ ] Notificación cuando se finaliza el paro
- [ ] Resumen diario de paros/fallas

