<?php

namespace App\Models\Inventario;

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
        'Fecha','Turno', // Columnas para referencia (fecha y turno del registro reservado)
        'TejInventarioTelaresId', // ID del registro específico en tej_inventario_telares (identificación única)
        'Status','NumeroEmpleado','NombreEmpl',
    ];

    protected $casts = [
        'Metros'     => 'decimal:4',
        'InventQty'  => 'decimal:4',
        // ProdDate se maneja con mutator para evitar fechas inválidas (1900-01-01)
    ];

    /**
     * Mutator para ProdDate: asegurar que NULL se guarde correctamente y evitar 1900-01-01
     */
    public function setProdDateAttribute($value)
    {
        // Si el valor es null, vacío, o es la fecha por defecto de SQL Server, guardar como NULL
        if ($value === null || $value === '' ||
            $value === '1900-01-01 00:00:00' ||
            $value === '1900-01-01' ||
            $value === '1900-01-01 00:00:00.000') {
            $this->attributes['ProdDate'] = null;
            return;
        }

        // Intentar parsear la fecha
        try {
            if (is_string($value)) {
                $parsed = \Carbon\Carbon::parse($value);
                // Verificar que no sea la fecha por defecto de SQL Server
                if ($parsed->year === 1900 && $parsed->month === 1 && $parsed->day === 1) {
                    $this->attributes['ProdDate'] = null;
                } elseif ($parsed->year >= 1901 && $parsed->year <= 2100) {
                    // Guardar como string en formato SQL Server
                    $this->attributes['ProdDate'] = $parsed->format('Y-m-d H:i:s');
                } else {
                    // Fecha fuera de rango razonable, guardar como NULL
                    $this->attributes['ProdDate'] = null;
                }
            } elseif ($value instanceof \DateTime || $value instanceof \Carbon\Carbon) {
                // Si ya es un objeto DateTime/Carbon
                $parsed = $value instanceof \Carbon\Carbon ? $value : \Carbon\Carbon::instance($value);
                if ($parsed->year === 1900 && $parsed->month === 1 && $parsed->day === 1) {
                    $this->attributes['ProdDate'] = null;
                } else {
                    $this->attributes['ProdDate'] = $parsed->format('Y-m-d H:i:s');
                }
            } else {
                $this->attributes['ProdDate'] = null;
            }
        } catch (\Throwable $e) {
            // Si no se puede parsear, guardar como NULL
            $this->attributes['ProdDate'] = null;
        }
    }

    /**
     * Accessor para ProdDate: convertir a Carbon cuando se lee
     */
    public function getProdDateAttribute($value)
    {
        if ($value === null || $value === '1900-01-01 00:00:00' || $value === '1900-01-01' || $value === '1900-01-01 00:00:00.000') {
            return null;
        }
        try {
            return $value ? \Carbon\Carbon::parse($value) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

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
