<?php

namespace Tests\Feature;

use App\Helpers\AuditoriaHelper;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifica el trigger tr_ReqProgramaTejido_Audit contra SQL Server real.
 *
 * El trigger es la lógica que se está probando: no hay forma de ejercitarla en sqlite,
 * así que la suite se salta sola si la conexión no es sqlsrv. Cada prueba crea y borra
 * sus propios registros desechables (prefijo ZZ-AUD) en ReqProgramaTejido.
 *
 * El acceso a la pantalla se prueba aparte, en AuditoriaProgramaTejidoPantallaTest.
 */
class AuditoriaProgramaTejidoTest extends TestCase
{
    /** Prefijo de los registros desechables; el tearDown borra todo lo que empiece así. */
    private const PREFIJO = 'ZZ-AUD';

    private const ORDEN_PRUEBA = self::PREFIJO.'-1';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlsrv') {
            $this->markTestSkipped('El trigger de auditoría solo existe en SQL Server.');
        }

        $this->limpiarRegistrosPrueba();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            $this->limpiarRegistrosPrueba();
        }

        parent::tearDown();
    }

    // =====================================================================
    // Ruido: lo que NO debe auditarse
    // =====================================================================

    public function test_un_update_sin_columnas_relevantes_no_genera_auditoria(): void
    {
        $id = $this->crearRegistro();
        $marca = $this->ultimoAuditId();

        $this->sellarContexto('PRUEBA_RUIDO');
        DB::update('UPDATE ReqProgramaTejido SET Observaciones = ? WHERE Id = ?', ['ruido', $id]);

        $this->assertSame(
            [],
            $this->auditoriaDesde($marca),
            'Un UPDATE que no toca ninguna columna auditada no debe dejar rastro.'
        );
    }

    public function test_reescribir_una_columna_con_su_mismo_valor_no_genera_auditoria(): void
    {
        $id = $this->crearRegistro();
        $marca = $this->ultimoAuditId();

        // Este es el caso que inflaba la tabla: los recálculos masivos reescriben
        // TotalPedido con el valor que ya tenía.
        $this->sellarContexto('PRUEBA_IDEMPOTENTE');
        DB::update('UPDATE ReqProgramaTejido SET TotalPedido = ? WHERE Id = ?', [1000, $id]);

        $this->assertSame([], $this->auditoriaDesde($marca), 'Escribir el mismo valor no es un cambio.');
    }

    // =====================================================================
    // UPDATE
    // =====================================================================

    public function test_un_update_relevante_registra_orden_contexto_y_diferencias(): void
    {
        $id = $this->crearRegistro();
        $marca = $this->ultimoAuditId();

        $this->sellarContexto('LIBERAR');
        DB::update('UPDATE ReqProgramaTejido SET TotalPedido = ?, NoTelarId = ? WHERE Id = ?', [2000, '996', $id]);

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas, 'Un UPDATE debe dejar exactamente un renglón.');

        $fila = $filas[0];
        $this->assertSame('UPDATE', $fila->Accion);
        $this->assertSame('Id='.$id.' | Orden='.self::ORDEN_PRUEBA, $fila->PK);
        $this->assertStringStartsWith('LIBERAR | ', $fila->Detalle);
        $this->assertStringContainsString('TotalPedido: 1000.00 -> 2000.00', $fila->Detalle);
        $this->assertStringContainsString('NoTelarId: 995 -> 996', $fila->Detalle);
        $this->assertStringNotContainsString('Posicion:', $fila->Detalle, 'No debe listar columnas que no cambiaron.');
    }

    /**
     * Un UPDATE de varias filas pasa por el trigger UNA vez, con inserted/deleted de N filas.
     * Si el FULL JOIN estuviera mal saldría un renglón mezclado o se perderían órdenes.
     */
    public function test_un_update_masivo_audita_cada_orden_por_separado(): void
    {
        $ids = [
            $this->crearRegistro(self::PREFIJO.'-1', '995', 901),
            $this->crearRegistro(self::PREFIJO.'-2', '996', 902),
            $this->crearRegistro(self::PREFIJO.'-3', '997', 903),
        ];
        $marca = $this->ultimoAuditId();

        $this->sellarContexto('BALANCEO');
        DB::update(
            'UPDATE ReqProgramaTejido SET TotalPedido = TotalPedido + 1 WHERE NoProduccion LIKE ?',
            [self::PREFIJO.'-%']
        );

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(3, $filas, 'Cada orden afectada necesita su propio renglón.');

        foreach ($filas as $fila) {
            $this->assertStringStartsWith('BALANCEO | ', $fila->Detalle);
            $this->assertStringContainsString('TotalPedido: 1000.00 -> 1001.00', $fila->Detalle);
        }

        $auditadas = array_map(fn ($f) => $f->PK, $filas);
        sort($auditadas);

        $esperadas = [
            'Id='.$ids[0].' | Orden='.self::PREFIJO.'-1',
            'Id='.$ids[1].' | Orden='.self::PREFIJO.'-2',
            'Id='.$ids[2].' | Orden='.self::PREFIJO.'-3',
        ];
        sort($esperadas);

        $this->assertSame($esperadas, $auditadas);
    }

    /**
     * Recorre las 16 columnas auditadas. Si alguien agrega una al trigger con el CAST
     * equivocado, o quita una por accidente, esta prueba lo caza.
     */
    #[DataProvider('columnasAuditadas')]
    public function test_cada_columna_auditada_se_reporta(string $columna, mixed $valorNuevo, string $esperado): void
    {
        $id = $this->crearRegistro();
        $marca = $this->ultimoAuditId();

        $this->sellarContexto('EDITAR');
        DB::update("UPDATE ReqProgramaTejido SET {$columna} = ? WHERE Id = ?", [$valorNuevo, $id]);

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas, "Cambiar {$columna} debe auditarse.");
        $this->assertStringContainsString($esperado, $filas[0]->Detalle);
    }

    public static function columnasAuditadas(): array
    {
        $orden = self::ORDEN_PRUEBA;

        return [
            'NoProduccion' => ['NoProduccion', self::PREFIJO.'-9', "NoProduccion: {$orden} -> ".self::PREFIJO.'-9'],
            'TotalPedido' => ['TotalPedido', 2500.5, 'TotalPedido: 1000.00 -> 2500.50'],
            'SaldoPedido' => ['SaldoPedido', 400, 'SaldoPedido: (vacio) -> 400.00'],
            'Produccion' => ['Produccion', 250, 'Produccion: (vacio) -> 250.00'],
            'NoTelarId' => ['NoTelarId', '996', 'NoTelarId: 995 -> 996'],
            'SalonTejidoId' => ['SalonTejidoId', 'YY', 'SalonTejidoId: ZZ -> YY'],
            'Posicion' => ['Posicion', 42, 'Posicion: 900 -> 42'],
            'FechaInicio' => ['FechaInicio', '2026-03-04 07:30:00', 'FechaInicio: (vacio) -> 2026-03-04 07:30'],
            'FechaFinal' => ['FechaFinal', '2026-03-09 18:00:00', 'FechaFinal: (vacio) -> 2026-03-09 18:00'],
            'EnProceso' => ['EnProceso', 1, 'EnProceso: 0 -> 1'],
            'Programado' => ['Programado', '2026-05-20', 'Programado: (vacio) -> 2026-05-20'],
            'TamanoClave' => ['TamanoClave', 'BATH', 'TamanoClave: (vacio) -> BATH'],
            'Prioridad' => ['Prioridad', 'URGENTE', 'Prioridad: (vacio) -> URGENTE'],
            'CalendarioId' => ['CalendarioId', 'CAL-ZZ', 'CalendarioId: (vacio) -> CAL-ZZ'],
            'Reprogramar' => ['Reprogramar', '1', 'Reprogramar: (vacio) -> 1'],
            'OrdCompartida' => ['OrdCompartida', 77, 'OrdCompartida: (vacio) -> 77'],
        ];
    }

    public function test_vaciar_una_columna_tambien_se_reporta(): void
    {
        $id = $this->crearRegistro();
        DB::update('UPDATE ReqProgramaTejido SET Prioridad = ? WHERE Id = ?', ['URGENTE', $id]);
        $marca = $this->ultimoAuditId();

        $this->sellarContexto('EDITAR');
        DB::update('UPDATE ReqProgramaTejido SET Prioridad = NULL WHERE Id = ?', [$id]);

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas, 'Borrar el contenido de una columna es un cambio.');
        $this->assertStringContainsString('Prioridad: URGENTE -> (vacio)', $filas[0]->Detalle);
    }

    // =====================================================================
    // Robustez: casos que rompieron la primera versión del trigger
    // =====================================================================

    /**
     * Prioridad es nvarchar(150). Con el CONVERT a NVARCHAR(50) de la primera versión,
     * dos valores que solo diferían después del carácter 50 se veían iguales y el
     * cambio desaparecía sin dejar rastro.
     */
    public function test_detecta_un_cambio_mas_alla_del_caracter_cincuenta(): void
    {
        $id = $this->crearRegistro();
        $base = str_repeat('A', 55);

        $this->sellarContexto('EDITAR');
        DB::update('UPDATE ReqProgramaTejido SET Prioridad = ? WHERE Id = ?', [$base.'-PRIMERA', $id]);

        $marca = $this->ultimoAuditId();
        DB::update('UPDATE ReqProgramaTejido SET Prioridad = ? WHERE Id = ?', [$base.'-SEGUNDA', $id]);

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas, 'Un cambio en la cola de un texto largo también es un cambio.');
        $this->assertStringContainsString('-PRIMERA -> '.$base.'-SEGUNDA', $filas[0]->Detalle);
    }

    /**
     * La auditoría nunca debe tumbar la escritura que observa. Con CAST (en vez de TRY_CAST)
     * un float fuera del rango de DECIMAL lanzaba "arithmetic overflow" dentro del trigger
     * y revertía el UPDATE del negocio.
     */
    public function test_un_valor_desmesurado_no_aborta_la_operacion_de_negocio(): void
    {
        $id = $this->crearRegistro();

        $this->sellarContexto('IMPORT');
        DB::update('UPDATE ReqProgramaTejido SET TotalPedido = ? WHERE Id = ?', [1.0e17, $id]);

        $this->assertEqualsWithDelta(
            1.0e17,
            (float) DB::table('ReqProgramaTejido')->where('Id', $id)->value('TotalPedido'),
            1.0e10,
            'El UPDATE debe persistir aunque el valor no quepa en el formato de la auditoría.'
        );
    }

    /**
     * La primera versión comparaba con ISNULL(x, '~'): si el valor real era '~', pasar a
     * NULL se veía como "sin cambio". Ahora la comparación es NULL-aware explícita.
     */
    public function test_un_valor_que_parece_centinela_no_engana_a_la_comparacion(): void
    {
        $id = $this->crearRegistro();

        $this->sellarContexto('EDITAR');
        DB::update('UPDATE ReqProgramaTejido SET Prioridad = ? WHERE Id = ?', ['~', $id]);

        $marca = $this->ultimoAuditId();
        DB::update('UPDATE ReqProgramaTejido SET Prioridad = NULL WHERE Id = ?', [$id]);

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas, 'Pasar de ~ a NULL es un cambio como cualquier otro.');
        $this->assertStringContainsString('Prioridad: ~ -> (vacio)', $filas[0]->Detalle);
    }

    // =====================================================================
    // INSERT y DELETE
    // =====================================================================

    public function test_el_insert_guarda_el_snapshot_del_alta(): void
    {
        $marca = $this->ultimoAuditId();

        $this->sellarContexto('CREAR');
        $id = $this->crearRegistro();

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas);
        $this->assertSame('INSERT', $filas[0]->Accion);
        $this->assertSame('Id='.$id.' | Orden='.self::ORDEN_PRUEBA, $filas[0]->PK);
        $this->assertSame(
            'CREAR | Orden='.self::ORDEN_PRUEBA.' | Salon=ZZ | Telar=995 | TotalPedido=1000.00 | EnProceso=0',
            $filas[0]->Detalle
        );
    }

    public function test_el_delete_conserva_la_orden_y_quien_la_borro(): void
    {
        $id = $this->crearRegistro();
        $marca = $this->ultimoAuditId();

        $this->sellarContexto('FINALIZA_DESARROLLADORES');
        DB::delete('DELETE FROM ReqProgramaTejido WHERE Id = ?', [$id]);

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas);
        $this->assertSame('DELETE', $filas[0]->Accion);
        $this->assertStringStartsWith('FINALIZA_DESARROLLADORES | ', $filas[0]->Detalle);
        $this->assertStringContainsString('Orden='.self::ORDEN_PRUEBA, $filas[0]->Detalle);
        $this->assertStringContainsString('Telar=995', $filas[0]->Detalle);
    }

    public function test_el_borrado_masivo_deja_rastro_de_cada_orden(): void
    {
        $this->crearRegistro(self::PREFIJO.'-1', '995', 901);
        $this->crearRegistro(self::PREFIJO.'-2', '996', 902);
        $marca = $this->ultimoAuditId();

        // Igual que ConfiguracionController: DELETE, no TRUNCATE (TRUNCATE no dispara triggers).
        $this->sellarContexto('BORRADO_MASIVO');
        DB::table('ReqProgramaTejido')->where('NoProduccion', 'like', self::PREFIJO.'-%')->delete();

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(2, $filas, 'Un borrado masivo debe auditar cada orden que arrasa.');

        foreach ($filas as $fila) {
            $this->assertSame('DELETE', $fila->Accion);
            $this->assertStringStartsWith('BORRADO_MASIVO | ', $fila->Detalle);
            $this->assertStringContainsString('Orden='.self::PREFIJO.'-', $fila->Detalle);
        }
    }

    // =====================================================================
    // Contexto (CONTEXT_INFO)
    // =====================================================================

    public function test_una_orden_sin_liberar_se_marca_como_tal(): void
    {
        $marca = $this->ultimoAuditId();

        $this->sellarContexto('CREAR');
        DB::statement(
            'INSERT INTO ReqProgramaTejido (SalonTejidoId, NoTelarId, TotalPedido, EnProceso, Observaciones)'
            .' VALUES (?, ?, ?, ?, ?)',
            ['ZZ', '994', 5, 0, self::PREFIJO]
        );

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas);
        $this->assertStringContainsString('Orden=SIN LIBERAR', $filas[0]->PK);
        $this->assertStringContainsString('Orden=SIN LIBERAR', $filas[0]->Detalle);
    }

    public function test_sin_contexto_sellado_la_fila_no_se_pierde(): void
    {
        $id = $this->crearRegistro();
        $marca = $this->ultimoAuditId();

        // Una escritura desde fuera de la app (job, script, SSMS) no sella CONTEXT_INFO.
        DB::statement('SET CONTEXT_INFO 0x');
        DB::update('UPDATE ReqProgramaTejido SET TotalPedido = ? WHERE Id = ?', [3000, $id]);

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas, 'Sin contexto la fila igual debe registrarse.');
        $this->assertStringStartsWith('SIN CONTEXTO | ', $filas[0]->Detalle);
        $this->assertStringContainsString('TotalPedido: 1000.00 -> 3000.00', $filas[0]->Detalle);
    }

    /**
     * CONTEXT_INFO son 128 bytes fijos. El SP pone 'acc' primero y 'user' al final justo
     * para que un nombre largo se coma solo a sí mismo y no la acción, el usuario ni la IP.
     */
    public function test_un_nombre_de_usuario_largo_no_se_come_el_contexto(): void
    {
        $id = $this->crearRegistro();
        $marca = $this->ultimoAuditId();

        DB::statement('EXEC dbo.sp_SetAppContext ?, ?, ?, ?', [
            987654,
            str_repeat('N', 120),
            '192.168.100.200',
            'ELIMINAR_ORD_COMPARTIDA_UNICO',
        ]);
        DB::update('UPDATE ReqProgramaTejido SET TotalPedido = ? WHERE Id = ?', [4000, $id]);

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas);
        $this->assertStringStartsWith('ELIMINAR_ORD_COMPARTIDA_UNICO | ', $filas[0]->Detalle);
        $this->assertSame('192.168.100.200', $filas[0]->IP);
        $this->assertSame(987654, (int) $filas[0]->UsuarioId);
        $this->assertStringStartsWith('NNN', $filas[0]->Usuario);
    }

    public function test_un_contexto_demasiado_largo_se_recorta_sin_romper(): void
    {
        $id = $this->crearRegistro();
        $marca = $this->ultimoAuditId();

        $this->sellarContexto(str_repeat('X', 60));
        DB::update('UPDATE ReqProgramaTejido SET TotalPedido = ? WHERE Id = ?', [5000, $id]);

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas);
        $this->assertStringStartsWith(
            str_repeat('X', 30).' | ',
            $filas[0]->Detalle,
            'El contexto se recorta a 30 chars, no desborda ni se pierde el diff.'
        );
        $this->assertStringContainsString('TotalPedido: 1000.00 -> 5000.00', $filas[0]->Detalle);
    }

    public function test_el_helper_de_contexto_produce_el_mismo_resultado_que_el_sp(): void
    {
        $id = $this->crearRegistro();
        $marca = $this->ultimoAuditId();

        AuditoriaHelper::contexto('DRAGDROP');
        DB::update('UPDATE ReqProgramaTejido SET Posicion = ? WHERE Id = ?', [12, $id]);

        $filas = $this->auditoriaDesde($marca);
        $this->assertCount(1, $filas);
        $this->assertStringStartsWith('DRAGDROP | ', $filas[0]->Detalle);
        $this->assertStringContainsString('Posicion: 900 -> 12', $filas[0]->Detalle);
    }

    // =====================================================================
    // El Id que devuelve Eloquent (SqlServerScopeIdentityProcessor)
    // =====================================================================

    public function test_eloquent_devuelve_el_id_real_y_no_el_de_la_auditoria(): void
    {
        // El trigger inserta en SYSAuditoria dentro del mismo INSERT: si el processor usara
        // @@IDENTITY, $modelo->Id traería el AuditId y find() sobre él fallaría.
        $modelo = new ReqProgramaTejido;
        $modelo->NoProduccion = self::ORDEN_PRUEBA;
        $modelo->SalonTejidoId = 'ZZ';
        $modelo->NoTelarId = '995';
        $modelo->TotalPedido = 1000;
        $modelo->EnProceso = 0;
        $modelo->save();

        $idReal = (int) DB::table('ReqProgramaTejido')->where('NoProduccion', self::ORDEN_PRUEBA)->value('Id');

        $this->assertSame($idReal, (int) $modelo->Id, 'save() devolvió un Id que no es el de la fila insertada.');
        $this->assertNotNull(ReqProgramaTejido::find($modelo->Id), 'El Id devuelto no existe en ReqProgramaTejido.');
    }

    public function test_dos_altas_seguidas_devuelven_el_id_de_su_propia_fila(): void
    {
        // Con @@IDENTITY las dos devolvían AuditIds consecutivos de SYSAuditoria: parecían
        // plausibles pero no correspondían a ninguna orden.
        $primero = $this->crearModelo(self::PREFIJO.'-1', '995', 901);
        $segundo = $this->crearModelo(self::PREFIJO.'-2', '996', 902);

        $this->assertNotSame((int) $primero->Id, (int) $segundo->Id);
        $this->assertSame(
            (int) DB::table('ReqProgramaTejido')->where('NoProduccion', self::PREFIJO.'-1')->value('Id'),
            (int) $primero->Id
        );
        $this->assertSame(
            (int) DB::table('ReqProgramaTejido')->where('NoProduccion', self::PREFIJO.'-2')->value('Id'),
            (int) $segundo->Id
        );
    }

    // =====================================================================
    // Aislamiento: nada más escribe en SYSAuditoria
    // =====================================================================

    public function test_ningun_otro_trigger_escribe_en_la_auditoria(): void
    {
        $triggers = DB::select(
            'SELECT o.name FROM sys.objects o'
            ." WHERE o.type = 'TR' AND OBJECT_DEFINITION(o.object_id) LIKE '%SYSAuditoria%'"
        );

        $this->assertCount(1, $triggers, 'Solo tr_ReqProgramaTejido_Audit debe escribir en SYSAuditoria.');
        $this->assertSame('tr_ReqProgramaTejido_Audit', $triggers[0]->name);
    }

    // =====================================================================
    // Utilidades
    // =====================================================================

    private function crearRegistro(?string $orden = null, string $telar = '995', int $posicion = 900): int
    {
        $orden ??= self::ORDEN_PRUEBA;

        DB::statement(
            'INSERT INTO ReqProgramaTejido (NoProduccion, SalonTejidoId, NoTelarId, TotalPedido, EnProceso, Posicion)'
            .' VALUES (?, ?, ?, ?, ?, ?)',
            [$orden, 'ZZ', $telar, 1000, 0, $posicion]
        );

        return (int) DB::table('ReqProgramaTejido')->where('NoProduccion', $orden)->value('Id');
    }

    private function crearModelo(string $orden, string $telar, int $posicion): ReqProgramaTejido
    {
        $modelo = new ReqProgramaTejido;
        $modelo->NoProduccion = $orden;
        $modelo->SalonTejidoId = 'ZZ';
        $modelo->NoTelarId = $telar;
        $modelo->TotalPedido = 1000;
        $modelo->EnProceso = 0;
        $modelo->Posicion = $posicion;
        $modelo->save();

        return $modelo;
    }

    private function limpiarRegistrosPrueba(): void
    {
        DB::table('ReqProgramaTejido')->where('NoProduccion', 'like', self::PREFIJO.'%')->delete();
        DB::table('ReqProgramaTejido')->where('Observaciones', self::PREFIJO)->delete();
    }

    private function sellarContexto(string $accion): void
    {
        DB::statement('EXEC dbo.sp_SetAppContext ?, ?, ?, ?', [1, 'Test Suite', '127.0.0.1', $accion]);
    }

    private function ultimoAuditId(): int
    {
        return (int) DB::selectOne('SELECT ISNULL(MAX(AuditId), 0) AS m FROM dbo.SYSAuditoria')->m;
    }

    private function auditoriaDesde(int $marca): array
    {
        return DB::select(
            'SELECT Accion, PK, UsuarioId, Usuario, IP, Detalle FROM dbo.SYSAuditoria'
            .' WHERE AuditId > ? ORDER BY AuditId',
            [$marca]
        );
    }
}
