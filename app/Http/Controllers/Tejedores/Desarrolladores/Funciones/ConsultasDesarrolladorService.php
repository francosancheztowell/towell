<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Helpers\TelDesarrolladoresHelper;
use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\Usuario;
use App\Models\Tejedores\TelTelaresOperador;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ConsultasDesarrolladorService
{
    protected CatCodificadosDesarrolladorService $catCodificadosService;

    public function __construct(?CatCodificadosDesarrolladorService $catCodificadosService = null)
    {
        $this->catCodificadosService = $catCodificadosService ?? app(CatCodificadosDesarrolladorService::class);
    }

    /**
     * Obtiene los datos necesarios para cargar la vista principal de desarrolladores.
     *
     * @return array{telares: Collection, telaresDestino: Collection, juliosRizo: Collection, juliosPie: Collection, desarrolladores: Collection, desarrolladorActual: string|null}
     */
    public function obtenerDatosIndex(): array
    {
        return [
            'telares' => $this->obtenerTelares(),
            'telaresDestino' => $this->obtenerTelaresDestino(),
            'juliosRizo' => $this->obtenerJuliosPorTipo('Rizo'),
            'juliosPie' => $this->obtenerJuliosPorTipo('Pie'),
            'desarrolladores' => $this->obtenerDesarrolladores(),
            'desarrolladorActual' => Auth::user()?->nombre,
        ];
    }

    /**
     * @return EloquentCollection<int, TelTelaresOperador>
     */
    private function obtenerTelares(): EloquentCollection
    {
        return TelTelaresOperador::select('NoTelarId')
            ->whereNotNull('NoTelarId')
            ->groupBy('NoTelarId')
            ->orderBy('NoTelarId')
            ->get();
    }

    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    public function obtenerTelaresDestino(): Collection
    {
        return ReqProgramaTejido::query()
            ->select('SalonTejidoId', 'NoTelarId')
            ->whereNotNull('SalonTejidoId')
            ->whereNotNull('NoTelarId')
            ->where('NoTelarId', '!=', '')
            ->distinct()
            ->orderBy('SalonTejidoId')
            ->orderBy('NoTelarId')
            ->get()
            ->map(static function ($row) {
                $salon = trim((string) ($row->SalonTejidoId ?? ''));
                $telar = trim((string) ($row->NoTelarId ?? ''));

                return [
                    'value' => $salon.'|'.$telar,
                    'label' => $telar,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, AtaMontadoTelasModel>
     */
    private function obtenerJuliosPorTipo(string $tipo, ?string $telarId = null): Collection
    {
        // ponytail: el unique() va en PHP a proposito. Medido en produccion,
        // AtaMontadoTelas tiene 1780 filas en total, asi que no compensa montar un
        // ROW_NUMBER() OVER (PARTITION BY NoJulio) para deduplicar. Si algun dia la
        // tabla crece un orden de magnitud, esa es la salida.

        $query = AtaMontadoTelasModel::query()
            ->whereNotNull('NoJulio')
            ->where('NoJulio', '!=', '')
            ->where('Tipo', $tipo)
            ->orderByDesc('Fecha');

        if ($telarId !== null && trim($telarId) !== '') {
            $query->where('NoTelarId', trim($telarId));
        }

        return $query->get(['NoJulio', 'InventSizeId', 'ConfigId', 'Fecha'])
            ->unique('NoJulio')
            ->values();
    }

    /**
     * Obtiene julios de rizo y pie filtrados por telar.
     */
    public function obtenerJuliosPorTelar(string $telarId): array
    {
        try {
            return [
                'success' => true,
                'juliosRizo' => $this->obtenerJuliosPorTipo('Rizo', $telarId),
                'juliosPie' => $this->obtenerJuliosPorTipo('Pie', $telarId),
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener los julios', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error al obtener los julios.',
            ];
        }
    }

    /**
     * @return Collection<int, Usuario>
     */
    private function obtenerDesarrolladores(): Collection
    {
        $usuarioActual = Auth::user();

        $desarrolladores = Usuario::porArea('Desarrolladores')
            ->activos()
            ->get();

        if ($usuarioActual && ! $desarrolladores->contains('idusuario', $usuarioActual->idusuario)) {
            $usuarioParaLista = $usuarioActual instanceof Usuario ? $usuarioActual : Usuario::find($usuarioActual->idusuario);
            if ($usuarioParaLista) {
                $desarrolladores = collect([$usuarioParaLista])->merge($desarrolladores)->sortBy('nombre')->values();
            }
        }

        return $desarrolladores;
    }

    /**
     * Obtiene las producciones disponibles para un telar específico.
     */
    public function obtenerProducciones(string $telarId): array
    {
        try {
            $query = ReqProgramaTejido::where('NoTelarId', $telarId)
                ->where('EnProceso', 0)
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '');

            $producciones = $query->select('Id', 'SalonTejidoId', 'NoProduccion', 'FechaInicio', 'TamanoClave', 'NombreProducto')
                ->distinct()
                ->orderBy('FechaInicio', 'asc')
                ->get();

            return [
                'success' => true,
                'producciones' => $producciones,
            ];
        } catch (Exception $e) {
            Log::error('Error al obtener las producciones', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error al obtener las producciones.',
            ];
        }
    }

    /**
     * Obtiene los detalles de un registro sin orden, buscando por Id.
     */
    public function obtenerDetallesOrdenPorId(int $id): array
    {
        try {
            $ordenData = ReqProgramaTejido::find($id);

            return $this->buildDetallesFromOrdenData($ordenData);
        } catch (Exception $e) {
            Log::error('Error al obtener los detalles', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error al obtener los detalles.',
            ];
        }
    }

    /**
     * Obtiene los detalles de la orden para una producción determinada.
     *
     * @param  string  $noProduccion
     */
    public function obtenerDetallesOrden($noProduccion): array
    {
        try {
            $ordenData = ReqProgramaTejido::where('NoProduccion', $noProduccion)->first();

            return $this->buildDetallesFromOrdenData($ordenData);
        } catch (Exception $e) {
            Log::error('Error al obtener los detalles', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error al obtener los detalles.',
            ];
        }
    }

    /**
     * Consulta el código de dibujo de ReqModelosCodificados.
     *
     * @param  string  $salonTejidoId
     * @param  string  $tamanoClave
     */
    public function obtenerCodigoDibujo($salonTejidoId, $tamanoClave): array
    {
        try {
            $codigoDibujo = ReqModelosCodificados::query()
                ->where('SalonTejidoId', $salonTejidoId)
                ->where('TamanoClave', $tamanoClave)
                ->whereNotNull('CodigoDibujo')
                ->orderByDesc('Id')
                ->value('CodigoDibujo');

            if (! $codigoDibujo) {
                return [
                    'success' => false,
                    'message' => 'No se encontró CodigoDibujo para los parámetros proporcionados.',
                ];
            }

            return [
                'success' => true,
                'codigoDibujo' => $codigoDibujo,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener CodigoDibujo',
            ];
        }
    }

    /**
     * Obtiene información preexistente de CatCodificados para un Telar y Producción.
     *
     * @param  string  $telarId
     * @param  string  $noProduccion
     */
    public function obtenerRegistroCatCodificado($telarId, $noProduccion): array
    {
        try {
            $registro = $this->catCodificadosService
                ->resolveForRead((string) $noProduccion, (string) $telarId);

            if ($registro) {
                $registro = $registro->only([
                    'JulioRizo', 'JulioPie', 'EfiInicial', 'EfiFinal', 'DesperdicioTrama',
                ]);
            }

            if (! $registro) {
                return [
                    'success' => false,
                    'message' => 'No se encontró información registrada',
                ];
            }

            return [
                'success' => true,
                'registro' => $registro,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener la información',
            ];
        }
    }

    private function buildDetallesFromOrdenData(?ReqProgramaTejido $ordenData): array
    {
        $detalles = [];

        $isZeroish = static function ($value): bool {
            $text = trim((string) ($value ?? ''));
            if ($text === '') {
                return true;
            }

            return (bool) preg_match('/^0+(?:\.0+)?$/', $text);
        };

        $shouldIncludeDetalle = static function (array $fila) use ($isZeroish): bool {
            $calibre = trim((string) ($fila['Calibre'] ?? ''));
            if ($calibre === '') {
                return false;
            }

            foreach (['Calibre', 'Hilo', 'Fibra', 'CodColor', 'NombreColor', 'Pasadas'] as $key) {
                if (! $isZeroish($fila[$key] ?? '')) {
                    return true;
                }
            }

            return false;
        };

        if ($ordenData) {
            $filaTrama = TelDesarrolladoresHelper::mapDetalleFila(
                $ordenData, 'CalibreTrama', 'CalibreTrama2', 'FibraTrama',
                'CodColorTrama', 'ColorTrama', 'PasadasTrama'
            );
            if ($shouldIncludeDetalle($filaTrama)) {
                $detalles[] = $filaTrama;
            }

            for ($i = 1; $i <= 5; $i++) {
                $filaComb = TelDesarrolladoresHelper::mapDetalleFila(
                    $ordenData, "CalibreComb{$i}", "CalibreComb{$i}2", "FibraComb{$i}",
                    "CodColorComb{$i}", $ordenData->{"NombreCC{$i}"} !== null ? "NombreCC{$i}" : "NomColorC{$i}",
                    "PasadasComb{$i}"
                );
                if ($shouldIncludeDetalle($filaComb)) {
                    $detalles[] = $filaComb;
                }
            }
        }

        return ['success' => true, 'detalles' => $detalles];
    }
}
