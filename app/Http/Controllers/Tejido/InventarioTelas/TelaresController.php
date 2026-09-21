<?php

namespace App\Http\Controllers\Tejido\InventarioTelas;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class TelaresController extends Controller
{
    /**
     * Inventario de telares Jacquard (vista)
     * Ordenado por la tabla InvSecuenciaTelares
     */
    public function inventarioJacquard()
    {
        $telares = $this->getSecuenciaTelares(['JACQUARD']);
        $datos = $this->cargarTelares(['JACQUARD'], $telares);

        foreach ($this->ultimosJuliosPorTelar($telares) as $telar => $julios) {
            if (isset($datos[$telar])) {
                $datos[$telar]['telarData']->ultimoJulioRizo = $julios['Rizo'];
                $datos[$telar]['telarData']->ultimoJulioPie = $julios['Pie'];
            }
        }

        return view('modulos/tejido/inventario-telas/inventario-telas', [
            'telares' => $telares,
            'datosTelaresCompletos' => $datos,
            'tipoInventario' => 'jacquard',
        ]);
    }

    /**
     * Inventario de telares Itema (vista)
     * Ordenado por la tabla InvSecuenciaTelares (ITEMA/SMIT)
     */
    public function inventarioItema()
    {
        $salones = ['ITEMA', 'SMIT'];
        $telares = array_map('intval', $this->getSecuenciaTelares($salones));

        // Un telar 3XX puede estar capturado como 1XX (318 -> 118).
        $candidatosDe = fn ($telar) => $this->resolverCandidatosTelar((int) $telar, 'ITEMA');

        $datos = $this->cargarTelares($salones, $telares, $candidatosDe);

        foreach ($this->ultimosJuliosPorTelar($telares, $candidatosDe) as $telar => $julios) {
            $datos[$telar]['telarData']->ultimoJulioRizo = $julios['Rizo'];
            $datos[$telar]['telarData']->ultimoJulioPie = $julios['Pie'];
        }

        return view('modulos/tejido/inventario-telas/inventario-telas', [
            'telares' => $telares,
            'datosTelaresCompletos' => $datos,
            'tipoInventario' => 'itema',
        ]);
    }

    /**
     * Inventario de telares Karl Mayer (vista)
     *
     * InvSecuenciaTelares no tiene renglones KARL MAYER (solo ITEMA/JACQUARD/SMIT),
     * asi que la secuencia sale vacia y se completa con los telares del
     * departamento en ReqProgramaTejido (401, 402, ...).
     */
    public function inventarioKarlMayer()
    {
        $telares = array_values(array_unique(array_merge(
            $this->getSecuenciaTelares(['KARL MAYER']),
            DB::table('ReqProgramaTejido')
                ->where('SalonTejidoId', 'KARL MAYER')
                ->distinct()
                ->orderBy('NoTelarId')
                ->pluck('NoTelarId')
                ->toArray()
        )));

        $datos = $this->cargarTelares(['KARL MAYER'], $telares);

        // KM no teje rizo/pie: las cuatro barras reemplazan a los julios en la vista.
        $folios = $this->foliosBarrasPorTelar($telares);

        foreach ($datos as $telar => $info) {
            $porBarra = $folios[$telar] ?? [];
            $datos[$telar]['telarData']->barras = $this->barrasDeOrden($info['telarData'], $porBarra);
            $montados = [];
            foreach ($porBarra as $noBarra => $numeros) {
                foreach ($numeros as $noJulio) {
                    $montados[] = (object) ['Barra' => $noBarra, 'NoJulio' => $noJulio];
                }
            }
            $datos[$telar]['telarData']->juliosMontados = $montados;
        }

        return view('modulos/tejido/inventario-telas/inventario-telas', [
            'telares' => $telares,
            'datosTelaresCompletos' => $datos,
            'tipoInventario' => 'karl-mayer',
        ]);
    }

