<?php

namespace App\Services\Monitoreo;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Registra errores agrupados por huella en SYSMonError + SYSMonErrorEvento (contrato §2).
 *
 * - Escribe por la conexión `sqlsrv_monitoreo`: un error dentro de una transacción
 *   de negocio queda registrado aunque esa transacción se revierta.
 * - Nunca lanza y nunca entra en recursión (candado estático).
 * - Nunca guarda valores del input: solo los NOMBRES de las llaves. La traza se
 *   arma con archivo:línea y función, sin argumentos.
 */
class ErrorRecorder
{
    public const TRAZA_MAX = 8192;

    private static bool $capturando = false;

    public function __construct(
        private readonly EstadoRequest $estado,
        private readonly ErrorTelegramNotifier $notificador,
    ) {}

    /** Excepción PHP reportada por el handler. Devuelve el Id del evento (o null). */
    public function capturar(Throwable $e): ?int
    {
        if ($this->debeIgnorar($e)) {
            return null;
        }

        return $this->conCandado(function () use ($e): ?int {
            $mensaje = $this->mensajeDe($e);
            $archivo = $this->rutaRelativa($e->getFile());
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return $this->registrar([
                'Origen' => 'php',
                'Clase' => $e::class,
                'Mensaje' => $mensaje,
                'Archivo' => $archivo,
                'Linea' => $e->getLine(),
            ], $status, $this->traza($e));
        });
    }

    /**
     * Respuesta >= 500 que no pasó por el handler (los catch que devuelven
     * response()->json(['message' => $e->getMessage()], 500)).
     */
    public function capturarRespuesta(Request $request, Response $response): ?int
    {
        return $this->conCandado(function () use ($request, $response): ?int {
            $status = $response->getStatusCode();
            $mensaje = $this->mensajeDeRespuesta($response);

            // El mensaje viene de $e->getMessage() en un catch: puede traer valores.
            // Se guarda normalizado (sin números, comillas, uuids ni rutas).
            return $this->registrar([
                'Origen' => 'http5xx',
                'Clase' => 'HTTP '.$status,
                'Mensaje' => $mensaje === '' ? 'Respuesta '.$status.' sin mensaje' : self::normalizar($mensaje),
                'Archivo' => null,
                'Linea' => null,
            ], $status, $this->llavesInput($request));
        });
    }

    /**
     * Error reportado por el cliente (telemetría). $datos ya viene recortado.
     *
     * @param  array{origen: string, mensaje: string, fuente: ?string, linea: ?int, stack: ?string, status: ?int, metodo: ?string, url: ?string, version: ?string}  $datos
     */
    public function capturarCliente(array $datos): ?int
    {
        return $this->conCandado(function () use ($datos): ?int {
            $mensaje = $datos['mensaje'] !== '' ? $datos['mensaje'] : 'Error sin mensaje';
            $clase = match (true) {
                $datos['origen'] === 'red' => 'HTTP '.($datos['status'] ?? 0),
                (bool) preg_match('/^([A-Z][A-Za-z]{2,40}(?:Error|Exception)):/', $mensaje, $m) => $m[1],
                default => 'Error',
            };

            return $this->registrar([
                'Origen' => $datos['origen'],
                'Clase' => $clase,
                'Mensaje' => $datos['origen'] === 'red' ? self::normalizar($mensaje) : $mensaje,
                'Archivo' => $datos['fuente'],
                'Linea' => $datos['linea'],
            ], $datos['status'], (string) ($datos['stack'] ?? ''), [
                'Url' => $datos['url'],
                'Metodo' => $datos['metodo'],
                'VersionFront' => $datos['version'],
            ]);
        });
    }

    /**
     * Huella (contrato §2): sha1(origen|clase|archivo|línea|mensaje normalizado).
     * Para http5xx el "archivo" es la ruta, así que agrupa por ruta|status|mensaje.
     */
    public static function huella(string $origen, string $clase, ?string $archivo, ?int $linea, string $mensaje): string
    {
        return sha1(implode('|', [$origen, $clase, (string) $archivo, (string) $linea, self::normalizar($mensaje)]));
    }

