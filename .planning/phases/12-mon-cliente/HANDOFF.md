# HANDOFF — fase 12 (MON-B) → dueño del PHP de monitoreo (sesión 13-14)

Hallazgos de la verificación en navegador contra los endpoints reales `/telemetria/*`. No bloquean la fase 12; son cambios en archivos que no son de esta sesión.

## 1. `SYSMonError.Ruta` de errores JS queda como `telemetria.error`

- **Archivo:** `app/Services/Monitoreo/ErrorRecorder.php` (`capturarCliente`) o `TelemetriaController::error`.
- **Qué pasa:** para errores de origen `js`/`livewire`/`red` la columna `Ruta` toma el nombre de la ruta de la request actual, que es el endpoint de telemetría. Visto en sqlite local: `{"Origen":"js","Ruta":"telemetria.error", …}`.
- **Cambio propuesto:** para errores de cliente, derivar `Ruta` de la vista (`SYSMonVista.Ruta` por el uuid `vista` que manda el cliente) o aceptar un campo `ruta` en el cuerpo. El cliente ya puede mandarlo: si se acepta `ruta`, basta con agregarlo al contrato §4 y el cliente lo incluye (1 línea en `resources/js/monitoreo/cliente.ts`, `reportar()`).
- **Por qué:** el panel de errores (fase 13) agrupa y filtra por ruta; hoy todos los errores de navegador caen en la misma.

## 2. `POST /telemetria/dispositivo/nombre` responde 204 aunque no se guarde

- **Archivo:** `TelemetriaController::nombre`.
- **Qué pasa:** ante un fallo de BD o sin cookie `towell_disp` responde 204 igual (principio "nunca 500"). El cliente no puede distinguir éxito de fallo.
- **Qué hizo el cliente:** la llave vieja `device_name_<hash>` de localStorage solo se borra cuando el render del modal ya trae el nombre del servidor (`data-nombre`), no al recibir 204. No hace falta cambio; se documenta para que nadie "optimice" el borrado.

## 3. (Informativo) El contrato §4 no lista `version` en `/telemetria/error`

El controlador ya lo lee (`$request->texto('version', 40)`) y el cliente lo manda. Sugerencia: agregarlo a la tabla del contrato en la próxima edición del integrador.
