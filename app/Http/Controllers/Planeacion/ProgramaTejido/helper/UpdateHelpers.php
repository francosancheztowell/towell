<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Helpers\StringTruncator;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UpdateHelpers
{
    public static function applyInlineFieldUpdates(ReqProgramaTejido $registro, array $data): void
    {
        $stringFields = [
            'calendario_id' => 'CalendarioId',
            'tamano_clave' => 'TamanoClave',
            'no_existe_base' => 'NoExisteBase',
            'rasurado' => 'Rasurado',
            'observaciones' => 'Observaciones',
            'dobladillo_id' => 'DobladilloId',
            'cod_color_trama' => 'CodColorTrama',
            'cod_color_comb1' => 'CodColorComb1',
            'cod_color_comb2' => 'CodColorComb2',
            'cod_color_comb3' => 'CodColorComb3',
            'cod_color_comb4' => 'CodColorComb4',
            'cod_color_comb5' => 'CodColorComb5',
            'nombre_cc1' => 'NombreCC1',
            'nombre_cc2' => 'NombreCC2',
            'nombre_cc3' => 'NombreCC3',
            'nombre_cc4' => 'NombreCC4',
            'nombre_cc5' => 'NombreCC5',
            'cuenta_pie' => 'CuentaPie',
            'cod_color_cta_pie' => 'CodColorCtaPie',
            'nombre_c_pie' => 'NombreCPie',
        ];

        foreach ($stringFields as $payloadKey => $attribute) {
            if (!array_key_exists($payloadKey, $data)) {
                continue;
            }
            $value = $data[$payloadKey];
            $registro->{$attribute} = ($value === null || $value === '')
                ? null
                : StringTruncator::truncate($attribute, $value);
        }

        $numericFields = [
            'no_tiras' => ['attr' => 'NoTiras', 'type' => 'int'],
            'peine' => ['attr' => 'Peine', 'type' => 'int'],
            'largo_crudo' => ['attr' => 'LargoCrudo', 'type' => 'float'],
            'luchaje' => ['attr' => 'Luchaje', 'type' => 'float'],
            'peso_crudo' => ['attr' => 'PesoCrudo', 'type' => 'float'],
            'calibre_trama2' => ['attr' => 'CalibreTrama2', 'type' => 'float'],
            'pasadas_comb1' => ['attr' => 'PasadasComb1', 'type' => 'int'],
            'pasadas_comb2' => ['attr' => 'PasadasComb2', 'type' => 'int'],
            'pasadas_comb3' => ['attr' => 'PasadasComb3', 'type' => 'int'],
            'pasadas_comb4' => ['attr' => 'PasadasComb4', 'type' => 'int'],
            'pasadas_comb5' => ['attr' => 'PasadasComb5', 'type' => 'int'],
            'ancho_toalla' => ['attr' => 'AnchoToalla', 'type' => 'float'],
            'medida_plano' => ['attr' => 'MedidaPlano', 'type' => 'int'],
            'pt_vs_cte' => ['attr' => 'PTvsCte', 'type' => 'int'],
        ];

        foreach ($numericFields as $payloadKey => $config) {
            if (!array_key_exists($payloadKey, $data)) {
                continue;
            }
            $value = $data[$payloadKey];
            if ($value === null || $value === '') {
                $registro->{$config['attr']} = null;
                continue;
            }
            $registro->{$config['attr']} = $config['type'] === 'int'
                ? (int) $value
                : (float) $value;
        }

        $dateFields = [
            'programar_prod' => 'ProgramarProd',
            'entrega_produc' => 'EntregaProduc',
            'entrega_pt' => 'EntregaPT',
            'entrega_cte' => 'EntregaCte',
            'fecha_inicio' => 'FechaInicio',
        ];

        foreach ($dateFields as $payloadKey => $attribute) {
            if (!array_key_exists($payloadKey, $data)) {
                continue;
            }
            $value = $data[$payloadKey];
            if ($value === null || $value === '') {
                $registro->{$attribute} = null;
                continue;
            }
            DateHelpers::setSafeDate($registro, $attribute, $value);
        }
    }

    public static function applyCantidad(ReqProgramaTejido $r, array $data): void
    {
        if (!array_key_exists('cantidad', $data)) {
            return;
        }

        $nueva = $data['cantidad'];

        if (!is_null($r->SaldoPedido)) {
            $r->SaldoPedido = $nueva;
        } elseif (!is_null($r->Produccion)) {
            $r->Produccion  = $nueva;
        } else {
            $r->SaldoPedido = $nueva;
        }
    }

    public static function applyCalculados(ReqProgramaTejido $r, array $d): void
    {
        if (array_key_exists('peso_grm2',$d))           $r->PesoGRM2      = is_null($d['peso_grm2']) ? null : (float) $d['peso_grm2'];
        if (array_key_exists('dias_eficiencia',$d))     $r->DiasEficiencia= $d['dias_eficiencia'];
        if (array_key_exists('prod_kg_dia',$d))         $r->ProdKgDia     = $d['prod_kg_dia'];
        if (array_key_exists('std_dia',$d))             $r->StdDia        = $d['std_dia'];
        if (array_key_exists('prod_kg_dia2',$d))        $r->ProdKgDia2    = $d['prod_kg_dia2'];
        if (array_key_exists('std_toa_hra',$d))         $r->StdToaHra     = $d['std_toa_hra'];
        if (array_key_exists('dias_jornada',$d))        $r->DiasJornada   = $d['dias_jornada'];
        if (array_key_exists('horas_prod',$d))          $r->HorasProd     = $d['horas_prod'];
        if (array_key_exists('std_hrs_efect',$d))       $r->StdHrsEfect   = $d['std_hrs_efect'];
    }

    public static function applyEficienciaVelocidad(ReqProgramaTejido $r, Request $req, array $d): void
    {
        $ef = $d['eficiencia_std'] ?? $req->input('EficienciaSTD') ?? $req->input('eficienciaSTD');
        $ve = $d['velocidad_std']  ?? $req->input('VelocidadSTD')  ?? $req->input('velocidadSTD');

        if ($ef !== null && is_numeric($ef)) {
            $r->EficienciaSTD = round((float) $ef, 2);
        }
        if ($ve !== null && is_numeric($ve)) {
            $r->VelocidadSTD  = (float) $ve;
        }
    }

    public static function applyColoresYCalibres(ReqProgramaTejido $r, array $d): void
    {
        // Nombres
        if (array_key_exists('nombre_color_1',$d)) $r->NombreCC1 = $d['nombre_color_1'];
        if (array_key_exists('nombre_color_2',$d)) $r->NombreCC2 = $d['nombre_color_2'];
        if (array_key_exists('nombre_color_3',$d)) $r->NombreCC3 = $d['nombre_color_3'];
        if (array_key_exists('nombre_color_6',$d)) $r->NombreCC5 = $d['nombre_color_6'];

        // Calibres
        if (array_key_exists('calibre_trama',$d))  $r->CalibreTrama  = $d['calibre_trama'];
        if (array_key_exists('calibre_c1',$d))     $r->CalibreComb12 = $d['calibre_c1'];
        if (array_key_exists('calibre_c2',$d))     $r->CalibreComb22 = $d['calibre_c2'];
        if (array_key_exists('calibre_c3',$d))     $r->CalibreComb32 = $d['calibre_c3'];
        if (array_key_exists('calibre_c4',$d)) {
            $r->CalibreComb42 = $d['calibre_c4'];
        }
        if (array_key_exists('calibre_c5',$d))     $r->CalibreComb52 = $d['calibre_c5'];

        // Fibras
        if (array_key_exists('fibra_trama',$d)) $r->FibraTrama = $d['fibra_trama'];
        if (array_key_exists('fibra_c1',$d))    $r->FibraComb1 = $d['fibra_c1'];
        if (array_key_exists('fibra_c2',$d))    $r->FibraComb2 = $d['fibra_c2'];
        if (array_key_exists('fibra_c3',$d))    $r->FibraComb3 = $d['fibra_c3'];
        if (array_key_exists('fibra_c4',$d))    $r->FibraComb4 = $d['fibra_c4'];
        if (array_key_exists('fibra_c5',$d))    $r->FibraComb5 = $d['fibra_c5'];

        // Códigos
        if (array_key_exists('cod_color_1',$d)) $r->CodColorTrama = $d['cod_color_1'];
        if (array_key_exists('cod_color_2',$d)) $r->CodColorComb2 = $d['cod_color_2'];
        if (array_key_exists('cod_color_3',$d)) $r->CodColorComb4 = $d['cod_color_3'];
        if (array_key_exists('cod_color_4',$d)) $r->CodColorComb1 = $d['cod_color_4'];
        if (array_key_exists('cod_color_5',$d)) $r->CodColorComb3 = $d['cod_color_5'];
        if (array_key_exists('cod_color_6',$d)) $r->CodColorComb5 = $d['cod_color_6'];
    }

    public static function applyFlogYTipoPedido(ReqProgramaTejido $r, ?string $flog): void
    {
        $prev = $r->getOriginal('FlogsId');
        $r->FlogsId = $flog ?: null;

        // Si se limpia el Flog, también limpiar TipoPedido; si no, derivarlo
        if ($r->FlogsId && strlen($r->FlogsId) >= 2) {
            $pref = strtoupper(substr($r->FlogsId,0,2));
            $r->TipoPedido = $pref;
        } else {
            $r->TipoPedido = null;
        }
    }

    public static function aplicarCamposFormulario(ReqProgramaTejido $nuevo, Request $req): void
    {
        $campos = [
            'CuentaRizo','CalibreRizo','CalibreRizo2','InventSizeId','NombreProyecto','NombreProducto',
            'ItemId',
            'Ancho','EficienciaSTD','VelocidadSTD','Maquina',
            'CodColorTrama','ColorTrama','CalibreTrama','CalibreTrama2','FibraTrama',
            'CalibreComb1','CalibreComb12','FibraComb1','CodColorComb1','NombreCC1',
            'CalibreComb2','CalibreComb22','FibraComb2','CodColorComb2','NombreCC2',
            'CalibreComb3','CalibreComb32','FibraComb3','CodColorComb3','NombreCC3',
            'CalibreComb4','CalibreComb42','FibraComb4','CodColorComb4','NombreCC4',
            'CalibreComb5','CalibreComb52','FibraComb5','CodColorComb5','NombreCC5',
            'CalibrePie','CalibrePie2','CuentaPie','FibraPie','CodColorCtaPie','NombreCPie',
            'AnchoToalla','PesoCrudo','Peine','MedidaPlano','NoTiras','Luchaje','Rasurado',
            'PasadasTrama','PasadasComb1','PasadasComb2','PasadasComb3','PasadasComb4','PasadasComb5',
            'DobladilloId','LargoCrudo',
            'Produccion','SaldoPedido','SaldoMarbete','ProgramarProd','NoProduccion','Programado',
            'CustName','Observaciones','TipoPedido','PesoGRM2','DiasEficiencia','ProdKgDia','StdDia',
            'ProdKgDia2','StdToaHra','DiasJornada','HorasProd','StdHrsEfect','Calc4','Calc5','Calc6'
        ];

        foreach ($campos as $campo) {
            if (!$req->has($campo) || $req->input($campo) === null || $req->input($campo) === '') {
                continue;
            }

            $valor = $req->input($campo);

            if (in_array($campo, [
                'CalibreRizo','CalibreRizo2','CalibreTrama','CalibreTrama2',
                'CalibreComb1','CalibreComb12','CalibreComb2','CalibreComb22','CalibreComb3','CalibreComb32',
                'CalibreComb4','CalibreComb42','CalibreComb5','CalibreComb52',
                'CalibrePie','CalibrePie2','EficienciaSTD',
                'AnchoToalla'
            ])) {
                $valor = is_numeric($valor) ? (float)$valor : null;
            } elseif (in_array($campo, ['VelocidadSTD','Peine','PesoCrudo','MedidaPlano','Ancho','NoTiras','Luchaje','LargoCrudo'])) {
                $valor = is_numeric($valor) ? (int)$valor : null;
            } elseif (in_array($campo, [
                'TotalPedido','Produccion','SaldoPedido','SaldoMarbete',
                'PesoGRM2','DiasEficiencia','ProdKgDia','StdDia','ProdKgDia2',
                'StdToaHra','DiasJornada','HorasProd','StdHrsEfect','Calc4','Calc5','Calc6'
            ])) {
                $valor = is_numeric($valor) ? (float)$valor : null;
            } else {
                $valor = (string)$valor;
                $valor = StringTruncator::truncate($campo, $valor);
            }

            if ($valor !== null) {
                $nuevo->{$campo} = $valor;
            }
        }
    }

    public static function aplicarAliasesEnNuevo(ReqProgramaTejido $nuevo, array $valoresAlias, Request $req): void
    {
        foreach ($valoresAlias as $dbField => $val) {
            if ($dbField === 'Luchaje' && $val !== '') {
                $nuevo->Luchaje = is_numeric($val) ? (int)$val : $nuevo->Luchaje;
                continue;
            }
            if ($val !== '' && ($nuevo->{$dbField} ?? null) === null) {
                $nuevo->{$dbField} = $val;
            }
        }
    }

    public static function aplicarFallbackModeloCodificado(ReqProgramaTejido $nuevo, Request $req): void
    {
        try {
            $claveTc = $req->input('tamano_clave') ?? $req->input('TamanoClave');
            $salonTc = $req->input('salon_tejido_id') ?? $req->input('SalonTejidoId');
            if (!$claveTc) {
                return;
            }

            $q = ReqModelosCodificados::query()->where('TamanoClave',$claveTc);
            if ($salonTc) {
                $q->where('SalonTejidoId',$salonTc);
            }

            $modeloCod = $q->orderByDesc('FechaTejido')->first();
            if (!$modeloCod) {
                return;
            }

            if (empty($nuevo->NombreProducto) || $nuevo->NombreProducto === 'null') {
                $nuevo->NombreProducto = StringTruncator::truncate(
                    'NombreProducto',
                    (string)($modeloCod->Nombre ?? '')
                );
            }
            if (empty($nuevo->NombreProyecto) || $nuevo->NombreProyecto === 'null') {
                $nuevo->NombreProyecto = StringTruncator::truncate(
                    'NombreProyecto',
                    (string)($modeloCod->NombreProyecto ?? $modeloCod->Descrip ?? $modeloCod->Descripcion ?? '')
                );
            }
            if (empty($nuevo->ItemId) && !empty($modeloCod->ItemId)) {
                $nuevo->ItemId = (string) $modeloCod->ItemId;
            }
            if (empty($nuevo->MedidaPlano) && !empty($modeloCod->MedidaPlano)) {
                $nuevo->MedidaPlano = (int)$modeloCod->MedidaPlano;
            }
            if (empty($nuevo->NombreCPie) && !empty($modeloCod->NombreCPie)) {
                $nuevo->NombreCPie = StringTruncator::truncate('NombreCPie', (string)$modeloCod->NombreCPie);
            }
        } catch (\Throwable $e) {
            Log::warning('Fallback ReqModelosCodificados', ['msg'=>$e->getMessage()]);
        }
    }
}



