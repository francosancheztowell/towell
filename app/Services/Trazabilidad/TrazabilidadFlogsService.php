<?php

namespace App\Services\Trazabilidad;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrazabilidadFlogsService
{
    private const TIPO_PEDIDO_LABELS = [
        '1' => 'Compra Especial',
        '2' => 'Stock',
    ];

    private const EMPRESA_LABELS = [
        '0' => 'TOWELL',
        '1' => 'TEXTIL',
    ];

    private const ESTADO_LINEA_LABELS = [
        '0' => 'Abierto',
        '1' => 'Facturado',
        '2' => 'Cancelado',
        '3' => 'Todo',
    ];

    private const CACHE_PREFIX = 'trazabilidad_flog_v1';

    // ponytail: 10 min de techo; el detalle de un Flog en AX cambia poco.
    private const CACHE_TTL = 600;

    /** Columnas de TwFlogsItemLine que consume mapearLineaFlog(). */
    private const COLUMNAS_LINEA = [
        'LINENUM', 'ESTADOLINEA', 'FECHACANCELACION', 'ITEMID', 'ITEMNAME', 'TIPOHILOID',
        'INVENTSIZEID', 'INVENTCOLORID', 'COLORNAME', 'RASURADOCRUDO', 'TIPODOBLADILLO',
        'TIPOCOSTURA', 'TIPOCORTEBATAID', 'VALORAGREGADO', 'PUNTADASBORDADO', 'INFOADICIONAL',
        'ANCHO', 'LARGO', 'PESOACABADO', 'DENSIDAD', 'INVENTQTY', 'FACTURADO', 'PORENTREGAR',
        'SALESUNIT', 'PURCHBARCODE', 'DUN14', 'RETAILLINK', 'NOMBREETIQUETA', 'CREATEDDATE',
        'SIMULACIONVTAS', 'SIMULACIONDISENO',
    ];

    // ponytail: con AX caído, cada cambio de filtro esperaba el timeout de conexión. Un minuto
    // sin reintentar: la tarjeta muestra "—" al instante y después vuelve a probar sola.
    private const AX_CAIDO_TTL = 60;

    private const FLOG_IMAGEN_UNC_ROOT = '\\\\192.168.2.11\\ImagenFlog\\';

    /**
     * @return array{
     *     estado: 'idle'|'not_found'|'error'|'ok',
     *     encontrado: bool,
     *     errorTipo: string|null,
     *     errorMensaje: string|null,
     *     general: array<string, mixed>,
     *     etiquetas: array<int, array<string, mixed>>,
     *     empaques: array<int, array<string, mixed>>,
     *     lineas: array<int, array<string, mixed>>
     * }
     */
    public function build(?string $idFlog): array
    {
        $idFlog = trim((string) $idFlog);
        if ($idFlog === '') {
            return $this->resultadoVacio('idle');
        }

        // ponytail: se cachean las filas de AX y no el arreglo armado, porque
        // resolverUrlImagen() usa route() con el host de la petición. Un cambio
        // en AX tarda hasta CACHE_TTL en verse; para forzarlo, olvidar($flog).
        $clave = $this->claveCache($idFlog);
        $datos = Cache::get($clave);

        if (! is_array($datos)) {
            try {
                $datos = $this->consultarAx($idFlog);
            } catch (Throwable $exception) {
                $tipo = $this->clasificarError($exception);

                Log::error('No se pudo consultar el Flog en TI.', [
                    'flog' => $idFlog,
                    'connection' => 'sqlsrv_ti',
                    'error_type' => $tipo,
                    'exception' => $exception::class,
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                ]);

                return $this->resultadoVacio('error', $tipo, $this->mensajeError($tipo));
            }

            // not_found no se cachea: el Flog puede darse de alta en AX en cualquier momento.
            if ($datos === null) {
                return $this->resultadoVacio('not_found');
            }

            Cache::put($clave, $datos, self::CACHE_TTL);
        }

        return [
            'estado' => 'ok',
            'encontrado' => true,
            'errorTipo' => null,
            'errorMensaje' => null,
            'general' => $this->armarGeneral($datos['cabecera'], $idFlog),
            'etiquetas' => array_map(fn (object $row) => [
                'itemId' => $this->txt($row->ITEMID ?? null),
                'name' => $this->txt($row->NAME ?? null),
                'comentarios' => $this->txt($row->COMENTARIOS ?? null),
                'imagenPath' => $this->txt($row->IMAGENETIQUETA ?? null),
                'imagenUrl' => $this->resolverUrlImagen($row->IMAGENETIQUETA ?? null),
            ], $datos['etiquetas']),
            'empaques' => array_map(fn (object $row) => [
                'idEmpaque' => $this->txt($row->IDEMPAQUE ?? null),
                'otroEmpaque' => $this->txt($row->OTROEMPAQUE ?? null),
                'imagenPath' => $this->txt($row->FILEOTROEMPAQUE ?? null),
                'imagenUrl' => $this->resolverUrlImagen($row->FILEOTROEMPAQUE ?? null),
            ], $datos['empaques']),
            'lineas' => array_map(fn (object $row) => $this->mapearLineaFlog($row), $datos['lineas']),
        ];
    }

