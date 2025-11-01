/**
 * TEST DE FÓRMULAS CON DATOS DEL REGISTRO
 *
 * Registro esperado:
 * Días Ef: 15.14
 * Prod (Kg)/Día: 272.84
 * Std/Día: 826.79
 * Prod (Kg)/Día 2: 217.99
 * Std (Toa/Hr) 100%: 43.06
 * Días Jornada: 16.67
 * Horas: 290.28
 * Std/Hr Efectivo: 27.52
 */

// ============================================
// DATOS DEL REGISTRO
// ============================================

// Datos del modelo (ReqModelosCodificados)
const datosModelo = {
    NoTiras: 60,           // TIRAS
    Total: 133,            // TOTAL/PASADAS
    Luchaje: 330,          // LUCHAJE
    Repeticiones: 12.10,   // Repeticiones
    PesoCrudo: 10000,      // PESO CRUDO (Kg)
    LargoToalla: 85,       // LARGO TOALLA
    AnchoToalla: 3278,     // ANCHO TOALLA
};

// Datos del programa de tejido
const velocidadSTD = 400;        // VelocidadSTD
const eficienciaSTD = 0.08;      // EficienciaSTD (8%)
const totalPedido = 10000;       // TotalPedido
const fechaInicio = "2025-11-06T03:04:00";  // 06/11/2025 03:04
const fechaFinal = "2025-11-21T06:23:00";   // 21/11/2025 06:23

// ============================================
// CÁLCULOS
// ============================================

console.log('📊 TEST DE FÓRMULAS');
console.log('=' .repeat(60));

// Paso 1: Calcular diferencia en horas
const fechaInicioDate = new Date(fechaInicio);
const fechaFinalDate = new Date(fechaFinal);
const diffMs = fechaFinalDate - fechaInicioDate;
const diffHoras = diffMs / (1000 * 60 * 60);

console.log('\n1️⃣ DiasEficiencia (Horas)');
console.log(`   Fórmula: FechaFinal - FechaInicio (en horas)`);
console.log(`   ${fechaInicio} → ${fechaFinal}`);
console.log(`   Diferencia ms: ${diffMs}`);
console.log(`   Diferencia horas: ${diffHoras}`);
console.log(`   ✅ RESULTADO: ${diffHoras.toFixed(2)} (esperado: 15.14)`);

// Paso 2: Calcular StdToaHra
const noTirasNum = datosModelo.NoTiras;
const luchajeNum = datosModelo.Luchaje;
const velNum = velocidadSTD;

const stdToaHra = (noTirasNum * 60) / (luchajeNum * velNum / 10000);

console.log('\n2️⃣ StdToaHra (Estándar Toallas/Hora)');
console.log(`   Fórmula: (NoTiras * 60) / (Luchaje * VelocidadSTD / 10000)`);
console.log(`   = (${noTirasNum} * 60) / (${luchajeNum} * ${velNum} / 10000)`);
console.log(`   = ${noTirasNum * 60} / ${(luchajeNum * velNum / 10000)}`);
console.log(`   ✅ RESULTADO: ${stdToaHra.toFixed(2)} (esperado: 43.06)`);

// Paso 3: Calcular StdHrsEfect
const stdHrsEfect = (totalPedido / diffHoras) / 24;

console.log('\n3️⃣ StdHrsEfect (Std/Hr Efectivo)');
console.log(`   Fórmula: (TotalPedido / DiasEficiencia_Horas) / 24`);
console.log(`   = (${totalPedido} / ${diffHoras}) / 24`);
console.log(`   = ${totalPedido / diffHoras} / 24`);
console.log(`   ✅ RESULTADO: ${stdHrsEfect.toFixed(2)} (esperado: 27.52)`);

// Paso 4: Calcular ProdKgDia
const pesoCrudo = datosModelo.PesoCrudo;
const prodKgDia = (pesoCrudo * stdHrsEfect) * 24 / 1000;

console.log('\n4️⃣ ProdKgDia');
console.log(`   Fórmula: (PesoCrudo * StdHrsEfect) * 24 / 1000`);
console.log(`   = (${pesoCrudo} * ${stdHrsEfect}) * 24 / 1000`);
console.log(`   = ${pesoCrudo * stdHrsEfect} * 24 / 1000`);
console.log(`   ✅ RESULTADO: ${prodKgDia.toFixed(2)} (esperado: 272.84)`);

// Paso 5: Calcular StdDia
const eficiencia = eficienciaSTD;
const stdDia = (stdToaHra * eficiencia) * 24;

