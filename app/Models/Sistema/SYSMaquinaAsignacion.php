<?php

declare(strict_types=1);

namespace App\Models\Sistema;

use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Máquina asignada a un usuario. El departamento sale de la máquina (URDCatalogoMaquinas).
 *
 * @property int $Id
 * @property int $idusuario
 * @property string $MaquinaId
 * @property bool $Supervisor
 */
class SYSMaquinaAsignacion extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'SYSMaquinasAsignacion';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = ['idusuario', 'MaquinaId', 'Supervisor'];

    protected $casts = [
        'idusuario' => 'integer',
        'Supervisor' => 'boolean',
    ];

    /** @return BelongsTo<URDCatalogoMaquina, $this> */
    public function maquina(): BelongsTo
    {
        return $this->belongsTo(URDCatalogoMaquina::class, 'MaquinaId', 'MaquinaId');
    }
}