    /**
     * Pedido, facturado y por entregar de AX (TwFlogsItemLine) para los Flogs del filtro.
     *
     * - Línea cancelada (ESTADOLINEA 2): solo cuenta lo que llegó a facturarse; no queda nada por entregar.
     * - PORENTREGAR es negativo cuando una línea se facturó de más. AX reparte la facturación
     *   entre líneas del mismo artículo/color sin orden fijo (F001399: línea 3 con -4,364 y la 18 en 0),
     *   así que se neta por producto (Flog + artículo + tamaño + color) y cada producto se topa en 0.
     *
     * @param  list<string>  $flogs
     *                               - Cancelado: lo que se canceló sin llegar a facturarse (no entra al pedido), y cuántas líneas.
     * @return array{pedido: float, facturado: float, porEntregar: float, cancelado: float, lineasCanceladas: int}|null null sin líneas o si AX falla.
     */
    public function facturacion(array $flogs, string $articulo = '', string $tamano = ''): ?array
    {
        $flogs = array_values(array_unique(array_filter(array_map('trim', $flogs))));
        if ($flogs === []) {
            return null;
        }
        sort($flogs);

        $clave = self::CACHE_PREFIX.'_fact2_'.app()->environment().'_'.md5(implode("\0", [...$flogs, '|', $articulo, $tamano]));

        $claveCaido = self::CACHE_PREFIX.'_ax_caido_'.app()->environment();
        if (Cache::has($claveCaido)) {
            return null;
        }

        try {
            // ponytail: mismo techo de 10 min que el detalle; olvidar() no limpia esta clave.
            return Cache::remember($clave, self::CACHE_TTL, function () use ($flogs, $articulo, $tamano): ?array {
                $totales = ['pedido' => 0.0, 'facturado' => 0.0, 'porEntregar' => 0.0, 'cancelado' => 0.0, 'lineasCanceladas' => 0];
                $lineas = 0;

                foreach (array_chunk($flogs, 1000) as $lote) {
                    // Neto por producto (Flog + artículo + tamaño + color): dentro del mismo producto
                    // lo facturado de más en una línea cubre a otra; entre productos distintos, no.
                    $porProducto = DB::connection('sqlsrv_ti')->table('dbo.TwFlogsItemLine')
                        ->selectRaw('COUNT(*) AS lineas,
                            SUM(CASE WHEN ESTADOLINEA = 2 THEN FACTURADO ELSE INVENTQTY END) AS pedido,
                            SUM(FACTURADO) AS facturado,
                            SUM(CASE WHEN ESTADOLINEA = 2 THEN 0 ELSE PORENTREGAR END) AS por_entregar,
                            SUM(CASE WHEN ESTADOLINEA = 2 AND INVENTQTY > FACTURADO THEN INVENTQTY - FACTURADO ELSE 0 END) AS cancelado,
                            SUM(CASE WHEN ESTADOLINEA = 2 THEN 1 ELSE 0 END) AS lineas_canceladas')
                        ->whereIn('IDFLOG', $lote)
                        ->when($articulo !== '', fn ($query) => $query->where('ITEMID', $articulo))
                        ->when($tamano !== '', fn ($query) => $query->where('INVENTSIZEID', $tamano))
                        ->groupBy('IDFLOG', 'ITEMID', 'INVENTSIZEID', 'INVENTCOLORID');

                    $fila = DB::connection('sqlsrv_ti')->query()->fromSub($porProducto, 'p')
                        ->selectRaw('SUM(lineas) AS lineas, SUM(pedido) AS pedido, SUM(facturado) AS facturado,
                            SUM(CASE WHEN por_entregar > 0 THEN por_entregar ELSE 0 END) AS por_entregar,
                            SUM(cancelado) AS cancelado, SUM(lineas_canceladas) AS lineas_canceladas')
                        ->first();

                    $lineas += (int) ($fila->lineas ?? 0);
                    $totales['pedido'] += (float) ($fila->pedido ?? 0);
                    $totales['facturado'] += (float) ($fila->facturado ?? 0);
                    $totales['porEntregar'] += (float) ($fila->por_entregar ?? 0);
                    $totales['cancelado'] += (float) ($fila->cancelado ?? 0);
                    $totales['lineasCanceladas'] += (int) ($fila->lineas_canceladas ?? 0);
                }

                return $lineas > 0 ? $totales : null;
            });
        } catch (Throwable $exception) {
            // La tarjeta muestra "—": que AX no responda no puede tirar la pantalla.
            Cache::put($claveCaido, true, self::AX_CAIDO_TTL);
            Log::warning('No se pudo consultar la facturación del Flog en TI.', [
                'flogs' => count($flogs),
                'error_type' => $this->clasificarError($exception),
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    /** Descarta la caché de un Flog (p. ej. tras corregirlo en AX). */
    public function olvidar(string $idFlog): void
    {
        Cache::forget($this->claveCache(trim($idFlog)));
    }

    private function claveCache(string $idFlog): string
    {
        // Incluye APP_ENV para que local y producción no compartan caché (mismo criterio que ModuloService).
        return self::CACHE_PREFIX.'_'.app()->environment().'_'.md5($idFlog);
    }

    /**
     * Filas del Flog en AX, o null si no existe. Las columnas IDFLOG son NVARCHAR
     * con intercalación Windows (Modern_Spanish_CI_AS_WS): el parámetro NVARCHAR
     * de pdo_sqlsrv no anula índices, así que no hace falta CAST.
     *
     * @return array{cabecera: object, etiquetas: list<object>, empaques: list<object>, lineas: list<object>}|null
     */
    private function consultarAx(string $idFlog): ?array
    {
        $conn = DB::connection('sqlsrv_ti');

        // TwFlogsCustomer es 1:1 con TwFlogsTable: una sola ida a AX para la cabecera.
        // Las columnas del cliente que repiten nombre con la tabla llevan prefijo C_.
        $cabecera = $conn->table('dbo.TwFlogsTable as t')
            ->leftJoin('dbo.TwFlogsCustomer as c', 'c.IDFLOG', '=', 't.IDFLOG')
            ->where('t.IDFLOG', $idFlog)
            ->select([
                't.IDFLOG', 't.TIPOPEDIDO', 't.NAMEPROYECT', 't.EMPRESA', 't.TRANSDATE',
                't.CUSTACCOUNT', 't.CUSTNAME', 't.NUMPROVEEDOR',
                'c.IDFLOG as C_IDFLOG', 'c.CUSTACCOUNT as C_CUSTACCOUNT', 'c.CUSTNAME as C_CUSTNAME',
                'c.NUMPROVEEDOR as C_NUMPROVEEDOR', 'c.CAGENTE', 'c.NAGENTE', 'c.PRUEBALABID',
                'c.PRUEBASLAB', 'c.PRUEBASLABTXT', 'c.TIPOCLIENTEID', 'c.CATEGORIACALIDAD',
                'c.PROCESOCATMEX', 'c.TWSUAVIZANTE', 'c.SUAVISANTEEXPTXT', 'c.AVISOESPECIALTXT',
                'c.INFOIMPORTANTE',
            ])
            ->first();

        if (! $cabecera) {
            return null;
        }

        return [
            'cabecera' => $cabecera,
            'etiquetas' => $conn->table('dbo.TwFlogsEtiquetasLinea')
                ->select(['ITEMID', 'NAME', 'COMENTARIOS', 'IMAGENETIQUETA'])
                ->where('IDFLOG', $idFlog)
                ->orderBy('LINENUM')
                ->get()->all(),
            'empaques' => $conn->table('dbo.TwBomEmpaque')
                ->select(['IDEMPAQUE', 'OTROEMPAQUE', 'FILEOTROEMPAQUE'])
                ->where('IDFLOG', $idFlog)
                ->orderBy('RECID')
                ->get()->all(),
            'lineas' => $conn->table('dbo.TwFlogsItemLine')
                ->select(self::COLUMNAS_LINEA)
                ->where('IDFLOG', $idFlog)
                ->orderBy('LINENUM')
                ->get()->all(),
        ];
    }

    /** @return array<string, string> */
    private function armarGeneral(object $cab, string $idFlog): array
    {
        // Sin fila en TwFlogsCustomer, C_IDFLOG llega null: equivale al antiguo $cliente === null.
        $hayCliente = ($cab->C_IDFLOG ?? null) !== null;
        $c = fn (string $col): mixed => $hayCliente ? ($cab->{$col} ?? null) : null;

        $custAccount = $this->txt($c('C_CUSTACCOUNT') ?? $cab->CUSTACCOUNT ?? null);
        $custName = $this->txt($c('C_CUSTNAME') ?? $cab->CUSTNAME ?? null);
        $cAgente = $this->txt($c('CAGENTE'));
        $nAgente = $this->txt($c('NAGENTE'));

        $pruebaLabIdRaw = $c('PRUEBALABID') ?? $c('PRUEBASLAB');
        $pruebasLabTxt = $this->txt($c('PRUEBASLABTXT'));

        return [
            'idFlog' => $this->txt($cab->IDFLOG ?? $idFlog),
            'tipoPedido' => $this->resolverTipoPedido($cab->TIPOPEDIDO ?? null, $idFlog),
            'nameProyect' => $this->txt($cab->NAMEPROYECT ?? null),
            'empresa' => $this->txt($cab->EMPRESA ?? null),
            'empresaLabel' => $this->resolverEmpresa($cab->EMPRESA ?? null),
            'transDate' => $this->formatearFecha($cab->TRANSDATE ?? null),
            'custAccount' => $custAccount,
            'custName' => $custName,
            'cliente' => trim($custAccount.' '.$custName),
            'numProveedor' => $this->txt($c('C_NUMPROVEEDOR') ?? $cab->NUMPROVEEDOR ?? null),
            'tipoClienteId' => $this->txt($c('TIPOCLIENTEID')),
            'categoriaCalidad' => $this->txt($c('CATEGORIACALIDAD')),
            'procesoCatMex' => $this->formatearSiNo($c('PROCESOCATMEX')),
            'cAgente' => $cAgente,
            'nAgente' => $nAgente,
            'agente' => $this->unirConSeparador([$cAgente, $nAgente], ' — '),
            'pruebaLabId' => $this->txt($pruebaLabIdRaw),
            'pruebasLabTxt' => $pruebasLabTxt,
            'pruebasLab' => $this->formatearPruebasLab($pruebaLabIdRaw, $pruebasLabTxt),
            'twSuavizante' => $this->resolverSuavizante($c('TWSUAVIZANTE'), $c('SUAVISANTEEXPTXT')),
            'avisoEspecialTxt' => $this->txt($c('AVISOESPECIALTXT')),
            'infoImportante' => $this->txt($c('INFOIMPORTANTE')),
        ];
    }

    /** @return array<string, mixed> */
    private function resultadoVacio(string $estado, ?string $errorTipo = null, ?string $errorMensaje = null): array
    {
        return [
            'estado' => $estado,
            'encontrado' => false,
            'errorTipo' => $errorTipo,
            'errorMensaje' => $errorMensaje,
            'general' => [],
            'etiquetas' => [],
            'empaques' => [],
            'lineas' => [],
        ];
    }

    private function clasificarError(Throwable $exception): string
    {
        $mensaje = strtolower($exception->getMessage());

        return match (true) {
            str_contains($mensaje, 'login failed'), str_contains($mensaje, 'authentication') => 'authentication',
            str_contains($mensaje, 'timeout'), str_contains($mensaje, 'timed out') => 'timeout',
            str_contains($mensaje, 'invalid object'), str_contains($mensaje, 'invalid column') => 'schema',
            str_contains($mensaje, 'server was not found'),
            str_contains($mensaje, 'network-related'),
            str_contains($mensaje, 'could not open a connection') => 'connection',
            default => 'query',
        };
    }

    private function mensajeError(string $tipo): string
    {
        return match ($tipo) {
            'authentication' => 'TI rechazó las credenciales de conexión.',
            'timeout' => 'TI tardó demasiado en responder.',
            'schema' => 'La estructura de datos de TI no coincide con la esperada.',
            'connection' => 'No fue posible establecer conexión con el servidor de TI.',
            default => 'TI respondió con un error al consultar el Flog.',
        };
    }

    /**
     * @return array<string, string>
     */
    private function mapearLineaFlog(object $row): array
    {
        return [
            'lineNum' => $this->formatearEntero($row->LINENUM ?? null),
            'estadoLinea' => $this->resolverEstadoLinea($row->ESTADOLINEA ?? null),
            'estadoLineaCodigo' => $this->codigoEstadoLinea($row->ESTADOLINEA ?? null),
            'fechaCancelacion' => $this->formatearFecha($row->FECHACANCELACION ?? null),
            'itemId' => $this->txt($row->ITEMID ?? null),
            'itemName' => $this->txt($row->ITEMNAME ?? null),
            'tipoHiloId' => $this->txt($row->TIPOHILOID ?? null),
            'inventSizeId' => $this->txt($row->INVENTSIZEID ?? null),
            'inventColorId' => $this->txt($row->INVENTCOLORID ?? null),
            'colorName' => $this->txt($row->COLORNAME ?? null),
            'rasuradoCrudo' => $this->formatearSiNo($row->RASURADOCRUDO ?? null),
            'tipoDobladillo' => $this->txt($row->TIPODOBLADILLO ?? null),
            'tipoCostura' => $this->txt($row->TIPOCOSTURA ?? null),
            'tipoCorteBataId' => $this->txt($row->TIPOCORTEBATAID ?? null),
            'valorAgregado' => $this->txt($row->VALORAGREGADO ?? null),
            'puntadasBordado' => $this->formatearDecimal3($row->PUNTADASBORDADO ?? null),
            'infoAdicional' => $this->txt($row->INFOADICIONAL ?? null),
            'ancho' => $this->formatearEntero($row->ANCHO ?? null),
            'largo' => $this->formatearEntero($row->LARGO ?? null),
            'pesoAcabado' => $this->formatearEntero($row->PESOACABADO ?? null),
            'densidad' => $this->formatearDecimal3($row->DENSIDAD ?? null),
            'inventQty' => $this->formatearEntero($row->INVENTQTY ?? null),
            'facturado' => $this->formatearEntero($row->FACTURADO ?? null),
            'porEntregar' => $this->formatearEntero($row->PORENTREGAR ?? null),
            'salesUnit' => $this->txt($row->SALESUNIT ?? null),
            'purchBarCode' => $this->txt($row->PURCHBARCODE ?? null),
            'dun14' => $this->txt($row->DUN14 ?? null),
            'retailLink' => $this->txt($row->RETAILLINK ?? null),
            'nombreEtiqueta' => $this->txt($row->NOMBREETIQUETA ?? null),
            'createdDate' => $this->formatearFecha($row->CREATEDDATE ?? null),
            'simulacionVtasUrl' => $this->resolverUrlImagen($row->SIMULACIONVTAS ?? null) ?? '',
            'simulacionDisenoUrl' => $this->resolverUrlImagen($row->SIMULACIONDISENO ?? null) ?? '',
        ];
    }

    public function resolverUrlImagen(?string $ruta): ?string
    {
        $ruta = trim((string) $ruta);
        if ($ruta === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $ruta)) {
            return $ruta;
        }

        $normalizada = str_replace('/', '\\', $ruta);
        $base = rtrim(self::FLOG_IMAGEN_UNC_ROOT, '\\').'\\';

        if (stripos($normalizada, $base) === 0) {
            $archivo = basename($normalizada);

            return $archivo !== ''
              ? route('trazabilidad.flog-archivo', ['file' => $archivo])
              : null;
        }

        $archivo = basename($normalizada);

        return $archivo !== '' && preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $archivo)
          ? route('trazabilidad.flog-archivo', ['file' => $archivo])
          : null;
    }

    public function rutaAbsolutaImagen(string $archivo): ?string
    {
        $archivo = basename($archivo);
        if ($archivo === '' || ! preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $archivo)) {
            return null;
        }

        $ruta = self::FLOG_IMAGEN_UNC_ROOT.$archivo;

        return is_file($ruta) ? $ruta : null;
    }

    private function codigoEstadoLinea(mixed $estado): string
    {
        if ($estado === null || $estado === '') {
            return '';
        }

        return is_numeric($estado)
            ? (string) (int) $estado
            : trim((string) $estado);
    }

    private function resolverEstadoLinea(mixed $estado): string
    {
        $codigo = $this->codigoEstadoLinea($estado);
        if ($codigo === '') {
            return '—';
        }

        if (isset(self::ESTADO_LINEA_LABELS[$codigo])) {
            return self::ESTADO_LINEA_LABELS[$codigo];
        }

        return $codigo;
    }

    private function resolverEmpresa(mixed $empresa): string
    {
        $codigo = trim((string) $empresa);
        if ($codigo === '') {
            return '—';
        }

        if (isset(self::EMPRESA_LABELS[$codigo])) {
            return self::EMPRESA_LABELS[$codigo];
        }

        $upper = strtoupper($codigo);
        if (in_array($upper, ['TOWELL', 'TEXTIL'], true)) {
            return $upper;
        }

        return $codigo;
    }

    private function resolverTipoPedido(mixed $tipoPedido, string $idFlog): string
    {
        $codigo = trim((string) $tipoPedido);
        if ($codigo !== '' && isset(self::TIPO_PEDIDO_LABELS[$codigo])) {
            return self::TIPO_PEDIDO_LABELS[$codigo];
        }

        if (strlen($idFlog) >= 2) {
            $pref = strtoupper(substr($idFlog, 0, 2));
            if ($pref === 'CE') {
                return 'Compra Especial';
            }
        }

        return $codigo !== '' ? $codigo : '—';
    }

    private function resolverSuavizante(mixed $codigo, mixed $texto): string
    {
        $texto = $this->txt($texto);
        if ($texto !== '') {
            return $texto;
        }

        $codigo = trim((string) $codigo);
        if ($codigo === '' || $codigo === '0' || $codigo === '3') {
            return 'Ninguno';
        }

        return $codigo;
    }

    private function formatearPruebasLab(mixed $flag, mixed $texto): string
    {
        $texto = $this->txt($texto);
        $flag = trim((string) $flag);

        if ($texto === '' && ($flag === '' || $flag === '0')) {
            return '—';
        }

        if ($texto !== '') {
            $tienePrueba = $flag !== '' && $flag !== '0';

            return ($tienePrueba ? 'Sí — ' : '').$texto;
        }

        return $this->formatearSiNo($flag);
    }

    private function formatearSiNo(mixed $valor): string
    {
        $v = trim((string) $valor);
        if ($v === '' || $v === '0') {
            return 'No';
        }

        return in_array(strtolower($v), ['1', 'si', 'sí', 'yes', 'true'], true) ? 'Sí' : $v;
    }

    private function formatearEntero(mixed $valor): string
    {
        if (blank($valor) && $valor !== 0 && $valor !== '0') {
            return '—';
        }

        $normalizado = str_replace(',', '.', trim((string) $valor));
        if ($normalizado === '' || ! is_numeric($normalizado)) {
            return $this->txt($valor) ?: '—';
        }

        return (string) (int) $normalizado;
    }

    private function formatearDecimal3(mixed $valor): string
    {
        if (blank($valor) && $valor !== 0 && $valor !== '0') {
            return '—';
        }

        $normalizado = str_replace(',', '.', trim((string) $valor));
        if ($normalizado === '' || ! is_numeric($normalizado)) {
            return $this->txt($valor) ?: '—';
        }

        return number_format((float) $normalizado, 3, '.', '');
    }

    private function formatearFecha(mixed $fecha): string
    {
        if (blank($fecha)) {
            return '—';
        }

        try {
            return Carbon::parse($fecha)->timezone('America/Mexico_City')->format('d/m/Y');
        } catch (Throwable) {
            return (string) $fecha;
        }
    }

    private function unirConSeparador(array $partes, string $sep): string
    {
        $limpias = array_values(array_filter(array_map(fn ($p) => $this->txt($p), $partes)));

        return $limpias !== [] ? implode($sep, $limpias) : '—';
    }

    private function txt(mixed $valor): string
    {
        return trim((string) ($valor ?? ''));
    }
}
