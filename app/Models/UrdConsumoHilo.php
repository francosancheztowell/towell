<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrdConsumoHilo extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'UrdConsumoHilo';

    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'Folio',
        'FolioConsumo',
        'ItemId',
        'ConfigId',
        'InventSizeId',
        'InventColorId',
        'InventLocationId',
        'InventBatchId',
        'WMSLocationId',
        'InventSerialId',
        'InventQty',
        'ProdDate',
        'Status',
        'NumeroEmpleado',
        'NombreEmpl',
        'Conos',
        'LoteProv',
        'NoProv',
    ];

    protected $casts = [
        'Id' => 'integer',
        'InventQty' => 'float',
        'ProdDate' => 'date',
        'Conos' => 'integer',
    ];

    /**
     * Relación con UrdProgramaUrdido (N:1)
     */
    public function programaUrdido()
    {
        return $this->belongsTo(UrdProgramaUrdido::class, 'Folio', 'Folio');
    }
}

