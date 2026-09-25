<?php

namespace App\Http\Controllers\Monitoreo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoreo\TelemetriaRequest;
use App\Services\Monitoreo\CierreRemotoService;
use App\Services\Monitoreo\DispositivoService;
use App\Services\Monitoreo\ErrorRecorder;
use App\Services\Monitoreo\Monitoreo;
use App\Services\Monitoreo\SesionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Endpoints cliente → servidor (contrato §4). Responden rápido y nunca 500: ante
 * cualquier fallo interno, 204 y log. Con el kill switch apagado, 204 sin tocar BD.
 */
class TelemetriaController extends Controller
{
    /** Tiempo visible de una pantalla: hasta 24 h (un andón pasa el turno completo abierto). */
    private const VISIBLE_MS_MAX = 86400000;

    public function __construct(
        private readonly DispositivoService $dispositivos,
        private readonly CierreRemotoService $cierres,
    ) {}

    public function latido(TelemetriaRequest $request): JsonResponse|Response
    {
        if (! Monitoreo::activo()) {
            return response()->noContent();
        }

        $uuid = DispositivoService::uuid($request);
        $visible = $request->booleano('visible');

        Monitoreo::seguro('latido', function () use ($request, $uuid, $visible): void {
            if ($uuid === null) {
                return;
            }

            $pantalla = $request->texto('pantalla', 20);
            $datos = [
                'UltimaActividad' => now(),
                'UltimaIp' => Monitoreo::ip($request),
                'UltimoUsuarioId' => (int) Auth::id(),
                'Visible' => $visible,
                'InactivoSeg' => $request->entero('inactivoSeg', 0, 86400) ?? 0,
            ];
            if (($ruta = $request->ruta()) !== null) {
                $datos['UltimaRuta'] = $ruta;
            }
            if (($version = $request->texto('version', 40)) !== null) {
                $datos['VersionFront'] = $version;
            }
            if ($pantalla !== null && preg_match('/^\d{2,5}x\d{2,5}$/', $pantalla)) {
                $datos['Pantalla'] = $pantalla;
            }

            if ($this->dispositivos->actualizar($uuid, $datos) === 0) {
                $this->dispositivos->idPorUuid($uuid, $request);
                $this->dispositivos->actualizar($uuid, $datos);
            }

            $sesionId = $request->session()->get(SesionService::LLAVE_SESION);
            if (is_numeric($sesionId)) {
                DB::connection('sqlsrv')->table('SYSMonSesion')
                    ->where('Id', (int) $sesionId)->whereNull('Fin')
                    ->update(['UltimaActividad' => now()]);
            }
        });

        $intervalos = (array) config('monitoreo.latido_seg');

        return response()->json([
            'cerrar' => (bool) Monitoreo::seguro('consultar cierre remoto', fn () => $this->cierres->pendiente($uuid), false),
            'intervalo' => (int) ($visible ? ($intervalos['visible'] ?? 60) : ($intervalos['oculta'] ?? 300)),
        ]);
    }

