<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crudo;

use App\Http\Controllers\Controller;
use App\Services\Crudo\CrudoAccess;
use Illuminate\Contracts\View\View;

final class CrudoController extends Controller
{
    public function __construct(
        private readonly CrudoAccess $access,
    ) {}

    public function index(): View
    {
        $this->access->authorize();

        return view('modulos.crudo.index');
    }
}
