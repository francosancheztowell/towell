# Guia de lectura para desarrolladores

## Objetivo

Orientar a nuevos desarrolladores o personal de soporte tecnico sobre como leer la documentacion del proyecto y por donde iniciar segun el tipo de cambio requerido.

## Si vas a trabajar en...

| Necesidad | Punto de inicio recomendado |
| --- | --- |
| login, acceso o permisos | `00-fase-publica.md`, `01-navigation.md`, `09-configuracion.md` |
| programa de tejido o muestras | `02-planeacion.md` |
| inventario de telares, trama, marcas o eficiencia | `03-tejido.md` |
| BPM o desarrolladores de tejedores | `04-tejedores.md` |
| urdido | `05-urdido.md` |
| engomado o formulacion | `06-engomado.md` |
| atadores | `07-atadores.md` |
| reserva de inventario y ordenes URD/ENG | `08-programa-urd-eng.md` |
| administracion de usuarios, modulos o secuencias | `09-configuracion.md` |
| paros y fallas | `10-mantenimiento.md` |
| mensajeria | `11-telegram.md` |

## Reglas practicas del sistema

- Planeacion es la fase mas transversal; muchos cambios terminan impactando `ReqProgramaTejido`.
- `ReqProgramaTejidoObserver` y `ProduccionTrait` tienen impacto alto porque recalculan o comparten comportamiento entre modulos.
- `Programa Urd Eng`, `Urdido` y `Engomado` forman una cadena funcional; revisar siempre las tres fases si tocas ordenes o produccion.
- `SYSMensaje` y Telegram son soporte transversal de notificaciones.

## Anexos recomendados

- `README.md` para el mapa general tecnico
- `MATRIZ-TECNICA-RUTAS.md` para localizar rapidamente rutas, controladores y archivos
