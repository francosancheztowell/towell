// Flujo de piso a 768×1024: reportar paro → Solicitudes → Terminar → finalizar → Solicitudes.
// Uso: node flujo.mjs <etiqueta>   (capturas en $ARNES_DATOS/shots/<etiqueta>/flujo-*.png)
import { createRequire } from 'module';
import fs from 'fs';
const require = createRequire((process.env.NODE_GLOBAL ?? '/opt/node22/lib/node_modules') + '/');
const { chromium } = require('playwright');
const base = process.env.ARNES_URL ?? 'http://127.0.0.1:8123';
const datos = process.env.ARNES_DATOS ?? `${(await import('os')).tmpdir()}/towell-arnes`;
const out = `${datos}/shots/${process.argv[2] ?? 'flujo'}`;
fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const ctx = await browser.newContext({ viewport: { width: 768, height: 1024 }, hasTouch: true, isMobile: true });
const page = await ctx.newPage();
const errores = [];
page.on('console', (m) => { if (m.type() === 'error') errores.push(m.text()); });
page.on('pageerror', (e) => errores.push(e.message));
// Espera a que terminen las transiciones de color (transition-colors, 150 ms).
const foto = async (n) => { await page.waitForTimeout(400); await page.screenshot({ path: `${out}/flujo-${n}.png` }); };
// Dos taps seguidos en < 300 ms Chromium los toma como doble toque (zoom) y no manda click.
const tocar = async (sel) => { await page.waitForTimeout(500); await page.tap(sel); };

await page.goto(base + '/__login/1?to=/mantenimiento/nuevo-paro', { waitUntil: 'networkidle' });
await page.waitForFunction(() => !document.getElementById('maquina').disabled);
await page.selectOption('#maquina', 'Mc Coy 1');
await page.waitForFunction(() => document.getElementById('orden_trabajo').value !== '');
await page.selectOption('#tipo_falla', 'MECANICO');
await page.waitForFunction(() => !document.getElementById('falla').disabled);
await page.selectOption('#falla', { label: 'Rotura de hilo' });
const descripcion = await page.$eval('#descripcion', (s) => s.selectedOptions[0]?.textContent);
await page.fill('#obs', 'ruido en fileta');
await foto('1-alta');
await tocar('#btn-aceptar');
await page.waitForSelector('.swal2-popup');
const aviso = await page.textContent('.swal2-popup');
await foto('2-folio');
await page.waitForURL('**/mantenimiento/solicitudes', { timeout: 10000 });
await page.waitForSelector('tr.row-paro');
const folios = await page.$$eval('tr.row-paro td[data-col="Folio"]', (tds) => tds.map((t) => t.textContent));
await tocar('tr.row-paro:has(td[data-col="Folio"]:text-is("PF00042"))');
await foto('3-solicitudes');
await tocar('#btn-terminar-paro');
await page.waitForURL('**/mantenimiento/finalizar-paro?id=*');
await page.waitForFunction(() => document.getElementById('maquina').value !== '' && document.getElementById('atendio').options.length > 1);
await page.selectOption('#atendio', 'Mecánico Uno');
const turno = await page.inputValue('#turno');
await tocar('label[for="calidad-5"]');
const estrella5Visible = await page.$eval('label[for="calidad-5"]', (l) => { const r = l.getBoundingClientRect(); return r.right <= window.innerWidth && r.left >= 0; });
await foto('4-finalizar');
await tocar('#btn-aceptar');
await page.waitForSelector('.swal2-popup');
await page.waitForURL('**/mantenimiento/solicitudes', { timeout: 10000 });
await page.waitForSelector('#tbody-paros[aria-busy="false"]');
const activos = await page.$$eval('tr.row-paro td[data-col="Folio"]', (tds) => tds.map((t) => t.textContent));
await foto('5-solicitudes-despues');

const r = { descripcion, aviso: aviso?.replace(/\s+/g, ' ').trim(), foliosTrasAlta: folios, turnoAutollenado: turno, estrella5Visible, activosTrasCierre: activos, errores };
console.log(JSON.stringify(r, null, 2));
fs.writeFileSync(`${out}/flujo.json`, JSON.stringify(r, null, 2));
await browser.close();
if (errores.length) process.exit(1);
