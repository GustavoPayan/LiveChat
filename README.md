# 🚀 NexGen Telegram Chat - WordPress Plugin v3.2.1

Plugin bidireccional de chat para WordPress con integración a Telegram Bot, N8N workflows e IA (Gemini, OpenAI, Claude).

**Última actualización:** 21 Marzo 2026 - Correcciones de Gemini API + Nonce Refresh System

---

## ⚡ Inicio en 3 Minutos

```bash
# 1. Copiar a WordPress
cp -r nexgen-telegram-chat /wp-content/plugins/

# 2. Activar en WordPress Admin
Plugins → NexGen Telegram Chat → Activar

# 3. Configurar IA (Gemini recomendado)
Settings → NexGen Chat → ⚡ IA/LLM
  • Proveedor: Google Gemini 2.0
  • Clave API: AIzaSyA...
  • Descripción negocio: Tu empresa...
  • Guardar cambios ✓

# 4. Test
Abrir website → Chat debe aparecer
Escribir nombre → Escribir mensaje → 🤖 Responde IA
```

---

## 📚 Documentación Completa

**IMPORTANTE:** Para guía EXHAUSTIVA, ver: **[DOCUMENTATION.md](DOCUMENTATION.md)**

Contiene secciones sobre:
- ✅ Instalación paso a paso
- ✅ Configuración detallada de todas las opciones
- ✅ Gemini, OpenAI, Claude setup
- ✅ Arquitectura del proyecto
- ✅ API referencias completas
- ✅ Troubleshooting detallado
- ✅ Changelog v1.0 → v3.2.1

---

## 🎯 Características Principales

✅ **Chat en Vivo** - Widget flotante responsive  
✅ **IA Inteligente** - Responde automáticamente con contexto  
✅ **Multi-Proveedor IA** - Gemini, OpenAI, Claude  
✅ **Seguridad Enterprise** - Nonce, rate limiting, validation  
✅ **Histórico Conversaciones** - Base de datos con búsqueda  
✅ **N8N Integration** - Workflows avanzados (opcional)  
✅ **Telegram Bot** - Recibe/envía desde Telegram  
✅ **Responsive Mobile** - Funciona en cualquier dispositivo  

---

## 🔧 Configuración Rápida

### Google Gemini (Recomendado - MÁS RÁPIDO)
```
1. Ir a: https://ai.google.dev/
2. Click: Get API Key
3. Copiar clave (AIzaSyA...)
4. WordPress Admin → Settings → NexGen Chat → ⚡ IA/LLM
5. Pegar en: "Clave API del Proveedor IA"
6. Cambiar a: "Google Gemini 2.0"
7. Rellenar: Descripción negocio
8. Click: 🧪 Probar Conexión LLM (must work!)
9. Guardar cambios ✓
```

### OpenAI (Premium - MÁS PRECISO)
```
1. https://platform.openai.com/account/api-keys
2. Create new secret key
3. Copiar (sk-proj-...)
4. Mismo proceso que Gemini
```

### Claude (Análisis Avanzado)
```
1. https://console.anthropic.com/
2. API Keys → Create new key
3. Mismo proceso
```

---

## ✅ Qué se Corrigió en v3.2.1

### 1. Gemini API Funciona Correctamente
```
ANTES: Plugin buscaba _nexgen_llm_api_key_encrypted
AHORA: Plugin busca nexgen_llm_api_key (correcto)
RESULTADO: Gemini responde mensajes sin errores
```

### 2. "Nonce Inválido" Solucionado
```
ANTES: Error después de ~24h sin recargar
AHORA: System refresh automático + retry
RESULTADO: Chat siempre funciona sin interrupciones
```

### 3. Documentación Consolidada
```
ANTES: 16 archivos .md confusos
AHORA: 1 archivo DOCUMENTATION.md completo
RESULTADO: Proyecto más limpio y mantenible
```

---

## 📋 Requisitos Mínimos

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+ o MariaDB 10.2+
- API Key (Gemini gratis o pago, OpenAI pago, Claude pago)

---

## 🐛 Soporte / Problemas

**¿Chat no funciona?** → Ver [DOCUMENTATION.md → Troubleshooting](DOCUMENTATION.md#-troubleshooting)

**¿API key no funciona?** → 
1. Verificar que está copiada correctamente
2. Click "🧪 Probar Conexión LLM" en admin
3. Si falla, revisar logs: `/wp-content/debug.log`

**¿Nonce error?** → Plugin ahora lo maneja automáticamente (v3.2.1+)

---

## 📦 Estructura Simplificada

```
nexgen-telegram-chat/
├── README.md                            ← Estás aquí (guía rápida)
├── DOCUMENTATION.md                     ← Guía COMPLETA
├── nexgen-telegram-chat.php             (Punto de entrada)
├── assets/
│   ├── chat.js                          (Frontend interactivo)
│   └── chatbot.css                      (Estilos)
└── includes/
    ├── class-plugin.php                 (Hooks + handlers AJAX)
    ├── class-llm-service.php            (Gemini/OpenAI/Claude)
    ├── class-security.php               (Validación + rate limit)
    ├── class-message-service.php        (BD: CRUD)
    └── ... (10 service classes más)
```

---

## 🔐 Seguridad Incluida

✅ Validación de nonce (previene XSS)  
✅ Sanitización de mensajes  
✅ Rate limiting (20 msgs/min)  
✅ SQL injection protection (prepared statements)  
✅ Bearer token auth (REST API)  

---

## 📞 Más Información

- **Documentación Completa:** [DOCUMENTATION.md](DOCUMENTATION.md)
- **Instalación**: [DOCUMENTATION.md#-instalación](DOCUMENTATION.md#-instalación)
- **Troubleshooting:** [DOCUMENTATION.md#-troubleshooting](DOCUMENTATION.md#-troubleshooting)
- **Changelog Completo:** [DOCUMENTATION.md#-changelog](DOCUMENTATION.md#-changelog)

---

**Versión:** 3.2.1  
**Licencia:** GPL-2.0+  
**Mantenimiento:** Activo ✅
