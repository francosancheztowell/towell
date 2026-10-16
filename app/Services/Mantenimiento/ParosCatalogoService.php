<?php

declare(strict_types=1);

namespace App\Services\Mantenimiento;

use App\Models\Atadores\AtaMaquinasModel;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Sistema\SYSMaquinaAsignacion;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Reglas de catálogo de paros: qué departamentos puede reportar cada usuario, qué máquinas
 * ofrece cada uno y qué fallas le aplican. Lo usan el combo en cascada (ParosCatalogoController),
 * la validación de `store` y el listado, para que lo que se acepta sea exactamente lo que se ofrece.
 *
 * Los departamentos salen de URDCatalogoMaquinas a través de SYSMaquinasAsignacion: cada quien
 * ve los departamentos de sus máquinas asignadas. Atadores y Calidad no están en ese catálogo
 * y se ofrecen por área, como antes (.planning/paros_acceso_maquinas_plan.md).
 */
final class ParosCatalogoService
{
    /** Áreas que reportan sin asignación: su departamento de paros es el área misma. */
    private const DEPARTAMENTOS_POR_AREA = ['ATADORES' => 'Atadores', 'CALIDAD' => 'Calidad'];

    /** Salones de telares: comparten el catálogo de fallas "Tejido". */
    private const DEPARTAMENTOS_TEJIDO = ['JACQUARD', 'ITEMA', 'SMITH', 'KARL MAYER'];

    /** @var array<int, list<string>> MaquinaId asignadas por idusuario, una consulta por petición. */
    private array $asignadas = [];

