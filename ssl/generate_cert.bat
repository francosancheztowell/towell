@echo off
echo Generando certificado SSL para desarrollo local...

REM Crear archivo de configuración OpenSSL
echo [req] > ssl.conf
echo default_bits = 2048 >> ssl.conf
echo prompt = no >> ssl.conf
echo default_md = sha256 >> ssl.conf
echo distinguished_name = dn >> ssl.conf
echo req_extensions = v3_req >> ssl.conf
echo. >> ssl.conf
echo [dn] >> ssl.conf
echo C=MX >> ssl.conf
echo ST=Mexico >> ssl.conf
echo L=Mexico >> ssl.conf
echo O=Towell >> ssl.conf
echo OU=IT >> ssl.conf
echo CN=localhost >> ssl.conf
echo. >> ssl.conf
echo [v3_req] >> ssl.conf
echo basicConstraints = CA:FALSE >> ssl.conf
echo keyUsage = nonRepudiation, digitalSignature, keyEncipherment >> ssl.conf
echo subjectAltName = @alt_names >> ssl.conf
echo. >> ssl.conf
echo [alt_names] >> ssl.conf
echo DNS.1 = localhost >> ssl.conf
echo DNS.2 = 127.0.0.1 >> ssl.conf
echo IP.1 = 127.0.0.1 >> ssl.conf

REM Generar clave privada
openssl genrsa -out localhost.key 2048

REM Generar certificado
openssl req -new -x509 -key localhost.key -out localhost.crt -days 365 -config ssl.conf -extensions v3_req

echo.
echo Certificado SSL generado exitosamente!
echo Archivos creados:
echo - localhost.key (clave privada)
echo - localhost.crt (certificado)
echo.
echo Para instalar el certificado en Windows:
echo 1. Doble click en localhost.crt
echo 2. Seleccionar "Instalar certificado"
echo 3. Seleccionar "Usuario actual"
echo 4. Seleccionar "Colocar todos los certificados en el siguiente almacén"
echo 5. Buscar "Entidades de certificación raíz de confianza"
echo 6. Finalizar la instalación
echo.
pause
