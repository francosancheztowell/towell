<?php

namespace App\Services\Monitoreo;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Identidad estable del dispositivo (cookie towell_disp) y su renglón en SYSMonDispositivo.
 *
 * Todas las escrituras van por query builder y dentro de Monitoreo::seguro(): un
 * fallo aquí nunca rompe la request.
 */
class DispositivoService
{
    public const COOKIE = 'towell_disp';

    /** Atributo de request con el uuid resuelto (existe aunque la cookie apenas se vaya a poner). */
    public const ATRIBUTO = 'mon_disp_uuid';

    private const TABLA = 'SYSMonDispositivo';

    private const COOKIE_MINUTOS = 60 * 24 * 365 * 5;

    /** Uuid del dispositivo de la request, o null si no trae cookie válida. */
    public static function uuid(Request $request): ?string
    {
        $uuid = $request->attributes->get(self::ATRIBUTO) ?? $request->cookie(self::COOKIE);

        return is_string($uuid) && Str::isUuid($uuid) ? strtolower($uuid) : null;
    }

    /**
     * Garantiza un uuid en la request. Devuelve [uuid, esNuevo].
     *
     * @return array{0: string, 1: bool}
     */
    public function asegurarUuid(Request $request): array
    {
        $uuid = self::uuid($request);
        $nuevo = $uuid === null;
        $uuid ??= (string) Str::uuid();

        $request->attributes->set(self::ATRIBUTO, $uuid);

        return [$uuid, $nuevo];
    }

    public function cookie(string $uuid, Request $request): Cookie
    {
        return Cookie::create(
            self::COOKIE,
            $uuid,
            now()->addMinutes(self::COOKIE_MINUTOS),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    /**
     * Id del dispositivo; lo crea si no existe y $crear. Cacheado por uuid.
     */
    public function idPorUuid(?string $uuid, Request $request, bool $crear = true): ?int
    {
        if ($uuid === null) {
            return null;
        }

        return Monitoreo::seguro('resolver dispositivo', function () use ($uuid, $request, $crear): ?int {
            $clave = 'mon:disp:id:'.$uuid;
            $id = Cache::get($clave);
            if (is_int($id)) {
                return $id;
            }

            $id = DB::connection('sqlsrv')->table(self::TABLA)->where('Uuid', $uuid)->value('Id');

            if ($id === null && $crear) {
                $id = $this->crear($uuid, $request);
            }

            if ($id === null) {
                return null;
            }

            Cache::put($clave, (int) $id, now()->addDay());

            return (int) $id;
        });
    }

    /**
     * Actualiza la actividad del dispositivo y de la sesión de monitoreo como
     * máximo una vez cada touch_cache_seg por dispositivo (candado Cache::add).
     */
    public function touch(Request $request, int $usuarioId, ?string $ruta): void
    {
        $uuid = self::uuid($request);
        if ($uuid === null) {
            return;
        }

        Monitoreo::seguro('touch de dispositivo', function () use ($request, $uuid, $usuarioId, $ruta): void {
            $segundos = max(1, (int) config('monitoreo.touch_cache_seg', 30));
            if (! Cache::add('mon:touch:'.$uuid, 1, $segundos)) {
                return;
            }

            $ahora = now();
            $sesionId = $request->hasSession() ? $request->session()->get(SesionService::LLAVE_SESION) : null;

            $datos = [
                'UltimaActividad' => $ahora,
                'UltimaIp' => mb_substr(getClientIpv4(), 0, 45),
                'UltimoUsuarioId' => $usuarioId,
            ];
            if ($ruta !== null) {
                $datos['UltimaRuta'] = mb_substr($ruta, 0, 150);
            }

            $actualizados = DB::connection('sqlsrv')->table(self::TABLA)->where('Uuid', $uuid)->update($datos);

            if ($actualizados === 0) {
                $this->idPorUuid($uuid, $request);
                DB::connection('sqlsrv')->table(self::TABLA)->where('Uuid', $uuid)->update($datos);
            }

            if (is_numeric($sesionId)) {
                DB::connection('sqlsrv')->table('SYSMonSesion')
                    ->where('Id', (int) $sesionId)
                    ->whereNull('Fin')
                    ->update(['UltimaActividad' => $ahora]);
            }
        });
    }

    /**
     * Actualiza columnas del dispositivo por uuid (latido, nombre). Devuelve filas afectadas.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(string $uuid, array $datos): int
    {
        return (int) DB::connection('sqlsrv')->table(self::TABLA)->where('Uuid', $uuid)->update($datos);
    }

    private function crear(string $uuid, Request $request): ?int
    {
        $ua = (string) ($request->userAgent() ?? '');
        $tipo = detectDeviceType($ua);
        $navegador = detectBrowser($ua);
        $so = detectOS($ua);
        $ahora = now();

        try {
            return (int) DB::connection('sqlsrv')->table(self::TABLA)->insertGetId([
                'Uuid' => $uuid,
                'Tipo' => match ($tipo['tipo'] ?? null) {
                    'tablet' => 'tablet',
                    'mobile' => 'movil',
                    'desktop' => 'pc',
                    default => 'desconocido',
                },
                'Modelo' => Monitoreo::texto(detectDeviceModel($ua), 80),
                'SO' => Monitoreo::texto(trim(($so['nombre'] ?? '').' '.($so['version'] ?? '')), 60),
                'Navegador' => Monitoreo::texto(trim(($navegador['nombre'] ?? '').' '.($navegador['version'] ?? '')), 60),
                'UaHash' => sha1($ua),
                'UltimaIp' => mb_substr(getClientIpv4(), 0, 45),
                'PrimeraVez' => $ahora,
                'UltimaActividad' => $ahora,
                'Visible' => true,
                'InactivoSeg' => 0,
            ], 'Id');
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Dos requests simultáneas del mismo dispositivo nuevo: la otra ganó.
            $id = DB::connection('sqlsrv')->table(self::TABLA)->where('Uuid', $uuid)->value('Id');

            return $id === null ? null : (int) $id;
        }
    }
}
