# Política de seguridad

## Reportar una vulnerabilidad

No abras un issue público. Usa el [reporte privado de GitHub](https://github.com/francosancheztowell/towell/security/advisories/new) con:

- módulo y ruta afectados,
- pasos para reproducir,
- impacto (datos expuestos, permisos que se saltan, etc.).

Respondemos en un máximo de 5 días hábiles y avisamos cuando la corrección llegue a producción.

## Versiones con soporte

Solo la rama `main` (lo que está en producción) recibe correcciones.

## Alcance

Autenticación y sesiones, permisos por módulo (`module.permission`), inyección SQL en consultas crudas, XSS en vistas Blade/TS, exposición de datos del ERP (`TI_PRO`, `TOW_PRO`) y secretos en el repositorio.
