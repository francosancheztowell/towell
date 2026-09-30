// Carrera de la cascada (code-review 19-08): la lista de máquinas de Urdido llega tarde
// y el operador ya eligió Engomado. El combo debe quedarse con las de Engomado.
import { createRequire } from 'module';
const require = createRequire((process.env.NODE_GLOBAL ?? '/opt/node22/lib/node_modules') + '/');
const { chromium } = require('playwright');
const base = process.env.ARNES_URL ?? 'http://127.0.0.1:8123';
const browser = await chromium.launch({ args: ['--no-sandbox'] });
const page = await (await browser.newContext({ viewport: { width: 768, height: 1024 } })).newPage();
await page.goto(base + '/__login/1?to=/mantenimiento/nuevo-paro', { waitUntil: 'networkidle' });
await page.route('**/api/mantenimiento/maquinas/Urdido', async (route) => {
  await new Promise((r) => setTimeout(r, 1500));
  await route.continue();
});
await page.selectOption('#depto', 'Engomado');
await page.selectOption('#depto', 'Urdido');
await page.selectOption('#depto', 'Engomado');
await page.waitForTimeout(2500);
const maquinas = await page.$$eval('#maquina option', (os) => os.map((o) => o.value).filter(Boolean));
console.log(JSON.stringify({ depto: await page.inputValue('#depto'), maquinas }));
await browser.close();
process.exit(maquinas.join() === 'West Point 2' ? 0 : 1);
