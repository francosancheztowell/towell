<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Inventario\InvTelasReservadas;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Tejedores\TejNotificaTejedorModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Servicio encargado de gestionar el inventario disponible y sus reservas.
 * Se conecta a la base de datos externa del ERP (TI-PRO) para consultar el inventario físico disponible 
 * y lo fusiona con el estado local de reservas en la base de datos interna.
 */
class InventarioReservasService
{
    /** Parámetros de conexión a la base de datos externa (TI-PRO) */
    private const TI_CONN     = 'sqlsrv_ti';
    private const DATAAREA    = 'PRO';
    private const LOC_TELA    = 'A-JUL/TELA';
    private const LIMIT_TI    = 2000;

    /** Patrones de búsqueda de Items para distinguir entre tipo Rizo y Pie */
    private const PATTERN_RIZO = '%JU-ENG-RI%';
    private const PATTERN_PIE  = '%JU-ENG-PI%';

    /** Columnas permitidas para filtrar en las peticiones del frontend */
    public const ALLOWED_FILTERS = [
        'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId', 'InventLocationId',
        'InventBatchId', 'WMSLocationId', 'InventSerialId', 'Tipo',
        'InventQty', 'Metros', 'ProdDate', 'NoTelarId',
    ];

    /** Mapeo de los campos del frontend a las columnas reales en la consulta SQL de TI-PRO */
    private const FILTER_SQL = [
        'ItemId'           => 's.ItemId',
        'ConfigId'         => 'd.ConfigId',
        'InventSizeId'     => 'd.InventSizeId',
        'InventColorId'    => 'd.InventColorId',
        'InventLocationId' => 'd.InventLocationId',
        'InventBatchId'    => 'd.InventBatchId',
        'WMSLocationId'    => 'd.WMSLocationId',
        'InventSerialId'   => 'd.InventSerialId',
        'InventQty'        => 'ISNULL(s.PhysicalInvent,0)',
        'Metros'           => 'ISNULL(ser.TwMts,0)',
        'ProdDate'         => 'ser.ProdDate',
    ];

