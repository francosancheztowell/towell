<?php

namespace App\Services\Atadores;

use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Tejido\TejInventarioTelares;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Filas del tablero /atadores/programaatadores.
 * Sin filtro en la URL recorta por rol. Con filtro (el botón Filtrar) devuelve
 * el universo y el navegador aplica Activo, En Proceso, etc.
 */
class ProgramaAtadoresListado
{
    /** @var array<int, string>|null */
    private ?array $telaresUsuario = null;

    /**
     * @return Collection<int, TejInventarioTelares|object>
     */
    public function filas(object $user, ?string $filtro): Collection
    {
        if ($filtro === 'autorizados') {
            return $this->autorizados();
        }

        $query = $this->consultaBase();

        if ($filtro === null) {
            $this->aplicarRol($query, $user);
        }

        return $query
            ->orderBy('tej_inventario_telares.fecha')
            ->orderBy('tej_inventario_telares.turno')
            ->get();
    }

    /**
     * @return array{filtroAplicado: string, telaresUsuario: array<int, string>, esTejedor: bool, esSupervisor: bool, filtroGlobalActivo: bool}
     */
    public function contexto(object $user, ?string $filtro): array
    {
        return [
            'filtroAplicado' => $filtro ?: 'todos',
            'telaresUsuario' => $this->telaresDe($user),
            'esTejedor' => $this->esTejedor($user),
            'esSupervisor' => $this->esSupervisor($user),
            'filtroGlobalActivo' => $filtro !== null,
        ];
    }

