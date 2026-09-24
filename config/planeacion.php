<?php

/*
|--------------------------------------------------------------------------
| Planeación · Programa Tejido
|--------------------------------------------------------------------------
|
| Ojo: 'programa_tejido_table' y 'programa_tejido_line_table' NO se declaran
| aquí a propósito. Los pone ProgramaTejidoContext por request (rutas de
| Muestras) y ReqProgramaTejido::getTable() cae a su $table cuando faltan.
|
| 'superficies' es la matriz de capacidades de la fase PT-01 (01.2). Hoy
| ningún código de runtime la lee: la consumen los tests de caracterización
| y el comando planeacion:programa-tejido-health. Fuente de los datos:
| research live del 2026-07-22 (.planning/phases/01-guardrails/01-RESEARCH.md).
| Un null significa "dato físico pendiente": se llena con la salida de
| .planning/phases/01-guardrails/sql/01-schema-fisico.sql, nunca a ojo.
|
*/

return [

    'superficies' => [

        'programa' => [
            'tabla' => 'ReqProgramaTejido',
            'tabla_lineas' => 'ReqProgramaTejidoLine',
            'modulo_permiso' => 2, // SYSRoles: Programa Tejido
            'columnas_ausentes' => [],
            // Longitudes físicas que difieren de StringTruncator (vacío = coinciden).
            'longitudes' => [],
            'capacidades' => [
                'redbooth' => true,
                'marbetes' => true,
                'produccion' => true,
                'descarga' => true,
                'finalizacion' => true,
            ],
        ],

        'muestras' => [
            'tabla' => 'MuestrasPrograma',
            'tabla_lineas' => 'MuestrasProgramaLine',
            'modulo_permiso' => 5, // SYSRoles: Muestras
            // Existen en ReqProgramaTejido y no en MuestrasPrograma (135 vs 141 columnas).
            'columnas_ausentes' => [
                'NoMarbete',
                'RollosProgramados',
                'ProdId',
                'ProduccionMarbetes',
                'IdRedbooth',
                'NombreRedbooth',
            ],
            // Columnas más cortas en MuestrasPrograma. Valor = longitud física en
            // caracteres; null = pendiente de RS2 del script 01-schema-fisico.sql.
            'longitudes' => [
                'CalendarioId' => null,
                'FlogsId' => null,
                'NombreProyecto' => null,
                'CustName' => null,
                'AplicacionId' => null,
                'Observaciones' => null,
                'ColorTrama' => null,
                'Prioridad' => null,
                'CombinaTram' => null,
                'BomId' => null,
                'BomName' => null,
            ],
            // null = pendiente de la decisión 01.3 (01-DECISION-PROGRAMA-MUESTRAS.md).
            // true = alternativa A (paridad física), false = alternativa B (exclusiva de Programa).
            'capacidades' => [
                'redbooth' => null,
                'marbetes' => null,
                'produccion' => null,
                'descarga' => null,
                'finalizacion' => null,
            ],
        ],

    ],

];