    /**
     * Carga el programa de varios telares en una sola consulta.
     *
     * Antes cada telar disparaba 4-6 queries dentro de un foreach (95 en Jacquard).
     * Aqui se trae todo el programa de los salones de golpe y el "en proceso" y la
     * "siguiente orden" se resuelven en memoria con las mismas reglas de antes:
     * en proceso = EnProceso 1 mas reciente; siguiente = la pendiente con Posicion
     * mayor, y si no hay, la primera pendiente por Posicion o por FechaInicio.
     *
     * @param  array<int, mixed>  $telares
     * @return array<mixed, array{telarData: object, ordenSig: object|null}>
     */
    private function cargarTelares(array $salones, array $telares, ?callable $candidatosDe = null): array
    {
        if ($telares === []) {
            return [];
        }

        // Itema busca cada telar con dos numeros (318 y 118), de ahi los candidatos.
        $candidatos = [];
        foreach ($telares as $telar) {
            $candidatos[(string) $telar] = array_map('strval', $candidatosDe ? $candidatosDe($telar) : [$telar]);
        }

        $programa = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->whereIn('NoTelarId', array_merge(...array_values($candidatos)))
            ->select(array_merge($this->selectColsProceso(), ['EntregaCte as Entrega']))
            ->get()
            ->groupBy(fn ($fila) => (string) $fila->Telar);

        $datos = [];

        foreach ($telares as $telar) {
            $suyos = $candidatos[(string) $telar];
            $preferido = $suyos[0];

            $filas = collect($suyos)
                ->flatMap(fn ($candidato) => ($programa[$candidato] ?? collect())->all())
                ->values();

            $enProceso = $filas->filter(fn ($f) => (int) $f->en_proceso === 1)
                // Primero el candidato preferido; entre varios en proceso, el de inicio
                // mas reciente (de ahi los negativos, para ordenar descendente).
                ->sortBy(fn ($f) => [
                    (string) $f->Telar === $preferido ? 0 : 1,
                    -1 * (int) strtotime((string) $f->Inicio_Tejido),
                    -1 * (int) $f->ProgramaId,
                ])
                ->first();

            $pendientes = $filas->filter(fn ($f) => (int) $f->en_proceso !== 1);

            if ($enProceso) {
                $posicion = (int) ($enProceso->Posicion ?? 0);
                $ordenSig = $this->ordenarPendientes(
                    $pendientes->filter(fn ($f) => $f->Posicion !== null && (int) $f->Posicion > $posicion)
                ) ?: $this->primeraPendiente($pendientes);
            } else {
                $enProceso = $this->objTelarVacio($telar);
                $ordenSig = $this->primeraPendiente($pendientes);
            }

            // Si el registro se encontro como 1XX, la vista muestra el 3XX de la secuencia.
            if (($enProceso->Telar ?? null) != $telar) {
                $enProceso->Telar = $telar;
            }

            $datos[$telar] = ['telarData' => $enProceso, 'ordenSig' => $ordenSig];
        }

        return $datos;
    }

    /** Pendiente mas proxima por Posicion, y si ninguna la tiene, por FechaInicio. */
    private function primeraPendiente($pendientes)
    {
        return $this->ordenarPendientes($pendientes->filter(fn ($f) => $f->Posicion !== null))
            ?: $this->ordenarPendientes($pendientes->filter(fn ($f) => $f->Inicio_Tejido !== null));
    }

    private function ordenarPendientes($pendientes)
    {
        return $pendientes
            ->sortBy(fn ($f) => [(int) ($f->Posicion ?? PHP_INT_MAX), (string) $f->Inicio_Tejido, (int) $f->ProgramaId])
            ->first();
    }

    /**
     * Ultimo julio de rizo y pie de varios telares en una sola consulta.
     *
     * @return array<mixed, array{Rizo: ?string, Pie: ?string}>
     */
    private function ultimosJuliosPorTelar(array $telares, ?callable $candidatosDe = null): array
    {
        if ($telares === []) {
            return [];
        }

        $candidatos = [];
        foreach ($telares as $telar) {
            $candidatos[(string) $telar] = array_map('strval', $candidatosDe ? $candidatosDe($telar) : [$telar]);
        }

        $ultimos = DB::table('AtaMontadoTelas')
            ->whereIn('NoTelarId', array_merge(...array_values($candidatos)))
            ->whereIn('Tipo', ['Rizo', 'Pie'])
            ->select('NoTelarId', 'Tipo', 'NoJulio', DB::raw(
                'ROW_NUMBER() OVER (PARTITION BY NoTelarId, Tipo ORDER BY CAST(Fecha AS DATE) DESC, Id DESC) AS rn'
            ));

        $julios = array_fill_keys($telares, ['Rizo' => null, 'Pie' => null]);
        $filas = DB::query()->fromSub($ultimos, 'u')->where('rn', 1)->get();

        foreach ($telares as $telar) {
            // El primer candidato manda; el 1XX solo cubre lo que no tenga el 3XX.
            foreach ($candidatos[(string) $telar] as $candidato) {
                foreach ($filas->where('NoTelarId', $candidato) as $fila) {
                    if (($julios[$telar][$fila->Tipo] ?? null) === null) {
                        $julios[$telar][$fila->Tipo] = $fila->NoJulio !== '' ? (string) $fila->NoJulio : null;
                    }
                }
            }
        }

        return $julios;
    }

