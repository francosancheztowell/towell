<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\Catalogos;

use App\Models\Planeacion\ReqAplicaciones;
use Illuminate\Validation\Rule;

class AplicacionRequest extends CatalogoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // En edición la ruta trae Id o AplicacionId; la clave no puede repetirse en otro registro.
        $actual = $this->route('aplicacion');
        $unica = Rule::unique(ReqAplicaciones::class, 'AplicacionId');
        if ($actual !== null) {
            $registro = ReqAplicaciones::buscarPorIdOClave((string) $actual);
            $unica = $registro ? $unica->ignore($registro->Id, 'Id') : $unica;
        }

        return [
            'AplicacionId' => ['required', 'string', 'max:50', $unica],
            'Nombre' => 'required|string|max:100',
            'Factor' => 'nullable|numeric',
        ];
    }
}
