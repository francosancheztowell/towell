<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Redbooth\ExternalActivitiesRequest;
use App\Http\Requests\Api\Redbooth\ExternalCommentsRequest;
use App\Http\Requests\Api\Redbooth\ExternalFilesRequest;
use App\Http\Requests\Api\Redbooth\ExternalTasksRequest;
use App\Services\Integraciones\ExternalRedboothService;
use Illuminate\Http\JsonResponse;

final class ExternalRedboothController extends Controller
{
    public function __construct(
        private readonly ExternalRedboothService $redbooth,
    ) {}

    public function me(): JsonResponse
    {
        return response()->json($this->redbooth->me());
    }

    public function activities(ExternalActivitiesRequest $request): JsonResponse
    {
        return response()->json($this->redbooth->activities($request->validated()));
    }

    public function tasks(ExternalTasksRequest $request): JsonResponse
    {
        return response()->json($this->redbooth->tasks($request->validated()));
    }

    public function comments(ExternalCommentsRequest $request): JsonResponse
    {
        return response()->json($this->redbooth->comments($request->validated()));
    }

    public function files(ExternalFilesRequest $request): JsonResponse
    {
        return response()->json($this->redbooth->files($request->validated()));
    }

    public function images(ExternalFilesRequest $request): JsonResponse
    {
        $files = $this->redbooth->files($request->validated());

        return response()->json(array_values(array_filter(
            $files,
            static fn (array $file): bool => str_starts_with(
                strtolower((string) ($file['mime_type'] ?? '')),
                'image/',
            ),
        )));
    }

    public function download(int $fileId): JsonResponse
    {
        return response()->json([
            'download_url' => $this->redbooth->fileDownloadUrl($fileId),
        ]);
    }
}
