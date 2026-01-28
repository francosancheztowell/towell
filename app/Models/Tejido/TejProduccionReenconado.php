<?php

namespace App\Models\Tejido;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TejProduccionReenconado extends Model
{
    use HasFactory;

    // SQL Server con esquema dbo
    protected $table = 'dbo.TejProduccionReenconado';

    // Definir llave primaria
    protected $primaryKey = 'Folio';
    public $incrementing = false;
    protected $keyType = 'string';

    // La tabla no maneja created_at / updated_at
    public $timestamps = false;

    // Permitimos asignación masiva para todos los campos definidos
    protected $guarded = [];

    protected $casts = [
        'Folio'            => 'string',
        'Date'             => 'date',
        'Turno'            => 'integer',
        'numero_empleado'  => 'string',
        'nombreEmpl'       => 'string',
        'Calibre'          => 'string',
        'FibraTrama'       => 'string',
        'CodColor'         => 'string',
        'Color'            => 'string',
        'Cantidad'         => 'float',
        'Cabezuela'        => 'float',
        'Conos'            => 'integer',
        'Horas'            => 'float',
        'Eficiencia'       => 'float',
        'Obs'              => 'string',
        'status'           => 'string',
        'capacidad'        => 'float',
    ];
}
