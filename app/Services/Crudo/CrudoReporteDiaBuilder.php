<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Exports\CrudoReporteDiaExport;
use App\Models\Crudo\CrudoAuditoria;
use App\Support\Crudo\CrudoProductionDay;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Arma el reporte diario de telares. Vive aparte del controlador porque el
 * envío programado por correo necesita exactamente el mismo archivo.
 */
final readonly class CrudoReporteDiaBuilder
{
    public function __construct(private CrudoDashboardService $dashboard) {}

    /**
     * El día de producción corre de 06:30 a 06:30; sin fecha válida se toma el
     * día en curso bajo esa regla (a las 03:00 todavía es el día anterior, y el
     * envío de las 06:00 cierra el día que acaba de terminar).
     */
    public function productionDay(string $fecha = ''): DateTimeImmutable
    {
        $timezone = new DateTimeZone((string) config('app.timezone'));
        $fecha = trim($fecha);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha, $timezone);

        if ($parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $fecha) {
            return $parsed;
        }

        return new DateTimeImmutable(
            CrudoProductionDay::forInstant(new DateTimeImmutable('now', $timezone)),
            $timezone,
        );
    }

    public function export(DateTimeImmutable $day): CrudoReporteDiaExport
    {
        return new CrudoReporteDiaExport(
            $this->dashboard->build($day),
            $day,
            $this->rutaLogo(),
            $this->auditorias($day),
            $this->sinPesoMuestra(),
        );
    }

    /**
     * Telares cuyo programa EnProceso no tiene peso muestra capturado. Sin ese
     * dato Calidad no puede pesar la muestra, así que se listan aparte.
     *
     * ponytail: consulta directa a la conexión del catálogo; no vale la pena
     * ampliar el repositorio del tablero para una lista de tres columnas.
     *
     * @return list<array{telar: string, orden: string, producto: string}>
     */
    private function sinPesoMuestra(): array
    {
        $filas = DB::connection((string) config('crudo.connections.catalog', 'sqlsrv'))
            ->table((string) config('crudo.tables.programs', 'dbo.ReqProgramaTejido'))
            ->where('EnProceso', 1)
            ->where(static function ($query): void {
                // PesoMuestra es texto en SQL Server: el vacío también es faltante.
                $query->whereNull('PesoMuestra')->orWhere('PesoMuestra', '');
            })
            ->orderBy('NoTelarId')
            ->get(['NoTelarId', 'NoProduccion', 'NombreProducto']);

        return $filas->map(static fn (object $fila): array => [
            'telar' => trim((string) $fila->NoTelarId),
            'orden' => trim((string) $fila->NoProduccion),
            'producto' => trim((string) $fila->NombreProducto),
        ])->all();
    }

    /**
     * Auditorías de Calidad capturadas dentro de la misma ventana 06:30 → 06:30.
     *
     * @return Collection<int, CrudoAuditoria>
     */
    private function auditorias(DateTimeImmutable $day): Collection
    {
        $inicio = $day->setTime(0, 0)
            ->modify('+'.(int) config('crudo.production_day_start_minutes', 390).' minutes');

        return CrudoAuditoria::query()
            ->with([
                'defecto1:Id,Falla',
                'defecto2:Id,Falla',
                'defecto3:Id,Falla',
                'defecto4:Id,Falla',
                'defecto5:Id,Falla',
            ])
            ->where('Fecha', '>=', $inicio)
            ->where('Fecha', '<', $inicio->modify('+1 day'))
            ->orderBy('Fecha')
            ->get();
    }

    public function fileName(DateTimeImmutable $day): string
    {
        return 'reporte_telares_'.$day->format('Y-m-d').'.xlsx';
    }

    /** Mismo archivo que usan los PDF y el export de Alineación. */
    public function rutaLogo(): ?string
    {
        $ruta = public_path('images/fondosTowell/logo.png');

        return is_file($ruta) ? $ruta : null;
    }
}
