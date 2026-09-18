# Módulo: Engomado

## Rol
Programa/producción engomado; fórmula; BPM; calificar julios eng; reportes.

## Rutas
`engomado.php` (~71). Prefijo `/engomado`.

## Reglas
- Fórmula: todas las del BOM del folio
- AX=1 misma regla que urdido sobre EngProduccionEngomado
- Status En Proceso exige Urdido Finalizado y tope 2× por máquina vía `ProgramBoardActionService` (mismo camino Livewire y POST legacy)
