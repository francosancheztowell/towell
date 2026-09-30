## Qué cambia y por qué

<!-- Módulo afectado y el problema que resuelve. Enlaza el issue o la fase de .planning si aplica. -->

## Cómo se probó

- [ ] `php artisan test`
- [ ] `npm run typecheck` y `npm run test:js`
- [ ] `composer quality` (Pint, PHPStan, PHPMD y ratchet sobre los archivos cambiados)
- [ ] Probado en pantalla (768 px / tablet si es una vista de planta)

## Checklist

- [ ] Las consultas son compatibles con SQL Server 2008 R2 (sin `OFFSET/FETCH`, `STRING_AGG`, `IIF`, `CONCAT`, `FORMAT`)
- [ ] Las rutas nuevas tienen su permiso (`module.permission`) y se probó el acceso sin él
- [ ] Sin `fetch` crudo, `onclick=` ni `<script>` inline nuevos (usar `window.http`, `window.notify` y el bundle del módulo)
- [ ] Capturas antes/después si cambia la interfaz