    /** Quita uuids, rutas absolutas, valores entre comillas y números. */
    public static function normalizar(string $mensaje): string
    {
        $m = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '{uuid}', $mensaje) ?? $mensaje;
        $m = preg_replace('#(?:[A-Za-z]:)?(?:[\\\\/][\w.\-]+){2,}#', '{ruta}', $m) ?? $m;
        $m = preg_replace('/"[^"]*"|\'[^\']*\'/', '?', $m) ?? $m;
        $m = preg_replace('/\d+(?:[.,]\d+)*/', 'N', $m) ?? $m;

        return trim(preg_replace('/\s+/', ' ', $m) ?? $m);
    }

    private function debeIgnorar(Throwable $e): bool
    {
        if (! Monitoreo::activo()) {
            return true;
        }

        foreach ((array) config('monitoreo.errores.ignorar', []) as $clase) {
            if (is_string($clase) && $e instanceof $clase) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  callable(): ?int  $fn
     */
    private function conCandado(callable $fn): ?int
    {
        if (self::$capturando || ! Monitoreo::activo()) {
            return null;
        }

        self::$capturando = true;
        // Aunque falle el registro, el 5xx ya no se debe volver a contar.
        $this->estado->errorReportado = true;

        try {
            return $fn();
        } catch (Throwable $falla) {
            try {
                Log::warning('Monitoreo: no se pudo registrar un error.', [
                    'exception' => $falla::class,
                    'message' => mb_substr($falla->getMessage(), 0, 500),
                ]);
            } catch (Throwable) {
            }

            return null;
        } finally {
            self::$capturando = false;
        }
    }

    /**
     * @param  array{Origen: string, Clase: string, Mensaje: string, Archivo: ?string, Linea: ?int}  $error
     * @param  array{Url?: ?string, Metodo?: ?string, VersionFront?: ?string}  $eventoExtra
     */
    private function registrar(array $error, ?int $status, string $traza, array $eventoExtra = []): ?int
    {
        $request = app()->bound('request') ? request() : null;
        $ruta = $this->rutaDe($request);

        $error['Clase'] = mb_substr($error['Clase'], 0, 200);
        $error['Mensaje'] = mb_substr($error['Mensaje'], 0, 1000);
        $error['Archivo'] = Monitoreo::texto($error['Archivo'], 300);

        $huella = self::huella(
            $error['Origen'],
            $error['Clase'],
            $error['Origen'] === 'http5xx' ? $ruta : $error['Archivo'],
            $error['Origen'] === 'http5xx' ? $status : $error['Linea'],
            $error['Mensaje'],
        );

        $db = DB::connection(Monitoreo::conexionErrores());
        $ahora = now();

        [$errorId, $alertar] = $this->upsert($db, $huella, $error + [
            'Ruta' => Monitoreo::texto($ruta, 150),
            'Estado' => 'nuevo',
            'Ocurrencias' => 1,
            'PrimeraVez' => $ahora,
            'UltimaVez' => $ahora,
        ]);

        $this->estado->errorId = $errorId;

        $eventoId = null;
        if ($this->dentroDelTope($errorId)) {
            $eventoId = (int) $db->table('SYSMonErrorEvento')->insertGetId([
                'ErrorId' => $errorId,
                'Fecha' => $ahora,
                'UsuarioId' => Auth::hasUser() ? (int) Auth::id() : null,
                'DispositivoId' => $request ? DispositivoService::idEnCache(DispositivoService::uuid($request)) : null,
                'SesionId' => $request?->hasSession() ? $request->session()->get(SesionService::LLAVE_SESION) : null,
                'Url' => Monitoreo::texto($eventoExtra['Url'] ?? ($request ? '/'.ltrim($request->path(), '/') : null), 300),
                'Metodo' => Monitoreo::texto($eventoExtra['Metodo'] ?? $request?->method(), 8),
                'Status' => $status,
                'VersionFront' => Monitoreo::texto($eventoExtra['VersionFront'] ?? null, 40),
                'Traza' => mb_strcut($traza, 0, self::TRAZA_MAX) ?: null,
            ], 'Id');
            $this->estado->eventoId = $eventoId;
        }

        if ($alertar) {
            $this->notificador->programar($errorId);
        }

        return $eventoId;
    }

    /**
     * Inserta o suma una ocurrencia. Devuelve [id, alertar]; alertar = huella nueva o regresión.
     *
     * @return array{0: int, 1: bool}
     */
    private function upsert($db, string $huella, array $fila): array
    {
        $existente = $db->table('SYSMonError')->where('Huella', $huella)->first(['Id', 'Estado']);

        if ($existente === null) {
            try {
                return [(int) $db->table('SYSMonError')->insertGetId(['Huella' => $huella] + $fila, 'Id'), true];
            } catch (UniqueConstraintViolationException) {
                // Otra request insertó la misma huella al mismo tiempo.
                $existente = $db->table('SYSMonError')->where('Huella', $huella)->first(['Id', 'Estado']);
            }
        }

        $regresion = $existente->Estado === 'resuelto';
        $cambios = ['Ocurrencias' => $db->raw('Ocurrencias + 1'), 'UltimaVez' => $fila['UltimaVez']];
        if ($regresion) {
            $cambios['Estado'] = 'nuevo';
        }

        $db->table('SYSMonError')->where('Id', $existente->Id)->update($cambios);

        return [(int) $existente->Id, $regresion];
    }

    /** Máximo max_eventos_dia eventos por huella y día; Ocurrencias siempre suma. */
    private function dentroDelTope(int $errorId): bool
    {
        $llave = 'mon:err:ev:'.$errorId.':'.now()->format('Ymd');
        Cache::add($llave, 0, now()->addDay());

        return (int) Cache::increment($llave) <= (int) config('monitoreo.errores.max_eventos_dia', 50);
    }

    private function mensajeDe(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            // Solo SQL con placeholders: getMessage() trae los bindings interpolados.
            return 'SQLSTATE['.$e->getCode().'] '.$e->getSql();
        }

        $mensaje = $e->getMessage();
        // Otras excepciones de BD pegan "(Connection: x, SQL: ...)" con valores.
        $corte = mb_strpos($mensaje, ' (Connection:');

        return $corte === false ? $mensaje : mb_substr($mensaje, 0, $corte);
    }

    private function mensajeDeRespuesta(Response $response): string
    {
        $contenido = (string) $response->getContent();
        $json = json_decode($contenido, true);

        if (is_array($json)) {
            $mensaje = $json['message'] ?? $json['error'] ?? $json['mensaje'] ?? '';

            return is_string($mensaje) ? mb_substr(trim($mensaje), 0, 1000) : '';
        }

        return '';
    }

    private function traza(Throwable $e): string
    {
        $lineas = [$e::class.' @ '.$this->rutaRelativa($e->getFile()).':'.$e->getLine()];

        foreach (array_slice($e->getTrace(), 0, 40) as $i => $frame) {
            $lineas[] = sprintf(
                '#%d %s:%s %s%s%s()',
                $i,
                $this->rutaRelativa($frame['file'] ?? '[internal]'),
                $frame['line'] ?? '?',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? '',
            );
        }

        if ($e->getPrevious() !== null) {
            $lineas[] = 'Causa: '.$e->getPrevious()::class;
        }

        $request = app()->bound('request') ? request() : null;

        return implode("\n", $lineas)."\n".($request ? $this->llavesInput($request) : '');
    }

    /** Solo los NOMBRES de las llaves del input, nunca sus valores. */
    private function llavesInput(Request $request): string
    {
        $llaves = array_slice(array_keys(Arr::dot($request->except(['_token']))), 0, 60);

        return $llaves === [] ? '' : 'Input: '.implode(', ', $llaves);
    }

    private function rutaDe(?Request $request): ?string
    {
        $ruta = $request?->route();
        if (! is_object($ruta)) {
            return null;
        }

        return $ruta->getName() ?? $ruta->uri();
    }

    private function rutaRelativa(string $archivo): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_replace('\\', '/', str_starts_with($archivo, $base) ? substr($archivo, strlen($base)) : $archivo);
    }
}
