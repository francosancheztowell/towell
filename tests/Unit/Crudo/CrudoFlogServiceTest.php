<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Services\Crudo\CrudoFlogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class CrudoFlogServiceTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $path = tempnam(sys_get_temp_dir(), 'crudo-flog-test-');
        $this->assertNotFalse($path);
        $this->database = $path;

        config()->set('database.connections.crudo_flog_test', [
            'driver' => 'sqlite',
            'database' => $this->database,
            'prefix' => '',
        ]);
        config()->set('crudo.connections.source', 'crudo_flog_test');
        config()->set('crudo.tables.flogs', 'TwFlogsTable');
        config()->set('crudo.tables.flog_lines', 'TwFlogsItemLine');
        config()->set('crudo.flog_cache_seconds', 0);
        Cache::flush();

        DB::purge('crudo_flog_test');

        Schema::connection('crudo_flog_test')->create('TwFlogsTable', function (Blueprint $table): void {
            $table->string('IDFLOG');
            $table->string('CUSTACCOUNT')->nullable();
            $table->string('CUSTNAME')->nullable();
            $table->integer('ESTADOFLOG')->nullable();
        });
        Schema::connection('crudo_flog_test')->create('TwFlogsItemLine', function (Blueprint $table): void {
            $table->string('IDFLOG');
            $table->integer('LINENUM');
            $table->string('ITEMID')->nullable();
            $table->string('INVENTSIZEID')->nullable();
            $table->string('PURCHBARCODE')->nullable();
            $table->string('SIMULACIONVTAS')->nullable();
            $table->string('SIMULACIONDISENO')->nullable();
        });
    }

    protected function tearDown(): void
    {
        URL::forceScheme(null);
        URL::forceRootUrl(null);
        DB::disconnect('crudo_flog_test');
        @unlink($this->database);

        parent::tearDown();
    }

    public function test_explicit_program_flog_is_the_primary_link_and_selects_its_exact_line(): void
    {
        $this->insertFlog('CE-FLOG-100', 'C001', 'Cliente principal');
        $this->insertLine('CE-FLOG-100', 1, 'ART-1', 'CH', 'PB-1');
        $this->insertLine(
            'CE-FLOG-100',
            2,
            'ART-2',
            'GDE',
            'PB-2',
            'https://example.test/ventas.jpg',
            'https://example.test/diseno.jpg',
        );

        $result = app(CrudoFlogService::class)->find([
            'flogId' => 'CE-FLOG-100',
            'itemId' => 'ART-2',
            'inventSizeId' => 'GDE',
        ], ['PB-AJENO']);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('program_flog', $result['source']);
        $this->assertSame('CE-FLOG-100', $result['flog']);
        $this->assertSame('C001 Cliente principal', $result['client']);
        $this->assertSame('ART-2', $result['itemId']);
        $this->assertSame('GDE', $result['inventSizeId']);
        $this->assertSame('https://example.test/ventas.jpg', $result['simulationSalesUrl']);
        $this->assertTrue($result['lineMatched']);
    }

    public function test_purch_barcode_links_historical_crudo_when_there_is_no_active_program(): void
    {
        $this->insertFlog('CE-FLOG-200', 'C002', 'Cliente histórico', 0);
        $this->insertLine('CE-FLOG-200', 1, 'ART-20', 'MED', 'PB-HIST');

        $result = app(CrudoFlogService::class)->find(null, ['PB-HIST']);

        $this->assertSame('ok', $result['status']);
        $this->assertSame('purch_barcode', $result['source']);
        $this->assertSame('CE-FLOG-200', $result['flog']);
        $this->assertSame('ART-20', $result['itemId']);
        $this->assertSame('MED', $result['inventSizeId']);
    }

    public function test_item_and_size_are_only_the_last_resort_link(): void
    {
        $this->insertFlog('CE-FLOG-9', 'C009', 'Cliente anterior');
        $this->insertFlog('CE-FLOG-10', 'C010', 'Cliente reciente');
        $this->insertLine('CE-FLOG-9', 1, 'ART-X', '100X200', 'PB-9');
        $this->insertLine('CE-FLOG-10', 1, 'ART-X', '100X200', 'PB-10');

        $result = app(CrudoFlogService::class)->find([
            'flogId' => null,
            'itemId' => 'ART-X',
            'inventSizeId' => '100X200',
        ]);

        $this->assertSame('item_size', $result['source']);
        $this->assertSame('CE-FLOG-10', $result['flog']);
    }

    public function test_repeated_flog_lookup_is_served_from_cache(): void
    {
        config()->set('crudo.flog_cache_seconds', 300);
        $this->insertFlog('CE-FLOG-CACHE', 'C100', 'Cliente cache');
        $this->insertLine('CE-FLOG-CACHE', 1, 'ART-CACHE', 'GDE', 'PB-CACHE');

        $queries = 0;
        DB::connection('crudo_flog_test')->listen(static function () use (&$queries): void {
            $queries++;
        });

        $service = app(CrudoFlogService::class);
        $first = $service->find([
            'flogId' => 'CE-FLOG-CACHE',
            'itemId' => 'ART-CACHE',
            'inventSizeId' => 'GDE',
        ]);
        $second = $service->find([
            'flogId' => 'CE-FLOG-CACHE',
            'itemId' => 'ART-CACHE',
            'inventSizeId' => 'GDE',
        ]);

        $this->assertSame('ok', $first['status']);
        $this->assertSame($first, $second);
        $this->assertSame(1, $queries);
    }

    public function test_cached_flog_builds_proxy_urls_with_the_current_request_host(): void
    {
        config()->set('crudo.flog_cache_seconds', 300);
        $this->insertFlog('CE-FLOG-HOST', 'C101', 'Cliente host');
        $this->insertLine(
            'CE-FLOG-HOST',
            1,
            'ART-HOST',
            'MED',
            'PB-HOST',
            'ventas-host.jpg',
            'diseno-host.jpg',
        );

        $service = app(CrudoFlogService::class);

        URL::forceRootUrl('http://127.0.0.1:8000');
        $local = $service->find([
            'flogId' => 'CE-FLOG-HOST',
            'itemId' => 'ART-HOST',
            'inventSizeId' => 'MED',
        ]);

        URL::forceRootUrl('https://192.168.2.15');
        URL::forceScheme('https');
        $network = $service->find([
            'flogId' => 'CE-FLOG-HOST',
            'itemId' => 'ART-HOST',
            'inventSizeId' => 'MED',
        ]);

        $this->assertStringStartsWith('http://127.0.0.1:8000/', $local['simulationSalesUrl']);
        $this->assertStringStartsWith('https://192.168.2.15/', $network['simulationSalesUrl']);
        $this->assertStringNotContainsString('127.0.0.1', $network['simulationSalesUrl']);
    }

    private function insertFlog(string $flog, string $account, string $client, int $state = 3): void
    {
        DB::connection('crudo_flog_test')->table('TwFlogsTable')->insert([
            'IDFLOG' => $flog,
            'CUSTACCOUNT' => $account,
            'CUSTNAME' => $client,
            'ESTADOFLOG' => $state,
        ]);
    }

    private function insertLine(
        string $flog,
        int $line,
        string $item,
        string $size,
        string $barcode,
        ?string $salesSimulation = null,
        ?string $designSimulation = null,
    ): void {
        DB::connection('crudo_flog_test')->table('TwFlogsItemLine')->insert([
            'IDFLOG' => $flog,
            'LINENUM' => $line,
            'ITEMID' => $item,
            'INVENTSIZEID' => $size,
            'PURCHBARCODE' => $barcode,
            'SIMULACIONVTAS' => $salesSimulation,
            'SIMULACIONDISENO' => $designSimulation,
        ]);
    }
}
