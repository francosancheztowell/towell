<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\EliminarTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\UpdateTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use App\Models\Planeacion\OrdColProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejido;
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
        try {
            $isMuestras = request()->is('planeacion/muestras');
            $basePath = $isMuestras ? '/planeacion/muestras' : '/planeacion/programa-tejido';
            $apiPath = $isMuestras ? '/muestras' : '/programa-tejido';
            $linePath = $isMuestras ? '/planeacion/muestras-line' : '/planeacion/req-programa-tejido-line';
            $pageTitle = $isMuestras ? 'Muestras' : 'Programa Tejido';

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
            ])->ordenado()->get();

            $columns = UtilityHelpers::getTableColumns();
            $hiddenFields = self::columnasOcultasDelUsuario();

            return view('modulos.programa-tejido.req-programa-tejido', compact(
                'registros',
                'columns',
                'hiddenFields',
                'basePath',
                'apiPath',
                'linePath',
                'pageTitle'
            ));
        } catch (\Throwable $e) {
            LogFacade::error('Error al cargar programa de tejido', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return view('modulos.programa-tejido.req-programa-tejido', [
                'registros' => collect(),
                'columns' => UtilityHelpers::getTableColumns(),
                'hiddenFields' => [],
                'error' => 'Error al cargar los datos: '.$e->getMessage(),
                'basePath' => $basePath ?? '/planeacion/programa-tejido',
                'apiPath' => $apiPath ?? '/programa-tejido',
                'linePath' => $linePath ?? '/planeacion/req-programa-tejido-line',
                'pageTitle' => $pageTitle ?? 'Programa de Tejido',
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
