<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\EliminarTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\UpdateTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use App\Models\Planeacion\OrdColProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoReadComparison;
use App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * @file ProgramaTejidoController.php
 *
 * @description Controlador principal para el programa de tejido. Mantiene: index, update,
 *              destroy, destroyEnProceso. Catálogos, operaciones, balanceo y calendarios están
 *              en ProgramaTejido*Controller dedicados. También sirve la vista de Muestras.
 *              El alta va por ProgramaTejidoOperacionesController (duplicar/dividir).
 *
 * @dependencies UpdateTejido, EliminarTejido, UtilityHelpers
 */
class ProgramaTejidoController extends Controller
{
    public function index()
    {
        // La superficie llega explícita a la vista (PT-02): antes $isMuestras se calculaba y
        // no se pasaba, así que Muestras mostraba Redbooth y acciones que no soporta.
        $superficie = ProgramaTejidoSurface::actual();
        $contexto = [
            'superficie' => $superficie,
            'isMuestras' => $superficie->esMuestras(),
            'capacidades' => $superficie->capacidades(),
            'basePath' => $superficie->basePath(),
            'apiPath' => $superficie->apiPath(),
            'linePath' => $superficie->linePath(),
            'pageTitle' => $superficie->titulo(),
        ];

        try {
            $registros = ReqProgramaTejido::select([
                'Id',
                'EnProceso',
                'Reprogramar',
                'CuentaRizo',
                'CalibreRizo2',
                'SalonTejidoId',
                'NoTelarId',
                'Posicion',
                'Ultimo',
                'CambioHilo',
                'Maquina',
                'Ancho',
                'EficienciaSTD',
                'VelocidadSTD',
                'FibraRizo',
                'CalibrePie2',
                'CalendarioId',
                'TamanoClave',
                'NoExisteBase',
                'ItemId',
                'InventSizeId',
                'Rasurado',
                'NombreProducto',
                'TotalPedido',
                'PorcentajeSegundos',
                'Produccion',
                'SaldoPedido',
                'SaldoMarbete',
                'ProgramarProd',
                'OrdCompartida',
                'NoProduccion',
                'Programado',
                'FlogsId',
                'CategoriaCalidad',
                'NombreProyecto',
                'CustName',
                'AplicacionId',
                'Observaciones',
                'TipoPedido',
                'NoTiras',
                'Peine',
                'Luchaje',
                'PesoCrudo',
                'LargoCrudo',
                'CalibreTrama2',
                'FibraTrama',
                'DobladilloId',
                'PasadasTrama',
                'PasadasComb1',
                'PasadasComb2',
                'PasadasComb3',
                'PasadasComb4',
                'PasadasComb5',
                'AnchoToalla',
                'CodColorTrama',
                'ColorTrama',
                'CalibreComb1',
                'FibraComb1',
                'CodColorComb1',
                'NombreCC1',
                'CalibreComb2',
                'FibraComb2',
                'CodColorComb2',
                'NombreCC2',
                'CalibreComb3',
                'FibraComb3',
                'CodColorComb3',
                'NombreCC3',
                'CalibreComb4',
                'FibraComb4',
                'CodColorComb4',
                'NombreCC4',
                'CalibreComb5',
                'FibraComb5',
                'CodColorComb5',
                'NombreCC5',
                'MedidaPlano',
                'CuentaPie',
                'CodColorCtaPie',
                'NombreCPie',
                'PesoGRM2',
                'DiasEficiencia',
                'ProdKgDia',
                'StdDia',
                'ProdKgDia2',
                'StdToaHra',
                'DiasJornada',
                'HorasProd',
                'StdHrsEfect',
                'FechaInicio',
                'Calc4',
                'Calc5',
                'Calc6',
                'FechaFinal',
                'EntregaProduc',
                'EntregaPT',
                'EntregaCte',
                'PTvsCte',
                'CuentaBarra1', 'CalibreBarra1', 'CodColorBarra1', 'ColorBarra1', 'FibraBarra1', 'PasadasBarra1',
                'CuentaBarra2', 'CalibreBarra2', 'CodColorBarra2', 'ColorBarra2', 'FibraBarra2', 'PasadasBarra2',
                'CuentaBarra3', 'CalibreBarra3', 'CodColorBarra3', 'ColorBarra3', 'FibraBarra3', 'PasadasBarra3',
                'CuentaBarra4', 'CalibreBarra4', 'CodColorBarra4', 'ColorBarra4', 'FibraBarra4', 'PasadasBarra4',
            ])->ordenado()->get();

            $columns = UtilityHelpers::getTableColumns();
            $hiddenFields = self::columnasOcultasDelUsuario();

            ProgramaTejidoReadComparison::programar($superficie, $registros);

            return view('modulos.programa-tejido.req-programa-tejido', [
                ...$contexto,
                'registros' => $registros,
                'columns' => $columns,
                'hiddenFields' => $hiddenFields,
            ]);
        } catch (\Throwable $e) {
            LogFacade::error('Error al cargar programa de tejido', [
                'superficie' => $superficie->value,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Error ≠ vacío: la vista pinta un estado de error, no "No hay registros / carga un
            // Excel". El detalle técnico se queda en el log, no en el HTML.
            return view('modulos.programa-tejido.req-programa-tejido', [
                ...$contexto,
                'registros' => collect(),
                'columns' => UtilityHelpers::getTableColumns(),
                'hiddenFields' => [],
                'error' => 'No se pudieron cargar los registros. Intenta de nuevo; si persiste, avisa a Sistemas.',
            ]);
        }
    }

    /**
     * Columnas que el usuario tiene ocultas. Se resuelven en el servidor para que el
     * HTML salga ya oculto: antes el front pintaba las 92, y despues escribia
     * style.display='none' celda por celda (59 columnas x 86 elementos = 5 074
     * escrituras) con el salto de layout correspondiente.
     */
    private static function columnasOcultasDelUsuario(): array
    {
        $userId = Auth::id();
        if (! $userId) {
            return [];
        }

        try {
            return OrdColProgramaTejido::query()
                ->where('UsuarioId', $userId)
                ->where('Estado', 1)
                ->pluck('Columna')
                ->all();
        } catch (\Throwable $e) {
            // Sin estado guardado se pintan todas: el front sigue pudiendo ocultarlas.
            LogFacade::warning('No se pudieron leer las columnas ocultas', ['msg' => $e->getMessage()]);

            return [];
        }
    }

    public function update(Request $request, int $id)
    {
        return UpdateTejido::actualizar($request, $id);
    }

    public function destroy(int $id)
    {
        return EliminarTejido::eliminar($id);
    }

    public function destroyEnProceso(int $id)
    {
        return EliminarTejido::eliminarEnProceso($id);
    }
}
