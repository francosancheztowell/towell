# Original User Request

## Initial Request — 2026-10-09T16:04:56Z

Auditoría técnica exhaustiva y diagnóstico integral de deuda técnica sobre todos los controladores (`app/Http/Controllers`) y modelos (`app/Models`) del proyecto Laravel Towell, evaluando código muerto/obsoleto, segmentación arquitectónica y brechas en la suite de pruebas (`tests/`).

Working directory: c:/xampp/htdocs/Towell
Integrity mode: development

## Requirements

### R1. Auditoría de código muerto y redundante (Dead Code & Waste Detection)
Analizar la totalidad de archivos en `app/Http/Controllers` y `app/Models` para identificar métodos inalcanzables o no referenciados en rutas (`routes/web.php`, `routes/public.php`, `routes/ai.php`), funciones obsoletas, consultas duplicadas y modelos, scopes o relaciones que ya no se utilicen en el sistema.

### R2. Diagnóstico de segmentación y arquitectura (Controller Bloat & Technical Debt)
Evaluar el acoplamiento y la distribución de responsabilidades en los controladores según las directrices del proyecto (controladores delgados, delegación a `Services` y `Repositories`, uso de `Form Requests` para validación). Clasificar los controladores mediante un semáforo de deuda técnica (Crítico, Medio, Aceptable) detallando métricas de líneas de código, complejidad ciclomática y mezcla de lógica de negocio o transaccional directa.

### R3. Auditoría de la suite de pruebas y brechas de cobertura (Test Suite & Coverage Gap Analysis)
Correlacionar los controladores y modelos existentes con la suite de pruebas en `tests/Unit` y `tests/Feature`. Identificar qué controladores de alto impacto o flujos críticos carecen completamente de pruebas, cuáles poseen pruebas superficiales o frágiles, y evaluar el estado actual de ejecución de los tests del proyecto.

### R4. Informe consolidado y plan de saneamiento priorizado (Actionable Remediation Plan)
Sintetizar todos los hallazgos en un reporte estructurado y verificable (`audit_deuda_tecnica_controllers_models.md`), presentando un inventario cuantitativo, el top de componentes con mayor riesgo operativo, y un plan de acción por fases (P1 Inmediato, P2 Mediano plazo, P3 Mejora continua) con recomendaciones arquitectónicas específicas para su refactorización segura.

## Acceptance Criteria

### Cobertura del análisis
- [ ] La auditoría abarca el 100% de los controladores en `app/Http/Controllers` y modelos en `app/Models`.
- [ ] Cada hallazgo de código muerto o redundante incluye la ruta del archivo, método o propiedad específica, y evidencia de no utilización comprobable en el codebase.

### Evaluación arquitectónica
- [ ] Se genera una tabla comparativa clasificando los controladores con métricas objetivas (líneas de código, número de métodos, uso de servicios vs consultas directas en el controller).
- [ ] Se identifican específicamente los controladores con sobrecarga crítica (ej. controladores con más de 300-500 líneas o lógica de negocio acoplada) con la propuesta de extracción a Services/Repositories.

### Diagnóstico de testing
- [ ] Se documenta el mapa de correlación entre controladores/modelos y sus archivos de prueba correspondientes en `tests/`.
- [ ] Se lista explícitamente el porcentaje o inventario de controladores y modelos sin cobertura de pruebas, priorizados por criticidad de negocio.

### Entregable final
- [ ] Se crea el archivo `audit_deuda_tecnica_controllers_models.md` en la raíz del proyecto con la estructura completa: Resumen ejecutivo, Métricas globales, Inventario de código muerto, Diagnóstico de segmentación, Mapa de brechas de testing y Plan de acción priorizado.
