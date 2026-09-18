---
target: codificacion-form create
total_score: 21
max_score: 40
na_heuristics: 
p0_count: 1
p1_count: 2
timestamp: 2026-09-18T15-55-33Z
slug: ources-views-catalagos-codificacion-form-blade-php
---
# Critique: Nuevo modelo (codificación)

Target: `resources/views/catalagos/codificacion-form.blade.php`
URL: `/planeacion/catalogos/codificacion-modelos/create`

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Dirty “Sin guardar”, spinner, chips. Identity no actualiza Item/Size; jump nav sin sección actual; dirty oculto en viewport estrecho. |
| 2 | Match System / Real World | 3 | Salón/telar/flog/rizo/pie/barras son de piso. Luego nombres AX, Calibre numérico vs 600/1T, ITEMA→SMIT. |
| 3 | User Control and Freedom | 2 | Cancelar + back + beforeunload. Confirm en cada Crear es un peaje, no un undo. Cambiar salón no aísla construcción. |
| 4 | Consistency and Standards | 2 | http/notify ok. Select nativo. Calibre KM texto vs rizo/pie number. Title case vs TIRAS/PASADAS. |
| 5 | Error Prevention | 1 | Solo 3 required. KM guarda sin telar y sin barras. Construcción oculta sigue en el POST. Sin min/max/maxlength/unicidad. novalidate. |
| 6 | Recognition Rather Than Recall | 2 | Salón/telar son selects. Flog, fibra, color, tipo rizo, rasurada, vendedor, prioridad son texto libre. |
| 7 | Flexibility and Efficiency | 2 | Enter-as-tab. No Ctrl+S, no copiar barra, confirm obligatorio. |
| 8 | Aesthetic and Minimalist Design | 2 | ~90 controles visibles. Labels 0.7rem. Métricas bien demotadas; el resto es un dump de columnas. |
| 9 | Error Recovery | 2 | Error inline + focus. aria-invalid se pone y no se quita. Mensaje servidor “Validación incorrecta”. |
| 10 | Help and Documentation | 2 | Intros de sección y banners fuertes. Sin ejemplo de Tamaño Clave, sin unidades, C5 críptico. |
| **Total** | | **21/40** | **Acceptable** |

## Design Specificity Verdict

**LLM:** Cáscara de Towell (chips KM/JAC/SMIT, cuatro barras vs rizo/pie, leftover-rizo) sobre una grilla genérica de columnas AX. Un planificador está codificando una toalla; el formulario se siente como editar un renglón de Excel.

**Detector:** 1 finding CLI (`side-tab` en `.cod-input[required] { border-left: 3px solid #dc2626 }`) — falso positivo: es marca de obligatorio, no acento de card. Overlay en HTML compilado (login bloqueó live): 20 nodos, mayormente `tiny-text` (13) y `undersized-ui-text` (4, “sin capturar” ~10px). `skipped-heading` h1→h4 del layout.

**Overlays:** no visibles en la URL live (302 a login). Overlay solo en fallback compilado sin Tailwind del layout.

## Overall Impression

La construcción por salón es el único momento en que el formulario piensa como tejeduría. El resto es un catálogo AX de ~150 inputs con tres llaves obligatorias. El riesgo no es que se vea feo: es que **Crear** acepte un Karl Mayer sin 401/402 y sin barras, y que lo oculto (rizo/pie) igual se persista.

## What's Working

1. Construcción sigue el telar: empty state Karl Mayer 401/402 vs Jacquard/Smit rizo/pie/C1–C5. Tests y `data-solo-salon`.
2. Chrome de Operate: sticky identity, Crear en navbar, Enter→siguiente campo, dirty + beforeunload, banner de duplicado.
3. Métricas en `<details>` con aviso de que el programa recalcula.

## Priority Issues

### [P0] Guardar está legalmente vacío
JS `REQUIRED` y PHP `REQUIRED_FIELDS` = TamanoClave, OrdenTejido, SalonTejidoId. KM puede crearse sin telar y sin barras. Hidden `data-solo-salon="std"` sigue en FormData. `store()` no llama `normalizeDataForTable()`.

### [P1] Duplicar pide cambiar llaves y no las bloquea
Banner + `cod-input--dup` en Tamaño Clave / Orden / Flog / Nombre / Fecha / Pedido. Prefill de los mismos valores. Sin unique. Un confirm clona el modelo.

