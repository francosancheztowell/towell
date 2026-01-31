<?php

namespace App\Http\Controllers\Tejido\InventarioTelas;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelaresController extends Controller
{
    /**
     * Mostrar información individual de un telar (vista)
     * Obtiene datos reales desde ReqProgramaTejido
     */
    public function mostrarTelarSulzer($telar)
    {
        $tipoSalon = $this->determinarTipoSalon($telar);

        // En ITEMA/SMIT buscamos en ambos salones
        $salones = ($tipoSalon === 'ITEMA' || $tipoSalon === 'SMIT') ? ['ITEMA', 'SMIT'] : [$tipoSalon];

        // Resolver candidatos de NoTelarId (p.ej. 318 y 118)
        $candidatos = $this->resolverCandidatosTelar($telar, $tipoSalon);

        // Telar en proceso
        $telarEnProceso = $this->fetchTelarEnProceso($salones, $candidatos);

        // Si no hay datos en proceso, crear objeto mínimo
        if (!$telarEnProceso) {
            $telarEnProceso = $this->objTelarVacio($telar);
        }

        // Siguiente orden programada (por Posicion/Secuencia)
        $ordenSig = null;
        if ($telarEnProceso->en_proceso) {
            $noTelarIdUsado = $telarEnProceso->NoTelarIdOriginal ?? $telarEnProceso->Telar ?? $telar;
            $posicionActual = isset($telarEnProceso->Posicion) ? (int)$telarEnProceso->Posicion : null;
            $ordenSig = $this->fetchSiguienteOrden(
                $salones,
                $noTelarIdUsado,
                $telarEnProceso->Inicio_Tejido ?? null,
                $telarEnProceso->ProgramaId ?? null,
                $posicionActual
            );
            // Si no encuentra con posición, buscar cualquier orden disponible
            if (!$ordenSig) {
                $ordenSig = $this->fetchPrimeraOrdenDisponible($salones, $noTelarIdUsado);
            }
        }

        $datos = collect([$telarEnProceso]);
        $tipo = strtolower($tipoSalon);

        return view('modulos/tejido/telares/telar-informacion-individual', compact('telar', 'datos', 'ordenSig', 'tipo'));
    }

    /**
     * Listado de órdenes programadas para un telar (vista)
     */
    public function obtenerOrdenesProgramadas($telar)
    {
        $tipoSalon = $this->determinarTipoSalon($telar);
        $salones = ($tipoSalon === 'ITEMA' || $tipoSalon === 'SMIT') ? ['ITEMA', 'SMIT'] : [$tipoSalon];

        $ordenes = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->where('NoTelarId', $telar)
            ->select([
                'NoTelarId as Telar',
                'EnProceso as en_proceso',
                'FechaInicio as Inicio_Tejido',
                'NombreProducto as Producto',
                'CustName as Cliente',
                'SaldoPedido as Cantidad',
                DB::raw("CASE WHEN EnProceso = 1 THEN 'En Proceso' ELSE 'Programado' END as Estado"),
                'NoProduccion as Orden_Prod',
                'EntregaCte as Entrega'
            ])
            ->orderBy('EnProceso', 'desc')
            ->orderBy('FechaInicio', 'asc')
            ->get();

        return view('modulos/tejido/telares/ordenes-programadas', compact('ordenes', 'telar'));
    }

