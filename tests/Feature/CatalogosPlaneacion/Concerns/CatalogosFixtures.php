<?php

namespace Tests\Feature\CatalogosPlaneacion\Concerns;

use App\Models\Planeacion\Catalogos\CatMatrizCalibres;
use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqCalendarioTab;
use App\Models\Planeacion\ReqEficienciaStd;
use App\Models\Planeacion\ReqMatrizHilos;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Models\Planeacion\ReqVelocidadStd;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesSqlsrvSqlite;

/**
 * Esquema sqlite de los catálogos de Planeación (19-06b): todo en la conexión 'sqlsrv' (sqlite en
 * memoria) como default, con el esquema 'dbo' adjunto para los modelos que lo escriben en $table.
 */
trait CatalogosFixtures
{
    use UsesSqlsrvSqlite;

    /** Permisos por nombre de SYSRoles (catalog-actions) e idrol de las rutas. */
    protected const MODULOS = [
        'Telares' => 8, 'Eficiencias STD' => 9, 'Velocidad STD' => 10, 'Calendarios' => 11,
        'Aplicaciones (Cat.)' => 12, 'Matriz Calibres' => 14, 'Matriz Hilos' => 15, 'Pesos por Rollos' => 172,
    ];

    protected function prepararCatalogos(): void
    {
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        $this->createAuthTable();

        foreach ([ReqAplicaciones::class, ReqEficienciaStd::class, ReqVelocidadStd::class,
            ReqMatrizHilos::class, CatMatrizCalibres::class, ReqPesosRollosTejido::class,
            ReqCalendarioLine::class, ReqProgramaTejidoLine::class] as $modelo) {
            $this->createTablaDesdeModelo($modelo);
        }
        $this->createTablaDesdeModelo(ReqProgramaTejido::class, ['UpdatedAt', 'CreatedAt']);
        // La llave de ReqCalendarioTab es texto (createTablaDesdeModelo la haría autoincremental).
        $this->createTablaDbo('ReqCalendarioTab', ['CalendarioId' => 'TEXT PRIMARY KEY', 'Nombre' => 'TEXT']);
        // Catálogo de Telares = URDCatalogoMaquinas (llave de texto, Id IDENTITY aparte).
        DB::connection('sqlsrv')->statement('CREATE TABLE "URDCatalogoMaquinas" ("Id" INTEGER PRIMARY KEY AUTOINCREMENT, "MaquinaId" TEXT NOT NULL UNIQUE, "Nombre" TEXT, "Departamento" TEXT, "Codificacion" TEXT, "Secuencia" INTEGER)');

        $this->actingAs($this->createUsuario(), 'web');
        foreach (self::MODULOS as $modulo => $idrol) {
            $this->grantModulo($modulo, ['acceso', 'crear', 'modificar', 'eliminar'], idRol: $idrol);
        }
    }

    /** Programa de tejido mínimo sin disparar el observer (solo para datos de partida). */
    protected function programa(array $atributos): ReqProgramaTejido
    {
        $programa = new ReqProgramaTejido;
        $programa->forceFill($atributos);
        $programa->saveQuietly();

        return $programa->refresh();
    }

    /** data-catalogo de la vista, decodificado. */
    protected function configDeVista(string $html): array
    {
        preg_match("/data-catalogo='([^']+)'/", $html, $m);

        return json_decode(html_entity_decode($m[1] ?? '{}'), true) ?? [];
    }
}