### [P1] Cambiar salón no aísla construcción
`aplicarSalon()` solo pone `hidden`. Jacquard→KM postea rizo/pie/C1–C5; KM→Smit postea barras. Banner leftover-rizo es load-time.

### [P2] Carga cognitiva alta (6/8 checklist)
Identificación 12 campos, Fechas 8, Medidas 12, C1–C5 9×5. Fechas mezcladas con vendedor/calidad. Geometry mezclada con Rasurada/Cambio de repaso/Velocidad STD.

### [P2] Accesibilidad de tablas bare + texto chico
62 inputs de tabla sin label (solo th). Labels 0.7rem, hints 0.65rem, “sin capturar” 0.62rem. aria-invalid sticky. Jump links 32px. Spinner sin reduced-motion.

## Persona Red Flags

**Alex:** Confirm en cada save. Sin Ctrl+S. ITEMA se guarda como SMIT. Editar métricas que el programa ignora.

**Jordan:** Doce campos de identidad antes del tipo de telar. Tamaño Clave vs Clave Modelo vs Clave vs Item ID. “C5 no tiene bandera Comb.” Sin ejemplo de clave.

**Sam:** Inputs bare sin nombre accesible. Color no es el único indicador (hay `*` y borde), pero errores no se limpian para AT. Focus en date pierde el ring custom. Sticky identity puede tapar campos si el jump wrappea.

**Planificador de tejido:** El trabajo es 401/402 con cuatro barras vs rizo/pie. El form lo explica y luego deja Telar — y filas “sin capturar”. Flog se teclea. Calibre rizo es number; en piso es 600/1T.

## Restrictions findings

Faltan: telar KM ∈ {401,402}; al menos una barra / cuenta rizo+pie; exclusión mutua de construcciones; unicidad de clave+orden; min/max/step en cantidades; maxlength vs SQL; catálogos (flog, fibra, color, tipo rizo, plano, rasurada, prioridad, tolerancia); métricas derived readonly.

Demasiado suelto: `sometimes|nullable` en todo lo no required; hidden enabled; 0 se conserva excepto Comb/Obs.

Demasiado estricto / tipo incorrecto: `type=number` en calibres de rizo/pie/C1–C5; Enter no submit (bueno para prevención, raro vs nativo); duplicado destaca sin bloquear.

## Animation findings

Casi solo funcional. `transition-colors` en botones, `fa-spin` 1s infinite, `animate-fade-in` en h1, SweetAlert. Construcción es `hidden` instantáneo. `prefers-reduced-motion` solo anula transitions que el form no define. Spinner y navbar no respetan reduced-motion.

## Accessibility findings

Labels+for en campos no-bare. Focus ring 2px #2563eb. Jump nav aria-label. Banners role=status. novalidate. aria-describedby no apunta a .cod-error. Contrast de hints 4.83:1 (AA justo) a 0.65rem. Overlay: tiny-text dominante.

## UI findings

Sticky identity bajo navbar z-50 es el patrón correcto. Grid 4 cols a 1024. Tablas min-width 720 (scroll horizontal). Primary vs derived es un lenguaje visual claro. Observaciones es input de una línea. Título “Nuevo modelo” no dice codificación. Identity meta pinta `·` de más: `— · Orden — · · Sin flog`.

## Cognitive load

Failures: single focus, chunking, grouping, one thing at a time, minimal choices, working memory. Pass: visual hierarchy, progressive disclosure (parcial). Count: **6 = high**.

## Minor observations

- pintarIdentidad no actualiza Item/Size.
- PasadasDibujo en CAMPOS_MODELO, ausente del form.
- window.codificacionData vuelca todos los atributos.
- Directorio `catalagos` (typo del repo).
- Detector side-tab es falso positivo.

## Questions to consider

- Si un KM vacío se puede Crear, ¿qué significa “codificar”?
- ¿Por qué Calibre es float en rizo/pie y string en barras?
- Flogs ID tiene lookup TI en el mismo controller. ¿Por qué se teclea?
- Si el programa recalcula métricas, ¿por qué 16 campos siguen aquí?
- ¿A quién protege “¿Guardar modelo?” si el caso peligroso no está en el diálogo?
