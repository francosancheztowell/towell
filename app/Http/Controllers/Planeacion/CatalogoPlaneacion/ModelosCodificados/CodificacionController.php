<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\ModelosCodificados;

use App\Http\Controllers\Controller;
use App\Imports\ReqModelosCodificadosImport;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Support\Planeacion\TelarSalonResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class CodificacionController extends Controller
{
    /** Columnas de la tabla (headers) */
    private const COLUMNAS = [
        'Tamaño Clave', 'Rasema', 'Fecha Orden', 'Fecha Cumplimiento', 'Departamento',
        'Telar Actual', 'Prioridad', 'Modelo', 'Clave Modelo', 'Clave AX', 'Tamaño AX',
        'Tolerancia', 'Codigo Dibujo', 'Fecha Compromiso', 'Flogs', 'Nombre de Formato Logístico',
        'Clave', 'Cantidad a Producir', 'Peine', 'Ancho', 'Largo', 'Peso crudo', 'Luchaje',
        'Tra', 'Hilo', 'FibraId', 'Tipo plano', 'Med plano', 'Tipo de Rizo', 'Altura de Rizo',
        'OBS', 'Velocidad STD', 'Calibre Rizo', 'Calibre Rizo 2', 'Cuenta Rizo', 'Fibra Rizo', 'Calibre Pie', 'Calibre Pie 2',
        'Cuenta Pie', 'Fibra Pie', 'C1', 'OBS', 'C2', 'OBS 2', 'C3', 'OBS 3', 'C4', 'OBS 4',
        'Med. de Cenefa', 'Med de inicio de rizo a cenefa', 'Rasurado', 'Tiras',
        'Repeticiones p/corte', 'No. De Marbetes', 'Cambio de repaso', 'Vendedor', 'Categoria Calidad',
        'Observaciones', 'Trama (Ancho Peine)', 'Log. Lucha Total', 'C1 Trama de Fondo', 'Hilo C1 Trama de Fondo',
        'OBS C1 Trama de Fondo', 'Pasadas C1', 'C2 Trama de Fondo', 'Hilo C2 Trama de Fondo',
        'OBS C2 Trama de Fondo', 'Pasadas C2 ', 'C3 Trama de Fondo', 'Hilo C3 Trama de Fondo',
        'OBS C3 Trama de Fondo', 'Pasadas C3 ', 'C4 Trama de Fondo', 'Hilo C4 Trama de Fondo',
        'OBS C4 Trama de Fondo', 'Pasadas C4 ', 'C5 Trama de Fondo', 'Hilo C5 Trama de Fondo',
        'OBS C5 Trama de Fondo', 'Pasadas C5', 'Total', 'Pasadas Dibujo', 'Contraccion', 'Tramas cm/Tejido',
        'Contrac Rizo', 'Clasificación(KG)', 'KG/Día', 'Densidad', 'Pzas/Día/ pasadas', 'Pzas/Día/ formula',
        'DIF', 'EFIC.', 'Rev', 'Tiras', 'Pasadas', 'ColumCT', 'ColumCU', 'ColumCV', 'Comprobar modelos duplicados',
    ];

    /** Mapeo campo => tipo (date|zero|null para string) */
    private const CAMPOS_MODELO = [
        'TamanoClave' => null,
        'OrdenTejido' => null,
        'FechaTejido' => 'date',
        'FechaCumplimiento' => 'date',
        'SalonTejidoId' => null,
        'NoTelarId' => null,
        'Prioridad' => null,
        'Nombre' => null,
        'ClaveModelo' => null,
        'ItemId' => null,
        'InventSizeId' => null,
        'Tolerancia' => null,
        'CodigoDibujo' => null,
        'FechaCompromiso' => 'date',
        'FlogsId' => null,
        'NombreProyecto' => null,
        'Clave' => null,
        'Pedido' => 'zero',
        'Peine' => 'zero',
        'AnchoToalla' => 'zero',
        'LargoToalla' => 'zero',
        'PesoCrudo' => 'zero',
        'Luchaje' => 'zero',
        'CalibreTrama' => 'zero',
        'CalibreTrama2' => 'zero',
        'CodColorTrama' => null,
        'ColorTrama' => null,
        'FibraId' => null,
        'DobladilloId' => null,
        'MedidaPlano' => 'zero',
        'TipoRizo' => null,
        'AlturaRizo' => 'zero',
        'Obs' => null,
        'VelocidadSTD' => 'zero',
        'CalibreRizo' => null,
        'CalibreRizo2' => null,
        'CuentaRizo' => 'zero',
        'FibraRizo' => null,
        'CalibrePie' => null,
        'CalibrePie2' => null,
        'CuentaPie' => 'zero',
        'FibraPie' => null,
        'Comb1' => null,
        'Obs1' => null,
        'Comb2' => null,
        'Obs2' => null,
        'Comb3' => null,
        'Obs3' => null,
        'Comb4' => null,
        'Obs4' => null,
        'MedidaCenefa' => null,
        'MedIniRizoCenefa' => null,
        'Rasurado' => null,
        'NoTiras' => 'zero',
        'Repeticiones' => 'zero',
        'TotalMarbetes' => 'zero',
        'CambioRepaso' => null,
        'Vendedor' => null,
        'CatCalidad' => null,
        'Obs5' => null,
        'AnchoPeineTrama' => 'zero',
        'LogLuchaTotal' => 'zero',
        'CalTramaFondoC1' => null,
        'CalTramaFondoC12' => null,
        'FibraTramaFondoC1' => null,
        'PasadasTramaFondoC1' => 'zero',
        'CalibreComb1' => null,
        'CalibreComb12' => null,
        'FibraComb1' => null,
        'CodColorC1' => null,
        'NomColorC1' => null,
        'PasadasComb1' => 'zero',
        'CalibreComb2' => null,
        'CalibreComb22' => null,
        'FibraComb2' => null,
        'CodColorC2' => null,
        'NomColorC2' => null,
        'PasadasComb2' => 'zero',
        'CalibreComb3' => null,
        'CalibreComb32' => null,
        'FibraComb3' => null,
        'CodColorC3' => null,
        'NomColorC3' => null,
        'PasadasComb3' => 'zero',
        'CalibreComb4' => null,
        'CalibreComb42' => null,
        'FibraComb4' => null,
        'CodColorC4' => null,
        'NomColorC4' => null,
        'PasadasComb4' => 'zero',
        'CalibreComb5' => null,
        'CalibreComb52' => null,
        'FibraComb5' => null,
        'CodColorC5' => null,
        'NomColorC5' => null,
        'PasadasComb5' => 'zero',
        'Total' => 'zero',
        'PasadasDibujo' => null,
        'Contraccion' => null,
        'TramasCMTejido' => null,
        'ContracRizo' => null,
        'ClasificacionKG' => null,
        'KGDia' => null,
        'Densidad' => null,
        'PzasDiaPasadas' => null,
        'PzasDiaFormula' => null,
        'DIF' => null,
        'EFIC' => null,
        'Rev' => null,
        'TIRAS' => 'zero',
        'PASADAS' => 'zero',
        'ColumCT' => null,
        'ColumCU' => null,
        'ColumCV' => null,
        'ComprobarModDup' => null,

        // Karl Mayer (telares 401/402): construccion de cuatro barras en vez de rizo/pie/C1-C5.
        // Van aqui y no solo en el formulario porque store() y update() hacen
        // $request->only(array_keys(CAMPOS_MODELO)): lo que falte en esta lista se descarta
        // en silencio, que es lo que ya les pasa a los 12 campos de color.
        'CuentaBarra1' => null,
        'CalibreBarra1' => null,
        'CodColorBarra1' => null,
        'ColorBarra1' => null,
        'FibraBarra1' => null,
        'PasadasBarra1' => 'zero',
        'CuentaBarra2' => null,
        'CalibreBarra2' => null,
        'CodColorBarra2' => null,
        'ColorBarra2' => null,
        'FibraBarra2' => null,
        'PasadasBarra2' => 'zero',
        'CuentaBarra3' => null,
        'CalibreBarra3' => null,
        'CodColorBarra3' => null,
        'ColorBarra3' => null,
        'FibraBarra3' => null,
        'PasadasBarra3' => 'zero',
        'CuentaBarra4' => null,
        'CalibreBarra4' => null,
        'CodColorBarra4' => null,
        'ColorBarra4' => null,
        'FibraBarra4' => null,
        'PasadasBarra4' => 'zero',
    ];

    /** Karl Mayer: cuatro barras (no hay Barra5 en BD). */
    private const CAMPOS_KM = [
        'CuentaBarra1', 'CalibreBarra1', 'CodColorBarra1', 'ColorBarra1', 'FibraBarra1', 'PasadasBarra1',
        'CuentaBarra2', 'CalibreBarra2', 'CodColorBarra2', 'ColorBarra2', 'FibraBarra2', 'PasadasBarra2',
        'CuentaBarra3', 'CalibreBarra3', 'CodColorBarra3', 'ColorBarra3', 'FibraBarra3', 'PasadasBarra3',
        'CuentaBarra4', 'CalibreBarra4', 'CodColorBarra4', 'ColorBarra4', 'FibraBarra4', 'PasadasBarra4',
    ];

    /** Jacquard / Smit: rizo, pie, trama y C1–C5. Karl Mayer no los guarda. */
    private const CAMPOS_STD = [
        'CalibreTrama', 'CalibreTrama2', 'CodColorTrama', 'ColorTrama', 'FibraId',
        'AnchoPeineTrama', 'LogLuchaTotal',
        'TipoRizo', 'AlturaRizo', 'CuentaRizo', 'CalibreRizo', 'CalibreRizo2', 'FibraRizo',
        'CuentaPie', 'CalibrePie', 'CalibrePie2', 'FibraPie',
        'MedidaCenefa', 'MedIniRizoCenefa',
        'Comb1', 'Obs1', 'Comb2', 'Obs2', 'Comb3', 'Obs3', 'Comb4', 'Obs4',
        'CalTramaFondoC1', 'CalTramaFondoC12', 'FibraTramaFondoC1', 'PasadasTramaFondoC1',
        'CalibreComb1', 'CalibreComb12', 'FibraComb1', 'CodColorC1', 'NomColorC1', 'PasadasComb1',
        'CalibreComb2', 'CalibreComb22', 'FibraComb2', 'CodColorC2', 'NomColorC2', 'PasadasComb2',
        'CalibreComb3', 'CalibreComb32', 'FibraComb3', 'CodColorC3', 'NomColorC3', 'PasadasComb3',
        'CalibreComb4', 'CalibreComb42', 'FibraComb4', 'CodColorC4', 'NomColorC4', 'PasadasComb4',
        'CalibreComb5', 'CalibreComb52', 'FibraComb5', 'CodColorC5', 'NomColorC5', 'PasadasComb5',
    ];

    /** Campos de fecha para validación */
    private const DATE_FIELDS = ['FechaTejido', 'FechaCumplimiento', 'FechaCompromiso'];

    /** Campos requeridos en alta y edición. Tamaño Clave se arma con Clave AX + Tamaño. */
    private const REQUIRED_FIELDS = ['TamanoClave', 'OrdenTejido', 'SalonTejidoId', 'ItemId', 'InventSizeId'];

    private function clearCodificacionCache(?int $id = null): void
    {
        Cache::forget('codificacion_total');
        Cache::forget('codificacion_fast_all');
        Cache::forget('codificacion_estimated_count');
        if ($id) {
            Cache::forget("codificacion_fast_id_{$id}");
        }
    }

    private function getColumnMaxLengths(string $table): array
    {
        $cacheKey = "column_lengths_{$table}";

        return Cache::remember($cacheKey, 3600, function () use ($table) {
            $rows = DB::select(
                'SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH AS max_len
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = ? AND CHARACTER_MAXIMUM_LENGTH IS NOT NULL',
                [$table]
            );

            $lengths = [];
            foreach ($rows as $row) {
                $maxLen = isset($row->max_len) ? (int) $row->max_len : null;
                if ($maxLen && $maxLen > 0) {
                    $lengths[$row->COLUMN_NAME] = $maxLen;
                }
            }

            return $lengths;
        });
    }

    private function getTableColumns(string $table): array
    {
        $cacheKey = "columns_{$table}";

        return Cache::remember($cacheKey, 3600, function () use ($table) {
            $rows = DB::select(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?',
                [$table]
            );

            $columns = [];
            foreach ($rows as $row) {
                $columns[$row->COLUMN_NAME] = true;
            }

            return $columns;
        });
    }

    /** Columnas enteras de la tabla (para redondear decimales antes del insert) */
    private function getIntColumns(string $table): array
    {
        return Cache::remember("int_columns_{$table}", 3600, function () use ($table) {
            $rows = DB::select(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = ? AND DATA_TYPE IN ('int','bigint','smallint','tinyint')",
                [$table]
            );

            return array_fill_keys(array_column($rows, 'COLUMN_NAME'), true);
        });
    }

    private function normalizeDataForTable(array $data, array $columns, array $lengths, array $intColumns = []): array
    {
        $normalized = [];
        foreach ($data as $column => $value) {
            if (! isset($columns[$column])) {
                continue;
            }
            if (isset($intColumns[$column]) && $value !== null && $value !== '' && is_numeric($value)) {
                $normalized[$column] = (int) round((float) $value);

                continue;
            }
            $normalized[$column] = $this->truncateValueForColumn($column, $value, $lengths);
        }

        return $normalized;
    }

    private function findCatCodificadoByOrden(string $orden): ?CatCodificados
    {
        $orden = trim($orden);
        if ($orden === '') {
            return null;
        }

        $registro = CatCodificados::where('OrdenTejido', $orden)->orderByDesc('Id')->first();
        if ($registro) {
            return $registro;
        }

        return CatCodificados::where('NoOrden', $orden)->orderByDesc('Id')->first();
    }

    /**
     * Modelo similar: orden, clave modelo, o par Clave AX + Tamaño.
     *
     * @param  array{orden?: string, clave_modelo?: string, item_id?: string, invent_size_id?: string}  $q
     */
    private function findCatCodificadoSimilar(array $q): ?CatCodificados
    {
        $orden = trim((string) ($q['orden'] ?? ''));
        $clave = trim((string) ($q['clave_modelo'] ?? ''));
        $item = trim((string) ($q['item_id'] ?? ''));
        $size = trim((string) ($q['invent_size_id'] ?? ''));

        if ($orden !== '') {
            $hit = $this->findCatCodificadoByOrden($orden);
            if ($hit) {
                return $hit;
            }
        }

        if ($clave !== '') {
            $hit = CatCodificados::where('ClaveModelo', $clave)->orderByDesc('Id')->first();
            if ($hit) {
                return $hit;
            }
            $hit = CatCodificados::where('Clave', $clave)->orderByDesc('Id')->first();
            if ($hit) {
                return $hit;
            }
        }

        if ($item !== '' && $size !== '') {
            return CatCodificados::where('ItemId', $item)
                ->where('InventSizeId', $size)
                ->orderByDesc('Id')
                ->first();
        }

        return null;
    }

    /**
     * @param  array{orden?: string, clave_modelo?: string, excluir_id?: int|string|null}  $q
     */
    private function findReqModeloSimilar(array $q): ?ReqModelosCodificados
    {
        $orden = trim((string) ($q['orden'] ?? ''));
        $clave = trim((string) ($q['clave_modelo'] ?? ''));
        $excluir = $q['excluir_id'] ?? null;

        $base = ReqModelosCodificados::query();
        if ($excluir !== null && $excluir !== '') {
            $base->where('Id', '!=', $excluir);
        }

        if ($orden !== '') {
            return (clone $base)->where('OrdenTejido', $orden)->orderByDesc('Id')->first();
        }

        if ($clave !== '') {
            return (clone $base)
                ->where(function ($w) use ($clave) {
                    $w->where('ClaveModelo', $clave)->orWhere('TamanoClave', $clave);
                })
                ->orderByDesc('Id')
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     */
    private function respuestaModeloSimilar(string $origen, string $orden, string $claveMod, string $itemId, string $sizeId, string $nombre, array $campos): array
    {
        return [
            'success' => true,
            'data' => [
                'origen' => $origen,
                'orden_trabajo' => $orden,
                'salon' => trim((string) ($campos['SalonTejidoId'] ?? '')),
                'clave_mod' => $claveMod,
                'clave_ax' => $itemId,
                'tamano' => $sizeId,
                'nombre' => $nombre,
                'campos' => $campos,
            ],
        ];
    }

    private function mapCatCodificadosToReq(CatCodificados $cat): array
    {
        $mapped = [
            'FechaTejido' => $cat->FechaTejido ?? null,
            'FechaCumplimiento' => $cat->FechaCumplimiento ?? null,
            'SalonTejidoId' => $cat->Departamento ?? null,
            'NoTelarId' => $cat->TelarId ?? null,
            'Prioridad' => $cat->Prioridad ?? null,
            'Nombre' => $cat->Nombre ?? null,
            'ClaveModelo' => $cat->ClaveModelo ?? null,
            'TamanoClave' => $cat->ClaveModelo ?? $cat->Clave ?? null,
            'ItemId' => $cat->ItemId ?? null,
            'InventSizeId' => $cat->InventSizeId ?? null,
            'Tolerancia' => $cat->Tolerancia ?? null,
            'CodigoDibujo' => $cat->CodigoDibujo ?? null,
            'FechaCompromiso' => $cat->FechaCompromiso ?? null,
            'FlogsId' => $cat->FlogsId ?? null,
            'NombreProyecto' => $cat->NombreProyecto ?? null,
            'CustName' => $cat->CustName ?? null,
            'Clave' => $cat->Clave ?? null,
            'Pedido' => $cat->Cantidad ?? $cat->Pedido ?? null,
            'Peine' => $cat->Peine ?? null,
            'AnchoToalla' => $cat->Ancho ?? null,
            'LargoToalla' => $cat->Largo ?? null,
            'PesoCrudo' => $cat->P_crudo ?? null,
            'Luchaje' => $cat->Luchaje ?? null,
            'CalibreTrama' => $cat->Tra ?? null,
            'CalibreTrama2' => $cat->CalibreTrama2 ?? null,
            'CodColorTrama' => $cat->CodColorTrama ?? null,
            'ColorTrama' => $cat->ColorTrama ?? null,
            'FibraId' => $cat->FibraId ?? null,
            'DobladilloId' => $cat->DobladilloId ?? null,
            'MedidaPlano' => $cat->MedidaPlano ?? null,
            'TipoRizo' => $cat->TipoRizo ?? null,
            'AlturaRizo' => $cat->AlturaRizo ?? null,
            'Obs' => $cat->Obs ?? null,
            'VelocidadSTD' => $cat->VelocidadSTD ?? null,
            'CalibreRizo' => $cat->CalibreRizo ?? null,
            'CalibreRizo2' => $cat->CalibreRizo2 ?? null,
            'CuentaRizo' => $cat->CuentaRizo ?? null,
            'FibraRizo' => $cat->FibraRizo ?? null,
            'CalibrePie' => $cat->CalibrePie ?? null,
            'CalibrePie2' => $cat->CalibrePie2 ?? null,
            'CuentaPie' => $cat->CuentaPie ?? null,
            'FibraPie' => $cat->FibraPie ?? null,
            'Comb1' => $cat->Comb1 ?? null,
            'Obs1' => $cat->Obs1 ?? null,
            'Comb2' => $cat->Comb2 ?? null,
            'Obs2' => $cat->Obs2 ?? null,
            'Comb3' => $cat->Comb3 ?? null,
            'Obs3' => $cat->Obs3 ?? null,
            'Comb4' => $cat->Comb4 ?? null,
            'Obs4' => $cat->Obs4 ?? null,
            'MedidaCenefa' => $cat->MedidaCenefa ?? null,
            'MedIniRizoCenefa' => $cat->MedIniRizoCenefa ?? null,
            'Rasurado' => $cat->Razurada ?? $cat->Rasurada ?? null,
            'NoTiras' => $cat->NoTiras ?? null,
            'Repeticiones' => $cat->Repeticiones ?? null,
            'TotalMarbetes' => $cat->NoMarbete ?? null,
            'CambioRepaso' => $cat->CambioRepaso ?? null,
            'Vendedor' => $cat->Vendedor ?? null,
            'CatCalidad' => $cat->CategoriaCalidad ?? null,
            'Obs5' => $cat->Obs5 ?? null,
            'AnchoPeineTrama' => $cat->TramaAnchoPeine ?? null,
            'LogLuchaTotal' => $cat->LogLuchaTotal ?? null,
            'CalTramaFondoC1' => $cat->CalTramaFondoC1 ?? null,
            'CalTramaFondoC12' => $cat->CalTramaFondoC12 ?? null,
            'FibraTramaFondoC1' => $cat->FibraTramaFondoC1 ?? null,
            'PasadasTramaFondoC1' => $cat->PasadasTramaFondoC1 ?? null,
            'CalibreComb1' => $cat->CalibreComb1 ?? null,
            'CalibreComb12' => $cat->CalibreComb12 ?? null,
            'FibraComb1' => $cat->FibraComb1 ?? null,
            'CodColorC1' => $cat->CodColorC1 ?? null,
            'NomColorC1' => $cat->NomColorC1 ?? null,
            'PasadasComb1' => $cat->PasadasComb1 ?? null,
            'CalibreComb2' => $cat->CalibreComb2 ?? null,
            'CalibreComb22' => $cat->CalibreComb22 ?? null,
            'FibraComb2' => $cat->FibraComb2 ?? null,
            'CodColorC2' => $cat->CodColorC2 ?? null,
            'NomColorC2' => $cat->NomColorC2 ?? null,
            'PasadasComb2' => $cat->PasadasComb2 ?? null,
            'CalibreComb3' => $cat->CalibreComb3 ?? null,
            'CalibreComb32' => $cat->CalibreComb32 ?? null,
            'FibraComb3' => $cat->FibraComb3 ?? null,
            'CodColorC3' => $cat->CodColorC3 ?? null,
            'NomColorC3' => $cat->NomColorC3 ?? null,
            'PasadasComb3' => $cat->PasadasComb3 ?? null,
            'CalibreComb4' => $cat->CalibreComb4 ?? null,
            'CalibreComb42' => $cat->CalibreComb42 ?? null,
            'FibraComb4' => $cat->FibraComb4 ?? null,
            'CodColorC4' => $cat->CodColorC4 ?? null,
            'NomColorC4' => $cat->NomColorC4 ?? null,
            'PasadasComb4' => $cat->PasadasComb4 ?? null,
            'CalibreComb5' => $cat->CalibreComb5 ?? null,
            'CalibreComb52' => $cat->CalibreComb52 ?? null,
            'FibraComb5' => $cat->FibraComb5 ?? null,
            'CodColorC5' => $cat->CodColorC5 ?? null,
            'NomColorC5' => $cat->NomColorC5 ?? null,
            'PasadasComb5' => $cat->PasadasComb5 ?? null,
            'Total' => $cat->Total ?? null,
            'PasadasDibujo' => $cat->PasadasDibujo ?? null,
            'Contraccion' => $cat->Contraccion ?? null,
            'TramasCMTejido' => $cat->TramasCMTejido ?? null,
            'ContracRizo' => $cat->ContracRizo ?? null,
            'ClasificacionKG' => $cat->ClasificacionKG ?? null,
            'KGDia' => $cat->KGDia ?? null,
            'Densidad' => $cat->Densidad ?? null,
            'PzasDiaPasadas' => $cat->PzasDiaPasadas ?? null,
            'PzasDiaFormula' => $cat->PzasDiaFormula ?? null,
            'DIF' => $cat->DIF ?? null,
            'EFIC' => $cat->EFIC ?? null,
            'Rev' => $cat->Rev ?? null,
            'TIRAS' => $cat->TIRAS ?? null,
            'PASADAS' => $cat->PASADAS ?? null,
            'ColumCT' => $cat->ColumCT ?? null,
            'ColumCU' => $cat->ColumCU ?? null,
            'ColumCV' => $cat->ColumCV ?? null,
            'ComprobarModDup' => $cat->ComprobarModDup ?? null,
            'Produccion' => $cat->Produccion ?? null,
            'Saldos' => $cat->Saldos ?? null,
            'CuentaBarra1' => $cat->CuentaBarra1 ?? null,
            'CalibreBarra1' => $cat->CalibreBarra1 ?? null,
            'CodColorBarra1' => $cat->CodColorBarra1 ?? null,
            'ColorBarra1' => $cat->ColorBarra1 ?? null,
            'FibraBarra1' => $cat->FibraBarra1 ?? null,
            'PasadasBarra1' => $cat->PasadasBarra1 ?? null,
            'CuentaBarra2' => $cat->CuentaBarra2 ?? null,
            'CalibreBarra2' => $cat->CalibreBarra2 ?? null,
            'CodColorBarra2' => $cat->CodColorBarra2 ?? null,
            'ColorBarra2' => $cat->ColorBarra2 ?? null,
            'FibraBarra2' => $cat->FibraBarra2 ?? null,
            'PasadasBarra2' => $cat->PasadasBarra2 ?? null,
            'CuentaBarra3' => $cat->CuentaBarra3 ?? null,
            'CalibreBarra3' => $cat->CalibreBarra3 ?? null,
            'CodColorBarra3' => $cat->CodColorBarra3 ?? null,
            'ColorBarra3' => $cat->ColorBarra3 ?? null,
            'FibraBarra3' => $cat->FibraBarra3 ?? null,
            'PasadasBarra3' => $cat->PasadasBarra3 ?? null,
            'CuentaBarra4' => $cat->CuentaBarra4 ?? null,
            'CalibreBarra4' => $cat->CalibreBarra4 ?? null,
            'CodColorBarra4' => $cat->CodColorBarra4 ?? null,
            'ColorBarra4' => $cat->ColorBarra4 ?? null,
            'FibraBarra4' => $cat->FibraBarra4 ?? null,
            'PasadasBarra4' => $cat->PasadasBarra4 ?? null,
        ];

        return $this->volcarConstruccionViejaABarrasSiKm($mapped);
    }

    private function truncateValueForColumn(string $column, $value, array $lengths)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $limit = $lengths[$column] ?? null;
        if (! $limit) {
            return $value;
        }

        $str = (string) $value;
        if (function_exists('mb_substr')) {
            return mb_substr($str, 0, $limit);
        }

        return substr($str, 0, $limit);
    }

    /** Obtener configuración de columnas para JavaScript */
    public static function getColumnasConfig(): array
    {
        return array_map(fn ($col, $idx) => ['index' => $idx, 'nombre' => $col], self::COLUMNAS, array_keys(self::COLUMNAS));
    }

    /** Vista principal - Solo estructura, datos via API */
    public function index()
    {
        try {
            // Solo enviamos la estructura, los datos se cargan via fetch
            $total = Cache::remember('codificacion_total', 300, fn () => ReqModelosCodificados::count());

            return view('catalagos.catalogoCodificacion', [
                'columnas' => self::COLUMNAS,
                'camposModelo' => self::CAMPOS_MODELO,
                'columnasConfig' => self::getColumnasConfig(),
                'totalRegistros' => $total,
                'apiUrl' => '/planeacion/catalogos/codificacion-modelos/api/all-fast',
            ]);
        } catch (\Exception $e) {
            Log::error('CodificacionController::index', ['error' => $e->getMessage()]);

            return view('catalagos.catalogoCodificacion', [
                'columnas' => self::COLUMNAS,
                'camposModelo' => self::CAMPOS_MODELO,
                'columnasConfig' => self::getColumnasConfig(),
                'totalRegistros' => 0,
                'apiUrl' => '/planeacion/catalogos/codificacion-modelos/api/all-fast',
                'error' => 'Error al cargar: '.$e->getMessage(),
            ]);
        }
    }

    public function create(Request $request)
    {
        $codificacion = null;

        // Si se está duplicando un registro, cargar sus datos
        if ($request->has('duplicate')) {
            $duplicateId = $request->query('duplicate');
            $original = ReqModelosCodificados::find($duplicateId);

            if (! $original) {
                return redirect()->route('planeacion.catalogos.codificacion-modelos')
                    ->with('error', 'Registro no encontrado para duplicar');
            }

            // Obtener todos los atributos del modelo original (incluyendo nulls)
            $attributes = $original->getAttributes();

            // Eliminar campos que no queremos copiar
            unset($attributes['Id']);
            unset($attributes['created_at']);
            unset($attributes['updated_at']);

            // Crear una nueva instancia vacía
            $codificacion = new ReqModelosCodificados;

            // Usar makeHidden para asegurar que todos los atributos estén disponibles
            // y luego asignar todos los atributos usando setRawAttributes para preservar valores null
            $codificacion->setRawAttributes($attributes, true);

            // Asegurar que no tenga ID asignado y que se trate como nuevo registro
            $codificacion->Id = null;
            $codificacion->exists = false;
            $codificacion->wasRecentlyCreated = false;
        }

        return view('catalagos.codificacion-form', [
            'codificacion' => $codificacion,
            'esDuplicado' => $codificacion !== null,
        ]);
    }

    public function edit($id)
    {
        $codificacion = ReqModelosCodificados::findOrFail($id);

        return view('catalagos.codificacion-form', [
            'codificacion' => $codificacion,
            'esDuplicado' => false,
        ]);
    }

    /** API: todos los registros - Optimizado con índice Id */
    public function getAll(): JsonResponse
    {
        try {
            // Consulta optimizada: usa índice de Id, selecciona solo campos necesarios
            $campos = array_merge(['Id'], array_keys(self::CAMPOS_MODELO));

            // Streaming JSON para grandes volúmenes - más eficiente en memoria
            $codificaciones = ReqModelosCodificados::query()
                ->select($campos)
                ->orderByDesc('Id')
                ->toBase()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $codificaciones,
                'total' => $codificaciones->count(),
            ])->header('Cache-Control', 'private, max-age=60'); // Cache 1 min
        } catch (\Exception $e) {
            Log::error('CodificacionController::getAll', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** API: datos compactos para carga rápida - Solo campos esenciales */
    public function getAllFast(Request $request): JsonResponse
    {
        try {
            // Solo campos esenciales para la tabla, ordenados por Id (usa índice clustered)
            $campos = array_merge(['Id'], array_keys(self::CAMPOS_MODELO));
            $idFilter = $request->filled('id') ? (int) $request->input('id') : null;
            $skipCache = $request->boolean('nocache', false);

            $cacheKey = $idFilter
                ? "codificacion_fast_id_{$idFilter}"
                : 'codificacion_fast_all';

            if (! $skipCache) {
                $cached = Cache::get($cacheKey);
                if ($cached !== null) {
                    return response()->json($cached)
                        ->header('Cache-Control', 'private, max-age=60')
                        ->header('X-Cache', 'HIT');
                }
            }

            DB::connection()->disableQueryLog();

            $columnsStr = implode(', ', array_map(fn ($col) => "[{$col}]", $campos));
            $query = ReqModelosCodificados::query()
                ->selectRaw($columnsStr)
                ->orderByDesc('Id')
                ->toBase();

            if ($idFilter !== null) {
                $row = $query->where('Id', $idFilter)->limit(1)->first();
                $data = $row ? [array_values((array) $row)] : [];

                $response = [
                    's' => true,
                    'd' => $data,
                    't' => $data ? 1 : 0,
                    'c' => $campos,
                ];

                if (! $skipCache) {
                    Cache::put($cacheKey, $response, 60);
                }

                return response()->json($response)
                    ->header('Cache-Control', 'private, max-age=60')
                    ->header('X-Cache', 'MISS');
            }

            $estimatedCount = Cache::remember(
                'codificacion_estimated_count',
                300,
                fn () => ReqModelosCodificados::query()->toBase()->count()
            );

            $data = $estimatedCount > 1000
                ? $this->fetchWithCursor($query)
                : $this->fetchWithGet($query);

            $response = [
                's' => true,
                'd' => $data,
                't' => count($data),
                'c' => $campos,
            ];

            if (! $skipCache) {
                Cache::put($cacheKey, $response, 60);
            }

            return response()->json($response)
                ->header('Cache-Control', 'private, max-age=60')
                ->header('X-Cache', 'MISS');
        } catch (\Exception $e) {
            Log::error('CodificacionController::getAllFast', ['error' => $e->getMessage()]);

            return response()->json(['s' => false, 'e' => $e->getMessage()], 500);
        }
    }

    private function fetchWithGet($query): array
    {
        $data = $query->get();

        $result = [];
        foreach ($data as $row) {
            $result[] = array_values((array) $row);
        }

        return $result;
    }

    private function fetchWithCursor($query): array
    {
        $result = [];

        foreach ($query->cursor() as $row) {
            $result[] = array_values((array) $row);
        }

        return $result;
    }

    /** API: un registro */
    public function show($id): JsonResponse
    {
        $codificacion = ReqModelosCodificados::find($id);

        return $codificacion
            ? response()->json(['success' => true, 'data' => $codificacion])
            : response()->json(['success' => false, 'message' => 'No encontrado'], 404);
    }

    /** Generar reglas de validación */
    private function getValidationRules(bool $isCreate = true): array
    {
        $rules = [];
        foreach (array_keys(self::CAMPOS_MODELO) as $field) {
            $rules[$field] = in_array($field, self::DATE_FIELDS)
                ? 'sometimes|nullable|date'
                : 'sometimes|nullable';
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            $rules[$field] = 'required';
        }
        $rules['OrdenTejido'] = 'required|regex:/^\d+$/';

        return $rules;
    }

    /**
     * KM no persiste rizo/pie/trama/C1–C5. Jacquard y Smit no persisten barras.
     * Vaciar en servidor porque los hidden del cliente igual pueden viajar en el POST.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payloadSegunSalon(array $data): array
    {
        $salon = (string) ($data['SalonTejidoId'] ?? '');
        $telar = (string) ($data['NoTelarId'] ?? '');
        $normalizado = TelarSalonResolver::normalizeSalon($salon, $telar);
        if ($normalizado !== '') {
            $data['SalonTejidoId'] = $normalizado;
        }

        $data = $this->volcarConstruccionViejaABarrasSiKm($data);
        $km = TelarSalonResolver::esKarlMayer($data['SalonTejidoId'] ?? '', $telar);
        foreach ($km ? self::CAMPOS_STD : self::CAMPOS_KM as $campo) {
            $data[$campo] = null;
        }

        return $data;
    }

    private function valorConstruccionLleno(mixed $valor): bool
    {
        $s = trim((string) ($valor ?? ''));

        return $s !== '' && $s !== '0' && $s !== '0.0' && $s !== '0.00';
    }

    /**
     * Captura vieja de KM: rizo/pie/C1–C5 en vez de barras.
     * Si las barras están vacías, se copian en orden a Barra 1–4.
     * Si ya hay barras, esas mandan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function volcarConstruccionViejaABarrasSiKm(array $data, ?string $salonForzado = null): array
    {
        $salon = trim((string) ($salonForzado ?? '')) !== ''
            ? (string) $salonForzado
            : (string) ($data['SalonTejidoId'] ?? '');
        $telar = (string) ($data['NoTelarId'] ?? '');
        if (! TelarSalonResolver::esKarlMayer($salon, $telar)) {
            return $data;
        }

        for ($n = 1; $n <= 4; $n++) {
            if ($this->valorConstruccionLleno($data["CuentaBarra{$n}"] ?? null)
                || $this->valorConstruccionLleno($data["CalibreBarra{$n}"] ?? null)
                || $this->valorConstruccionLleno($data["FibraBarra{$n}"] ?? null)) {
                return $data;
            }
        }

        $fuentes = [
            [
                'Cuenta' => $data['CuentaRizo'] ?? null,
                'Calibre' => $data['CalibreRizo'] ?? $data['CalibreRizo2'] ?? null,
                'Fibra' => $data['FibraRizo'] ?? null,
            ],
            [
                'Cuenta' => $data['CuentaPie'] ?? null,
                'Calibre' => $data['CalibrePie'] ?? $data['CalibrePie2'] ?? null,
                'Fibra' => $data['FibraPie'] ?? null,
            ],
        ];
        for ($c = 1; $c <= 5; $c++) {
            $hilo = $data['CalibreComb'.$c.'2'] ?? null;
            $fuentes[] = [
                'Cuenta' => null,
                'Calibre' => $data["CalibreComb{$c}"] ?? $hilo,
                'CodColor' => $data["CodColorC{$c}"] ?? null,
                'Color' => $data["NomColorC{$c}"] ?? null,
                'Fibra' => $data["FibraComb{$c}"] ?? null,
                'Pasadas' => $data["PasadasComb{$c}"] ?? null,
            ];
        }

        $barra = 1;
        foreach ($fuentes as $src) {
            if ($barra > 4) {
                break;
            }
            $hay = $this->valorConstruccionLleno($src['Cuenta'] ?? null)
                || $this->valorConstruccionLleno($src['Calibre'] ?? null)
                || $this->valorConstruccionLleno($src['Fibra'] ?? null)
                || $this->valorConstruccionLleno($src['CodColor'] ?? null)
                || $this->valorConstruccionLleno($src['Color'] ?? null)
                || $this->valorConstruccionLleno($src['Pasadas'] ?? null);
            if (! $hay) {
                continue;
            }
            $data["CuentaBarra{$barra}"] = $src['Cuenta'] ?? null;
            $data["CalibreBarra{$barra}"] = $src['Calibre'] ?? null;
            $data["CodColorBarra{$barra}"] = $src['CodColor'] ?? null;
            $data["ColorBarra{$barra}"] = $src['Color'] ?? null;
            $data["FibraBarra{$barra}"] = $src['Fibra'] ?? null;
            $data["PasadasBarra{$barra}"] = $src['Pasadas'] ?? null;
            $barra++;
        }

        return $data;
    }

    /**
     * Tamaño Clave y Clave Modelo = Tamaño (InventSizeId) + Clave AX (ItemId): FEL7897.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function completarClaves(array $data): array
    {
        $item = trim((string) ($data['ItemId'] ?? ''));
        $size = trim((string) ($data['InventSizeId'] ?? ''));
        $concat = $size.$item;
        if ($concat === '') {
            return $data;
        }
        if (trim((string) ($data['TamanoClave'] ?? '')) === '') {
            $data['TamanoClave'] = $concat;
        }
        if (trim((string) ($data['ClaveModelo'] ?? '')) === '') {
            $data['ClaveModelo'] = $concat;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDeRequest(Request $request): array
    {
        $data = $this->completarClaves($request->only(array_keys(self::CAMPOS_MODELO)));

        return $this->payloadSegunSalon($data);
    }

    /** API: crear */
    public function store(Request $request): JsonResponse
    {
        $data = $this->datosDeRequest($request);
        $validator = Validator::make($data, $this->getValidationRules(true));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validación incorrecta',
                'errors' => $validator->errors(),
            ], 422);
        }

        $codificacion = ReqModelosCodificados::create($data);
        $this->clearCodificacionCache((int) $codificacion->Id);

        return response()->json([
            'success' => true,
            'message' => 'Registro creado',
            'data' => $codificacion,
        ], 201);
    }

    /** API: actualizar */
    public function update(Request $request, $id): JsonResponse
    {
        $codificacion = ReqModelosCodificados::find($id);
        if (! $codificacion) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        $data = $this->datosDeRequest($request);
        $validator = Validator::make($data, $this->getValidationRules(false));
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validación incorrecta',
                'errors' => $validator->errors(),
            ], 422);
        }

        $codificacion->update($data);
        $this->clearCodificacionCache((int) $codificacion->Id);

        return response()->json([
            'success' => true,
            'message' => 'Registro actualizado',
            'data' => $codificacion,
        ]);
    }

    /** API: eliminar */
    public function destroy($id): JsonResponse
    {
        $codificacion = ReqModelosCodificados::find($id);
        if (! $codificacion) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        // Verificar si la clave mod (campo Clave) está siendo utilizada en ReqProgramaTejido
        // La clave mod se relaciona con TamanoClave en ReqProgramaTejido
        $claveMod = $codificacion->Clave;
        if ($claveMod) {
            $enUso = DB::table('ReqProgramaTejido')
                ->where('TamanoClave', $claveMod)
                ->exists();

            if ($enUso) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el registro porque la clave mod "'.$claveMod.'" está siendo utilizada en Programa Tejido',
                ], 422);
            }
        }

        $codificacion->delete();
        $this->clearCodificacionCache((int) $codificacion->Id);

        return response()->json(['success' => true, 'message' => 'Registro eliminado']);
    }

    /** API: duplicar registro */
    public function duplicate($id): JsonResponse
    {
        try {
            $original = ReqModelosCodificados::find($id);

            if (! $original) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado',
                ], 404);
            }

            // Obtener todos los atributos del modelo original
            $attributes = $original->getAttributes();

            // Eliminar el ID para que se cree un nuevo registro
            unset($attributes['Id']);

            // Crear un nuevo registro con los mismos datos
            $duplicado = ReqModelosCodificados::create($attributes);

            $this->clearCodificacionCache((int) $duplicado->Id);

            return response()->json([
                'success' => true,
                'message' => 'Registro duplicado exitosamente',
                'data' => $duplicado,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CodificacionController::duplicate', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al duplicar el registro: '.$e->getMessage(),
            ], 500);
        }
    }

    /** Procesar Excel */
    public function procesarExcel(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240',
            ]);

            $importId = (string) Str::uuid();
            $path = $request->file('archivo_excel')->getRealPath();
            $ext = strtolower($request->file('archivo_excel')->getClientOriginalExtension());

            $totalRows = null;
            try {
                $reader = $ext === 'xls' ? new XlsReader : new XlsxReader;
                $info = $reader->listWorksheetInfo($path);
                $totalRows = isset($info[0]['totalRows']) ? max(0, (int) $info[0]['totalRows'] - 2) : null;
            } catch (\Throwable $e) {
                Log::warning('No se pudo obtener totalRows: '.$e->getMessage());
            }

            Excel::queueImport(new ReqModelosCodificadosImport($importId, $totalRows), $request->file('archivo_excel'));

            return response()->json([
                'success' => true,
                'message' => 'Import encolado',
                'data' => [
                    'import_id' => $importId,
                    'total_rows' => $totalRows,
                    'poll_url' => '/planeacion/catalogos/codificacion-modelos/excel-progress/'.$importId,
                ],
            ], 202);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validación fallida', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error importación Excel', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }

    /** Consultar progreso del import */
    public function importProgress($id): JsonResponse
    {
        $state = Cache::get('excel_import_progress:'.$id);
        if (! $state) {
            return response()->json(['success' => false, 'message' => 'Progreso no encontrado'], 404);
        }

        $pct = ! empty($state['total_rows']) && $state['total_rows'] > 0
            ? round(100 * (($state['processed_rows'] ?? 0) / $state['total_rows']), 1)
            : null;

        $errors = array_map(fn ($e) => [
            'fila' => $e['fila'] ?? 'N/A',
            'error' => substr($e['error'] ?? 'Error desconocido', 0, 150),
        ], $state['errors'] ?? []);

        return response()->json([
            'success' => true,
            'data' => $state,
            'percent' => $pct,
            'errors' => $errors,
            'has_errors' => ! empty($errors),
        ]);
    }

    /** Obtener salones y números de telar */
    public function getSalonesYTelares(): JsonResponse
    {
        try {
            // Usar Query Builder de Laravel
            // Las columnas correctas son: TipoTelar (equivalente a SalonTejidoId) y NoTelar (equivalente a NoTelarId)
            $data = DB::table('InvSecuenciaTelares')
                ->select('TipoTelar', 'NoTelar')
                ->whereNotNull('TipoTelar')
                ->whereNotNull('NoTelar')
                ->distinct()
                ->orderBy('TipoTelar')
                ->orderBy('NoTelar')
                ->get();

            // Agrupar por salón (TipoTelar)
            $salones = [];
            $telaresPorSalon = [];
            $telaresITEMASMIT = []; // Agrupar telares de ITEMA y SMIT juntos

            foreach ($data as $item) {
                // Acceder a las propiedades
                $salon = $item->TipoTelar ?? null;
                $telar = $item->NoTelar ?? null;

                // Validar que no sean null o vacíos
                if (empty($salon) || empty($telar)) {
                    continue;
                }

                // Convertir a string y limpiar
                $salon = trim((string) $salon);
                $telar = trim((string) $telar);

                // Saltar si están vacíos después de trim
                if ($salon === '' || $telar === '') {
                    continue;
                }

                // Convertir telar a string para consistencia
                $telarStr = (string) $telar;

                // InvSecuenciaTelares guarda KM; el form y ReqModelosCodificados usan KARL MAYER.
                $canon = TelarSalonResolver::normalizeSalon($salon, $telarStr);
                if ($canon === 'KARL MAYER') {
                    $salon = 'KARL MAYER';
                }

                // ITEMA se trata como SMIT - unificar ambos
                if ($salon === 'ITEMA' || $salon === 'SMIT') {
                    // Agrupar todos los telares de ITEMA y SMIT juntos bajo SMIT
                    if (! in_array($telarStr, $telaresITEMASMIT, true)) {
                        $telaresITEMASMIT[] = $telarStr;
                    }
                    // Agregar ITEMA a la lista de salones (se mostrará como ITEMA pero se manejará como SMIT)
                    if ($salon === 'ITEMA' && ! in_array('ITEMA', $salones, true)) {
                        $salones[] = 'ITEMA';
                    }
                    // También agregar SMIT si existe en la base de datos
                    if ($salon === 'SMIT' && ! in_array('SMIT', $salones, true)) {
                        $salones[] = 'SMIT';
                    }
                } else {
                    // Otros salones normales
                    if (! isset($telaresPorSalon[$salon])) {
                        $telaresPorSalon[$salon] = [];
                    }

                    if (! in_array($telarStr, $telaresPorSalon[$salon], true)) {
                        $telaresPorSalon[$salon][] = $telarStr;
                    }

                    // Agregar salón único
                    if (! in_array($salon, $salones, true)) {
                        $salones[] = $salon;
                    }
                }
            }

            // Asignar telares compartidos a SMIT e ITEMA (ITEMA se trata como SMIT)
            if (! empty($telaresITEMASMIT)) {
                sort($telaresITEMASMIT);
                // Siempre asignar a SMIT
                $telaresPorSalon['SMIT'] = $telaresITEMASMIT;
                // Siempre asignar a ITEMA también (son los mismos telares)
                $telaresPorSalon['ITEMA'] = $telaresITEMASMIT;
            }

            if (! in_array('KARL MAYER', $salones, true)) {
                $salones[] = 'KARL MAYER';
            }
            $kmTelares = $telaresPorSalon['KARL MAYER'] ?? [];
            foreach (['401', '402'] as $telarKm) {
                if (! in_array($telarKm, $kmTelares, true)) {
                    $kmTelares[] = $telarKm;
                }
            }
            $telaresPorSalon['KARL MAYER'] = $kmTelares;

            // Ordenar salones y telares
            sort($salones);
            foreach ($telaresPorSalon as $salon => $telares) {
                if (is_array($telares)) {
                    sort($telaresPorSalon[$salon]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'salones' => array_values($salones),
                    'telaresPorSalon' => $telaresPorSalon,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error obteniendo salones y telares', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener salones y telares: '.$e->getMessage(),
                'debug' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ] : null,
            ], 500);
        }
    }

    /** Estadísticas - Optimizado para usar índices */
    public function estadisticas(): JsonResponse
    {
        // ⚡ OPTIMIZACIÓN: Usar índices para estadísticas
        // Cachear estadísticas por 5 minutos
        return response()->json([
            'success' => true,
            'data' => Cache::remember('codificacion_estadisticas', 300, function () {
                return [
                    'total_registros' => ReqModelosCodificados::count(),
                    // ⚡ Usar índice IX_RMC_Salon_FechaTejido para groupBy por salón
                    'por_salon' => DB::table('ReqModelosCodificados')
                        ->select('SalonTejidoId', DB::raw('count(*) as total'))
                        ->whereNotNull('SalonTejidoId')
                        ->groupBy('SalonTejidoId')
                        ->orderBy('SalonTejidoId')
                        ->get(),
                    'por_prioridad' => ReqModelosCodificados::selectRaw('Prioridad, count(*) as total')
                        ->whereNotNull('Prioridad')
                        ->groupBy('Prioridad')
                        ->orderBy('Prioridad')
                        ->get(),
                ];
            }),
        ]);
    }

    /**
     * Obtener datos de TwFlogsTable basado en ItemId e InventSizeId
     */
    public function getFlogsData(Request $request): JsonResponse
    {
        try {
            $itemId = trim($request->input('item_id', ''));
            $inventSizeId = trim($request->input('invent_size_id', ''));

            if (empty($itemId) || empty($inventSizeId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ItemId e InventSizeId son requeridos',
                ], 400);
            }

            $flogs = DBFacade::connection('sqlsrv_ti')
                ->table('dbo.TwFlogsItemLine as fil')
                ->join('dbo.TwFlogsTable as ft', 'ft.IDFLOG', '=', 'fil.IDFLOG')
                ->select('ft.IDFLOG', 'ft.NAMEPROYECT', 'ft.CUSTNAME')
                ->whereRaw('LTRIM(RTRIM(fil.ITEMID)) = ?', [$itemId])
                ->whereRaw('LTRIM(RTRIM(fil.INVENTSIZEID)) = ?', [$inventSizeId])
                ->whereIn('ft.ESTADOFLOG', [3, 4, 5, 21])
                ->orderByDesc('ft.IDFLOG')
                ->first();

            if (! $flogs) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron datos para la combinación de Clave AX y Tamaño',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'idflog' => $flogs->IDFLOG ?? null,
                    'nombre' => $flogs->NAMEPROYECT ?? '',
                    'custname' => $flogs->CUSTNAME ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CodificacionController::getFlogsData', [
                'error' => $e->getMessage(),
                'item_id' => $request->input('item_id'),
                'invent_size_id' => $request->input('invent_size_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Buscar en CatCodificados por Orden de Trabajo para importar datos
     */
    public function getCatCodificadosByOrden(Request $request): JsonResponse
    {
        $orden = trim((string) $request->query('orden_trabajo', $request->query('orden', '')));
        $clave = trim((string) $request->query('clave_modelo', ''));
        $item = trim((string) $request->query('item_id', ''));
        $size = trim((string) $request->query('invent_size_id', ''));

        if ($orden === '' && $clave === '' && ($item === '' || $size === '')) {
            return response()->json([
                'success' => false,
                'message' => 'Indica orden de tejido, clave modelo o Clave AX + Tamaño',
            ], 422);
        }

        $registro = $this->findCatCodificadoSimilar([
            'orden' => $orden,
            'clave_modelo' => $clave,
            'item_id' => $item,
            'invent_size_id' => $size,
        ]);
        if (! $registro) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró un modelo similar en CatCodificados',
            ], 404);
        }

        $claveMod = trim((string) ($registro->ClaveModelo ?? ''));
        if ($claveMod === '' || $claveMod === '0') {
            $claveMod = trim((string) ($registro->Clave ?? ''));
        }

        $campos = $this->volcarConstruccionViejaABarrasSiKm(
            $this->mapCatCodificadosToReq($registro),
            $request->query('salon')
        );
        $itemId = trim((string) ($registro->ItemId ?? ''));
        $sizeId = trim((string) ($registro->InventSizeId ?? ''));
        $concat = $sizeId.$itemId;
        if (trim((string) ($campos['TamanoClave'] ?? '')) === '' && $concat !== '') {
            $campos['TamanoClave'] = $concat;
        }
        if (trim((string) ($campos['ClaveModelo'] ?? '')) === '' && $concat !== '') {
            $campos['ClaveModelo'] = $concat;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'orden_trabajo' => trim((string) ($registro->OrdenTejido ?? $orden)),
                'salon' => trim((string) ($registro->Departamento ?? '')),
                'clave_mod' => $claveMod,
                'clave_ax' => $itemId,
                'tamano' => $sizeId,
                'nombre' => trim((string) ($registro->Nombre ?? '')),
                'campos' => $campos,
            ],
        ]);
    }

    public function modeloSimilar(Request $request): JsonResponse
    {
        $origen = strtolower(trim((string) $request->query('origen', '')));
        $por = strtolower(trim((string) $request->query('por', '')));
        $valor = trim((string) $request->query('valor', ''));
        $excluir = $request->query('excluir_id');

        if (! in_array($origen, ['req', 'cat'], true) || ! in_array($por, ['orden', 'clave'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Indica de dónde (esta tabla o CatCodificados) y cómo (orden o clave modelo)',
            ], 422);
        }

        if ($valor === '') {
            return response()->json([
                'success' => false,
                'message' => $por === 'orden' ? 'Escribe la orden de tejido' : 'Escribe la clave modelo',
            ], 422);
        }

        if ($por === 'orden' && ! preg_match('/^\d+$/', $valor)) {
            return response()->json([
                'success' => false,
                'message' => 'La orden de tejido solo lleva números',
            ], 422);
        }

        $q = [
            'orden' => $por === 'orden' ? $valor : '',
            'clave_modelo' => $por === 'clave' ? $valor : '',
            'excluir_id' => $excluir,
        ];

        if ($origen === 'cat') {
            $registro = $this->findCatCodificadoSimilar($q);
            if (! $registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay un modelo con esos datos en CatCodificados',
                ], 404);
            }

            $claveMod = trim((string) ($registro->ClaveModelo ?? ''));
            if ($claveMod === '' || $claveMod === '0') {
                $claveMod = trim((string) ($registro->Clave ?? ''));
            }
            $campos = $this->volcarConstruccionViejaABarrasSiKm(
                $this->mapCatCodificadosToReq($registro),
                $request->query('salon')
            );
            $itemId = trim((string) ($registro->ItemId ?? ''));
            $sizeId = trim((string) ($registro->InventSizeId ?? ''));
            $concat = $sizeId.$itemId;
            if (trim((string) ($campos['TamanoClave'] ?? '')) === '' && $concat !== '') {
                $campos['TamanoClave'] = $concat;
            }
            if (trim((string) ($campos['ClaveModelo'] ?? '')) === '' && $concat !== '') {
                $campos['ClaveModelo'] = $concat;
            }

            return response()->json($this->respuestaModeloSimilar(
                'cat',
                trim((string) ($registro->OrdenTejido ?? $q['orden'])),
                $claveMod,
                $itemId,
                $sizeId,
                trim((string) ($registro->Nombre ?? '')),
                $campos
            ));
        }

        $registro = $this->findReqModeloSimilar($q);
        if (! $registro) {
            return response()->json([
                'success' => false,
                'message' => 'No hay un modelo con esos datos en esta tabla',
            ], 404);
        }

        $campos = array_intersect_key(
            $registro->getAttributes(),
            array_flip(array_keys(self::CAMPOS_MODELO))
        );
        unset($campos['Id']);
        $campos = $this->volcarConstruccionViejaABarrasSiKm($campos, $request->query('salon'));

        return response()->json($this->respuestaModeloSimilar(
            'req',
            trim((string) ($registro->OrdenTejido ?? $q['orden'])),
            trim((string) ($registro->ClaveModelo ?: $registro->TamanoClave ?: '')),
            trim((string) ($registro->ItemId ?? '')),
            trim((string) ($registro->InventSizeId ?? '')),
            trim((string) ($registro->Nombre ?? '')),
            $campos
        ));
    }

    /**
     * Duplicar o importar registros de codificación
     */
    public function duplicarImportar(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'registro_id_original' => 'required|integer|exists:ReqModelosCodificados,Id',
                'modo' => 'required|in:duplicar,importar',
                'datos' => 'required|array',
                'datos.*.orden_trabajo' => 'required_if:modo,importar|string',
                'datos.*.salon' => 'required|string',
                'datos.*.clave_mod' => 'required|string',
                'datos.*.clave_ax' => 'required|string',
                'datos.*.nombre' => 'required|string',
                'datos.*.tamano' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $registroOriginalId = $request->input('registro_id_original');
            $modo = $request->input('modo');
            $datos = $request->input('datos');

            // Obtener el registro original
            $registroOriginal = ReqModelosCodificados::find($registroOriginalId);
            if (! $registroOriginal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro original no encontrado',
                ], 404);
            }

            $registrosCreados = [];
            $columns = $this->getTableColumns('ReqModelosCodificados');
            $lengths = $this->getColumnMaxLengths('ReqModelosCodificados');
            $intColumns = $this->getIntColumns('ReqModelosCodificados');
            $hasCustName = Schema::hasColumn('ReqModelosCodificados', 'CustName');
            DB::beginTransaction();

            try {
                foreach ($datos as $dato) {
                    if ($modo === 'importar') {
                        $ordenTrabajo = trim((string) ($dato['orden_trabajo'] ?? ''));
                        $catRegistro = $this->findCatCodificadoByOrden($ordenTrabajo);
                        if (! $catRegistro) {
                            DB::rollBack();

                            return response()->json([
                                'success' => false,
                                'message' => "No se encontro CatCodificados para orden {$ordenTrabajo}",
                            ], 404);
                        }

                        $data = $this->mapCatCodificadosToReq($catRegistro);

                        $data['OrdenTejido'] = $ordenTrabajo;
                        $data['SalonTejidoId'] = $dato['salon'] ?? $data['SalonTejidoId'];
                        $data['TamanoClave'] = $dato['clave_mod'] ?? $data['TamanoClave'];
                        $data['ItemId'] = $dato['clave_ax'] ?? $data['ItemId'];
                        $data['InventSizeId'] = $dato['tamano'] ?? $data['InventSizeId'];
                        $data['Nombre'] = $dato['nombre'] ?? $data['Nombre'];

                        if (isset($dato['idflog']) && $dato['idflog'] !== '') {
                            $data['FlogsId'] = $dato['idflog'];
                        }
                        if (isset($dato['custname']) && $dato['custname'] !== '') {
                            $data['NombreProyecto'] = $dato['custname'];
                            if ($hasCustName) {
                                $data['CustName'] = $dato['custname'];
                            }
                        }

                        $data = $this->normalizeDataForTable($data, $columns, $lengths, $intColumns);
                        $nuevoRegistro = new ReqModelosCodificados;
                        $nuevoRegistro->forceFill($data);
                        $nuevoRegistro->save();
                        $registrosCreados[] = $nuevoRegistro->Id;

                        continue;
                    }

                    // Modo duplicar: Crear una copia del registro original
                    $nuevoRegistro = $registroOriginal->replicate();

                    // Actualizar solo los campos especificados
                    $nuevoRegistro->SalonTejidoId = $this->truncateValueForColumn('SalonTejidoId', $dato['salon'], $lengths);
                    $nuevoRegistro->TamanoClave = $this->truncateValueForColumn('TamanoClave', $dato['clave_mod'], $lengths);
                    $nuevoRegistro->ItemId = $this->truncateValueForColumn('ItemId', $dato['clave_ax'], $lengths);
                    $nuevoRegistro->Nombre = $this->truncateValueForColumn('Nombre', $dato['nombre'], $lengths);
                    $nuevoRegistro->InventSizeId = $this->truncateValueForColumn('InventSizeId', $dato['tamano'], $lengths);

                    // Si viene idflog y custname (modo duplicar), actualizarlos
                    if (isset($dato['idflog'])) {
                        $nuevoRegistro->FlogsId = $this->truncateValueForColumn('FlogsId', $dato['idflog'], $lengths);
                    }
                    if (isset($dato['custname']) && $dato['custname'] !== '') {
                        $nuevoRegistro->NombreProyecto = $this->truncateValueForColumn('NombreProyecto', $dato['custname'], $lengths);
                        if ($hasCustName) {
                            $nuevoRegistro->CustName = $this->truncateValueForColumn('CustName', $dato['custname'], $lengths);
                        }
                    }

                    $nuevoRegistro->save();
                    $registrosCreados[] = $nuevoRegistro->Id;
                }

                DB::commit();
                $this->clearCodificacionCache();

                return response()->json([
                    'success' => true,
                    'message' => count($registrosCreados).' registro(s) creado(s) correctamente',
                    'registros_ids' => $registrosCreados,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('CodificacionController::duplicarImportar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear los registros: '.$e->getMessage(),
            ], 500);
        }
    }
}
