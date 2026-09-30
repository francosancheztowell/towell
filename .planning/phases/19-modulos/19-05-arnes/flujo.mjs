// Flujo completo de Programa Urd/Eng con Playwright, paso a paso, con captura y consola por paso.
// Uso: node flujo.mjs <etiqueta>      (ARNES_URL, ARNES_SALIDA, ANCHO/ALTO como en shoot.mjs)
// Pasos: reservar un julio al telar 203 → marcar 201 y 202 (Rizo) → Programar → programación de
// requerimientos (hilo, tamaño, destino) → Siguiente → creación de órdenes (fila, destino, BOM,
// material, construcción, datos de engomado) → Crear Órdenes → confirmar fecha → folio.
// Cada paso deja <n>-<paso>.png y flujo.json ({paso, ok, detalle, errores, http}).
// El flujo escribe en la BD del arnés: vuelve a correr setup.php antes de repetirlo.
import { createRequire } from 'module';
import fs from 'fs';
import os from 'os';

const require = createRequire((process.env.NODE_GLOBAL ?? '/opt/node22/lib/node_modules') + '/');
const { chromium } = require('playwright');

const etiqueta = process.argv[2] ?? 'antes';
const base = process.env.ARNES_URL ?? 'http://127.0.0.1:8125';
const datos = process.env.ARNES_DATOS ?? `${os.tmpdir()}/towell-arnes-1905`;
const out = process.env.ARNES_SALIDA ?? `${datos}/capturas/flujo-${etiqueta}`;
fs.mkdirSync(out, { recursive: true });

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const ctx = await browser.newContext({
  viewport: { width: Number(process.env.ANCHO ?? 1280), height: Number(process.env.ALTO ?? 800) },
  locale: 'es-MX',
  timezoneId: 'America/Mexico_City',
});
const page = await ctx.newPage();
let errores = [];
let http = [];
page.on('console', (m) => { if (m.type() === 'error') errores.push('console: ' + m.text()); });
page.on('pageerror', (e) => errores.push('pageerror: ' + e.message));
page.on('response', (r) => { if (r.status() >= 400) http.push(`${r.status()} ${r.request().method()} ${r.url().replace(base, '')}`.slice(0, 300)); });

const reporte = [];
let n = 0;
let abortar = false;
async function paso(nombre, fn) {
  if (abortar) { reporte.push({ paso: nombre, ok: false, detalle: 'omitido: falló un paso anterior' }); return; }
  n++;
  errores = []; http = [];
  let ok = true;
  let detalle = '';
  try {
    detalle = (await fn()) ?? '';
  } catch (e) {
    ok = false;
    detalle = e.message.split('\n')[0];
  }
  await page.waitForTimeout(400);
  await page.screenshot({ path: `${out}/${String(n).padStart(2, '0')}-${nombre}.png` }).catch(() => {});
  reporte.push({ paso: nombre, ok, detalle, url: page.url().replace(base, '').slice(0, 120), errores, http });
  console.log(`${ok ? 'ok ' : 'ERR'} ${nombre} ${detalle} ${errores.length || http.length ? '| ' + [...errores, ...http].join(' || ').slice(0, 300) : ''}`);
  if (!ok) abortar = true;
}
const swalTexto = async () => (await page.locator('.swal2-popup').first().innerText().catch(() => '')).replace(/\s+/g, ' ').trim();
const cerrarSwal = async () => {
  const b = page.locator('.swal2-confirm:visible');
  if (await b.count()) await b.first().click();
};
/** Fila de telares por número y tipo. */
const filaTelar = (no, tipo) => page.locator('#telaresTable tbody tr.selectable-row')
  .filter({ has: page.locator('td', { hasText: new RegExp(`^\\s*${no}\\s*$`) }) })
  .filter({ hasText: tipo });

await page.goto(base + '/__login/1');

await paso('reservar-abrir', async () => {
  await page.goto(base + '/programaurdeng', { waitUntil: 'networkidle' });
  return `${await page.locator('#telaresTable tbody tr.selectable-row').count()} telares`;
});

await paso('reservar-julio-203', async () => {
  await filaTelar('203', 'Rizo').first().click();
  await page.waitForLoadState('networkidle');
  const inv = page.locator('#inventarioTable tbody tr.selectable-row-inventario').filter({ hasText: 'J-502' });
  await inv.first().click();
  await page.locator('#btnReservar').click();
  await page.locator('.swal2-confirm').click(); // "Si, reservar"
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(800);
  const txt = await swalTexto();
  await cerrarSwal();
  return txt || 'sin mensaje';
});

await paso('marcar-201-202', async () => {
  await page.goto(base + '/programaurdeng', { waitUntil: 'networkidle' });
  for (const no of ['201', '202']) await filaTelar(no, 'Rizo').first().locator('.telar-checkbox').check();
  return 'marcados';
});

await paso('programar', async () => {
  await Promise.all([page.waitForURL(/programacion-requerimientos/, { timeout: 15000 }), page.locator('#btnProgramar').click()]);
  await page.waitForLoadState('networkidle');
  return `${await page.locator('#tbodyRequerimientos tr').count()} filas`;
});

