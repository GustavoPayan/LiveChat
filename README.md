# � NexGen Telegram Chat - WordPress Plugin v3.0

Plugin bidireccional de chat para WordPress con integración a Telegram Bot, N8N workflows y IA Gemini.

---

## 📋 Inicio Rápido

### 1. Instalación del Plugin
```bash
# Copiar a WordPress
cp -r . /wp-content/plugins/nexgen-telegram-chat/
# Activar en WordPress Admin
```

### 2. Configurar N8N + Gemini
**Guía completa:** [N8N_IMPORT_GUIDE.md](N8N_IMPORT_GUIDE.md)

**Pasos rápidos:**
```
1. Importar: n8n-simple-workflow.json
2. Variables: GEMINI_API_KEY, WORDPRESS_URL, WORDPRESS_API_KEY
3. Activar workflow
4. Listo ✓
```

### 3. Configuración en WordPress
Panel Admin → Configuración → NexGen Chat

---

## 🏗️ Arquitectura

**Service-Oriented Design (v3.0+)**

```
includes/
├── class-plugin.php           (Hooks principales)
├── class-security.php         (Validación, rate limiting)
├── class-session-service.php  (Gestión de cookies)
├── class-message-service.php  (BD - CRUD)
├── class-telegram-service.php (API Telegram)
├── class-n8n-service.php      (Workflow N8N)
├── class-context-service.php  (Contexto empresa)
└── class-n8n-endpoints.php    (REST API)

assets/
├── chat.js         (Frontend - AJAX, polling)
└── chatbot.css     (Estilos)
```

---

## 🔌 REST API Endpoints

Todos requieren: `Authorization: Bearer {WORDPRESS_API_KEY}`

```
POST   /wp-json/nexgen/v1/n8n-message        Recibir mensajes desde N8N
GET    /wp-json/nexgen/v1/context            Obtener contexto (servicios, precios)
POST   /wp-json/nexgen/v1/save-message       Guardar respuesta bot
POST   /wp-json/nexgen/v1/webhook-response   Enviar respuesta al chat
```

---

## 📊 Flujo Completo

```
Chat Widget → WordPress → N8N Webhook → Gemini API
                ↓
          Procesar respuesta
                ↓
          Guardar en BD  
                ↓
          Mostrar en chat ✓
```

---

## 🔐 Seguridad

✅ Validación de nonce (XSS protection)
✅ Validación de mensajes (spam, length)
✅ Rate limiting (20 msgs/min)
✅ Bearer token auth
✅ Prepared statements (SQL injection)
✅ Cookies httponly (no interfiere REST API)

---

## 📁 Archivos Principales

| Archivo | Descripción |
|---------|------------|
| `nexgen-telegram-chat.php` | Bootstrap principal |
| `n8n-simple-workflow.json` | Flujo N8N (importar en n8n) |
| `N8N_IMPORT_GUIDE.md` | Guía importación N8N |
| `.env.example` | Template variables |
| `SESSION_FIX_CHANGELOG.md` | Fix sesiones PHP |

---

## 🧪 Testing

```bash
# Test API Context
curl https://tu-sitio.com/wp-json/nexgen/v1/context \
  -H "Authorization: Bearer TU-API-KEY"

# Test Gemini
curl -X POST "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=TU-KEY" \
  -H "Content-Type: application/json" \
  -d '{"contents":[{"parts":[{"text":"Hola"}]}]}'
```

---

## 📚 Documentación

- [N8N_IMPORT_GUIDE.md](N8N_IMPORT_GUIDE.md) - Guía N8N
- [SESSION_FIX_CHANGELOG.md](SESSION_FIX_CHANGELOG.md) - Fix sesiones
- [.env.example](.env.example) - Configuración

---

## ✨ Versión

- **Version:** 3.0.0+
- **PHP:** 7.4+
- **WordPress:** 5.0+
- **Status:** Production Ready ✅

---
