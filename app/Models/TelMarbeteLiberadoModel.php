<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelMarbeteLiberadoModel extends Model
{
    use HasFactory;

    // protected $connection = 'sqlsrv'; // o 'ProdTowel' si usas otra conexión
    protected $table = 'TelMarbeteLiberado';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'PurchBarCode',   // NVARCHAR(25)  - Marbete
        'ItemId',         // NVARCHAR(20)  - Artículo
        'InventSizeId',   // NVARCHAR(10)  - Tamaño
        'InventBatchId',  // NVARCHAR(10)  - Orden
        'WMSLocationId',  // NVARCHAR(10)  - Telar
        'QtySched',       // REAL          - Piezas
        'Salon',          // NVARCHAR(20)  - Salón
        'CUANTAS',        // INT           - Cuantas
    ];

    protected $casts = [
        'Id'       => 'integer',
        'QtySched' => 'float',
        'CUANTAS'  => 'integer',
    ];

    /* ===== Scopes útiles (opcionales) ===== */

    public function scopeByBarcode($q, string $barcode)
    {
        return $q->where('PurchBarCode', $barcode);
    }

    public function scopeByTelar($q, string $telar)
    {
        return $q->where('WMSLocationId', $telar);
    }

    public function scopeByItem($q, string $itemId)
    {
        return $q->where('ItemId', $itemId);
    }
}