    /**
     * Inventario de telares Jacquard (vista)
     * Ordenado por la tabla InvSecuenciaTelares
     */
    public function inventarioJacquard()
    {
        $telaresOrdenados = $this->getSecuenciaTelares(['JACQUARD']); // pluck NoTelar por Secuencia
        $datosTelaresCompletos = [];

        foreach ($telaresOrdenados as $numeroTelar) {
            $salones = ['JACQUARD'];
            $candidatos = [$numeroTelar];

            $telarEnProceso = $this->fetchTelarEnProceso($salones, $candidatos);
            $ordenSig = null;

            if ($telarEnProceso && $telarEnProceso->en_proceso) {
                // Si hay telar en proceso, buscar siguiente orden usando su posición (secuencia)
                $posicionActual = isset($telarEnProceso->Posicion) ? (int)$telarEnProceso->Posicion : null;

                $ordenSig = $this->fetchSiguienteOrden(
                    $salones,
                    $numeroTelar,
                    $telarEnProceso->Inicio_Tejido ?? null,
                    $telarEnProceso->ProgramaId ?? null,
                    $posicionActual
                );

                // Si no encuentra con posición, buscar cualquier orden disponible
                if (!$ordenSig) {
                    $ordenSig = $this->fetchPrimeraOrdenDisponible($salones, $numeroTelar);
                }
            } else {
                // Si no hay proceso, buscar la primera orden disponible (más próxima)
                $telarEnProceso = $this->objTelarVacio($numeroTelar);
                $ordenSig = $this->fetchPrimeraOrdenDisponible($salones, $numeroTelar);
            }

            $datosTelaresCompletos[$numeroTelar] = [
                'telarData' => $telarEnProceso,
                'ordenSig'  => $ordenSig
            ];
        }

        return view('modulos/tejido/inventario-telas/inventario-telas', [
            'telares'              => $telaresOrdenados,
            'datosTelaresCompletos' => $datosTelaresCompletos,
            'tipoInventario'       => 'jacquard'
        ]);
    }

    /**
     * Inventario de telares Itema (vista)
     * Ordenado por la tabla InvSecuenciaTelares (ITEMA/SMIT)
     */
    public function inventarioItema()
    {
        // Registros brutos (debug)
        $todosRegistrosItema = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', ['ITEMA', 'SMIT'])
            ->select('NoTelarId', 'EnProceso', 'NoProduccion', 'NombreProducto', 'SalonTejidoId')
            ->get();

        // Tomar la secuencia desde la tabla
        $telaresOrdenados = $this->getSecuenciaTelares(['ITEMA', 'SMIT']); // pluck NoTelar por Secuencia
        $telaresOrdenados = array_map('intval', $telaresOrdenados);

        $datosTelaresCompletos = [];

        foreach ($telaresOrdenados as $numeroTelar) {
            $salones = ['ITEMA', 'SMIT'];

            // Resolver candidatos 3XX y 1XX si aplica
            $candidatos = $this->resolverCandidatosTelar($numeroTelar, 'ITEMA');

            // En proceso
            $telarEnProceso = $this->fetchTelarEnProceso($salones, $candidatos);

            // Siguiente orden
            $ordenSig = null;
            if ($telarEnProceso && $telarEnProceso->en_proceso) {
                // Si hay telar en proceso, buscar siguiente orden usando candidatos (3XX y 1XX)
                $candidatosOrden = $this->resolverCandidatosTelar($numeroTelar, 'ITEMA');
                $ordenSig = $this->fetchSiguienteOrdenConCandidatos(
                    $salones,
                    $candidatosOrden,
                    $telarEnProceso->Inicio_Tejido,
                    $telarEnProceso->ProgramaId ?? null
                );
                // Si no encuentra con fecha, buscar cualquier orden disponible
                if (!$ordenSig) {
                    $ordenSig = $this->fetchPrimeraOrdenDisponibleConCandidatos($salones, $candidatosOrden);
                }
            } else {
                // Si no hay proceso, buscar la primera orden disponible
                $telarEnProceso = $this->objTelarVacio($numeroTelar);
                $candidatosOrden = $this->resolverCandidatosTelar($numeroTelar, 'ITEMA');
                $ordenSig = $this->fetchPrimeraOrdenDisponibleConCandidatos($salones, $candidatosOrden);
            }

            // Si el en_proceso se obtuvo con formato 1XX, mostrar 3XX en la vista (consistencia de secuencia)
            if ($telarEnProceso && isset($telarEnProceso->Telar) && $telarEnProceso->Telar != $numeroTelar) {
                $telarEnProceso->Telar = $numeroTelar;
            }

            $datosTelaresCompletos[$numeroTelar] = [
                'telarData' => $telarEnProceso,
                'ordenSig'  => $ordenSig
            ];
        }

        return view('modulos/tejido/inventario-telas/inventario-telas', [
            'telares'              => $telaresOrdenados,
            'datosTelaresCompletos' => $datosTelaresCompletos,
            'tipoInventario'       => 'itema'
        ]);
    }

