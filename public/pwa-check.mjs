// ponytail: check de instalabilidad PWA. `node public/pwa-check.mjs`
// Verifica los criterios que Chrome exige para ofrecer "Instalar app".
// Falla si alguien vuelve a vaciar los iconos o a quitar el fetch handler.
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const dir = dirname(fileURLToPath(import.meta.url));
const fail = [];
const ok = (msg) => console.log("  OK   " + msg);
const bad = (msg) => { fail.push(msg); console.log("  FAIL " + msg); };

// --- manifest ---
const m = JSON.parse(readFileSync(join(dir, "manifest.json"), "utf8"));

m.name || m.short_name ? ok("manifest: name/short_name") : bad("manifest: falta name y short_name");
m.start_url ? ok(`manifest: start_url = ${m.start_url}`) : bad("manifest: falta start_url");

const displays = ["fullscreen", "standalone", "minimal-ui"];
displays.includes(m.display)
  ? ok(`manifest: display = ${m.display}`)
  : bad(`manifest: display "${m.display}" no es instalable (usa ${displays.join("/")})`);

// Chrome exige >=192x192 y recomienda 512x512, ambos PNG.
const icons = m.icons || [];
icons.length ? ok(`manifest: ${icons.length} icono(s) declarados`) : bad("manifest: icons vacio -> Chrome NO ofrece instalar");

const pngSize = (rel) => {
  // Lee ancho/alto del header PNG (IHDR: bytes 16-23).
  const buf = readFileSync(join(dir, rel));
  return [buf.readUInt32BE(16), buf.readUInt32BE(20)];
};

for (const need of [192, 512]) {
  const hit = icons.find((i) => (i.sizes || "").split(" ").includes(`${need}x${need}`));
  if (!hit) { bad(`manifest: falta icono ${need}x${need}`); continue; }
  try {
    const [w, h] = pngSize(hit.src);
    w === need && h === need
      ? ok(`icono ${need}x${need}: ${hit.src} existe y mide ${w}x${h}`)
      : bad(`icono ${hit.src} declara ${need}x${need} pero mide ${w}x${h}`);
  } catch {
    bad(`icono ${hit.src} declarado pero el archivo no existe`);
  }
}

// --- service worker ---
const sw = readFileSync(join(dir, "sw.js"), "utf8");
/addEventListener\(\s*["']fetch["']/.test(sw)
  ? ok("sw.js: tiene fetch handler")
  : bad("sw.js: sin fetch handler -> Chrome NO ofrece instalar");
/respondWith/.test(sw)
  ? ok("sw.js: el fetch handler responde (respondWith)")
  : bad("sw.js: fetch handler sin respondWith, no cuenta como offline-capable");

// Regresion del bug de login: el SW nunca debe interceptar POST.
/req\.method\s*!==\s*["']GET["']/.test(sw)
  ? ok("sw.js: deja pasar POST sin interceptar (protege login/CSRF)")
  : bad("sw.js: no excluye POST -> riesgo de romper login/CSRF");

console.log(fail.length ? `\n${fail.length} fallo(s)` : "\nPWA instalable: todos los criterios OK");
process.exit(fail.length ? 1 : 0);
