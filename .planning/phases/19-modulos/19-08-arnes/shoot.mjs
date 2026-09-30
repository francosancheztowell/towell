// Uso: node shoot.mjs <etiqueta> <archivo-de-urls>
// Como 19-01-arnes/shoot.mjs, pero a 768×1024 (tablet) y 1280×800, y cuenta los <h1>.
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
const reporte = [];
for (const [w, h] of [[768, 1024], [1280, 800]]) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h } });
  const page = await ctx.newPage();
  await page.goto(base + '/__login/1');
  for (const linea of fs.readFileSync(lista, 'utf8').split('\n').filter(Boolean)) {
    const [nombre, url, pasos] = linea.split('|').map(s => s.trim());
    const errores = [];
    const onC = m => { if (m.type() === 'error') errores.push('console: ' + m.text()); };
    const onE = e => errores.push('pageerror: ' + e.message);
    page.on('console', onC); page.on('pageerror', onE);
    let status = 0; let h1 = -1; let scripts = -1;
    try {
      const r = await page.goto(base + url, { waitUntil: 'networkidle' });
      status = r?.status() ?? 0;
      if (pasos) await new Function('page', `return (async () => { ${pasos} })()`)(page);
      await page.waitForTimeout(500);
      h1 = await page.locator('h1').count();
      scripts = await page.evaluate(() => [...document.querySelectorAll('main script:not([src]), #app script:not([src])')].length);
      await page.screenshot({ path: `${out}/${nombre}-${w}.png`, fullPage: false });
    } catch (e) { errores.push('driver: ' + e.message.split('\n')[0]); }
    page.off('console', onC); page.off('pageerror', onE);
    reporte.push({ nombre, viewport: `${w}x${h}`, url, status, h1, errores });
    console.log(`${w} ${status} h1=${h1} ${nombre} ${errores.length ? 'ERR ' + errores.join(' || ').slice(0, 400) : 'ok'}`);
  }
  await ctx.close();
}
fs.writeFileSync(`${out}/reporte.json`, JSON.stringify(reporte, null, 2));
await browser.close();
