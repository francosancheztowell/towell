<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Exportador del archivo OEE_ATADORES.xlsx (hoja DETALLE)
    |--------------------------------------------------------------------------
    |
    | "python" ejecuta scripts/oee_export.py (openpyxl + pyodbc). Requiere
    | Python 3, pip install -r scripts/requirements.txt y ODBC Driver for SQL Server.
    |
    */
    'export_driver' => env('OEE_EXPORT_DRIVER', 'python'),

    'python_binary' => env('OEE_PYTHON_BINARY', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'),

    'python_timeout' => (int) env('OEE_PYTHON_TIMEOUT', 900),

    'script_path' => env('OEE_PYTHON_SCRIPT', base_path('scripts/oee_export.py')),

    'database_connection' => env('OEE_EXPORT_DB_CONNECTION', 'sqlsrv'),

];
