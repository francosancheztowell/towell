<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CalendarioT1;
use App\Models\CalendarioT2;
use App\Models\CalendarioT3;
use App\Models\Planeacion;

class CalendarioController extends Controller
{
    public function index(Request $request)
    {
        // Tabla 1: ReqCalendarioTab - Datos de ejemplo estáticos
        $calendarioTab = collect([
            (object)['Calendariold' => 'CAL001', 'Nombre' => 'Calendario Producción Enero'],
            (object)['Calendariold' => 'CAL002', 'Nombre' => 'Calendario Mantenimiento Febrero'],
            (object)['Calendariold' => 'CAL003', 'Nombre' => 'Calendario Turnos Marzo'],
            (object)['Calendariold' => 'CAL004', 'Nombre' => 'Calendario Especial Abril'],
            (object)['Calendariold' => 'CAL005', 'Nombre' => 'Calendario Vacaciones Mayo'],
            (object)['Calendariold' => 'CAL006', 'Nombre' => 'Calendario Fin de Semana Junio'],
            (object)['Calendariold' => 'CAL007', 'Nombre' => 'Calendario Feriados Julio'],
            (object)['Calendariold' => 'CAL008', 'Nombre' => 'Calendario Emergencias Agosto'],
            (object)['Calendariold' => 'CAL009', 'Nombre' => 'Calendario Capacitación Septiembre'],
            (object)['Calendariold' => 'CAL010', 'Nombre' => 'Calendario Inventario Octubre'],
        ]);

        // Tabla 2: ReqCalendarioLine - Datos de ejemplo estáticos
        $calendarioLine = collect([
            (object)['Calendariold' => 'CAL001', 'Fechalnicio' => '2024-01-01 06:00:00', 'FechaFin' => '2024-01-01 14:00:00', 'HorasTurno' => 8.0, 'Turno' => 1],
            (object)['Calendariold' => 'CAL001', 'Fechalnicio' => '2024-01-01 14:00:00', 'FechaFin' => '2024-01-01 22:00:00', 'HorasTurno' => 8.0, 'Turno' => 2],
            (object)['Calendariold' => 'CAL001', 'Fechalnicio' => '2024-01-01 22:00:00', 'FechaFin' => '2024-01-02 06:00:00', 'HorasTurno' => 8.0, 'Turno' => 3],
            (object)['Calendariold' => 'CAL002', 'Fechalnicio' => '2024-02-01 07:00:00', 'FechaFin' => '2024-02-01 15:00:00', 'HorasTurno' => 8.0, 'Turno' => 1],
            (object)['Calendariold' => 'CAL002', 'Fechalnicio' => '2024-02-01 15:00:00', 'FechaFin' => '2024-02-01 23:00:00', 'HorasTurno' => 8.0, 'Turno' => 2],
            (object)['Calendariold' => 'CAL003', 'Fechalnicio' => '2024-03-01 08:00:00', 'FechaFin' => '2024-03-01 16:00:00', 'HorasTurno' => 8.0, 'Turno' => 1],
            (object)['Calendariold' => 'CAL003', 'Fechalnicio' => '2024-03-01 16:00:00', 'FechaFin' => '2024-03-02 00:00:00', 'HorasTurno' => 8.0, 'Turno' => 2],
            (object)['Calendariold' => 'CAL004', 'Fechalnicio' => '2024-04-01 06:30:00', 'FechaFin' => '2024-04-01 14:30:00', 'HorasTurno' => 8.0, 'Turno' => 1],
            (object)['Calendariold' => 'CAL004', 'Fechalnicio' => '2024-04-01 14:30:00', 'FechaFin' => '2024-04-01 22:30:00', 'HorasTurno' => 8.0, 'Turno' => 2],
            (object)['Calendariold' => 'CAL005', 'Fechalnicio' => '2024-05-01 09:00:00', 'FechaFin' => '2024-05-01 17:00:00', 'HorasTurno' => 8.0, 'Turno' => 1],
            (object)['Calendariold' => 'CAL006', 'Fechalnicio' => '2024-06-01 10:00:00', 'FechaFin' => '2024-06-01 18:00:00', 'HorasTurno' => 8.0, 'Turno' => 1],
            (object)['Calendariold' => 'CAL007', 'Fechalnicio' => '2024-07-01 11:00:00', 'FechaFin' => '2024-07-01 19:00:00', 'HorasTurno' => 8.0, 'Turno' => 1],
            (object)['Calendariold' => 'CAL008', 'Fechalnicio' => '2024-08-01 12:00:00', 'FechaFin' => '2024-08-01 20:00:00', 'HorasTurno' => 8.0, 'Turno' => 1],
            (object)['Calendariold' => 'CAL009', 'Fechalnicio' => '2024-09-01 13:00:00', 'FechaFin' => '2024-09-01 21:00:00', 'HorasTurno' => 8.0, 'Turno' => 1],
            (object)['Calendariold' => 'CAL010', 'Fechalnicio' => '2024-10-01 14:00:00', 'FechaFin' => '2024-10-01 22:00:00', 'HorasTurno' => 8.0, 'Turno' => 1],
        ]);

        // Aplicar filtros de búsqueda para tabla 1 (solo frontend)
        if ($request->calendario_tab) {
            $calendarioTab = $calendarioTab->filter(function ($item) use ($request) {
                return stripos($item->Calendariold, $request->calendario_tab) !== false ||
                       stripos($item->Nombre, $request->calendario_tab) !== false;
            });
        }

        // Aplicar filtros de búsqueda para tabla 2 (solo frontend)
        if ($request->calendario_line) {
            $calendarioLine = $calendarioLine->filter(function ($item) use ($request) {
                return stripos($item->Calendariold, $request->calendario_line) !== false ||
                       stripos($item->Fechalnicio, $request->calendario_line) !== false ||
                       stripos($item->FechaFin, $request->calendario_line) !== false ||
                       stripos($item->Turno, $request->calendario_line) !== false;
            });
        }

        $totalTab = $calendarioTab->count();
        $totalLine = $calendarioLine->count();

        return view('catalagos.calendarios', [
            'calendarioTab' => $calendarioTab,
            'calendarioLine' => $calendarioLine,
            'totalTab' => $totalTab,
            'totalLine' => $totalLine
        ]);
    }
}