    /**
     * API: Proceso actual de un telar
     */
    public function procesoActual($telarId)
    {
        $tipoSalon = $this->determinarTipoSalon($telarId, true);
        if (! $tipoSalon) {
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
                'FibraPie as Fibra_Pie',
                'CuentaBarra1', 'CalibreBarra1', 'FibraBarra1',
                'CuentaBarra2', 'CalibreBarra2', 'FibraBarra2',
                'CuentaBarra3', 'CalibreBarra3', 'FibraBarra3',
                'CuentaBarra4', 'CalibreBarra4', 'FibraBarra4',
            ])
            ->first();

        // Karl Mayer no teje rizo/pie: el modal de seleccion necesita las cuatro barras.
        if ($procesoActual && $tipoSalon === 'KARL MAYER') {
            $procesoActual->barras = $this->barrasDeOrden(
                $procesoActual,
                $this->foliosBarrasPorTelar([$telarId])[$telarId] ?? []
            );
        }

        return response()->json($procesoActual ?: null);
    }

    /**
     * API: Siguiente orden de un telar
     */
    public function siguienteOrden($telarId)
    {
        $tipoSalon = $this->determinarTipoSalon($telarId, true);
        if (! $tipoSalon) {
            return response()->json(['error' => 'Tipo de telar no reconocido'], 400);
        }

        $salones = ($tipoSalon === 'ITEMA' || $tipoSalon === 'SMIT') ? ['ITEMA', 'SMIT'] : [$tipoSalon];

        $telarEnProceso = DB::table('ReqProgramaTejido')
            ->whereIn('SalonTejidoId', $salones)
            ->where('NoTelarId', $telarId)
            ->where('EnProceso', 1)
            ->select('Id as ProgramaId', 'FechaInicio', 'Posicion')
            ->first();

        if (! $telarEnProceso) {
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
                'FibraPie as Fibra_Pie',
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
        if ($telar >= 200 && $telar <= 215) {
            return 'JACQUARD';
        }   // Jacquard
        if ($telar >= 299 && $telar <= 320) {
            return 'ITEMA';
        }      // Itema
        if ($telar >= 303 && $telar <= 306) {
            return 'KARL MAYER';
        } // Ajustable
        if ($telar == 401 || $telar == 402) {
            return 'KARL MAYER';
        }  // Karl Mayer

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
            'FibraComb5 as FIBRA_C5',
            // Karl Mayer: la construccion de las cuatro barras viaja en la misma orden.
            'CuentaBarra1', 'CalibreBarra1', 'FibraBarra1',
            'CuentaBarra2', 'CalibreBarra2', 'FibraBarra2',
            'CuentaBarra3', 'CalibreBarra3', 'FibraBarra3',
            'CuentaBarra4', 'CalibreBarra4', 'FibraBarra4',
        ];
    }

    /**
     * Traer la siguiente orden programada con select configurable.
     * Si hay varias con la misma FechaInicio, se toma la de Id mayor al actual.
     */
    private function fetchSiguienteOrden(array $salones, $noTelarId, $fechaInicioActual, $programaIdActual = null, $posicionActual = null, ?array $select = null)
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
            'EntregaCte as Entrega',
        ];

        // Si hay posición actual, buscar por secuencia (Posicion mayor a la actual)
        if (! is_null($posicionActual) && $posicionActual > 0) {
            // Intentar buscar con Posicion mayor primeroggg
            // IMPORTANTE: EnProceso puede ser NULL, no solo 0
            $ordenConPosicion = DB::table('ReqProgramaTejido')
                ->whereIn('SalonTejidoId', $salones)
                ->where('NoTelarId', $noTelarId)
                ->where(function ($q) {
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
            ->where(function ($q) {
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
     * Barras 1-4 de Karl Mayer a partir de la orden que las teje.
     *
     * KM no teje rizo/pie: la construccion de las cuatro barras viene en la misma
     * fila de ReqProgramaTejido ({Cuenta,Calibre,Fibra}Barra1..4). Cada barra
     * puede llevar hasta 4 julios (AtaMontadoTelas.NoJulio).
     *
     * @param  array<int, array<int, string>>  $julios  NoJulio montados por barra
     * @return array<int, object>
     */
    private function barrasDeOrden(?object $orden, array $julios): array
    {
        $barras = [];

        for ($n = 1; $n <= 4; $n++) {
            $deLaBarra = array_values(array_filter($julios[$n] ?? [], fn ($julio) => $julio !== ''));
            $barras[$n] = (object) [
                'Cuenta' => $orden->{"CuentaBarra{$n}"} ?? null,
                'Calibre' => $orden->{"CalibreBarra{$n}"} ?? null,
                'Fibra' => $orden->{"FibraBarra{$n}"} ?? null,
                'Julios' => $deLaBarra,
            ];
        }

        return $barras;
    }

    /**
     * Julios montados de cada barra, hasta 4 por barra.
     *
     * Misma tabla que J Rizo / J Pie: AtaMontadoTelas. En Jacquard hay un julio
     * por Tipo. En Karl Mayer una barra (Tipo 1..4) puede tener hasta 4 NoJulio
     * reservados a la vez; se toman los 4 mas recientes por fecha y luego por Id.
     *
     * @return array<mixed, array<int, array<int, string>>>
     */
    private function foliosBarrasPorTelar(array $telares): array
    {
        if ($telares === []) {
            return [];
        }

        $barra = "CASE
            WHEN UPPER(LTRIM(RTRIM(Tipo))) IN ('1', 'B1', 'BARRA 1', 'BARRA1') THEN '1'
            WHEN UPPER(LTRIM(RTRIM(Tipo))) IN ('2', 'B2', 'BARRA 2', 'BARRA2') THEN '2'
            WHEN UPPER(LTRIM(RTRIM(Tipo))) IN ('3', 'B3', 'BARRA 3', 'BARRA3') THEN '3'
            WHEN UPPER(LTRIM(RTRIM(Tipo))) IN ('4', 'B4', 'BARRA 4', 'BARRA4') THEN '4'
            ELSE NULL
        END";

        $ultimos = DB::table('AtaMontadoTelas')
            ->whereIn('NoTelarId', array_map('strval', $telares))
            ->whereRaw("{$barra} IS NOT NULL")
            ->select('NoTelarId', 'Id', 'NoJulio', DB::raw("{$barra} AS Barra"), DB::raw(
                "ROW_NUMBER() OVER (PARTITION BY NoTelarId, {$barra} ORDER BY CAST(Fecha AS DATE) DESC, Id DESC) AS rn"
            ));

        $julios = [];
        foreach ($telares as $telar) {
            $julios[$telar] = [1 => [], 2 => [], 3 => [], 4 => []];
        }

        $filas = DB::query()->fromSub($ultimos, 'u')->where('rn', '<=', 4)->get()
            ->sortBy('Id')
            ->values();

        foreach ($filas as $fila) {
            if ($fila->NoJulio === null || $fila->NoJulio === '') {
                continue;
            }
            foreach ($telares as $telar) {
                if ((string) $telar === (string) $fila->NoTelarId) {
                    $julios[$telar][(int) $fila->Barra][] = (string) $fila->NoJulio;
                }
            }
        }

        return $julios;
    }

    /**
     * Objeto mínimo para cuando no hay telar en proceso.
     */
    private function objTelarVacio($numeroTelar)
    {
        return (object) [
            'ProgramaId' => null,
            'Telar' => $numeroTelar,
            'NoTelarIdOriginal' => $numeroTelar,
            'en_proceso' => false,
            'Orden_Prod' => null,
            'Id_Flog' => null,
            'Cliente' => null,
            'Tiras' => null,
            'Tamano_AX' => null,
            'ItemId' => null,
            'Nombre_Producto' => null,
            'Cuenta' => null,
            'Calibre_Rizo' => null,
            'Fibra_Rizo' => null,
            'Cuenta_Pie' => null,
            'Calibre_Pie' => null,
            'Fibra_Pie' => null,
            'CALIBRE_TRA' => null,
            'COLOR_TRAMA' => null,
            'Saldos' => null,
            'Prod_Kg_Dia' => null,
            'Marbetes_Pend' => null,
            'MarbetesPend' => null,
            'Inicio_Tejido' => null,
            'Fin_Tejido' => null,
            'Fecha_Compromiso' => null,
            'Total_Paros' => 0,
            'Tiempo_Paro' => null,
            'ultimoJulioRizo' => null,
            'ultimoJulioPie' => null,
        ];
    }
}
