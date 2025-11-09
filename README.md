# Towell

Sistema de gestión de producción y planificación empresarial para la industria textil.

## Descripción

Towell es una aplicación web desarrollada con Laravel que proporciona un sistema integral para la gestión y planificación de procesos de producción en la industria textil. El sistema permite administrar módulos de planeación, tejido, urdido, engomado, atadores, tejedores, mantenimiento y configuración, con un sistema de permisos granular basado en roles de usuario.

## Características Principales

### Módulos del Sistema

- **Planeación**: Gestión de programa de tejido, catálogos (telares, eficiencia, velocidad, calendarios, aplicaciones), matriz de hilos, codificación de modelos, altas especiales y pronósticos
- **Tejido**: Inventario de telas, secuencias de inventario, marcas finales, requerimientos de trama, cortes de eficiencia, producción de reenconado cabezuela
- **Urdido y Engomado**: Programa de urdido y engomado, reservar y programar telares, programación de requerimientos
- **Atadores**: Gestión de atadores y programación de requerimientos
- **Tejedores**: Configuración y gestión de procesos de tejedores
- **Mantenimiento**: Herramientas de mantenimiento del sistema
- **Configuración**: Gestión de usuarios, módulos del sistema, permisos, cargar planeación

### Funcionalidades Adicionales

- Sistema de autenticación con número de empleado y contraseña
- Autenticación por código QR
- Sistema de permisos por módulo (acceso, crear, modificar, eliminar, registrar)
- Gestión dinámica de módulos y submódulos
- Importación de datos desde archivos Excel
- Interfaz de usuario responsiva
- Aplicación Web Progresiva (PWA)
- Gestión de folios y secuencias
- Gestión de turnos de producción
- Caché de módulos y permisos para optimización

## Requisitos del Sistema

### Servidor

- PHP >= 8.2
- Composer
- Node.js >= 18.x
- npm o yarn
- Servidor web (Apache/Nginx)
- SQL Server (recomendado) o base de datos compatible
- Extensiones PHP: pdo_sqlsrv, sqlsrv, mbstring, xml, curl, zip, gd

### Base de Datos

- SQL Server (configuración por defecto)
- Se requiere conexión a base de datos SQL Server con las tablas correspondientes

## Instalación

### 1. Clonar el Repositorio

```bash
git clone <url-del-repositorio>
cd Towell
```

### 2. Instalar Dependencias de PHP

```bash
composer install
```

### 3. Instalar Dependencias de Node.js

```bash
npm install
```

### 4. Configurar Variables de Entorno

Copiar el archivo de ejemplo de variables de entorno:

```bash
cp .env.example .env
```

Editar el archivo `.env` y configurar:

- `APP_NAME`: Nombre de la aplicación
- `APP_ENV`: Entorno (local, staging, production)
- `APP_DEBUG`: Modo debug (true/false)
- `APP_URL`: URL de la aplicación
- `DB_CONNECTION`: Conexión de base de datos (sqlsrv)
- `DB_HOST`: Host de la base de datos
- `DB_PORT`: Puerto de la base de datos
- `DB_DATABASE`: Nombre de la base de datos
- `DB_USERNAME`: Usuario de la base de datos
- `DB_PASSWORD`: Contraseña de la base de datos

### 5. Generar Clave de Aplicación

```bash
php artisan key:generate
```

### 6. Ejecutar Migraciones

```bash
php artisan migrate
```

### 7. Compilar Assets

Para desarrollo:

```bash
npm run dev
```

Para producción:

```bash
npm run build
```

### 8. Configurar Permisos de Almacenamiento

```bash
php artisan storage:link
```

Asegurar permisos de escritura en los directorios:

```bash
chmod -R 775 storage bootstrap/cache
```

## Estructura del Proyecto

```
Towell/
├── app/
│   ├── Console/          # Comandos de Artisan
│   ├── Exceptions/       # Manejo de excepciones
│   ├── Helpers/          # Funciones auxiliares
│   ├── Http/
│   │   ├── Controllers/  # Controladores de la aplicación
│   │   ├── Middleware/   # Middleware personalizado
│   │   └── Requests/     # Form requests de validación
│   ├── Imports/          # Clases de importación de Excel
│   ├── Models/           # Modelos de Eloquent
│   ├── Observers/        # Observadores de modelos
│   ├── Providers/        # Service providers
│   ├── Services/         # Servicios de la aplicación
│   └── Traits/           # Traits reutilizables
├── bootstrap/            # Archivos de arranque
├── config/               # Archivos de configuración
├── database/
│   ├── migrations/       # Migraciones de base de datos
│   ├── seeders/          # Seeders de base de datos
│   └── scripts/          # Scripts de utilidad
├── public/               # Archivos públicos
├── resources/
│   ├── css/              # Estilos CSS
│   ├── js/               # JavaScript
│   └── views/            # Vistas Blade
├── routes/               # Definición de rutas
├── storage/              # Archivos de almacenamiento
└── tests/                # Pruebas automatizadas
```