    /**
     * Inventario de telares Karl Mayer (vista)
     * Ordenado por la tabla InvSecuenciaTelares
     */
    public function inventarioKarlMayer()
    {
        $telaresOrdenados = $this->getSecuenciaTelares(['KARL MAYER']);
        $datosTelaresCompletos = [];

        foreach ($telaresOrdenados as $numeroTelar) {
            $salones = ['KARL MAYER'];
            $candidatos = [$numeroTelar];

            $telarEnProceso = $this->fetchTelarEnProceso($salones, $candidatos);
            $ordenSig = null;

            if ($telarEnProceso && $telarEnProceso->en_proceso) {
                $posicionActual = isset($telarEnProceso->Posicion) ? (int)$telarEnProceso->Posicion : null;
                $ordenSig = $this->fetchSiguienteOrden(
                    $salones,
                    $numeroTelar,
                    $telarEnProceso->Inicio_Tejido ?? null,
                    $telarEnProceso->ProgramaId ?? null,
                    $posicionActual
                );
                // Si no encuentra con posición, buscar cualquier orden disponible
                if (!$ordenSig) {
                    $ordenSig = $this->fetchPrimeraOrdenDisponible($salones, $numeroTelar);
                }
            } else {
                $telarEnProceso = $this->objTelarVacio($numeroTelar);
                $ordenSig = $this->fetchPrimeraOrdenDisponible($salones, $numeroTelar);
            }

            $datosTelaresCompletos[$numeroTelar] = [
                'telarData' => $telarEnProceso,
                'ordenSig'  => $ordenSig
            ];
        }

        return view('modulos/tejido/inventario-telas/inventario-telas', [
            'telares'               => $telaresOrdenados,
            'datosTelaresCompletos' => $datosTelaresCompletos,
            'tipoInventario'        => 'karl-mayer'
        ]);
    }

    /**
     * API: Proceso actual de un telar
     */
    public function procesoActual($telarId)
    {
        $tipoSalon = $this->determinarTipoSalon($telarId, true);
        if (!$tipoSalon) {
            return response()->json(['error' => 'Tipo de telar no reconocido'], 400);
        }

        $salones = ($tipoSalon === 'ITEMA' || $tipoSalon === 'SMIT') ? ['ITEMA', 'SMIT'] : [$tipoSalon];
        $procesoActual = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->where('NoTelarId', $telarId)
            ->where('EnProceso', 1)
            ->select([
                'CuentaRizo as Cuenta',
                'CalibreRizo as Calibre_Rizo',
                'FibraRizo as Fibra_Rizo',
                'CuentaPie as Cuenta_Pie',
                'CalibrePie as Calibre_Pie',
                'FibraPie as Fibra_Pie'
            ])
            ->first();

        return response()->json($procesoActual ?: null);
    }

    /**
     * API: Siguiente orden de un telar
     */
    public function siguienteOrden($telarId)
    {
        $tipoSalon = $this->determinarTipoSalon($telarId, true);
        if (!$tipoSalon) {
            return response()->json(['error' => 'Tipo de telar no reconocido'], 400);
        }

        $salones = ($tipoSalon === 'ITEMA' || $tipoSalon === 'SMIT') ? ['ITEMA', 'SMIT'] : [$tipoSalon];

        $telarEnProceso = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->where('NoTelarId', $telarId)
            ->where('EnProceso', 1)
            ->select('Id as ProgramaId', 'FechaInicio', 'Posicion')
            ->first();

        if (!$telarEnProceso) {
            return response()->json(['error' => 'Telar no encontrado en proceso'], 404);
        }

        $siguienteOrden = $this->fetchSiguienteOrden(
            $salones,
            $telarId,
            $telarEnProceso->FechaInicio ?? null,
            $telarEnProceso->ProgramaId,
            $telarEnProceso->Posicion ?? null,
            [
                'CuentaRizo as Cuenta',
                'CalibreRizo2',
                'FibraRizo as Fibra_Rizo',
                'CuentaPie as Cuenta_Pie',
                'CalibrePie2',
                'FibraPie as Fibra_Pie'
            ]
        );

        return response()->json($siguienteOrden ?: null);
    }

