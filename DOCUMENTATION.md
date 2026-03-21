# 📚 NexGen Telegram Chat - Documentación Completa

Guía exhaustiva para instalar, configurar y usar el plugin NexGen Telegram Chat v3.0 en WordPress.

---

## 📑 Tabla de Contenidos

1. [Inicio Rápido](#inicio-rápido)
2. [Instalación](#instalación)
3. [Configuración Inicial](#configuración-inicial)
4. [Arquitectura del Proyecto](#arquitectura-del-proyecto)
5. [Configuración Detallada](#configuración-detallada)
6. [API Referencias](#api-referencias)
7. [Troubleshooting](#troubleshooting)
8. [Changelog](#changelog)

---

## 🚀 Inicio Rápido

### 1. Copiar Plugin
```bash
# Descargar archivos a WordPress
cp -r nexgen-telegram-chat/ /wp-content/plugins/

# O copiar manualmente vía FTP
# FTP → wp-content/plugins/nexgen-telegram-chat/
```

### 2. Activar en WordPress
```
WordPress Admin
  → Plugins
    → NexGen Telegram Chat
      → Activar
```

### 3. Configurar Telegram (Opcional)
```
WordPress Admin
  → Settings (Configuración)
    → NexGen Chat
      → 🤖 Telegram
        → Pegar Token del Bot
        → Pegar Chat ID destino
```

### 4. Configurar LLM (Gemini/OpenAI/Claude)
```
WordPress Admin
  → Settings
    → NexGen Chat
      → ⚡ IA/LLM
        → En sesión anterior: Cambiar proveedor a "Google Gemini 2.0"
        → Pegar API Key: AIzaSyAVPMLKEqV134PrXV6JnqJcOhD_g9-jZSE
        → Rellenar: Descripción del negocio
        → Clic: 🧪 Probar Conexión LLM
        → Guardar cambios
```

### 5. Prueba el Chat
```
Ir a cualquier página del sitio → Chat debe aparecer en esquina inferior derecha
Escribir nombre → Escribir mensaje → ¡Debería responder la IA!
```

---

## 📦 Instalación

### Requisitos
- **WordPress**: 5.0+
- **PHP**: 7.4+
- **MySQL**: 5.7+ or MariaDB 10.2+
- **SSL/HTTPS**: Recomendado (para seguridad)

### Instalación Manual
```bash
# 1. Descargar plugin
git clone https://github.com/... nexgen-telegram-chat
cd nexgen-telegram-chat

# 2. Copiar a WordPress
cp -r . /ruta/a/wp-content/plugins/nexgen-telegram-chat

# 3. Abrir WordPress Admin
# 4. Plugins → Activar "NexGen Telegram Chat"
```

### Instalación vía ZIP (WordPress Admin)
```
WordPress Admin
  → Plugins
    → Agregar nuevo
      → Subir plugin
        → Seleccionar ZIP
        → Instalar ahora
        → Activar
```

### Verificar Instalación
```php
// Agregar a wp-config.php para debug
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );

// Ver logs en: /wp-content/debug.log
```

---

## ⚙️ Configuración Inicial

### Panel de Administración

Ubicación: **WordPress Admin → Settings → NexGen Chat**

#### Tab 1: 🤖 Telegram
```
Token del Bot de Telegram
- Obtener de: @BotFather en Telegram
- Formato: 123456789:ABCdefGHIjklMNOpqrSTUvwxyzABCDEfg
- Requerido: NO (opcional si solo usas IA)

Chat ID Destino
- ID donde recibir notificaciones
- Obtener: Enviar /start a @userinfobot
- Formato: Número (ej: 123456789)
- Requerido: NO
```

#### Tab 2: 💬 Chat UI
```
Título del Chat
- Default: "Chat de Soporte"
- Visible para usuarios

Mensaje de Bienvenida
- Default: "¡Hola! ¿En qué puedo ayudarte?"
- Mostrado al abrir chat

Posición del Chat
- Opciones: bottom-right (recomendado), bottom-left, top-right, top-left

Retraso Auto-apertura
- Default: 20 segundos
- Tiempo antes de abrir chat automáticamente (0 = deshabilitado)
```

#### Tab 3: 🎨 Apariencia
```
Color Primario
- Default: #007cba (azul WordPress)
- Usado en botón, headers

Color Secundario
- Default: #00ba7c (verde)
- Usado en mensajes bot

Color de Fondo
- Default: #ffffff (blanco)
- Fondo de la ventana chat

Posición
- bottom-right (default)
```

#### Tab 4: ⚡ IA/LLM
```
IMPORTANTE: Esta es la sección crítica para funciones de IA

1. Habilitar Routing Híbrido
   - Checkbox para activar/desactivar sistema IA
   - Default: ✅ Activado

2. Proveedor de IA
   - OpenAI (GPT-4o-mini) ← RECOMENDADO
   - Claude 3.5 Haiku (Análisis avanzado)
   - Google Gemini 2.0 (Más rápido) ← TU OPCIÓN

3. Clave API
   - Guardar en: nexgen_llm_api_key
   - NOTA IMPORTANTE: El plugin busca esta clave en:
     a) wp-config.php (constante NEXGEN_LLM_API_KEY)
     b) WordPress option (nexgen_llm_api_key) ← Donde lo guardas
   - NO guarda en: _nexgen_llm_api_key_encrypted (CORREGIDO EN v3.2.1)

4. Contexto de Negocio
   - Descripción de tu empresa (producto, servicios)
   - Se incluye en los prompts de IA
   - Ayuda a responder preguntas relevantes

5. Tipo de Negocio
   - General, SaaS, E-commerce, Service, Agency
   - Tailoriza las respuestas de IA

6. Temas Soportados
   - Una por línea
   - Ejemplo:
     ```
     Información de producto
     Precios y planes
     Soporte técnico
     Especificaciones
     ```

7. Palabras Clave de Validación
   - Palabras para detectar si pregunta está en scope
   - Si no detecta → rechaza pregunta

8. Palabras Clave para Leads
   - Palabras que disparan workflow de leads
   - Ejemplo: "contacto", "presupuesto", "reunión"
   - Activa integración con N8N

9. Mensaje de Rechazo
   - Respuesta cuando pregunta está fuera de scope
   - Ejemplo: "Aprecio tu pregunta, pero no puedo ayudarte con eso"

10. Temperatura (Creatividad)
    - 0-1 (float)
    - 0 = Preciso/determinístico
    - 0.7 = Balanceado (DEFAULT)
    - 1 = Muy creativo/aleatorio

11. Tokens Máximos
    - Default: 500
    - Máxima longitud de respuesta
```

---

## 🏗️ Arquitectura del Proyecto

### Estructura de Carpetas
```
nexgen-telegram-chat/
├── nexgen-telegram-chat.php      (Bootstrap - punto de entrada)
├── README.md                       (Inicio rápido + intro)
├── DOCUMENTATION.md                (Este archivo - guía completa)
│
├── assets/
│   ├── chat.js                     (Frontend - polling, AJAX, UI chat)
│   └── chatbot.css                 (Estilos - responsive, colores)
│
├── includes/
│   ├── class-plugin.php            (Hooks WordPress, AJAX handlers)
│   ├── class-security.php          (Nonce, validación, rate-limiting)
│   ├── class-session-service.php   (Gestión de sesiones)
│   ├── class-message-service.php   (BD: CRUD mensajes)
│   ├── class-telegram-service.py   (API Telegram Bot)
│   ├── class-llm-service.php       (OpenAI, Claude, Gemini)
│   ├── class-llm-router.php        (Routing híbrido: LLM 80% → N8N 15% → Block 5%)
│   ├── class-context-service.php   (Contexto empresa para prompts)
│   ├── class-n8n-service.php       (Webhook N8N - integration)
│   ├── class-n8n-endpoints.php     (REST API endpoints)
│   └── admin-page.php              (Template admin settings)
│
└── [16 archivos .md consolidados en DOCUMENTATION.md]
```

### Servicio-Oriented Design

Cada clase maneja UNA responsabilidad:

```php
NexGen_Security::verify_nonce()           // Seguridad
NexGen_Session_Service::get_session_id()  // Sesiones
NexGen_Message_Service::save_message()    // BD
NexGen_LLM_Service::query()               // IA
NexGen_N8N_Service::send_to_n8n()         // N8N
NexGen_Telegram_Service::send_message()   // Telegram
```

### Flujo de Mensajes

```
1. Usuario escribe en chat widget
                 ↓
2. chat.js hace POST a admin-ajax.php
                 ↓
3. WordPress router → handle_send_message() en class-plugin.php
                 ↓
4. Verificar nonce + validar mensaje
                 ↓
5. NexGen_LLM_Router::process_message()
                 ├─ 80% → NexGen_LLM_Service::query() [Gemini/OpenAI/Claude]
                 ├─ 15% → NexGen_N8N_Service (Lead workflows)
                 └─ 5% → Blocked/Spam
                 ↓
6. Respuesta guardada en BD (wp_nexgen_chat_messages)
                 ↓
7. JavaScript polling detecta mensaje nuevo
                 ↓
8. Mostrar respuesta en chat widget
```

---

## 📝 Configuración Detallada

### 1. Configuración de Gemini (Tu caso)

#### Obtener API Key
```
1. Ir a: https://ai.google.dev/
2. Click en "Get API Key"
3. Seleccionar proyecto o crear uno
4. Copiar clave (AIzaSyA...)
5. NO compartir públicamente
```

#### Guardar en WordPress
```
WordPress Admin
→ Settings
  → NexGen Chat
    → ⚡ IA/LLM
      → Campo: "Clave API del Proveedor IA"
      → Pegar: AIzaSyAVPMLKEqV134PrXV6JnqJcOhD_g9-jZSE
      → Proveedor: "Google Gemini 2.0"
      → Guardar cambios
```

#### Verificar Funcionamiento
```
WordPress Admin
→ Settings
  → NexGen Chat
    → ⚡ IA/LLM
      → Click: "🧪 Probar Conexión LLM"
      → Debe responder con latencia + costo + respuesta ✓
```

### 2. Configuración de OpenAI (Alternativa Premium)

```
1. Ir a: platform.openai.com/account/api-keys
2. "Create new secret key"
3. Copiar (sk-proj-...)
4. Guardar en WordPress (mismo proceso que Gemini)
5. Seleccionar: "OpenAI (GPT-4o-mini)"
```

### 3. Configuración de Claude (Análisis Profundo)

```
1. Ir a: console.anthropic.com/
2. API Keys → Create
3. Copiar clave
4. Guardar en WordPress
5. Seleccionar: "Claude 3.5 Haiku"
```

### 4. Configuración de N8N (Automation - Opcional)

Ver: [N8N_IMPORT_GUIDE.md](#) (referencia, ahora en DOCUMENTATION.md)

---

## 🔌 API Referencias

### REST API Endpoints (Requieren: `Authorization: Bearer {API_KEY}`)

```php
// 1. Recibir mensaje desde N8N
POST /wp-json/nexgen/v1/n8n-message
Body: {
  session_id: "chat_site_abc123",
  message: "Hola desde N8N"
}

// 2. Obtener contexto empresa
GET /wp-json/nexgen/v1/context
Response: {
  business_name: "Mi Negocio",
  business_type: "Agency",
  description: "..."
}

// 3. Guardar repuesta bot
POST /wp-json/nexgen/v1/save-message
Body: {
  session_id: "chat_site_abc123",
  message: "Respuesta del bot",
  type: "bot"
}

// 4. Webhook respuesta
POST /wp-json/nexgen/v1/webhook-response
Body: {
  session_id: "chat_site_abc123",
  message: "Respuesta"
}
```

### AJAX Actions (Frontend)

```javascript
// Enviar mensaje
action: nexgen_send_message
  data: { message, session_id, nonce }

// Obtener mensajes
action: nexgen_get_messages
  data: { session_id, last_message_id, nonce }

// Renovar nonce (NUEVO v3.2.1)
action: nexgen_refresh_nonce
  data: {} (no requiere nonce previo)

// Guardar nombre visitor
action: nexgen_set_chat_name
  data: { name, nonce }
```

---

## 🐛 Troubleshooting

### Problema: "Nonce inválido" al escribir nombre

**Síntomas:**
- Escribir nombre → Error "Nonce inválido"
- Chat no inicia

**Solución NUEVA (v3.2.1):**
- El plugin ahora RENUEVA automáticamente nonces cada 12 horas
- Si nonce expira, JavaScript lo pide de nuevo (transparent)
- Recargar página si persiste

**Causa raíz (SOLUCIONADO):**
- Nonce localizado solo 1 vez al cargar página
- Expiraba después de ~24 horas
- Ahora se refresca automáticamente

---

### Problema: Gemini API no responde

**Síntomas:**
- Enviar mensaje → respuesta vacía o error API
- "Empty response from Gemini"

**Solución:**

1. **Verificar API Key**
   ```
   Settings → NexGen Chat → ⚡ IA/LLM
   Campo: "Clave API del Proveedor IA"
   Debe tener algo como: AIzaSyA...
   ```

2. **Test Conexión**
   ```
   Click: "🧪 Probar Conexión LLM"
   Debe mostrar respuesta + latencia
   Si falla → verificar key
   ```

3. **Check Logs** (si WP_DEBUG activo)
   ```php
   // wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   
   // Ver: /wp-content/debug.log
   // Buscar: "query_gemini", "llm_query_error"
   ```

4. **Problema CORREGIDO (v3.2.1)**
   ```
   ANTES: Plugin buscaba en _nexgen_llm_api_key_encrypted
   AHORA: Plugin busca en nexgen_llm_api_key (donde se guarda)
   ```

---

### Problema: Chat no aparece en página

**Síntomas:**
- Página cargada pero sin chat widget

**Solución:**

1. **Verificar plugin activo**
   ```
   Plugins → NexGen Chat → Activado ✓
   ```

2. **Limpiar caché**
   ```
   Si usas caché plugin:
   - Vaciar caché del plugin
   - Limpiar caché del navegador
   ```

3. **Debug modo**
   ```
   Abrir consola (F12) en navegador
   Debería mostrar:
   NexGen Chat - Configuración: {...}
   NexGen Chat - Session ID: chat_site_abc123
   ```

---

### Problema: Chat lento o no responde

**Causa:** Polling interval muy corto o servidor lento

**Solución:**

```php
// En class-plugin.php, aumentar interval
'poll_interval' => 5000  // Cambiar de 3000 a 5000 ms

// O en WordPress Settings
// (si implementa UI para esto)
```

---

### Problema: Mensajes no se guardan

**Causa:** Validación fallando o BD inaccessible

**Verificar:**

```php
// 1. Tabla existe
SELECT * FROM wp_nexgen_chat_messages LIMIT 1;

// 2. Rate limiting
// Si > 20 msgs/minuto → bloqueado
NexGen_Security::check_rate_limit($session_id)

// 3. Validación de mensaje
// Máx 1000 caracteres
// No scripts, HTML
NexGen_Security::validate_message()
```

---

## 📜 Changelog

### v3.2.1 (21 Marzo 2026) - Correcciones
```
✅ FIXED: Gemini API key lookup corrected
   - Cambiar de _nexgen_llm_api_key_encrypted a nexgen_llm_api_key
   - Permite usar API key guardada en admin panel
   - Gemini ahora funciona correctamente

✅ NEW: Nonce Refresh System
   - Nuevo endpoint: nexgen_refresh_nonce
   - JavaScript retry automático si nonce expira
   - Refresh preventivo cada 12 horas
   - Soluciona: "Nonce inválido" al inicio de chat

✅ IMPROVED: Documentación consolidada
   - 16 archivos .md ahora en 1 DOCUMENTATION.md
   - README.md simplificado con links
   - Más fácil de mantener
```

### v3.0.0 (Febrero 2026)
```
- Service-Oriented Architecture
- Hybrid LLM Router (80% LLM → 15% N8N → 5% Block)
- Multi-provider support (OpenAI, Claude, Gemini)
- Rate limiting + Security hardening
- Phase 5 completion: Message caching + pagination
```

### v2.0.0 (Enero 2026)
```
- N8N Integration (Phase 2)
- Admin Panel reorganizado en tabs
- Context Service para business prompts
```

### v1.0.0 (Diciembre 2025)
```
- Initial release
- Telegram Bot integration
- OpenAI chat support
```

---

## 📞 Soporte

**Problemas o mejoras?**

1. Revisar [Troubleshooting](#troubleshooting) section
2. Verificar logs en: `/wp-content/debug.log`
3. Activar `define('WP_DEBUG', true);` en wp-config.php

---

**Versión:** 3.2.1  
**Última actualización:** 21 Marzo 2026  
**Mantenedor:** NexGen Team
