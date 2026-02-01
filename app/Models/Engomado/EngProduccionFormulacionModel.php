<?php

namespace App\Models\Engomado;

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
        'fecha',
        'Hora',
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
        'Viscocidad',   
        'Status',
        'obs_calidad',
    ];

    protected $casts = [
        'fecha'          => 'date',
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
