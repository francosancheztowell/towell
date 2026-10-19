<?php

declare(strict_types=1);

namespace App\Models\UrdEngomado;

use App\Models\Urdido\URDCatalogoMaquina;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Calendario de máquinas de Urdido/Engomado: minutos disponibles por máquina y turno
 * (tiempo total del OEE, ver .planning/oee_urdido_plan.md). Sin fila = turno no programado.
 * La ventana es [FechaInicio, FechaInicio + Minutos); el fin no se guarda.
 *
 * @property int $Id
 * @property int $Maquina URDCatalogoMaquinas.Id
 * @property Carbon $FechaInicio
 * @property int $Minutos
 * @property int $Turno
 */
class UrdEngCalMaquina extends Model
{
    /** Tope de Minutos (CHECK de la tabla): un turno nunca pasa de un día. */
    public const MAX_MINUTOS = 1440;

    /** Ventanas de reloj (TurnoHelper): 1 = mañana, 2 = tarde, 3 = noche (cuenta para el día en que empieza). */
    public const VENTANAS = [1 => '06:30', 2 => '14:30', 3 => '22:30'];

    /**
     * Qué turno (cuadrilla 1..4) cubre cada ventana según el día ISO (1 = lunes … 7 = domingo).
     * Hoja impresa de Urdido (owner, 19-oct-2026); cada día trabajan tres y uno descansa.
     */
    public const ROTACION_URDIDO = [
        1 => [1 => 1, 2 => 3, 3 => 4], // lunes
        2 => [1 => 1, 2 => 3, 3 => 4], // martes
        3 => [1 => 1, 2 => 2, 3 => 3], // miércoles
        4 => [1 => 1, 2 => 2, 3 => 3], // jueves
        5 => [1 => 1, 2 => 2, 3 => 4], // viernes
        6 => [1 => 1, 2 => 2, 3 => 4], // sábado
        7 => [1 => 3, 2 => 2, 3 => 4], // domingo
    ];

    protected $connection = 'sqlsrv';

    protected $table = 'UrdEngCalMaquinas';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = ['Maquina', 'FechaInicio', 'Minutos', 'Turno'];

    protected $casts = [
        'Id' => 'integer',
        'Maquina' => 'integer',
        'FechaInicio' => 'datetime',
        'Minutos' => 'integer',
        'Turno' => 'integer',
    ];

    /** @return BelongsTo<URDCatalogoMaquina, $this> */
    public function maquina(): BelongsTo
    {
        return $this->belongsTo(URDCatalogoMaquina::class, 'Maquina', 'Id');
    }

    public function fin(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->FechaInicio)->addMinutes($this->Minutos);
    }

    /**
     * ¿La ventana [inicio, inicio + minutos) se encima con otro turno de la máquina?
     * Sin DATEADD (los tests corren en SQLite): como ningún turno pasa de MAX_MINUTOS,
     * basta traer los que empiezan desde un día antes y comparar en PHP.
     */
    public static function seEncima(int $maquina, CarbonImmutable $inicio, int $minutos, ?int $ignorarId = null): bool
    {
        $fin = $inicio->addMinutes($minutos);

        return self::query()
            ->where('Maquina', $maquina)
            ->where('FechaInicio', '>', $inicio->subMinutes(self::MAX_MINUTOS))
            ->where('FechaInicio', '<', $fin)
            ->when($ignorarId !== null, fn ($q) => $q->where('Id', '!=', $ignorarId))
            ->get()
            ->contains(fn (self $fila): bool => $fila->fin() > $inicio);
    }

    /**
     * Turnos de un mes según una plantilla: por cada día del mes cuyo día de la semana
     * esté en $dias (1 = lunes … 7 = domingo), un turno por cada ventana (mañana, tarde, noche).
     * El número de turno (cuadrilla) sale de $rotacion según el día y la ventana.
     *
     * @param  array<int, array{ventana: int, hora: string, minutos: int}>  $ventanas  hora 'HH:MM'
     * @param  array<int, int>  $dias
     * @param  array<int, array<int, int>>  $rotacion  día ISO => [ventana => turno]
     * @return list<array{FechaInicio: CarbonImmutable, Minutos: int, Turno: int}>
     */
    public static function plantillaMes(string $mes, array $ventanas, array $dias, array $rotacion = self::ROTACION_URDIDO): array
    {
        $dia = CarbonImmutable::createFromFormat('!Y-m', $mes);
        $fin = $dia->addMonth();
        $filas = [];

        for (; $dia < $fin; $dia = $dia->addDay()) {
            if (! in_array($dia->dayOfWeekIso, $dias, true)) {
                continue;
            }
            foreach ($ventanas as $v) {
                [$h, $m] = array_map('intval', explode(':', $v['hora']));
                $filas[] = ['FechaInicio' => $dia->setTime($h, $m), 'Minutos' => $v['minutos'], 'Turno' => $rotacion[$dia->dayOfWeekIso][$v['ventana']]];
            }
        }

        return $filas;
    }
}
