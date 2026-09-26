# HANDOFF — fase 19 (módulos)

Pedidos de cambios en archivos que no son de la sesión que los detecta. Cada fila: archivo, cambio, por qué, quién lo pidió.

## De 19-01 (Urdido + Engomado)

| # | Para | Archivo | Cambio | Por qué |
|---|---|---|---|---|
| U1 | 19-05 | `resources/js/urd-eng/edicion-ordenes.ts` | Importar el modal de calificar julios desde `resources/js/modulos/urdido/comun/calificar-julios/` en vez de llamar `window.abrirModalCalificarJuliosEng` | Hoy queda un puente `window` (marcado `// PUENTE 19-01`) solo por este llamador; al importarlo se quita el puente |
| U2 | 17-02 | `.planning/phases/17-ux/17-02-CHECKLIST.md` | Cuando exista, enlazarlo desde `19-00-RECETA.md` (cabecera) — o avisar a la siguiente 19-xx que lo haga | La receta usa una checklist mínima provisional (§7) |
| U3 | 17-02 | helper UX-06 (long-press / botón "⋮") | Al publicarse, adoptarlo en los filtros por menú contextual de `engomado/captura-formula` | Hoy esos filtros solo se abren con clic derecho (inalcanzable en iPad) |
| U4 | 17-02 / DS | `resources/views/components/ui/modal-base.blade.php` | Cambiar el `onclick="{{ $closeJs }}"` del botón × por un atributo de datos que lea `componentes/dialog.ts` | Cada vista que adopta `x-ui.modal-base` hereda un `onclick=` en el HTML renderizado; los tests guardianes de 19-xx tienen que revisar el fuente Blade en vez del HTML |
