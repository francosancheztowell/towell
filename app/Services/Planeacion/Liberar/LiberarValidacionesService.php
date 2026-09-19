<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Liberar;

use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Validaciones de folio único y métricas de producción al liberar.
 *
 * Extraído de LiberarOrdenesController. Misma entrada → misma salida:
 * NoProduccion no puede repetirse en otro renglón del programa ni en
 * CatCodificados de otro telar; tiras, saldo, repeticiones, marbetes,
 * metros, pzas, rollos, piezas y (si hay datos) densidad deben ser > 0.
 */
final class LiberarValidacionesService
{
    /** Cache en memoria para Schema::getColumnListing, por nombre de tabla. */
    private array $columnListingCache = [];

    /**
     * OrdenTejido / NoProduccion debe ser único: no puede repetirse en otro registro de programa
     * ni en CatCodificados para otro telar (mismo telar = fila que se actualizará al liberar).
     *
     * @return string|null mensaje de error para JSON, o null si el folio es válido
     */
    public function validarOrdenTejidoUnicoParaLiberacion(string $folio, ReqProgramaTejido $registro): ?string
    {
        $folio = trim($folio);
        if ($folio === '') {
            return 'No se pudo validar el número de orden.';
        }

        $duplicadoPrograma = ReqProgramaTejido::query()
            ->where('NoProduccion', $folio)
            ->where('Id', '!=', $registro->Id)
            ->exists();

        if ($duplicadoPrograma) {
            return 'El número de orden "'.$folio.'" ya está asignado en otro registro del programa de tejido.';
        }

        try {
            $modelo = new CatCodificados;
            $table = $modelo->getTable();
            $columns = $this->columnasDeTabla($table);

            $query = CatCodificados::query();
            $hasKeyFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $folio);
                $hasKeyFilter = true;
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $folio);
                $hasKeyFilter = true;
            }

            if (! $hasKeyFilter && in_array('NoProduccion', $columns, true)) {
                $query->where('NoProduccion', $folio);
                $hasKeyFilter = true;
            }

            if (! $hasKeyFilter) {
                return null;
            }

            $codificados = $query->get();
            if ($codificados->isEmpty()) {
                return null;
            }

            $telarCol = null;
            if (in_array('TelarId', $columns, true)) {
                $telarCol = 'TelarId';
            } elseif (in_array('NoTelarId', $columns, true)) {
                $telarCol = 'NoTelarId';
            }

            $noTelarSesion = trim((string) ($registro->NoTelarId ?? ''));

            if ($telarCol === null) {
                return 'El número de orden "'.$folio.'" ya existe en catálogo codificados.';
            }

            foreach ($codificados as $c) {
                $telarCod = trim((string) ($c->{$telarCol} ?? ''));
                if ($telarCod !== $noTelarSesion) {
                    return 'El número de orden "'.$folio.'" ya existe en codificados para otro telar.';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('validarOrdenTejidoUnicoParaLiberacion', [
                'folio' => $folio,
                'error' => $e->getMessage(),
            ]);

            return 'No se pudo validar la unicidad del número de orden en codificados.';
        }

        return null;
    }

    /**
     * Tiras, saldo en toallas (SaldoPedido), marbetes, metros x rollo y resto de métricas no pueden ser cero ni nulos al liberar.
     * Combina trama y observaciones opcionales.
     */
    public function validarMetricasProduccionParaLiberacion(ReqProgramaTejido $registro): ?string
    {
        $ref = $this->referenciaCortaRegistro($registro);

        $tiras = $registro->NoTiras;
        if ($tiras === null || ! is_numeric($tiras) || (int) $tiras <= 0) {
            return 'Las tiras deben ser mayores a cero (no se puede liberar con tiras vacías o en cero).'.$ref;
        }

        $saldoToallas = $registro->SaldoPedido;
        if ($saldoToallas === null || ! is_numeric($saldoToallas) || (float) $saldoToallas <= 0.0) {
            return 'El saldo pedido en toallas debe ser mayor a cero.'.$ref;
        }

        $rep = $registro->Repeticiones;
        if ($rep === null || ! is_numeric($rep) || (int) $rep <= 0) {
            return 'Repeticiones deben ser mayores a cero según la fórmula (revisa peso de rollo, peso crudo y tiras).'.$ref;
        }

        $sm = $registro->SaldoMarbete;
        if ($sm === null || ! is_numeric($sm) || (float) $sm <= 0.0) {
            return 'No marbetes no puede ser cero ni vacío; debe coincidir con la fórmula.'.$ref;
        }

        $mts = $registro->MtsRollo;
        if ($mts === null || ! is_numeric($mts) || (float) $mts <= 0.0) {
            return 'Metros x rollo deben ser mayores a cero (no pueden quedar vacíos o en cero).'.$ref;
        }

        $pzas = $registro->PzasRollo;
        if ($pzas === null || ! is_numeric($pzas) || (float) $pzas <= 0.0) {
            return 'Pzas x rollo deben ser mayores a cero según la fórmula.'.$ref;
        }

        $tr = $registro->TotalRollos;
        if ($tr === null || ! is_numeric($tr) || (float) $tr <= 0.0) {
            return 'Total rollos debe ser mayor a cero.'.$ref;
        }

        $tp = $registro->TotalPzas;
        if ($tp === null || ! is_numeric($tp) || (float) $tp <= 0.0) {
            return 'Total piezas (toallas) debe ser mayor a cero.'.$ref;
        }

        if ($this->registroTieneDatosParaCalcularDensidad($registro)) {
            $den = $registro->Densidad;
            if ($den === null || ! is_numeric($den) || (float) $den <= 0.0) {
                return 'Densidad debe ser mayor a cero (revisa ancho, largo y peso crudo).'.$ref;
            }
        }

        return null;
    }

    /**
     * Referencia corta para mensajes 422: Id + producto/item si existen.
     */
    public function referenciaCortaRegistro(ReqProgramaTejido $registro): string
    {
        $partes = array_filter([
            $registro->NombreProducto !== null ? trim((string) $registro->NombreProducto) : '',
            $registro->ItemId !== null ? trim((string) $registro->ItemId) : '',
        ], static fn (string $s): bool => $s !== '');

        $extra = $partes !== [] ? ' — '.implode(' / ', $partes) : '';

        return ' (Id '.$registro->Id.$extra.')';
    }

    /**
     * ¿Peso, ancho y largo presentes para poder exigir densidad mayor a cero?
     */
    private function registroTieneDatosParaCalcularDensidad(ReqProgramaTejido $registro): bool
    {
        $peso = $registro->PesoCrudo ?? null;
        $ancho = $registro->Ancho ?? null;
        $largo = $registro->LargoCrudo ?? null;
        if ($peso === null || $ancho === null || $largo === null) {
            return false;
        }
        if (! is_numeric($peso) || ! is_numeric($ancho)) {
            return false;
        }
        if ((float) $ancho <= 0.0) {
            return false;
        }
        $largoNum = is_numeric($largo)
            ? (float) $largo
            : (float) str_replace([' Cms.', 'Cms.', 'cm', 'CM', ' '], '', (string) $largo);

        return $largoNum > 0.0;
    }

    /**
     * Columnas de una tabla con cache por instancia para evitar consultar la metadata
     * en cada renglón. La metadata no cambia durante el request.
     *
     * @return array<int, string>
     */
    private function columnasDeTabla(string $table): array
    {
        if (! isset($this->columnListingCache[$table])) {
            $this->columnListingCache[$table] = Schema::getColumnListing($table);
        }

        return $this->columnListingCache[$table];
    }
}
