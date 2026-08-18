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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Se sella SIEMPRE, tambien sin sesion. CONTEXT_INFO vive en la conexion fisica y el
        // driver ODBC las reutiliza entre peticiones: si una peticion anonima no lo reescribe,
        // hereda el contexto del ultimo usuario que uso esa conexion y el trigger de auditoria
        // le atribuye la escritura a esa persona. Una fila sin autor es un hueco; una fila con
        // el autor equivocado es una mentira.
        try {
            $connection = DB::connection();

            // En pruebas usamos sqlite en memoria para simular sqlsrv.
            // El EXEC solo aplica cuando la conexion real es SQL Server.
            if ($connection->getDriverName() !== 'sqlsrv') {
                return $next($request);
            }

            $usuario = Auth::user();
            $uid = $usuario ? (int) $usuario->getKey() : null;
            $user = $usuario
                ? substr((string) ($usuario->nombre ?? $usuario->numero_empleado ?? 'Sistema'), 0, 120)
                : null;
            $ip = substr((string) $request->ip(), 0, 64);

            // Sin @Accion: cada peticion arranca sin contexto de negocio, y es
            // AuditoriaHelper::contexto() quien lo sella antes de cada operacion.
            $connection->statement('EXEC dbo.sp_SetAppContext ?, ?, ?', [$uid, $user, $ip]);
        } catch (\Throwable $e) {
            Log::warning('SetSqlContextInfo: No se pudo establecer contexto', [
                'error' => $e->getMessage(),
            ]);
        }

        return $next($request);
    }
}
