<?php

declare(strict_types=1);

namespace App\Services\ProgramaUrdEng;

use App\Models\Inventario\InvTelasReservadas;
use App\Models\Tejedores\TejNotificaTejedorModel;
use App\Models\Tejido\TejInventarioTelares;
use App\Support\ProgramaUrdEng\CompatibilidadInventario;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
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
    private const TI_CONN = 'sqlsrv_ti';

    private const DATAAREA = 'PRO';

    /** Almacenes de julios: engomados listos (TELA) y urdidos de Karl Mayer (URD). */
    private const ALMACENES = ['A-JUL/TELA', 'A-JUL/URD'];

    /**
     * Localidades de maquina: el julio ya esta montado en la engomadora, no esta disponible.
     * KM1 (Karl Mayer) si entra: ahi los julios estan en piso esperando telar.
     */
    private const LOCALIDADES_EN_MAQUINA = ['MC1', 'MC2', 'MC3'];

    private const LIMIT_TI = 2000;

    /**
     * Patrones de búsqueda de Items para distinguir entre tipo Rizo y Pie.
     * Sin comodín inicial para que el índice de ItemId se use (auditoría §2.6: 2147 ms → 130 ms).
     */
    private const PATTERN_RIZO = 'JU-ENG-RI%';

    private const PATTERN_PIE = 'JU-ENG-PI%';

    /** Julio de urdido (Karl Mayer): vive en A-JUL/URD y no es rizo ni pie, va sin Tipo. Sin comodín inicial (ver arriba). */
    private const PATTERN_URDIDO = 'JULIO-URDIDO%';

    /** Columnas permitidas para filtrar en las peticiones del frontend */
    public const ALLOWED_FILTERS = [
        'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId', 'InventLocationId',
        'InventBatchId', 'WMSLocationId', 'InventSerialId', 'Tipo',
        'InventQty', 'Metros', 'ProdDate', 'NoTelarId',
    ];

    /** Mapeo de los campos del frontend a las columnas reales en la consulta SQL de TI-PRO */
    private const FILTER_SQL = [
        'ItemId' => 's.ItemId',
        'ConfigId' => 'd.ConfigId',
        'InventSizeId' => 'd.InventSizeId',
        'InventColorId' => 'd.InventColorId',
        'InventLocationId' => 'd.InventLocationId',
        'InventBatchId' => 'd.InventBatchId',
        'WMSLocationId' => 'd.WMSLocationId',
        'InventSerialId' => 'd.InventSerialId',
        'InventQty' => 'ISNULL(s.PhysicalInvent,0)',
        'Metros' => 'ISNULL(ser.TwMts,0)',
        'ProdDate' => 'ser.ProdDate',
    ];

    /**
     * Normaliza los filtros que llegan desde la petición (querystring/body).
     * Asegura que el array de salida tenga siempre el formato estricto: [['columna' => '...', 'valor' => '...']]
     *
     * @param  mixed  $raw  Filtros en formato crudo.
     * @return array Filtros estructurados.
     */
    public function normalizeFilters($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $filter) {
            if (is_array($filter) && isset($filter['columna'], $filter['valor'])) {
                $normalized[] = [
                    'columna' => (string) $filter['columna'],
                    'valor' => (string) $filter['valor'],
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
     * @param  array|object  $obj  Objeto o array con los datos de la pieza.
     * @return string Clave concatenada con '|'.
     */
    /**
     * Folio de urdido de los julios que TI-PRO no clasifica, indexado por lote normalizado.
     *
     * Una sola consulta a UrdProgramaUrdido: el lote del ERP (InventBatchId) es el folio del
     * programa, y su columna RizoPie trae 'Rizo'/'Pie' o la barra '1'..'4' de Karl Mayer.
     *
     * @param  array<int, object>  $rowsTi
     * @return array<string, string>
     */
    private function tiposPorFolioUrdido(array $rowsTi): array
    {
        $lotes = [];
        foreach ($rowsTi as $row) {
            if (! $this->esJulioUrdido($row)) {
                continue;
            }
            $lote = trim((string) ($row->InventBatchId ?? ''));
            if ($lote !== '') {
                // array_unique y no una llave: PHP convierte '4072' en int si va de indice.
                $lotes[] = $lote;
            }
        }

        $lotes = array_values(array_unique($lotes));

        if ($lotes === []) {
            return [];
        }

        $tipos = [];

        // El folio se guarda con ceros a la izquierda ('01269') y el lote no siempre: se
        // indexa por ambas formas para que crucen igual.
        DB::table('UrdProgramaUrdido')
            ->whereIn('Folio', array_values(array_unique(array_merge(
                $lotes,
                array_map(fn (string $l) => str_pad(ltrim($l, '0'), 5, '0', STR_PAD_LEFT), $lotes)
            ))))
            ->select('Folio', 'RizoPie')
            ->orderBy('Id')
            ->get()
            ->each(function ($fila) use (&$tipos) {
                $tipo = trim((string) ($fila->RizoPie ?? ''));
                if ($tipo !== '') {
                    // El ultimo gana: si un folio se recapturo, manda el renglon mas reciente.
                    $tipos[$this->normalizeFolio($fila->Folio)] = $tipo;
                }
            });

        return $tipos;
    }

    /**
     * Solo el julio de urdido (Karl Mayer) necesita el cruce con el programa de urdido.
     * Rizo y Pie ya vienen clasificados por el ItemId en TI-PRO y no se tocan.
     */
    private function esJulioUrdido(object $row): bool
    {
        return str_contains(mb_strtoupper(trim((string) ($row->ItemId ?? ''))), 'JULIO-URDIDO');
    }

    /** Lote y folio se comparan sin ceros a la izquierda: '01269' y '1269' son el mismo. */
    private function normalizeFolio($folio): string
    {
        return ltrim(trim((string) ($folio ?? '')), '0');
    }

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
     * @param  array  $filtros  Filtros normalizados a aplicar.
     * @return array Resultado con el formato ['data' => array, 'total' => int].
     */
    public function getDisponibleData(array $filtros): array
    {
        $filtroNoTelarId = null;
        $filtrosTi = [];

        // 1. Separar los filtros locales de los que se envían por query a TI-PRO.
        // 'NoTelarId' vive en las reservas locales; un 'Tipo' de barra ('1'..'4') sale del
        // programa de urdido, no de TI-PRO, asi que tambien se resuelve aqui.
        $filtroTipoBarra = null;

        foreach ($filtros as $f) {
            $columna = $f['columna'] ?? '';
            $valor = trim($f['valor'] ?? '');

            if ($columna === 'NoTelarId') {
                $filtroNoTelarId = $valor;

                continue;
            }

            if ($columna === 'Tipo' && preg_match('/^[1-4]$/', $valor)) {
                $filtroTipoBarra = $valor;

                continue;
            }

            $filtrosTi[] = $f;
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

        // TI-PRO no sabe de barras: el tipo del julio de urdido sale del programa de urdido,
        // cruzando el lote (InventBatchId) con su folio.
        $tiposPorFolio = $this->tiposPorFolioUrdido($rowsTi);

        $resultados = [];

        // Determinar si el usuario pide ver "sólo los disponibles" (es decir, los que NO tienen reserva)
        $wantOnlyAvailable = false;
        if ($filtroNoTelarId !== null && $filtroNoTelarId !== '') {
            $v = mb_strtolower($filtroNoTelarId, 'UTF-8');
            $wantOnlyAvailable = in_array($v, ['null', 'vacío', 'vacio', 'disponible'], true);
        }

        // 4. Procesar resultados de TI-PRO: cruzar (left join lógico) con reservas locales
        foreach ($rowsTi as $row) {
            if ($this->esJulioUrdido($row)) {
                $row->Tipo = $tiposPorFolio[$this->normalizeFolio($row->InventBatchId)] ?? null;
            }

            if ($filtroTipoBarra !== null && (string) ($row->Tipo ?? '') !== $filtroTipoBarra) {
                continue;
            }

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
            return $noTelarId !== null && $noTelarId !== '';
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
    public function getDiagnosticoReservas(?string $noTelar, int $limit): Collection
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
     * @param  array  $data  Datos validados de la reserva.
     * @return array ['created' => bool, 'message' => string]
     */
    public function ejecutarReserva(array $data): array
    {
        // El lote es el prefijo del numero de julio ('00061-744' -> '00061').
        $lote = CompatibilidadInventario::loteDerivado(
            $data['InventSerialId'] ?? null,
            $data['InventBatchId'] ?? null
        );
        if ($lote !== '') {
            $data['InventBatchId'] = $lote;
        }

        // Las tres escrituras son una sola operacion: si falla la del telar, no
        // puede quedar una reserva huerfana con el telar sin marcar como Reservado.
        return DB::transaction(function () use ($data): array {
            $created = false;
            $msg = 'Pieza reservada correctamente.';

            // Regla de negocio: consumir notificaciones (avisos de los tejedores) previas a la reserva
            $this->aplicarReglaNotificaTejedorAntesDeReservar($data);

            try {
                InvTelasReservadas::create($data);
                $created = true;
            } catch (QueryException $qe) {
                if (! self::esViolacionDeUnico($qe)) {
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
        });
    }

    /**
     * 2601 y 2627 son violacion de indice unico en SQL Server. Vienen en
     * errorInfo[1] (codigo del driver); getCode() devuelve el SQLSTATE '23000',
     * que nunca coincide con esos numeros.
     */
    public static function esViolacionDeUnico(QueryException $qe): bool
    {
        return in_array((int) ($qe->errorInfo[1] ?? 0), [2601, 2627], true);
    }

    /**
     * Actualiza la información (ConfigId, LoteProveedor, etc.) y la bandera "Reservado" en un Telar.
     */
    private function actualizarEstadoTelarTrasReserva(int $telarId, array $data): void
    {
        $telar = TejInventarioTelares::where('id', $telarId)->where('status', 'Activo')->first();

        if (! $telar) {
            // Antes se tragaba con un warning y la reserva respondia success:true
            // dejando el telar sin marcar. Ahora revienta la transaccion.
            throw new \RuntimeException(
                "No se encontró telar activo {$telarId} para marcar como reservado."
            );
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
        if (! empty($data['NoProveedor'])) {
            $telar->NoProveedor = $this->normalizeDimValue($data['NoProveedor']);
        }

        $telar->save();
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

        if (! $pendiente) {
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

            // El julio de esta reserva. En una barra puede ser el 2, 3 o 4;
            // copiar siempre la primera columna le pegaba el julio anterior.
            [$julio, $orden] = $this->parDeLaReserva($telar, $data);
            if ($julio !== '') {
                $pendiente->no_julio = $julio;
            }
            if ($orden !== '') {
                $pendiente->no_orden = $orden;
            }
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
     * Julio y orden de la pieza que se está reservando.
     * Rizo y pie viven en la primera columna. Una barra de Karl Mayer usa
     * la columna cuyo julio coincide con el serial de la reserva.
     *
     * @return array{0: string, 1: string}
     */
    private function parDeLaReserva(TejInventarioTelares $telar, array $data): array
    {
        $serial = trim((string) ($data['InventSerialId'] ?? ''));

        foreach (InventarioTelaresService::PARES_JULIO as $columnaJulio => $columnaOrden) {
            $julio = trim((string) ($telar->{$columnaJulio} ?? ''));
            if ($julio === '') {
                continue;
            }
            if ($serial === '' || strcasecmp($julio, $serial) === 0) {
                return [$julio, trim((string) ($telar->{$columnaOrden} ?? ''))];
            }
        }

        return [$serial, ''];
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

        if (! $telarId) {
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
     * @param  array  $input  Datos de la reserva a cancelar.
     * @return array ['updated' => bool]
     */
    public function ejecutarCancelar(array $input): array
    {
        $query = InvTelasReservadas::query();

        // 1. Identificar la reserva
        if (! empty($input['Id'])) {
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

            if (! $tieneReservasActivas) {
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
     * @param  array  $filtros  Lista de filtros a aplicar en la consulta.
     * @param  int  $limit  Límite de registros a traer.
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
                        ->whereIn('d.InventLocationId', self::ALMACENES);
                })
                ->leftJoin(DB::raw('InventSerial AS ser WITH (NOLOCK)'), function ($join) {
                    $join->on('ser.InventSerialId', '=', 'd.InventSerialId')
                        ->on('ser.ItemId', '=', 's.ItemId')
                        ->where('ser.DATAAREAID', '=', self::DATAAREA);
                })
                ->where('s.DATAAREAID', self::DATAAREA)
                ->where('s.PhysicalInvent', '>', 0) // Solo inventario realmente disponible
                // Un julio en MC1/MC2/MC3 ya esta en la engomadora: no se puede reservar.
                ->whereNotIn(
                    DB::raw("LTRIM(RTRIM(ISNULL(d.WMSLocationId, '')))"),
                    self::LOCALIDADES_EN_MAQUINA
                )
                ->where(function ($q) {
                    // Filtrar que al menos sean productos de la categoría requerida (Rizo, Pie o urdido de KM)
                    $q->where('s.ItemId', 'like', self::PATTERN_RIZO)
                        ->orWhere('s.ItemId', 'like', self::PATTERN_PIE)
                        ->orWhere('s.ItemId', 'like', self::PATTERN_URDIDO);
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

                if (! $col || $val === '') {
                    continue;
                }

                if ($col === 'Tipo') {
                    $v = mb_strtolower($val, 'UTF-8');
                    if (strpos($v, 'rizo') !== false) {
                        $query->where('s.ItemId', 'like', self::PATTERN_RIZO);
                    } elseif (strpos($v, 'pie') !== false) {
                        $query->where('s.ItemId', 'like', self::PATTERN_PIE);
                    }

                    // ponytail: una barra de Karl Mayer ('1'..'4') no filtra nada y se
                    // ofrecen todos los julios. En TI-PRO solo existen items JU-ENG-RI y
                    // JU-ENG-PI; cuando haya items por barra, agregar su patron aqui.
                    continue;
                }

                if ($col === 'ProdDate') {
                    try {
                        $date = Carbon::parse($val)->format('Y-m-d');
                        $query->whereRaw('CAST(ser.ProdDate AS DATE) = ?', [$date]);
                    } catch (Throwable) {
                        $query->whereRaw('CAST(ser.ProdDate AS NVARCHAR(23)) LIKE ?', ['%'.$val.'%']);
                    }

                    continue;
                }

                if ($col === 'InventQty' || $col === 'Metros') {
                    $expr = self::FILTER_SQL[$col];
                    if (is_numeric($val)) {
                        $query->whereRaw("$expr = ?", [(float) $val]);
                    } else {
                        $query->whereRaw("CAST($expr AS NVARCHAR(50)) LIKE ?", ['%'.$val.'%']);
                    }

                    continue;
                }

                // Filtrar cualquier otro campo estándar usando LIKE ignorando mayúsculas/minúsculas
                if (isset(self::FILTER_SQL[$col])) {
                    $expr = self::FILTER_SQL[$col];
                    $query->whereRaw(
                        "LOWER(CAST($expr AS NVARCHAR(100))) LIKE ?",
                        ['%'.mb_strtolower($val, 'UTF-8').'%']
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