    /* ===========================
     * Helpers privados
     * =========================== */

    /**
     * Determinar el salón por número de telar.
     */
    private function determinarTipoSalon($telar, $strict = false)
    {
        if ($telar >= 200 && $telar <= 215) { return 'JACQUARD'; }   // Jacquard
        if ($telar >= 299 && $telar <= 320) { return 'ITEMA'; }      // Itema
        if ($telar >= 303 && $telar <= 306) { return 'KARL MAYER'; } // Ajustable

        return $strict ? null : 'JACQUARD';
    }

    /**
     * Candidatos de NoTelarId: para ITEMA considerar 3XX y 1XX.
     */
    private function resolverCandidatosTelar(int $telar, string $tipoSalon): array
    {
        // Por default, solo el propio
        $candidatos = [$telar];

        // En ITEMA/SMIT si viene como 3XX, probamos 1XX también (318 -> 118)
        if (($tipoSalon === 'ITEMA' || $tipoSalon === 'SMIT') && $telar >= 300 && $telar < 400) {
            $candidatos[] = 100 + ($telar % 100);
        }

        return $candidatos;
    }

    /**
     * Selección estándar de columnas para el telar en proceso.
     */
    private function selectColsProceso(): array
    {
        return [
            'Id as ProgramaId',                // <= para desempatar y encontrar el siguiente correcto
            'NoTelarId as Telar',
            'NoTelarId as NoTelarIdOriginal',
            'EnProceso as en_proceso',
            'Posicion',                        // <= para buscar siguiente orden por secuencia
            'NoProduccion as Orden_Prod',
            'FlogsId as Id_Flog',
            'CustName as Cliente',
            'NoTiras as Tiras',
            'TamanoClave as Tamano_AX',
            'ItemId as ItemId',
            'NombreProducto as Nombre_Producto',
            'CuentaRizo as Cuenta',
            'CalibreRizo2',
            'FibraRizo as Fibra_Rizo',
            'CuentaPie as Cuenta_Pie',
            'CalibrePie2',
            'FibraPie as Fibra_Pie',
            'CalibreTrama2',
            'ColorTrama as COLOR_TRAMA',
            'TotalPedido as Saldos',
            'Produccion as Prod_Kg_Dia',
            'SaldoMarbete as Marbetes_Pend',
            'SaldoMarbete as MarbetesPend',
            'FechaInicio as Inicio_Tejido',
            'FechaFinal as Fin_Tejido',
            'EntregaCte as Fecha_Compromiso',
            DB::raw('0 as Total_Paros'),
            DB::raw('NULL as Tiempo_Paro'),
            // adicionales
            'PasadasTrama as PASADAS_TRAMA',
            'NombreCC1 as COLOR_C1',
            'NombreCC2 as COLOR_C2',
            'NombreCC3 as COLOR_C3',
            'NombreCC4 as COLOR_C4',
            'NombreCC5 as COLOR_C5',
            'CalibreComb12 as CALIBRE_C1',
            'CalibreComb22 as CALIBRE_C2',
            'CalibreComb32 as CALIBRE_C3',
            'CalibreComb42 as CALIBRE_C4',
            'CalibreComb52 as CALIBRE_C5',
            'FibraComb1 as FIBRA_C1',
            'FibraComb2 as FIBRA_C2',
            'FibraComb3 as FIBRA_C3',
            'FibraComb4 as FIBRA_C4',
            'FibraComb5 as FIBRA_C5'
        ];
    }

