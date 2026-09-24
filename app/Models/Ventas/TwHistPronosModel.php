<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Solo lectura: dbo.TwHistoricosPronostico (base ReportesTowel) — Plan.
 * Tabla plana de líneas de pronóstico, sin primary key.
 *
 * Varias personas consultan estos datos en simultáneo (dashboards/reportes);
 * el modelo bloquea save()/delete() para que nadie escriba por accidente
 * sobre la fuente de reportes.
 */
class TwHistPronosModel extends Model
{
    protected $connection = 'sqlsrv_Reportes_Towell';

    protected $table = 'dbo.TwHistoricosPronostico';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $guarded = ['*'];

    public function save(array $options = []): bool
    {
        throw new RuntimeException('TwHistPronosModel es de solo lectura: dbo.TwHistoricosPronostico no admite escrituras.');
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('TwHistPronosModel es de solo lectura: dbo.TwHistoricosPronostico no admite escrituras.');
    }
}
