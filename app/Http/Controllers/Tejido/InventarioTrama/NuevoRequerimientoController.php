<?php

namespace App\Http\Controllers\Tejido\InventarioTrama;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Tejido\TejTrama;
use App\Models\Tejido\TejTramaConsumos;
use App\Helpers\TurnoHelper;
use App\Helpers\FolioHelper;
use App\Helpers\AuditoriaHelper;
use Carbon\Carbon;

class NuevoRequerimientoController extends Controller
{
    /** @var string[] */
    private const SALON_ITEMA = ['ITEMA', 'SMIT'];

    /**
     * Vista principal: construye $vm para el Blade.
     * - Modo edición si viene ?folio=
     * - Modo nuevo si no viene folio
     */
    public function index()
    {
        $folioEditar = request()->query('folio');
        $hoyMx       = now('America/Mexico_City');
        $turno       = TurnoHelper::getTurnoActual();
        $turnoDesc   = TurnoHelper::getTurnoFormato($turno);

        $enProceso   = TejTrama::where('Status', 'En Proceso')->first();

        // Si no hay folio para editar y no hay en proceso, mostrar folio sugerido
        $folioInicial = $folioEditar ?: ($enProceso->Folio ?? null);
        if (!$folioInicial) {
            $folioInicial = FolioHelper::obtenerFolioSugerido('Trama', 5);
        }

        $vm = [
            'pageTitle'           => $folioEditar ? 'Editar Requerimiento' : 'Nuevo Requerimiento',
            'folio'               => $folioInicial,
            'fecha'               => $folioEditar ? null : $hoyMx->toDateString(),
            'turnoDesc'           => $folioEditar ? null : $turnoDesc,
            'enProcesoExists'     => (bool) $enProceso,
            'consultaUrl'         => route('tejido.inventario.trama.consultar.requerimiento'),
            'actualizarCantidadUrl' => route('modulo.nuevo.requerimiento.actualizar.cantidad'),
            'listaTelares'        => [],
            'telares'             => [],
        ];

        if ($folioEditar) {
            // ====== MODO EDICIÓN ======
            [$cabecera, $detalles, $consumosPorTelar] = $this->cargarEdicionPorFolio($folioEditar);

            $vm['folio']     = $cabecera->Folio ?? $vm['folio'];
            $vm['fecha']     = $cabecera->Fecha ?? $vm['fecha'];
            $vm['turnoDesc'] = $cabecera && $cabecera->Turno ? TurnoHelper::getTurnoFormato($cabecera->Turno) : $vm['turnoDesc'];

            $vm['listaTelares'] = array_keys($consumosPorTelar);

            foreach ($vm['listaTelares'] as $noTelar) {
                $salonNom = strtoupper($consumosPorTelar[$noTelar]['salon'] ?? '');
                $tipo     = ($salonNom === 'ITEMA' ? 'itema' : 'jacquard');

                // Datos base visibles arriba
                $telarData = $this->mapTelarData(null, [
                    'Orden_Prod'      => $consumosPorTelar[$noTelar]['orden']    ?? '',
                    'Nombre_Producto' => $consumosPorTelar[$noTelar]['producto'] ?? '',
                    'Inicio_Tejido'   => $cabecera->Fecha ?? null,
                ]);

                // Completar con Programa en Proceso (si existe)
                $pt = $this->fetchProgramaEnProceso((int)$noTelar, $tipo);
                if ($pt) {
                    $telarData = array_merge($telarData, $this->mapTelarData($pt));
                }

                // Rows desde consumos guardados
                $rows = $consumosPorTelar[$noTelar]['items'] ?? [];

                // Evitar duplicados: registrar calibres ya existentes
                $exist = [];
                foreach ($rows as $r) {
                    if ($r['calibre'] !== null) {
                        $exist[number_format((float)$r['calibre'], 2)] = true;
                    }
                }
                // Añadir TRA/C1..C5 faltantes con cantidad 0
                $rows = array_values(array_merge(
                    $rows,
                    $this->buildRowsFromTelarData($telarData, $exist)
                ));

                $vm['telares'][] = [
                    'numero'     => (string)$noTelar,
                    'salon'      => $salonNom ?: ($tipo === 'itema' ? 'ITEMA' : 'JACQUARD'),
                    'tipo'       => $tipo,
                    'telarData'  => $telarData,
                    'ordenSig'   => null,
                    'rows'       => $rows,
                ];
            }
        } else {
            // ====== MODO NUEVO ======
            $jac = DB::table('InvSecuenciaTrama')->where('TipoTelar', 'JACQUARD')->orderBy('Secuencia')->pluck('NoTelar')->toArray();
            $itm = DB::table('InvSecuenciaTrama')->where('TipoTelar', 'ITEMA')   ->orderBy('Secuencia')->pluck('NoTelar')->toArray();
            $telaresOrdenados = array_merge($jac, $itm);

            $vm['listaTelares'] = $telaresOrdenados;

            foreach ($telaresOrdenados as $noTelar) {
                $tipoSalon = $this->determinarTipoSalon((int)$noTelar); // 'JACQUARD' | 'ITEMA'
                $tipo      = strtolower($tipoSalon);                    // 'jacquard' | 'itema'

                $pt = $this->fetchProgramaEnProceso((int)$noTelar, $tipo);

                $telarData = $this->mapTelarData($pt);

                // Construir filas TRA + C1..C5 (si existen)
                $rows = $this->buildRowsFromTelarData($telarData);

                // Orden siguiente (si tenemos Inicio_Tejido)
                $ordenSig = null;
                if (!empty($telarData['Inicio_Tejido'])) {
                    $ordenSig = $this->buscarOrdenSiguiente($telarData['Inicio_Tejido'], (int)$noTelar, $tipoSalon);
                }

                $vm['telares'][] = [
                    'numero'     => (string)$noTelar,
                    'salon'      => $tipoSalon,
                    'tipo'       => $tipo,
                    'telarData'  => $telarData,
                    'ordenSig'   => $ordenSig,
                    'rows'       => $rows,
                ];
            }
        }

        // Normaliza índices
        $vm['telares'] = array_values($vm['telares']);

        return view('modulos.inventario-trama.nuevo-requerimiento', ['vm' => $vm]);
    }

