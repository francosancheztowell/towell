<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReqProgramaTejidoLine extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla (incluye mayúsculas exactas para SQL Server)
     */
    protected $table = 'ReqProgramaTejidoLine';

    /**
     * Clave primaria personalizada
     */
    protected $primaryKey = 'Id';

    /**
     * La tabla no tiene timestamps
     */
    public $timestamps = false;

    /**
     * Atributos asignables en masa
     */
    protected $fillable = [
        'ProgramaId',
        'Fecha',
        'Cantidad',
        'Kilos',
        'Aplicacion',
        'Trama',
        'Combina1',
        'Combina2',
        'Combina3',
        'Combina4',
        'Combina5',
        'Pie',
        'Rizo',
    ];

    /**
     * Casts de tipos de datos
     */
    protected $casts = [
        'Id'         => 'integer',
        'ProgramaId' => 'integer',
        'Fecha'      => 'date',
        'Cantidad'   => 'float',
        'Kilos'      => 'float',
        'Aplicacion' => 'float',
        'Trama'      => 'float',
        'Combina1'   => 'float',
        'Combina2'   => 'float',
        'Combina3'   => 'float',
        'Combina4'   => 'float',
        'Combina5'   => 'float',
        'Pie'        => 'float',
        'Rizo'       => 'float',
    ];
}


