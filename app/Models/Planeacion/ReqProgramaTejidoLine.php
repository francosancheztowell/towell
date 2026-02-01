<?php
declare(strict_types=1);

namespace App\Models\Planeacion;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ReqProgramaTejidoLine extends Model
{
    use HasFactory;

    protected $table = 'ReqProgramaTejidoLine';
    protected $primaryKey = 'Id';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    public function getTable()
    {
        $override = config('planeacion.programa_tejido_line_table');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        return $this->table;
    }

    public static function tableName(): string
    {
        $override = config('planeacion.programa_tejido_line_table');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        return (new static())->table;
    }

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
        'MtsRizo',
        'MtsPie',
    ];

    protected $casts = [
        'Id'         => 'integer',
        'ProgramaId' => 'integer',
        'Fecha'      => 'date', // DATE en SQL Server
        'Cantidad'   => 'float', // REAL en SQL Server
        'Kilos'      => 'float', // REAL en SQL Server
        'Aplicacion' => 'string', // VARCHAR(50) en SQL Server
        'Trama'      => 'float', // REAL en SQL Server
        'Combina1'   => 'float', // REAL en SQL Server
        'Combina2'   => 'float', // REAL en SQL Server
        'Combina3'   => 'float', // REAL en SQL Server
        'Combina4'   => 'float', // REAL en SQL Server
        'Combina5'   => 'float', // REAL en SQL Server
        'Pie'        => 'float', // REAL en SQL Server
        'Rizo'       => 'float', // REAL en SQL Server
        'MtsRizo'    => 'float', // REAL en SQL Server
        'MtsPie'     => 'float', // REAL en SQL Server
    ];

    /* ---------- Relaciones ---------- */
    public function programa()
    {
        return $this->belongsTo(ReqProgramaTejido::class, 'ProgramaId', 'Id');
    }

    /* ---------- Scopes útiles ---------- */
    public function scopePrograma(Builder $q, int $programaId): Builder
    {
        return $q->where('ProgramaId', $programaId);
    }

    public function scopeOnDate(Builder $q, string $date): Builder
    {
        return $q->whereDate('Fecha', $date);
    }

    public function scopeBetween(Builder $q, string $from, string $to): Builder
    {
        // Usar whereDate con >= y <= para asegurar que incluya ambos extremos
        // whereBetween puede excluir el último día en SQL Server con tipo DATE
        return $q->whereDate('Fecha', '>=', $from)
                  ->whereDate('Fecha', '<=', $to);
    }

    /* ---------- Normalizador de numéricos vacíos -> null ---------- */
    public function setAttribute($key, $value)
    {
        static $numeric = [
            'Cantidad','Kilos','Trama',
            'Combina1','Combina2','Combina3','Combina4','Combina5',
            'Pie','Rizo','MtsRizo','MtsPie',
        ];
        // Aplicacion ya no es numérico, es VARCHAR(50)
        if (in_array($key, $numeric, true) && ($value === '' || $value === 'null')) {
            $value = null;
        }
        return parent::setAttribute($key, $value);
    }
}
