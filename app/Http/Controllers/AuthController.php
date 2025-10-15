<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;  // Asegúrate de importar el FormRequest
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('login');
    }

    public function login(LoginRequest $request)
    {
        // La validación se aplica automáticamente
        $validated = $request->validated();

        // Buscar el empleado en la base de datos
        $empleado = Usuario::where('numero_empleado', $request->numero_empleado)->first();

        $passwordOk = false;
        if ($empleado) {
            // Verificar si la contraseña está hasheada o en texto plano
            $storedPassword = $empleado->contrasenia;

            // Si la contraseña almacenada parece ser un hash (empieza con $2y$)
            if (str_starts_with($storedPassword, '$2y$')) {
                $passwordOk = Hash::check($request->contrasenia, $storedPassword);
            } else {
                // Si está en texto plano, comparar directamente
                $passwordOk = $request->contrasenia === $storedPassword;
            }
        }

        if ($empleado && $passwordOk) {
            // Contraseña correcta, realizar login
            Auth::login($empleado);
            session()->flash('bienvenida', true);
            return redirect()->intended('/produccionProceso');
        }

        // ==============================================================
        // TEMPORAL: BYPASS DE LOGIN
        // Permite entrar solo con número de empleado aunque la contraseña no
        // coincida. Úsese mientras se ajusta/migra la autenticación.
        // IMPORTANTE: quitar este bloque cuando el login quede estable.
        // ==============================================================
        if ($empleado) {
            Auth::login($empleado);
            session()->flash('bienvenida', true);
            session()->flash('login_bypass', true); // indicador de bypass
            return redirect()->intended('/produccionProceso');
        }

        // Si no se encuentran las credenciales, devolver error
        return back()->with('error', 'Su contraseña está incorrecta');
    }

    public function loginQR(Request $request)
    {
        $request->validate([
            'numero_empleado' => 'required|exists:sqlsrv_ti.SYSUsuario,numero_empleado'
        ]);

        // Buscar el usuario en la BD
        $empleado = Usuario::where('numero_empleado', $request->numero_empleado)->first();

        if ($empleado) {
            // Iniciar sesión sin contraseña
            Auth::login($empleado);
            session()->flash('bienvenida', true);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Empleado no encontrado'], 401);
    }

    public function logout()
    {
        Auth::logout();
        return redirect('/');
    }
}
