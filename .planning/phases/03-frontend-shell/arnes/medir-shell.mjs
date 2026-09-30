// PT 03 · GATE: legacy vs shell v2 con el mismo método de 04-perf (Chromium headless).
//   node medir-shell.mjs <etiqueta> <baseUrl> [repeticiones=20]
// Por superficie: TTFB (responseStart − requestStart), DOMContentLoaded − responseEnd y
// clic de fila → fila seleccionada (hasta el siguiente frame), mediana de N cargas; bytes del
// documento; errores de consola; capturas 1280×800 y 768×1024 en $ARNES_DATOS/shots/<etiqueta>/.
import { createRequire } from 'module';
import fs from 'fs';
import zlib from 'zlib';
const require = createRequire((process.env.NODE_GLOBAL ?? '/opt/node22/lib/node_modules') + '/');
const { chromium } = require('playwright');
const [,, etiqueta, base, rep = '20'] = process.argv;
const N = Number(rep);
const datos = process.env.ARNES_DATOS ?? '/tmp/towell-arnes';
const out = `${datos}/shots/${etiqueta}`;
fs.mkdirSync(out, { recursive: true });
const mediana = (xs) => { const s = [...xs].sort((a, b) => a - b); return s[Math.floor(s.length / 2)]; };
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const resultado = {};
for (const [ancho, alto] of [[1280, 800], [768, 1024]]) {
  const ctx = await browser.newContext({ viewport: { width: ancho, height: alto } });
  const page = await ctx.newPage();
  await page.goto(base + '/__login/1');
  for (const sup of ['programa-tejido', 'muestras']) {
    const errores = [];
    page.on('console', (m) => { if (m.type() === 'error') errores.push(m.text()); });
    page.on('pageerror', (e) => errores.push(e.message));
    const r = await page.goto(`${base}/planeacion/${sup}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(300);
    await page.screenshot({ path: `${out}/${sup}-${ancho}x${alto}.png` });
    page.removeAllListeners('console'); page.removeAllListeners('pageerror');
    if (ancho !== 1280) continue; // las métricas, solo una vez
    const html = await r.text();
    const ttfb = [], dcl = [], clic = [];
    for (let i = 0; i < N; i++) {
      await page.reload({ waitUntil: 'load' });
      const t = await page.evaluate(() => { const n = performance.getEntriesByType('navigation')[0]; return { ttfb: n.responseStart - n.requestStart, dcl: n.domContentLoadedEventStart - n.responseEnd }; });
      ttfb.push(t.ttfb); dcl.push(t.dcl);
      await page.waitForTimeout(250); // los listeners del bundle se enlazan tras DOMContentLoaded
      clic.push(await page.evaluate(async (i) => {
        const filas = document.querySelectorAll('#mainTable tbody tr.selectable-row');
        const fila = filas[(i * 7) % filas.length];
        const t0 = performance.now();
        fila.querySelector('td:not([style*="display:none"])').click();
        await new Promise((ok) => requestAnimationFrame(() => ok()));
        return fila.classList.contains('bg-blue-500') || fila.classList.contains('row-selected') || fila.className.includes('bg-blue') ? performance.now() - t0 : -1;
      }, i));
    }
    resultado[sup] = {
      html_kb: +(Buffer.byteLength(html) / 1024).toFixed(0), gzip_kb: +(zlib.gzipSync(html, { level: 6 }).length / 1024).toFixed(1),
      ttfb_ms: +mediana(ttfb).toFixed(0), dcl_menos_response_ms: +mediana(dcl).toFixed(0), clic_fila_ms: +mediana(clic).toFixed(1),
      clic_fallidos: clic.filter((x) => x < 0).length, errores_consola: errores,
    };
    console.log(etiqueta, sup, JSON.stringify(resultado[sup]));
  }
  await ctx.close();
}
fs.writeFileSync(`${out}/metricas.json`, JSON.stringify(resultado, null, 2));
await browser.close();
