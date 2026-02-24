# N8N Quick Start - 5 Minutes Setup

## 🚀 Fastest Way to Test (No API Keys Needed)

### Step 1: Create N8N Workflow (3 minutes)

1. Go to **n8n.cloud** (register free) OR run locally
2. Click **"+ New Workflow"**
3. Name it: `LiveChat Auto-Response`
4. Click **"Import from URL/JSON"** or **File → Import**
5. **Paste this JSON** (copy from `n8n-workflow-export.json` in repo):

```json
{
  "nodes": [
    {
      "parameters": {"path": "livechat", "httpMethod": "POST"},
      "id": "webhook",
      "name": "Webhook",
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 1,
      "position": [250, 300]
    },
    {
      "parameters": {
        "functionCode": "const input = $input.all()[0];\nconst msg = (input.json?.message || '').toLowerCase();\n\nconst responses = {\n  'hosting': '🌐 Nuestros planes de hosting incluyen SSL y soporte 24/7. ¿Cuál es tu presupuesto?',\n  'seo': '📈 Podemos posicionar tu sitio en Google. ¿En qué sector trabajas?',\n  'dominio': '🔗 Registramos dominios en todas las extensiones. ¿Qué nombre buscas?',\n  'precio': '💰 Los planes comienzan en €500/mes. ¿Necesitas un presupuesto personalizado?'\n};\n\nlet reply = '👋 Hola! Gracias por contactar. Pregunta sobre: hosting, seo, dominio, precio';\n\nfor (const [keyword, text] of Object.entries(responses)) {\n  if (msg.includes(keyword)) {\n    reply = text;\n    break;\n  }\n}\n\nreturn {\n  response: reply\n};"
      },
      "id": "function",
      "name": "Generate Response",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [650, 300]
    },
    {
      "parameters": {"response": "noData"},
      "id": "response",
      "name": "Webhook Response",
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1,
      "position": [1050, 300]
    }
  ],
  "connections": {
    "Webhook": {"main": [[{"node": "function"}]]},
    "function": {"main": [[{"node": "response"}]]}
  },
  "active": false
}
```

6. Click **Save**
7. Click **Activate** (turn ON)

### Step 2: Get Webhook URL (30 seconds)

1. Click on **Webhook node** (the first one)
2. Copy the **Webhook URL** → Looks like:
   ```
   https://n8n.yourinstance.com/webhook/abc123
   ```

### Step 3: Configure WordPress Plugin (90 seconds)

1. Go to WordPress Admin → **NexGen Livechat**
2. Scroll to **"🤖 Automatización N8N"**
3. Fill in:
   - ✅ **Habilitar N8N**: Check it
   - **URL del Webhook N8N**: Paste the webhook URL from Step 2
   - **API Key**: Leave blank (optional)
   - **Timeout**: 10 seconds
   - **Keywords** (paste these):
     ```
     hosting
     seo
     dominio
     precio
     desarrollo
     wordpress
     marketing
     ssl
     ```
4. Click **Save Changes**

### Step 4: Test (1 minute)

**Test in chat widget:**
1. Open your website with the LiveChat widget
2. Type a message: "¿Tienes hosting?" (contains keyword "hosting")
3. Wait 2-3 seconds
4. See auto-response: "🌐 Nuestros planes de hosting..."

**If not working:**
1. Check N8N workflow is **Activated** (toggle ON)
2. Check webhook URL is correct in WordPress settings
3. Open N8N → Click **Test** on Webhook node
4. Send this test:
```json
{
  "message": "¿Tienes hosting?",
  "session_id": "chat_test_001",
  "visitor": "Juan",
  "site": "Test",
  "timestamp": "2026-02-23 10:00:00"
}
```

---

## 🤖 Add AI Model (Optional - For Better Responses)

### Using OpenAI (Free $5 Credit)

1. Get API key: https://platform.openai.com/api-keys
2. In N8N workflow, replace the **Generate Response** node with:
   - Delete current node
   - Add → **OpenAI** node
   - Paste API key
   - Set model: `gpt-3.5-turbo`
   - Prompt:
   ```
   You are a helpful support agent for a web development company.
   Customer: {{ $json.visitor }}
   Message: {{ $json.message }}
   
   Respond helpfully in 1-2 sentences in the same language.
   ```
   - Connect to HTTP Response

3. Save & Activate

---

## 📊 What Keywords Trigger N8N

Messages containing ANY of these trigger N8N:
- hosting
- seo
- dominio
- precio
- desarrollo
- wordpress
- marketing
- ssl

Other messages go directly to **Telegram human support**.

---

## ✅ Testing Checklist

- [ ] N8N workflow created and activated
- [ ] Webhook URL copied from N8N
- [ ] WordPress settings updated with webhook URL
- [ ] Keywords configured in WordPress
- [ ] Test message sent in chat
- [ ] Auto-response received in chat
- [ ] Telegram still receives non-matching messages

---

## Example Flow

```
User: "¿Tienes hosting?"
         ↓
    (Contains keyword "hosting")
         ↓
    Routes to N8N
         ↓
Generate Response node processes
         ↓
Returns: "🌐 Nuestros planes de hosting..."
         ↓
User sees auto-response in chat
```

```
User: "Cuéntame tu historia"
         ↓
    (No matching keyword)
         ↓
    Routes to Telegram human
         ↓
Human support sees message in Telegram
```

---

## 🔧 Troubleshooting

| Issue | Fix |
|-------|-----|
| Workflow not responding | Click **Activate** toggle in N8N (must be ON) |
| Wrong webhook in settings | Copy again from Webhook node in N8N |
| Getting timeout | Increase WordPress timeout to 15-20 seconds in settings |
| No auto-response | Check keyword matches exactly (case-insensitive) |
| N8N returns 404 | Verify webhook path is correct |

---

## Next Level: Multi-Language Support

Add this to your Function node for Spanish/English:

```javascript
const msg = $input.all()[0].json.message;
const isSpanish = /[áéíóúñ]|[á-ñÀ-ÿ]/i.test(msg);

const esResponses = {
  'hosting': '🌐 Hosting con SSL y soporte 24/7...',
  'seo': '📈 Posicionamiento en Google...'
};

const enResponses = {
  'hosting': '🌐 Hosting with SSL and 24/7 support...',
  'seo': '📈 Google ranking and SEO optimization...'
};

const responses = isSpanish ? esResponses : enResponses;
// ... rest of logic
```

---

## 📞 Need Help?

- N8N Docs: https://docs.n8n.io
- LiveChat Plugin: Check `includes/class-n8n-service.php`
- Test webhook: https://webhook.site (paste your N8N webhook URL to see requests)

**You're ready to test! 🎉**
