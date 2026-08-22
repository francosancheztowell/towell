<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\StoreUsuarioRequest;
use App\Models\Sistema\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * El turno 4 cubre descansos: no tiene horario fijo, puede caer en cualquier
 * momento del dia. Por eso NO se deriva del reloj (TurnoHelper sigue devolviendo
 * solo 1/2/3) y vive unicamente como atributo del usuario.
 */
class UsuarioTurnoTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        // El repositorio de usuarios escribe con el modelo Usuario, cuya tabla
        // es 'dbo.SYSUsuario'; createAuthTable() solo crea 'SYSUsuario'.
        // En sqlite 'dbo' es un schema, asi que hay que adjuntarlo.
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        Schema::connection('sqlsrv')->create('dbo.SYSUsuario', function (Blueprint $table) {
            $table->increments('idusuario');
            $table->string('nombre', 150);
            $table->string('contrasenia', 255);
            $table->string('numero_empleado', 30)->nullable();
            $table->string('turno', 50)->nullable();
            $table->timestamps();
        });
    }

    public function test_turno_4_pasa_la_validacion_de_alta_de_usuario(): void
    {
        $reglas = (new StoreUsuarioRequest)->rules();

        $validator = Validator::make([
            'numero_empleado' => '9004',
            'nombre' => 'Comodin Cubre Descansos',
            'contrasenia' => 'secreto',
            'turno' => '4',
        ], $reglas);

        $this->assertFalse(
            $validator->fails(),
            'turno=4 debe pasar la validacion: '.$validator->errors()->first('turno')
        );
    }

    public function test_turno_4_se_guarda_y_se_relee_como_string(): void
    {
        Usuario::create([
            'nombre' => 'Comodin Cubre Descansos',
            'contrasenia' => 'secreto',
            'numero_empleado' => '9004',
            'turno' => '4',
        ]);

        $usuario = Usuario::where('numero_empleado', '9004')->first();

        $this->assertNotNull($usuario);
        // Se compara con string: un cast a int en el modelo rompería los
        // consumidores que hacen comparaciones sueltas contra '1'/'2'/'3'.
        $this->assertSame('4', $usuario->turno);
    }

    public function test_los_selects_de_usuario_ofrecen_el_turno_4(): void
    {
        // ponytail: assert de texto sobre la blade en lugar de renderizarla.
        // Renderizar el form exige rutas, permisos y $departamentos; esto falla
        // igual si alguien borra la opcion, que es lo unico que importa aqui.
        $selects = [
            resource_path('views/modulos/usuarios/form_usuario.blade.php'),
            resource_path('views/modulos/usuarios/select.blade.php'),
        ];

        foreach ($selects as $archivo) {
            $this->assertStringContainsString(
                'value="4"',
                file_get_contents($archivo),
                basename($archivo).' perdio la opcion de turno 4'
            );
        }
    }
}
