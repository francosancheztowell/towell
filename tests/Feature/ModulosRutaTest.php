<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SiembraPermisos;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * El alta de modulos tiene que capturar SYSRoles.Ruta.
 *
 * ModulosController nunca la escribio y el formulario no la pedia, asi que los 9 modulos
 * creados desde enero de 2026 nacieron con Ruta = NULL. Sin Ruta, moduleNameForRoute() no
 * puede resolver el modulo y los permisos quedan obligados a buscarse por NOMBRE, que esta
 * repetido en SYSRoles. Asi nacio el bug de "Utileria": el idrol 188 (Planeacion, creado por
 * la UI, sin Ruta) colisionaba con el 67 (Configuracion, dado de alta por SQL en 2025 con
 * Ruta), y el gate validaba el modulo equivocado para 54 personas.
 */
final class ModulosRutaTest extends TestCase
{
    use SiembraPermisos;
    use UsesSqlsrvSqlite;

    private const IDROL_MODULOS = 101;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();

        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('SYSRoles')) {
            $schema->create('SYSRoles', function (Blueprint $table) {
                $table->increments('idrol');
                $table->string('orden');
                $table->string('modulo');
                $table->integer('acceso')->default(0);
                $table->integer('crear')->default(0);
                $table->integer('modificar')->default(0);
                $table->integer('eliminar')->default(0);
                $table->integer('reigstrar')->default(0);
                $table->string('imagen')->nullable();
                $table->string('Ruta')->nullable();
                $table->integer('Nivel')->nullable();
                $table->string('Dependencia')->nullable();
                $table->timestamps();
            });
        }
        if (! $schema->hasTable('SYSUsuariosRoles')) {
            $schema->create('SYSUsuariosRoles', function (Blueprint $table) {
                $table->integer('idusuario');
                $table->integer('idrol');
                $table->integer('acceso')->default(0);
                $table->integer('crear')->default(0);
                $table->integer('modificar')->default(0);
                $table->integer('eliminar')->default(0);
                $table->integer('registrar')->default(0);
            });
        }
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('SYSUsuariosRoles');
        Schema::connection('sqlsrv')->dropIfExists('SYSRoles');

        parent::tearDown();
    }

    public function test_crear_un_modulo_persiste_su_ruta(): void
    {
        $this->actingAs($this->usuarioConPermisos())
            ->post(route('configuracion.utileria.modulos.store'), [
                'orden' => '950',
                'modulo' => 'Modulo De Prueba',
                'Nivel' => 1,
                'Dependencia' => null,
                'Ruta' => '/modulo-de-prueba',
                'acceso' => 1,
            ]);

        $fila = DB::connection('sqlsrv')->table('SYSRoles')
            ->where('modulo', 'Modulo De Prueba')
            ->first();

        $this->assertNotNull($fila, 'No se creo el modulo.');
        $this->assertSame(
            '/modulo-de-prueba',
            $fila->Ruta,
            'El alta perdio la Ruta: el modulo no se podra resolver con moduleNameForRoute() '.
            'y sus permisos dependeran del nombre.'
        );
    }

    public function test_el_formulario_de_modulos_pide_la_ruta(): void
    {
        // El controller ya aceptaba Ruta (esta en $fillable y store usa $request->except).
        // Lo que faltaba era el campo en la vista, asi que eso es lo que hay que fijar.
        $blade = file_get_contents(resource_path('views/modulos/gestion-modulos/index.blade.php'));

        $this->assertSame(
            2,
            substr_count($blade, 'name="Ruta"'),
            'La vista de gestion de modulos debe pedir Ruta en el alta y en la edicion.'
        );
        $this->assertStringContainsString(
            'data-ruta=',
            $blade,
            'La fila de la tabla debe exponer data-ruta para poder rellenar el modal de edicion.'
        );
    }

    private function usuarioConPermisos(): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Alta de modulos']);
        $usuario->idusuario = 999201;

        $this->sembrarPermisos($usuario->idusuario, [self::IDROL_MODULOS => 'Modulos']);

        return $usuario;
    }
}
