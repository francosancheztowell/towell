// Flujo completo de Programa Urd/Eng con Playwright, paso a paso, con captura y consola por paso.
// Uso: node flujo.mjs <etiqueta>
//   ARNES_URL (servidor), ARNES_DATOS (los sqlite del MISMO servidor: se consultan para verificar
//   el folio en BD), ARNES_SALIDA (carpeta de salida), ANCHO/ALTO (default 1280x800).
// Grupos (si un paso falla se omite el resto de su grupo, no los demás):
//   orden: reservar J-502 al telar 203 → marcar 201+202 → Programar → programación (tamaño, hilo)
//          → Siguiente → creación (fila, destino, BOM, material, construcción, engomado)
//          → Crear Órdenes → fecha → folio → verificación en BD.
//   tactil (768 px, touch): long-press en encabezado (menú de columna), "⋮" de fila (menú de
//          fila) y long-press en celda editable. Solo existe en el "después": en el "antes" se
//          marca como omitido (solo había clic derecho).
//   karl-mayer: 401 / barra 1 / fibra / tamaño / BOM / material / julios → Crear Orden → fecha
//          → folio → verificación en BD.
// Compatible antes/después: fecha por modal x-ui (`data-fecha-requerimiento-*`) o SweetAlert;
// botón crear `[data-accion="crear-ordenes"]` o `#btn-crear-ordenes`.
// Cada paso deja NN-paso.png y flujo.json. El flujo escribe en la BD: resembrar (setup.php) antes de repetirlo.
import { createRequire } from 'module';
import { execFileSync } from 'child_process';
import fs from 'fs';
import os from 'os';

const require = createRequire((process.env.NODE_GLOBAL ?? '/opt/node22/lib/node_modules') + '/');
const { chromium } = require('playwright');

const etiqueta = process.argv[2] ?? 'antes';
const base = process.env.ARNES_URL ?? 'http://127.0.0.1:8125';
const datos = process.env.ARNES_DATOS ?? `${os.tmpdir()}/towell-arnes-1905`;
const out = process.env.ARNES_SALIDA ?? `${datos}/capturas/flujo-${etiqueta}`;
fs.mkdirSync(out, { recursive: true });

/** Consulta el sqlite del arnés (vía php, sin dependencias de node). */
function sql(consulta, params = []) {
  const php = `$p=new PDO('sqlite:'.$argv[1]);$s=$p->prepare($argv[2]);$s->execute(json_decode($argv[3],true));echo json_encode($s->fetchAll(PDO::FETCH_ASSOC));`;
  return JSON.parse(execFileSync('php', ['-r', php, `${datos}/main.sqlite`, consulta, JSON.stringify(params)]).toString());
}

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const nuevaPagina = async (opts) => {
  const ctx = await browser.newContext({ locale: 'es-MX', timezoneId: 'America/Mexico_City', ...opts });
  const p = await ctx.newPage();
  p.on('console', (m) => { if (m.type() === 'error') errores.push('console: ' + m.text()); });
  p.on('pageerror', (e) => errores.push('pageerror: ' + e.message));
  p.on('response', (r) => { if (r.status() >= 400) http.push(`${r.status()} ${r.request().method()} ${r.url().replace(base, '')}`.slice(0, 300)); });
  await p.goto(base + '/__login/1');
  return p;
};
let errores = [];
let http = [];
let page = await nuevaPagina({ viewport: { width: Number(process.env.ANCHO ?? 1280), height: Number(process.env.ALTO ?? 800) } });

const reporte = [];
let n = 0;
const abortado = new Map(); // grupo → true si falló, null si se omitió a propósito
class Omitido extends Error {}
async function paso(grupo, nombre, fn) {
  if (abortado.has(grupo)) {
    const falla = abortado.get(grupo);
    reporte.push({ grupo, paso: nombre, ok: falla ? false : null, detalle: falla ? 'omitido: falló un paso anterior del grupo' : 'omitido' });
    return;
  }
  n++;
  errores = []; http = [];
  let ok = true;
  let detalle = '';
  try {
    detalle = (await fn()) ?? '';
  } catch (e) {
    ok = e instanceof Omitido ? null : false;
    detalle = (e instanceof Omitido ? 'omitido: ' : '') + e.message.split('\n')[0];
  }
  await page.waitForTimeout(400);
  await page.screenshot({ path: `${out}/${String(n).padStart(2, '0')}-${nombre}.png` }).catch(() => {});
  reporte.push({ grupo, paso: nombre, ok, detalle, url: page.url().replace(base, '').slice(0, 120), errores, http });
  const marca = ok === null ? '-- ' : ok ? 'ok ' : 'ERR';
  console.log(`${marca} [${grupo}] ${nombre} ${detalle} ${errores.length || http.length ? '| ' + [...errores, ...http].join(' || ').slice(0, 300) : ''}`);
  if (ok !== true) abortado.set(grupo, ok === false);
}

