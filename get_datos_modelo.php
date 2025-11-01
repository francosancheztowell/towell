<?php

// Cargar la aplicación Laravel
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Buscar el modelo con Clave MB7304
$modelo = \DB::table('ReqModelosCodificados')
    ->where('ClaveModelo', 'MB7304')
    ->first();

if ($modelo) {
    echo "✅ MODELO ENCONTRADO (MB7304):\n";
    echo "NoTiras: " . $modelo->NoTiras . "\n";
    echo "Total: " . $modelo->Total . "\n";
    echo "Luchaje: " . $modelo->Luchaje . "\n";
    echo "Repeticiones: " . $modelo->Repeticiones . "\n";
    echo "PesoCrudo: " . $modelo->PesoCrudo . "\n";
    echo "LargoToalla: " . $modelo->LargoToalla . "\n";
    echo "AnchoToalla: " . $modelo->AnchoToalla . "\n";
} else {
    echo "❌ MODELO NO ENCONTRADO\n";
}

// También obtener el registro ID 154
$registro = \DB::table('ReqProgramaTejido')->where('Id', 154)->first();
if ($registro) {
    echo "\n✅ REGISTRO ID 154 ENCONTRADO:\n";
    echo "FechaInicio: " . $registro->FechaInicio . "\n";
    echo "FechaFinal: " . $registro->FechaFinal . "\n";
    echo "VelocidadSTD: " . $registro->VelocidadSTD . "\n";
    echo "EficienciaSTD: " . $registro->EficienciaSTD . "\n";
    echo "TotalPedido: " . $registro->TotalPedido . "\n";
    echo "PesoGRM2: " . $registro->PesoGRM2 . "\n";
    echo "DiasEficiencia: " . $registro->DiasEficiencia . "\n";
    echo "ProdKgDia: " . $registro->ProdKgDia . "\n";
    echo "StdDia: " . $registro->StdDia . "\n";
    echo "ProdKgDia2: " . $registro->ProdKgDia2 . "\n";
    echo "StdToaHra: " . $registro->StdToaHra . "\n";
    echo "DiasJornada: " . $registro->DiasJornada . "\n";
    echo "HorasProd: " . $registro->HorasProd . "\n";
    echo "StdHrsEfect: " . $registro->StdHrsEfect . "\n";
}