    /**
     * Persistencia principal de requerimientos (crear/editar).
     */
    public function guardarRequerimientos(Request $request)
    {
        try {
            DB::beginTransaction();

            $turno = TurnoHelper::getTurnoActual();
            $fecha = Carbon::now('America/Mexico_City')->toDateString();

            $providedFolio = trim((string)$request->input('folio', ''));



            $folio = $this->resolverFolio($providedFolio, $turno, $fecha);


            $consumos = $request->input('consumos', []);
            $consumos = is_array($consumos) ? $consumos : [];

            foreach ($consumos as $i => $consumo) {
                $this->upsertConsumoNormalizado($folio, $consumo, $i);
            }

            // Si es nuevo y no hubo filas, inserta una mínima para no dejar folio “vacío”
            if (empty($consumos) && $providedFolio === '') {
                $this->asegurarConsumoMinimo($folio);
            }

            // (No bloqueante) Actualiza fechas en Programa
            // $this->actualizarFechasReqProgramaTejido($consumos, $fecha);



            // Regresar consumos para actualizar data-consumo-id en el DOM
            $consumosGuardados = TejTramaConsumos::where('Folio', substr($folio, 0, 10))
                ->get()
                ->map(fn($c) => [
                    'id'        => $c->Id,
                    'folio'     => $c->Folio,
                    'telar'     => $c->NoTelarId,
                    'calibre'   => $c->CalibreTrama,
                    'fibra'     => $c->FibraTrama,
                    'cod_color' => $c->CodColorTrama,
                    'color'     => $c->ColorTrama,
                    'cantidad'  => $c->Cantidad,
                ])->toArray();

            DB::commit();

            // Registrar evento de auditoría
            $accion = $providedFolio ? 'UPDATE' : 'INSERT';
            $detalle = "Folio={$folio} | Consumos=" . count($consumos);
            AuditoriaHelper::logEvento('TejTrama', $accion, $detalle, $request);

            return response()->json([
                'success'  => true,
                'message'  => 'Requerimientos guardados exitosamente',
                'folio'    => $folio,
                'turno'    => $turno,
                'consumos' => $consumosGuardados,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('guardarRequerimientos fallo', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar requerimientos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** API: Información del turno y un folio sugerido (para otras pantallas) */
    public function getTurnoInfo()
    {
        $turno       = TurnoHelper::getTurnoActual();
        $descripcion = TurnoHelper::getTurnoFormato($turno);
        $folio       = $this->generarFolioSugerido();

        return response()->json(['turno' => $turno, 'descripcion' => $descripcion, 'folio' => $folio]);
    }

    /** API: ¿Existe un folio “En Proceso”? */
    public function enProcesoInfo()
    {
        $enProceso = TejTrama::where('Status', 'En Proceso')->orderByDesc('Fecha')->first();
        return response()->json(['exists' => (bool)$enProceso, 'folio' => $enProceso->Folio ?? null]);
    }

    /** API: Actualiza solo cantidad de un consumo */
    public function actualizarCantidad(Request $request)
    {
        try {
            $data = $request->validate([
                'id'       => 'required|integer',
                'cantidad' => 'required|numeric|min:0',
            ]);

            // SQL directo (compatibilidad SQL Server 2008)
            $updated = DB::update(
                'UPDATE TejTramaConsumos SET Cantidad = ? WHERE Id = ?',
                [(float)$data['cantidad'], (int)$data['id']]
            );

            if ($updated > 0) {
                $consumo = DB::table('TejTramaConsumos')->where('Id', $data['id'])->first();
                
                // Registrar evento de auditoría
                AuditoriaHelper::logEvento(
                    'TejTramaConsumos',
                    'UPDATE',
                    "Id={$data['id']} | Cantidad={$data['cantidad']}",
                    $request
                );

                return response()->json([
                    'success'      => true,
                    'message'      => 'Cantidad actualizada correctamente',
                    'cantidad'     => $consumo->Cantidad,
                    'updated_rows' => $updated,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se encontró el registro o no se pudo actualizar',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('actualizarCantidad fallo', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar cantidad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** API: Obtener calibres (igual que reconocado) */
    public function getCalibres()
    {
        try {
            $items = DB::connection('sqlsrv_ti')
                ->table('InventTable')
                ->select('ItemId')
                ->where('ItemGroupId', 'HILO DIREC')
                ->where('DATAAREAID', 'PRO')
                ->orderBy('ItemId')
                ->distinct()
                ->get();

            return response()->json(['success' => true, 'data' => $items]);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo calibres', ['exception' => $e]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** API: Obtener fibras por ItemId (igual que reconocado) */
    public function getFibras(Request $request)
    {
        $itemId = $request->query('itemId');
        if (!$itemId) {
            return response()->json(['success' => false, 'message' => 'ItemId requerido'], 400);
        }

        try {
            $fibras = DB::connection('sqlsrv_ti')
                ->table('ConfigTable')
                ->select('ConfigId')
                ->where('ItemId', $itemId)
                ->where('DATAAREAID', 'PRO')
                ->orderBy('ConfigId')
                ->distinct()
                ->get();

            return response()->json(['success' => true, 'data' => $fibras]);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo fibras', ['exception' => $e, 'itemId' => $itemId]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** API: Obtener colores por ItemId (igual que reconocado) */
    public function getColores(Request $request)
    {
        $itemId = $request->query('itemId');
        if (!$itemId) {
            return response()->json(['success' => false, 'message' => 'ItemId requerido'], 400);
        }

        try {
            $colores = DB::connection('sqlsrv_ti')
                ->table('InventColor')
                ->select('InventColorId', 'Name')
                ->where('ItemId', $itemId)
                ->where('DATAAREAID', 'PRO')
                ->orderBy('InventColorId')
                ->get();

            return response()->json(['success' => true, 'data' => $colores]);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo colores', ['exception' => $e, 'itemId' => $itemId]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** API: Buscar artículos (calibres) para autocomplete - DEPRECATED, usar getCalibres */
    public function buscarArticulos(Request $request)
    {
        return $this->getCalibres();
    }

    /** API: Buscar fibras para autocomplete - DEPRECATED, usar getFibras */
    public function buscarFibras(Request $request)
    {
        return $this->getFibras($request);
    }

    /** API: Buscar códigos de color para autocomplete - DEPRECATED, usar getColores */
    public function buscarCodigosColor(Request $request)
    {
        return $this->getColores($request);
    }

    /** API: Buscar nombres de color para autocomplete */
    public function buscarNombresColor(Request $request)
    {
        try {
            $search = $request->query('q', '');
            $search = trim($search);

            $query = DB::table('TejTramaConsumos')
                ->select('ColorTrama')
                ->whereNotNull('ColorTrama')
                ->where('ColorTrama', '!=', '')
                ->distinct();

            if ($search !== '') {
                $query->whereRaw('ColorTrama LIKE ?', ['%' . $search . '%']);
            }

            $colores = $query->orderBy('ColorTrama')
                ->limit(50)
                ->pluck('ColorTrama')
                ->unique()
                ->values();

            return response()->json($colores->toArray());
        } catch (\Throwable $e) {
            Log::error('buscarNombresColor fallo', ['error' => $e->getMessage()]);
            return response()->json([], 500);
        }
    }

    // =========================================================
    // Helpers de construcción de VM (index)
    // =========================================================

    /**
     * @return array{0: object|null, 1: \Illuminate\Support\Collection, 2: array}
     */
    private function cargarEdicionPorFolio(string $folio): array
    {
        $cabecera = DB::table('TejTrama')->where('Folio', $folio)->first();
        $detalles = DB::table('TejTramaConsumos')->where('Folio', $folio)->get();

        $consumosPorTelar = [];
        foreach ($detalles as $d) {
            $telar = (string)($d->NoTelarId ?? '');
            if (!isset($consumosPorTelar[$telar])) {
                $consumosPorTelar[$telar] = [
                    'salon'    => (string)($d->SalonTejidoId ?? ''),
                    'orden'    => (string)($d->NoProduccion ?? ''),
                    'producto' => (string)($d->NombreProducto ?? ''),
                    'items'    => [],
                ];
            }
            $consumosPorTelar[$telar]['items'][] = [
                'id'        => $d->Id,
                'calibre'   => $d->CalibreTrama,
                'fibra'     => $d->FibraTrama ?: null,
                'cod_color' => $d->CodColorTrama ?: null,
                'color'     => $d->ColorTrama ?: null,
                'cantidad'  => (float)($d->Cantidad ?? 0),
            ];
        }

        return [$cabecera, $detalles, $consumosPorTelar];
    }

    /** Busca ReqProgramaTejido en proceso para un telar (maneja ITEMA con SMIT y fallback 3XX->1XX). */
    private function fetchProgramaEnProceso(int $noTelar, string $tipo): ?object
    {
        $salones = ($tipo === 'itema') ? self::SALON_ITEMA : [strtoupper($tipo)];
        $n = $noTelar;

        $q = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->where('NoTelarId', $n)
            ->where('EnProceso', 1);

        // Fallback ITEMA: si no existe 3XX, busca 1XX
        if ($tipo === 'itema' && !$q->exists()) {
            $n = 100 + ($noTelar % 100);
            $q = DB::table('ReqProgramaTejido')
                ->whereIn('SalonTejidoId', $salones)
                ->where('NoTelarId', $n)
                ->where('EnProceso', 1);
        }

        return $q->select([
            'NoTelarId',
            'EnProceso',
            'NoProduccion as Orden_Prod',
            'FlogsId as Id_Flog',
            'CategoriaCalidad as Calidad',
            'CustName as Cliente',
            'TamanoClave as InventSizeId',
            'ItemId as ItemId',
            'NombreProducto as Nombre_Producto',
            'TotalPedido as Saldos',
            'Produccion',
            'FechaInicio',
            'FechaFinal',
            // Trama
            'CalibreTrama as CALIBRE_TRA',
            'FibraTrama as FIBRA_TRA',
            'CodColorTrama as CODIGO_COLOR_TRAMA',
            'ColorTrama as COLOR_TRAMA',
            // Combos
            'CalibreComb1 as CALIBRE_C1', 'FibraComb1 as FIBRA_C1', 'CodColorComb1 as CODIGO_COLOR_C1', 'NombreCC1 as COLOR_C1',
            'CalibreComb2 as CALIBRE_C2', 'FibraComb2 as FIBRA_C2', 'CodColorComb2 as CODIGO_COLOR_C2', 'NombreCC2 as COLOR_C2',
            'CalibreComb3 as CALIBRE_C3', 'FibraComb3 as FIBRA_C3', 'CodColorComb3 as CODIGO_COLOR_C3', 'NombreCC3 as COLOR_C3',
            'CalibreComb4 as CALIBRE_C4', 'FibraComb4 as FIBRA_C4', 'CodColorComb4 as CODIGO_COLOR_C4', 'NombreCC4 as COLOR_C4',
            'CalibreComb5 as CALIBRE_C5', 'FibraComb5 as FIBRA_C5', 'CodColorComb5 as CODIGO_COLOR_C5', 'NombreCC5 as COLOR_C5',
        ])->first();
    }

    /** Estandariza el objeto Programa a arreglo que el Blade pinta en telarData. */
    private function mapTelarData($pt, array $overrides = []): array
    {
        $base = [
            'Orden_Prod'      => null,
            'Id_Flog'         => null,
            'Cliente'         => null,
            'InventSizeId'    => null,
            'ItemId'          => null,
            'Nombre_Producto' => null,
            'Saldos'          => null,
            'Produccion'      => null,
            'Inicio_Tejido'   => null,
            'Fin_Tejido'      => null,

            'CALIBRE_TRA' => null, 'FIBRA_TRA' => null, 'CODIGO_COLOR_TRAMA' => null, 'COLOR_TRAMA' => null,
            'CALIBRE_C1'  => null, 'FIBRA_C1'  => null, 'CODIGO_COLOR_C1'    => null, 'COLOR_C1'   => null,
            'CALIBRE_C2'  => null, 'FIBRA_C2'  => null, 'CODIGO_COLOR_C2'    => null, 'COLOR_C2'   => null,
            'CALIBRE_C3'  => null, 'FIBRA_C3'  => null, 'CODIGO_COLOR_C3'    => null, 'COLOR_C3'   => null,
            'CALIBRE_C4'  => null, 'FIBRA_C4'  => null, 'CODIGO_COLOR_C4'    => null, 'COLOR_C4'   => null,
            'CALIBRE_C5'  => null, 'FIBRA_C5'  => null, 'CODIGO_COLOR_C5'    => null, 'COLOR_C5'   => null,
        ];

        if ($pt) {
            $map = (array)$pt;
            $base = array_merge($base, [
                'Orden_Prod'      => $map['Orden_Prod']      ?? null,
                'Id_Flog'         => $map['Id_Flog']         ?? null,
                'Calidad'         => $map['Calidad']         ?? null,
                'Cliente'         => $map['Cliente']         ?? null,
                'InventSizeId'    => $map['InventSizeId']    ?? null,
                'ItemId'          => $map['ItemId']          ?? null,
                'Nombre_Producto' => $map['Nombre_Producto'] ?? null,
                'Saldos'          => $map['Saldos']          ?? null,
                'Produccion'      => $map['Produccion']      ?? null,
                // Trae el query completo: fecha y hr, Carbon lo transforma a un DateString
                'Inicio_Tejido'   => !empty($map['FechaInicio']) ? Carbon::parse($map['FechaInicio']) ->toDateString() : null,
                'Fin_Tejido'      => !empty($map['FechaFinal']) ? Carbon::parse($map['FechaFinal']) ->toDateString() : null,

                'CALIBRE_TRA' => $map['CALIBRE_TRA'] ?? null,
                'FIBRA_TRA'   => $map['FIBRA_TRA']   ?? null,
                'CODIGO_COLOR_TRAMA' => $map['CODIGO_COLOR_TRAMA'] ?? null,
                'COLOR_TRAMA' => $map['COLOR_TRAMA'] ?? null,

                'CALIBRE_C1' => $map['CALIBRE_C1'] ?? null, 'FIBRA_C1' => $map['FIBRA_C1'] ?? null,
                'CODIGO_COLOR_C1' => $map['CODIGO_COLOR_C1'] ?? null, 'COLOR_C1' => $map['COLOR_C1'] ?? null,
                'CALIBRE_C2' => $map['CALIBRE_C2'] ?? null, 'FIBRA_C2' => $map['FIBRA_C2'] ?? null,
                'CODIGO_COLOR_C2' => $map['CODIGO_COLOR_C2'] ?? null, 'COLOR_C2' => $map['COLOR_C2'] ?? null,
                'CALIBRE_C3' => $map['CALIBRE_C3'] ?? null, 'FIBRA_C3' => $map['FIBRA_C3'] ?? null,
                'CODIGO_COLOR_C3' => $map['CODIGO_COLOR_C3'] ?? null, 'COLOR_C3' => $map['COLOR_C3'] ?? null,
                'CALIBRE_C4' => $map['CALIBRE_C4'] ?? null, 'FIBRA_C4' => $map['FIBRA_C4'] ?? null,
                'CODIGO_COLOR_C4' => $map['CODIGO_COLOR_C4'] ?? null, 'COLOR_C4' => $map['COLOR_C4'] ?? null,
                'CALIBRE_C5' => $map['CALIBRE_C5'] ?? null, 'FIBRA_C5' => $map['FIBRA_C5'] ?? null,
                'CODIGO_COLOR_C5' => $map['CODIGO_COLOR_C5'] ?? null, 'COLOR_C5' => $map['COLOR_C5'] ?? null,
            ]);
        }

        return array_merge($base, $overrides);
    }

    /**
     * Genera filas TRA + C1..C5 a partir de telarData.
     * $existKeys: claves (number_format(calibre,2)) ya presentes (para edición).
     */
    private function buildRowsFromTelarData(array $td, array $existKeys = []): array
    {
        $add = function($cal, $fib, $cod, $col) {
            if ($cal === null || (float)$cal == 0.0) return null;
            return [
                'id'        => null,
                'calibre'   => (float)$cal,
                'fibra'     => $fib ?: null,
                'cod_color' => $cod ?: null,
                'color'     => $col ?: null,
                'cantidad'  => 0,
            ];
        };

        $candidates = [];
        $candidates[] = $add($td['CALIBRE_TRA'], $td['FIBRA_TRA'], $td['CODIGO_COLOR_TRAMA'], $td['COLOR_TRAMA']);
        $candidates[] = $add($td['CALIBRE_C1'],  $td['FIBRA_C1'],  $td['CODIGO_COLOR_C1'],  $td['COLOR_C1']);
        $candidates[] = $add($td['CALIBRE_C2'],  $td['FIBRA_C2'],  $td['CODIGO_COLOR_C2'],  $td['COLOR_C2']);
        $candidates[] = $add($td['CALIBRE_C3'],  $td['FIBRA_C3'],  $td['CODIGO_COLOR_C3'],  $td['COLOR_C3']);
        $candidates[] = $add($td['CALIBRE_C4'],  $td['FIBRA_C4'],  $td['CODIGO_COLOR_C4'],  $td['COLOR_C4']);
        $candidates[] = $add($td['CALIBRE_C5'],  $td['FIBRA_C5'],  $td['CODIGO_COLOR_C5'],  $td['COLOR_C5']);

        $rows = [];
        foreach ($candidates as $r) {
            if (!$r) continue;
            $k = number_format((float)$r['calibre'], 2);
            if (!empty($existKeys[$k])) continue; // ya existe en edición
            $existKeys[$k] = true;
            $rows[] = $r;
        }
        return $rows;
    }

    /** Orden siguiente según FechaInicio (para el encabezado “SIG. ORDEN”). */
    private function buscarOrdenSiguiente($inicioTejido, int $telar, string $tipoSalon): ?object
    {
        $salones  = $tipoSalon === 'ITEMA' ? self::SALON_ITEMA : [$tipoSalon];
        $noTelDb  = ($tipoSalon === 'ITEMA') ? (100 + ($telar % 100)) : $telar;

        return DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->where('NoTelarId', $noTelDb)
            ->where('EnProceso', 0)
            ->where('FechaInicio', '>', $inicioTejido)
            ->orderBy('FechaInicio')
            ->select([
                'NoTelarId as Telar',
                'NoProduccion as Orden_Prod',
                'ItemId as ItemId',
                'TamanoClave as Tamano_AX',
                'NombreProducto as Nombre_Producto',
                'TotalPedido as Saldos',
                'FechaInicio as Inicio_Tejido',
            ])->first();
    }

    /** Heurística simple para mapear telar a salón. Ajusta rangos si ocupas. */
    private function determinarTipoSalon(int $telar): string
    {
        if ($telar >= 299 && $telar <= 320) return 'ITEMA';
        return 'JACQUARD';
    }

    // =========================================================
    // Helpers de guardado
    // =========================================================

    private function resolverFolio(string $providedFolio, string $turno, string $fecha): string
    {
        if ($providedFolio !== '') {
            $registro = TejTrama::query()->where('Folio', $providedFolio)->lockForUpdate()->first();
            if (!$registro) abort(404, 'El folio indicado no existe');

            $usuario = Auth::user();
            $registro->update([
                'numero_empleado' => $usuario->numero_empleado ?? '',
                'nombreEmpl'      => $usuario->nombre ?? '',
            ]);

            return $registro->Folio;
        }

        // Reusar “En Proceso” si ya existe
        $enProceso = TejTrama::query()->where('Status', 'En Proceso')->lockForUpdate()->first();
        if ($enProceso) {
            return $enProceso->Folio;
        }

        // Crear nuevo
        $folio = $this->generarFolioSecuencial();

        $usuario = Auth::user();

        TejTrama::create([
            'Folio'           => $folio,
            'Fecha'           => $fecha,
            'Status'          => 'En Proceso',
            'Turno'           => $turno,
            'numero_empleado' => $usuario->numero_empleado ?? '',
            'nombreEmpl'      => $usuario->nombre ?? '',
        ]);

        return $folio;
    }

    private function upsertConsumoNormalizado(string $folio, array $consumo, int $index): void
    {
        if (empty($consumo['telar'])) {
            Log::warning("Consumo {$index} sin telar, omitido");
            return;
        }

        $calibre   = isset($consumo['calibre']) && $consumo['calibre'] !== '' ? (float)$consumo['calibre'] : null;
        $cantidad  = isset($consumo['cantidad']) ? (float)$consumo['cantidad'] : 0.0;
        $telar     = substr((string)($consumo['telar'] ?? ''), 0, 10);
        $salon     = substr((string)($consumo['salon'] ?? ''), 0, 10);
        $orden     = substr((string)($consumo['orden'] ?? ''), 0, 15);
        $producto  = isset($consumo['producto']) ? substr((string)$consumo['producto'], 0, 20) : '';
        $fibra     = isset($consumo['fibra']) ? substr((string)$consumo['fibra'], 0, 15) : null;
        $codColor  = isset($consumo['cod_color']) ? substr((string)$consumo['cod_color'], 0, 10) : null;
        $color     = isset($consumo['color']) ? substr((string)$consumo['color'], 0, 60) : null;

        $fibra    = ($fibra === '' || $fibra === '-') ? null : $fibra;
        $codColor = ($codColor === '' || $codColor === '-') ? null : $codColor;
        $color    = ($color === '' || $color === '-') ? null : $color;
        $producto = ($producto === '-' ? '' : $producto);

        // Clave compuesta con tolerancia en calibre
        $query = TejTramaConsumos::where('Folio', substr($folio, 0, 10))
            ->where('NoTelarId', $telar);

        if ($calibre === null) {
            $query->whereNull('CalibreTrama');
        } else {
            $query->whereRaw('ABS(CalibreTrama - ?) < 0.01', [$calibre]);
        }

        $fibra === null ? $query->whereNull('FibraTrama') : $query->where('FibraTrama', $fibra);
        $codColor === null ? $query->whereNull('CodColorTrama') : $query->where('CodColorTrama', $codColor);
        $color === null ? $query->whereNull('ColorTrama') : $query->where('ColorTrama', $color);

        if ($existente = $query->first()) {
            $existente->update([
                'SalonTejidoId'  => $salon,
                'NoProduccion'   => $orden,
                'NombreProducto' => $producto,
                'Cantidad'       => $cantidad,
            ]);
            return;
        }

        TejTramaConsumos::create([
            'Folio'          => substr($folio, 0, 10),
            'NoTelarId'      => $telar,
            'SalonTejidoId'  => $salon,
            'NoProduccion'   => $orden,
            'CalibreTrama'   => $calibre,
            'NombreProducto' => $producto,
            'FibraTrama'     => $fibra,
            'CodColorTrama'  => $codColor,
            'ColorTrama'     => $color,
            'Cantidad'       => $cantidad,
        ]);
    }

    private function asegurarConsumoMinimo(string $folio): void
    {
        if (TejTramaConsumos::where('Folio', substr($folio, 0, 10))->count() > 0) return;

        TejTramaConsumos::create([
            'Folio'          => substr($folio, 0, 10),
            'NoTelarId'      => '',
            'SalonTejidoId'  => '',
            'NoProduccion'   => '',
            'CalibreTrama'   => null,
            'NombreProducto' => '',
            'FibraTrama'     => null,
            'CodColorTrama'  => null,
            'ColorTrama'     => null,
            'Cantidad'       => 0,
        ]);
    }

    // =========================================================
    // Folios
    // =========================================================

    /** Genera el siguiente folio usando SSYSFoliosSecuencias. Formato TR00001, TR00002, ... */
    private function generarFolioSecuencial(): string
    {
        return FolioHelper::obtenerSiguienteFolio('Trama', 5);
    }

    /** Folio sugerido (sin lock), útil para UI. */
    private function generarFolioSugerido(): string
    {
        return FolioHelper::obtenerFolioSugerido('Trama', 5);
    }

    // =========================================================
    // Actualización de fechas en Programa de Tejido (no bloqueante)
    // =========================================================
}