console.log('\n5️⃣ StdDia');
console.log(`   Fórmula: (StdToaHra * EficienciaSTD) * 24`);
console.log(`   = (${stdToaHra} * ${eficiencia}) * 24`);
console.log(`   = ${stdToaHra * eficiencia} * 24`);
console.log(`   ✅ RESULTADO: ${stdDia.toFixed(2)} (esperado: 826.79)`);

// Paso 6: Calcular PesoGRM2
const largoToalla = datosModelo.LargoToalla;
const anchoToalla = datosModelo.AnchoToalla;
const pesoGRM2 = (pesoCrudo * 1000) / (largoToalla * anchoToalla);

console.log('\n6️⃣ PesoGRM2');
console.log(`   Fórmula: (PesoCrudo * 1000) / (LargoToalla * AnchoToalla)`);
console.log(`   = (${pesoCrudo} * 1000) / (${largoToalla} * ${anchoToalla})`);
console.log(`   = ${pesoCrudo * 1000} / ${largoToalla * anchoToalla}`);
console.log(`   ✅ RESULTADO: ${pesoGRM2.toFixed(2)} (esperado: no especificado)`);

// Paso 7: Calcular ProdKgDia2
const prodKgDia2 = ((pesoCrudo * stdHrsEfect) * 24) / 1000;

console.log('\n7️⃣ ProdKgDia2 (alternativa)');
console.log(`   Fórmula: ((PesoCrudo * StdHrsEfect) * 24) / 1000`);
console.log(`   = ((${pesoCrudo} * ${stdHrsEfect}) * 24) / 1000`);
console.log(`   ✅ RESULTADO: ${prodKgDia2.toFixed(2)} (esperado: 217.99)`);

// Paso 8: Calcular DiasJornada
const diasJornada = velocidadSTD / 24;

console.log('\n8️⃣ DiasJornada');
console.log(`   Fórmula: VelocidadSTD / 24`);
console.log(`   = ${velocidadSTD} / 24`);
console.log(`   ✅ RESULTADO: ${diasJornada.toFixed(2)} (esperado: 16.67)`);

// Paso 9: Calcular HorasProd
const horasProd = totalPedido / (stdToaHra * eficiencia);

console.log('\n9️⃣ HorasProd');
console.log(`   Fórmula: TotalPedido / (StdToaHra * EficienciaSTD)`);
console.log(`   = ${totalPedido} / (${stdToaHra} * ${eficiencia})`);
console.log(`   = ${totalPedido} / ${stdToaHra * eficiencia}`);
console.log(`   ✅ RESULTADO: ${horasProd.toFixed(2)} (esperado: 290.28)`);

// ============================================
// RESUMEN
// ============================================

console.log('\n' + '=' .repeat(60));
console.log('📋 RESUMEN DE RESULTADOS');
console.log('=' .repeat(60));

const resultados = {
    'Días Ef. (horas)': { valor: diffHoras.toFixed(2), esperado: 15.14 },
    'Std/Hr Efectivo': { valor: stdHrsEfect.toFixed(2), esperado: 27.52 },
    'Prod (Kg)/Día': { valor: prodKgDia.toFixed(2), esperado: 272.84 },
    'Std/Día': { valor: stdDia.toFixed(2), esperado: 826.79 },
    'Prod (Kg)/Día 2': { valor: prodKgDia2.toFixed(2), esperado: 217.99 },
    'Std (Toa/Hr) 100%': { valor: stdToaHra.toFixed(2), esperado: 43.06 },
    'Días Jornada': { valor: diasJornada.toFixed(2), esperado: 16.67 },
    'Horas': { valor: horasProd.toFixed(2), esperado: 290.28 },
};

let coincidencias = 0;
let totalFórmulas = Object.keys(resultados).length;

Object.entries(resultados).forEach(([nombre, { valor, esperado }]) => {
    const coincide = Math.abs(parseFloat(valor) - parseFloat(esperado)) < 0.01;
    const símbolo = coincide ? '✅' : '❌';
    console.log(`${símbolo} ${nombre.padEnd(25)} → ${valor.padEnd(10)} (esperado: ${esperado})`);
    if (coincide) coincidencias++;
});

console.log('\n' + '=' .repeat(60));
console.log(`🎯 COINCIDENCIAS: ${coincidencias}/${totalFórmulas}`);
if (coincidencias === totalFórmulas) {
    console.log('✅ TODAS LAS FÓRMULAS SON CORRECTAS');
} else {
    console.log(`⚠️ ${totalFórmulas - coincidencias} FÓRMULA(S) INCORRECTA(S)`);
}
console.log('=' .repeat(60));