    /**
     * Traer el registro en proceso respetando preferencia por el primer candidato.
     */
    private function fetchTelarEnProceso(array $salones, array $candidatos)
    {
        // Preferir el primer candidato si existieran ambos
        $preferido = $candidatos[0];

        $q = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->whereIn('NoTelarId', $candidatos)
            ->where('EnProceso', 1)
            ->select($this->selectColsProceso())
            // preferencia: primero donde NoTelarId = $preferido
            ->orderByRaw('CASE WHEN NoTelarId = ? THEN 0 ELSE 1 END', [$preferido])
            // si hay más de uno en proceso, tomar el de inicio más reciente
            ->orderBy('FechaInicio', 'desc')
            ->orderBy('Id', 'desc');

        return $q->first();
    }

    /**
     * Traer la siguiente orden programada con select configurable.
     * Si hay varias con la misma FechaInicio, se toma la de Id mayor al actual.
     */
    private function fetchSiguienteOrden(array $salones, $noTelarId, $fechaInicioActual, $programaIdActual = null, $posicionActual = null, array $select = null)
    {
        $select = $select ?: [
            'NoTelarId as Telar',
            'NoProduccion as Orden_Prod',
            'ItemId as ItemId',
            'TamanoClave as Tamano_AX',
            'NombreProducto as Nombre_Producto',
            'CuentaRizo as Cuenta',
            'CalibreRizo2',
            'FibraRizo as Fibra_Rizo',
            'CuentaPie as Cuenta_Pie',
            'CalibrePie2',
            'FibraPie as Fibra_Pie',
            'TotalPedido as Saldos',
            'FechaInicio as Inicio_Tejido',
            'EntregaCte as Entrega'
        ];

        // Ver todas las órdenes del telar (en proceso y no en proceso)
        $todasOrdenes = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->where('NoTelarId', $noTelarId)
            ->select('Id', 'EnProceso', 'Posicion', 'NoProduccion', 'FechaInicio')
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->get();

        // Si hay posición actual, buscar por secuencia (Posicion mayor a la actual)
        if (!is_null($posicionActual) && $posicionActual > 0) {
            // Intentar buscar con Posicion mayor primeroggg
            // IMPORTANTE: EnProceso puede ser NULL, no solo 0
            $ordenConPosicion = DB::table('ReqProgramaTejido')
                ->whereIn('SalonTejidoId', $salones)
                ->where('NoTelarId', $noTelarId)
                ->where(function($q) {
                    $q->where('EnProceso', 0)
                      ->orWhereNull('EnProceso');
                })
                ->whereNotNull('Posicion')
                ->where('Posicion', '>', $posicionActual)
                ->select($select)
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->orderBy('Id', 'asc')
                ->first();

            if ($ordenConPosicion) {
                return $ordenConPosicion;
            }
        }

        // Si no encontró con Posicion específica, buscar cualquier orden disponible
        // Priorizar las que tienen Posicion
        // IMPORTANTE: EnProceso puede ser NULL, no solo 0
        $ordenDisponible = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->where('NoTelarId', $noTelarId)
            ->where(function($q) {
                $q->where('EnProceso', 0)
                  ->orWhereNull('EnProceso');
            })
            ->select($select)
            ->orderByRaw('CASE WHEN Posicion IS NOT NULL THEN 0 ELSE 1 END') // Priorizar Posicion
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->orderBy('Id', 'asc')
            ->first();

        return $ordenDisponible;
    }

