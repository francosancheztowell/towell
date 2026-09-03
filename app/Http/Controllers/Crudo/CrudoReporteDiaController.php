<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crudo;

use App\Http\Controllers\Controller;
use App\Services\Crudo\CrudoAccess;
use App\Services\Crudo\CrudoReporteDiaBuilder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CrudoReporteDiaController extends Controller
{
    public function __construct(
        private readonly CrudoAccess $access,
        private readonly CrudoReporteDiaBuilder $reporte,
    ) {}

    public function excel(Request $request): BinaryFileResponse
    {
        // Mismo permiso que la auditoría del telar: `registrar` sobre Crudo.
        $this->access->authorizeRegister();

        // Antes, una fecha mal formada caía en silencio al día de hoy sin
        // avisar; ahora se rechaza para que quien pide el reporte sepa que
        // el parámetro no se entendió, en vez de recibir el día equivocado.
        $validated = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $day = $this->reporte->productionDay((string) ($validated['fecha'] ?? ''));

        return Excel::download(
            $this->reporte->export($day),
            $this->reporte->fileName($day),
        );
    }
}
