# HANDOFF — PT 04-perf (rama `claude/pt-04-perf`)

## A. Ya hecho fuera de la propiedad PT (el integrador debe aceptarlo o rehacerlo)

| Archivo (dueño) | Cambio | Por qué |
|---|---|---|
| `tests/Feature/ProgramaTejidoJsSyntaxTest.php` (fuera del glob `tests/Feature/Planeacion/**`) | 1 aserción + su comentario: `'window.PT_BOOT'` → `'<script type="application/json" id="pt-boot">'` | El test fija el contrato de `scripts/main.blade.php`; el corte 5 cambió `PT_BOOT` de código a JSON. Sin el cambio la suite queda en rojo. El resto del test (tamaño < 20 KB, bundle de Vite) no se tocó. |

## B. Pedidos

| # | Archivo (dueño) | Cambio pedido | Por qué |
|---|---|---|---|
| 1 | `resources/views/components/programa-tejido/req-programa-tejido-line-table.blade.php` (no está en la fila PT) | Pasar a la fila PT del protocolo, o que su dueño mueva su `<script>` (19 KB) a `resources/js/programa-tejido/` | Es el mayor bloque inline que queda en la grilla; solo lo usa Programa Tejido |
| 2 | `resources/views/components/navbar/navbar.blade.php` (UX-global) | Mover `mostrarModalDiasLiberar` (2 KB inline) a un módulo | Mismo motivo; PT solo podía tocar 1 línea de ese archivo |
| 3 | `modulos/programa-tejido/modal/redbooth.blade.php` (15-02 en esta ola) | Al terminar 15-02, devolverlo a PT para mover su `<script>` (18 KB) al bundle | Último bloque inline de PT en la página de Programa |
