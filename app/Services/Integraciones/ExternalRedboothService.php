<?php

declare(strict_types=1);

namespace App\Services\Integraciones;

use App\Models\Sistema\Usuario;

final readonly class ExternalRedboothService
{
    public function __construct(
        private RedboothService $redbooth,
    ) {}

    /** @return array<string, mixed> */
    public function me(): array
    {
        return $this->redbooth->me($this->technicalUser());
    }

    /** @param array<string, int|string> $filters */
    public function activities(array $filters): array
    {
        return $this->redbooth->activities($this->technicalUser(), $filters);
    }

    /** @param array<string, bool|int|string> $filters */
    public function tasks(array $filters): array
    {
        return $this->redbooth->tasks($this->technicalUser(), $filters);
    }

    /** @param array<string, int|string> $filters */
    public function comments(array $filters): array
    {
        return $this->redbooth->comments($this->technicalUser(), $filters);
    }

    /** @param array<string, bool|int|string> $filters */
    public function files(array $filters): array
    {
        return $this->redbooth->files($this->technicalUser(), $filters);
    }

    public function fileDownloadUrl(int $fileId): string
    {
        return $this->redbooth->fileDownloadUrl($this->technicalUser(), $fileId);
    }

    private function technicalUser(): Usuario
    {
        $userId = (int) config('redbooth.external_user_id');
        $usuario = new Usuario;
        $usuario->idusuario = $userId;

        return $usuario;
    }
}
