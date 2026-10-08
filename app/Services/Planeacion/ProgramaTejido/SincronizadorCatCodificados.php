<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Helpers\AuditoriaHelper;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\ColumnasDeTabla;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copia a CatCodificados lo que cambió en un programa de tejido (extraído de
 * ReqProgramaTejidoObserver). La fila se localiza por OrdenTejido = NoProduccion, acotada
 * al telar del registro cuando lo trae (igual que LiberarOrdenesController).
 *
 * Los mensajes de log conservan el prefijo ReqProgramaTejidoObserver:: porque el monitoreo
 * (huellas en SYSMonError) y los tests los buscan así.
 */
final class SincronizadorCatCodificados
{
    /**
     * Campo en ReqProgramaTejido => columna en CatCodificados. Se copia solo si el campo
     * cambió en este save (wasChanged).
     */
    private const CAMPOS = [
        'TamanoClave' => 'ClaveModelo',
        'ItemId' => 'ItemId',
        'TotalPedido' => 'Pedido',
        'SaldoPedido' => 'Saldos',
        'Produccion' => 'Produccion',
        // La producción de marbetes NO se sincroniza: en ReqProgramaTejido son piezas y en
        // CatCodificados son marbetes (lo alimenta el proceso externo). Distinta unidad.
        'FlogsId' => 'FlogsId',
        'NombreProyecto' => 'NombreProyecto',
        'PesoCrudo' => 'P_crudo',
        // La fila en CatCodificados se localiza por OrdenTejido + TelarId al liberar/editar:
        // si la orden se mueve de telar o salón, CatCodificados debe seguirla.
        'NoTelarId' => 'TelarId',
        'SalonTejidoId' => 'Departamento',
    ];

    private const AUDITORIA = ['FechaModificacion' => 1, 'HoraModificacion' => 1, 'UsuarioModifica' => 1];

    /**
     * Copia los campos de {@see self::CAMPOS} que cambiaron. Nunca lanza: registra y reporta.
     */
    public function sincronizarCambios(ReqProgramaTejido $programa): void
    {
        try {
            $noProduccion = trim((string) ($programa->NoProduccion ?? ''));
            $cambios = $noProduccion === '' ? [] : $this->camposCambiados($programa);
            if ($cambios === []) {
                return;
            }

            $tabla = (new CatCodificados)->getTable();

            // Filtrar a las columnas que existen en CatCodificados (defensa por si una columna se
            // renombró en SQL Server).
            $columnasExistentes = ColumnasDeTabla::de($tabla);
            if ($columnasExistentes === []) {
                // PT-02, hallazgo 5: tabla ilegible ≠ "nada que sincronizar". Antes se saltaba sin log.
                Log::error('ReqProgramaTejidoObserver::sincronizarCatCodificados error', [
                    'programa_id' => $programa->Id ?? null,
                    'message' => "Sin columnas legibles en {$tabla}: CatCodificados no se sincronizó.",
                ]);

                return;
            }
            $cambiosFiltrados = array_intersect_key($cambios, array_flip($columnasExistentes));

            // Si tras el filtro quedan solo los campos de auditoría, no vale la pena actualizar.
            if (empty(array_diff_key($cambiosFiltrados, self::AUDITORIA))) {
                return;
            }

            // Se acota al telar del registro, igual que LiberarOrdenesController::actualizarCatCodificados.
            // Sin este filtro el update masivo por OrdenTejido escribía los datos de un telar en las
            // filas de los otros: hay órdenes repartidas en dos telares y cada una tiene sus propias
            // métricas. Si el registro no trae telar se conserva el update masivo, que es el caso de
            // Balanceo (varias filas de la misma orden sin telar propio).
            $noTelarId = trim((string) ($programa->NoTelarId ?? ''));

            $query = $programa->getConnection()->table($tabla)->where('OrdenTejido', $noProduccion);
            if ($noTelarId !== '' && in_array('TelarId', $columnasExistentes, true)) {
                $query->where('TelarId', $noTelarId);
            }

            $afectadas = $query->update($cambiosFiltrados);

            Log::info('ReqProgramaTejidoObserver: CatCodificados sincronizado', [
                'orden' => $noProduccion,
                'telar' => $noTelarId !== '' ? $noTelarId : 'todos',
                'filas_afectadas' => $afectadas,
                'campos_solicitados' => array_keys($cambios),
                'campos_actualizados' => array_keys($cambiosFiltrados),
            ]);
        } catch (Throwable $e) {
            Log::error('ReqProgramaTejidoObserver::sincronizarCatCodificados error', [
                'programa_id' => $programa->Id ?? null,
                'message' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * Copia a CatCodificados las fórmulas de producción ya recalculadas. Corre dentro de la
     * transacción de quien llama (junto con el UPDATE de la cabecera) y lanza si falla.
     * Mismo criterio que LiberarOrdenesController: OrdenTejido + TelarId; solo por OrdenTejido
     * se pisaban filas de otros telares cuando un folio se reparte entre varios.
     *
     * @param  array{repeticiones: int, pzasRollo: float, mtsRollo: float|null, totalRollos: float|null, totalPzas: float|null}  $resultados
     * @return int filas afectadas (0 si el registro no tiene NoProduccion)
     */
    public function propagarFormulas(ConnectionInterface $connection, ReqProgramaTejido $programa, array $resultados): int
    {
        $noProduccion = trim((string) ($programa->NoProduccion ?? ''));
        if ($noProduccion === '') {
            return 0;
        }

        $queryCat = $connection->table((new CatCodificados)->getTable())->where('OrdenTejido', $noProduccion);
        $telar = trim((string) ($programa->NoTelarId ?? ''));
        if ($telar !== '') {
            $queryCat->where('TelarId', $telar);
        }
        $updateCat = [
            'Repeticiones' => $resultados['repeticiones'],
            'PzasRollo' => $resultados['pzasRollo'],
            'MtsRollo' => $resultados['mtsRollo'],
            'TotalRollos' => $resultados['totalRollos'],
            'TotalPzas' => $resultados['totalPzas'],
            'FechaModificacion' => Carbon::now()->format('Y-m-d'),
            'HoraModificacion' => Carbon::now()->format('H:i:s'),
        ];
        $usuario = AuditoriaHelper::obtenerUsuarioActual();
        if (! empty($usuario)) {
            $updateCat['UsuarioModifica'] = $usuario;
        }

        return $queryCat->update($updateCat);
    }

    /**
     * Campos del mapeo que cambiaron, más fecha/hora/usuario de modificación.
     *
     * @return array<string, mixed>
     */
    private function camposCambiados(ReqProgramaTejido $programa): array
    {
        $cambios = [];
        foreach (self::CAMPOS as $campoRpt => $campoCat) {
            if ($programa->wasChanged($campoRpt)) {
                $cambios[$campoCat] = $programa->getAttribute($campoRpt);
            }
        }
        if ($cambios === []) {
            return [];
        }

        $now = Carbon::now();
        $cambios['FechaModificacion'] = $now->format('Y-m-d');
        $cambios['HoraModificacion'] = $now->format('H:i:s');
        try {
            $usuario = AuditoriaHelper::obtenerUsuarioActual();
            if (! empty($usuario)) {
                $cambios['UsuarioModifica'] = $usuario;
            }
        } catch (Throwable) {
            // Si el helper no está disponible, se omite UsuarioModifica.
        }

        return $cambios;
    }
}
