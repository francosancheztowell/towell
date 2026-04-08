<?php

namespace Tests\Unit;

use App\Services\OeeAtadores\OeeAtadoresPythonExportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class OeeAtadoresPythonExportServiceTest extends TestCase
{
    public function test_run_uses_fake_process_without_throwing_when_exit_zero(): void
    {
        Process::fake([
            '*' => Process::result(output: '', errorOutput: '', exitCode: 0),
        ]);

        $service = new OeeAtadoresPythonExportService;

        $service->run(
            storage_path('framework/testing/oee-dummy.xlsx'),
            CarbonImmutable::parse('2026-01-05'),
            CarbonImmutable::parse('2026-01-05'),
            'tokentest',
            storage_path('app/oee_export_tokentest.json')
        );

        Process::assertRan(function ($process) {
            $cmd = $process->command;
            $line = is_array($cmd) ? implode(' ', $cmd) : (string) $cmd;

            return str_contains($line, 'oee_export.py')
                && str_contains($line, 'tokentest');
        });
    }
}
