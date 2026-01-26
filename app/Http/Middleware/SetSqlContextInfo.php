<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetSqlContextInfo
{
    /**
     * Handle an incoming request.
     * Establece el contexto de SQL Server antes de ejecutar queries
     * para que los triggers puedan capturar información del usuario
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            try {
                $uid = (int) Auth::id();
                $user = substr((string) (Auth::user()->nombre ?? Auth::user()->numero_empleado ?? 'Sistema'), 0, 120);
                $ip = substr((string) $request->ip(), 0, 64);

                // Ejecutar stored procedure para establecer el contexto
                // Esto se ejecuta en la MISMA conexión antes de tus queries
                DB::statement("EXEC dbo.sp_SetAppContext ?, ?, ?", [$uid, $user, $ip]);
            } catch (\Throwable $e) {
                // Si falla el stored procedure, continuar sin contexto
                // (por si no existe aún en la BD)
                Log::warning('SetSqlContextInfo: No se pudo establecer contexto', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $next($request);
    }
}
