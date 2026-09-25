<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Tests\TestCase;

/**
 * PERF-14 (plan 18-03; vive aquí por la propiedad de archivos de la sesión): fuera de
 * producción, un atributo descartado por fill() o leído sin cargar deja un aviso en el
 * log, una sola vez por modelo y atributo en la request, y nunca lanza.
 */
class AvisosDeModeloTest extends TestCase
{
    public function test_un_atributo_fuera_de_fillable_avisa_una_vez_y_se_descarta_como_siempre(): void
    {
        Log::spy();

        $modelo = new ModeloDeAviso;
        $modelo->fill(['Nombre' => 'A', 'Columna' => 'x']);
        $modelo->fill(['Nombre' => 'B', 'Columna' => 'y']);

        $this->assertSame(['Nombre' => 'B'], $modelo->getAttributes());
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $mensaje, array $contexto) => str_contains($mensaje, 'fill() descartó')
            && $contexto['atributo'] === ModeloDeAviso::class.'::Columna');
    }

    public function test_leer_un_atributo_no_cargado_avisa_una_vez_y_devuelve_null(): void
    {
        Log::spy();

        $modelo = (new ModeloDeAviso)->newFromBuilder(['Id' => 1, 'Nombre' => 'A']);

        $this->assertNull($modelo->Ausente);
        $this->assertNull($modelo->Ausente);
        $this->assertSame('A', $modelo->Nombre);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $mensaje, array $contexto) => str_contains($mensaje, 'no se cargó')
            && $contexto['atributo'] === ModeloDeAviso::class.'::Ausente');
    }

    public function test_en_produccion_no_se_activa(): void
    {
        $this->app['env'] = 'production';

        (new ReflectionMethod(AppServiceProvider::class, 'avisarAtributosDeModelo'))
            ->invoke(new AppServiceProvider($this->app));

        $this->assertFalse(Model::preventsSilentlyDiscardingAttributes());
        $this->assertFalse(Model::preventsAccessingMissingAttributes());
    }

    protected function tearDown(): void
    {
        // Los flags son estáticos: el siguiente boot los vuelve a fijar, pero no se deja
        // el estado de "producción" colgado entre pruebas.
        Model::preventSilentlyDiscardingAttributes();
        Model::preventAccessingMissingAttributes();

        parent::tearDown();
    }
}

class ModeloDeAviso extends Model
{
    protected $table = 'ModeloDeAviso';

    protected $fillable = ['Nombre'];

    public $timestamps = false;
}
