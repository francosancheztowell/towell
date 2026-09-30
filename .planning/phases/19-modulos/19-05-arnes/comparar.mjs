// Uso: node comparar.mjs <dir-antes> <dir-despues> <dir-salida> [ancho=768]
// Una imagen por pantalla: antes | después, lado a lado (renderizado con Chromium, sin ImageMagick).
import { createRequire } from 'module';
import fs from 'fs';
import path from 'path';

const require = createRequire((process.env.NODE_GLOBAL ?? '/opt/node22/lib/node_modules') + '/');
const { chromium } = require('playwright');
const [, , antes, despues, salida, ancho = '768'] = process.argv;
fs.mkdirSync(salida, { recursive: true });
const b64 = (f) => 'data:image/png;base64,' + fs.readFileSync(f).toString('base64');
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width: Number(ancho) * 2 + 24, height: 400 } });
for (const f of fs.readdirSync(antes).filter((f) => f.endsWith(`-${ancho}.png`)).sort()) {
  const d = path.join(despues, f);
  if (!fs.existsSync(d)) { console.log('sin después:', f); continue; }
  const col = (t, src) => `<div><div style="font:bold 16px sans-serif;padding:4px">${t}</div><img src="${src}" style="display:block;border:1px solid #999"></div>`;
  await page.setContent(`<body style="margin:0;display:flex;gap:8px;padding:4px;background:#fff">${col('ANTES · ' + f, b64(path.join(antes, f)))}${col('DESPUÉS', b64(d))}</body>`);
  await page.screenshot({ path: path.join(salida, f), fullPage: true });
  console.log('ok', f);
}
await browser.close();
