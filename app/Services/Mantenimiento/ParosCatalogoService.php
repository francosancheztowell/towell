<?php

declare(strict_types=1);

namespace App\Services\Mantenimiento;

use App\Models\Atadores\AtaMaquinasModel;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Sistema\SysDepartamento;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Support\Collection;

/**
 * Reglas de catálogo del alta de paros: qué departamentos pueden reportar, qué máquinas
 * ofrece cada uno y qué fallas le aplican. Lo usan el combo en cascada (ParosCatalogoController)
 * y la validación de `store`, para que lo que se acepta sea exactamente lo que se ofrece.
 */
final class ParosCatalogoService
{
    /**
     * Departamentos del catálogo que no operan máquinas: no se ofrecen ni se aceptan al
     * reportar. Trama sí tiene telares asignados, pero no reporta paros desde esta pantalla.
     */
    public const DEPARTAMENTOS_EXCLUIDOS = ['Mantenimiento', 'Sistemas', 'Contabilidad', 'Directivos', 'Planeacion', 'Trama'];

    /** Departamentos de tejido: comparten el catálogo de fallas "Tejido". */
    private const DEPARTAMENTOS_TEJIDO = ['JACQUARD', 'ITEMA', 'KARL MAYER', 'KARLMAYER', 'SMITH', 'TEJEDORES', 'TRMA', 'TRAMA', 'DESARROLLADORES', 'SUPERVISORES'];

    /** Estos reciben todos sus telares sin filtrar por salón. */
    private const DEPARTAMENTOS_CON_TELARES_PROPIOS = ['TEJEDORES', 'TRAMA', 'DESARROLLADORES', 'SUPERVISORES'];

    /**
     * Departamentos que pueden reportar un paro, en orden alfabético.
     *
     * @return list<string>
     */
    public function departamentos(): array
    {
        $excluidos = array_map('mb_strtoupper', self::DEPARTAMENTOS_EXCLUIDOS);

        return SysDepartamento::query()
            ->orderBy('Depto')
            ->pluck('Depto')
            ->map(fn ($d): string => trim((string) $d))
            ->filter(fn (string $d): bool => $d !== '' && ! in_array(mb_strtoupper($d), $excluidos, true))
            ->values()
            ->all();
    }

    /**
     * Máquinas que ofrece un departamento.
     *
     * - Urdido / Engomado: catálogo URDCatalogoMaquina.
     * - Atadores: catálogo AtaMaquinasModel.
     * - Calidad: todos los telares, más las máquinas de Urdido y Engomado.
     * - El resto: los telares asignados al empleado en TelTelaresOperador.
     *
     * @return Collection<int, mixed>|null Cada fila es array con: MaquinaId, Nombre, Departamento y, en Calidad, DepartamentoOrigen.
     */
    public function maquinas(string $departamento, ?string $numeroEmpleado): ?Collection
    {
        $depUpper = strtoupper(trim($departamento));

        if (in_array($depUpper, ['URDIDO', 'ENGOMADO'], true)) {
            return URDCatalogoMaquina::where('Departamento', $departamento)
                ->orderBy('MaquinaId')
                ->get(['MaquinaId', 'Nombre', 'Departamento'])
                ->map(fn (URDCatalogoMaquina $m): array => [
                    'MaquinaId' => (string) $m->getAttribute('MaquinaId'),
                    'Nombre' => (string) $m->Nombre,
                    'Departamento' => (string) $m->Departamento,
                ])
                ->values();
        }

        if ($depUpper === 'ATADORES') {
            return AtaMaquinasModel::orderBy('MaquinaId')
                ->get()
                ->map(fn ($m): array => $this->fila((string) $m->getAttribute('MaquinaId'), $departamento))
                ->values();
        }

        if ($depUpper === 'CALIDAD') {
            return $this->maquinasParaCalidad($departamento);
        }

        if ($numeroEmpleado === null || trim($numeroEmpleado) === '') {
            return null;
        }

        $query = TelTelaresOperador::query()->where('numero_empleado', $numeroEmpleado);

        // El resto (Sistemas, Contabilidad, Directivos…) no tiene telares asignados:
        // recibe una lista vacía, que es la respuesta correcta.
        if (! in_array($depUpper, self::DEPARTAMENTOS_CON_TELARES_PROPIOS, true)) {
            $query->whereIn('SalonTejidoId', [$departamento]);
        }

        return $query
            ->select('NoTelarId as MaquinaId')
            ->whereNotNull('NoTelarId')
            ->distinct()
            ->orderBy('NoTelarId')
            ->get()
            ->map(fn ($m): array => $this->fila((string) $m->getAttribute('MaquinaId'), $departamento))
            ->values();
    }

