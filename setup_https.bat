@echo off
echo ========================================
echo    CONFIGURACION HTTPS PARA LARAVEL
echo ========================================
echo.

REM Verificar si estamos en el directorio correcto
if not exist "artisan" (
    echo Error: No se encuentra el archivo artisan.
    echo Asegurate de ejecutar este script desde el directorio raiz de Laravel.
    pause
    exit /b 1
)

echo 1. Verificando dependencias...

REM Verificar PHP
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: PHP no está instalado o no está en el PATH.
    pause
    exit /b 1
)

REM Verificar OpenSSL
openssl version >nul 2>&1
if %errorlevel% neq 0 (
    echo Advertencia: OpenSSL no está disponible.
    echo Esto es necesario para generar certificados SSL.
    echo.
    echo Opciones para instalar OpenSSL:
    echo - Instalar Git for Windows (incluye OpenSSL)
    echo - Instalar OpenSSL desde https://slproweb.com/products/Win32OpenSSL.html
    echo - Usar XAMPP con SSL habilitado
    echo.
    set /p continue="¿Continuar sin OpenSSL? (s/n): "
    if /i not "%continue%"=="s" exit /b 1
)

echo ✅ PHP encontrado
echo.

echo 2. Generando certificados SSL...

if exist "ssl\localhost.crt" (
    echo Los certificados SSL ya existen.
    set /p regenerate="¿Regenerar certificados? (s/n): "
    if /i "%regenerate%"=="s" (
        cd ssl
        call generate_cert.bat
        cd ..
    )
) else (
    echo Generando nuevos certificados SSL...
    cd ssl
    call generate_cert.bat
    cd ..
)

echo.

echo 3. Configurando Laravel...

REM Verificar si existe .env
if not exist ".env" (
    echo Archivo .env no encontrado. Creando desde .env.example...
    if exist ".env.example" (
        copy ".env.example" ".env"
    ) else (
        echo Error: No se encuentra .env.example
        pause
        exit /b 1
    )
)

REM Generar APP_KEY si no existe
php artisan key:generate

echo ✅ Laravel configurado
echo.

echo 4. Configurando permisos...

REM En Windows no es necesario, pero agregamos para compatibilidad
echo ✅ Permisos configurados
echo.

echo 5. Opciones de servidor HTTPS:
echo.
echo a) Servidor PHP simple con SSL:
echo    php server_https.php
echo.
echo b) Servidor Laravel con HTTPS (requiere configuración adicional):
echo    php artisan serve --host=0.0.0.0 --port=8000
echo.
echo c) Apache/XAMPP con SSL:
echo    - Copia ssl/xampp-https.conf a tu configuración de Apache
echo    - Habilita mod_ssl en Apache
echo    - Reinicia Apache
echo.

set /p choice="Selecciona una opción (a/b/c): "

if /i "%choice%"=="a" (
    echo.
    echo Iniciando servidor HTTPS simple...
    php server_https.php
) else if /i "%choice%"=="b" (
    echo.
    echo Iniciando servidor Laravel (HTTP - configura HTTPS manualmente)...
    php artisan serve --host=0.0.0.0 --port=8000
) else if /i "%choice%"=="c" (
    echo.
    echo Para usar Apache/XAMPP con HTTPS:
    echo 1. Copia el contenido de ssl/xampp-https.conf
    echo 2. Agregalo a tu archivo de configuración de Apache (httpd.conf o httpd-ssl.conf)
    echo 3. Habilita mod_ssl: descomenta la línea #LoadModule ssl_module modules/mod_ssl.so
    echo 4. Reinicia Apache
    echo 5. Accede a https://localhost
    echo.
    pause
) else (
    echo Opción inválida.
    pause
)

echo.
echo ========================================
echo    CONFIGURACION COMPLETADA
echo ========================================
echo.
echo IMPORTANTE:
echo - Instala el certificado ssl/localhost.crt en Windows para evitar advertencias
echo - En producción, usa certificados SSL válidos de una CA confiable
echo - Configura FORCE_HTTPS=true en tu archivo .env para producción
echo.
pause
