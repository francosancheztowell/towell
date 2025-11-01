/**
 * ANÁLISIS DEL REGISTRO
 *
 * El registro proporcionado tiene esta estructura (separado por TAB):
 * 2766 (ID?)
 * SMIT (Salon?)
 * 320 (Telar)
 * 0
 * SMI 320 (Máquina)
 * 70 (Ancho?)
 * 8% (Eficiencia)
 * 400 (Velocidad)
 * O16 (?)
 * 10 (?)
 * Calendario Tej3
 * MB7304 (Clave?)
 * ...
 * 10000 (Total Pedido?)
 * 28/09/2025 00:00 (Fecha captura?)
 * 06/11/2025 03:04 (Fecha inicio)
 * 21/11/2025 06:23 (Fecha fin)
 *
 * RESULTADOS ESPERADOS:
 * Días Ef. = 15.14
 * Prod (Kg)/Día = 272.84
 * Std/Día = 826.79
 * Prod (Kg)/Día 2 = 217.99
 * Std (Toa/Hr) 100% = 43.06
 * Días Jornada = 16.67
 * Horas = 290.28
 * Std/Hr Efectivo = 27.52
 */

// Analizar: 15.14 horas en DÍAS es:
const diasEnFormatoDH = 15.14;
const diasEnteros = Math.floor(diasEnFormatoDH);
const horasDecimales = (diasEnFormatoDH - diasEnteros) * 100;  // 0.14 → 14 (horas)
const totalHorasEnFormatoDH = diasEnteros * 24 + horasDecimales;

console.log('🔍 ANÁLISIS DEL FORMATO d.HH');
console.log(`Valor: ${diasEnFormatoDH}`);
console.log(`Días: ${diasEnteros}`);
console.log(`Horas (parte decimal): ${horasDecimales}`);
console.log(`Total horas: ${diasEnteros} * 24 + ${horasDecimales} = ${totalHorasEnFormatoDH} horas`);

// Verificar con fechas
const fechaInicio = new Date("2025-11-06T03:04:00");
const fechaFinal = new Date("2025-11-21T06:23:00");
const diffMs = fechaFinal - fechaInicio;
const diffHoras = diffMs / (1000 * 60 * 60);
const diffDias = diffHoras / 24;

console.log('\n📅 CÁLCULO CON FECHAS');
console.log(`Inicio: ${fechaInicio.toISOString()}`);
console.log(`Final: ${fechaFinal.toISOString()}`);
console.log(`Diferencia ms: ${diffMs}`);
console.log(`Diferencia horas: ${diffHoras}`);
console.log(`Diferencia días: ${diffDias}`);
console.log(`En formato d.HH: ${Math.floor(diffDias)}.${Math.floor((diffDias % 1) * 100)}`);

// HIPÓTESIS: Las fechas podrían estar mal interpretadas
// O los valores esperados son para OTRO registro

console.log('\n💡 HIPÓTESIS: Comprobando si 15.14 corresponde a otro tipo de cálculo');

// Si 15.14 es días.horas en formato Excel:
// 15 días + 14 horas = 15 * 24 + 14 = 374 horas
console.log(`15 días + 14 horas = ${15*24 + 14} horas`);

// O tal vez es 15.14 DÍAS (decimal normal)
console.log(`15.14 días en horas = ${15.14 * 24} horas`);

// O quizás el registro tiene datos diferentes
console.log('\n⚠️ VERIFICACIÓN: Los valores esperados pueden ser de otro registro');
console.log('Necesitamos verificar:');
console.log('1. Que el registro sea el correcto');
console.log('2. Que los datos del modelo estén correctos');
console.log('3. Que las fechas de inicio/fin sean correctas');
