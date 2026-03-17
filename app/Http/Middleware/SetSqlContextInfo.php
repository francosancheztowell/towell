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
     * para que los triggers puedan capturar informacion del usuario.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            try {
                $connection = DB::connection();

                // En pruebas usamos sqlite en memoria para simular sqlsrv.
                // El EXEC solo aplica cuando la conexion real es SQL Server.
                if ($connection->getDriverName() !== 'sqlsrv') {
                    return $next($request);
                }

                $uid = (int) Auth::id();
                $user = substr((string) (Auth::user()->nombre ?? Auth::user()->numero_empleado ?? 'Sistema'), 0, 120);
                $ip = substr((string) $request->ip(), 0, 64);

                $connection->statement('EXEC dbo.sp_SetAppContext ?, ?, ?', [$uid, $user, $ip]);
            } catch (\Throwable $e) {
                Log::warning('SetSqlContextInfo: No se pudo establecer contexto', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $next($request);
    }
}
