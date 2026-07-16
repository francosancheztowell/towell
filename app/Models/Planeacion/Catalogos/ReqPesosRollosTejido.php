<?php

declare(strict_types=1);

namespace App\Models\Planeacion\Catalogos;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $Id
 * @property string $ItemId
 * @property string $ItemName
 * @property string $InventSizeId
 * @property float $PesoRollo
 * @property string|null $FechaCreacion
 * @property string|null $HoraCreacion
 * @property string|null $UsuarioCrea
 * @property string|null $FechaModificacion
 * @property string|null $HoraModificacion
 * @property string|null $UsuarioModifica
 */
class ReqPesosRollosTejido extends Model
{
    protected $table = 'ReqPesosRolloTejido';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'ItemId',
        'ItemName',
        'InventSizeId',
        'PesoRollo',
        'FechaCreacion',
        'HoraCreacion',
        'UsuarioCrea',
        'FechaModificacion',
        'HoraModificacion',
        'UsuarioModifica',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'Id' => 'integer',
            'PesoRollo' => 'float',
        ];
    }
}
