/**
 * TEST DE FÓRMULAS CON DATOS REALES DEL REGISTRO ID 154
 */

// ============================================
// DATOS DEL REGISTRO REAL
// ============================================

const datos = {
    // Modelo (ReqModelosCodificados)
    NoTiras: 3,
    Total: 60,           // Este es PASADAS en el header
    Luchaje: 133,
    Repeticiones: 330,   // Espera... esto no concuerda. En el código dice PasadasComb1 = NULL
    PesoCrudo: 12.1,     // Este parece ser CalibreTrama, no PesoCrudo
    LargoToalla: 85,     // MedidaPlano
    AnchoToalla: 3278,

    // Programa
    VelocidadSTD: 400,
    EficienciaSTD: 0.8,
    TotalPedido: 21000,

    // Fechas
    FechaInicio: "2025-10-04T05:26:57.000Z",
    FechaFinal: "2025-11-06T05:37:01.000Z",
};

// VALORES ESPERADOS DEL REGISTRO
const esperado = {
    PesoGRM2: 33.007,
    DiasEficiencia: 272.8416,   // ⚠️ Este valor parece estar MAL en la tabla
    ProdKgDia: 826.7927,
    StdDia: 209.9555,
    ProdKgDia2: 43.0621,
    StdToaHra: 16.6667,
    DiasJornada: 609.5845,
    HorasProd: 26.5095,
    StdHrsEfect: 664,
};

console.log('📊 ANÁLISIS DEL REGISTRO REAL');
console.log('=' .repeat(80));

// El problema es que los datos en la tabla están en orden diferente
// Déjame reorganizar según la estructura correcta

console.log('\n⚠️ PROBLEMA IDENTIFICADO:');
console.log('Los valores guardados en la BD parece que están en orden incorrecto');
console.log('o mapean a columnas diferentes de lo esperado.');
console.log('\nValores salvados en BD:');
console.log(`PesoGRM2: 33.007`);
console.log(`DiasEficiencia: 272.8416 ← ESTO NO PUEDE SER CORRECTO (son demasiadas horas)`);
console.log(`ProdKgDia: 826.7927`);
console.log(`StdDia: 209.9555`);
console.log(`ProdKgDia2: 43.0621`);
console.log(`StdToaHra: 16.6667`);
console.log(`DiasJornada: 609.5845`);
console.log(`HorasProd: 26.5095`);
console.log(`StdHrsEfect: 664`);

// Calcular correctamente
const fechaInicioDate = new Date(datos.FechaInicio);
const fechaFinalDate = new Date(datos.FechaFinal);
const diffMs = fechaFinalDate - fechaInicioDate;
const diffHoras = diffMs / (1000 * 60 * 60);

console.log('\n' + '=' .repeat(80));
console.log('CÁLCULOS CORRECTOS:');
console.log('=' .repeat(80));

console.log(`\n📅 Diferencia en horas`);
console.log(`Inicio: ${datos.FechaInicio}`);
console.log(`Final: ${datos.FechaFinal}`);
console.log(`Total horas: ${diffHoras.toFixed(4)}`);

// Aquí está el problema - necesitamos ver qué valores están siendo guardados
// y en qué orden

console.log('\n❓ INVESTIGACIÓN: Mapeando valores salvados a columnas');
console.log(`¿33.007 es PesoGRM2? Verificar: (12.1 * 1000) / (85 * 3278) = ${(12.1 * 1000) / (85 * 3278)}`);
console.log(`¿272.8416 es DiasEficiencia? Verificar: ${diffHoras.toFixed(4)} horas`);

// Los valores están invertidos o mal mapeados
// Déjame verificar si están en otro orden

console.log('\n🔍 HIPÓTESIS: Los valores podrían estar salvándose en orden diferente');
console.log(`Quizás: DiasEficiencia → 272.8416 es en realidad otro campo?`);
console.log(`¿O es que PesoCrudo es diferente?`);

// NECESARIO: Verificar en la DB directamente cuáles son los datos del modelo
