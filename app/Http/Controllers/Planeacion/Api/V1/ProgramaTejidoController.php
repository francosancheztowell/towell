<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planeacion\Api\V1;

use App\DTO\Planeacion\ProgramaTejido\ProgramaTejidoFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\ProgramaTejido\IndexProgramaTejidoRequest;
use App\Http\Resources\Planeacion\ProgramaTejidoResource;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoReadService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProgramaTejidoController extends Controller
{
    public function __construct(private readonly ProgramaTejidoReadService $service) {}

    public function index(IndexProgramaTejidoRequest $request): AnonymousResourceCollection
    {
        return ProgramaTejidoResource::collection(
            $this->service->paginate(ProgramaTejidoFilters::fromArray($request->validated()))
        );
    }
}