    /**
     * Traer la siguiente orden programada usando candidatos (útil para ITEMA con 3XX/1XX).
     * Busca en todos los candidatos y retorna la primera orden siguiente encontrada.
     */
    private function fetchSiguienteOrdenConCandidatos(array $salones, array $candidatos, $fechaInicioActual, $programaIdActual = null, $posicionActual = null, array $select = null)
    {
        $select = $select ?: [
            'NoTelarId as Telar',
            'NoProduccion as Orden_Prod',
            'ItemId as ItemId',
            'TamanoClave as Tamano_AX',
            'NombreProducto as Nombre_Producto',
            'CuentaRizo as Cuenta',
            'CalibreRizo2',
            'FibraRizo as Fibra_Rizo',
            'CuentaPie as Cuenta_Pie',
            'CalibrePie2',
            'FibraPie as Fibra_Pie',
            'TotalPedido as Saldos',
            'FechaInicio as Inicio_Tejido',
            'EntregaCte as Entrega'
        ];

        // Si hay posición actual, buscar por secuencia (Posicion mayor a la actual)
        if (!is_null($posicionActual) && $posicionActual > 0) {
            // Intentar buscar con Posicion mayor primero
            // IMPORTANTE: EnProceso puede ser NULL, no solo 0
            $ordenConPosicion = DB::table('ReqProgramaTejido')
                ->whereIn('SalonTejidoId', $salones)
                ->whereIn('NoTelarId', $candidatos)
                ->where(function($q) {
                    $q->where('EnProceso', 0)
                      ->orWhereNull('EnProceso');
                })
                ->whereNotNull('Posicion')
                ->where('Posicion', '>', $posicionActual)
                ->select($select)
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->orderBy('Id', 'asc')
                ->first();

            if ($ordenConPosicion) {
                return $ordenConPosicion;
            }
        }

        // Si no encontró con Posicion específica, buscar cualquier orden disponible
        // Priorizar las que tienen Posicion
        // IMPORTANTE: EnProceso puede ser NULL, no solo 0
        return DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->whereIn('NoTelarId', $candidatos)
            ->where(function($q) {
                $q->where('EnProceso', 0)
                  ->orWhereNull('EnProceso');
            })
            ->select($select)
            ->orderByRaw('CASE WHEN Posicion IS NOT NULL THEN 0 ELSE 1 END') // Priorizar Posicion
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->orderBy('Id', 'asc')
            ->first();
    }

    /**
     * Obtener secuencia de telares desde la tabla InvSecuenciaTelares.
     * Devuelve un array de NoTelar ordenado por Secuencia.
     */
    private function getSecuenciaTelares(array $tipos): array
    {
        return DB::table('InvSecuenciaTelares')
            ->whereIn('TipoTelar', $tipos)
            ->orderBy('Secuencia')
            ->pluck('NoTelar')
            ->toArray();
    }

    /**
     * Obtener la primera orden disponible para un telar (cuando no hay proceso actual).
     */
    private function fetchPrimeraOrdenDisponible(array $salones, $noTelarId, array $select = null)
    {
        $select = $select ?: [
            'NoTelarId as Telar',
            'NoProduccion as Orden_Prod',
            'ItemId as ItemId',
            'TamanoClave as Tamano_AX',
            'NombreProducto as Nombre_Producto',
            'CuentaRizo as Cuenta',
            'CalibreRizo2',
            'FibraRizo as Fibra_Rizo',
            'CuentaPie as Cuenta_Pie',
            'CalibrePie2',
            'FibraPie as Fibra_Pie',
            'TotalPedido as Saldos',
            'FechaInicio as Inicio_Tejido',
            'EntregaCte as Entrega'
        ];

        // Buscar ordenada por Posicion (secuencia), luego por fecha
        // IMPORTANTE: EnProceso puede ser NULL, no solo 0
        $orden = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->where('NoTelarId', $noTelarId)
            ->where(function($q) {
                $q->where('EnProceso', 0)
                  ->orWhereNull('EnProceso');
            })
            ->whereNotNull('Posicion')
            ->select($select)
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->orderBy('Id', 'asc')
            ->first();

        // Si no encuentra con Posicion, buscar con FechaInicio
        if (!$orden) {
            $orden = DB::table('ReqProgramaTejido')
                ->whereIn('SalonTejidoId', $salones)
                ->where('NoTelarId', $noTelarId)
                ->where(function($q) {
                    $q->where('EnProceso', 0)
                      ->orWhereNull('EnProceso');
                })
                ->whereNotNull('FechaInicio')
                ->select($select)
                ->orderBy('FechaInicio', 'asc')
                ->orderBy('Id', 'asc')
                ->first();
        }

        // Si aún no encuentra, buscar sin restricciones
        if (!$orden) {
            $orden = DB::table('ReqProgramaTejido')
                ->whereIn('SalonTejidoId', $salones)
                ->where('NoTelarId', $noTelarId)
                ->where(function($q) {
                    $q->where('EnProceso', 0)
                      ->orWhereNull('EnProceso');
                })
                ->select($select)
                ->orderBy('Id', 'asc')
                ->first();

        }

        return $orden;
    }

