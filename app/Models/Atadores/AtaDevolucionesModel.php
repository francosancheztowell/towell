<?php

namespace App\Models\Atadores;

use App\Models\Atadores\AtaMontadoTelasModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtaDevolucionesModel extends Model
{
    protected $table = 'AtaDevoluciones';
    protected $connection = 'sqlsrv';
    public $timestamps = false;
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'RefId',
        'NoJulio',
        'Kilos',
        'Ubicacion',
        'Metros',
        'FechaDevol',
        'Cuenta',
        'Calibre',
        'Hilo',
        'NoProduccion',
        'Tipo',
        'Obs',
        'ConfigId',
        'InventSizeId',
        'InventColorId',
        'Integrer',
        'Estatus',
    ];

    protected $casts = [
        'RefId' => 'integer',
        'Kilos' => 'float',
        'Metros' => 'float',
        'FechaDevol' => 'date',
        'Integrer' => 'integer',
    ];

    /**
     * Proceso de atado (AtaMontadoTelas) al que pertenece esta devolución.
     * FK: AtaDevoluciones.RefId -> AtaMontadoTelas.Id
     */
    public function montadoTela(): BelongsTo
    {
        return $this->belongsTo(AtaMontadoTelasModel::class, 'RefId', 'Id');
    }
}
