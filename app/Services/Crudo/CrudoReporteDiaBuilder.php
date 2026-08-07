<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Exports\CrudoReporteDiaExport;
use DateTimeImmutable;
use DateTimeZone;

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

        return (new DateTimeImmutable('now', $timezone))
            ->modify('-'.(int) config('crudo.production_day_start_minutes', 390).' minutes')
            ->setTime(0, 0);
    }

    public function export(DateTimeImmutable $day): CrudoReporteDiaExport
    {
        return new CrudoReporteDiaExport(
            $this->dashboard->build($day),
            $day,
            $this->rutaLogo(),
        );
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
