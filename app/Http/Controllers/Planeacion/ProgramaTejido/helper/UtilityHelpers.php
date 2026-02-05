<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UtilityHelpers
{
    public static function getTableColumns(): array
    {
        return [
            // Orden solicitado: todas visibles por ahora
            ['field' => 'EnProceso', 'label' => 'Estado', 'dateType' => null],
            ['field' => 'Reprogramar', 'label' => 'Reprogramar', 'dateType' => null],
            ['field' => 'CuentaRizo', 'label' => 'Cuenta', 'dateType' => null],
            ['field' => 'CalibreRizo2', 'label' => 'Calibre Rizo', 'dateType' => null],
            ['field' => 'SalonTejidoId', 'label' => 'Salón', 'dateType' => null],
            ['field' => 'NoTelarId', 'label' => 'Telar', 'dateType' => null],
            ['field' => 'Ultimo', 'label' => 'Último', 'dateType' => null],
            ['field' => 'CambioHilo', 'label' => 'C Hilo', 'dateType' => null],
            ['field' => 'Maquina', 'label' => 'Maq', 'dateType' => null],
            ['field' => 'Ancho', 'label' => 'Ancho', 'dateType' => null],
            ['field' => 'EficienciaSTD', 'label' => 'Ef Std', 'dateType' => null],
            ['field' => 'VelocidadSTD', 'label' => 'Vel', 'dateType' => null],
            ['field' => 'FibraRizo', 'label' => 'Hilo', 'dateType' => null],
            ['field' => 'CalibrePie2', 'label' => 'Calibre Pie', 'dateType' => null],
            ['field' => 'CalendarioId', 'label' => 'Jornada', 'dateType' => null],
            ['field' => 'TamanoClave', 'label' => 'Clave Mod.', 'dateType' => null],
            ['field' => 'NoExisteBase', 'label' => 'Usar cuando no existe en base', 'dateType' => null],
            ['field' => 'ItemId', 'label' => 'Clave AX', 'dateType' => null],
            ['field' => 'InventSizeId', 'label' => 'Tamaño AX', 'dateType' => null],
            ['field' => 'Rasurado', 'label' => 'Rasurado', 'dateType' => null],
            ['field' => 'NombreProducto', 'label' => 'Producto', 'dateType' => null],
            ['field' => 'TotalPedido', 'label' => 'Pedido', 'dateType' => null],
            ['field' => 'PorcentajeSegundos', 'label' => '% Segundas', 'dateType' => null],
            ['field' => 'Produccion', 'label' => 'Producción', 'dateType' => null],
            ['field' => 'SaldoPedido', 'label' => 'Saldos', 'dateType' => null],
            ['field' => 'SaldoMarbete', 'label' => 'Saldo Marbetes', 'dateType' => null],
            ['field' => 'ProgramarProd', 'label' => 'Day Scheduling', 'dateType' => 'date'],
            ['field' => 'NoProduccion', 'label' => 'Orden Prod.', 'dateType' => null],
            ['field' => 'Programado', 'label' => 'INN', 'dateType' => 'date'],
            ['field' => 'FlogsId', 'label' => 'Id Flog', 'dateType' => null],
            ['field' => 'CategoriaCalidad', 'label' => 'Cat. Calidad', 'dateType' => null],
            ['field' => 'NombreProyecto', 'label' => 'Descrip.', 'dateType' => null],
            ['field' => 'CustName', 'label' => 'Custname', 'dateType' => null],
            ['field' => 'AplicacionId', 'label' => 'Aplic.', 'dateType' => null],
            ['field' => 'Observaciones', 'label' => 'Obs', 'dateType' => null],
            ['field' => 'TipoPedido', 'label' => 'Tipo Ped.', 'dateType' => null],
            ['field' => 'NoTiras', 'label' => 'Tiras', 'dateType' => null],
            ['field' => 'Peine', 'label' => 'Pei.', 'dateType' => null],
            ['field' => 'LargoCrudo', 'label' => 'Lcr', 'dateType' => null],
            ['field' => 'PesoCrudo', 'label' => 'Pcr', 'dateType' => null],
            ['field' => 'Luchaje', 'label' => 'Luc', 'dateType' => null],
            ['field' => 'CalibreTrama2', 'label' => 'Calibre Tra', 'dateType' => null],
            ['field' => 'FibraTrama', 'label' => 'Fibra Trama', 'dateType' => null],
            ['field' => 'DobladilloId', 'label' => 'Dob', 'dateType' => null],
            ['field' => 'PasadasTrama', 'label' => 'Pasadas Tra', 'dateType' => null],
            ['field' => 'PasadasComb1', 'label' => 'Pasadas C1', 'dateType' => null],
            ['field' => 'PasadasComb2', 'label' => 'Pasadas C2', 'dateType' => null],
            ['field' => 'PasadasComb3', 'label' => 'Pasadas C3', 'dateType' => null],
            ['field' => 'PasadasComb4', 'label' => 'Pasadas C4', 'dateType' => null],
            ['field' => 'PasadasComb5', 'label' => 'Pasadas C5', 'dateType' => null],
            ['field' => 'AnchoToalla', 'label' => 'Ancho por Toalla', 'dateType' => null],
            ['field' => 'CodColorTrama', 'label' => 'Código Color Tra', 'dateType' => null],
            ['field' => 'ColorTrama', 'label' => 'Color Tra', 'dateType' => null],
            ['field' => 'CalibreComb1', 'label' => 'Calibre C1', 'dateType' => null],
            ['field' => 'FibraComb1', 'label' => 'Fibra C1', 'dateType' => null],
            ['field' => 'CodColorComb1', 'label' => 'Código Color C1', 'dateType' => null],
            ['field' => 'NombreCC1', 'label' => 'Color C1', 'dateType' => null],
            ['field' => 'CalibreComb2', 'label' => 'Calibre C2', 'dateType' => null],
            ['field' => 'FibraComb2', 'label' => 'Fibra C2', 'dateType' => null],
            ['field' => 'CodColorComb2', 'label' => 'Código Color C2', 'dateType' => null],
            ['field' => 'NombreCC2', 'label' => 'Color C2', 'dateType' => null],
            ['field' => 'CalibreComb3', 'label' => 'Calibre C3', 'dateType' => null],
            ['field' => 'FibraComb3', 'label' => 'Fibra C3', 'dateType' => null],
            ['field' => 'CodColorComb3', 'label' => 'Código Color C3', 'dateType' => null],
            ['field' => 'NombreCC3', 'label' => 'Color C3', 'dateType' => null],
            ['field' => 'CalibreComb4', 'label' => 'Calibre C4', 'dateType' => null],
            ['field' => 'FibraComb4', 'label' => 'Fibra C4', 'dateType' => null],
            ['field' => 'CodColorComb4', 'label' => 'Código Color C4', 'dateType' => null],
            ['field' => 'NombreCC4', 'label' => 'Color C4', 'dateType' => null],
            ['field' => 'CalibreComb5', 'label' => 'Calibre C5', 'dateType' => null],
            ['field' => 'FibraComb5', 'label' => 'Fibra C5', 'dateType' => null],
            ['field' => 'CodColorComb5', 'label' => 'Código Color C5', 'dateType' => null],
            ['field' => 'NombreCC5', 'label' => 'Color C5', 'dateType' => null],
            ['field' => 'MedidaPlano', 'label' => 'Plano', 'dateType' => null],
            ['field' => 'CuentaPie', 'label' => 'Cuenta Pie', 'dateType' => null],
            ['field' => 'CodColorCtaPie', 'label' => 'Código Color Pie', 'dateType' => null],
            ['field' => 'NombreCPie', 'label' => 'Color Pie', 'dateType' => null],
            ['field' => 'PesoGRM2', 'label' => 'Peso (gr/m²)', 'dateType' => null],
            ['field' => 'DiasEficiencia', 'label' => 'Días Ef.', 'dateType' => null],
            ['field' => 'ProdKgDia', 'label' => 'Prod (Kg)/Día', 'dateType' => null],
            ['field' => 'StdDia', 'label' => 'Std/Día', 'dateType' => null],
            ['field' => 'ProdKgDia2', 'label' => 'Prod (Kg)/Día 2', 'dateType' => null],
            ['field' => 'StdToaHra', 'label' => 'Std (Toa/Hr) 100%', 'dateType' => null],
            ['field' => 'DiasJornada', 'label' => 'Días Jornada', 'dateType' => null],
            ['field' => 'HorasProd', 'label' => 'Horas', 'dateType' => null],
            ['field' => 'StdHrsEfect', 'label' => 'Std/Hr Efectivo', 'dateType' => null],
            ['field' => 'FechaInicio', 'label' => 'Inicio', 'dateType' => 'datetime'],
            ['field' => 'FechaFinal', 'label' => 'Fin', 'dateType' => 'datetime'],
            ['field' => 'EntregaProduc', 'label' => 'Fecha Compromiso', 'dateType' => 'date'],
            ['field' => 'EntregaPT', 'label' => 'Fecha Compromiso', 'dateType' => 'date'],
            ['field' => 'EntregaCte', 'label' => 'Entrega', 'dateType' => 'datetime'],
            ['field' => 'PTvsCte', 'label' => 'Dif vs Compromiso', 'dateType' => null],

        ];
    }

    public static function extractResumen(ReqProgramaTejido $r): array
    {
        return [
            'Id' => $r->Id,
            // Campos editables inline
            'TamanoClave' => $r->TamanoClave,
            'ItemId' => $r->ItemId,
            'InventSizeId' => $r->InventSizeId,
            'FlogsId' => $r->FlogsId,
            'NombreProducto' => $r->NombreProducto,
            'NombreProyecto' => $r->NombreProyecto,
            'CustName' => $r->CustName,
            'CalendarioId' => $r->CalendarioId,
            'AplicacionId' => $r->AplicacionId,
            'TipoPedido' => $r->TipoPedido,
            'Rasurado' => $r->Rasurado,
            'CambioHilo' => $r->CambioHilo,
            'Ancho' => $r->Ancho,
            'AnchoToalla' => $r->AnchoToalla,
            'EficienciaSTD' => $r->EficienciaSTD,
            'VelocidadSTD' => $r->VelocidadSTD,
            'FibraRizo' => $r->FibraRizo,
            'CuentaRizo' => $r->CuentaRizo,
            'CalibreRizo' => $r->CalibreRizo,
            'CalibreRizo2' => $r->CalibreRizo2,
            'CalibrePie' => $r->CalibrePie,
            'CalibrePie2' => $r->CalibrePie2,
            'CuentaPie' => $r->CuentaPie,
            'FibraPie' => $r->FibraPie,
            'TotalPedido' => $r->TotalPedido,
            'PedidoTempo' => $r->PedidoTempo,
            'SaldoPedido' => $r->SaldoPedido,
            'Produccion'  => $r->Produccion,
            'FechaInicio' => $r->FechaInicio,
            'FechaFinal'  => $r->FechaFinal,
            'EntregaProduc' => $r->EntregaProduc,
            'EntregaPT' => $r->EntregaPT,
            'EntregaCte' => $r->EntregaCte,
            'ProgramarProd' => $r->ProgramarProd,
            'PTvsCte' => $r->PTvsCte,
            'NoTiras' => $r->NoTiras,
            'Peine' => $r->Peine,
            'LargoCrudo' => $r->LargoCrudo,
            'Luchaje' => $r->Luchaje,
            'PesoCrudo' => $r->PesoCrudo,
            'PesoGRM2' => $r->PesoGRM2,
            'DiasEficiencia' => $r->DiasEficiencia,
            'ProdKgDia' => $r->ProdKgDia,
            'StdDia' => $r->StdDia,
            'ProdKgDia2' => $r->ProdKgDia2,
            'StdToaHra' => $r->StdToaHra,
            'DiasJornada' => $r->DiasJornada,
            'HorasProd' => $r->HorasProd,
            'StdHrsEfect' => $r->StdHrsEfect,
            'Observaciones' => $r->Observaciones,
            'PorcentajeSegundos' => $r->PorcentajeSegundos,
            'DobladilloId' => $r->DobladilloId,
            'MedidaPlano' => $r->MedidaPlano,
            'PasadasTrama' => $r->PasadasTrama,
            'CalibreTrama'=> $r->CalibreTrama,
            'CalibreTrama2'=> $r->CalibreTrama2,
            'FibraTrama'  => $r->FibraTrama,
            'CodColorTrama'=> $r->CodColorTrama,
            'ColorTrama' => $r->ColorTrama,
            'PasadasComb1' => $r->PasadasComb1,
            'CalibreComb1' => $r->CalibreComb1,
            'CalibreComb12'=> $r->CalibreComb12,
            'FibraComb1'  => $r->FibraComb1,
            'CodColorComb1'=> $r->CodColorComb1,
            'NombreCC1'   => $r->NombreCC1,
            'PasadasComb2' => $r->PasadasComb2,
            'CalibreComb2' => $r->CalibreComb2,
            'CalibreComb22'=> $r->CalibreComb22,
            'FibraComb2'  => $r->FibraComb2,
            'CodColorComb2'=> $r->CodColorComb2,
            'NombreCC2'   => $r->NombreCC2,
            'PasadasComb3' => $r->PasadasComb3,
            'CalibreComb3' => $r->CalibreComb3,
            'CalibreComb32'=> $r->CalibreComb32,
            'FibraComb3'  => $r->FibraComb3,
            'CodColorComb3'=> $r->CodColorComb3,
            'NombreCC3'   => $r->NombreCC3,
            'PasadasComb4' => $r->PasadasComb4,
            'CalibreComb4' => $r->CalibreComb4,
            'CalibreComb42'=> $r->CalibreComb42,
            'FibraComb4'  => $r->FibraComb4,
            'CodColorComb4'=> $r->CodColorComb4,
            'NombreCC4'   => $r->NombreCC4,
            'PasadasComb5' => $r->PasadasComb5,
            'CalibreComb5' => $r->CalibreComb5,
            'CalibreComb52'=> $r->CalibreComb52,
            'FibraComb5'  => $r->FibraComb5,
            'CodColorComb5'=> $r->CodColorComb5,
            'NombreCC5'   => $r->NombreCC5,
        ];
    }

    public static function resolveTipoPedidoFromFlog(?string $flogsId): ?string
    {
        if (!$flogsId || strlen($flogsId) < 2) {
            return null;
        }
        return strtoupper(substr($flogsId,0,2));
    }

    public static function resolverAliases(Request $req): array
    {
        $map = [
            'NombreProducto' => ['Nombre','NombreProducto','Modelo','Producto'],
            'NoTiras'        => ['NoTiras','Tiras'],
            'Luchaje'        => ['Luchaje','LargoToalla','Largo','Altura','Alto'],
            'ColorTrama'     => ['ColorTrama'],
            'NombreCC1'      => ['NombreCC1','NomColorC1'],
            'NombreCC2'      => ['NombreCC2','NomColorC2'],
            'MedidaPlano'    => ['MedidaPlano','Plano'],
            'NombreCPie'     => ['NombreCPie','Color Pie','Nombre C Pie'],
            'PasadasTrama'   => ['PasadasTrama','Total'],
            'CodColorComb2'  => ['CodColorC2','FibraC2','FibraComb2'],
        ];
        $out = [];
        foreach ($map as $db => $aliases) {
            foreach ($aliases as $a) {
                if ($req->has($a) && $req->filled($a)) {
                    $val = $req->input($a);
                    if (in_array($db,['NoTiras','Luchaje','MedidaPlano','PasadasTrama'])) {
                        $val = is_numeric($val) ? (int)$val : $val;
                    } else {
                        $val = (string)$val;
                    }
                    $out[$db] = $val;
                    break;
                }
            }
        }
        return $out;
    }

    public static function marcarCambioHiloAnterior(string $salon, $noTelarId, ?string $nuevoHilo): void
    {
        try {
            $anterior = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($noTelarId)
                ->where('Ultimo',1)
                ->first();

            if (!$anterior) {
                $anterior = ReqProgramaTejido::query()
                    ->salon($salon)
                    ->telar($noTelarId)
                    ->orderByDesc('Id')
                    ->first();
            }

            if ($anterior && $anterior->FibraRizo !== null && $anterior->FibraRizo !== '' && $anterior->FibraRizo !== $nuevoHilo) {
                $anterior->CambioHilo = 1;
                $anterior->save();
            }
        } catch (\Throwable $e) {
            Log::warning('marcarCambioHiloAnterior error', ['msg'=>$e->getMessage()]);
        }
    }
}



