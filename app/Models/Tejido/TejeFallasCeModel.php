<?php

namespace App\Models\Tejido;

use Illuminate\Database\Eloquent\Model;

class TejeFallasCeModel extends Model
{
    //
    protected $table = "TejeFallasCe";

    protected $primaryKey = "Id";

    public $incrementing = true;

    protected $fillable = [
        "Clave",
        "Descripcion",
        'Activo',
    ];
}
