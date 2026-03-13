# 🚀 Guía: Importar Flujo N8N Gemini - FUNCIONA

## ✅ Archivo Correcto

**Usa:** `n8n-workflow-importable.json` (NUEVO - totalmente limpio)

**NO USES:** `n8n-gemini-workflow-FIXED.json` (tenía problemas de importación)

---

## 📋 Paso a Paso

### 1️⃣ Ir a N8N

1. Abrir tu instancia de n8n en navegador
2. Click en **"New Workflow"**

### 2️⃣ Importar el Archivo

**Opción A: Upload directo**
1. Click en el botón **≡ (Menú)** > **Import**
2. Seleccionar **"From File"**
3. Elegir `n8n-workflow-importable.json`
4. Click **"Open"**

**Opción B: Copiar/Pegar**
1. Abrir `n8n-workflow-importable.json` en editor de texto
2. Copiar todo el contenido
3. En n8n, click **≡ > Import**
4. Seleccionar **"From Clipboard"**
5. Pegar contenido
6. Click **"Import"**

### 3️⃣ Configurar Variables de Entorno

En n8n, ir a **Settings > Environment Variables**:

```
GEMINI_API_KEY=tu-api-key-de-gemini
WORDPRESS_URL=https://tu-sitio.com
WORDPRESS_API_KEY=tu-api-key-segura
```

**Cómo obtener cada una:**

| Variable | Cómo obtener |
|----------|-------------|
| `GEMINI_API_KEY` | [Google API Console](https://console.cloud.google.com) → Create API Key |
| `WORDPRESS_URL` | La URL de tu WordPress (ej: https://miempresa.com) |
| `WORDPRESS_API_KEY` | Genera con: `openssl rand -base64 32` |

### 4️⃣ Crear Webhook Credentials

En n8n:

1. Click en **Credentials** (esquina arriba-derecha)
2. Click **"Create New"**
3. Tipo: **"Header Auth"**
4. Nombre: `WordPress_Bearer`
5. Headers:
   ```
   Authorization: Bearer {{ $env.WORDPRESS_API_KEY }}
   ```
6. **Save**

### 5️⃣ Activar el Webhook

1. En el workflow, click en el nodo **"Webhook In"**
2. Click **"Start listening"**
3. Copiar la URL del webhook
4. Guardar en WordPress (admin page)

### 6️⃣ Activar el Workflow

1. Click en **"Workflow Settings"** (arriba-derecha)
2. Toggle **"Active"** = ON
3. Click **"Save"**

---

## ✅ Validación

### Test 1: Verificar que se importó

Después de importar, deberías ver:
- ✓ 11 nodos
- ✓ Conexiones entre todos
- ✓ Webhook está listening

### Test 2: Probar endpoint WordPress

```bash
curl -X GET https://tu-sitio.com/wp-json/nexgen/v1/context \
  -H "Authorization: Bearer TU-API-KEY"
```

Debería retornar:
```json
{
  "company": {...},
  "services": [...],
  "pricing": [...],
  "faq": [...]
}
```

### Test 3: Ejecutar flujo manualmente

1. En n8n, abrir el workflow
2. Click en nodo **"Webhook In"**
3. Click **"Listen and test"**
4. En terminal, ejecutar:

```bash
curl -X POST https://tu-n8n.com/webhook/livechat \
  -H "Content-Type: application/json" \
  -d '{
    "message": "¿Cuáles son vuestros servicios?",
    "session_id": "chat_test_abc123",
    "visitor": "Test User"
  }'
```

5. Ver que el flujo se ejecuta
6. Comprobar que Gemini respondió

---

## 🎯 Estructura del Flujo

```
Webhook In (recibir mensaje)
    ↓
Extract Input (parsear datos)
    ↓
Fetch Context (obtener info de WordPress)
    ↓
Extract Leads (buscar email, teléfono)
    ↓
Validate (verificar que es mensaje válido)
    ↓
Check Valid (ramificación: válido o inválido)
    ├→ Build Prompt
    │  ↓
    │  Gemini API (generar respuesta)
    │  ↓
    │  Process Response
    │  ↓
    │  Save to WordPress
    │  ↓
    │  Success Response
    │
    └→ Handle Invalid (Si mensaje no válido)
```

---

## ❌ Errores Comunes

### Error: "Import failed"
→ Verificar que el archivo JSON esté completo (no editado manualmente)

### Error: "Webhook authentication failed"
→ Verificar que `WORDPRESS_API_KEY` sea idéntico en n8n y WordPress

### Error: "Gemini API 401"
→ Verificar que `GEMINI_API_KEY` sea válido en Google Cloud

### Error: "Cannot find header auth credential"
→ Crear credencial "Header Auth" en n8n según paso 4

### El webhook no recibe mensajes
→ 1. Click en "Webhook In"
→ 2. Verificar que está en modo "Listen and test"
→ 3. Copiar URL correcta

---

## 🔧 Diferencias Entre Archivos

| Aspecto | Antiguo ❌ | Nuevo ✅ |
|---------|-----------|---------|
| Importable | NO | SÍ |
| Template strings | Rotos | Funcionales |
| bodyParametersJson | Muy complejo | Simple y limpio |
| Credenciales | Hardcodeadas | Dinámicas |
| Nodos | 13 | 11 (optimizado) |
| Error handling | Incompleto | Robusto |

---

## 📞 Si Sigues Con Problemas

1. **Revisar logs de n8n:**
   - Executions → Seleccionar ejecución → Ver detail en cada nodo

2. **Activar DEBUG en WordPress:**
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```
   Luego revisar `wp-content/debug.log`

3. **Probar Gemini directamente:**
   ```bash
   curl -X POST https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=TU-KEY \
     -H "Content-Type: application/json" \
     -d '{
       "contents": [{
         "parts": [{"text": "Hola"}]
       }]
     }'
   ```

4. **Probar endpoints WordPress:**
   ```bash
   # Context
   curl https://tu-sitio.com/wp-json/nexgen/v1/context \
     -H "Authorization: Bearer TU-KEY"
   ```

---

## ✅ Checklist Final

- [ ] Leí esta guía
- [ ] Descargué `n8n-workflow-importable.json`
- [ ] Importé en n8n
- [ ] Creé variables de entorno
- [ ] Creé credencial "Header Auth"
- [ ] Activé webhook
- [ ] Actué workflow
- [ ] Probé con curl
- [ ] Vi respuesta de Gemini
- [ ] Chat widget funciona
- [ ] Producción activa

---

**Versión:** 1.0  
**Fecha:** 13 de Marzo, 2026  
**Status:** ✅ Completamente importable
