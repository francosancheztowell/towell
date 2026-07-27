<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MecVerificaMaquinaLineModel extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'MecVerificaMaquinaLine';

    protected $primaryKey = 'Id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'CveOperador',
        'NomOperador',
        'Ajusto',
        'Reparo',
        'Cambio',
        'Lubrico',
        'FaltaRefacc',
        'HoraInicial',
        'HoraFinal',
        'TotalMinutos',
        'Calificacion',
        'CveTejedor',
        'NomTejedor',
    ];

    protected $casts = [
        'Id' => 'integer',
        'Ajusto' => 'boolean',
        'Reparo' => 'boolean',
        'Cambio' => 'boolean',
        'Lubrico' => 'boolean',
        'FaltaRefacc' => 'boolean',
        'TotalMinutos' => 'integer',
        'Calificacion' => 'integer',
    ];

    public function verificacion(): BelongsTo
    {
        return $this->belongsTo(MecVerificaMaquinaModel::class, 'Folio', 'Folio');
    }
}
