# Configuración de la API de Telegram

## 📋 Descripción
Este proyecto incluye integración con la API de Telegram para enviar mensajes desde la aplicación.

## 🚀 Cómo configurar

### 1. Crear un Bot de Telegram

1. Abre Telegram y busca el usuario **@BotFather**
2. Envía el comando `/newbot`
3. Sigue las instrucciones para elegir un nombre y username para tu bot
4. BotFather te proporcionará un **token** (algo como: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)
5. Guarda este token, lo necesitarás para la configuración

### 2. Obtener el Chat ID

⚠️ **IMPORTANTE:** El `chat_id` NO es tu número de teléfono. Es un identificador único que Telegram asigna a cada chat.

#### Método Recomendado: Usar la ruta de la aplicación
1. Asegúrate de tener el `TELEGRAM_BOT_TOKEN` configurado en tu `.env`
2. Envía un mensaje a tu bot en Telegram (busca tu bot y escribe cualquier cosa, por ejemplo: `/start`)
3. Visita esta URL en tu navegador (debes estar autenticado):
   ```
   http://localhost:8000/telegram/get-chat-id
   ```
4. Verás una lista de `chat_id` disponibles. Copia el que corresponda a tu usuario.

#### Método Alternativo: Usar la API de Telegram directamente
1. Busca tu bot en Telegram por el username que le diste
2. Envíale cualquier mensaje (por ejemplo: `/start`)
3. Visita la siguiente URL en tu navegador (reemplaza TU_TOKEN con el token que te dio BotFather):
   ```
   https://api.telegram.org/bot8202582254:AAHW_BsOuWZ1BeQqjdndgeNIplS6Ptf-wG4/getUpdates
   ```
4. Busca el campo `"chat":{"id":123456789}` - ese número es tu `chat_id`
5. **NOTA:** El número de teléfono (2223217136) NO es el chat_id. Debes usar el número que aparece en `chat.id`

#### Para grupos
1. Agrega el bot a un grupo de Telegram
2. Envía un mensaje en el grupo
3. Usa la misma URL del método alternativo
4. El chat_id de un grupo comienza con `-` (ejemplo: `-123456789`)

### 3. Configurar variables de entorno

Agrega las siguientes líneas a tu archivo `.env`:

```env
# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=8202582254:AAHW_BsOuWZ1BeQqjdndgeNIplS6Ptf-wG4
TELEGRAM_CHAT_ID=TU_CHAT_ID_AQUI
```

**⚠️ IMPORTANTE:** 
- El `TELEGRAM_CHAT_ID` **NO es tu número de teléfono** (2223217136)
- Es un identificador numérico único que Telegram asigna a cada chat
- Debes obtenerlo siguiendo el paso 2 (Obtener el Chat ID)
- El número de teléfono 2223217136 es solo para referencia, pero necesitas el `chat_id` real

**Pasos para obtener tu chat_id:**
1. Envía un mensaje a tu bot en Telegram
2. Visita: `http://localhost:8000/telegram/get-chat-id` (o usa la API directamente)
3. Copia el `chat_id` que aparece y reemplázalo en el `.env`

### 4. Limpiar caché de configuración

Ejecuta en la terminal:

```bash
php artisan config:clear
```

## 📁 Estructura de archivos creados

```
app/Http/Controllers/Telegram/
└── TelegramController.php       # Controlador para manejar envío de mensajes

config/
└── services.php                 # Configuración de servicios (incluye Telegram)

routes/
└── web.php                      # Rutas de la API de Telegram
```

## 🔧 Uso

### En la vista de Reporte de Fallos y Paros

El botón verde **"Enviar"** en la barra superior envía un mensaje "Hola Mundo 👋" a Telegram.

### Endpoints disponibles

1. **Enviar mensaje**
   - Ruta: `/telegram/send`
   - Método: `POST`
   - Body (JSON):
     ```json
     {
       "mensaje": "Tu mensaje aquí"
     }
     ```

2. **Obtener información del bot**
   - Ruta: `/telegram/bot-info`
   - Método: `GET`

## ✅ Verificación

Para verificar que todo funciona:

1. Asegúrate de que las variables de entorno estén configuradas
2. Limpia la caché: `php artisan config:clear`
3. Ve a la página de Reporte de Fallos y Paros
4. Haz clic en el botón verde "Enviar"
5. Deberías recibir un mensaje en Telegram y ver una notificación de éxito

## 🔍 Solución de problemas

### Error: "Credenciales de Telegram no configuradas"
- Verifica que las variables `TELEGRAM_BOT_TOKEN` y `TELEGRAM_CHAT_ID` estén en tu archivo `.env`
- Ejecuta `php artisan config:clear`

### Error: "No se pudo enviar el mensaje"
- Verifica que el token del bot sea correcto
- Verifica que hayas enviado al menos un mensaje al bot (paso 2)
- Verifica que el chat_id sea correcto

### El mensaje no llega
- Asegúrate de haber iniciado una conversación con el bot enviando `/start`
- Si usas un grupo, asegúrate de que el bot esté agregado al grupo
- Verifica los logs de Laravel: `storage/logs/laravel.log`

## 📚 Recursos adicionales

- [Documentación oficial de Telegram Bot API](https://core.telegram.org/bots/api)
- [Guía de BotFather](https://core.telegram.org/bots#6-botfather)

