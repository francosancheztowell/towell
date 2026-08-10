<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\CrudoReporteDiaMail;
use App\Services\Crudo\CrudoReporteDiaBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

final class EnviarReporteCrudoCommand extends Command
{
    protected $signature = 'crudo:enviar-reporte
                            {--fecha= : Día de producción a reportar (Y-m-d). Por omisión, el vigente 06:30–06:30}
                            {--para=* : Destinatarios que reemplazan a los de crudo.report_recipients}';

    protected $description = 'Genera el reporte diario de telares y lo envía por correo con el Excel adjunto.';

    public function handle(CrudoReporteDiaBuilder $reporte): int
    {
        $destinatarios = $this->destinatarios();

        if ($destinatarios === []) {
            $this->error('No hay destinatarios: define CRUDO_REPORTE_CORREOS en el .env o usa --para.');

            return self::FAILURE;
        }

        $day = $reporte->productionDay((string) $this->option('fecha'));

        try {
            $export = $reporte->export($day);

            Mail::to($destinatarios)->send(new CrudoReporteDiaMail(
                $day,
                $export->summary(),
                (string) Excel::raw($export, ExcelFormat::XLSX),
                $reporte->fileName($day),
                $reporte->rutaLogo(),
            ));
        } catch (Throwable $exception) {
            // El scheduler no tiene quien lea la salida: el log es la traza real.
            Log::error('No fue posible enviar el reporte de Crudo', [
                'fecha' => $day->format('Y-m-d'),
                'destinatarios' => $destinatarios,
                'error' => $exception->getMessage(),
            ]);
            $this->error('Falló el envío: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Reporte del %s enviado a %s.',
            $day->format('d/m/Y'),
            implode(', ', $destinatarios),
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function destinatarios(): array
    {
        /** @var list<string> $opcion */
        $opcion = (array) $this->option('para');
        $correos = $opcion !== []
            ? $opcion
            : (array) config('crudo.report_recipients', []);

        return array_values(array_filter(
            array_map(static fn (mixed $correo): string => trim((string) $correo), $correos),
            static fn (string $correo): bool => filter_var($correo, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }
}