    public function vista(TelemetriaRequest $request): Response
    {
        if (! Monitoreo::activo()) {
            return response()->noContent();
        }

        Monitoreo::seguro('registrar vista', function () use ($request): void {
            $uuid = $request->input('uuid');
            $dispositivoId = $this->dispositivos->idPorUuid(DispositivoService::uuid($request), $request);
            if (! is_string($uuid) || ! Str::isUuid($uuid) || $dispositivoId === null) {
                return;
            }

            $sesionId = $request->session()->get(SesionService::LLAVE_SESION);

            try {
                DB::connection('sqlsrv')->table('SYSMonVista')->insert([
                    'Uuid' => strtolower($uuid),
                    'SesionId' => is_numeric($sesionId) ? (int) $sesionId : null,
                    'DispositivoId' => $dispositivoId,
                    'UsuarioId' => (int) Auth::id(),
                    'Ruta' => $request->ruta() ?? 'desconocida',
                    'Url' => $request->soloPath('url', 300) ?? '/',
                    'Tipo' => $request->input('tipo') === 'suave' ? 'suave' : 'carga',
                    'Inicio' => now(),
                    'TtfbMs' => $request->entero('nav.ttfb'),
                    'DomMs' => $request->entero('nav.dom'),
                    'CargaMs' => $request->entero('nav.carga'),
                    'Kb' => $request->entero('nav.kb', 0, 1000000),
                    'ServidorMs' => $request->entero('st.app'),
                    'ConsultasMs' => $request->entero('st.db'),
                    'ConsultasN' => $request->entero('st.q', 0, 100000),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Reintento del cliente con el mismo uuid: idempotente.
            }
        });

        return response()->noContent();
    }

    public function vistaFin(TelemetriaRequest $request, string $uuid): Response
    {
        if (! Monitoreo::activo() || ! Str::isUuid($uuid)) {
            return response()->noContent();
        }

        Monitoreo::seguro('cerrar vista', function () use ($request, $uuid): void {
            DB::connection('sqlsrv')->table('SYSMonVista')
                ->where('Uuid', strtolower($uuid))
                ->where('UsuarioId', (int) Auth::id())
                ->whereNull('Fin')
                ->update(['Fin' => now(), 'VisibleMs' => $request->entero('visibleMs', 0, self::VISIBLE_MS_MAX)]);
        });

        return response()->noContent();
    }

    public function error(TelemetriaRequest $request, ErrorRecorder $errores): Response
    {
        if (! Monitoreo::activo()) {
            return response()->noContent();
        }

        Monitoreo::seguro('registrar error de cliente', function () use ($request, $errores): void {
            $llave = 'mon:err-cliente:'.(DispositivoService::uuid($request) ?? $request->ip());
            $maximo = (int) config('monitoreo.errores.throttle_cliente_min', 30);

            // Excedente: se descarta en silencio (el cliente no reintenta).
            if (RateLimiter::tooManyAttempts($llave, $maximo)) {
                return;
            }
            RateLimiter::hit($llave, 60);

            $origen = $request->input('origen');

            $errores->capturarCliente([
                'origen' => in_array($origen, ['js', 'livewire', 'red'], true) ? $origen : 'js',
                'mensaje' => $request->texto('mensaje', 1000) ?? '',
                'fuente' => $request->soloPath('fuente', 300),
                'linea' => $request->entero('linea', 0, 10000000),
                'stack' => $request->texto('stack', ErrorRecorder::TRAZA_MAX),
                'status' => $request->entero('status', 0, 999),
                'metodo' => $request->texto('metodo', 8),
                'url' => $request->soloPath('url', 300),
                'version' => $request->texto('version', 40),
                'ruta' => $this->rutaDelError($request),
            ]);
        });

        return response()->noContent();
    }

    /**
     * Ruta de la PÁGINA donde ocurrió el error (HANDOFF 12 §1), nunca la de este endpoint:
     * la que manda el cliente (meta `towell-ruta`); si no, la de su vista; si no, 'desconocida'.
     */
    private function rutaDelError(TelemetriaRequest $request): string
    {
        $ruta = $request->ruta();
        $vista = $request->input('vista');

        if ($ruta === null && is_string($vista) && Str::isUuid($vista)) {
            $ruta = Monitoreo::texto(DB::connection('sqlsrv')->table('SYSMonVista')
                ->where('Uuid', strtolower($vista))
                ->where('UsuarioId', (int) Auth::id())
                ->value('Ruta'), 150);
        }

        return $ruta ?? 'desconocida';
    }

    public function nombre(TelemetriaRequest $request): Response
    {
        if (! Monitoreo::activo()) {
            return response()->noContent();
        }

        Monitoreo::seguro('nombrar dispositivo', function () use ($request): void {
            $uuid = DispositivoService::uuid($request);
            if ($uuid === null) {
                return;
            }

            $datos = ['Nombre' => $request->texto('nombre', 80)];
            if ($this->dispositivos->actualizar($uuid, $datos) === 0) {
                $this->dispositivos->idPorUuid($uuid, $request);
                $this->dispositivos->actualizar($uuid, $datos);
            }
        });

        return response()->noContent();
    }
}
