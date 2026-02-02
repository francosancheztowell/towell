<?php

namespace App\Models\Engomado;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EngProduccionFormulacionModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv'; // o 'ProdTowel'

    protected $table = 'EngProduccionFormulacion';

    // Ahora usamos Id como PK, pero mantenemos Folio como clave única para compatibilidad
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Id',
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
        'Id'            => 'integer',
        'fecha'         => 'date',
        'Calibre'       => 'float',
        'Kilos'         => 'float',
        'Litros'        => 'float',
        'TiempoCocinado' => 'float',
        'Solidos'       => 'float',
        'Viscocidad'    => 'float',
    ];

    /** Relación: encabezado → líneas por Id (nueva relación mejorada) */
    public function lines()
    {
        return $this->hasMany(EngFormulacionLineModel::class, 'EngProduccionFormulacionId', 'Id');
    }

    /** Relación alternativa: encabezado → líneas por Folio (mantener compatibilidad) */
    public function linesByFolio()
    {
        return $this->hasMany(EngFormulacionLineModel::class, 'Folio', 'Folio');
    }
}
