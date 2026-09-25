<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * PERF-05 (lazy loading → log fuera de producción) y PERF-06 (request con más de 500 ms de
 * consultas en una conexión → log). Ninguno lanza.
 */
final class ObservabilidadConsultasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('perf_padres', function (Blueprint $table) {
            $table->increments('id');
        });
        Schema::create('perf_hijos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('padre_id');
        });
        DB::table('perf_padres')->insert([['id' => 1], ['id' => 2]]);
        DB::table('perf_hijos')->insert([['padre_id' => 1], ['padre_id' => 2]]);
    }

    public function test_fuera_de_produccion_el_lazy_loading_se_loguea_una_vez_y_no_lanza(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
        Log::spy();

        $total = 0;
        foreach (PerfPadre::all() as $padre) {
            $total += $padre->hijos->count(); // N+1: una query por padre
        }
        foreach (PerfPadre::all() as $padre) {
            $total += $padre->hijos->count();
        }

        $this->assertSame(4, $total, 'La relación se carga igual: solo se avisa.');
        Log::shouldHaveReceived('warning')
            ->with('Rendimiento: relación cargada en lazy (posible N+1).', \Mockery::on(
                fn (array $ctx) => $ctx['relacion'] === PerfPadre::class.'::hijos',
            ))
            ->once();
    }

    public function test_en_produccion_no_se_activa(): void
    {
        $this->app['env'] = 'production';
        $this->avisarLazyLoading();
        $this->assertFalse(Model::preventsLazyLoading());

        $this->app['env'] = 'testing';
        $this->avisarLazyLoading();
        $this->assertTrue(Model::preventsLazyLoading());
    }

    public function test_mas_de_500_ms_de_consultas_en_una_conexion_se_loguea_una_vez(): void
    {
        Log::spy();
        $conexion = DB::connection();
        DB::reconnect(); // vuelve a disparar ConnectionEstablished: no debe duplicar el aviso

        $conexion->logQuery('select 1', [], 300);
        Log::shouldNotHaveReceived('warning');

        $conexion->logQuery('select * from lenta', [], 250);
        $conexion->logQuery('select 2', [], 900);

        Log::shouldHaveReceived('warning')
            ->with('Rendimiento: la request lleva más de 500 ms en consultas.', \Mockery::on(
                fn (array $ctx) => $ctx['conexion'] === $conexion->getName()
                    && $ctx['ms'] >= 550 && $ctx['ms'] < 1000
                    && $ctx['ultima_consulta'] === 'select * from lenta',
            ))
            ->once();
    }

    private function avisarLazyLoading(): void
    {
        (new ReflectionMethod(AppServiceProvider::class, 'avisarLazyLoading'))
            ->invoke(new AppServiceProvider($this->app));
    }
}

class PerfPadre extends Model
{
    protected $table = 'perf_padres';

    public $timestamps = false;

    public function hijos(): HasMany
    {
        return $this->hasMany(PerfHijo::class, 'padre_id');
    }
}

class PerfHijo extends Model
{
    protected $table = 'perf_hijos';

    public $timestamps = false;
}
