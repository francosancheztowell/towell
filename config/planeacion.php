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
| 'superficies' es la matriz de capacidades de la fase PT-01 (01.2). Desde
| PT-02 la lee App\Services\Planeacion\ProgramaTejido\ProgramaTejidoSurface
| (guards 422 de capacidades B, módulo de permiso, UI de Muestras) además de
| los tests y el comando planeacion:programa-tejido-health. Fuente de los datos:
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
                'longitudes' => true,
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
            // Decisión 01.3 aprobada por el owner (01-DECISION-PROGRAMA-MUESTRAS.md §5).
            // true = alternativa A (paridad física aditiva vía database/sql/pt_muestras_*.sql,
            // pendiente de que el DBA la aplique: mientras, columnas_ausentes sigue igual);
            // false = alternativa B (exclusiva de Programa: el backend responde 422 y la UI
            // de Muestras oculta la acción).
            'capacidades' => [
                'redbooth' => false,
                'marbetes' => true,
                'produccion' => true,
                'descarga' => false,
                'finalizacion' => false,
                'longitudes' => true,
            ],
        ],

    ],

    /*
    | Lectura v2 (PT-02 · 02.3/02.4). Apagada por default: con el flag en false el
    | endpoint v2 responde 404 y la pantalla sigue siendo exactamente la legacy.
    | shadow_sample: fracción (0..1) de cargas legacy que se comparan contra v2
    | después de responder; 0 = nunca.
    */
    'read_v2' => [
        'programa' => (bool) env('PLANEACION_PROGRAMA_READ_V2', false),
        'muestras' => (bool) env('PLANEACION_MUESTRAS_READ_V2', false),
        'shadow_sample' => (float) env('PLANEACION_READ_V2_SHADOW_SAMPLE', 0),
    ],

];
