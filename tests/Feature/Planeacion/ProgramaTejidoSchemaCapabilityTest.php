<?php

namespace Tests\Feature\Planeacion;

use App\Helpers\StringTruncator;
use App\Models\Planeacion\Muestras;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * PT-01 · 01.2 — Matriz de capacidades por superficie (config/planeacion.php).
 *
 * El esquema físico vive en SQL Server y aquí no hay SQL Server: los datos de la
 * matriz salen del research live (2026-07-22) y los huecos (longitudes de Muestras)
 * quedan en null hasta correr .planning/phases/01-guardrails/sql/01-schema-fisico.sql.
 * Este test hace que esos datos no se queden como documentación muerta:
 *  - toda capacidad está declarada para ambas superficies;
 *  - todo código que escribe o lee una columna que Muestras no tiene está inventariado
 *    con su tabla y capacidad (un archivo nuevo que la use rompe el test);
 *  - cuando se llenen las longitudes reales, el truncado que exceda la longitud física
 *    de Muestras rompe el test hasta clasificarlo.
 */
class ProgramaTejidoSchemaCapabilityTest extends TestCase
{
    private const CAPACIDADES = ['redbooth', 'marbetes', 'produccion', 'descarga', 'finalizacion', 'longitudes'];

    /**
     * Archivos de app/ que mencionan alguna columna ausente en Muestras.
     * tabla: 'superficie' = tabla de Programa o Muestras según ProgramaTejidoContext
     *        (alcanzable desde rutas de Muestras); 'programa' = ReqProgramaTejido fija;
     *        'otra' = columna homónima de otra tabla (CatCodificados, Engomado).
     *
     * @var array<string, array{0: list<string>, 1: string, 2: string}>
     */
    private const INVENTARIO = [
        'app/Models/Planeacion/ReqProgramaTejido.php' => [['IdRedbooth', 'NoMarbete', 'NombreRedbooth', 'ProdId', 'RollosProgramados'], 'superficie', 'modelo: $fillable/$casts compartidos con Muestras'],
        'app/Observers/ReqProgramaTejidoObserver.php' => [['RollosProgramados'], 'superficie', 'produccion: UPDATE de fórmulas falla en Muestras y se traga'],
        'app/Http/Controllers/Planeacion/ProgramaTejido/LiberarOrdenesController.php' => [['NoMarbete', 'RollosProgramados'], 'superficie', 'marbetes: liberar en Muestras falla; editor sin ruta en Muestras'],
        'app/Http/Controllers/Planeacion/ProgramaTejido/helper/UtilityHelpers.php' => [['NoMarbete', 'RollosProgramados'], 'superficie', 'marbetes: solo lectura (null en Muestras)'],
        'app/Http/Controllers/Planeacion/ProgramaTejido/RedboothProgramaTejidoController.php' => [['IdRedbooth', 'NombreRedbooth'], 'superficie', 'redbooth: sin ruta en Muestras'],
        'app/Services/Planeacion/ProgramaTejido/ProgramaTejidoSurface.php' => [['IdRedbooth', 'NoMarbete', 'NombreRedbooth', 'ProdId', 'ProduccionMarbetes', 'RollosProgramados'], 'superficie', 'metadata: capacidad → columnas físicas que exige (no lee ni escribe)'],
        'app/Services/Trazabilidad/TrazabilidadRedboothService.php' => [['IdRedbooth', 'NombreRedbooth'], 'programa', 'redbooth: Trazabilidad no cambia de tabla'],
        'app/Http/Controllers/Tejedores/NotificarMontadoRollo/NotificarMontRollosController.php' => [['ProdId'], 'programa', 'produccion: DB::table(ReqProgramaTejido) fijo'],
        'app/Models/Planeacion/Catalogos/CatCodificados.php' => [['IdRedbooth', 'NoMarbete', 'NombreRedbooth'], 'otra', 'CatCodificados'],
        'app/Http/Controllers/Planeacion/CatalogoPlaneacion/ModelosCodificados/CodificacionController.php' => [['NoMarbete'], 'otra', 'CatCodificados'],
        'app/Http/Controllers/Planeacion/ProgramaTejido/OrdenDeCambio/Felpa/OrdenDeCambioFelpaController.php' => [['NoMarbete'], 'otra', 'CatCodificados'],
        'app/Http/Controllers/Tejido/Reportes/SaldosController.php' => [['NoMarbete'], 'otra', 'CatCodificados'],
        'app/Exports/Saldos2026Export.php' => [['NoMarbete'], 'otra', 'CatCodificados (vía SaldosController)'],
        'app/Services/Planeacion/CatCodificados/Excel/CatCodificadosExcelHeaderMapper.php' => [['NoMarbete'], 'otra', 'CatCodificados'],
        'app/Services/Planeacion/CatCodificados/Excel/CatCodificadosExcelRowMapper.php' => [['NoMarbete'], 'otra', 'CatCodificados'],
        'app/Services/Planeacion/Liberar/LiberarCatCodificadosWriter.php' => [['NoMarbete'], 'otra', 'CatCodificados'],
        'app/Services/Planeacion/RevivirOrdenProgramaDesdeCatService.php' => [['NoMarbete'], 'otra', 'lee CatCodificados.NoMarbete'],
        'app/Services/Planeacion/SaldoMarbeteCodificacionService.php' => [['ProduccionMarbetes'], 'otra', 'CatCodificados'],
        'app/Http/Controllers/Engomado/CapturaFormulas/EngProduccionFormulacionController.php' => [['ProdId'], 'otra', 'Engomado'],
        'app/Models/Engomado/EngProduccionFormulacionModel.php' => [['ProdId'], 'otra', 'Engomado'],
    ];