## Configuración

### Base de Datos

El sistema utiliza SQL Server como base de datos principal. Asegúrese de tener configurada la conexión en el archivo `.env`:

```
DB_CONNECTION=sqlsrv
DB_HOST=tu-servidor-sql
DB_PORT=1433
DB_DATABASE=nombre_base_datos
DB_USERNAME=usuario
DB_PASSWORD=contraseña
```

### Autenticación

El sistema utiliza autenticación personalizada basada en el modelo `Usuario`. Los usuarios se autentican mediante número de empleado y contraseña. También se soporta autenticación por código QR.

### Permisos

El sistema implementa un sistema de permisos granular por módulo. Cada usuario puede tener permisos específicos (acceso, crear, modificar, eliminar, registrar) para cada módulo del sistema.

## Uso

### Acceso al Sistema

1. Navegar a la URL de la aplicación
2. Ingresar número de empleado y contraseña
3. O utilizar código QR para autenticación rápida

### Navegación

El sistema presenta una interfaz modular donde los usuarios pueden acceder a los módulos según sus permisos. Los módulos se organizan jerárquicamente:

- Módulos principales (Nivel 1)
- Submódulos (Nivel 2)
- Submódulos de nivel 3

### Importación de Datos

El sistema permite importar datos desde archivos Excel para varios módulos:

- Catálogo de telares
- Eficiencia estándar
- Velocidad estándar
- Calendarios
- Aplicaciones
- Programa de tejido

## Desarrollo

### Comandos Útiles

Ejecutar servidor de desarrollo:

```bash
php artisan serve
```

Ejecutar en modo desarrollo completo (servidor, cola, logs, vite):

```bash
composer dev
```

Limpiar caché:

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### Estructura de Código

El proyecto sigue las convenciones de Laravel:

- Controladores en `app/Http/Controllers`
- Modelos en `app/Models`
- Vistas en `resources/views`
- Rutas en `routes/web.php`
- Middleware en `app/Http/Middleware`

### Helpers

El proyecto incluye helpers personalizados:

- `FolioHelper`: Gestión de folios y secuencias
- `TurnoHelper`: Gestión de turnos de producción
- `StringTruncator`: Truncado de strings según límites de base de datos
- `format_helpers.php`: Funciones de formateo
- `permission-helpers.php`: Funciones de permisos

## Dependencias Principales

### Backend

- Laravel Framework ^12.0
- Maatwebsite Excel ^3.1
- Twilio SDK ^8.4

### Frontend

- Tailwind CSS ^4.1.14
- Vite ^6.0.11
- Axios ^1.7.4

## Seguridad

- Autenticación mediante sesiones
- Validación de entrada en formularios
- Protección CSRF en todas las rutas
- Sanitización de datos de entrada
- Contraseñas hasheadas (soporte para texto plano durante migración)

## Mantenimiento

### Logs

Los logs de la aplicación se almacenan en `storage/logs/laravel.log`.

### Backup

Se recomienda realizar backups regulares de:

- Base de datos
- Archivos subidos en `storage/app/public`
- Archivos de configuración en `config/`

### Actualizaciones

Para actualizar las dependencias:

```bash
composer update
npm update
```

## Soporte

Para problemas o consultas, contactar al equipo de desarrollo.

## Licencia

Este proyecto es software propietario. Todos los derechos reservados.

## Versión

Versión actual: 1.0.0

## Autores

Equipo de Desarrollo Towell

## Changelog

Ver archivo CHANGELOG.md para historial de cambios.

## Notas Adicionales

- El sistema está diseñado para funcionar como Progressive Web App (PWA)
- Requiere conexión a SQL Server para funcionamiento completo
- Los permisos se cachean por 24 horas para optimización
- El sistema soporta múltiples conexiones de base de datos
