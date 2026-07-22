<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integraciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integraciones\RedboothActivitiesRequest;
use App\Http\Requests\Integraciones\RedboothCommentsRequest;
use App\Http\Requests\Integraciones\RedboothFilesRequest;
use App\Http\Requests\Integraciones\RedboothTasksRequest;
use App\Models\Integraciones\RedboothCredential;
use App\Models\Sistema\Usuario;
use App\Services\Integraciones\RedboothService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class RedboothController extends Controller
{
    public function __construct(
        private readonly RedboothService $redbooth,
    ) {}

    public function connect(Request $request): RedirectResponse
    {
        $state = Str::random(64);
        $usuarioId = (int) $this->usuario($request)->getKey();
        $request->session()->put('redbooth_oauth_state', $state);
        Cache::put(
            $this->stateCacheKey($state),
            $usuarioId,
            now()->addMinutes(10),
        );
        Cache::put(
            $this->pendingUserCacheKey($usuarioId),
            hash('sha256', $state),
            now()->addMinutes(10),
        );

        return redirect()->away($this->redbooth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull('redbooth_oauth_state', '');
        $receivedState = (string) $request->query('state', '');
        $usuario = $this->usuario($request);
        $usuarioId = (int) $usuario->getKey();
        $cachedUserId = $receivedState !== ''
            ? (int) Cache::pull($this->stateCacheKey($receivedState), 0)
            : 0;
        $pendingStateHash = (string) Cache::pull($this->pendingUserCacheKey($usuarioId), '');
        $validSessionState = $expectedState !== '' && hash_equals($expectedState, $receivedState);
        $validCachedState = $cachedUserId > 0 && $cachedUserId === $usuarioId;
        $validPendingAuthorization = $pendingStateHash !== ''
            && ($receivedState === '' || hash_equals($pendingStateHash, hash('sha256', $receivedState)));

        abort_unless(
            $validSessionState || $validCachedState || $validPendingAuthorization,
            403,
            'La respuesta OAuth de Redbooth no es válida.',
        );

        if ($request->filled('error')) {
            return redirect()
                ->route('redbooth.status')
                ->with('error', 'Redbooth rechazó la autorización.');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:2048'],
        ]);

        $this->redbooth->exchangeAuthorizationCode(
            $usuario,
            $validated['code'],
        );

        return redirect()
            ->route('redbooth.status')
            ->with('success', 'Redbooth se conectó correctamente.');
    }

    public function status(Request $request): JsonResponse
    {
        $credential = RedboothCredential::query()
            ->where('usuario_id', (int) $this->usuario($request)->getKey())
            ->first();

        return response()->json([
            'connected' => $credential !== null,
            'expires_at' => $credential?->expires_at?->toIso8601String(),
            'connect_url' => route('redbooth.connect'),
            'test_url' => route('redbooth.me'),
            'message' => session('success') ?? session('error'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->redbooth->me($this->usuario($request)));
    }

    public function activities(RedboothActivitiesRequest $request): JsonResponse
    {
        return response()->json($this->redbooth->activities(
            $this->usuario($request),
            $request->validated(),
        ));
    }

    public function tasks(RedboothTasksRequest $request): JsonResponse
    {
        return response()->json($this->redbooth->tasks(
            $this->usuario($request),
            $request->validated(),
        ));
    }

    public function comments(RedboothCommentsRequest $request): JsonResponse
    {
        return response()->json($this->redbooth->comments(
            $this->usuario($request),
            $request->validated(),
        ));
    }

    public function files(RedboothFilesRequest $request): JsonResponse
    {
        return response()->json($this->redbooth->files(
            $this->usuario($request),
            $request->validated(),
        ));
    }

    public function images(RedboothFilesRequest $request): JsonResponse
    {
        $files = $this->redbooth->files(
            $this->usuario($request),
            $request->validated(),
        );

        return response()->json(array_values(array_filter(
            $files,
            static fn (array $file): bool => str_starts_with(
                strtolower((string) ($file['mime_type'] ?? '')),
                'image/',
            ),
        )));
    }

    public function download(Request $request, int $fileId): RedirectResponse
    {
        return redirect()->away($this->redbooth->fileDownloadUrl(
            $this->usuario($request),
            $fileId,
        ));
    }

    public function disconnect(Request $request): JsonResponse
    {
        $this->redbooth->disconnect($this->usuario($request));

        return response()->json(['message' => 'La conexión local con Redbooth fue eliminada.']);
    }

    private function usuario(Request $request): Usuario
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof Usuario, 401);

        return $usuario;
    }

    private function stateCacheKey(string $state): string
    {
        return 'redbooth-oauth-state:'.hash('sha256', $state);
    }

    private function pendingUserCacheKey(int $usuarioId): string
    {
        return "redbooth-oauth-pending-user:{$usuarioId}";
    }
}