    /**
     * Campos cuyo truncado excede la longitud física de Muestras y ya están
     * clasificados (se atienden con la decisión 01.3 §4.6). Vacío mientras las
     * longitudes sigan en null.
     */
    private const GAPS_LONGITUD = [];

    public function test_ambas_superficies_declaran_las_mismas_capacidades(): void
    {
        $superficies = config('planeacion.superficies');

        $this->assertSame(['programa', 'muestras'], array_keys($superficies));
        foreach ($superficies as $nombre => $s) {
            $this->assertSame(self::CAPACIDADES, array_keys($s['capacidades']), $nombre);
            foreach ($s['capacidades'] as $capacidad => $valor) {
                $this->assertContains($valor, [true, false, null], "{$nombre}.{$capacidad}");
            }
        }
        $this->assertSame(array_fill_keys(self::CAPACIDADES, true), $superficies['programa']['capacidades']);
        $this->assertSame(ReqProgramaTejido::tableName(), $superficies['programa']['tabla']);
        $this->assertSame(Muestras::tableName(), $superficies['muestras']['tabla']);
    }

    public function test_muestras_declara_la_decision_01_3_del_owner(): void
    {
        // 01-DECISION-PROGRAMA-MUESTRAS.md §5: A = true (paridad física), B = false (exclusiva de Programa).
        $this->assertSame([
            'redbooth' => false,
            'marbetes' => true,
            'produccion' => true,
            'descarga' => false,
            'finalizacion' => false,
            'longitudes' => true,
        ], config('planeacion.superficies.muestras.capacidades'));
        $this->assertNotContains(null, config('planeacion.superficies.muestras.capacidades'), 'Ya no hay capacidades sin decidir');
    }

    public function test_cada_capacidad_a_de_muestras_tiene_su_sql_aditivo(): void
    {
        // Alternativa A = DDL aditivo versionado, nunca migración (el historial no coincide con live).
        foreach (['marbetes', 'produccion', 'longitudes'] as $capacidad) {
            $ruta = database_path("sql/pt_muestras_{$capacidad}.sql");
            $this->assertFileExists($ruta);
            $sql = (string) file_get_contents($ruta);
            $this->assertStringContainsString('ROLLBACK', mb_strtoupper($sql), "{$capacidad}: falta el rollback");
            $this->assertStringContainsString('NOTA PARA EL DBA', mb_strtoupper($sql), "{$capacidad}: falta la nota para el DBA");
            $this->assertDoesNotMatchRegularExpression('/\bDROP\s+TABLE\b|\bDELETE\s+FROM\b|\bUPDATE\s+dbo\./i', $sql, "{$capacidad}: el script debe ser aditivo");
        }

        // Cada columna que Muestras no tiene por marbetes/producción entra con preflight.
        $aditivos = file_get_contents(database_path('sql/pt_muestras_marbetes.sql')).file_get_contents(database_path('sql/pt_muestras_produccion.sql'));
        foreach (['NoMarbete', 'RollosProgramados', 'ProduccionMarbetes', 'ProdId'] as $columna) {
            $this->assertStringContainsString("COL_LENGTH('dbo.MuestrasPrograma', '{$columna}') IS NULL", $aditivos, $columna);
        }
        // Las 11 longitudes se igualan contra la de Programa, leída de sys.columns (no a ojo).
        $longitudes = (string) file_get_contents(database_path('sql/pt_muestras_longitudes.sql'));
        foreach (array_keys(config('planeacion.superficies.muestras.longitudes')) as $columna) {
            $this->assertStringContainsString("'{$columna}'", $longitudes, $columna);
        }
    }

