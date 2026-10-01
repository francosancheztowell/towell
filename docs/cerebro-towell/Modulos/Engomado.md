# Módulo: Engomado

## Rol
Programa/producción engomado; fórmula; BPM; calificar julios eng; reportes.

## Rutas
`engomado.php` (~71). Prefijo `/engomado`.

## Reglas
- Fórmula: todas las del BOM del folio
- AX=1 misma regla que urdido sobre EngProduccionEngomado
- Status En Proceso exige Urdido Finalizado y tope 2× por máquina vía `ProgramBoardActionService` (mismo camino Livewire y POST legacy)
- Producción, Kg. Bruto: tope 2000 kg (server `maxKgBrutoAllowed()` + validación al finalizar). En pantalla el exceso solo se marca en rojo y no se guarda; **nunca** se reescribe el input al teclear.
- Producción, autoguardado de Kg. Bruto (`resources/js/modulos/engomado/produccion/filas.ts`, `ESPERA_AUTOGUARDADO_MS = 10_000`): guarda tras **10 s** sin teclear, al salir del campo o con Enter. Es lento a propósito (oct-2026): los capturistas teclean despacio y el guardado a 1 s reescribía el valor (`12` → `12.00`) y les movía el cursor. Mientras hay un guardado pendiente o en camino se deshabilita **Finalizar** y no se deja marcar **Listo** en esa fila, para no cerrar con un peso sin guardar. La respuesta del servidor no toca el input si el usuario volvió a entrar a él.
