<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Helpers\StringTruncator;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\EliminarTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\EditTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\UpdateTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UpdateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * @file ProgramaTejidoController.php
 * @description Controlador principal para el programa de tejido. Mantiene: index, edit, update, store,
 *              destroy, destroyEnProceso. Catálogos, operaciones, balanceo y calendarios están
 *              en ProgramaTejido*Controller dedicados. También sirve la vista de Muestras.
 * @dependencies EditTejido, UpdateTejido, EliminarTejido, UpdateHelpers, UtilityHelpers, TejidoHelpers
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
                'PTvsCte'
            ])->ordenado()->get();

            $columns = UtilityHelpers::getTableColumns();

            return view('modulos.programa-tejido.req-programa-tejido', compact(
                'registros',
                'columns',
                'basePath',
                'apiPath',
                'linePath',
                'pageTitle'
            ));
        } catch (\Throwable $e) {
            LogFacade::error('Error al cargar programa de tejido', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('modulos.programa-tejido.req-programa-tejido', [
                'registros' => collect(),
                'columns' => UtilityHelpers::getTableColumns(),
                'error' => 'Error al cargar los datos: ' . $e->getMessage(),
                'basePath' => $basePath ?? '/planeacion/programa-tejido',
                'apiPath' => $apiPath ?? '/programa-tejido',
                'linePath' => $linePath ?? '/planeacion/req-programa-tejido-line',
                'pageTitle' => $pageTitle ?? 'Programa de Tejido',
            ]);
        }
    }

    public function edit(int $id)
    {
        return EditTejido::editar($id);
    }

    public function update(Request $request, int $id)
    {
        return UpdateTejido::actualizar($request, $id);
    }

    public function store(Request $request)
    {
        $request->validate([
            'salon_tejido_id' => 'required|string',
            'tamano_clave' => 'nullable|string',
            'hilo' => 'nullable|string',
            'idflog' => 'nullable|string',
            'calendario_id' => 'nullable|string',
            'aplicacion_id' => 'nullable|string',
            'telares' => 'required|array|min:1',
            'telares.*.no_telar_id' => 'required|string',
            'telares.*.cantidad' => 'nullable|numeric',
            'telares.*.fecha_inicio' => 'nullable|date',
            'telares.*.fecha_final' => 'nullable|date',
            'telares.*.compromiso_tejido' => 'nullable|date',
            'telares.*.fecha_cliente' => 'nullable|date',
            'telares.*.fecha_entrega' => 'nullable|date',
        ]);

        $salon = $request->string('salon_tejido_id')->toString();
        $tamanoClave = $request->input('tamano_clave');
        $hilo = $request->input('hilo');
        $flogsId = $request->input('idflog');
        $calendarioId = $request->input('calendario_id');
        $aplicacionId = $request->input('aplicacion_id');

        $tipoPedido = UtilityHelpers::resolveTipoPedidoFromFlog($flogsId);
        $valoresAlias = UtilityHelpers::resolverAliases($request);
        $creados = [];

        DBFacade::beginTransaction();
        try {
            foreach ($request->input('telares', []) as $fila) {
                $noTelarId = $fila['no_telar_id'];

                UtilityHelpers::marcarCambioHiloAnterior($salon, $noTelarId, $hilo);

                ReqProgramaTejido::where('SalonTejidoId', $salon)
                    ->where('NoTelarId', $noTelarId)
                    ->where(function ($query) {
                        $query->where('Ultimo', '1');
                    })
                    ->update(['Ultimo' => 0]);

                $nuevo = new ReqProgramaTejido();
                $nuevo->EnProceso      = 0;
                $nuevo->SalonTejidoId  = $salon;
                $nuevo->NoTelarId      = $noTelarId;
                $nuevo->Ultimo         = 1;
                $nuevo->TamanoClave    = $tamanoClave;
                $nuevo->FibraRizo      = $hilo;
                $nuevo->FlogsId        = $flogsId;
                $nuevo->CalendarioId   = $calendarioId;
                $nuevo->AplicacionId   = $aplicacionId;

                if (isset($fila['cambio_hilo'])) {
                    $nuevo->CambioHilo = $fila['cambio_hilo'];
                } else {
                    $nuevo->CambioHilo = $request->input('CambioHilo', 0);
                }

                $nuevo->FechaInicio     = $fila['fecha_inicio'] ?? null;
                $nuevo->FechaFinal      = $fila['fecha_final'] ?? null;
                $nuevo->EntregaProduc   = $fila['compromiso_tejido'] ?? null;
                $nuevo->EntregaCte      = $fila['fecha_cliente'] ?? null;
                $nuevo->EntregaPT       = $fila['fecha_entrega'] ?? null;
                $nuevo->TotalPedido     = $fila['cantidad'] ?? null;
                $nuevo->TipoPedido      = $tipoPedido ?? $request->input('TipoPedido');

                if ($request->filled('Maquina')) {
                    $nuevo->Maquina = (string) $request->input('Maquina');
                }

                if ($request->filled('AnchoToalla')) {
                    $nuevo->Ancho = $request->input('AnchoToalla');
                } elseif ($request->filled('Ancho')) {
                    $nuevo->Ancho = $request->input('Ancho');
                }

                UpdateHelpers::aplicarCamposFormulario($nuevo, $request);

                if ($request->filled('ItemId')) {
                    $nuevo->ItemId = (string) $request->input('ItemId');
                }
                if ($request->filled('CustName')) {
                    $nuevo->CustName = StringTruncator::truncate('CustName', (string) $request->input('CustName'));
                }

                UpdateHelpers::aplicarAliasesEnNuevo($nuevo, $valoresAlias, $request);
                UpdateHelpers::aplicarFallbackModeloCodificado($nuevo, $request);

                foreach (
                    [
                        'NombreProducto',
                        'NombreProyecto',
                        'NombreCC1',
                        'NombreCC2',
                        'NombreCC3',
                        'NombreCC4',
                        'NombreCC5',
                        'NombreCPie',
                        'ColorTrama',
                        'CodColorTrama',
                        'Maquina',
                        'FlogsId',
                        'AplicacionId',
                        'CalendarioId',
                        'Observaciones',
                        'Rasurado'
                    ] as $campoStr
                ) {
                    if (isset($nuevo->{$campoStr}) && is_string($nuevo->{$campoStr})) {
                        $nuevo->{$campoStr} = StringTruncator::truncate($campoStr, $nuevo->{$campoStr});
                    }
                }

                $nuevo->Posicion = TejidoHelpers::obtenerSiguientePosicionDisponible($salon, $noTelarId);

                $nuevo->CreatedAt = Carbon::now();
                $nuevo->UpdatedAt = Carbon::now();
                $nuevo->save();
                $creados[] = $nuevo;
            }

            DBFacade::commit();

            return response()->json([
                'success' => true,
                'message' => 'Programa de tejido creado correctamente',
                'data'    => $creados,
            ]);
        } catch (\Throwable $e) {
            DBFacade::rollBack();
            return response()->json(['success' => false, 'message' => 'Error al crear programa de tejido: ' . $e->getMessage()], 500);
        }
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