    /** ¿Esa máquina está entre las que ofrece el departamento a este empleado? */
    public function esMaquinaDe(string $departamento, string $maquina, ?string $numeroEmpleado): bool
    {
        $buscada = mb_strtoupper(trim($maquina));

        return $buscada !== ''
            && ($this->maquinas($departamento, $numeroEmpleado) ?? collect())
                ->contains(fn (array $m): bool => mb_strtoupper(trim($m['MaquinaId'])) === $buscada);
    }

    /**
     * Departamentos de CatParosFallas que aplican al departamento elegido.
     *
     * - Los de tejido reutilizan el catálogo "Tejido".
     * - Calidad consulta "Calidad" y también "Tejido" por compatibilidad con catálogos anteriores.
     *
     * @return list<string>
     */
    public function catalogoDepartamentos(string $departamento): array
    {
        $depUpper = strtoupper(trim($departamento));

        if (in_array($depUpper, self::DEPARTAMENTOS_TEJIDO, true)) {
            return ['Tejido'];
        }

        return $depUpper === 'CALIDAD' ? ['Calidad', 'Tejido'] : [$departamento];
    }

    /**
     * ¿Esta falla se ofrece al departamento? "Calidad" se puede reportar desde cualquiera.
     */
    public function fallaAplicaA(string $departamento, CatParosFallas $falla): bool
    {
        $catalogo = trim((string) $falla->Departamento);

        foreach ([...$this->catalogoDepartamentos($departamento), 'Calidad'] as $permitido) {
            if (strcasecmp(trim($permitido), $catalogo) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @return array{MaquinaId: string, Nombre: string, Departamento: string} */
    private function fila(string $maquinaId, string $departamento): array
    {
        return ['MaquinaId' => $maquinaId, 'Nombre' => $maquinaId, 'Departamento' => $departamento];
    }

    /**
     * @return Collection<int, mixed>
     */
    private function maquinasParaCalidad(string $departamento): Collection
    {
        $telares = TelTelaresOperador::query()
            ->select('NoTelarId as MaquinaId')
            ->whereNotNull('NoTelarId')
            ->distinct()
            ->get()
            ->map(function ($item) use ($departamento): array {
                $maquinaId = trim((string) $item->getAttribute('MaquinaId'));

                return $this->fila($maquinaId, $departamento) + ['DepartamentoOrigen' => 'Tejido'];
            });

        $urdidoEngomado = URDCatalogoMaquina::query()
            ->whereIn('Departamento', ['Urdido', 'Engomado'])
            ->get(['MaquinaId', 'Nombre', 'Departamento'])
            ->map(function (URDCatalogoMaquina $item) use ($departamento): array {
                $maquinaId = trim((string) $item->getAttribute('MaquinaId'));
                $origen = strcasecmp(trim((string) $item->Departamento), 'Engomado') === 0 ? 'Engomado' : 'Urdido';

                return [
                    'MaquinaId' => $maquinaId,
                    'Nombre' => trim((string) $item->Nombre) ?: $maquinaId,
                    'Departamento' => $departamento,
                    'DepartamentoOrigen' => $origen,
                ];
            });

        $ordenGrupos = ['Tejido' => 0, 'Urdido' => 1, 'Engomado' => 2];

        return $telares
            ->concat($urdidoEngomado)
            ->filter(fn (array $maquina): bool => $maquina['MaquinaId'] !== '')
            ->unique(fn (array $maquina): string => mb_strtoupper($maquina['MaquinaId']))
            ->sort(function (array $left, array $right) use ($ordenGrupos): int {
                $grupo = ($ordenGrupos[$left['DepartamentoOrigen']] ?? 99) <=> ($ordenGrupos[$right['DepartamentoOrigen']] ?? 99);

                return $grupo !== 0 ? $grupo : strnatcasecmp($left['MaquinaId'], $right['MaquinaId']);
            })
            ->values();
    }
}
