---
phase: planeacion-restructuring
plan: "05"
wave: 4
depends_on: ["04"]
autonomous: false
requirements: [PT-MUT-01, PT-DOM-01, PT-DOM-02, PT-ROL-01]
files_modified:
  - app/Http/Requests/Planeacion/ProgramaTejido/*
  - app/Actions/Planeacion/ProgramaTejido/*
  - app/Data/Planeacion/ProgramaTejido/*
  - app/Services/Planeacion/ProgramaTejido/Calculations/*
  - app/Http/Controllers/Planeacion/ProgramaTejido/ProgramaTejidoController.php
  - app/Observers/ReqProgramaTejidoObserver.php
must_haves:
  truths:
    - "Cada mutación nueva valida con FormRequest y ejecuta un caso de uso transaccional."
    - "El controller deja de contener reglas, pero los endpoints/payloads legacy siguen compatibles."
    - "No se sustituye el observer completo en una sola fase."
    - "Una falla de derivados no puede devolverse como éxito silencioso."
  artifacts:
    - "DTOs/actions pequeños para edición, reprogramación y calendario."
    - "Calculadores puros con shadow comparison contra comportamiento actual."
    - "Adapters que preservan callers y endpoints legacy."
  key_links:
    - "FormRequest -> DTO -> Action -> transaction"
    - "Action -> legacy helper/observer adapter"
    - "Result -> Resource/response compatible"
---

<objective>
Extraer primero las mutaciones de menor radio de impacto a casos de uso explícitos, sin entrar aún a secuencia, grupos, balanceo, liberar o finalizar.
</objective>

<tasks>

<task type="auto">
<name>05.1 Catálogo de mutaciones y ownership</name>
<files>.planning/planeacion-restructuring/MUTATION-MATRIX.md</files>
<action>Para cada endpoint/caller registrar tablas leídas/escritas, observer on/off, transacción, derivados, locks, respuesta, idempotencia y rollback. Clasificar simple, secuencia, grupo, balance, boundary operativo o integración.</action>
<verify>Cada POST/PUT/PATCH/DELETE inventariado en plan 01 tiene categoría y owner.</verify>
<done>Ninguna mutación se mueve sin conocer efectos y consumidores.</done>
</task>

<task type="auto">
<name>05.2 Extraer validación y DTOs</name>
<files>app/Http/Requests/Planeacion/ProgramaTejido/*, app/Data/Planeacion/ProgramaTejido/*</files>
<action>Empezar por edición inline, reprogramación y calendario. Mantener nombres de payload legacy mediante mapeo de borde; normalizar IDs/texto/fechas una vez. Las reglas que dependen de superficie consultan capabilities.</action>
<verify>Contract tests prueban payloads actuales, 422, límites físicos Programa/Muestras y autorización.</verify>
<done>Controller recibe input validado/tipado sin cambiar el cliente legacy.</done>
</task>

<task type="auto">
<name>05.3 Actions transaccionales simples</name>
<files>app/Actions/Planeacion/ProgramaTejido/UpdateField.php, app/Actions/Planeacion/ProgramaTejido/Reprogram.php, app/Actions/Planeacion/ProgramaTejido/ChangeCalendar.php</files>
<action>Extraer un caso de uso por archivo. La transacción abarca cabecera y derivados que deban ser atómicos. Mantener helpers/observer como adapters; eliminar catches silenciosos solo donde los tests demuestren rollback/respuesta correcta.</action>
<verify>Inyectar falla en cada dependencia y comprobar rollback + error observable; health check posterior.</verify>
<done>Las tres familias simples pueden activarse individualmente por flag y volver al handler legacy.</done>
</task>

<task type="auto">
<name>05.4 Calculadores puros y comparación</name>
<files>app/Services/Planeacion/ProgramaTejido/Calculations/*, tests/Unit/Planeacion/ProgramaTejido/*</files>
<action>Extraer funciones deterministas de fórmula/formato sin escribir BD. Ejecutar old/new con mismos inputs en tests y, opcionalmente, shadow sampling. No cambiar redondeos ni nombres; documentar divergencias antes de corregirlas.</action>
<verify>Matriz de casos reales/límite produce resultados idénticos o una decisión aprobada.</verify>
<done>La lógica pura deja de duplicarse gradualmente sin mover todavía la orquestación crítica.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
<name>05.5 Canary de mutaciones simples</name>
<action>Habilitar una familia por vez para usuarios canary; comparar responses, logs e invariantes antes de avanzar.</action>
<verify>Al menos un ciclo de uso acordado, cero rollback inesperado y rollback de flag probado.</verify>
<done>Se autoriza entrar a mutaciones de secuencia/grupo.</done>
</task>

</tasks>

<verification>
- Contract tests legacy/v2 por endpoint.
- Unit tests de calculadores y Feature tests transaccionales.
- Health check before/after de cada familia.
- Muestras probada o marcada explícitamente no soportada por capability.
- Controllers reducidos sin crear un `ProgramaTejidoService` monolítico.
</verification>

<success_criteria>
Las mutaciones simples tienen entradas, transacciones, resultados y errores explícitos; sus endpoints siguen compatibles y el observer permanece como adaptador controlado.
</success_criteria>

<rollback>
Flags por familia vuelven al controller/service legacy. No hay dual-write ni cambios destructivos de schema.</rollback>