/* ---------- utilidades de página ---------- */
const swalTexto = async () => (await page.locator('.swal2-popup:visible').first().innerText().catch(() => '')).replace(/\s+/g, ' ').trim();
const cerrarSwal = async () => {
  const b = page.locator('.swal2-confirm:visible');
  if (await b.count()) await b.first().click();
};
/** Fecha de requerimiento: modal x-ui (después) o SweetAlert (antes). Devuelve cuál fue. */
async function confirmarFecha() {
  const modal = page.locator('[data-fecha-requerimiento-confirmar]:visible');
  const swal = page.locator('.swal2-popup:visible').filter({ hasText: /requiere el material/i });
  await modal.or(swal).first().waitFor({ timeout: 8000 });
  if (await modal.count()) { await modal.first().click(); return 'modal x-ui'; }
  await page.locator('.swal2-confirm:visible').first().click();
  return 'SweetAlert';
}
/** Espera el aviso final (SweetAlert de notify.alert en ambos) y lo devuelve como texto. */
async function avisoFinal() {
  await page.locator('.swal2-popup:visible').filter({ hasText: /Folio|Error|error/ }).first().waitFor({ timeout: 15000 });
  return swalTexto();
}
// Por data-* (antes y después): la 1ª celda del después también lleva el botón "⋮".
const filaTelar = (no, tipo) => page.locator(`#telaresTable tbody tr.selectable-row[data-telar="${no}"]`)
  .and(page.locator(`[data-tipo="${tipo.toUpperCase()}"], [data-tipo="${tipo}"]`));

/* ================= Grupo orden: reservar → programación → creación → crear ================= */
await paso('orden', 'reservar-abrir', async () => {
  await page.goto(base + '/programaurdeng', { waitUntil: 'networkidle' });
  return `${await page.locator('#telaresTable tbody tr.selectable-row').count()} telares`;
});

await paso('orden', 'reservar-julio-203', async () => {
  await filaTelar('203', 'Rizo').first().click();
  await page.waitForLoadState('networkidle');
  await page.locator('#inventarioTable tbody tr.selectable-row-inventario').filter({ hasText: 'J-502' }).first().click();
  await page.locator('#btnReservar').click();
  await page.locator('.swal2-confirm:visible').click(); // notify.confirm "Sí, reservar" (SweetAlert en ambos)
  // Aviso de éxito: toast nativo (después, .towell-toast) o toast de SweetAlert (antes).
  const aviso = page.locator('.towell-toast__msg, .swal2-popup').filter({ hasText: /reservad/i }).first();
  await aviso.waitFor({ timeout: 8000 });
  const txt = (await aviso.innerText()).replace(/\s+/g, ' ');
  await cerrarSwal();
  const bd = sql("SELECT NoTelarId, InventSerialId, Status FROM InvTelasReservadas WHERE InventSerialId = 'J-502'");
  if (!bd.length) throw new Error('la reserva no quedó en InvTelasReservadas');
  return `${txt.slice(0, 80) || 'sin mensaje'} · BD: ${JSON.stringify(bd[0])}`;
});

await paso('orden', 'marcar-201-202', async () => {
  await page.goto(base + '/programaurdeng', { waitUntil: 'networkidle' });
  for (const no of ['201', '202']) await filaTelar(no, 'Rizo').first().locator('.telar-checkbox').check();
  return 'marcados';
});

await paso('orden', 'programar', async () => {
  await Promise.all([page.waitForURL(/programacion-requerimientos/, { timeout: 15000 }), page.locator('#btnProgramar').click()]);
  await page.waitForLoadState('networkidle');
  return `${await page.locator('#tbodyRequerimientos tr').count()} filas`;
});

