<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Planeacion\ReqProgramaTejido;

$telarId = $argv[1] ?? '202';

echo "=== Registros del telar $telarId ===\n\n";

$registros = ReqProgramaTejido::where('NoTelarId', $telarId)
    ->select('Id', 'NoProduccion', 'EnProceso')
    ->orderBy('Id')
    ->get();

foreach ($registros as $r) {
    $enProcesoVal = $r->EnProceso;
    $enProcesoTipo = gettype($enProcesoVal);
    $enProcesoStr = var_export($enProcesoVal, true);
    
    echo "Id: {$r->Id} | NoProduccion: {$r->NoProduccion} | EnProceso: {$enProcesoStr} (tipo: {$enProcesoTipo})\n";
}

echo "\n=== Simulando consulta de obtenerProducciones ===\n";
$producciones = ReqProgramaTejido::where('NoTelarId', $telarId) 
    ->where(function ($query) {
        $query->whereNull('EnProceso')
            ->orWhere('EnProceso', 0)
            ->orWhere('EnProceso', false);
    })
    ->whereNotNull('NoProduccion')
    ->where('NoProduccion', '!=', '')
    ->select('Id', 'SalonTejidoId', 'NoProduccion', 'EnProceso')
    ->distinct()
    ->orderBy('Id', 'asc')
    ->get();

echo "Producciones que DEBERIAN aparecer (EnProceso = 0/null/false):\n";
foreach ($producciones as $p) {
    echo "Id: {$p->Id} | NoProduccion: {$p->NoProduccion} | EnProceso: " . var_export($p->EnProceso, true) . "\n";
}

echo "\n=== SQL generado ===\n";
$sql = ReqProgramaTejido::where('NoTelarId', $telarId) 
    ->where(function ($query) {
        $query->whereNull('EnProceso')
            ->orWhere('EnProceso', 0)
            ->orWhere('EnProceso', false);
    })
    ->whereNotNull('NoProduccion')
    ->where('NoProduccion', '!=', '')
    ->toSql();
echo $sql . "\n";
