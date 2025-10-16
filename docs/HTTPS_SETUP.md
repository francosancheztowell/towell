# 🔒 Configuración HTTPS para Laravel

## 📋 Resumen

Esta guía te ayudará a configurar HTTPS en tu proyecto Laravel tanto para desarrollo como para producción.

## 🚀 Configuración Rápida (Desarrollo)

### Opción 1: Script Automático
```bash
# Ejecutar el script de configuración automática
setup_https.bat
```

### Opción 2: Configuración Manual

1. **Generar certificados SSL:**
   ```bash
   cd ssl
   generate_cert.bat
   ```

2. **Instalar el certificado en Windows:**
   - Doble click en `ssl/localhost.crt`
   - Seleccionar "Instalar certificado"
   - Seleccionar "Usuario actual"
   - Seleccionar "Colocar todos los certificados en el siguiente almacén"
   - Buscar "Entidades de certificación raíz de confianza"
   - Finalizar la instalación

3. **Iniciar servidor HTTPS:**
   ```bash
   php server_https.php
   ```

## 🌐 Configuración para XAMPP/Apache

### 1. Habilitar mod_ssl
En `xampp/apache/conf/httpd.conf`, descomenta:
```apache
LoadModule ssl_module modules/mod_ssl.so
Include conf/extra/httpd-ssl.conf
```

### 2. Configurar Virtual Host
Agrega el contenido de `ssl/xampp-https.conf` a tu configuración de Apache.

### 3. Reiniciar Apache
```bash
# En XAMPP Control Panel, reinicia Apache
```

## 🏭 Configuración para Producción

### 1. Variables de Entorno
En tu archivo `.env`:
```env
APP_URL=https://tu-dominio.com
FORCE_HTTPS=true
```

### 2. Certificados SSL Válidos
- Usa Let's Encrypt (gratuito)
- O certificados de una CA comercial
- NO uses certificados autofirmados en producción

### 3. Configuración del Servidor Web
#### Nginx:
```nginx
server {
    listen 443 ssl http2;
    server_name tu-dominio.com;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    root /path/to/laravel/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

#### Apache:
```apache
<VirtualHost *:443>
    ServerName tu-dominio.com
    DocumentRoot /path/to/laravel/public
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    <Directory /path/to/laravel/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## 🔧 Middleware ForceHttps

El middleware `ForceHttps` está configurado para:
- Redirigir automáticamente HTTP a HTTPS
- Agregar headers de seguridad
- Funcionar en diferentes entornos

### Configuración:
```php
// En config/force_https.php
'force_https' => env('FORCE_HTTPS', false),
'environments' => [
    'production' => true,
    'staging' => true,
    'local' => false,
],
```

## 🛡️ Headers de Seguridad

El middleware incluye headers de seguridad automáticos:
- `Strict-Transport-Security`
- `X-Content-Type-Options`
- `X-Frame-Options`
- `X-XSS-Protection`
- `Referrer-Policy`

## 🐛 Solución de Problemas

### Error: "Certificado no confiable"
**Solución:** Instala el certificado `ssl/localhost.crt` en Windows.

### Error: "OpenSSL no encontrado"
**Soluciones:**
1. Instalar Git for Windows (incluye OpenSSL)
2. Instalar OpenSSL desde https://slproweb.com/products/Win32OpenSSL.html
3. Usar XAMPP con SSL habilitado

### Error: "Puerto 8000 en uso"
**Solución:** Cambiar el puerto en los scripts o cerrar otros servicios.

### Error: "mod_ssl no encontrado" (Apache)
**Solución:** Habilitar mod_ssl en la configuración de Apache.

## 📁 Archivos Generados

```
ssl/
├── localhost.crt          # Certificado SSL
├── localhost.key          # Clave privada
├── ssl.conf              # Configuración OpenSSL
├── generate_cert.bat     # Script para generar certificados
└── xampp-https.conf      # Configuración para Apache

server_https.php          # Servidor HTTPS simple
start_https.bat           # Script para iniciar HTTPS
setup_https.bat           # Script de configuración automática
```

## 🔄 Comandos Útiles

```bash
# Generar nuevos certificados
cd ssl && generate_cert.bat

# Iniciar servidor HTTPS simple
php server_https.php

# Iniciar Laravel con HTTPS (requiere configuración adicional)
php artisan serve --host=0.0.0.0 --port=8000

# Verificar configuración SSL
openssl x509 -in ssl/localhost.crt -text -noout
```

## ⚠️ Notas Importantes

1. **Desarrollo:** Los certificados autofirmados son seguros para desarrollo local
2. **Producción:** Siempre usa certificados SSL válidos de una CA confiable
3. **Seguridad:** El middleware ForceHttps redirige automáticamente HTTP a HTTPS
4. **Performance:** HTTPS tiene un pequeño overhead, pero es necesario para seguridad
5. **SEO:** Google favorece sitios con HTTPS en los resultados de búsqueda

## 🆘 Soporte

Si tienes problemas:
1. Revisa los logs de Laravel: `storage/logs/laravel.log`
2. Verifica la configuración de Apache/Nginx
3. Comprueba que los certificados estén instalados correctamente
4. Asegúrate de que el puerto 443 (HTTPS) no esté bloqueado por firewall