    private function consultaBase()
    {
        $ultimo = $this->ultimoMontado();

        return TejInventarioTelares::query()
            ->select(
                'tej_inventario_telares.id',
                'tej_inventario_telares.fecha',
                'tej_inventario_telares.turno',
                'tej_inventario_telares.no_telar',
                'tej_inventario_telares.tipo',
                'tej_inventario_telares.no_julio',
                // Karl Mayer: una barra lleva hasta 4 julios.
                'tej_inventario_telares.no_julio2',
                'tej_inventario_telares.no_julio3',
                'tej_inventario_telares.no_julio4',
                'tej_inventario_telares.localidad',
                'tej_inventario_telares.metros',
                'tej_inventario_telares.no_orden',
                'tej_inventario_telares.tipo_atado',
                'tej_inventario_telares.cuenta',
                'tej_inventario_telares.calibre',
                'tej_inventario_telares.hilo',
                'tej_inventario_telares.ConfigId',
                'tej_inventario_telares.InventSizeId',
                'tej_inventario_telares.InventColorId',
                DB::raw('tej_inventario_telares.loteProveedor as LoteProveedor'),
                DB::raw('tej_inventario_telares.noProveedor as NoProveedor'),
                'tej_inventario_telares.horaParo',
                DB::raw("CASE
                    WHEN AtaMontadoTelas.Estatus = 'Autorizado' THEN 'Autorizado'
                    WHEN AtaMontadoTelas.Estatus = 'Calificado' THEN 'Calificado'
                    WHEN AtaMontadoTelas.Estatus = 'Terminado' THEN 'Terminado'
                    WHEN AtaMontadoTelas.Estatus = 'En Proceso' THEN 'En Proceso'
                    ELSE 'Activo'
                END as status_proceso")
            )
            ->leftJoinSub($ultimo, 'ata_ultimo', function ($join) {
                $join->on('tej_inventario_telares.no_julio', '=', 'ata_ultimo.NoJulio')
                    ->on('tej_inventario_telares.no_orden', '=', 'ata_ultimo.NoProduccion');
            })
            ->leftJoin('AtaMontadoTelas', 'AtaMontadoTelas.Id', '=', 'ata_ultimo.Id')
            ->whereNotNull('tej_inventario_telares.no_julio')
            ->where('tej_inventario_telares.no_julio', '!=', '');
    }

    /**
     * Un solo Id por julio y orden: el más alto. Así un atado duplicado no
     * multiplica la fila del inventario.
     */
    private function ultimoMontado()
    {
        $conexion = (new TejInventarioTelares)->getConnectionName() ?: (string) config('database.default');

        return $this->ultimoMontadoEn($conexion);
    }

    private function aplicarRol($query, object $user): void
    {
        if ($this->esTejedor($user)) {
            $telares = $this->telaresDe($user);
            if ($telares === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('tej_inventario_telares.no_telar', $telares)
                    ->where('AtaMontadoTelas.Estatus', 'Terminado');
            }

            return;
        }

        if ($this->esAreaAtadores($user) || $this->esPuestoAtador($user)) {
            if ($this->restringirAtador($user)) {
                $query->where(function ($q) {
                    $q->whereNull('AtaMontadoTelas.Estatus')
                        ->orWhere('AtaMontadoTelas.Estatus', 'En Proceso');
                });
            } else {
                $query->whereRaw('0 = 1');
            }

            return;
        }

        if ($this->esSupervisor($user)) {
            $query->where(function ($q) {
                $q->whereNull('AtaMontadoTelas.Estatus')
                    ->orWhereIn('AtaMontadoTelas.Estatus', ['En Proceso', 'Calificado']);
            });

            return;
        }

        $query->whereRaw('0 = 1');
    }

    /**
     * Autorizados cuyo registro más reciente (por Id) sigue en Autorizado,
     * unidos al inventario en una sola consulta.
     */
    private function autorizados(): Collection
    {
        $filas = AtaMontadoTelasModel::query()
            ->joinSub($this->ultimoMontadoEn('sqlsrv'), 'ultimo', function ($join) {
                $join->on('AtaMontadoTelas.Id', '=', 'ultimo.Id');
            })
            ->where('AtaMontadoTelas.Estatus', 'Autorizado')
            ->orderBy('AtaMontadoTelas.Fecha')
            ->orderBy('AtaMontadoTelas.Turno')
            ->get([
                'AtaMontadoTelas.Id',
                'AtaMontadoTelas.Fecha',
                'AtaMontadoTelas.Turno',
                'AtaMontadoTelas.NoTelarId',
                'AtaMontadoTelas.Tipo',
                'AtaMontadoTelas.NoJulio',
                'AtaMontadoTelas.Metros',
                'AtaMontadoTelas.NoProduccion',
                'AtaMontadoTelas.LoteProveedor',
                'AtaMontadoTelas.NoProveedor',
                'AtaMontadoTelas.HoraParo',
                'AtaMontadoTelas.ConfigId',
                'AtaMontadoTelas.InventSizeId',
                'AtaMontadoTelas.InventColorId',
            ]);

        if ($filas->isEmpty()) {
            return $filas;
        }

        // SQL Server acepta 2100 parametros por consulta y los autorizados ya pasan
        // de 1400 julios: se consulta por bloques y el par julio|orden se cruza abajo.
        $inventario = $filas->pluck('NoJulio')->map(fn ($v) => (string) $v)->unique()->chunk(2000)
            ->flatMap(fn ($julios) => TejInventarioTelares::query()->whereIn('no_julio', $julios->values()->all())->get())
            ->keyBy(fn ($inv) => (string) $inv->no_julio.'|'.(string) $inv->no_orden);

        return $filas->map(function ($ata) use ($inventario) {
            $inv = $inventario->get((string) $ata->NoJulio.'|'.(string) $ata->NoProduccion);

            if ($inv) {
                $inv->setAttribute('status_proceso', 'Autorizado');

                return $inv;
            }

            return (object) [
                'id' => $ata->Id,
                'fecha' => $ata->Fecha ? Carbon::parse($ata->Fecha) : null,
                'status_proceso' => 'Autorizado',
                'turno' => $ata->Turno,
                'no_telar' => $ata->NoTelarId,
                'tipo' => $ata->Tipo,
                'no_julio' => $ata->NoJulio,
                'localidad' => null,
                'metros' => $ata->Metros,
                'no_orden' => $ata->NoProduccion,
                'tipo_atado' => null,
                'cuenta' => null,
                'calibre' => null,
                'hilo' => null,
                'LoteProveedor' => $ata->LoteProveedor,
                'NoProveedor' => $ata->NoProveedor,
                'horaParo' => $ata->HoraParo,
                'ConfigId' => $ata->ConfigId,
                'InventSizeId' => $ata->InventSizeId,
                'InventColorId' => $ata->InventColorId,
            ];
        });
    }

    private function ultimoMontadoEn(string $conexion)
    {
        return DB::connection($conexion)
            ->table('AtaMontadoTelas')
            ->select('NoJulio', 'NoProduccion', DB::raw('MAX(Id) as Id'))
            ->groupBy('NoJulio', 'NoProduccion');
    }

    private function esTejedor(object $user): bool
    {
        return in_array(strtoupper(trim((string) ($user->area ?? ''))), ['TEJEDORES', 'TEJEDOR'], true);
    }

    private function esSupervisor(object $user): bool
    {
        return strtolower(trim((string) ($user->puesto ?? ''))) === 'supervisor';
    }

    private function esAreaAtadores(object $user): bool
    {
        return in_array(strtolower(trim((string) ($user->area ?? ''))), ['atador', 'atadores'], true);
    }

    private function esPuestoAtador(object $user): bool
    {
        return in_array(strtolower(trim((string) ($user->puesto ?? ''))), ['atador', 'atadores'], true);
    }

    private function restringirAtador(object $user): bool
    {
        return $this->esPuestoAtador($user)
            && userCan('acceso', 'Programa Atadores')
            && userCan('crear', 'Programa Atadores');
    }

    /**
     * @return array<int, string>
     */
    private function telaresDe(object $user): array
    {
        if (! $this->esTejedor($user)) {
            return [];
        }

        if ($this->telaresUsuario === null) {
            $this->telaresUsuario = TelTelaresOperador::query()
                ->where('numero_empleado', $user->numero_empleado)
                ->pluck('NoTelarId')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        return $this->telaresUsuario;
    }
}
