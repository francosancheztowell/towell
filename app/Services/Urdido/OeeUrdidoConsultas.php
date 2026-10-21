<?php

declare(strict_types=1);

namespace App\Services\Urdido;

use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\UrdEngomado\UrdEngCalMaquina;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Services\Costos\CuotasUrdidoService;
use Carbon\CarbonImmutable;

/**
 * Lecturas de BD del reporte OEE Urdido (julios, paros y calendario por máquina);
 * el cálculo vive en OeeUrdidoService::armar(), que se prueba sin BD.
 */
final class OeeUrdidoConsultas
{
    /** Julios del periodo (±1 día para las ventanas en el borde); solo los finalizados salen en el reporte. */
    public function julios(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return UrdProduccionUrdido::query()
            ->with('programa:Folio,MaquinaId,BomId,Fibra,InventSizeId')
            ->with('defecto:Id,Clave,Defecto')
            ->whereBetween('Fecha', [$desde->subDay()->toDateString(), $hasta->toDateString()])
            ->orderBy('Fecha')->orderBy('HoraInicial')
            ->get()
            ->map(function (UrdProduccionUrdido $j) use ($desde, $hasta) {
                $fecha = $j->Fecha instanceof \DateTimeInterface ? $j->Fecha->format('Y-m-d') : substr((string) $j->Fecha, 0, 10);
                $minutos = (float) (CuotasUrdidoService::duracion($j->HoraInicial, $j->HoraFinal) ?? 0);
                $kg = (float) $j->KgNeto;
                $oficiales = [];
                foreach ([1, 2, 3] as $n) {
                    if (trim((string) $j->{'NomEmpl'.$n}) !== '') {
                        $oficiales[] = trim((string) $j->{'NomEmpl'.$n});
                    }
                }

                return [
                    'maquina' => self::maquina($j->programa?->MaquinaId),
                    'fecha' => $fecha,
                    'dia' => self::dia($fecha),
                    'hora' => substr((string) $j->HoraInicial, 0, 5),
                    'hora_fin' => substr((string) $j->HoraFinal, 0, 5),
                    'inicio' => $j->HoraInicial ? strtotime($fecha.' '.substr((string) $j->HoraInicial, 0, 8)) : null,
                    'minutos' => $minutos,
                    'finalizado' => (bool) $j->Finalizar && $fecha >= $desde->toDateString() && $fecha < $hasta->toDateString(),
                    'orden' => $j->Folio,
                    'julio' => $j->NoJulio,
                    'lmat' => $j->programa?->BomId,
                    'config' => $j->programa?->Fibra,
                    'tamano' => $j->programa?->InventSizeId,
                    'kg' => $kg,
                    'kg_buenos' => $j->ClaveDefecto ? 0.0 : $kg,
                    'metros' => (float) $j->Metros1 + (float) $j->Metros2 + (float) $j->Metros3,
                    'defecto' => $j->defecto?->Clave,
                    'defecto_nombre' => $j->defecto?->Defecto,
                    'penalizacion' => $j->Penalizacion !== null ? (float) $j->Penalizacion : null,
                    'oficiales' => implode(' / ', $oficiales),
                ];
            })
            ->all();
    }

    /** Paros cerrados de Urdido que tocan el periodo, por máquina (los abiertos se ignoran): [inicio, fin, necesario]. */
    public function paros(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $ts = fn ($f, $h) => strtotime(($f instanceof \DateTimeInterface ? $f->format('Y-m-d') : substr((string) $f, 0, 10)).' '.substr((string) $h, 0, 8));
        // ManFallasParos.Falla guarda el código (CatParosFallas.Falla), no la descripción.
        $necesarios = CatParosFallas::query()
            ->where('Departamento', 'URDIDO')->where('Necesario', true)
            ->get(['TipoFallaId', 'Falla'])
            ->mapWithKeys(fn ($c) => [$c->TipoFallaId.'|'.$c->Falla => true]);

        return ManFallasParos::query()
            ->where('Depto', 'Urdido')
            ->whereNotNull('FechaFin')->whereNotNull('HoraFin')
            ->where('Fecha', '<', $hasta->toDateString())
            ->where('FechaFin', '>=', $desde->toDateString())
            ->get(['MaquinaId', 'Fecha', 'Hora', 'FechaFin', 'HoraFin', 'TipoFallaId', 'Falla'])
            ->groupBy(fn ($p) => self::maquina($p->MaquinaId))
            ->map(fn ($l) => $l->map(fn ($p) => [$ts($p->Fecha, $p->Hora), $ts($p->FechaFin, $p->HoraFin), $necesarios->has($p->TipoFallaId.'|'.$p->Falla)])->all())
            ->all();
    }

    /** Turnos programados que tocan el periodo, por máquina: [inicio, fin). */
    public function calendario(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return UrdEngCalMaquina::query()
            ->with('maquina:Id,MaquinaId')
            ->where('FechaInicio', '>=', $desde->subDay())
            ->where('FechaInicio', '<', $hasta)
            ->get()
            ->groupBy(fn (UrdEngCalMaquina $c) => self::maquina($c->maquina?->MaquinaId))
            ->map(fn ($l) => $l->map(fn (UrdEngCalMaquina $c) => [$c->FechaInicio->getTimestamp(), $c->fin()->getTimestamp()])->all())
            ->all();
    }

    /** '2026-10-13' → '13 oct martes'. */
    public static function dia(string $fecha): string
    {
        $c = CarbonImmutable::parse($fecha)->locale('es');

        return $c->day.' '.rtrim($c->translatedFormat('M'), '.').' '.$c->translatedFormat('l');
    }

    /** 'Mc Coy 1' / 'MC Coy 1' → MCCOY1; 'Karl Mayer' / 'KM1' → KM1. */
    public static function maquina(?string $id): string
    {
        $m = strtoupper(str_replace(' ', '', (string) $id));

        return $m === 'KARLMAYER' ? 'KM1' : $m;
    }
}
