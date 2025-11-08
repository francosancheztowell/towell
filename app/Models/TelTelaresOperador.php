<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelTelaresOperador extends Model
{
    //

    protected $table = 'TelTelaresOperador';

    // Esta tabla no tiene PK explícita; usamos numero_empleado como clave “lógica”.
    protected $primaryKey = 'numero_empleado';
    public $incrementing = false;  // no es identity/autoincrement
    protected $keyType = 'string';
    public $timestamps = false;    // no created_at/updated_at

    protected $fillable = [
        'numero_empleado',
        'nombreEmpl',
        'NoTelarId',
    ];

    /**
     * Clave usada para el route model binding.
     */
    public function getRouteKeyName()
    {
        return 'numero_empleado';
    }
}