    /**
     * Normaliza los filtros que llegan desde la petición (querystring/body).
     * Asegura que el array de salida tenga siempre el formato estricto: [['columna' => '...', 'valor' => '...']]
     *
     * @param mixed $raw Filtros en formato crudo.
     * @return array Filtros estructurados.
     */
    public function normalizeFilters($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $filter) {
            if (is_array($filter) && isset($filter['columna'], $filter['valor'])) {
                $normalized[] = [
                    'columna' => (string) $filter['columna'], 
                    'valor' => (string) $filter['valor']
                ];
            }
        }
        return $normalized;
    }

    /**
     * Limpia y normaliza un valor que formará parte de la clave dimensional (dimKey).
     * Convierte nulos o strings 'null' en cadenas vacías para mantener consistencia.
     */
    public function normalizeDimValue($value): string
    {
        if ($value === null || in_array($value, ['null', 'NULL'], true)) {
            return '';
        }
        return trim((string) $value);
    }

    /**
     * Genera una clave única (dimKey) para una pieza en base a sus dimensiones.
     * Esta clave es fundamental para cruzar (hacer match) el inventario físico de TI-PRO con las reservas locales.
     *
     * @param array|object $obj Objeto o array con los datos de la pieza.
     * @return string Clave concatenada con '|'.
     */
    public function dimKey($obj): string
    {
        $fields = [
            'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId',
            'InventLocationId', 'InventBatchId', 'WMSLocationId', 'InventSerialId',
        ];

        $values = [];
        foreach ($fields as $field) {
            $value = is_array($obj) ? ($obj[$field] ?? null) : ($obj->$field ?? null);
            $values[] = $this->normalizeDimValue($value);
        }

        return implode('|', $values);
    }

    /**
     * Obtiene el inventario disponible consultando TI-PRO y lo fusiona con las reservas locales activas.
     * Es el core de la vista de inventario.
     *
     * @param array $filtros Filtros normalizados a aplicar.
     * @return array Resultado con el formato ['data' => array, 'total' => int].
     */
    public function getDisponibleData(array $filtros): array
    {
        $filtroNoTelarId = null;
        $filtrosTi = [];

        // 1. Separar el filtro local de 'NoTelarId' de los filtros que se envían por query a TI-PRO
        foreach ($filtros as $f) {
            if (($f['columna'] ?? '') === 'NoTelarId') {
                $filtroNoTelarId = trim($f['valor'] ?? '');
            } else {
                $filtrosTi[] = $f;
            }
        }

        // 2. Obtener reservas locales activas y armar un mapa de acceso rápido (por dimKey)
        $reservadasMap = [];

        InvTelasReservadas::query()
            ->where('Status', 'Reservado')
            ->select([
                'Id', 'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId',
                'InventLocationId', 'InventBatchId', 'WMSLocationId', 'InventSerialId',
                'NoTelarId', 'Tipo', 'Metros', 'InventQty', 'ProdDate', 'SalonTejidoId',
            ])
            ->get()
            ->each(function ($reserva) use (&$reservadasMap) {
                $key = $this->dimKey($reserva);
                $reservadasMap[$key] = $reserva;
            });

        // 3. Consultar a la base de datos externa (TI-PRO). 
        // Esta es nuestra base principal: SI NO ESTÁ EN TI-PRO, NO SE MUESTRA.
        $rowsTi = $this->queryDisponibleFromTiPro($filtrosTi, self::LIMIT_TI);
        $resultados = [];

        // Determinar si el usuario pide ver "sólo los disponibles" (es decir, los que NO tienen reserva)
        $wantOnlyAvailable = false;
        if ($filtroNoTelarId !== null && $filtroNoTelarId !== '') {
            $v = mb_strtolower($filtroNoTelarId, 'UTF-8');
            $wantOnlyAvailable = in_array($v, ['null', 'vacío', 'vacio', 'disponible'], true);
        }

        // 4. Procesar resultados de TI-PRO: cruzar (left join lógico) con reservas locales
        foreach ($rowsTi as $row) {
            $rowKey = $this->dimKey($row);
            
            // Si la pieza de TI-PRO está reservada localmente, le inyectamos los datos de la reserva
            if (isset($reservadasMap[$rowKey])) {
                $reserva = $reservadasMap[$rowKey];
                $row->NoTelarId = $reserva->NoTelarId;
                $row->ReservaId = $reserva->Id;
                $row->SalonTejidoId = $reserva->SalonTejidoId;
            } else {
                $row->NoTelarId = null;
                $row->ReservaId = null;
                $row->SalonTejidoId = null;
            }

            // Filtrar localmente por NoTelarId si fue solicitado
            if ($this->shouldExcludeByTelarFilter($row->NoTelarId, $filtroNoTelarId, $wantOnlyAvailable)) {
                continue;
            }
            
            $resultados[] = $row;
        }

        return ['data' => $resultados, 'total' => count($resultados)];
    }

    /**
     * Determina si un registro debe excluirse basado en la búsqueda del Telar.
     */
    private function shouldExcludeByTelarFilter(?string $noTelarId, ?string $filtroNoTelarId, bool $wantOnlyAvailable): bool
    {
        if ($filtroNoTelarId === null || $filtroNoTelarId === '') {
            return false;
        }

        if ($wantOnlyAvailable) {
            // Si queremos solo disponibles, excluye si tiene un telar asignado (es decir, está reservado)
            return ($noTelarId !== null && $noTelarId !== '');
        }

        // Búsqueda por coincidencia de texto (LIKE) en el número de telar
        return stripos((string) ($noTelarId ?? ''), $filtroNoTelarId) === false;
    }

    /**
     * Obtiene todas las reservas activas asociadas a un número de telar específico.
     */
    public function getReservasPorTelar(string $noTelar)
    {
        return InvTelasReservadas::where('NoTelarId', $noTelar)
            ->where('Status', 'Reservado')
            ->orderByDesc('Id')
            ->get()
            ->map(function ($r) {
                $r->dimKey = $this->dimKey($r);
                return $r;
            });
    }

    /** 
     * Herramienta de diagnóstico: Muestra las reservas más recientes (con su dimKey calculada).
     */
    public function getDiagnosticoReservas(?string $noTelar, int $limit): \Illuminate\Support\Collection
    {
        $query = InvTelasReservadas::where('Status', 'Reservado')->orderByDesc('Id');
        if ($noTelar !== null && $noTelar !== '') {
            $query->where('NoTelarId', $noTelar);
        }
        return $query->limit($limit)->get();
    }

    /**
     * Flujo principal para reservar una pieza: 
     * 1. Genera el registro de la reserva.
     * 2. Consume notificaciones de tejedor si aplican.
     * 3. Actualiza el inventario de telares marcándolo como reservado.
     *
     * @param array $data Datos validados de la reserva.
     * @return array ['created' => bool, 'message' => string]
     */
    public function ejecutarReserva(array $data): array
    {
        $created = false;
        $msg = 'Pieza reservada correctamente.';

        // Derivar el InventBatchId a partir del prefijo de InventSerialId (ej. '00061-744' -> '00061')
        $serialId = trim((string) ($data['InventSerialId'] ?? ''));
        if ($serialId !== '' && strpos($serialId, '-') !== false) {
            $prefijo = trim(explode('-', $serialId)[0] ?? '');
            if ($prefijo !== '') {
                $data['InventBatchId'] = $prefijo;
            }
        }

        // Regla de negocio: consumir notificaciones (avisos de los tejedores) previas a la reserva
        $this->aplicarReglaNotificaTejedorAntesDeReservar($data);

        try {
            InvTelasReservadas::create($data);
            $created = true;
        } catch (\Illuminate\Database\QueryException $qe) {
            // Códigos SQL Server 2601 y 2627 indican violación de índice único. Se ignora como "duplicado".
            if (!in_array($qe->getCode(), [2601, 2627], true)) {
                throw $qe; 
            }
            $msg = 'La pieza ya estaba reservada (se evitó el duplicado).';
        }

        // Actualizar el estado 'Reservado' y atributos dimensionales en el catálogo de telares
        $tejInventarioTelaresId = $data['TejInventarioTelaresId'] ?? null;
        if ($tejInventarioTelaresId) {
            $this->actualizarEstadoTelarTrasReserva((int) $tejInventarioTelaresId, $data);
        }

        return ['created' => $created, 'message' => $msg];
    }

    /**
     * Actualiza la información (ConfigId, LoteProveedor, etc.) y la bandera "Reservado" en un Telar.
     */
    private function actualizarEstadoTelarTrasReserva(int $telarId, array $data): void
    {
        try {
            $telar = TejInventarioTelares::where('id', $telarId)->where('status', 'Activo')->first();
            
            if (!$telar) {
                Log::warning('ReservaInventario: No se encontró telar activo para actualizar', [
                    'tej_inventario_telares_id' => $telarId,
                ]);
                return;
            }

            $telar->Reservado = true;
            
            if (isset($data['ConfigId'])) {
                $telar->ConfigId = $this->normalizeDimValue($data['ConfigId']);
            }
            if (isset($data['InventSizeId'])) {
                $telar->InventSizeId = $this->normalizeDimValue($data['InventSizeId']);
            }
            if (isset($data['InventColorId'])) {
                $telar->InventColorId = $this->normalizeDimValue($data['InventColorId']);
            }
            if (array_key_exists('InventBatchId', $data)) {
                $telar->LoteProveedor = $this->normalizeDimValue($data['InventBatchId']);
            }
            if (!empty($data['NoProveedor'])) {
                $telar->NoProveedor = $this->normalizeDimValue($data['NoProveedor']);
            }
            
            $telar->save();

        } catch (Throwable $e) {
            Log::warning('Error al actualizar estatus de reservado en el telar', [
                'tej_inventario_telares_id' => $telarId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Regla de negocio compleja:
     * Si el tejedor reportó una falta/paro en este telar/tipo (Rizo o Pie), tomamos la hora de esa 
     * notificación, se la pasamos al telar (`horaParo`) para estadísticas, y cerramos la notificación.
     */
    private function aplicarReglaNotificaTejedorAntesDeReservar(array $data): void
    {
        $noTelar = trim((string) ($data['NoTelarId'] ?? ''));
        $tipo = $this->resolverTipoReserva($data);

        if ($noTelar === '' || $tipo === null) {
            return;
        }

        // 1. Buscar una notificación de tejedor pendiente de asignar reserva
        $pendiente = TejNotificaTejedorModel::query()
            ->whereRaw('LTRIM(RTRIM(telar)) = ?', [$noTelar])
            ->whereRaw('LOWER(LTRIM(RTRIM(tipo))) = ?', [mb_strtolower($tipo, 'UTF-8')])
            ->where(function ($q) {
                $q->whereNull('Reserva')
                  ->orWhere('Reserva', 0)
                  ->orWhere('Reserva', false);
            })
            ->orderByDesc('Fecha')
            ->orderByDesc('id')
            ->first();

        if (!$pendiente) {
            return; // No hay reportes pendientes del tejedor
        }

        // 2. Obtener el telar físico (BD local) en el que recaerá la reserva
        $telar = $this->obtenerTelarObjetivoParaNotificacion($data, $noTelar, $tipo);
        
        if ($telar) {
            // Se le transfiere la hora del reporte al telar (para cálculos de eficiencia)
            $horaPendiente = trim((string) ($pendiente->hora ?? ''));
            if ($horaPendiente !== '') {
                $telar->horaParo = $horaPendiente;
                $telar->save();
            }

            // A la notificación se le adjuntan los datos del julio y orden involucrados
            $pendiente->no_julio = $telar->no_julio;
            $pendiente->no_orden = $telar->no_orden;
        } else {
            Log::warning('ReservaInventario: notificación pendiente encontrada, pero sin telar objetivo válido', [
                'notifica_id' => $pendiente->id ?? null,
                'no_telar' => $noTelar,
                'tipo' => $tipo,
            ]);
        }

        // 3. Se marca como "atendida" (Reserva = 1)
        $pendiente->Reserva = 1;
        $pendiente->save();

        Log::info('ReservaInventario: notificación de tejedor consumida exitosamente', [
            'notifica_id' => $pendiente->id ?? null,
            'tej_inventario_telares_id' => $telar?->id,
            'no_telar' => $noTelar,
        ]);
    }

    /**
     * Busca el registro activo en `TejInventarioTelares` usando el ID o el nombre y tipo.
     */
    private function obtenerTelarObjetivoParaNotificacion(array $data, string $noTelar, ?string $tipo): ?TejInventarioTelares
    {
        $telarId = isset($data['TejInventarioTelaresId']) && is_numeric($data['TejInventarioTelaresId'])
            ? (int) $data['TejInventarioTelaresId']
            : null;

        if ($telarId) {
            $telar = TejInventarioTelares::where('id', $telarId)->where('status', 'Activo')->first();
            if ($telar) {
                return $telar;
            }
        }

        $query = TejInventarioTelares::where('no_telar', $noTelar)->where('status', 'Activo');
        if ($tipo !== null) {
            $query->whereRaw('LOWER(LTRIM(RTRIM(tipo))) = ?', [mb_strtolower($tipo, 'UTF-8')]);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Determina el tipo de reserva ('Rizo' o 'Pie') basándose en los datos entrantes o el telar.
     */
    private function resolverTipoReserva(array $data): ?string
    {
        $tipo = $this->normalizeTipoReserva($data['Tipo'] ?? null);
        if ($tipo !== null) {
            return $tipo;
        }

        $telarId = isset($data['TejInventarioTelaresId']) && is_numeric($data['TejInventarioTelaresId'])
            ? (int) $data['TejInventarioTelaresId']
            : null;

        if (!$telarId) {
            return null;
        }

        $tipoTelar = TejInventarioTelares::where('id', $telarId)->value('tipo');
        return $this->normalizeTipoReserva($tipoTelar);
    }

    /**
     * Estandariza la cadena del tipo asegurando que devuelva 'Rizo', 'Pie' o nulo.
     */
    private function normalizeTipoReserva($tipo): ?string
    {
        if ($tipo === null) {
            return null;
        }

        $t = mb_strtolower(trim((string) $tipo), 'UTF-8');
        if ($t === '') {
            return null;
        }
        if ($t === 'rizo') {
            return 'Rizo';
        }
        if ($t === 'pie') {
            return 'Pie';
        }

        return trim((string) $tipo);
    }

    /**
     * Cancela una o varias reservas. 
     * Puede cancelar a través del `Id` único, o bien localizándola por sus dimensiones.
     *
     * @param array $input Datos de la reserva a cancelar.
     * @return array ['updated' => bool]
     */
    public function ejecutarCancelar(array $input): array
    {
        $query = InvTelasReservadas::query();

        // 1. Identificar la reserva
        if (!empty($input['Id'])) {
            $query->where('Id', $input['Id']);
        } else {
            // Cancelar usando el conjunto dimensional exacto
            $query->where('NoTelarId', $input['NoTelarId'])
                ->where('ItemId', $input['ItemId'])
                ->where('ConfigId', $input['ConfigId'] ?? '')
                ->where('InventSizeId', $input['InventSizeId'] ?? '')
                ->where('InventColorId', $input['InventColorId'] ?? '')
                ->where('InventLocationId', $input['InventLocationId'] ?? '')
                ->where('InventBatchId', $input['InventBatchId'] ?? '')
                ->where('WMSLocationId', $input['WMSLocationId'] ?? '')
                ->where('InventSerialId', $input['InventSerialId'] ?? '');
        }

        $reservasACancelar = $query->get();
        $noTelarId = $reservasACancelar->isNotEmpty() ? $reservasACancelar->first()->NoTelarId : null;
        
        // 2. Cambiar estatus a Cancelado
        $updatedRows = $query->update(['Status' => 'Cancelado']);

        // 3. Revisar si debemos liberar la bandera de "Reservado" en el Telar físico
        if ($updatedRows > 0 && $noTelarId) {
            $this->liberarTelarSiNoHayReservasActivas($noTelarId);
        }

        return ['updated' => $updatedRows > 0];
    }

    /**
     * Revisa si un telar se quedó vacío (sin piezas reservadas). Si es así, libera su estado.
     */
    private function liberarTelarSiNoHayReservasActivas(string $noTelarId): void
    {
        try {
            $tieneReservasActivas = InvTelasReservadas::where('NoTelarId', $noTelarId)
                ->where('Status', 'Reservado')
                ->exists();

            if (!$tieneReservasActivas) {
                TejInventarioTelares::where('no_telar', $noTelarId)
                    ->where('status', 'Activo')
                    ->update(['Reservado' => false]);
            }
        } catch (Throwable $e) {
            Log::warning('Error al actualizar campo Reservado al cancelar reserva', [
                'noTelarId' => $noTelarId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Realiza la consulta a la base de datos externa de TI-PRO para obtener el inventario físico disponible.
     * Utiliza un nivel de aislamiento READ UNCOMMITTED (NOLOCK) para evitar bloqueos en el ERP de producción.
     *
     * @param array $filtros Lista de filtros a aplicar en la consulta.
     * @param int $limit Límite de registros a traer.
     * @return array Resultados obtenidos.
     */
    private function queryDisponibleFromTiPro(array $filtros = [], int $limit = self::LIMIT_TI): array
    {
        $cn = DB::connection(self::TI_CONN);
        
        // Evitar locks en tablas del ERP para no afectar producción
        $cn->statement('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;');

        try {
            $query = $cn->table(DB::raw('InventSum AS s WITH (NOLOCK)'))
                ->join(DB::raw('InventDim AS d WITH (NOLOCK)'), function ($join) {
                    $join->on('d.InventDimId', '=', 's.InventDimId')
                        ->where('d.DATAAREAID', '=', self::DATAAREA)
                        ->where('d.InventLocationId', '=', self::LOC_TELA);
                })
                ->leftJoin(DB::raw('InventSerial AS ser WITH (NOLOCK)'), function ($join) {
                    $join->on('ser.InventSerialId', '=', 'd.InventSerialId')
                        ->on('ser.ItemId', '=', 's.ItemId')
                        ->where('ser.DATAAREAID', '=', self::DATAAREA);
                })
                ->where('s.DATAAREAID', self::DATAAREA)
                ->where('s.AvailPhysical', '>', 0) // Solo inventario realmente disponible
                ->where(function ($q) {
                    // Filtrar que al menos sean productos de la categoría requerida (Rizo o Pie)
                    $q->where('s.ItemId', 'like', self::PATTERN_RIZO)
                        ->orWhere('s.ItemId', 'like', self::PATTERN_PIE);
                })
                ->selectRaw("LTRIM(RTRIM(ISNULL(s.ItemId, ''))) AS ItemId")
                ->selectRaw("LTRIM(RTRIM(ISNULL(d.ConfigId, ''))) AS ConfigId")
                ->selectRaw("LTRIM(RTRIM(ISNULL(d.InventSizeId, ''))) AS InventSizeId")
                ->selectRaw("LTRIM(RTRIM(ISNULL(d.InventColorId, ''))) AS InventColorId")
                ->selectRaw("LTRIM(RTRIM(ISNULL(d.InventLocationId, ''))) AS InventLocationId")
                ->selectRaw("LTRIM(RTRIM(ISNULL(d.InventBatchId, ''))) AS InventBatchId")
                ->selectRaw("LTRIM(RTRIM(ISNULL(d.WMSLocationId, ''))) AS WMSLocationId")
                ->selectRaw("LTRIM(RTRIM(ISNULL(d.InventSerialId, ''))) AS InventSerialId")
                ->selectRaw(
                    "CASE
                        WHEN s.ItemId LIKE ? THEN 'Rizo'
                        WHEN s.ItemId LIKE ? THEN 'Pie'
                        ELSE NULL
                     END AS Tipo",
                    [self::PATTERN_RIZO, self::PATTERN_PIE]
                )
                ->selectRaw('ISNULL(ser.TwMts, 0) AS Metros')
                ->selectRaw('ISNULL(s.PhysicalInvent, 0) AS InventQty')
                ->addSelect('ser.ProdDate')
                ->limit($limit);

            // Aplicar filtros dinámicos indicados por el usuario/UI
            foreach ($filtros as $f) {
                $col = $f['columna'] ?? null;
                $val = trim($f['valor'] ?? '');
                
                if (!$col || $val === '') {
                    continue;
                }

                if ($col === 'Tipo') {
                    $v = mb_strtolower($val, 'UTF-8');
                    if (strpos($v, 'rizo') !== false) {
                        $query->where('s.ItemId', 'like', self::PATTERN_RIZO);
                    } elseif (strpos($v, 'pie') !== false) {
                        $query->where('s.ItemId', 'like', self::PATTERN_PIE);
                    }
                    continue;
                }

                if ($col === 'ProdDate') {
                    try {
                        $date = Carbon::parse($val)->format('Y-m-d');
                        $query->whereRaw('CAST(ser.ProdDate AS DATE) = ?', [$date]);
                    } catch (Throwable) {
                        $query->whereRaw('CAST(ser.ProdDate AS NVARCHAR(23)) LIKE ?', ['%' . $val . '%']);
                    }
                    continue;
                }

                if ($col === 'InventQty' || $col === 'Metros') {
                    $expr = self::FILTER_SQL[$col];
                    if (is_numeric($val)) {
                        $query->whereRaw("$expr = ?", [(float) $val]);
                    } else {
                        $query->whereRaw("CAST($expr AS NVARCHAR(50)) LIKE ?", ['%' . $val . '%']);
                    }
                    continue;
                }

                // Filtrar cualquier otro campo estándar usando LIKE ignorando mayúsculas/minúsculas
                if (isset(self::FILTER_SQL[$col])) {
                    $expr = self::FILTER_SQL[$col];
                    $query->whereRaw(
                        "LOWER(CAST($expr AS NVARCHAR(100))) LIKE ?",
                        ['%' . mb_strtolower($val, 'UTF-8') . '%']
                    );
                }
            }

            return $query
                ->orderBy('s.ItemId')
                ->orderBy('d.ConfigId')
                ->get()
                ->all();

        } finally {
            // Restaurar el nivel de aislamiento al finalizar (incluso si hubo excepción)
            $cn->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED;');
        }
    }
}
