# Kardex — conectar el libro (InvKardex*) en ProdTowel (.24)

Objetivo: reemplazar los datos simulados del módulo Kardex por las cifras del libro donde las tablas lo permiten.
Rama: `feat/kardex` · Entrega: un PR (`single-pr`) · Runner: `node --test` (JS), `php artisan test` (PHP).

## Specs

- S1. Front del módulo Kardex con 5 secciones basado en el HTML de referencia, con datos simulados: "crea la estructura y front de cada uno de momento con datos simulados y crea el front desde cero pero basandote en el kardex, obvio con los estilos actuales de la app".
- S2. Conectar con las tablas de ProdTowel lo que se pueda: "Estos son los campos para la db y estan en la base de datos de ProdTowel ve que es lo que puedes empezar a conectar y que no" → "si, conecta lo que se puede con las cifras del libro".
  - Tablas: InvKardexURDID, InvKardexAJULURD, InvKardexAJULTELA, InvKardexAEPTEJID, InvKardexCrudo (conexión `sqlsrv_tow_pro`, nombre `ProdTowel.dbo.*`), por `YEARDATE`/`MONTHDATE`.
  - Inicial = INICIALKG, Final = SALDOKG. Consumos: URDID SALPRODMC1/2/3/KM; AJULURD SALWP2+SALWP3; AJULTELA JAQKG+ITEMAKG; AEPTEJID SALJACQ+SALITEMA+SALSMITH+SALKM; Crudo ENTJACQUARD+ENTITEMA+ENTSMITH+ENTKARLMAYER.
  - Cifras del libro también en la flecha Tejido → Crudo y en las cajas del flujo (no los finales físicos del ERP).
  - Lo que no sale de esas tablas sigue simulado y se marca como tal.
- S3. "Despues crea los commit y crea el pr".

## Tasks

| ID | Specs | Ruta | Estado | Commit |
|----|-------|------|--------|--------|
| T1 | S1 | inline | hecho | c74d2a20 |
| T2 | S2 | inline | hecho | (commit T2) |
| T3 | S3 | inline | pendiente | — |

## Log

- L1 (2026-10-16): pedido original — front del módulo kardex basado en Kardex_Materia_Prima_y_Produccion_en_Proceso_Agosto_2026.html, con datos simulados, flujo SVG y gráficas.
- L2: "si, conecta lo que se puede con las cifras del libro". "Despues crea los commit y crea el pr".
- L3: agosto 2026 verificado contra la hoja REs del HTML: los totales de las 4 secciones y Crudo (278,200.82) cuadran. Mapeo neto: URDID Traspasos = ENTTRASPASOS − SALTRASPASOS; AJULTELA Otros = SALOTROS − ENTRADASOTROS; AEPTEJID Otros = SALOTROS − (EXISTENCIA − INI − ENTURDIDO − ENTENGOM). Localidad = WMSLOCATIONID.
- L4: T2 verificado con la BD real (agosto 2026): los totales de URDID, AJULURD, AJULTELA y AEPTEJID que arma KardexLibroBuilder son idénticos a la hoja REs del HTML; flujo con cifras del libro (Tejido → Crudo 225,019.53). Checks: 15 tests PHP de kardex, 683 JS, typecheck, Pint, PHPStan, PHPMD, ratchet y build en verde. Sin verificación visual: la página pide login y el navegador integrado no abre towell.test.
