<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planeacion\Api\V1;

use App\DTO\Planeacion\PesosRollos\PesoRolloData;
use App\DTO\Planeacion\PesosRollos\PesoRolloFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\PesosRollos\IndexPesoRolloRequest;
use App\Http\Requests\Planeacion\PesosRollos\UpsertPesoRolloRequest;
use App\Http\Resources\Planeacion\PesoRolloResource;
use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use App\Services\Planeacion\PesosRollos\PesoRolloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PesoRolloController extends Controller
{
    public function __construct(private readonly PesoRolloService $service) {}

    public function index(IndexPesoRolloRequest $request): AnonymousResourceCollection
    {
        return PesoRolloResource::collection(
            $this->service->paginate(PesoRolloFilters::fromArray($request->validated()))
        );
    }

    public function store(UpsertPesoRolloRequest $request): JsonResponse
    {
        $model = $this->service->create(PesoRolloData::fromArray($request->validated()));

        return response()->json([
            'data' => (new PesoRolloResource($model))->resolve($request),
            'message' => 'Peso por rollo creado correctamente.',
        ], 201);
    }

    public function update(
        UpsertPesoRolloRequest $request,
        ReqPesosRollosTejido $pesoRollo,
    ): JsonResponse {
        $model = $this->service->update($pesoRollo, PesoRolloData::fromArray($request->validated()));

        return response()->json([
            'data' => (new PesoRolloResource($model))->resolve($request),
            'message' => 'Peso por rollo actualizado correctamente.',
        ]);
    }

    public function destroy(ReqPesosRollosTejido $pesoRollo): JsonResponse
    {
        $this->service->delete($pesoRollo);

        return response()->json([
            'data' => null,
            'message' => 'Peso por rollo eliminado correctamente.',
        ]);
    }
}
