<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Acceso
    |--------------------------------------------------------------------------
    |
    | Reutiliza el módulo Nivel 1 "Ventas" que ya existe en SYSRoles
    | (idrol 204, Ruta /ventas). No hace falta crear un submódulo nuevo:
    | quien ya tiene acceso a Ventas ve este dashboard directamente.
    |
    */
    'permission_module' => env('VENTAS_DASHBOARD_PERMISSION_MODULE', 'Ventas'),
];
