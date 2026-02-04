<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UpdateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Observers\ReqProgramaTejidoObserver;
use App\Helpers\AuditoriaHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LogFacade;

class UpdateTejido
{
    private static array $totalModeloCache = [];
    private static array $modeloCodificadoCache = [];

    public static function actualizar(Request $request, int $id)
    {
        $registro = ReqProgramaTejido::findOrFail($id);

        foreach ([
            'programar_prod','entrega_produc','entrega_pt','entrega_cte','fecha_final',
            'pedido','no_tiras','peine','largo_crudo','luchaje','peso_crudo','pt_vs_cte',
            'ancho','ancho_toalla'
        ] as $k) {
            if ($request->has($k) && is_string($request->input($k)) && trim($request->input($k)) === '') {
                $request->merge([$k => null]);
            }
        }

        $data = $request->validate([
            'hilo'          => ['sometimes','nullable','string'],
            'calendario_id' => ['sometimes','nullable','string'],
            'tamano_clave'  => ['sometimes','nullable','string'],
            'rasurado'      => ['sometimes','nullable','string'],
            'pedido'        => ['sometimes','nullable','numeric','min:0'],
            'programar_prod'=> ['sometimes','nullable','date'],
            'idflog'        => ['sometimes','nullable','string'],
            'descripcion'   => ['sometimes','nullable','string'],
            'aplicacion_id' => ['sometimes','nullable','string'],
            'no_tiras'      => ['sometimes','nullable','numeric'],
            'peine'         => ['sometimes','nullable','numeric'],
            'largo_crudo'   => ['sometimes','nullable','numeric'],
            'luchaje'       => ['sometimes','nullable','numeric'],
            'peso_crudo'    => ['sometimes','nullable','numeric'],
            'entrega_produc'=> ['sometimes','nullable','date'],
            'entrega_pt'    => ['sometimes','nullable','date'],
            'entrega_cte'   => ['sometimes','nullable','date'],
            'pt_vs_cte'     => ['sometimes','nullable','numeric'],
            'fecha_final'   => ['sometimes','nullable','date'],
            'ancho'         => ['sometimes','nullable','numeric','min:0'],
            'ancho_toalla'  => ['sometimes','nullable','numeric','min:0'],
        ]);

        // Snapshot
        $fechaFinalAntes = (string)($registro->FechaFinal ?? '');
        $horasProdAntes  = (float)($registro->HorasProd ?? 0);
        $cantidadAntes   = self::sanitizeNumber($registro->SaldoPedido ?? $registro->Produccion ?? $registro->TotalPedido ?? 0);

        // Flags correctos
        $afectaCalendario = false;   // solo acomodación en líneas
        $afectaDuracion   = false;   // cambia HorasProd necesaria (pedido/modelo/no_tiras/luchaje)
        $afectaFormulas   = false;   // cálculos (peso, etc.)
        $afectaAplicacion = false;  // cambia aplicación (requiere actualizar líneas)
        $fechaFinalManual = false;

        // ===== Aplicar cambios =====

        if (array_key_exists('hilo', $data)) {
            $registro->FibraRizo = $data['hilo'] ?: null;
        }

        if (array_key_exists('calendario_id', $data)) {
            $registro->CalendarioId = $data['calendario_id'] ?: null;
            $afectaCalendario = true;
        }

        if (array_key_exists('tamano_clave', $data)) {
            $nuevaClave = $data['tamano_clave'] ?: null;

            // Validar que el salón sea Jacquard o Smit antes de aplicar cambios del modelo
            $salon = $registro->SalonTejidoId ?? '';
            $salonUpper = strtoupper($salon);
            $esJacquardOSmit = str_contains($salonUpper, 'JAC') || str_contains($salonUpper, 'SMI') || str_contains($salonUpper, 'SMIT');

            if (!empty($nuevaClave) && $esJacquardOSmit) {
                // Primero verificar si existe en el salón actual
                $datosModelo = self::obtenerDatosModeloCodificado($salon, $nuevaClave);

                if ($datosModelo) {
                    // Existe en el salón actual, proceder con la actualización
                    // Clave, ID flog, tamaño (InventSizeId), item y producto desde el modelo codificado
                    $registro->TamanoClave = $nuevaClave;
                    if (isset($datosModelo['FlogsId'])) {
                        $registro->FlogsId = $datosModelo['FlogsId'] !== null ? mb_substr((string)$datosModelo['FlogsId'], 0, 30) : null;
                    }
                    if (isset($datosModelo['InventSizeId'])) {
                        $registro->InventSizeId = $datosModelo['InventSizeId'] !== null ? (string)$datosModelo['InventSizeId'] : null;
                    }
                    if (isset($datosModelo['ItemId'])) {
                        $registro->ItemId = $datosModelo['ItemId'] !== null ? (string)$datosModelo['ItemId'] : null;
                    }
                    if (isset($datosModelo['NombreProducto']) && !array_key_exists('descripcion', $data)) {
                        $np = $datosModelo['NombreProducto'] !== null ? mb_substr((string)$datosModelo['NombreProducto'], 0, 50) : null;
                        $registro->NombreProducto = $np;
                    }
                    // Tipo de pedido si viene del FlogsId (ej. RS, CE)
                    if (isset($datosModelo['FlogsId']) && preg_match('/^([A-Za-z]{2})-/', (string)$datosModelo['FlogsId'], $m)) {
                        $registro->TipoPedido = $m[1];
                    }
                    // Actualizar campos técnicos del modelo (solo si no se están editando explícitamente)
                    if (!array_key_exists('no_tiras', $data) && isset($datosModelo['NoTiras'])) {
                        $registro->NoTiras = $datosModelo['NoTiras'] !== null ? (float)$datosModelo['NoTiras'] : null;
                    }
                    if (!array_key_exists('luchaje', $data) && isset($datosModelo['Luchaje'])) {
                        $registro->Luchaje = $datosModelo['Luchaje'] !== null ? (float)$datosModelo['Luchaje'] : null;
                    }
                    if (isset($datosModelo['Repeticiones'])) {
                        $registro->Repeticiones = $datosModelo['Repeticiones'] !== null ? (float)$datosModelo['Repeticiones'] : null;
                    }
                    if (!array_key_exists('peso_crudo', $data) && isset($datosModelo['PesoCrudo'])) {
                        $registro->PesoCrudo = $datosModelo['PesoCrudo'] !== null ? (int)$datosModelo['PesoCrudo'] : null;
                    }
                    if (!array_key_exists('peine', $data) && isset($datosModelo['Peine'])) {
                        $registro->Peine = $datosModelo['Peine'] !== null ? (int)$datosModelo['Peine'] : null;
                    }
                    if (!array_key_exists('largo_crudo', $data) && isset($datosModelo['LargoToalla'])) {
                        $registro->LargoCrudo = $datosModelo['LargoToalla'] !== null ? (int)$datosModelo['LargoToalla'] : null;
                    }
                    if (!array_key_exists('ancho', $data) && !array_key_exists('ancho_toalla', $data)) {
                        if (isset($datosModelo['AnchoToalla'])) {
                            $ancho = $datosModelo['AnchoToalla'] !== null ? (float)$datosModelo['AnchoToalla'] : null;
                            $registro->Ancho = $ancho;
                            $registro->AnchoToalla = $ancho;
                        }
                    }

                    // Campos de rizo
                    if (!array_key_exists('hilo', $data) && isset($datosModelo['FibraRizo'])) {
                        $valor = $datosModelo['FibraRizo'] ?: null;
                        $registro->FibraRizo = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CuentaRizo'])) {
                        $valor = $datosModelo['CuentaRizo'] ?: null;
                        $registro->CuentaRizo = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CalibreRizo'])) {
                        $registro->CalibreRizo = $datosModelo['CalibreRizo'] !== null ? (float)$datosModelo['CalibreRizo'] : null;
                    }
                    if (isset($datosModelo['CalibreRizo2'])) {
                        $registro->CalibreRizo2 = $datosModelo['CalibreRizo2'] !== null ? (float)$datosModelo['CalibreRizo2'] : null;
                    }

                    // Campos de pie
                    if (isset($datosModelo['CalibrePie'])) {
                        $registro->CalibrePie = $datosModelo['CalibrePie'] !== null ? (float)$datosModelo['CalibrePie'] : null;
                    }
                    if (isset($datosModelo['CalibrePie2'])) {
                        $registro->CalibrePie2 = $datosModelo['CalibrePie2'] !== null ? (float)$datosModelo['CalibrePie2'] : null;
                    }
                    if (isset($datosModelo['CuentaPie'])) {
                        $valor = $datosModelo['CuentaPie'] ?: null;
                        $registro->CuentaPie = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    // Color Pie (NombreCPie/CodColorCtaPie): ReqModelosCodificados no tiene esas columnas, no se actualizan desde modelo. OrdPrincipal NO se actualiza.

                    // Campos de trama (CalibreTrama / CalibreTrama2)
                    if (isset($datosModelo['CalibreTrama'])) {
                        $registro->CalibreTrama = $datosModelo['CalibreTrama'] !== null ? (float)$datosModelo['CalibreTrama'] : null;
                    }
                    if (isset($datosModelo['CalibreTrama2'])) {
                        $registro->CalibreTrama2 = $datosModelo['CalibreTrama2'] !== null ? (float)$datosModelo['CalibreTrama2'] : null;
                    }
                    if (isset($datosModelo['FibraTrama'])) {
                        $valor = $datosModelo['FibraTrama'] ?: null;
                        $registro->FibraTrama = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['PasadasTrama'])) {
                        $registro->PasadasTrama = $datosModelo['PasadasTrama'] !== null ? (int)$datosModelo['PasadasTrama'] : null;
                    }
                    if (isset($datosModelo['CodColorTrama'])) {
                        $valor = $datosModelo['CodColorTrama'] ?: null;
                        $registro->CodColorTrama = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['ColorTrama'])) {
                        $valor = $datosModelo['ColorTrama'] ?: null;
                        $registro->ColorTrama = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }

                    // Campos de combinaciones
                    if (isset($datosModelo['PasadasComb1'])) $registro->PasadasComb1 = $datosModelo['PasadasComb1'] !== null ? (int)$datosModelo['PasadasComb1'] : null;
                    if (isset($datosModelo['PasadasComb2'])) $registro->PasadasComb2 = $datosModelo['PasadasComb2'] !== null ? (int)$datosModelo['PasadasComb2'] : null;
                    if (isset($datosModelo['PasadasComb3'])) $registro->PasadasComb3 = $datosModelo['PasadasComb3'] !== null ? (int)$datosModelo['PasadasComb3'] : null;
                    if (isset($datosModelo['PasadasComb4'])) $registro->PasadasComb4 = $datosModelo['PasadasComb4'] !== null ? (int)$datosModelo['PasadasComb4'] : null;
                    if (isset($datosModelo['PasadasComb5'])) $registro->PasadasComb5 = $datosModelo['PasadasComb5'] !== null ? (int)$datosModelo['PasadasComb5'] : null;

                    // Campos de combinaciones con truncamiento para evitar errores de truncamiento en BD
                    if (isset($datosModelo['CalibreComb1'])) {
                        $valor = $datosModelo['CalibreComb1'] ?: null;
                        $registro->CalibreComb1 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CalibreComb12'])) $registro->CalibreComb12 = $datosModelo['CalibreComb12'] !== null ? (float)$datosModelo['CalibreComb12'] : null;
                    if (isset($datosModelo['FibraComb1'])) {
                        $valor = $datosModelo['FibraComb1'] ?: null;
                        $registro->FibraComb1 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CodColorComb1'])) {
                        $valor = $datosModelo['CodColorComb1'] ?: null;
                        $registro->CodColorComb1 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['NombreCC1'])) {
                        $valor = $datosModelo['NombreCC1'] ?: null;
                        $registro->NombreCC1 = $valor !== null ? mb_substr((string)$valor, 0, 60) : null;
                    }

                    if (isset($datosModelo['CalibreComb2'])) {
                        $valor = $datosModelo['CalibreComb2'] ?: null;
                        $registro->CalibreComb2 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CalibreComb22'])) $registro->CalibreComb22 = $datosModelo['CalibreComb22'] !== null ? (float)$datosModelo['CalibreComb22'] : null;
                    if (isset($datosModelo['FibraComb2'])) {
                        $valor = $datosModelo['FibraComb2'] ?: null;
                        $registro->FibraComb2 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CodColorComb2'])) {
                        $valor = $datosModelo['CodColorComb2'] ?: null;
                        $registro->CodColorComb2 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['NombreCC2'])) {
                        $valor = $datosModelo['NombreCC2'] ?: null;
                        $registro->NombreCC2 = $valor !== null ? mb_substr((string)$valor, 0, 60) : null;
                    }

                    if (isset($datosModelo['CalibreComb3'])) {
                        $valor = $datosModelo['CalibreComb3'] ?: null;
                        $registro->CalibreComb3 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CalibreComb32'])) $registro->CalibreComb32 = $datosModelo['CalibreComb32'] !== null ? (float)$datosModelo['CalibreComb32'] : null;
                    if (isset($datosModelo['FibraComb3'])) {
                        $valor = $datosModelo['FibraComb3'] ?: null;
                        $registro->FibraComb3 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CodColorComb3'])) {
                        $valor = $datosModelo['CodColorComb3'] ?: null;
                        $registro->CodColorComb3 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['NombreCC3'])) {
                        $valor = $datosModelo['NombreCC3'] ?: null;
                        $registro->NombreCC3 = $valor !== null ? mb_substr((string)$valor, 0, 60) : null;
                    }

                    if (isset($datosModelo['CalibreComb4'])) {
                        $valor = $datosModelo['CalibreComb4'] ?: null;
                        $registro->CalibreComb4 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CalibreComb42'])) $registro->CalibreComb42 = $datosModelo['CalibreComb42'] !== null ? (float)$datosModelo['CalibreComb42'] : null;
                    if (isset($datosModelo['FibraComb4'])) {
                        $valor = $datosModelo['FibraComb4'] ?: null;
                        $registro->FibraComb4 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CodColorComb4'])) {
                        $valor = $datosModelo['CodColorComb4'] ?: null;
                        $registro->CodColorComb4 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['NombreCC4'])) {
                        $valor = $datosModelo['NombreCC4'] ?: null;
                        $registro->NombreCC4 = $valor !== null ? mb_substr((string)$valor, 0, 60) : null;
                    }

                    if (isset($datosModelo['CalibreComb5'])) {
                        $valor = $datosModelo['CalibreComb5'] ?: null;
                        $registro->CalibreComb5 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CalibreComb52'])) $registro->CalibreComb52 = $datosModelo['CalibreComb52'] !== null ? (float)$datosModelo['CalibreComb52'] : null;
                    if (isset($datosModelo['FibraComb5'])) {
                        $valor = $datosModelo['FibraComb5'] ?: null;
                        $registro->FibraComb5 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['CodColorComb5'])) {
                        $valor = $datosModelo['CodColorComb5'] ?: null;
                        $registro->CodColorComb5 = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['NombreCC5'])) {
                        $valor = $datosModelo['NombreCC5'] ?: null;
                        $registro->NombreCC5 = $valor !== null ? mb_substr((string)$valor, 0, 60) : null;
                    }

                    // Otros campos
                    if (isset($datosModelo['MedidaPlano'])) {
                        $registro->MedidaPlano = $datosModelo['MedidaPlano'] !== null ? (float)$datosModelo['MedidaPlano'] : null;
                    }
                    if (isset($datosModelo['DobladilloId'])) {
                        $valor = $datosModelo['DobladilloId'] ?: null;
                        $registro->DobladilloId = $valor !== null ? mb_substr((string)$valor, 0, 40) : null;
                    }
                    if (isset($datosModelo['VelocidadSTD'])) {
                        $registro->VelocidadSTD = $datosModelo['VelocidadSTD'] !== null ? (int)$datosModelo['VelocidadSTD'] : null;
                    }
                    if (!array_key_exists('rasurado', $data) && isset($datosModelo['Rasurado'])) {
                        $valor = $datosModelo['Rasurado'] ?: null;
                        $registro->Rasurado = $valor !== null ? mb_substr((string)$valor, 0, 10) : null;
                    }

                    // Actualizar FlogsId y TipoPedido si viene del modelo
                    if (isset($datosModelo['FlogsId']) && !array_key_exists('idflog', $data)) {
                        UpdateHelpers::applyFlogYTipoPedido($registro, $datosModelo['FlogsId']);
                    }

                    // Actualizar NombreProyecto si viene del modelo (truncar a 50 caracteres por límite en BD)
                    if (isset($datosModelo['NombreProyecto']) && !array_key_exists('descripcion', $data)) {
                        $nombreProyecto = $datosModelo['NombreProyecto'] ?: null;
                        if ($nombreProyecto !== null) {
                            $nombreProyecto = mb_substr($nombreProyecto, 0, 50);
                        }
                        $registro->NombreProyecto = $nombreProyecto;
                    }

                    $afectaDuracion = true;
                    $afectaFormulas = true;
                } else {
                    // No existe en el salón actual, buscar en otros salones Jacquard/Smit
                    $salonEncontrado = self::buscarClaveModeloEnOtrosSalones($nuevaClave, $salon);

                    if ($salonEncontrado) {
                        // Existe en otro salón, retornar error con alerta
                        return response()->json([
                            'success' => false,
                            'message' => "La clave modelo \"{$nuevaClave}\" no existe en el salón actual ({$salon}), pero se encontró en el salón: {$salonEncontrado}",
                            'tipo' => 'alerta',
                            'salon_encontrado' => $salonEncontrado
                        ], 422);
                    } else {
                        // No existe en ningún salón Jacquard/Smit, retornar error
                        return response()->json([
                            'success' => false,
                            'message' => "La clave modelo \"{$nuevaClave}\" no existe en los codificados de Jacquard o SMIT",
                            'tipo' => 'error'
                        ], 422);
                    }
                }
            } else {
                // Si no es Jacquard/Smit o la clave está vacía, solo actualizar la clave sin cambiar otros campos
                $registro->TamanoClave = $nuevaClave;
                if (!empty($nuevaClave)) {
                    $afectaDuracion = true;
                    $afectaFormulas = true;
                }
            }
        }

        if (array_key_exists('rasurado', $data)) {
            $registro->Rasurado = $data['rasurado'] ?: null;
        }

        if (array_key_exists('pedido', $data)) {
            $totalPedido = $data['pedido'] !== null ? (float)$data['pedido'] : null;
            if ($totalPedido !== null) {
                $registro->TotalPedido = $totalPedido;

                $prod = (float)($registro->Produccion ?? 0);
                $registro->SaldoPedido = max(0, $totalPedido - $prod);

                $afectaDuracion = true;
                $afectaFormulas = true;
            }
        }

        if (array_key_exists('programar_prod', $data)) {
            if ($data['programar_prod']) DateHelpers::setSafeDate($registro, 'ProgramarProd', $data['programar_prod']);
            else $registro->ProgramarProd = null;
        }

        if (array_key_exists('idflog', $data)) {
            UpdateHelpers::applyFlogYTipoPedido($registro, $data['idflog']);
        }

        if (array_key_exists('descripcion', $data)) {
            $registro->NombreProyecto = $data['descripcion'] ?: null;
        }

        if (array_key_exists('aplicacion_id', $data)) {
            $nuevaAplicacion = ($data['aplicacion_id'] === 'NA' || $data['aplicacion_id'] === '') ? null : $data['aplicacion_id'];
            $aplicacionAnterior = $registro->AplicacionId;
            $registro->AplicacionId = $nuevaAplicacion;

            // Detectar si realmente cambió la aplicación
            if ((string)$aplicacionAnterior !== (string)$nuevaAplicacion) {
                $afectaAplicacion = true;
            }
        }

        if (array_key_exists('no_tiras', $data)) {
            $registro->NoTiras = $data['no_tiras'] !== null ? (float)$data['no_tiras'] : null;
            $afectaDuracion = true;
            $afectaFormulas = true;
        }

        if (array_key_exists('peine', $data)) {
            $registro->Peine = $data['peine'] !== null ? (float)$data['peine'] : null;
        }

        if (array_key_exists('largo_crudo', $data)) {
            $registro->LargoCrudo = $data['largo_crudo'] !== null ? (float)$data['largo_crudo'] : null;
        }

        if (array_key_exists('luchaje', $data)) {
            $registro->Luchaje = $data['luchaje'] !== null ? (float)$data['luchaje'] : null;
            $afectaDuracion = true;
            $afectaFormulas = true;
        }

        if (array_key_exists('peso_crudo', $data)) {
            $registro->PesoCrudo = $data['peso_crudo'] !== null ? (float)$data['peso_crudo'] : null;
            $afectaFormulas = true;
        }

        // Ancho / AnchoToalla: al editar solo se recalcula PesoGRM2
        $editoAncho = false;
        if (array_key_exists('ancho_toalla', $data)) {
            $valor = $data['ancho_toalla'] !== null && $data['ancho_toalla'] !== '' ? (float)$data['ancho_toalla'] : null;
            $registro->AnchoToalla = $valor;
            $registro->Ancho = $valor;
            $editoAncho = true;
        }
        if (array_key_exists('ancho', $data)) {
            $valor = $data['ancho'] !== null && $data['ancho'] !== '' ? (float)$data['ancho'] : null;
            $registro->Ancho = $valor;
            $registro->AnchoToalla = $valor;
            $editoAncho = true;
        }
        // No marcar $afectaFormulas: al editar solo ancho solo se recalcula PesoGRM2 más abajo

        if (array_key_exists('entrega_produc', $data)) {
            if ($data['entrega_produc']) DateHelpers::setSafeDate($registro, 'EntregaProduc', $data['entrega_produc']);
            else $registro->EntregaProduc = null;
        }

        if (array_key_exists('entrega_pt', $data)) {
            if ($data['entrega_pt']) DateHelpers::setSafeDate($registro, 'EntregaPT', $data['entrega_pt']);
            else $registro->EntregaPT = null;
        }

        if (array_key_exists('entrega_cte', $data)) {
            if ($data['entrega_cte']) DateHelpers::setSafeDate($registro, 'EntregaCte', $data['entrega_cte']);
            else $registro->EntregaCte = null;
        }

        if (array_key_exists('pt_vs_cte', $data)) {
            $registro->PTvsCte = $data['pt_vs_cte'] !== null ? (float)$data['pt_vs_cte'] : null;
        }

        if (array_key_exists('fecha_final', $data) && $data['fecha_final']) {
            $registro->FechaFinal = Carbon::parse($data['fecha_final'])->format('Y-m-d H:i:s');
            $fechaFinalManual = true;
        }

        // ===== 2) Recalcular FechaFinal =====
        // REGLA: cambiar calendario NO cambia duración; solo re-acomoda en líneas.
        $recalcularFecha = (!$fechaFinalManual) && !empty($registro->FechaInicio) && ($afectaCalendario || $afectaDuracion);

        if ($recalcularFecha) {
            $inicio = Carbon::parse($registro->FechaInicio);

            // Snap si cayó en gap (solo si hay calendario)
            if ($afectaCalendario && !empty($registro->CalendarioId)) {
                $snap = self::snapInicioAlCalendario($registro->CalendarioId, $inicio);
                if ($snap && !$snap->equalTo($inicio)) {
                    $fechaInicioAnterior = $registro->FechaInicio;
                    $enProceso = ($registro->EnProceso == 1 || $registro->EnProceso === true);
                    $registro->FechaInicio = $snap->format('Y-m-d H:i:s');
                    $inicio = $snap;

                    // Auditoría: cambio de FechaInicio por snap al calendario
                    AuditoriaHelper::logCambioFechaInicio(
                        'ReqProgramaTejido',
                        $registro->Id,
                        $fechaInicioAnterior,
                        $registro->FechaInicio,
                        'Snap Calendario',
                        $request,
                        $enProceso
                    );
                }
            }

            // Duración:
            // - si SOLO cambió calendario: usa HorasProd existente (evita drift 16:09->16:11)
            // - si cambió pedido/modelo/etc: recalcula HorasProd
            if ($afectaDuracion) {
                $horasNecesarias = self::calcularHorasProd($registro);

                // fallback proporcional (igual a tu duplicar)
                if ($horasNecesarias <= 0 && $horasProdAntes > 0) {
                    $cantNew = self::sanitizeNumber($registro->SaldoPedido ?? $registro->Produccion ?? $registro->TotalPedido ?? 0);
                    if ($cantidadAntes > 0 && $cantNew > 0) {
                        $horasNecesarias = $horasProdAntes * ($cantNew / $cantidadAntes);
                    }
                }
            } else {
                $horasNecesarias = $horasProdAntes > 0 ? $horasProdAntes : self::calcularHorasProd($registro);
            }

            if ($horasNecesarias <= 0) {
                $registro->FechaFinal = $inicio->copy()->addDays(30)->format('Y-m-d H:i:s');
            } else {
                if (!empty($registro->CalendarioId)) {
                    $fin = BalancearTejido::calcularFechaFinalDesdeInicio($registro->CalendarioId, $inicio, $horasNecesarias);
                    if (!$fin) $fin = $inicio->copy()->addSeconds((int) round($horasNecesarias * 3600));
                    $registro->FechaFinal = $fin->format('Y-m-d H:i:s');
                } else {
                    $registro->FechaFinal = $inicio->copy()->addSeconds((int) round($horasNecesarias * 3600))->format('Y-m-d H:i:s');
                }
            }
        }

        // ===== 3) Fórmulas =====
        // Si SOLO cambió calendario (y NO cambió duración), recalcula SOLO lo que depende de diffDias
        $soloCalendario = $afectaCalendario && !$afectaDuracion && !$fechaFinalManual && !$afectaFormulas;

        if ($soloCalendario) {
            self::recalcularSoloDiffDias($registro);
        } elseif ($afectaFormulas || $afectaDuracion || $afectaCalendario || $fechaFinalManual) {
            $formulas = self::calcularFormulasEficiencia($registro);
            foreach ($formulas as $campo => $valor) {
                $registro->{$campo} = $valor;
            }
        }

        // Si se editó ancho/ancho_toalla: recalcular PesoGRM2 (usar LargoCrudo si no hay LargoToalla)
        if ($editoAncho) {
            $pesoCrudo   = (float)($registro->PesoCrudo ?? 0);
            $largo       = (float)($registro->LargoToalla ?? $registro->LargoCrudo ?? 0);
            $anchoToalla = (float)($registro->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largo > 0 && $anchoToalla > 0) {
                $registro->PesoGRM2 = (float) round(($pesoCrudo * 10000) / ($largo * $anchoToalla), 2);
            }
        }

        $fechaFinalCambiada = ((string)($registro->FechaFinal ?? '') !== $fechaFinalAntes);

        // ===== 4) Truncar strings antes de guardar (evitar error "String or binary data would be truncated")
        self::truncarStringsAntesDeGuardar($registro);

        // ===== 5) Log de campos que se actualizarán y guardar =====
        $dirty = $registro->getDirty();
        if (!empty($dirty)) {
            $campos = array_keys($dirty);
            $ordenNoActualizada = !in_array('OrdPrincipal', $campos, true);
            LogFacade::info('UpdateTejido: campos actualizados', [
                'id' => $registro->Id,
                'campos' => $campos,
                'valores' => $dirty,
                'orden_produccion_no_actualizada' => $ordenNoActualizada,
                'calibre_trama' => [
                    'CalibreTrama' => $registro->CalibreTrama ?? null,
                    'CalibreTrama2' => $registro->CalibreTrama2 ?? null,
                ],
                'color_pie' => [
                    'CodColorCtaPie' => $registro->CodColorCtaPie ?? null,
                    'NombreCPie' => $registro->NombreCPie ?? null,
                ],
            ]);
        }

        $registro->saveQuietly();

        // ===== 6) Cascada (solo si cambió FechaFinal y NO es Ultimo) =====
        if ($fechaFinalCambiada && (int)($registro->Ultimo ?? 0) !== 1) {
            try { DateHelpers::cascadeFechas($registro); }
            catch (\Throwable $e) {
                LogFacade::warning('UpdateTejido: cascadeFechas error', ['id'=>$registro->Id,'error'=>$e->getMessage()]);
            }
        }

        // ===== 7) Líneas (solo si cambió planeación) =====
        $necesitaLineas = $afectaCalendario || $afectaDuracion || $fechaFinalCambiada || $fechaFinalManual;

        if ($necesitaLineas) {
            try {
                $observer = new ReqProgramaTejidoObserver();
                $observer->saved($registro);
            } catch (\Throwable $e) {
                LogFacade::warning('UpdateTejido: observer saved error', ['id'=>$registro->Id,'error'=>$e->getMessage()]);
            }
        }

        // ===== 8) Actualizar Aplicacion en líneas existentes (solo si cambió aplicación y NO se regeneraron líneas) =====
        if ($afectaAplicacion && !$necesitaLineas) {
            try {
                self::actualizarAplicacionEnLineas($registro);
            } catch (\Throwable $e) {
                LogFacade::warning('UpdateTejido: actualizarAplicacionEnLineas error', ['id'=>$registro->Id,'error'=>$e->getMessage()]);
            }
        }

        $registro = $registro->fresh(); // para devolver lo definitivo

        return response()->json([
            'success' => true,
            'message' => 'Programa de tejido actualizado',
            'data'    => UtilityHelpers::extractResumen($registro),
        ]);
    }

    /**
     * Trunca todos los atributos string del registro a longitudes seguras antes de guardar,
     * para evitar el error SQL "String or binary data would be truncated".
     */
    private static function truncarStringsAntesDeGuardar(ReqProgramaTejido $registro): void
    {
        $limites = [
            'NombreProyecto' => 50,
            'NombreProducto' => 50,
            'FlogsId' => 30,
            'InventSizeId' => 20,
            'ItemId' => 20,
            'TamanoClave' => 50,
            'TipoPedido' => 10,
            'FibraRizo' => 40,
            'DobladilloId' => 40,
            'CuentaRizo' => 20,
            'CuentaPie' => 20,
            'CodColorCtaPie' => 10,
            'NombreCPie' => 60,
            'FibraTrama' => 40,
            'CodColorTrama' => 40,
            'ColorTrama' => 40,
            'Rasurado' => 10,
            'FibraComb1' => 40, 'FibraComb2' => 40, 'FibraComb3' => 40, 'FibraComb4' => 40, 'FibraComb5' => 40,
            'CodColorComb1' => 40, 'CodColorComb2' => 40, 'CodColorComb3' => 40, 'CodColorComb4' => 40, 'CodColorComb5' => 40,
            'NombreCC1' => 60, 'NombreCC2' => 60, 'NombreCC3' => 60, 'NombreCC4' => 60, 'NombreCC5' => 60,
            'CalibreComb1' => 40, 'CalibreComb2' => 40, 'CalibreComb3' => 40, 'CalibreComb4' => 40, 'CalibreComb5' => 40,
        ];
        foreach ($limites as $attr => $max) {
            if (!isset($registro->$attr) || !is_string($registro->$attr)) {
                continue;
            }
            $registro->$attr = mb_substr($registro->$attr, 0, $max);
        }
    }

    private static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon
    {
        return TejidoHelpers::snapInicioAlCalendario($calendarioId, $fechaInicio);
    }

    private static function calcularHorasProd(ReqProgramaTejido $p): float
    {
        $vel   = (float) ($p->VelocidadSTD ?? 0);
        $efic  = (float) ($p->EficienciaSTD ?? 0);
        $cant  = self::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);
        $noTiras = (float) ($p->NoTiras ?? 0);
        $luchaje = (float) ($p->Luchaje ?? 0);
        $rep     = (float) ($p->Repeticiones ?? 0);

        $total = self::obtenerTotalModelo($p->TamanoClave ?? null);
        return TejidoHelpers::calcularHorasProd(
            $vel,
            $efic,
            $cant,
            $noTiras,
            $total,
            $luchaje,
            $rep
        );
    }

    private static function obtenerTotalModelo(?string $tamanoClave): float
    {
        $key = trim((string)$tamanoClave);
        if ($key === '') return 0.0;

        if (isset(self::$totalModeloCache[$key])) return self::$totalModeloCache[$key];

        $modelo = self::getModeloCodificado($key);
        $total  = $modelo ? (float)$modelo['Total'] : 0.0;

        self::$totalModeloCache[$key] = $total;
        return $total;
    }

    private static function getModeloCodificado(string $tamanoClave): ?array
    {
        $key = trim($tamanoClave);
        if ($key === '') return null;

        if (array_key_exists($key, self::$modeloCodificadoCache)) return self::$modeloCodificadoCache[$key];

        $m = ReqModelosCodificados::query()
            ->select(['TamanoClave','Total','NoTiras','Luchaje','Repeticiones'])
            ->where('TamanoClave', $key)
            ->first();

        if (!$m) {
            self::$modeloCodificadoCache[$key] = null;
            return null;
        }

        return self::$modeloCodificadoCache[$key] = [
            'Total'        => (float)($m->Total ?? 0),
            'NoTiras'      => (float)($m->NoTiras ?? 0),
            'Luchaje'      => (float)($m->Luchaje ?? 0),
            'Repeticiones' => (float)($m->Repeticiones ?? 0),
        ];
    }

    /**
     * Busca la clave modelo en otros salones Jacquard/Smit (excluyendo el salón actual)
     * Retorna el nombre del salón donde se encontró, o null si no existe
     */
    private static function buscarClaveModeloEnOtrosSalones(string $tamanoClave, string $salonActual): ?string
    {
        $tam = trim($tamanoClave);
        if ($tam === '') return null;

        // Normalizar: quitar dobles espacios y usar mayúsculas para comparación flexible
        $tam = preg_replace('/\s+/', ' ', $tam);
        $tamUpper = strtoupper($tam);

        // Obtener todos los salones Jacquard/Smit disponibles
        $salonesJacquardOSmit = ReqModelosCodificados::query()
            ->where(function($q) {
                $q->whereRaw("UPPER(SalonTejidoId) LIKE ?", ['%JAC%'])
                  ->orWhereRaw("UPPER(SalonTejidoId) LIKE ?", ['%SMI%'])
                  ->orWhereRaw("UPPER(SalonTejidoId) LIKE ?", ['%SMIT%']);
            })
            ->distinct()
            ->pluck('SalonTejidoId')
            ->filter(function($s) use ($salonActual) {
                // Excluir el salón actual
                return strtoupper($s) !== strtoupper($salonActual);
            })
            ->values()
            ->toArray();

        // Buscar en cada salón
        foreach ($salonesJacquardOSmit as $salon) {
            $qBase = ReqModelosCodificados::where('SalonTejidoId', $salon);

            // Intento exacto
            $existe = (clone $qBase)
                ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(TamanoClave))), '  ', ' ') = ?", [$tamUpper])
                ->exists();

            if ($existe) {
                return $salon;
            }

            // Prefijo
            $existe = (clone $qBase)
                ->whereRaw('UPPER(TamanoClave) like ?', [$tamUpper . '%'])
                ->exists();

            if ($existe) {
                return $salon;
            }

            // Contiene
            $existe = (clone $qBase)
                ->whereRaw('UPPER(TamanoClave) like ?', ['%' . $tamUpper . '%'])
                ->exists();

            if ($existe) {
                return $salon;
            }
        }

        return null;
    }

    /**
     * Obtiene todos los datos del modelo codificado para un salón y clave modelo específicos
     * Similar a getDatosRelacionados pero para uso interno en UpdateTejido
     */
    private static function obtenerDatosModeloCodificado(string $salon, string $tamanoClave): ?array
    {
        $tam = trim($tamanoClave);
        if ($tam === '') return null;

        // Normalizar: quitar dobles espacios y usar mayúsculas para comparación flexible
        $tam = preg_replace('/\s+/', ' ', $tam);

        $selectCols = [
            'TamanoClave', 'SalonTejidoId', 'FlogsId', 'NombreProyecto', 'InventSizeId', 'ItemId', 'Nombre',
            'VelocidadSTD', 'AnchoToalla', 'CuentaPie', 'MedidaPlano', 'PesoCrudo', 'NoTiras', 'Luchaje',
            'Repeticiones', 'Total', 'CalibreTrama', 'CalibreTrama2', 'FibraId', 'FibraRizo', 'CalibreRizo',
            'CalibreRizo2', 'CuentaRizo', 'CalibrePie', 'CalibrePie2', 'Peine', 'Rasurado', 'CodColorTrama',
            'ColorTrama', 'DobladilloId',             'PasadasTramaFondoC1', 'FibraTramaFondoC1', 'PasadasComb1',
            'PasadasComb2', 'PasadasComb3', 'PasadasComb4', 'PasadasComb5', 'CalibreComb1', 'CalibreComb12',
            'FibraComb1', 'CodColorC1', 'NomColorC1', 'CalibreComb2', 'CalibreComb22', 'FibraComb2',
            'CodColorC2', 'NomColorC2', 'CalibreComb3', 'CalibreComb32', 'FibraComb3', 'CodColorC3',
            'NomColorC3', 'CalibreComb4', 'CalibreComb42', 'FibraComb4', 'CodColorC4', 'NomColorC4',
            'CalibreComb5', 'CalibreComb52', 'FibraComb5', 'CodColorC5', 'NomColorC5', 'LargoToalla'
        ];

        $qBase = ReqModelosCodificados::where('SalonTejidoId', $salon);

        // Intento exacto
        $datos = (clone $qBase)
            ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(TamanoClave))), '  ', ' ') = ?", [strtoupper($tam)])
            ->select($selectCols)
            ->first();

        // Prefijo
        if (!$datos) {
            $datos = (clone $qBase)
                ->whereRaw('UPPER(TamanoClave) like ?', [strtoupper($tam) . '%'])
                ->select($selectCols)
                ->first();
        }

        // Contiene
        if (!$datos) {
            $datos = (clone $qBase)
                ->whereRaw('UPPER(TamanoClave) like ?', ['%' . strtoupper($tam) . '%'])
                ->select($selectCols)
                ->first();
        }

        if (!$datos) {
            return null;
        }

        // Mapear campos del modelo codificado a los nombres que se usan en ReqProgramaTejido
        return [
            'TamanoClave' => $datos->TamanoClave ?? null,
            'SalonTejidoId' => $datos->SalonTejidoId ?? null,
            'FlogsId' => $datos->FlogsId ?? null,
            'NombreProyecto' => $datos->NombreProyecto ?? null,
            'InventSizeId' => $datos->InventSizeId ?? null,
            'ItemId' => $datos->ItemId ?? null,
            'Nombre' => $datos->Nombre ?? null,
            'NombreProducto' => $datos->Nombre ?? null,
            'VelocidadSTD' => $datos->VelocidadSTD ?? null,
            'AnchoToalla' => $datos->AnchoToalla ?? null,
            'CuentaPie' => $datos->CuentaPie ?? null,
            'MedidaPlano' => $datos->MedidaPlano ?? null,
            'PesoCrudo' => $datos->PesoCrudo ?? null,
            'NoTiras' => $datos->NoTiras ?? null,
            'Luchaje' => $datos->Luchaje ?? null,
            'Repeticiones' => $datos->Repeticiones ?? null,
            'Total' => $datos->Total ?? null,
            'CalibreTrama' => $datos->CalibreTrama2 ?? null,
            'CalibreTrama2' => $datos->CalibreTrama ?? null,
            'FibraId' => $datos->FibraId ?? null,
            'FibraRizo' => $datos->FibraRizo ?? null,
            'CalibreRizo' => $datos->CalibreRizo ?? null,
            'CalibreRizo2' => $datos->CalibreRizo2 ?? null,
            'CuentaRizo' => $datos->CuentaRizo ?? null,
            'CalibrePie' => $datos->CalibrePie ?? null,
            'CalibrePie2' => $datos->CalibrePie2 ?? null,
            'Peine' => $datos->Peine ?? null,
            'Rasurado' => $datos->Rasurado ?? null,
            'Ancho' => $datos->AnchoToalla ?? null,
            'LargoToalla' => $datos->LargoToalla ?? null,
            'CodColorTrama' => $datos->CodColorTrama ?? null,
            'ColorTrama' => $datos->ColorTrama ?? null,
            'DobladilloId' => $datos->DobladilloId ?? null,
            'PasadasTrama' => $datos->PasadasTramaFondoC1 ?? null,
            'FibraTrama' => $datos->FibraTramaFondoC1 ?? null,
            'PasadasComb1' => $datos->PasadasComb1 ?? null,
            'PasadasComb2' => $datos->PasadasComb2 ?? null,
            'PasadasComb3' => $datos->PasadasComb3 ?? null,
            'PasadasComb4' => $datos->PasadasComb4 ?? null,
            'PasadasComb5' => $datos->PasadasComb5 ?? null,
            'CalibreComb1' => $datos->CalibreComb1 ?? null,
            'CalibreComb12' => $datos->CalibreComb12 ?? null,
            'FibraComb1' => $datos->FibraComb1 ?? null,
            'CodColorComb1' => $datos->CodColorC1 ?? null,
            'NombreCC1' => $datos->NomColorC1 ?? null,
            'CalibreComb2' => $datos->CalibreComb2 ?? null,
            'CalibreComb22' => $datos->CalibreComb22 ?? null,
            'FibraComb2' => $datos->FibraComb2 ?? null,
            'CodColorComb2' => $datos->CodColorC2 ?? null,
            'NombreCC2' => $datos->NomColorC2 ?? null,
            'CalibreComb3' => $datos->CalibreComb3 ?? null,
            'CalibreComb32' => $datos->CalibreComb32 ?? null,
            'FibraComb3' => $datos->FibraComb3 ?? null,
            'CodColorComb3' => $datos->CodColorC3 ?? null,
            'NombreCC3' => $datos->NomColorC3 ?? null,
            'CalibreComb4' => $datos->CalibreComb4 ?? null,
            'CalibreComb42' => $datos->CalibreComb42 ?? null,
            'FibraComb4' => $datos->FibraComb4 ?? null,
            'CodColorComb4' => $datos->CodColorC4 ?? null,
            'NombreCC4' => $datos->NomColorC4 ?? null,
            'CalibreComb5' => $datos->CalibreComb5 ?? null,
            'CalibreComb52' => $datos->CalibreComb52 ?? null,
            'FibraComb5' => $datos->FibraComb5 ?? null,
            'CodColorComb5' => $datos->CodColorC5 ?? null,
            'NombreCC5' => $datos->NomColorC5 ?? null,
        ];
    }

    // Recalcular SOLO lo que depende de diffDias (para calendar-only)
    private static function recalcularSoloDiffDias(ReqProgramaTejido $p): void
    {
        if (empty($p->FechaInicio) || empty($p->FechaFinal)) return;

        $inicio = Carbon::parse($p->FechaInicio);
        $fin    = Carbon::parse($p->FechaFinal);
        $diffSeg  = abs($fin->getTimestamp() - $inicio->getTimestamp());
        $diffDias = $diffSeg / 86400;

        if ($diffDias <= 0) return;

        $cantidad = self::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);
        $pesoCrudo = (float)($p->PesoCrudo ?? 0);

        $p->DiasEficiencia = (float) round($diffDias, 2);

        $stdHrsEfect = ($cantidad / $diffDias) / 24;
        $p->StdHrsEfect = (float) round($stdHrsEfect, 2);

        if ($pesoCrudo > 0) {
            $p->ProdKgDia2 = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
        }
    }

    // Tu método completo (idéntico a Duplicar)
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        // <- usa el mismo que ya tienes (no lo re-pego aquí para no alargar),
        //    el de tu Update actual está bien.
        return DuplicarTejido::calcularFormulasEficiencia($programa); // si lo tienes público
    }

    private static function sanitizeNumber($value): float
    {
        return TejidoHelpers::sanitizeNumber($value);
    }

    /**
     * Actualiza el campo Aplicacion en las líneas existentes cuando cambia AplicacionId
     * sin necesidad de regenerar todas las líneas
     */
    private static function actualizarAplicacionEnLineas(ReqProgramaTejido $programa): void
    {
        if (!$programa->Id || $programa->Id <= 0) {
            return;
        }

        // Obtener el factor de aplicación
        $factorAplicacion = null;
        if ($programa->AplicacionId) {
            $aplicacionData = ReqAplicaciones::where('AplicacionId', $programa->AplicacionId)->first();
            if ($aplicacionData) {
                $factorAplicacion = (float) $aplicacionData->Factor;
            }
        }

        // Obtener todas las líneas del programa
        $lineas = ReqProgramaTejidoLine::where('ProgramaId', $programa->Id)
            ->whereNotNull('Kilos')
            ->where('Kilos', '>', 0)
            ->get();

        // Actualizar cada línea: Aplicacion = Factor * Kilos
        foreach ($lineas as $linea) {
            $kilos = (float) ($linea->Kilos ?? 0);
            $nuevoAplicacion = ($factorAplicacion !== null && $kilos > 0)
                ? round($factorAplicacion * $kilos, 6)
                : null;

            $linea->Aplicacion = $nuevoAplicacion;
            $linea->saveQuietly();
        }
    }
}
