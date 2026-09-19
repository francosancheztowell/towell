<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Liberar;

use App\Helpers\AuditoriaHelper;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Escritura de CatCodificados / ReqModelosCodificados al liberar o editar campos.
 *
 * Extraído de LiberarOrdenesController. Misma entrada → mismos side effects en BD.
 * CreaProd = 1 al liberar es intencional (BUG-004 wontfix): no “corregir” ese flag.
 */
final class LiberarCatCodificadosWriter
{
    /** Cache de metadata de columnas por instancia (un request / un writer). */
    private array $columnListingCache = [];

    public function __construct(
        private readonly LiberarCodigoDibujoResolver $codigoDibujoResolver = new LiberarCodigoDibujoResolver,
    ) {}

    /**
     * Columnas de una tabla con cache para evitar consultar la metadata
     * en cada save/registro. Por instancia: no se comparte entre tests ni requests.
     */
    private function columnasDeTabla(string $table): array
    {
        if (! isset($this->columnListingCache[$table])) {
            $this->columnListingCache[$table] = Schema::getColumnListing($table);
        }

        return $this->columnListingCache[$table];
    }

    /**
     * Actualiza un campo específico en CatCodificados basado en ReqProgramaTejido
     */
    public function actualizarCampo(ReqProgramaTejido $registro, string $field, mixed $value): void
    {
        try {
            $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
            $noTelarId = trim((string) ($registro->NoTelarId ?? ''));

            // Si no hay NoProduccion, no podemos actualizar CatCodificados
            if (empty($noProduccion)) {
                return;
            }

            $modelo = new CatCodificados;
            $table = $modelo->getTable();
            $columns = $this->columnasDeTabla($table);

            $query = CatCodificados::query();
            $hasKeyFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $noProduccion);
                $hasKeyFilter = true;
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $noProduccion);
                $hasKeyFilter = true;
            }

            if (in_array('TelarId', $columns, true)) {
                $query->where('TelarId', $noTelarId);
            } elseif (in_array('NoTelarId', $columns, true)) {
                $query->where('NoTelarId', $noTelarId);
            }

            if (! $hasKeyFilter) {
                $query->where('NoProduccion', $noProduccion);
            }

            $registroCodificado = $query->first();

            if (! $registroCodificado) {
                return;
            }

            // Mapear el campo de ReqProgramaTejido a CatCodificados
            $campoCatCodificados = null;
            if ($field === 'SaldoMarbete') {
                $campoCatCodificados = 'NoMarbete';
            } elseif ($field === 'CombinaTrama') {
                $campoCatCodificados = 'CombinaTram';
            } elseif ($field === 'CambioHilo' || $field === 'CambioRepaso') {
                $campoCatCodificados = 'CambioRepaso';
            } else {
                $campoCatCodificados = $field;
            }

            // Verificar que el campo existe en CatCodificados
            if (! in_array($campoCatCodificados, $columns, true)) {
                return;
            }

            // Asignar el valor según el tipo de campo
            if ($campoCatCodificados === 'NoMarbete') {
                $registroCodificado->NoMarbete = $value !== null && $value !== '' ? (float) round((float) $value, 0) : null;
            } elseif ($campoCatCodificados === 'Repeticiones') {
                $registroCodificado->Repeticiones = $value !== null && $value !== '' ? (int) (float) $value : null;
            } elseif ($campoCatCodificados === 'NoTiras') {
                $registroCodificado->NoTiras = $value !== null && $value !== '' ? (int) round((float) $value) : null;
            } elseif ($campoCatCodificados === 'Densidad') {
                $registroCodificado->Densidad = $value !== null ? round((float) $value, 4) : null;
            } elseif ($campoCatCodificados === 'CombinaTram') {
                $registroCodificado->CombinaTram = $value !== null ? trim((string) $value) : null;
            } elseif ($campoCatCodificados === 'CambioRepaso') {
                $registroCodificado->CambioRepaso = $value !== null && strtoupper(trim((string) $value)) === 'SI' ? 'SI' : 'NO';
            } elseif ($campoCatCodificados === 'TotalRollos') {
                // TotalRollos, redondear hacia arriba si hay decimal
                $registroCodificado->TotalRollos = $value !== null ? (float) ceil((float) $value) : null;
            } else {
                // MtsRollo, PzasRollo, TotalPzas
                $registroCodificado->{$campoCatCodificados} = $value !== null ? (float) $value : null;
            }

            $registroCodificado->save();
        } catch (\Exception $e) {
            Log::error('Error al actualizar CatCodificados campo editable', [
                'no_produccion' => $registro->NoProduccion ?? null,
                'field' => $field,
                'error' => $e->getMessage(),
            ]);
            // No lanzar excepción para no interrumpir el guardado en ReqProgramaTejido
        }
    }

    /**
     * Actualiza CatCodificados con los campos de ReqProgramaTejido después de liberar.
     *
     * @param  string|null  $codigoDibujoDesdePantalla  Valor de la columna Codigo Dibujo al liberar (mismo que en la vista); si es null/vacío se intenta resolver con CatCodificados.
     */
    public function actualizar(ReqProgramaTejido $registro, ?string $codigoDibujoDesdePantalla = null, ?bool $asignarFlogs = null): bool
    {
        try {
            $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
            $noTelarId = trim((string) ($registro->NoTelarId ?? ''));

            if (empty($noProduccion)) {
                return false;
            }

            $modelo = new CatCodificados;
            $table = $modelo->getTable();
            $columns = $this->columnasDeTabla($table);

            $query = CatCodificados::query();
            $hasKeyFilter = false;

            if (in_array('OrdenTejido', $columns, true)) {
                $query->where('OrdenTejido', $noProduccion);
                $hasKeyFilter = true;
            } elseif (in_array('NumOrden', $columns, true)) {
                $query->where('NumOrden', $noProduccion);
                $hasKeyFilter = true;
            }

            if (in_array('TelarId', $columns, true)) {
                $query->where('TelarId', $noTelarId);
            } elseif (in_array('NoTelarId', $columns, true)) {
                $query->where('NoTelarId', $noTelarId);
            }

            if (! $hasKeyFilter) {
                $query->where('NoProduccion', $noProduccion);
            }

            $registroCodificado = $query->first();

            if (! $registroCodificado) {
                // Normal en órdenes nuevas: la fila la crea después la orden de cambio.
                return false;
            }

            // Código de dibujo: priorizar lo enviado desde la grilla (autollenado o edición); si no, último CatCodificados (misma lógica que obtenerCodigoDibujo)
            $codigoDibujoFinal = null;
            $explicito = $codigoDibujoDesdePantalla !== null ? trim($codigoDibujoDesdePantalla) : '';
            if ($explicito !== '') {
                $codigoDibujoFinal = $explicito;
            } elseif (in_array('CodigoDibujo', $columns, true)) {
                $resuelto = $this->codigoDibujoResolver->resolver(
                    trim((string) ($registro->ItemId ?? '')),
                    trim((string) ($registro->InventSizeId ?? '')),
                    trim((string) ($registro->SalonTejidoId ?? ''))
                );
                if ($resuelto !== null && $resuelto !== '') {
                    $codigoDibujoFinal = $resuelto;
                }
            }

            // Valores alineados a fórmulas Excel (repeticiones = TRUNCAR; no. marbetes = float sin forzar techo)
            $payload = [
                'BomId' => $registro->BomId,
                'BomName' => $registro->BomName,
                'HiloAX' => $registro->HiloAX,
                'MtsRollo' => $registro->MtsRollo,
                'PzasRollo' => $registro->PzasRollo,
                'TotalRollos' => $registro->TotalRollos !== null ? (float) ceil((float) $registro->TotalRollos) : null,
                'TotalPzas' => $registro->TotalPzas,
                'Repeticiones' => $registro->Repeticiones !== null ? (int) (float) $registro->Repeticiones : null,
                'NoTiras' => $registro->NoTiras !== null && is_numeric($registro->NoTiras) ? (int) $registro->NoTiras : null,
                'NoMarbete' => $registro->SaldoMarbete !== null ? (float) round((float) $registro->SaldoMarbete, 0) : null, // SaldoMarbete en ReqProgramaTejido = NoMarbete en CatCodificados
                'CombinaTram' => $registro->CombinaTram,
                'CambioRepaso' => $registro->CambioHilo,
                'Densidad' => $registro->Densidad !== null ? (float) $registro->Densidad : null,
                'Obs5' => $registro->Observaciones,
                'CreaProd' => 1,
                'ActualizaLmat' => $registro->ActualizaLmat ?? 0,
                'FlogsId' => $registro->FlogsId,
                'NombreProyecto' => $registro->NombreProyecto,
                'CategoriaCalidad' => $registro->CategoriaCalidad,
                'CustName' => $registro->CustName,
                'PesoMuestra' => $registro->PesoMuestra,
                'OrdPrincipal' => $registro->OrdPrincipal,
            ];

            // 0 = la orden no lleva flog, 1 = sí lo lleva. Sólo se escribe cuando el renglón
            // está en el catálogo y el usuario decidió; si no, la columna se deja intacta.
            if ($asignarFlogs !== null) {
                $payload['AsignarFlogs'] = $asignarFlogs ? 1 : 0;
            }

            $updated = false;

            // Asignar todos los campos del payload, EXCEPTO TotalRollos y TotalPzas que se manejarán después.
            // Comparación case-insensitive contra los nombres reales de columnas en SQL Server, para evitar
            // problemas si el casing del payload no coincide exactamente con el de la BD.
            foreach ($payload as $column => $value) {
                if ($column === 'TotalRollos' || $column === 'TotalPzas') {
                    continue;
                }

                $existeColumna = false;
                $columnaReal = $column;
                foreach ($columns as $colDb) {
                    if (strcasecmp($colDb, $column) === 0) {
                        $existeColumna = true;
                        $columnaReal = $colDb;
                        break;
                    }
                }

                if (! $existeColumna) {
                    continue;
                }

                $registroCodificado->setAttribute($columnaReal, $value);
                $updated = true;
            }

            // FORZAR asignación de TotalRollos y TotalPzas SIEMPRE (incluso si son null)
            // Aplicar ceil() a TotalRollos si hay decimal
            $valorTotalRollos = $registro->TotalRollos !== null ? (float) ceil((float) $registro->TotalRollos) : null;
            $valorTotalPzas = $registro->TotalPzas !== null ? (float) $registro->TotalPzas : null;

            if (in_array('TotalRollos', $columns, true)) {
                $registroCodificado->TotalRollos = $valorTotalRollos;
                $updated = true;
            }
            if (in_array('TotalPzas', $columns, true)) {
                $registroCodificado->TotalPzas = $valorTotalPzas;
                $updated = true;
            }

            // FORZAR asignación de CategoriaCalidad SIEMPRE (incluso si es null)
            if (in_array('CategoriaCalidad', $columns, true)) {
                $registroCodificado->CategoriaCalidad = $registro->CategoriaCalidad;
                $updated = true;
            }

            // FORZAR asignación de CustName SIEMPRE (incluso si es null)
            if (in_array('CustName', $columns, true)) {
                $registroCodificado->CustName = $registro->CustName;
                $updated = true;
            }

            // FORZAR asignación de PesoMuestra SIEMPRE (incluso si es null)
            if (in_array('PesoMuestra', $columns, true)) {
                $registroCodificado->PesoMuestra = $registro->PesoMuestra !== null ? (float) $registro->PesoMuestra : null;
                $updated = true;
            }

            // FORZAR asignación de OrdPrincipal SIEMPRE (incluso si es null)
            if (in_array('OrdPrincipal', $columns, true)) {
                $ordPrincipalRaw = $registro->OrdPrincipal;
                $ordPrincipalValue = null;
                if ($ordPrincipalRaw !== null && $ordPrincipalRaw !== '') {
                    $ordPrincipalStr = trim((string) $ordPrincipalRaw);
                    if (is_numeric($ordPrincipalStr)) {
                        $ordPrincipalValue = (int) $ordPrincipalStr;
                    } elseif ($ordPrincipalStr !== '') {
                        $ordPrincipalValue = $ordPrincipalStr;
                    }
                }
                $registroCodificado->OrdPrincipal = $ordPrincipalValue;
                $updated = true;
            }

            if ($codigoDibujoFinal !== null && in_array('CodigoDibujo', $columns, true)) {
                $registroCodificado->CodigoDibujo = $codigoDibujoFinal;
                $updated = true;
            }

            // FORZAR OrdCompartida y OrdCompartidaLider siempre (incluso si son null) para mantener
            // el seguimiento de órdenes compartidas en CatCodificados sincronizado con ReqProgramaTejido.
            if (in_array('OrdCompartida', $columns, true)) {
                $ordCompartidaRaw = $registro->OrdCompartida;
                $registroCodificado->OrdCompartida = ($ordCompartidaRaw !== null && trim((string) $ordCompartidaRaw) !== '')
                    ? (int) trim((string) $ordCompartidaRaw)
                    : null;
                $updated = true;
            }

            if (in_array('OrdCompartidaLider', $columns, true)) {
                $esLider = $registro->OrdCompartidaLider === 1
                    || $registro->OrdCompartidaLider === true
                    || $registro->OrdCompartidaLider === '1';
                $registroCodificado->OrdCompartidaLider = $esLider ? 1 : null;
                $updated = true;
            }

            // Aplicar campos de auditoría: primero creación si no existen, luego modificación
            // Usar false para aplicar ambos (creación y modificación)
            AuditoriaHelper::aplicarCamposAuditoria($registroCodificado, false);

            // Forzar UsuarioCrea si no existe y la columna existe
            if (in_array('UsuarioCrea', $columns, true)) {
                $usuarioActual = trim((string) ($registroCodificado->UsuarioCrea ?? ''));
                if (empty($usuarioActual)) {
                    $usuario = AuditoriaHelper::obtenerUsuarioActual();
                    $registroCodificado->UsuarioCrea = $usuario;
                    $updated = true;
                }
            }

            if ($updated || $registroCodificado->isDirty()) {
                $registroCodificado->save();
                $registroCodificado->refresh();
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('LiberarCatCodificadosWriter::actualizar error', [
                'orden' => $registro->NoProduccion ?? null,
                'telar' => $registro->NoTelarId ?? null,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Actualiza ReqModelosCodificados con OrdPrincipal y PesoMuestra desde ReqProgramaTejido.
     * Busca por TamanoClave, ClaveModelo o OrdenTejido (NoProduccion).
     */
    public function actualizarReqModelos(ReqProgramaTejido $registro): void
    {
        try {
            $tamanoClave = trim((string) ($registro->TamanoClave ?? ''));
            $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
            $salonTejidoId = trim((string) ($registro->SalonTejidoId ?? ''));

            if (empty($tamanoClave) && empty($noProduccion)) {
                return;
            }

            $query = ReqModelosCodificados::query();

            // Buscar por OrdenTejido (NoProduccion) si está disponible
            if (! empty($noProduccion)) {
                $query->where('OrdenTejido', $noProduccion);
            } elseif (! empty($tamanoClave)) {
                // Si no hay OrdenTejido, buscar por TamanoClave
                $query->where('TamanoClave', $tamanoClave);
                // Si hay SalonTejidoId, filtrar por él también
                if (! empty($salonTejidoId)) {
                    $query->where('SalonTejidoId', $salonTejidoId);
                }
            } else {
                return;
            }

            $modelos = $query->get();

            if ($modelos->isEmpty()) {
                return;
            }

            // Obtener valores a actualizar
            $pesoMuestra = $registro->PesoMuestra !== null ? (float) $registro->PesoMuestra : null;
            $ordPrincipalRaw = $registro->OrdPrincipal;
            $ordPrincipal = null;
            if ($ordPrincipalRaw !== null && $ordPrincipalRaw !== '') {
                $ordPrincipalStr = trim((string) $ordPrincipalRaw);
                // Si es numérico, convertir a int; si no, intentar parsearlo
                if (is_numeric($ordPrincipalStr)) {
                    $ordPrincipal = (int) $ordPrincipalStr;
                } elseif ($ordPrincipalStr !== '') {
                    // Si no es numérico pero tiene valor, intentar guardarlo (puede fallar si la columna es INT)
                    $ordPrincipal = $ordPrincipalStr;
                }
            }

            // Actualizar todos los registros encontrados
            foreach ($modelos as $modelo) {
                $updated = false;
                if ($pesoMuestra !== null) {
                    $modelo->PesoMuestra = $pesoMuestra;
                    $updated = true;
                }
                if ($ordPrincipal !== null) {
                    $modelo->OrdPrincipal = $ordPrincipal;
                    $updated = true;
                }
                if ($updated) {
                    $modelo->save();
                }
            }
        } catch (\Throwable $e) {
            // Antes este catch estaba vacío y los errores de sync ReqModelosCodificados se perdían en
            // silencio: el registro de ReqProgramaTejido quedaba commiteado pero su espejo en el
            // catálogo no, generando inconsistencia silenciosa. Ver auditoría QW9.
            Log::warning('LiberarOrdenes: actualizarReqModelosCodificados falló (sync ReqModelosCodificados)', [
                'registro_id' => $registro->Id ?? null,
                'no_produccion' => $registro->NoProduccion ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Alias de {@see LiberarCodigoDibujoResolver::resolver()} para callers del writer.
     */
    public function resolverCodigoDibujo(string $itemId, string $inventSizeId, string $departamento): ?string
    {
        return $this->codigoDibujoResolver->resolver($itemId, $inventSizeId, $departamento);
    }
}
