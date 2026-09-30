# HANDOFF — 22-01 (track CAL)

Cosas que la sesión CAL no tocó porque no son suyas. Ninguna bloquea el merge de `claude/22-cal-gates`.

| Para | Archivo / tema | Qué | Por qué |
|---|---|---|---|
| Owner / ADOP (Ola 4) | `composer.lock` | Actualizar dependencias con advisories: 56 en 16 paquetes (`phpoffice/phpspreadsheet` crítico; `guzzlehttp/guzzle`, `league/commonmark`, `laravel/framework`, `maatwebsite/excel`, `symfony/http-kernel`, `symfony/mime` altos). `composer audit --locked` los lista. | Preexistentes. El gate nuevo solo bloquea los que agregue un cambio de `composer.lock`; bajarlos es una actualización con pruebas propias. |
| Owner / ADOP | `package-lock.json` | `npm audit fix` (`nanoid` < 3.3.18, alto). | Preexistente; `package.json/lock` está congelado en la Ola 3 salvo `jscpd`. |
| Owner (decisión de negocio) | `app/Imports/ReqModelosCodificadosImport.php::cleanExcelFormula` | ¿Debe seguir descartando todo texto sin dígitos de más de 2 caracteres (p. ej. `Modelo` = "TOALLA", `Pedido` = "ABIERTO") y las fechas de texto con `/`? | Los tests (`tests/Unit/Calidad/ReqModelosCodificadosImportTest.php`) fijan el comportamiento actual; cambiarlo es de negocio, no de calidad. |
| 19-06 | `CodificacionController::importProgress` | Opcional: mostrar en la UI `data.errors` / `data.total_errors` del import de modelos codificados (hoy el aviso final solo muestra nuevos/actualizados). | El import ya deja los errores en caché (antes se perdían en cola). |
| 19-03 | `OeeAtadoresFileService` | Sus 10 métodos privados sin uso (entradas `is unused` del baseline) y es el archivo de mayor riesgo de la tabla de cobertura (31 % en 3 042 líneas). | Anexo previsto en 22-CONTEXT. |
| PT (05.1) | `DuplicarTejido` (0 %), `ReqProgramaTejido{Update,Simple}Import` (2–3 %), `DividirTejido` (39 %) | Tests antes de partir/deduplicar; jscpd marca 704 líneas duplicadas entre los dos imports (`npm run ratchet -- --top 'duplicación %'`). | Tabla de 22-01-SUMMARY.md. |
