<?php

declare(strict_types=1);

namespace App\Http\Requests\Crudo;

use App\Services\Crudo\CrudoAccess;
use Illuminate\Foundation\Http\FormRequest;

abstract class CrudoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(CrudoAccess::class)->canAccess();
    }
}
