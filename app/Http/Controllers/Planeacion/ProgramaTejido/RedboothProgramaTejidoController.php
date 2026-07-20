<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\ProgramaTejido\SaveRedboothProgramaTejidoRequest;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\Usuario;
use App\Services\Integraciones\RedboothService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Consulta tareas y vincula registros de Programa Tejido con Redbooth.
 */
final class RedboothProgramaTejidoController extends Controller
{
    private const PROJECT_ID = 2113514;

    private const TASK_LIST_ID = 6863455;

    public function __construct(
        private readonly RedboothService $redbooth,
    ) {}

    public function projectOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $usuario = $request->user();
        abort_unless($usuario instanceof Usuario, 401);

        $search = trim((string) ($validated['q'] ?? ''));
        $tasks = $this->redbooth->tasks($usuario, [
            'project_id' => self::PROJECT_ID,
            'task_list_id' => self::TASK_LIST_ID,
            'order' => 'created_at-DESC',
            'per_page' => 1000,
            'page' => 1,
        ]);

        $results = collect($tasks)
            ->filter(static fn (array $task): bool => ($task['deleted'] ?? false) !== true)
            ->filter(static function (array $task) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                return Str::contains(
                    Str::lower((string) ($task['name'] ?? '')),
                    Str::lower($search),
                ) || str_contains((string) ($task['id'] ?? ''), $search);
            })
            ->map(static function (array $task): array {
                $id = (int) ($task['id'] ?? 0);
                $name = trim((string) ($task['name'] ?? ''));

                return [
                    'id' => $id,
                    'name' => $name,
                    'text' => "{$id} — {$name}",
                ];
            })
            ->filter(static fn (array $task): bool => $task['id'] > 0 && $task['name'] !== '')
            ->values()
            ->all();

        return response()->json(['results' => $results]);
    }

    public function store(SaveRedboothProgramaTejidoRequest $request): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof Usuario, 401);
        $validated = $request->validated();
        $taskId = (int) $validated['redbooth_task_id'];
        $task = collect($this->tasks($usuario))
            ->first(static fn (array $candidate): bool => (int) ($candidate['id'] ?? 0) === $taskId
                && ($candidate['deleted'] ?? false) !== true);

        if (! is_array($task) || trim((string) ($task['name'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'redbooth_task_id' => 'La tarea seleccionada no pertenece a la lista configurada de Redbooth.',
            ]);
        }

        $taskName = trim((string) $task['name']);
        $source = (string) ($validated['source'] ?? 'programa');
        $catCodificadosActualizados = DB::connection('sqlsrv')->transaction(function () use ($validated, $taskId, $taskName, $source): int {
            if ($source === 'catcodificados') {
                $cat = CatCodificados::query()
                    ->lockForUpdate()
                    ->findOrFail((int) $validated['cat_codificados_id']);
                $orden = trim((string) ($cat->OrdenTejido ?? ''));
                $catQuery = CatCodificados::query();
                $orden !== '' ? $catQuery->where('OrdenTejido', $orden) : $catQuery->whereKey($cat->getKey());
                $updated = $catQuery->update([
                    'IdRedbooth' => $taskId,
                    'NombreRedbooth' => $taskName,
                ]);
                if ($orden !== '') {
                    ReqProgramaTejido::query()->where('NoProduccion', $orden)->update([
                        'IdRedbooth' => $taskId,
                        'NombreRedbooth' => $taskName,
                    ]);
                }

                return $updated;
            }

            $programa = ReqProgramaTejido::query()
                ->lockForUpdate()
                ->findOrFail((int) $validated['req_programa_tejido_id']);
            $programa->update([
                'IdRedbooth' => $taskId,
                'NombreRedbooth' => $taskName,
            ]);

            $orden = trim((string) ($programa->NoProduccion ?? ''));
            if ($orden === '') {
                return 0;
            }

            return CatCodificados::query()
                ->where('OrdenTejido', $orden)
                ->update([
                    'IdRedbooth' => $taskId,
                    'NombreRedbooth' => $taskName,
                ]);
        });

        return response()->json([
            'success' => true,
            'idRedbooth' => $taskId,
            'nombreRedbooth' => $taskName,
            'catCodificadosActualizados' => $catCodificadosActualizados,
        ]);
    }

    public function show(Request $request, int $programa): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof Usuario, 401);

        $source = $request->string('source')->toString();
        $registro = $source === 'catcodificados'
            ? CatCodificados::query()->findOrFail($programa)
            : ReqProgramaTejido::query()->findOrFail($programa);
        $taskId = (int) ($registro->IdRedbooth ?? 0);
        if ($taskId <= 0) {
            return response()->json([
                'linked' => false,
                'programaId' => (int) $registro->getKey(),
            ]);
        }

        $task = collect($this->tasks($usuario))
            ->first(static fn (array $candidate): bool => (int) ($candidate['id'] ?? 0) === $taskId);

        if (! is_array($task)) {
            throw ValidationException::withMessages([
                'redbooth_task_id' => 'La tarea vinculada ya no está disponible en Redbooth.',
            ]);
        }

        $comments = $this->redbooth->comments($usuario, [
            'target_type' => 'Task',
            'target_id' => $taskId,
            'project_id' => self::PROJECT_ID,
            'order' => 'created_at-DESC',
            'per_page' => 1000,
            'page' => 1,
        ]);
        $users = $this->redbooth->users($usuario, [
            'project_id' => self::PROJECT_ID,
            'per_page' => 1000,
            'page' => 1,
        ]);
        $usersById = collect($users)
            ->filter(static fn (array $user): bool => (int) ($user['id'] ?? 0) > 0)
            ->keyBy(static fn (array $user): int => (int) $user['id']);
        $comments = collect($comments)->map(static function (array $comment) use ($usersById): array {
            $user = $usersById->get((int) ($comment['user_id'] ?? 0));
            if (! is_array($user)) {
                return $comment;
            }

            $fullName = trim((string) ($user['name'] ?? $user['full_name'] ?? ''));
            if ($fullName === '') {
                $fullName = trim(implode(' ', array_filter([
                    $user['first_name'] ?? null,
                    $user['last_name'] ?? null,
                ], static fn (mixed $part): bool => is_string($part) && trim($part) !== '')));
            }

            $comment['user'] = $user;
            $comment['user_name'] = $fullName !== ''
                ? $fullName
                : (string) ($user['username'] ?? $user['email'] ?? 'Usuario Redbooth');

            return $comment;
        })->values()->all();
        $files = $this->redbooth->files($usuario, [
            'project_id' => self::PROJECT_ID,
            'target_type' => 'Task',
            'target_id' => $taskId,
            'type' => 'file',
            'order' => 'created_at-ASC',
            'per_page' => 1000,
            'page' => 1,
        ]);

        $files = collect($files)->map(static function (array $file): array {
            $fileId = (int) ($file['id'] ?? 0);
            $file['download_url'] = $fileId > 0 && Route::has('redbooth.files.download')
                ? route('redbooth.files.download', ['fileId' => $fileId])
                : null;
            $file['is_image'] = str_starts_with(strtolower((string) ($file['mime_type'] ?? '')), 'image/');

            return $file;
        })->values()->all();
        $comments = collect($comments)->map(static function (array $comment) use ($files): array {
            $explicitIds = [];
            foreach (['file_ids', 'attachment_ids', 'attachments', 'files'] as $key) {
                foreach ((array) ($comment[$key] ?? []) as $attachment) {
                    $fileId = is_array($attachment)
                        ? (int) ($attachment['id'] ?? $attachment['file_id'] ?? 0)
                        : (int) $attachment;
                    if ($fileId > 0) {
                        $explicitIds[] = $fileId;
                    }
                }
            }

            $commentUserId = (int) ($comment['user_id'] ?? 0);
            $commentCreatedAt = (int) ($comment['created_at'] ?? 0);
            $comment['files'] = collect($files)
                ->filter(static function (array $file) use ($explicitIds, $commentUserId, $commentCreatedAt): bool {
                    $fileId = (int) ($file['id'] ?? 0);
                    if ($explicitIds !== []) {
                        return in_array($fileId, $explicitIds, true);
                    }

                    $fileUserId = (int) ($file['user_id'] ?? 0);
                    $fileCreatedAt = (int) ($file['created_at'] ?? 0);

                    return $commentUserId > 0
                        && $fileUserId === $commentUserId
                        && $commentCreatedAt > 0
                        && $fileCreatedAt > 0
                        && abs($fileCreatedAt - $commentCreatedAt) <= 120;
                })
                ->values()
                ->all();

            return $comment;
        })
            ->filter(static function (array $comment): bool {
                $body = trim((string) ($comment['body'] ?? $comment['comment'] ?? ''));
                $bodyHtml = (string) ($comment['body_html'] ?? '');
                $htmlText = trim(html_entity_decode(strip_tags($bodyHtml)));
                $hasInlineImage = stripos($bodyHtml, '<img') !== false;
                $hasFiles = ! empty($comment['files']);

                return $body !== '' || $htmlText !== '' || $hasInlineImage || $hasFiles;
            })
            ->sortByDesc(static fn (array $comment): int => (int) ($comment['created_at'] ?? 0))
            ->values()
            ->all();

        return response()->json([
            'linked' => true,
            'programaId' => (int) $registro->getKey(),
            'idRedbooth' => $taskId,
            'nombreRedbooth' => (string) $registro->NombreRedbooth,
            'task' => $task,
            'comments' => $comments,
            'files' => $files,
        ]);
    }

    public function destroy(Request $request, int $programa): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof Usuario, 401);

        $source = $request->string('source')->toString();
        $catCodificadosActualizados = DB::connection('sqlsrv')->transaction(function () use ($programa, $source): int {
            if ($source === 'catcodificados') {
                $cat = CatCodificados::query()->lockForUpdate()->findOrFail($programa);
                $orden = trim((string) ($cat->OrdenTejido ?? ''));
                $catQuery = CatCodificados::query();
                $orden !== '' ? $catQuery->where('OrdenTejido', $orden) : $catQuery->whereKey($cat->getKey());
                $updated = $catQuery->update([
                    'IdRedbooth' => null,
                    'NombreRedbooth' => null,
                ]);
                if ($orden !== '') {
                    ReqProgramaTejido::query()->where('NoProduccion', $orden)->update([
                        'IdRedbooth' => null,
                        'NombreRedbooth' => null,
                    ]);
                }

                return $updated;
            }

            $registro = ReqProgramaTejido::query()->lockForUpdate()->findOrFail($programa);
            $orden = trim((string) ($registro->NoProduccion ?? ''));
            $registro->update([
                'IdRedbooth' => null,
                'NombreRedbooth' => null,
            ]);

            if ($orden === '') {
                return 0;
            }

            return CatCodificados::query()
                ->where('OrdenTejido', $orden)
                ->update([
                    'IdRedbooth' => null,
                    'NombreRedbooth' => null,
                ]);
        });

        return response()->json([
            'success' => true,
            'catCodificadosActualizados' => $catCodificadosActualizados,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function tasks(Usuario $usuario): array
    {
        return $this->redbooth->tasks($usuario, [
            'project_id' => self::PROJECT_ID,
            'task_list_id' => self::TASK_LIST_ID,
            'order' => 'created_at-DESC',
            'per_page' => 1000,
            'page' => 1,
        ]);
    }
}
