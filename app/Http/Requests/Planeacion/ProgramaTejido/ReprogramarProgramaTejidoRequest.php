<?php

declare(strict_types=1);

namespace App\Http\Requests\Planeacion\ProgramaTejido;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST {programa-tejido,muestras}/{id}/reprogramar en v2 (PT-05). Única diferencia con
 * el legacy: un valor inválido responde 422 (el legacy lo atrapaba y devolvía 500).
 */
final class ReprogramarProgramaTejidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'reprogramar' => 'nullable|string|in:1,2',
        ];
    }
}
