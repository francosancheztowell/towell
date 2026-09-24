<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\Sistema\Usuario;
use App\Services\Monitoreo\AccesoService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect('/produccionProceso');
        }

        return view('login');
    }

    public function login(LoginRequest $request, AccesoService $accesos)
    {
        $request->validated();

        // Rate limit ANTES de revisar la contraseña (MON-04). Límites en MonitoreoServiceProvider.
        $limites = $this->limitesLogin($request);
        foreach ($limites as $limite) {
            if (RateLimiter::tooManyAttempts($limite->key, $limite->maxAttempts)) {
                $segundos = RateLimiter::availableIn($limite->key);
                $accesos->registrar('bloqueo', [
                    'NumeroEmpleado' => $request->numero_empleado,
                    'Motivo' => 'rate_limit',
                ]);

                return back()->with('error', "Demasiados intentos, espera {$segundos} segundos.");
            }
        }

        $empleado = Usuario::query()
            ->select(['idusuario', 'numero_empleado', 'nombre', 'contrasenia'])
            ->where('numero_empleado', $request->numero_empleado)
            ->first();

        $passwordOk = false;
        $needsLegacyRehash = false;

        if ($empleado) {
            $storedPassword = (string) $empleado->contrasenia;

            if (str_starts_with($storedPassword, '$2y$')) {
                $passwordOk = Hash::check($request->contrasenia, $storedPassword);

                if ($passwordOk && Hash::needsRehash($storedPassword)) {
                    $empleado->contrasenia = Hash::make($request->contrasenia);
                    $empleado->save();
                }
            } else {
                $passwordOk = hash_equals($storedPassword, (string) $request->contrasenia);
                $needsLegacyRehash = $passwordOk;
            }
        }

        if ($empleado && $passwordOk) {
            if ($needsLegacyRehash) {
                $empleado->contrasenia = Hash::make($request->contrasenia);
                $empleado->save();
            }

            RateLimiter::clear($limites[0]->key);

            // ponytail: "recordar" siempre activo. Las pantallas de andón corren
            // sin nadie que las atienda; si la sesión muere, la cookie de
            // remember las reautentica sola en vez de dejar un login en pantalla.
            // Si algún día hace falta distinguir kiosco de PC, pasar un booleano.
            Auth::login($empleado, true);
            $request->session()->regenerate();
            session()->flash('bienvenida', true);

            return redirect()->intended('/produccionProceso');
        }

        foreach ($limites as $limite) {
            RateLimiter::hit($limite->key, $limite->decaySeconds);
        }
        $accesos->registrar('login_fallido', [
            'NumeroEmpleado' => $request->numero_empleado,
            'UsuarioId' => $empleado?->idusuario,
            'Motivo' => $empleado ? 'contrasena' : 'usuario_inexistente',
        ]);

        return back()->with('error', 'Credenciales incorrectas. Verifica tu numero de empleado y contrasenia.');
    }

    public function logout(Request $request)
    {
        // Solo este dispositivo: Auth::logout() rotaría el remember_token y sacaría
        // a todas las tablets del mismo usuario (decisión 2026-09-24).
        Auth::logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Límites del limiter `login` con llave propia (el primero es empleado+IP).
     *
     * @return list<Limit>
     */
    private function limitesLogin(Request $request): array
    {
        $limites = RateLimiter::limiter('login')($request);

        return array_map(static function (Limit $limite): Limit {
            $limite->key = 'login|'.$limite->key;

            return $limite;
        }, array_values(is_array($limites) ? $limites : [$limites]));
    }
}
