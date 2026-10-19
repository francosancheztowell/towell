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
     * esté en $dias (1 = lunes … 7 = domingo), un turno por cada entrada de $turnos.
     *
     * @param  array<int, array{turno: int, hora: string, minutos: int}>  $turnos  hora 'HH:MM'
     * @param  array<int, int>  $dias
     * @return list<array{FechaInicio: CarbonImmutable, Minutos: int, Turno: int}>
     */
    public static function plantillaMes(string $mes, array $turnos, array $dias): array
    {
        $dia = CarbonImmutable::createFromFormat('!Y-m', $mes);
        $fin = $dia->addMonth();
        $filas = [];

        for (; $dia < $fin; $dia = $dia->addDay()) {
            if (! in_array($dia->dayOfWeekIso, $dias, true)) {
                continue;
            }
            foreach ($turnos as $t) {
                [$h, $m] = array_map('intval', explode(':', $t['hora']));
                $filas[] = ['FechaInicio' => $dia->setTime($h, $m), 'Minutos' => $t['minutos'], 'Turno' => $t['turno']];
            }
        }

        return $filas;
    }
}
