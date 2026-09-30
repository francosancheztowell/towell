// Uso: node shoot.mjs <etiqueta> [urls.txt]
//   ARNES_URL    servidor del arnés (default http://127.0.0.1:8125)
//   ARNES_SALIDA carpeta de salida (default $ARNES_DATOS/capturas/<etiqueta>)
//   VIEWPORTS    "768x1024,1280x800" (default)   FULL=1 captura página completa
// Salida: <nombre>-<ancho>.png por pantalla y viewport + consola.json (status, errores de consola,
// pageerror, respuestas >= 400) por pantalla y viewport.
// urls.txt: `nombre | ruta | pasos JS opcionales (variable page)`. En la ruta, {TELARES} y
// {REQUERIMIENTOS} se sustituyen por el JSON (urlencoded) de casos.json.
import { createRequire } from 'module';
import fs from 'fs';
import os from 'os';
import path from 'path';
import { fileURLToPath } from 'url';

const aqui = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire((process.env.NODE_GLOBAL ?? '/opt/node22/lib/node_modules') + '/');
const { chromium } = require('playwright');

const [, , etiqueta = 'antes', lista = path.join(aqui, 'urls.txt')] = process.argv;
const base = process.env.ARNES_URL ?? 'http://127.0.0.1:8125';
const datos = process.env.ARNES_DATOS ?? `${os.tmpdir()}/towell-arnes-1905`;
const out = process.env.ARNES_SALIDA ?? `${datos}/capturas/${etiqueta}`;
const viewports = (process.env.VIEWPORTS ?? '768x1024,1280x800').split(',').map((v) => v.split('x').map(Number));
const casos = JSON.parse(fs.readFileSync(path.join(aqui, 'casos.json'), 'utf8'));
const sustituir = (url) => url.replace(/\{(\w+)\}/g, (_, k) => encodeURIComponent(JSON.stringify(casos[k] ?? '')));

fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const reporte = [];

for (const [ancho, alto] of viewports) {
  const ctx = await browser.newContext({ viewport: { width: ancho, height: alto }, locale: 'es-MX', timezoneId: 'America/Mexico_City' });
  const page = await ctx.newPage();
  await page.goto(base + '/__login/1');
  for (const linea of fs.readFileSync(lista, 'utf8').split('\n').filter((l) => l.trim() && !l.startsWith('#'))) {
    const [nombre, url, pasos] = linea.split('|').map((s) => s.trim());
    const errores = [];
    const avisos = [];
    const http = [];
    const onC = (m) => {
      if (m.type() === 'error') errores.push('console: ' + m.text());
      if (m.type() === 'warning') avisos.push(m.text());
    };
    const onE = (e) => errores.push('pageerror: ' + e.message);
    const onR = (r) => { if (r.status() >= 400) http.push(`${r.status()} ${r.request().method()} ${r.url().replace(base, '')}`.slice(0, 300)); };
    page.on('console', onC); page.on('pageerror', onE); page.on('response', onR);
    let status = 0;
    try {
      const r = await page.goto(base + sustituir(url), { waitUntil: 'networkidle', timeout: 30000 });
      status = r?.status() ?? 0;
      if (pasos) await new Function('page', `return (async () => { ${pasos} })()`)(page);
      await page.waitForTimeout(600);
      await page.screenshot({ path: `${out}/${nombre}-${ancho}.png`, fullPage: process.env.FULL === '1' });
    } catch (e) { errores.push('driver: ' + e.message.split('\n')[0]); }
    page.off('console', onC); page.off('pageerror', onE); page.off('response', onR);
    reporte.push({ nombre, viewport: `${ancho}x${alto}`, url, status, errores, http, avisos });
    console.log(`${status} ${nombre}@${ancho} ${errores.length || http.length ? 'ERR ' + [...errores, ...http].join(' || ').slice(0, 400) : 'ok'}`);
  }
  await ctx.close();
}
fs.writeFileSync(`${out}/consola.json`, JSON.stringify(reporte, null, 2));
await browser.close();
console.log(`→ ${out}`);
