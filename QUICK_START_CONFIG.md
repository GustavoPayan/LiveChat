# Quick Start: Admin Panel Configuration

## 🚀 Get to the Settings in 3 Clicks

```
WordPress Admin Dashboard
  └─ Settings (left sidebar)
      └─ NexGen Chat
          └─ ⚡ IA/LLM (click this tab!)
```

---

## 📝 Form Fields & What to Fill In

### 1️⃣ Enable Hybrid Routing
**Field**: `Habilitar Routing LLM Híbrido`  
**Type**: Checkbox  
**Default**: ✅ Checked (enabled)  
**Action**: Leave checked to activate LLM system  

---

### 2️⃣ Choose Your LLM Provider
**Field**: `Proveedor de IA del Negocio`  
**Type**: Select dropdown  
**Options**:
- OpenAI (GPT-4o-mini) — **Recommended** ← SELECT THIS
- Claude 3.5 Haiku — Best for analysis
- Google Gemini 2.0 — Fastest response

**What to do**: 
1. Click dropdown
2. Select "OpenAI (GPT-4o-mini) - Recomendado"
3. Copy your API key below

---

### 3️⃣ Add Your API Key
**Field**: `Clave API del Proveedor IA`  
**Type**: Password input (hidden in browser)  
**Required**: YES (plugin won't work without this)  
**Where to get**:

**For OpenAI**:
1. Go to https://platform.openai.com/account/api-keys
2. Click "Create new secret key"
3. Copy the key (looks like: `sk-proj-xxxxxxxxxxxxx`)
4. Paste in this field

**For Claude**:
1. Go to https://console.anthropic.com/
2. Click "API Keys"
3. Create and copy your key
4. Paste in field

**For Gemini**:
1. Go to https://ai.google.dev/
2. Get API key
3. Paste in field

---

### 4️⃣ Describe Your Business
**Field**: `Contexto de Tu Negocio`  
**Type**: Large textarea (6 rows)  
**Required**: YES (helps LLM understand your business)  
**Example**:
```
Somos una agencia de marketing digital que ayuda a pequeñas empresas
a crecer en redes sociales. Ofrecemos servicios de:
- Gestión de redes sociales (Instagram, Facebook, TikTok)
- Creación de contenido viral
- Publicidad pagada
- Análisis y reportes
```

**Why needed**: LLM uses this to understand scope and give relevant answers

---

### 5️⃣ Select Your Business Type
**Field**: `Tipo de Negocio`  
**Type**: Select dropdown  
**Options**: General, SaaS, E-commerce, Services, Agency  
**Choose**: The one that best matches your business  

---

### 6️⃣ List Topics You Support
**Field**: `Temas Que Tu IA Puede Manejar`  
**Type**: Textarea (one topic per line)  
**Default Suggestions**:
```
Información de producto
Precios y planes
Características
Soporte técnico
Especificaciones
```

**Your own example** (for marketing agency):
```
Gestión de redes sociales
Creación de contenido
Publicidad pagada
Análisis de métricas
Estrategia digital
```

---

### 7️⃣ Set Scope Keywords (Optional)
**Field**: `Palabras Clave de Validación`  
**Type**: Textarea (one per line)  
**Purpose**: LLM uses these to validate if question is in scope  
**Example**:
```
redes
contenido
instagram
tiktok
publicidad
facebook
```

*Leave empty if you want LLM to decide on its own*

---

### 8️⃣ Lead Detection Keywords (Optional)
**Field**: `Palabras Clave para Detectar Leads`  
**Type**: Textarea (one per line)  
**Purpose**: When user says these words, system routes to Telegram for human follow-up  
**Example**:
```
contacto
presupuesto
agendar
reunión
propuesta
contratación
```

*Leave empty to disable lead routing*

---

### 9️⃣ Custom Rejection Message (Optional)
**Field**: `Mensaje de Rechazo para Preguntas Fuera de Alcance`  
**Type**: Textarea (3 rows)  
**Purpose**: Response when user asks something LLM can't handle  
**Default** (if blank):
```
Aprecio tu pregunta, pero ese tema está fuera de mi área de conocimiento.
Por favor contacta con nuestro equipo de soporte.
```

**Custom example**:
```
Esa pregunta es sobre un tema que no puedo responder. 
Te recomendamos contactar directamente con nuestro equipo.
```

---

### 🔟 Adjust Creativity Level
**Field**: `Temperatura (Nivel de Creatividad)`  
**Type**: Range slider (0 to 1)  
**Default**: 0.7  
**Meaning**:
- 0 = Very precise, consistent answers (use for factual)
- 0.7 = Balanced (RECOMMENDED)
- 1 = Very creative, varied answers (use for brainstorming)

**What to do**: Drag slider to your preference  
*Real-time display updates as you drag*

---

### 1️⃣1️⃣ Response Length Limit
**Field**: `Tokens Máximos por Respuesta`  
**Type**: Number input  
**Default**: 500 tokens (≈ 375 words)  
**Min**: 50 | **Max**: 2000  
**Adjust if**:
- Answers too short → increase to 700-800
- Answers too long → decrease to 300-400
- Token example: "Hello" = 1 token, sentence ≈ 10 tokens

---

## ✅ Checklist Before Saving

- [x] "Habilitar Routing LLM Híbrido" is checked ✓
- [x] "Proveedor" is set to OpenAI (or Claude/Gemini)
- [x] "Clave API" is filled in (starts with sk-, sk-ant-, or AIzaSy)
- [x] "Contexto de Tu Negocio" describes what you do
- [x] "Tipo de Negocio" is selected
- [x] "Temas Soportados" has at least 3 topics
- [x] Temperature is set (default 0.7 is good)
- [x] Max Tokens looks reasonable (default 500 is good)

---

## 🧪 Test Your Connection

### After Saving Settings (click "Guardar cambios"):

1. **Look for button**: "🧪 Probar Conexión LLM"
2. **Click the button**
3. **Wait 2-3 seconds** for response
4. **You should see**:
   ```
   ✅ Conexión LLM exitosa!
   
   📝 Respuesta:
   [Sample response from LLM]
   
   ⏱️ Latencia: 450ms
   💰 Costo: $0.0015
   ```

### If You Get an Error:

| Error | Fix |
|-------|-----|
| "Clave API no configurada" | Paste your API key in the field above |
| "LLM no está habilitado" | Check the "Habilitar..." checkbox |
| "Error LLM: Invalid API Key" | Verify key is correct, copy fresh from provider |
| "Error LLM: 401 Unauthorized" | API key invalid or expired |
| "Error LLM: Rate Limited" | Too many requests, wait a minute |
| "No se puede conectar" | Check internet, firewall, VPN |

---

## 🎯 What Happens After You Save

✅ Settings stored in WordPress  
✅ Chat widget uses your LLM provider  
✅ All user messages first go to LLM  
✅ If within scope → instant response  
✅ If out of scope or lead detected → routes to Telegram  
✅ Admin sees metrics in database  

---

## 📊 Verification Steps

### In WordPress Admin
1. Go back to Settings → NexGen Chat → ⚡ IA/LLM
2. All your values should still be there
3. Password field empty (normal for security)

### On Your Website
1. Open the chat widget
2. Send test message: "What is 2+2?"
3. You should get instant response (not waiting for human)
4. Message appears immediately in chat

### In Browser Console
1. Open Chrome DevTools (F12)
2. Network tab
3. Send message in chat
4. Look for request to: `admin-ajax.php?action=nexgen_send_message`
5. Response should include: `"type":"llm"` or `"type":"telegram"`

---

## 💡 Pro Tips

1. **Test multiple times** — Different questions to see routing
2. **Monitor costs** — Check cost display in test results
3. **Adjust temperature** — Try 0.5 (precise) vs 0.9 (creative)
4. **Set keywords carefully** — More keywords = stricter scope
5. **Save often** — Click "Guardar cambios" after any edit

---

## 🚀 You're Ready!

All 11 fields are configured, test button is working, and hybrid system is active.

**Estimated time**: 5-10 minutes  
**Difficulty**: Easy (mostly copy-paste)  
**Support**: Check troubleshooting section in ADMIN_PANEL_SETUP.md  

---

**Next**: Go to WordPress Admin → Settings → NexGen Chat → ⚡ IA/LLM and start configuring! 🎉