await paso('requerimientos-llenar', async () => {
  // Orden importa: el tamaño llena cuenta/calibre por código (sin evento) y #btnSiguiente solo se
  // reevalúa en input/change de la tabla; por eso el hilo va DESPUÉS del tamaño (en el "antes",
  // elegir hilo y luego tamaño deja el botón deshabilitado hasta tocar otro campo).
  const fila = page.locator('#tbodyRequerimientos tr').first();
  const tam = fila.locator('input[data-field="tamano"]');
  await tam.fill('3040');
  await page.locator('.tamano-dropdown div[data-value="3040-12.5/1"]').first().click({ timeout: 8000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500); // guarda tamaño, cuenta y calibre (POST actualizar-telar)
  const hilo = fila.locator('select[data-field="hilo"]');
  await hilo.selectOption('A12');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(800); // el cambio de hilo recalcula el resumen de semanas
  const destino = fila.locator('select[data-field="destino"]');
  if (await destino.count() && !(await destino.inputValue())) await destino.selectOption({ index: 1 });
  const v = async (sel) => fila.locator(sel).inputValue().catch(() => '-');
  return `tamaño=${await v('input[data-field="tamano"]')} cuenta=${await v('input[data-field="cuenta"]')} calibre=${await v('input[data-field="calibre"]')} hilo=${await v('select[data-field="hilo"]')} metros=${await v('input[data-field="metros"]')} kilos=${await v('input[data-field="kilos"]')} destino=${await v('select[data-field="destino"]')}`;
});

await paso('siguiente', async () => {
  if (await page.locator('#btnSiguiente').isDisabled()) throw new Error('#btnSiguiente deshabilitado (faltan campos)');
  await Promise.all([page.waitForURL(/creacion-ordenes/, { timeout: 15000 }), page.locator('#btnSiguiente').click()]);
  await page.waitForLoadState('networkidle');
  return decodeURIComponent(new URL(page.url()).searchParams.get('telares') ?? '').slice(0, 200);
});

await paso('creacion-fila-bom', async () => {
  const fila = page.locator('[data-bom-input="true"]').first().locator('xpath=ancestor::tr');
  await fila.click();
  const destino = fila.locator('[data-destino-select="true"]');
  if (await destino.count()) await destino.selectOption('Jacquard Smit');
  const bom = fila.locator('[data-bom-input="true"]');
  await bom.fill('URD 30');
  await page.locator('#bom-suggestions-global div').first().click({ timeout: 8000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(800);
  return `${await page.locator('.checkbox-material').count()} materiales de engomado`;
});

await paso('creacion-material-y-datos', async () => {
  await page.locator('.checkbox-material').first().check();
  const c = page.locator('#tbodyConstruccionUrdido tr').first().locator('input');
  await c.nth(0).fill('3');
  await c.nth(1).fill('640');
  await page.locator('#inputNucleo').selectOption({ index: 1 });
  await page.locator('#inputNoTelas').fill('2');
  if (!(await page.locator('#inputAnchoBalonas').inputValue())) await page.locator('#inputAnchoBalonas').selectOption({ index: 1 });
  await page.locator('#inputCuendeadosMin').fill('2');
  if (!(await page.locator('#inputMaquinaEngomado').inputValue())) await page.locator('#inputMaquinaEngomado').selectOption({ index: 1 });
  await page.locator('#inputLMatEngomado').fill('ENG 3040-A12');
  await page.locator('#inputLMatEngomado').press('Tab');
  if (!(await page.locator('#inputBomFormula').inputValue())) await page.locator('#inputBomFormula').selectOption({ index: 1 });
  return `metraje=${await page.locator('#inputMetrajeTelas').inputValue()} formula=${await page.locator('#inputBomFormula').inputValue()}`;
});

await paso('crear-ordenes', async () => {
  await page.locator('#btn-crear-ordenes').click();
  const pregunta = await swalTexto();
  if (!/requiere el material/i.test(pregunta)) throw new Error('Swal inesperado: ' + pregunta.slice(0, 200));
  await page.locator('.swal2-confirm').click();
  await page.waitForLoadState('networkidle');
  await page.locator('.swal2-popup').filter({ hasText: /Folio|Error/ }).first().waitFor({ timeout: 15000 });
  const txt = await swalTexto();
  if (!/Folio/.test(txt)) throw new Error(txt.slice(0, 200));
  return txt.slice(0, 200);
});

await paso('volver-a-reservar', async () => {
  await cerrarSwal();
  await page.waitForURL(/reservar-programar|programaurdeng/, { timeout: 10000 });
  await page.waitForLoadState('networkidle');
  return (await filaTelar('201', 'Rizo').first().innerText()).replace(/\s+/g, ' ');
});

fs.writeFileSync(`${out}/flujo.json`, JSON.stringify(reporte, null, 2));
await browser.close();
console.log(`→ ${out}`);
