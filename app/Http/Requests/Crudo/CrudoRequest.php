<?php

declare(strict_types=1);

namespace App\Http\Requests\Crudo;

use Illuminate\Foundation\Http\FormRequest;

abstract class CrudoRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $module = trim((string) config('crudo.permission_module', ''));

        return $module === ''
            || (function_exists('userCan') && userCan('acceso', $module));
    }
}