await paso('orden', 'requerimientos-llenar', async () => {
  // En el "antes" el orden importa: el tamaño llena cuenta/calibre por código (sin evento) y
  // #btnSiguiente solo se reevalúa en input/change; por eso el hilo va DESPUÉS del tamaño.
  const fila = page.locator('#tbodyRequerimientos tr').first();
  await fila.locator('input[data-field="tamano"]').fill('3040');
  await page.locator('.tamano-dropdown [data-value="3040-12.5/1"]').first().click({ timeout: 8000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500); // guarda tamaño, cuenta y calibre (POST actualizar-telar)
  await fila.locator('select[data-field="hilo"]').selectOption('A12');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(800); // el cambio de hilo recalcula el resumen de semanas
  const v = async (sel) => fila.locator(sel).inputValue().catch(() => '-');
  return `tamaño=${await v('input[data-field="tamano"]')} cuenta=${await v('input[data-field="cuenta"]')} calibre=${await v('input[data-field="calibre"]')} hilo=${await v('select[data-field="hilo"]')} metros=${await v('input[data-field="metros"]')} kilos=${await v('input[data-field="kilos"]')}`;
});

await paso('orden', 'siguiente', async () => {
  if (await page.locator('#btnSiguiente').isDisabled()) throw new Error('#btnSiguiente deshabilitado (faltan campos)');
  await Promise.all([page.waitForURL(/creacion-ordenes/, { timeout: 15000 }), page.locator('#btnSiguiente').click()]);
  await page.waitForLoadState('networkidle');
  return decodeURIComponent(new URL(page.url()).searchParams.get('telares') ?? '').slice(0, 160);
});

await paso('orden', 'creacion-fila-bom', async () => {
  const fila = page.locator('[data-bom-input="true"]').first().locator('xpath=ancestor::tr');
  await fila.click();
  const destino = fila.locator('[data-destino-select="true"]');
  if (await destino.count()) await destino.selectOption('Jacquard Smit');
  await fila.locator('[data-bom-input="true"]').fill('URD 30');
  await page.locator('#bom-suggestions-global div').first().click({ timeout: 8000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(800);
  const urd = (await page.locator('#tbodyMaterialesUrdido').innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 60);
  return `${await page.locator('.checkbox-material').count()} materiales de engomado · panel urdido: "${urd}"`;
});

await paso('orden', 'creacion-material-y-datos', async () => {
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

let folioOrden = '';
await paso('orden', 'crear-ordenes', async () => {
  await page.locator('[data-accion="crear-ordenes"], #btn-crear-ordenes').first().click();
  const via = await confirmarFecha();
  const txt = await avisoFinal();
  const m = txt.match(/Folio:\s*(\w+)/);
  if (!m) throw new Error(txt.slice(0, 200));
  folioOrden = m[1];
  return `fecha por ${via} · ${txt.slice(0, 160)}`;
});

await paso('orden', 'verificar-bd', async () => {
  const urd = sql('SELECT Folio, NoTelarId, Status, MaquinaId, BomId, Fibra, InventSizeId, Metros FROM UrdProgramaUrdido WHERE Folio = ?', [folioOrden]);
  const eng = sql('SELECT Folio, MaquinaEng, BomEng, BomFormula, NoTelas FROM EngProgramaEngomado WHERE Folio = ?', [folioOrden]);
  const julios = sql('SELECT Julios, Hilos FROM UrdJuliosOrden WHERE Folio = ?', [folioOrden]);
  const consumo = sql('SELECT InventSerialId, InventQty FROM UrdConsumoHilo WHERE Folio = ?', [folioOrden]);
  const telares = sql("SELECT no_telar, no_orden, Programado FROM tej_inventario_telares WHERE no_orden = ? AND tipo = 'Rizo'", [folioOrden]);
  if (urd.length !== 1 || eng.length !== 1 || !julios.length || !consumo.length || telares.length !== 2) {
    throw new Error(`BD incompleta: urd=${urd.length} eng=${eng.length} julios=${julios.length} consumo=${consumo.length} telares=${telares.length}`);
  }
  return JSON.stringify({ urd: urd[0], eng: eng[0], julios, consumo, telares });
});

await paso('orden', 'volver-a-reservar', async () => {
  await cerrarSwal();
  await page.waitForURL(/reservar-programar|programaurdeng/, { timeout: 10000 });
  await page.waitForLoadState('networkidle');
  return (await filaTelar('201', 'Rizo').first().innerText()).replace(/\s+/g, ' ');
});

/* ================= Grupo tactil: 768 px con touch ================= */
page = await nuevaPagina({ viewport: { width: 768, height: 1024 }, hasTouch: true, isMobile: false });

/** Long-press real con eventos de puntero táctiles (accionesTactiles escucha pointerdown/up). */
async function longPress(loc, ms = 700) {
  await loc.scrollIntoViewIfNeeded();
  const b = await loc.boundingBox();
  const x = b.x + b.width / 2;
  const y = b.y + b.height / 2;
  await loc.evaluate((el, [x, y]) => {
    const o = { bubbles: true, cancelable: true, composed: true, pointerType: 'touch', pointerId: 7, isPrimary: true, button: 0, buttons: 1, clientX: x, clientY: y };
    el.dispatchEvent(new PointerEvent('pointerdown', o));
  }, [x, y]);
  await page.waitForTimeout(ms);
  await loc.evaluate((el, [x, y]) => {
    el.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, cancelable: true, pointerType: 'touch', pointerId: 7, isPrimary: true, button: 0, buttons: 0, clientX: x, clientY: y }));
  }, [x, y]);
}

await paso('tactil', 'tactil-abrir-768', async () => {
  await page.goto(base + '/programaurdeng', { waitUntil: 'networkidle' });
  if (!(await page.locator('#puMenuFila').count())) throw new Omitido('sin menú táctil (#puMenuFila): pantalla del "antes"');
  const b = await page.locator('#telaresTable tbody tr.selectable-row').first().getByRole('button').first().boundingBox();
  return `botón ⋮ de la 1ª fila: ${Math.round(b?.width ?? 0)}×${Math.round(b?.height ?? 0)} px`;
});

await paso('tactil', 'long-press-encabezado', async () => {
  await longPress(page.locator('#telaresTable thead .sortable[data-column="cuenta"]').first());
  const menu = page.locator('#tableContextMenu');
  await menu.waitFor({ state: 'visible', timeout: 3000 });
  return `menú de columna: ${(await menu.innerText()).replace(/\s+/g, ' ')}`;
});

await paso('tactil', 'boton-menu-fila', async () => {
  await page.keyboard.press('Escape');
  await page.mouse.click(5, 1000);
  await page.locator('#telaresTable tbody tr.selectable-row').first().getByRole('button', { name: /Acciones del telar/ }).tap();
  const menu = page.locator('#puMenuFila');
  await menu.waitFor({ state: 'visible', timeout: 3000 });
  const alto = (await menu.locator('button').first().boundingBox())?.height ?? 0;
  return `menú de fila: ${(await menu.innerText()).replace(/\s+/g, ' ')} · alto opción ${Math.round(alto)} px`;
});

await paso('tactil', 'long-press-celda', async () => {
  await page.keyboard.press('Escape');
  const celda = page.locator('#telaresTable tbody tr.selectable-row').first().locator('.editable-cell').first();
  await longPress(celda);
  await celda.locator('input').waitFor({ state: 'visible', timeout: 3000 });
  const v = await celda.locator('input').inputValue();
  await celda.locator('input').press('Escape');
  return `celda en edición con valor ${v} (Esc cancela)`;
});

/* ================= Grupo karl-mayer ================= */
page = await nuevaPagina({ viewport: { width: Number(process.env.ANCHO ?? 1280), height: Number(process.env.ALTO ?? 800) } });

await paso('karl-mayer', 'km-llenar', async () => {
  await page.goto(base + '/programa-urd-eng/karl-mayer', { waitUntil: 'networkidle' });
  await page.selectOption('select[name="no_telar"]', '401');
  await page.selectOption('select[name="barras"]', '1');
  await page.selectOption('#input-fibra', 'A12');
  await page.fill('#input-tamano', '1800');
  await page.locator('#tamano-dropdown [data-value="1800-8/1"]').first().click({ timeout: 8000 });
  await page.waitForTimeout(300);
  await page.fill('input[name="metros"]', '3000');
  await page.fill('#input-lmat', 'URD 1800-KM');
  await page.dispatchEvent('#input-lmat', 'change');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(600);
  await page.locator('.chk-detalle-lmat').first().check();
  await page.locator('input[name="hilos[]"]').first().fill('500');
  return `cuenta=${await page.inputValue('#input-cuenta')} calibre=${await page.inputValue('#input-calibre')} materiales=${await page.locator('.chk-detalle-lmat').count()} lote=${await page.inputValue('#input-lote-proveedor')}`;
});

let folioKm = '';
await paso('karl-mayer', 'km-crear', async () => {
  const boton = page.locator('#btnCrearOrden');
  if (await boton.isDisabled()) throw new Error('#btnCrearOrden deshabilitado (formulario incompleto)');
  await boton.click();
  const via = await confirmarFecha();
  const txt = await avisoFinal();
  const m = txt.match(/Folio:\s*(\w+)/);
  if (!m) throw new Error(txt.slice(0, 200));
  folioKm = m[1];
  return `fecha por ${via} · ${txt.slice(0, 120)}`;
});

await paso('karl-mayer', 'km-verificar-bd', async () => {
  const urd = sql('SELECT Folio, NoTelarId, RizoPie, MaquinaId, BomId, Status FROM UrdProgramaUrdido WHERE Folio = ?', [folioKm]);
  const consumo = sql('SELECT InventSerialId FROM UrdConsumoHilo WHERE Folio = ?', [folioKm]);
  if (urd.length !== 1) throw new Error(`UrdProgramaUrdido con folio ${folioKm}: ${urd.length} filas`);
  await cerrarSwal();
  return JSON.stringify({ urd: urd[0], consumo });
});

fs.writeFileSync(`${out}/flujo.json`, JSON.stringify(reporte, null, 2));
await browser.close();
const fallos = reporte.filter((r) => r.ok === false).length;
console.log(`→ ${out} (${fallos} paso(s) con error)`);
