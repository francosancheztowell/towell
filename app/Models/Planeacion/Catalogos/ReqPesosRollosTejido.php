<?php
namespace App\Models\Planeacion\Catalogos;
use Illuminate\Database\Eloquent\Model;

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
        'UsuarioModifica'
    ];
}
