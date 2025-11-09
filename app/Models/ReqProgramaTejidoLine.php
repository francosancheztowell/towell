<?php
declare(strict_types=1);

namespace App\Models;

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
        'Fecha'      => 'date', // Cambiar a 'date' para mejor compatibilidad con SQL Server
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
        'MtsRizo'    => 'float',
        'MtsPie'     => 'float',
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
        return $q->whereBetween('Fecha', [$from, $to]);
    }

    /* ---------- Normalizador de numéricos vacíos -> null ---------- */
    public function setAttribute($key, $value)
    {
        static $numeric = [
            'Cantidad','Kilos','Aplicacion','Trama',
            'Combina1','Combina2','Combina3','Combina4','Combina5',
            'Pie','Rizo','MtsRizo','MtsPie',
        ];
        if (in_array($key, $numeric, true) && ($value === '' || $value === 'null')) {
            $value = null;
        }
        return parent::setAttribute($key, $value);
    }
}