    /**
     * Departamentos en los que el usuario puede reportar, en orden alfabético.
     *
     * @return list<string>
     */
    public function departamentos(?Authenticatable $usuario): array
    {
        $porArea = self::DEPARTAMENTOS_POR_AREA[$this->areaDe($usuario)] ?? null;

        return URDCatalogoMaquina::query()
            ->whereIn('MaquinaId', $this->maquinasAsignadas($usuario))
            ->distinct()
            ->pluck('Departamento')
            ->map(fn ($d): string => trim((string) $d))
            ->filter()
            ->when($porArea !== null, fn (Collection $c) => $c->push($porArea))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * MaquinaId asignadas al usuario en SYSMaquinasAsignacion.
     *
     * @return list<string>
     */
    public function maquinasAsignadas(?Authenticatable $usuario): array
    {
        // getKey y no getAuthIdentifier: en algunos modelos de usuario el identificador de sesión es numero_empleado.
        $id = $usuario instanceof Model ? (int) $usuario->getKey() : 0;
        if ($id === 0) {
            return [];
        }

        return $this->asignadas[$id] ??= SYSMaquinaAsignacion::query()
            ->where('idusuario', $id)
            ->pluck('MaquinaId')
            ->map(fn ($m): string => trim((string) $m))
            ->all();
    }

    /**
     * Máquinas que el usuario puede elegir en un departamento.
     *
     * - Atadores: catálogo AtaMaquinasModel.
     * - Calidad: todos los telares más Urdido y Engomado.
     * - El resto: sus máquinas asignadas de ese departamento.
     *
     * Nunca más de lo que permite `departamentos()`: un departamento ajeno da lista vacía.
     *
     * @return Collection<int, mixed> Cada fila: MaquinaId, Nombre, Departamento y, en Calidad, DepartamentoOrigen.
     */
    public function maquinas(string $departamento, ?Authenticatable $usuario): Collection
    {
        if (! $this->puedeReportarEn($departamento, $usuario)) {
            return collect();
        }

        $depUpper = mb_strtoupper(trim($departamento));

        if ($depUpper === 'ATADORES') {
            return AtaMaquinasModel::orderBy('MaquinaId')
                ->get()
                ->map(fn ($m): array => $this->fila((string) $m->getAttribute('MaquinaId'), $departamento))
                ->values();
        }

        if ($depUpper === 'CALIDAD') {
            return $this->maquinasParaCalidad($departamento);
        }

        return URDCatalogoMaquina::query()
            ->where('Departamento', $departamento)
            ->whereIn('MaquinaId', $this->maquinasAsignadas($usuario))
            ->orderBy('MaquinaId')
            ->get(['MaquinaId', 'Nombre', 'Departamento'])
            ->map(fn (URDCatalogoMaquina $m): array => [
                'MaquinaId' => trim((string) $m->getAttribute('MaquinaId')),
                'Nombre' => trim((string) $m->Nombre) ?: trim((string) $m->getAttribute('MaquinaId')),
                'Departamento' => (string) $m->Departamento,
            ])
            ->values();
    }

    public function puedeReportarEn(string $departamento, ?Authenticatable $usuario): bool
    {
        return in_array(trim($departamento), $this->departamentos($usuario), true);
    }

    /** ¿Esa máquina está entre las que el departamento ofrece a este usuario? */
    public function esMaquinaDe(string $departamento, string $maquina, ?Authenticatable $usuario): bool
    {
        $buscada = mb_strtoupper(trim($maquina));

        return $buscada !== ''
            && $this->maquinas($departamento, $usuario)
                ->contains(fn (array $m): bool => mb_strtoupper(trim($m['MaquinaId'])) === $buscada);
    }

    /**
     * ¿Puede ver o cerrar un paro de esa máquina? Quien tiene máquinas asignadas, solo las suyas.
     * Sin asignación no se restringe por máquina (Mantenimiento y supervisores atienden todo);
     * Atadores y Calidad van por área.
     */
    public function puedeVerParo(string $maquinaId, ?Authenticatable $usuario): bool
    {
        $asignadas = $this->maquinasAsignadas($usuario);

        return $asignadas === []
            || $this->esPorArea($usuario)
            || in_array(mb_strtoupper(trim($maquinaId)), array_map('mb_strtoupper', $asignadas), true);
    }

    /** Área Atadores o Calidad: su acceso a paros es por área, no por máquina. */
    public function esPorArea(?Authenticatable $usuario): bool
    {
        return isset(self::DEPARTAMENTOS_POR_AREA[$this->areaDe($usuario)]);
    }

    /**
     * Departamentos de CatParosFallas que aplican al departamento elegido.
     *
     * - Los salones de telares reutilizan el catálogo "Tejido".
     * - Calidad consulta "Calidad" y también "Tejido" por compatibilidad con catálogos anteriores.
     * - El resto, solo el suyo: sin fallas capturadas no hay qué reportar (decisión del owner).
     *
     * @return list<string>
     */
    public function catalogoDepartamentos(string $departamento): array
    {
        $depUpper = mb_strtoupper(trim($departamento));

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

    /**
     * Departamentos con los que se puede capturar una falla o filtrar el reporte: los de
     * URDCatalogoMaquinas más Atadores, Calidad y Tejido (catálogo compartido de los salones).
     *
     * @return list<string>
     */
    public function departamentosDelCatalogo(): array
    {
        return URDCatalogoMaquina::query()
            ->distinct()
            ->pluck('Departamento')
            ->map(fn ($d): string => trim((string) $d))
            ->filter()
            ->merge(['Atadores', 'Calidad', 'Tejido'])
            ->unique(fn (string $d): string => mb_strtoupper($d))
            ->sort()
            ->values()
            ->all();
    }

    private function areaDe(?Authenticatable $usuario): string
    {
        return $usuario instanceof Model ? mb_strtoupper(trim((string) $usuario->getAttribute('area'))) : '';
    }

    /** @return array{MaquinaId: string, Nombre: string, Departamento: string} */
    private function fila(string $maquinaId, string $departamento): array
    {
        return ['MaquinaId' => $maquinaId, 'Nombre' => $maquinaId, 'Departamento' => $departamento];
    }

    /**
     * Telares (salones de URDCatalogoMaquina::DEPARTAMENTOS_TELARES), Urdido y Engomado.
     *
     * @return Collection<int, mixed>
     */
    private function maquinasParaCalidad(string $departamento): Collection
    {
        $ordenGrupos = ['Tejido' => 0, 'Urdido' => 1, 'Engomado' => 2];

        return URDCatalogoMaquina::query()
            ->whereIn('Departamento', [...URDCatalogoMaquina::DEPARTAMENTOS_TELARES, 'Urdido', 'Engomado'])
            ->get(['MaquinaId', 'Nombre', 'Departamento'])
            ->map(function (URDCatalogoMaquina $item) use ($departamento): array {
                $maquinaId = trim((string) $item->getAttribute('MaquinaId'));
                $origen = trim((string) $item->Departamento);

                return [
                    'MaquinaId' => $maquinaId,
                    'Nombre' => trim((string) $item->Nombre) ?: $maquinaId,
                    'Departamento' => $departamento,
                    'DepartamentoOrigen' => in_array($origen, ['Urdido', 'Engomado'], true) ? $origen : 'Tejido',
                ];
            })
            ->filter(fn (array $maquina): bool => $maquina['MaquinaId'] !== '')
            ->sort(function (array $left, array $right) use ($ordenGrupos): int {
                $grupo = $ordenGrupos[$left['DepartamentoOrigen']] <=> $ordenGrupos[$right['DepartamentoOrigen']];

                return $grupo !== 0 ? $grupo : strnatcasecmp($left['MaquinaId'], $right['MaquinaId']);
            })
            ->values();
    }
}