    public function test_el_modelo_compartido_acepta_columnas_que_muestras_no_tiene(): void
    {
        $ausentes = config('planeacion.superficies.muestras.columnas_ausentes');
        $enFillable = array_values(array_intersect((new Muestras)->getFillable(), $ausentes));
        sort($enFillable);

        // Caracterización: Muestras hereda $fillable de Programa, así que un create()/fill()
        // con estas llaves llega al INSERT y SQL Server lo rechaza. ProduccionMarbetes no
        // está en $fillable (el observer no la sincroniza a propósito).
        $this->assertSame(['IdRedbooth', 'NoMarbete', 'NombreRedbooth', 'ProdId', 'RollosProgramados'], $enFillable);
        $this->assertSame(['ProduccionMarbetes'], array_values(array_diff($ausentes, $enFillable)));
    }

    public function test_todo_codigo_que_toca_columnas_ausentes_en_muestras_esta_inventariado(): void
    {
        $ausentes = implode('|', config('planeacion.superficies.muestras.columnas_ausentes'));
        $patron = "/['\"]({$ausentes})['\"]|->({$ausentes})\\b/";
        $encontrado = [];

        foreach (File::allFiles(app_path()) as $archivo) {
            if ($archivo->getExtension() !== 'php' || ! preg_match_all($patron, $archivo->getContents(), $m)) {
                continue;
            }
            $columnas = array_values(array_unique(array_filter([...$m[1], ...$m[2]])));
            sort($columnas);
            $encontrado[str_replace('\\', '/', 'app/'.$archivo->getRelativePathname())] = $columnas;
        }
        ksort($encontrado);

        $inventario = array_map(fn (array $fila) => $fila[0], self::INVENTARIO);
        ksort($inventario);

        $this->assertSame($inventario, $encontrado, 'Código nuevo o cambiado usa columnas que Muestras no tiene: clasificarlo en INVENTARIO');
    }

    public function test_el_truncado_no_excede_la_longitud_fisica_de_cada_superficie(): void
    {
        $limites = StringTruncator::getFieldLimits();
        $excedidos = [];
        $pendientes = [];

        foreach (config('planeacion.superficies') as $nombre => $superficie) {
            foreach ($superficie['longitudes'] as $campo => $longitud) {
                $this->assertArrayHasKey($campo, $limites, "{$campo} no tiene límite en StringTruncator");
                if ($longitud === null) {
                    $pendientes[] = "{$nombre}.{$campo}";
                } elseif ($limites[$campo] > $longitud) {
                    $excedidos[] = "{$nombre}.{$campo}";
                }
            }
        }

        $this->assertSame(self::GAPS_LONGITUD, $excedidos, 'StringTruncator deja pasar más de lo que cabe: clasificar en GAPS_LONGITUD');

        // Hueco marcado: las 11 longitudes de Muestras siguen sin dato físico. Al llenarlas
        // con la salida de 01-schema-fisico.sql (RS2) esta lista se vacía.
        $this->assertSame([
            'muestras.CalendarioId', 'muestras.FlogsId', 'muestras.NombreProyecto', 'muestras.CustName',
            'muestras.AplicacionId', 'muestras.Observaciones', 'muestras.ColorTrama', 'muestras.Prioridad',
            'muestras.CombinaTram', 'muestras.BomId', 'muestras.BomName',
        ], $pendientes);
    }

    public function test_no_hay_migraciones_que_toquen_muestras(): void
    {
        // PT-01 no ejecuta migraciones; esto congela que ninguna migración versionada
        // intenta alinear Muestras por su cuenta (la decisión 01.3 va por .sql con preflight).
        $tocan = array_filter(
            File::files(database_path('migrations')),
            fn ($f) => str_contains($f->getContents(), 'MuestrasPrograma')
        );

        $this->assertSame([], array_values(array_map(fn ($f) => $f->getFilename(), $tocan)));
    }
}
