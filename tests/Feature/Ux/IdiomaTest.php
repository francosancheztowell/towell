<?php

declare(strict_types=1);

namespace Tests\Feature\Ux;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** UX-08: validaciones y textos del framework en español. */
class IdiomaTest extends TestCase
{
    public function test_el_locale_por_defecto_es_espanol(): void
    {
        $this->assertStringContainsString("'locale' => env('APP_LOCALE', 'es')", (string) file_get_contents(config_path('app.php')));
        $this->assertFileExists(lang_path('es/validation.php'));
        $this->assertStringContainsString('APP_LOCALE=es', (string) file_get_contents(base_path('.env.example')));
    }

    public function test_los_mensajes_de_validacion_salen_en_espanol(): void
    {
        app()->setLocale('es');

        $errores = Validator::make(
            ['numero_empleado' => '', 'correo' => 'x', 'cantidad' => 'abc'],
            ['numero_empleado' => 'required', 'correo' => 'email', 'cantidad' => 'numeric'],
        )->errors();

        $this->assertSame('El campo número de empleado es obligatorio.', $errores->first('numero_empleado'));
        $this->assertSame('El campo correo debe ser un correo electrónico válido.', $errores->first('correo'));
        $this->assertSame('El campo cantidad debe ser un número.', $errores->first('cantidad'));
    }

    public function test_es_tiene_las_mismas_llaves_que_el_ingles_del_framework(): void
    {
        $en = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $es = require lang_path('es/validation.php');

        $faltan = array_diff(array_keys($en), array_keys($es));
        $this->assertSame([], array_values($faltan), 'faltan traducciones');
    }

    public function test_json_de_textos_comunes_es_valido(): void
    {
        $json = json_decode((string) file_get_contents(lang_path('es.json')), true);

        $this->assertIsArray($json);
        $this->assertSame('Anterior', $json['Previous']);
    }
}
