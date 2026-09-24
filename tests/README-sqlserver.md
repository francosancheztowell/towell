# Tests del grupo `sqlserver`

`phpunit.xml` corre la suite en sqlite en memoria y **excluye** el grupo `sqlserver`.
Esos tests ejercitan cosas que solo existen en SQL Server (el trigger
`tr_ReqProgramaTejido_Audit`, la pantalla de auditoría y la ruta real de
Programa Tejido), así que en sqlite no prueban nada. Además conservan su guarda
`markTestSkipped` por si alguien los corre a mano sin SQL Server.

| Archivo | Qué necesita |
|---|---|
| `tests/Feature/AuditoriaProgramaTejidoTest.php` | Trigger de auditoría en `ReqProgramaTejido` |
| `tests/Feature/AuditoriaProgramaTejidoPantallaTest.php` | Tabla de auditoría + usuarios del área Sistemas y de otra área |
| `tests/Feature/ProgramaTejidoIndexSmokeTest.php` | `/planeacion/programa-tejido` contra datos reales |

## Cómo correrlos (Laragon, con BD de pruebas)

> Escriben y borran registros desechables (prefijos `ZZ-AUD`, `ZZ-PANTALLA`).
> Úsalos contra una copia de la BD, **nunca** contra ProdTowel.

```powershell
# PowerShell: las variables de entorno del proceso ganan a las <env> de phpunit.xml
$env:DB_CONNECTION = "sqlsrv"
$env:DB_DATABASE   = "<BD de pruebas>"
php artisan test --group=sqlserver
Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE
```

`--group=sqlserver` en la línea de comandos reemplaza el `<exclude>` de `phpunit.xml`.

## Cuándo agregar un test aquí

Solo si lo que prueba **es** semántica de SQL Server (triggers, `sp_SetAppContext`,
`sys.*`, `SESSION_CONTEXT`). Si solo le falta una tabla, usa
`Tests\Concerns\UsesSqlsrvSqlite` (`useSqlsrvSqlite()`, `createTablaDesdeModelo()`).
Nunca `markTestSkipped`/`markTestIncomplete` para ponerlo en verde.
