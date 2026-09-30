# HANDOFF — PT 03 (rama `claude/pt-03-shell-livewire`)

## A. Ya hecho fuera de la fila PT 03 (necesario para no dejar un test en rojo)

| Archivo | Cambio | Por qué |
|---|---|---|
| `tests/Unit/TrazabilidadStructureTest.php` (Trazabilidad) | 1 línea: la aserción `payload.flog_asignacion = flogAsignacion` ahora lee `resources/js/modulos/redbooth/modal.js` en vez de `modal/redbooth.blade.php` | HANDOFF PT-05 B4: la lógica del modal salió del Blade tal cual; la aserción conserva su intención |
| `scripts/ratchet-baseline.json` | `onclick=` 265 → 264 | Compartido, solo bajar |

## B. Pedidos a otros dueños

| # | Archivo (dueño) | Cambio pedido | Por qué |
|---|---|---|---|
| A1 | `scripts/ratchet.mjs` (BASE) | Excluir `type="application/json"` de la métrica `<script> inline en blade` (como ya hace `medir-grilla.php`) | Pasar un script a un bundle con boot JSON (receta de 19-00) no baja la métrica: Redbooth quedó −1 +1 |
| C1 | Navbar de PT a 768 px (PT + layout 17-02) | El navbar de Programa Tejido ya se desbordaba en tablet con todos los permisos (el logo tapa el título; el botón de filtros queda cortado) y el "⋮" de UX-06 suma 44 px. Propuesta: agrupar los botones de columnas (fijar/ocultar/restablecer/filtros) en un menú "Columnas" a < 1024 px | Hallazgo H1 de `03-SUMMARY.md`; es diseño del navbar, no del shell |
| O1 | Owner / integrador | **GATE de PT 03 no pasa** (§0 de `03-SUMMARY.md`): v2 queda apagado y, según la regla de la fase, **04-ux no sigue sobre Livewire**. Decidir si 04-ux se replantea sobre el Blade/TS actual (lo que sí bajó números en 04-perf) y si el shell v2 se retira o se queda apagado como base | Decisión de roadmap (ROADMAP/STATE son del integrador) |
| O2 | Owner (Laragon) | Opcional: runbook §5 de `03-SUMMARY.md` para confirmar el GATE con datos reales | El GATE se midió con 85 filas sintéticas |

## C. Cerrados por esta fase

- **17-02 B1** (`onclick="mostrarModalDiasLiberar()"` del navbar de PT → `data-accion` + import de `componentes/dias-liberar.ts`).
- **17-02 B2** (menús contextuales de PT y liberar-ordenes → `accionesTactiles` + un "⋮" en el navbar).
- **PT-05 B4** (Redbooth → `resources/js/modulos/redbooth/`, cargado por el propio modal).
