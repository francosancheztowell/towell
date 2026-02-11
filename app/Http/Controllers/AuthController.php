<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;  // Asegúrate de importar el FormRequest
use Illuminate\Support\Facades\Auth;
use App\Models\Sistema\Usuario;
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
        $request->validated();

        // Buscar el empleado en la base de datos
        $empleado = Usuario::query()
            ->select(['idusuario', 'numero_empleado', 'nombre', 'contrasenia'])
            ->where('numero_empleado', $request->numero_empleado)
            ->first();

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
            // Contraseña correcta, realizar login (seguro)
            Auth::login($empleado);
            $request->session()->regenerate();
            session()->flash('bienvenida', true);
            return redirect()->intended('/produccionProceso');
        }

        // Credenciales inválidas
        return back()->with('error', 'Credenciales incorrectas. Verifica tu número de empleado y contraseña.');
    }

    public function loginQR(Request $request)
    {
        try {
            $request->validate([
                'numero_empleado' => 'required|string'
            ]);

            // Buscar el usuario en la BD
            $empleado = Usuario::where('numero_empleado', $request->numero_empleado)->first();

            if ($empleado) {
                // Iniciar sesión sin contraseña
                Auth::login($empleado);
                $request->session()->regenerate();
                session()->flash('bienvenida', true);

                return response()->json([
                    'success' => true,
                    'message' => 'Login exitoso',
                    'user' => $empleado->nombre
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Empleado no encontrado: ' . $request->numero_empleado
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function logout()
    {
        Auth::logout();
        return redirect('/');
    }
}
