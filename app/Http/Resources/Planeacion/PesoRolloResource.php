<?php

declare(strict_types=1);

namespace App\Http\Resources\Planeacion;

use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PesoRolloResource extends JsonResource
{
    /** @return array<string, int|float|string|null> */
    public function toArray(Request $request): array
    {
        /** @var ReqPesosRollosTejido $model */
        $model = $this->resource;

        return [
            'id' => $model->Id,
            'item_id' => $model->ItemId,
            'item_name' => $model->ItemName,
            'invent_size_id' => $model->InventSizeId,
            'peso_rollo' => $model->PesoRollo,
            'fecha_creacion' => $model->FechaCreacion,
            'usuario_crea' => $model->UsuarioCrea,
            'fecha_modificacion' => $model->FechaModificacion,
            'usuario_modifica' => $model->UsuarioModifica,
        ];
    }
}
