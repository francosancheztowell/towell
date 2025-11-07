<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvTelasReservadas extends Model
{
    protected $table = 'InvTelasReservadas';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'ItemId','ConfigId','InventSizeId','InventColorId','InventLocationId',
        'InventBatchId','WMSLocationId','InventSerialId',
        'Tipo','Metros','InventQty','ProdDate',
        'NoTelarId','SalonTejidoId',
        'Status','NumeroEmpleado','NombreEmpl',
    ];

    protected $casts = [
        'Metros'     => 'decimal:4',
        'InventQty'  => 'decimal:4',
        'ProdDate'   => 'datetime',
    ];

    /** Clave compuesta “natural” para comparar piezas entre orígenes */
    public function getDimKeyAttribute(): string
    {
        return implode('|', [
            $this->ItemId, $this->ConfigId, $this->InventSizeId, $this->InventColorId,
            $this->InventLocationId, $this->InventBatchId, $this->WMSLocationId,
            $this->InventSerialId,
        ]);
    }
}
