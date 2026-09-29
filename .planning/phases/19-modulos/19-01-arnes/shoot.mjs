// Uso: node shoot.mjs <etiqueta> <archivo-de-urls>  → capturas 768x1024 + errores de consola por pantalla
import { createRequire } from 'module';
import fs from 'fs';
const require = createRequire((process.env.NODE_GLOBAL ?? '/opt/node22/lib/node_modules') + '/');
const { chromium } = require('playwright');
const [,, etiqueta, lista] = process.argv;
const base = process.env.ARNES_URL ?? 'http://127.0.0.1:8123';
const datos = process.env.ARNES_DATOS ?? `${(await import('os')).tmpdir()}/towell-arnes`;
const out = `${datos}/shots/${etiqueta}`;
fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const ctx = await browser.newContext({ viewport: { width: 768, height: 1024 } });
const page = await ctx.newPage();
await page.goto(base + '/__login/1');
const reporte = [];
for (const linea of fs.readFileSync(lista, 'utf8').split('\n').filter(Boolean)) {
  const [nombre, url, pasos] = linea.split('|').map(s => s.trim());
  const errores = [];
  const onC = m => { if (m.type() === 'error') errores.push('console: ' + m.text()); };
  const onE = e => errores.push('pageerror: ' + e.message);
  page.on('console', onC); page.on('pageerror', onE);
  let status = 0;
  try {
    const r = await page.goto(base + url, { waitUntil: 'networkidle' });
    status = r?.status() ?? 0;
    if (pasos) await new Function('page', `return (async () => { ${pasos} })()`)(page);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${out}/${nombre}.png`, fullPage: false });
  } catch (e) { errores.push('driver: ' + e.message.split('\n')[0]); }
  page.off('console', onC); page.off('pageerror', onE);
  reporte.push({ nombre, url, status, errores });
  console.log(`${status} ${nombre} ${errores.length ? 'ERR ' + errores.join(' || ').slice(0, 400) : 'ok'}`);
}
fs.writeFileSync(`${out}/reporte.json`, JSON.stringify(reporte, null, 2));
await browser.close();
