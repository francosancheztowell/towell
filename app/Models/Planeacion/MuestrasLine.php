<?php
declare(strict_types=1);

namespace App\Models\Planeacion;

class MuestrasLine extends ReqProgramaTejidoLine
{
    protected $table = 'MuestrasProgramaLine';

    public function getTable()
    {
        return $this->table;
    }

    public static function tableName(): string
    {
        return (new static())->table;
    }

    public function programa()
    {
        return $this->belongsTo(Muestras::class, 'ProgramaId', 'Id');
    }
}
