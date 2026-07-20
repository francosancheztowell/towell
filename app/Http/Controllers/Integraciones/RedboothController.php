<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integraciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integraciones\RedboothActivitiesRequest;
use App\Models\Integraciones\RedboothCredential;
use App\Models\Sistema\Usuario;
use App\Services\Integraciones\RedboothService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RedboothController extends Controller
{
    public function __construct(
        private readonly RedboothService $redbooth,
    ) {}

    public function connect(Request $request): RedirectResponse
    {
        $state = Str::random(64);
        $request->session()->put('redbooth_oauth_state', $state);

        return redirect()->away($this->redbooth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = (string) $request->session()->pull('redbooth_oauth_state', '');
        $receivedState = (string) $request->query('state', '');

        abort_unless(
            $expectedState !== '' && hash_equals($expectedState, $receivedState),
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
            $this->usuario($request),
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
}
