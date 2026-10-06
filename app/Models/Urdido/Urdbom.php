<?php

declare(strict_types=1);

namespace App\Models\Urdido;

use Illuminate\Database\Eloquent\Model;

/**
 * Lista de materiales de urdido (dbo.Urdbom): qué calibre/color lleva cada Lmat de un folio.
 *
 * @property string|null $Folio
 * @property string|null $Calibre
 * @property string|null $Config
 * @property string|null $Color
 * @property string|null $Porcentaje decimal:2
 * @property string $cump decimal:4
 * @property string $importe decimal:4
 * @property int|null $Materiales solo en el renglón agrupado por folio (COUNT), ver ListaMateriales
 */
class Urdbom extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'Urdbom';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = ['Folio', 'Lmat', 'Calibre', 'Config', 'Color', 'Cantidad', 'Porcentaje', 'cump', 'importe'];

    protected $casts = [
        'Cantidad' => 'decimal:2',
        'Porcentaje' => 'decimal:2',
        'cump' => 'decimal:4',
        'importe' => 'decimal:4',
    ];
}
