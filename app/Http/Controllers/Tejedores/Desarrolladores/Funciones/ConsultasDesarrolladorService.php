<?php
namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Sistema\Usuario;
use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Helpers\TelDesarrolladoresHelper;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ConsultasDesarrolladorService
{
    /**
     * Obtiene los datos necesarios para cargar la vista principal de desarrolladores.
     *
     * @return array{telares: \Illuminate\Support\Collection, telaresDestino: \Illuminate\Support\Collection, juliosRizo: \Illuminate\Support\Collection, juliosPie: \Illuminate\Support\Collection, desarrolladores: \Illuminate\Support\Collection, desarrolladorActual: string|null}
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
    private function obtenerTelaresDestino(): Collection
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
                    'value' => $salon . '|' . $telar,
                    'label' => $telar . ' (' . $salon . ')',
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, AtaMontadoTelasModel>
     */
    private function obtenerJuliosPorTipo(string $tipo): Collection
    {
        return AtaMontadoTelasModel::query()
            ->whereNotNull('NoJulio')
            ->where('NoJulio', '!=', '')
            ->where('Tipo', $tipo)
            ->orderByDesc('Fecha')
            ->get(['NoJulio', 'InventSizeId', 'Fecha'])
            ->unique('NoJulio')
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Usuario>
     */
    private function obtenerDesarrolladores(): Collection
    {
        $usuarioActual = Auth::user();

        $desarrolladores = Usuario::porArea('Desarrolladores')
            ->activos()
            ->get();

        if ($usuarioActual && !$desarrolladores->contains('idusuario', $usuarioActual->idusuario)) {
            $usuarioParaLista = $usuarioActual instanceof Usuario ? $usuarioActual : Usuario::find($usuarioActual->idusuario);
            if ($usuarioParaLista) {
                $desarrolladores = collect([$usuarioParaLista])->merge($desarrolladores)->sortBy('nombre')->values();
            }
        }

        return $desarrolladores;
    }

    /**
     * Obtiene las producciones disponibles para un telar específico.
     *
     * @param string $telarId
     * @return array
     */
    public function obtenerProducciones($telarId): array
    {
        try {
            $producciones = ReqProgramaTejido::where('NoTelarId', $telarId)
                ->where(function ($query) {
                    $query->where('EnProceso', 0);
                })
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->select('SalonTejidoId', 'NoProduccion', 'FechaInicio', 'TamanoClave', 'NombreProducto')
                ->distinct()
                ->orderBy('FechaInicio', 'asc')
                ->get();

            return [
                'success' => true,
                'producciones' => $producciones
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener las producciones: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene los detalles de la orden para una producción determinada.
     *
     * @param string $noProduccion
     * @return array
     */
    public function obtenerDetallesOrden($noProduccion): array
    {
        try {
            $ordenData = ReqProgramaTejido::where('NoProduccion', $noProduccion)->first();
            $detalles = [];

            $isZeroish = static function ($value): bool {
                $text = trim((string) ($value ?? ''));
                if ($text === '') return true;
                return (bool) preg_match('/^0+(?:\.0+)?$/', $text);
            };

            $shouldIncludeDetalle = static function (array $fila) use ($isZeroish): bool {
                $calibre = trim((string) ($fila['Calibre'] ?? ''));
                if ($calibre === '') return false;

                $keys = ['Calibre', 'Hilo', 'Fibra', 'CodColor', 'NombreColor', 'Pasadas'];
                foreach ($keys as $key) {
                    if (!$isZeroish($fila[$key] ?? '')) {
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

            return [
                'success' => true,
                'detalles' => $detalles
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener los detalles: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta el código de dibujo de ReqModelosCodificados.
     *
     * @param string $salonTejidoId
     * @param string $tamanoClave
     * @return array
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

            if (!$codigoDibujo) {
                return [
                    'success' => false,
                    'message' => 'No se encontró CodigoDibujo para los parámetros proporcionados.'
                ];
            }

            return [
                'success' => true,
                'codigoDibujo' => $codigoDibujo
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener CodigoDibujo'
            ];
        }
    }

    /**
     * Obtiene información preexistente de CatCodificados para un Telar y Producción.
     *
     * @param string $telarId
     * @param string $noProduccion
     * @return array
     */
    public function obtenerRegistroCatCodificado($telarId, $noProduccion): array
    {
        try {
            $modelo = new CatCodificados();
            $table = $modelo->getTable();
            $columns = Schema::getColumnListing($table);

            $query = CatCodificados::query();
            $hasOrderFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $noProduccion);
                $hasOrderFilter = true;
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $noProduccion);
                $hasOrderFilter = true;
            }

            if (in_array('TelarId', $columns, true)) {
                $query->where('TelarId', $telarId);
            } elseif (in_array('NoTelarId', $columns, true)) {
                $query->where('NoTelarId', $telarId);
            }

            if (!$hasOrderFilter) {
                $query->where('NoProduccion', $noProduccion);
            }

            $registro = $query->select([
                'JulioRizo', 'JulioPie', 'EfiInicial', 'EfiFinal', 'DesperdicioTrama',
            ])->first();

            if (!$registro) {
                return [
                    'success' => false,
                    'message' => 'No se encontró información registrada'
                ];
            }

            return [
                'success' => true,
                'registro' => $registro,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener la información'
            ];
        }
    }
}
