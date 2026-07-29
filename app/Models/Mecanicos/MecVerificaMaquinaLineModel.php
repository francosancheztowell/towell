<?php

namespace App\Models\Mecanicos;

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
        'NoTelarId',
        'Orden',
        'Actividad',
        'Valor',
    ];

    protected $casts = [
        'Orden' => 'integer',
    ];

    public function verificacion(): BelongsTo
    {
        return $this->belongsTo(MecVerificaMaquinaModel::class, 'Folio', 'Folio');
    }
}
