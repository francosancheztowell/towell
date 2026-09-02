<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Helpers\FolioHelper;
use App\Helpers\StringTruncator;
use App\Helpers\TurnoHelper;
use App\Models\Crudo\CrudoAuditoria;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\ManFallasParos;
use App\Support\Crudo\CrudoAuditAnswer;
use App\Support\Crudo\CrudoDefectRanking;
use App\Support\Crudo\CrudoProductionDay;
use App\Support\Crudo\CrudoSalon;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CrudoAuditService
{
    /**
     * @return Collection<int, CrudoAuditoria>
     */
    public function todayForMachine(string $machineId): Collection
    {
        [$from, $to] = $this->todayWindow();

        return CrudoAuditoria::query()
            ->select([
                'Id',
                'Fecha',
                'NoTelarId',
                'Salon',
                'OrdenTrabajo',
                'Turno',
                'CveEmpl',
                'NomEmpl',
                'AlineacionOrden',
                'DibujoJacquard',
                'IdentificacionJulio',
                'Marbetes',
                'Observaciones',
                'Defecto1Id',
                'Defecto1Pzas',
                'Defecto2Id',
                'Defecto2Pzas',
                'Defecto3Id',
                'Defecto3Pzas',
                'Defecto4Id',
                'Defecto4Pzas',
                'Defecto5Id',
                'Defecto5Pzas',
                'ParoId',
            ])
            ->with([
                'defecto1:Id,Falla,Descripcion',
                'defecto2:Id,Falla,Descripcion',
                'defecto3:Id,Falla,Descripcion',
                'defecto4:Id,Falla,Descripcion',
                'defecto5:Id,Falla,Descripcion',
                'paro:Id,Folio,Falla,Descripcion,Estatus',
            ])
            ->where('NoTelarId', trim($machineId))
            ->where('Fecha', '>=', $from)
            ->where('Fecha', '<', $to)
            ->orderByDesc('Fecha')
            ->orderByDesc('Id')
            // ponytail: tope duro; el carril tiene scroll y nadie revisa más de
            // unas cuantas auditorías de un mismo telar en un día.
            ->limit(max(1, (int) config('crudo.audit_history_limit', 25)))
            ->get();
    }

    /**
     * Guarda solamente la auditoría. No crea registros en ManFallasParos.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeAudit(array $data, Authenticatable $user): CrudoAuditoria
    {
        return DB::connection('sqlsrv')->transaction(
            fn (): CrudoAuditoria => CrudoAuditoria::query()->create(
                $this->auditAttributes($data, $user),
            ),
        );
    }

    /**
     * Guarda la auditoría y crea su paro usando el defecto con más piezas.
     * Ambas escrituras son atómicas: o se guardan las dos o no se guarda ninguna.
     *
     * @param  array<string, mixed>  $data
     * @return array{audit: CrudoAuditoria, stop: ManFallasParos, principal_defect: CatParosFallas, pieces: int}
     */
    public function storeAuditWithStop(array $data, Authenticatable $user): array
    {
        return DB::connection('sqlsrv')->transaction(function () use ($data, $user): array {
            $defects = $this->normalizeDefects($data['defectos'] ?? []);
            $principal = $this->principalDefect($defects);
            $catalog = $this->qualityCatalogFor($defects);
            $principalCatalog = $catalog->get($principal['defecto_id']);

            if (! $principalCatalog instanceof CatParosFallas) {
                throw ValidationException::withMessages([
                    'defectos' => 'El defecto principal ya no está disponible en el catálogo de Calidad.',
                ]);
            }

            if (ManFallasParos::hayActivoEnMaquina(
                (string) $data['no_telar_id'],
                (string) $principalCatalog->TipoFallaId,
            )) {
                throw ValidationException::withMessages([
                    'defectos' => 'Ya existe un paro activo para este telar y tipo de falla. Finalízalo antes de generar otro.',
                ]);
            }

            $audit = CrudoAuditoria::query()->create($this->auditAttributes($data, $user));
            $folio = FolioHelper::obtenerSiguienteFolio('ParosFallas', 5);

            if (trim($folio) === '') {
                throw new RuntimeException('No fue posible generar el folio del paro.');
            }

            $now = now(config('app.timezone'));
            $stop = ManFallasParos::query()->create([
                'Folio' => $folio,
                'Estatus' => 'Activo',
                'Fecha' => $now->toDateString(),
                'Hora' => $now->format('H:i:s'),
                'Depto' => 'Calidad',
                'MaquinaId' => $audit->NoTelarId,
                'TipoFallaId' => $principalCatalog->TipoFallaId,
                'Falla' => $principalCatalog->Falla,
                'Descripcion' => $principalCatalog->Descripcion,
                'OrdenTrabajo' => $audit->OrdenTrabajo,
                'Obs' => $this->buildStopMessage($audit, $principalCatalog, $principal['piezas']),
                'CveEmpl' => $audit->CveEmpl,
                'NomEmpl' => $audit->NomEmpl,
                'Turno' => $audit->Turno,
                'Enviado' => true,
                'HoraFin' => null,
                'CveAtendio' => null,
                'NomAtendio' => null,
                'TurnoAtendio' => null,
            ]);

            $audit->ParoId = $stop->Id;
            $audit->save();

            return [
                'audit' => $audit,
                'stop' => $stop,
                'principal_defect' => $principalCatalog,
                'pieces' => $principal['piezas'],
            ];
        });
    }

    public function buildStopMessage(
        CrudoAuditoria $audit,
        CatParosFallas $principalDefect,
        int $pieces,
    ): string {
        $parts = [
            'Auditoría #'.$audit->getKey(),
            'Alineación: '.$this->answerLabel($audit->AlineacionOrden),
        ];

        if ($this->isJacquard((string) $audit->Salon)) {
            $parts[] = 'Dibujo JAC: '.$this->answerLabel($audit->DibujoJacquard);
        }

        $parts[] = 'Ident. julio: '.$this->answerLabel($audit->IdentificacionJulio);
        $parts[] = sprintf(
            'Principal: %s (%d pzas)',
            trim((string) $principalDefect->Falla),
            $pieces,
        );

        $observations = Str::squish((string) ($audit->Observaciones ?? ''));
        if ($observations !== '') {
            $parts[] = 'Obs: '.$observations;
        }

        // ManFallasParos.Obs es NVARCHAR(255).
        return (string) StringTruncator::truncateToLength(implode(' | ', $parts), 255);
    }

    /**
     * @param  list<array{defecto_id: int, piezas: int}>  $defects
     * @return array{defecto_id: int, piezas: int}
     */
    public function principalDefect(array $defects): array
    {
        if ($defects === []) {
            throw ValidationException::withMessages([
                'defectos' => 'Captura al menos un defecto con piezas para generar el paro.',
            ]);
        }

        return $defects[CrudoDefectRanking::principalIndex($defects)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function auditAttributes(array $data, Authenticatable $user): array
    {
        $defects = $this->normalizeDefects($data['defectos'] ?? []);
        $checklist = is_array($data['checklist'] ?? null) ? $data['checklist'] : [];
        $isJacquard = $this->isJacquard((string) $data['salon']);

        $attributes = [
            'Fecha' => now(config('app.timezone')),
            'NoTelarId' => trim((string) $data['no_telar_id']),
            'Salon' => trim((string) $data['salon']),
            'OrdenTrabajo' => $this->nullableText($data['orden_trabajo'] ?? null),
            'Turno' => $this->userShift($user),
            'CveEmpl' => $this->nullableText(data_get($user, 'numero_empleado')),
            'NomEmpl' => $this->nullableText(data_get($user, 'nombre')),
            'AlineacionOrden' => $checklist['alineacion_orden'] ?? null,
            'DibujoJacquard' => $isJacquard ? ($checklist['dibujo_jacquard'] ?? null) : null,
            'IdentificacionJulio' => $checklist['identificacion_julio'] ?? null,
            'Marbetes' => isset($data['marbetes']) ? (int) $data['marbetes'] : null,
            'Observaciones' => $this->nullableText($data['observaciones'] ?? null),
            'ParoId' => null,
        ];

        for ($slot = 1; $slot <= 5; $slot++) {
            $defect = $defects[$slot - 1] ?? null;
            $attributes["Defecto{$slot}Id"] = $defect['defecto_id'] ?? null;
            $attributes["Defecto{$slot}Pzas"] = $defect['piezas'] ?? 0;
        }

        return $attributes;
    }

    /**
     * @param  array<int, mixed>  $defects
     * @return list<array{defecto_id: int, piezas: int}>
     */
    private function normalizeDefects(array $defects): array
    {
        return array_values(array_map(
            static fn (array $defect): array => [
                'defecto_id' => (int) $defect['defecto_id'],
                'piezas' => (int) $defect['piezas'],
            ],
            $defects,
        ));
    }

    /**
     * @param  list<array{defecto_id: int, piezas: int}>  $defects
     * @return \Illuminate\Support\Collection<int, CatParosFallas>
     */
    private function qualityCatalogFor(array $defects): \Illuminate\Support\Collection
    {
        $ids = array_column($defects, 'defecto_id');

        return CatParosFallas::query()
            ->where('Departamento', 'Calidad')
            ->whereIn('Id', $ids)
            ->get()
            ->keyBy(fn (CatParosFallas $item): int => (int) $item->Id);
    }

    private function answerLabel(?bool $answer): string
    {
        return CrudoAuditAnswer::fromBool($answer)->label();
    }

    private function isJacquard(string $salon): bool
    {
        return CrudoSalon::isJacquard($salon);
    }

    private function userShift(Authenticatable $user): int
    {
        return TurnoHelper::resolverTurnoOperativo(data_get($user, 'turno', 1));
    }

    /**
     * Ventana 06:30-06:30 del día de producción en curso, la misma que usa
     * CrudoReporteDiaBuilder::auditorias, para que el modal y el reporte
     * coincidan en qué auditorías son "de hoy".
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function todayWindow(): array
    {
        $timezone = config('app.timezone');
        $productionDay = CrudoProductionDay::forInstant(now($timezone));
        $startMinutes = (int) config('crudo.production_day_start_minutes', 390);
        $from = Carbon::parse($productionDay, $timezone)->addMinutes($startMinutes);

        return [$from, $from->copy()->addDay()];
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
