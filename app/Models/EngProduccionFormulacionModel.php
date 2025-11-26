<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EngProduccionFormulacionModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv'; // o 'ProdTowel'

    protected $table = 'EngProduccionFormulacion';

    // En tu grid no aparece Id; tomamos Folio como PK (string)
    protected $primaryKey = 'Folio';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'Hora',           // time -> lo manejamos como string
        'MaquinaId',
        'Cuenta',
        'Calibre',
        'Tipo',
        'CveEmpl',
        'NomEmpl',
        'Olla',
        'Formula',
        'Kilos',
        'Litros',
        'ProdId',
        'TiempoCocinado',
        'Solidos',
        'Viscocidad',     // así aparece en tu captura
        'Status',
    ];

    protected $casts = [
        'Calibre'        => 'float',
        'Kilos'          => 'float',
        'Litros'         => 'float',
        'TiempoCocinado' => 'float',
        'Solidos'        => 'float',
        'Viscocidad'     => 'float',
    ];

    /** Relación: encabezado → líneas por Folio */
    public function lines()
    {
        return $this->hasMany(EngFormulacionLineModel::class, 'Folio', 'Folio');
    }
}
