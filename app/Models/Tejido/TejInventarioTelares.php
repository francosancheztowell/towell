<?php

namespace App\Models\Tejido;

use Illuminate\Database\Eloquent\Model;

class TejInventarioTelares extends Model
{
    protected $table = 'tej_inventario_telares';

    protected $primaryKey = 'id';

    protected $fillable = [
        'no_telar',
        'status',
        'tipo',
        'cuenta',
        'calibre',
        'fecha',
        'turno',
        'hilo',
        'metros',
        'no_julio',
        // Karl Mayer: una barra se alimenta de cuatro julios.
        'no_julio2',
        'no_julio3',
        'no_julio4',
        'no_orden',
        // Misma posición que no_julio2..4: cada julio de la barra puede traer otra orden.
        'no_orden2',
        'no_orden3',
        'no_orden4',
        'tipo_atado',
        'salon',
        'localidad',
        'LoteProveedor',
        'NoProveedor',
        'horaParo',
        'Reservado',
        'Programado',
        'ConfigId',
        'InventSizeId',
        'InventColorId',
    ];

    protected $casts = [
        'fecha' => 'date',
        'calibre' => 'decimal:2',
        'metros' => 'decimal:2',
        'turno' => 'integer',
        'Reservado' => 'boolean',
        'Programado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Accessors to map non-standard DB column names
    public function getLoteProveedorAttribute()
    {
        // Prefer native attribute if present
        if (array_key_exists('LoteProveedor', $this->attributes)) {
            return $this->attributes['LoteProveedor'];
        }
        // Common camelCase in MySQL
        if (array_key_exists('loteProveedor', $this->attributes)) {
            return $this->attributes['loteProveedor'];
        }
        // Map from possible DB column with underscore and mixed case
        if (array_key_exists('lote_Proveedor', $this->attributes)) {
            return $this->attributes['lote_Proveedor'];
        }

        return null;
    }

    public function getNoProveedorAttribute()
    {
        if (array_key_exists('NoProveedor', $this->attributes)) {
            return $this->attributes['NoProveedor'];
        }
        if (array_key_exists('noProveedor', $this->attributes)) {
            return $this->attributes['noProveedor'];
        }
        // Some sources may store as "No. Proveedor" (with dot and space)
        if (array_key_exists('No. Proveedor', $this->attributes)) {
            return $this->attributes['No. Proveedor'];
        }

        return null;
    }
}
