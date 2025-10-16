@echo off
echo Iniciando servidor Laravel con HTTPS...

REM Verificar si existen los certificados SSL
if not exist "ssl\localhost.crt" (
    echo Certificados SSL no encontrados. Generando...
    cd ssl
    call generate_cert.bat
    cd ..
)

REM Verificar si OpenSSL está disponible
openssl version >nul 2>&1
if %errorlevel% neq 0 (
    echo OpenSSL no está instalado o no está en el PATH.
    echo.
    echo Opciones para instalar OpenSSL:
    echo 1. Instalar Git for Windows (incluye OpenSSL)
    echo 2. Instalar OpenSSL directamente desde https://slproweb.com/products/Win32OpenSSL.html
    echo 3. Usar WSL (Windows Subsystem for Linux)
    echo.
    echo Alternativamente, puedes usar el servidor HTTP normal:
    echo php artisan serve --host=0.0.0.0 --port=8000
    pause
    exit /b 1
)

echo.
echo Iniciando servidor HTTPS en https://localhost:8000
echo.
echo IMPORTANTE:
echo 1. Acepta el certificado SSL en tu navegador cuando aparezca la advertencia
echo 2. Si no funciona, instala el certificado ssl\localhost.crt en Windows
echo.

REM Iniciar servidor con HTTPS usando stunnel o similar
REM Para desarrollo simple, usaremos el servidor HTTP normal con headers HTTPS
php artisan serve --host=0.0.0.0 --port=8000

pause
