<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\Sistema\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect('/produccionProceso');
        }

        return view('login');
    }

    public function login(LoginRequest $request)
    {
        $request->validated();

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

            Auth::login($empleado);
            $request->session()->regenerate();
            session()->flash('bienvenida', true);

            return redirect()->intended('/produccionProceso');
        }

        return back()->with('error', 'Credenciales incorrectas. Verifica tu numero de empleado y contrasenia.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
