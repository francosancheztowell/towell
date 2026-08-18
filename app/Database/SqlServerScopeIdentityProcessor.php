<?php

namespace App\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\SqlServerProcessor;

/**
 * Devuelve el Id recién insertado usando SCOPE_IDENTITY() en lugar de @@IDENTITY.
 *
 * El processor de Laravel llama a PDO::lastInsertId(), que en pdo_sqlsrv resuelve
 *
 * @@IDENTITY: el último identity de la SESIÓN, incluyendo el que generan los triggers.
 * Con tr_ReqProgramaTejido_Audit activo, un INSERT en ReqProgramaTejido devolvía el
 * AuditId de SYSAuditoria en vez del Id real (medido: devolvía 1 en vez de 620), y de ahí
 * salían los ModelNotFoundException al borrar o releer un registro recién creado.
 *
 * SCOPE_IDENTITY() ignora lo que inserte el trigger porque es de otro ámbito, pero solo
 * responde dentro del mismo batch: por eso el SELECT viaja pegado al INSERT y no como
 * una consulta aparte (una segunda llamada devolvería NULL).
 */
class SqlServerScopeIdentityProcessor extends SqlServerProcessor
{
    /**
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int|string
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $connection = $query->getConnection();

        $connection->recordsHaveBeenModified();

        $result = $connection->selectFromWriteConnection(
            'set nocount on; '.$sql.'; select scope_identity() as id',
            $values
        );

        $id = $result[0]->id ?? null;

        return is_numeric($id) ? (int) $id : $id;
    }
}
