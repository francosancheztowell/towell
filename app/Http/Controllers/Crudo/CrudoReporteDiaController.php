<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crudo;

use App\Exports\CrudoReporteDiaExport;
use App\Http\Controllers\Controller;
use App\Services\Crudo\CrudoAccess;
use App\Services\Crudo\CrudoDashboardService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CrudoReporteDiaController extends Controller
{
    public function __construct(
        private readonly CrudoAccess $access,
        private readonly CrudoDashboardService $dashboard,
    ) {}

    public function excel(Request $request): BinaryFileResponse
    {
        // Mismo permiso que la auditoría del telar: `registrar` sobre Crudo.
        $this->access->authorizeRegister();

        $day = $this->productionDay((string) $request->query('fecha', ''));
        $data = $this->dashboard->build($day);

        return Excel::download(
            new CrudoReporteDiaExport($data, $day, $this->rutaLogo()),
            'reporte_telares_'.$day->format('Y-m-d').'.xlsx',
        );
    }

    /** Mismo archivo que usan los PDF y el export de Alineación. */
    private function rutaLogo(): ?string
    {
        $ruta = public_path('images/fondosTowell/logo.png');

        return is_file($ruta) ? $ruta : null;
    }

    /**
     * El día de producción corre de 06:30 a 06:30; sin parámetro se reporta el
     * día en curso bajo esa regla (a las 03:00 todavía es el día anterior).
     */
    private function productionDay(string $fecha): DateTimeImmutable
    {
        $timezone = new DateTimeZone((string) config('app.timezone'));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', trim($fecha), $timezone);

        if ($parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === trim($fecha)) {
            return $parsed;
        }

        return (new DateTimeImmutable('now', $timezone))
            ->modify('-'.(int) config('crudo.production_day_start_minutes', 390).' minutes')
            ->setTime(0, 0);
    }
}
