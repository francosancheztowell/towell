<?php

declare(strict_types=1);

namespace App\Models\Sistema;

use Illuminate\Database\Eloquent\Model;

/**
 * Departamento de URDCatalogoMaquinas asignado a un usuario: en paros ve todas sus máquinas,
 * también las que se den de alta después. Supervisor va igual en todas las filas del usuario.
 *
 * @property int $Id
 * @property int $idusuario
 * @property string $Departamento
 * @property bool $Supervisor
 */
class SYSDepartamentoAsignacion extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'SYSDepartamentosAsignacion';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = ['idusuario', 'Departamento', 'Supervisor'];

    protected $casts = [
        'idusuario' => 'integer',
        'Supervisor' => 'boolean',
    ];
}