    /**
     * Obtener la primera orden disponible usando candidatos (para ITEMA).
     */
    private function fetchPrimeraOrdenDisponibleConCandidatos(array $salones, array $candidatos, array $select = null)
    {
        $select = $select ?: [
            'NoTelarId as Telar',
            'NoProduccion as Orden_Prod',
            'ItemId as ItemId',
            'TamanoClave as Tamano_AX',
            'NombreProducto as Nombre_Producto',
            'CuentaRizo as Cuenta',
            'CalibreRizo2',
            'FibraRizo as Fibra_Rizo',
            'CuentaPie as Cuenta_Pie',
            'CalibrePie2',
            'FibraPie as Fibra_Pie',
            'TotalPedido as Saldos',
            'FechaInicio as Inicio_Tejido',
            'EntregaCte as Entrega'
        ];

        // Buscar ordenada por Posicion (secuencia), luego por fecha
        // IMPORTANTE: EnProceso puede ser NULL, no solo 0
        $orden = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->whereIn('NoTelarId', $candidatos)
            ->where(function($q) {
                $q->where('EnProceso', 0)
                  ->orWhereNull('EnProceso');
            })
            ->whereNotNull('Posicion')
            ->select($select)
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->orderBy('Id', 'asc')
            ->first();

        // Si no encuentra con Posicion, buscar con FechaInicio
        if (!$orden) {
            $orden = DB::table('ReqProgramaTejido')
                ->whereIn('SalonTejidoId', $salones)
                ->whereIn('NoTelarId', $candidatos)
                ->where(function($q) {
                    $q->where('EnProceso', 0)
                      ->orWhereNull('EnProceso');
                })
                ->whereNotNull('FechaInicio')
                ->select($select)
                ->orderBy('FechaInicio', 'asc')
                ->orderBy('Id', 'asc')
                ->first();
        }

        // Si aún no encuentra, buscar sin restricciones
        if (!$orden) {
            $orden = DB::table('ReqProgramaTejido')
                ->whereIn('SalonTejidoId', $salones)
                ->whereIn('NoTelarId', $candidatos)
                ->where(function($q) {
                    $q->where('EnProceso', 0)
                      ->orWhereNull('EnProceso');
                })
                ->select($select)
                ->orderBy('Id', 'asc')
                ->first();
        }

        return $orden;
    }

    /**
     * Objeto mínimo para cuando no hay telar en proceso.
     */
    private function objTelarVacio($numeroTelar)
    {
        return (object) [
            'ProgramaId'        => null,
            'Telar'             => $numeroTelar,
            'NoTelarIdOriginal' => $numeroTelar,
            'en_proceso'        => false,
            'Orden_Prod'        => null,
            'Id_Flog'           => null,
            'Cliente'           => null,
            'Tiras'             => null,
            'Tamano_AX'         => null,
            'ItemId'            => null,
            'Nombre_Producto'   => null,
            'Cuenta'            => null,
            'Calibre_Rizo'      => null,
            'Fibra_Rizo'        => null,
            'Cuenta_Pie'        => null,
            'Calibre_Pie'       => null,
            'Fibra_Pie'         => null,
            'CALIBRE_TRA'       => null,
            'COLOR_TRAMA'       => null,
            'Saldos'            => null,
            'Prod_Kg_Dia'       => null,
            'Marbetes_Pend'     => null,
            'MarbetesPend'      => null,
            'Inicio_Tejido'     => null,
            'Fin_Tejido'        => null,
            'Fecha_Compromiso'  => null,
            'Total_Paros'       => 0,
            'Tiempo_Paro'       => null,
        ];
    }
}
