// Uso: node shoot.ts <etiqueta> <archivo-de-urls>
// Capturas a 768×1024 y 1280×800 + errores de consola por pantalla (Node ≥ 22.18 ejecuta TS).
import { createRequire } from 'node:module';
import fs from 'node:fs';
import os from 'node:os';

const require = createRequire((process.env.NODE_GLOBAL ?? '/opt/node22/lib/node_modules') + '/');
const { chromium } = require('playwright');
const [, , etiqueta, lista] = process.argv;
const base = process.env.ARNES_URL ?? 'http://127.0.0.1:8123';
const datos = process.env.ARNES_DATOS ?? `${os.tmpdir()}/towell-arnes`;
const out = `${datos}/shots/${etiqueta}`;
fs.mkdirSync(out, { recursive: true });

interface Fila { nombre: string; url: string; vista: string; status: number; errores: string[] }

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const reporte: Fila[] = [];
for (const [ancho, alto] of [[768, 1024], [1280, 800]]) {
    const ctx = await browser.newContext({ viewport: { width: ancho, height: alto } });
    const page = await ctx.newPage();
    await page.goto(base + '/__login/1');
    for (const linea of fs.readFileSync(lista, 'utf8').split('\n').filter(Boolean)) {
        const [nombre, url, pasos] = linea.split('|').map((s) => s.trim());
        const errores: string[] = [];
        const onC = (m: { type(): string; text(): string }) => { if (m.type() === 'error') errores.push('console: ' + m.text()); };
        const onE = (e: Error) => errores.push('pageerror: ' + e.message);
        page.on('console', onC);
        page.on('pageerror', onE);
        let status = 0;
        try {
            const r = await page.goto(base + url, { waitUntil: 'networkidle' });
            status = r?.status() ?? 0;
            if (pasos) await new Function('page', `return (async () => { ${pasos} })()`)(page);
            await page.waitForTimeout(400);
            await page.screenshot({ path: `${out}/${nombre}-${ancho}.png`, fullPage: false });
        } catch (e) {
            errores.push('driver: ' + String((e as Error).message).split('\n')[0]);
        }
        page.off('console', onC);
        page.off('pageerror', onE);
        reporte.push({ nombre, url, vista: `${ancho}x${alto}`, status, errores });
        console.log(`${status} ${nombre}@${ancho} ${errores.length ? 'ERR ' + errores.join(' || ').slice(0, 400) : 'ok'}`);
    }
    await ctx.close();
}
fs.writeFileSync(`${out}/reporte.json`, JSON.stringify(reporte, null, 2));
await browser.close();
