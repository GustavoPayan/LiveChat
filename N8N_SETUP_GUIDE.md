# N8N Workflow Setup Guide - LiveChat Integration

This guide shows how to create a free N8N workflow that integrates with the LiveChat plugin for automated responses.

## Prerequisites

1. **N8N Instance**: 
   - Free cloud: https://n8n.cloud (register for free account)
   - Or self-hosted: `docker run -it --rm --name n8n -p 5678:5678 n8nio/n8n`
   - Or use n8n.io desktop version

2. **Free AI Model Options**:
   - **OpenAI** (recommended): Get free trial at https://platform.openai.com/account/api-keys (~$5 free credit)
   - **Hugging Face**: Free API at https://huggingface.co/inference-api
   - **Replicate**: Free tier at https://replicate.com
   - **Local Ollama**: Free self-hosted at https://ollama.ai
   - **Simple Echo Test**: For testing without API costs (replies saying "I see you mentioned: [keyword]")

## Step 1: Create N8N Workflow

### In N8N Cloud/Desktop:
1. Click **"+ New Workflow"**
2. Name it: `LiveChat Auto-Response Flow`
3. Click **Start** button to begin

---

## Step 2: Add Webhook Trigger Node

1. **Add Node** → Search for **"Webhook"** → Select **"Webhook (Trigger)"**
2. **Configure Webhook Node**:
   - **Method**: POST
   - **Authentication**: None (or add Basic Auth if needed)
   - Click **"Save Node Credentials"** if using auth
   - Copy the **Webhook URL** (you'll paste this in WordPress settings)
   - Example: `https://n8n.yourinstance.com/webhook/abc123def456`

3. **Test the webhook**:
   - Don't save yet - you'll test after building the flow

---

## Step 3: Add Data Processing Node

1. **Add Node** → Search for **"Set"** → Select **"Set" (core node)**
2. **Configure Set Node**:
   - Add these fields to extract from incoming webhook:
     ```
     Fields to Set:
     - Name: message
       Value: {{ $json.message }}
     
     - Name: session_id
       Value: {{ $json.session_id }}
     
     - Name: visitor
       Value: {{ $json.visitor }}
     
     - Name: site
       Value: {{ $json.site }}
     ```
   - This ensures all data is available for the next node

---

## Step 4: Add AI Response Node

### Option A: Using OpenAI (GPT-4/3.5 - Recommended)

1. **Add Node** → Search for **"OpenAI"** → Select **"OpenAI" (LLM)**
2. **Create Credentials**:
   - Click **"Create new"** under Credentials
   - Paste your OpenAI API key from https://platform.openai.com/api-keys
   - Name it: `OpenAI Free Tier`
3. **Configure OpenAI Node**:
   ```
   Model: gpt-3.5-turbo (free tier, fastest)
   
   Prompt:
   You are a helpful support assistant for a web development and digital marketing agency.
   
   Visitor: {{ $json.visitor }}
   Site: {{ $json.site }}
   
   Customer message: {{ $json.message }}
   
   Respond helpfully and concisely in the same language as the message. 
   Keep response under 150 words.
   ```
   - **Temperature**: 0.7 (balanced creativity)
   - **Max Tokens**: 200

---

### Option B: Using Hugging Face (Free, No Credit Card)

1. **Add Node** → Search for **"HTTP Request"** → Select **"HTTP Request"**
2. **Configure HTTP Node**:
   ```
   Method: POST
   URL: https://api-inference.huggingface.co/models/mistralai/Mistral-7B-Instruct-v0.1
   
   Headers:
   Authorization: Bearer YOUR_HUGGING_FACE_API_TOKEN
   Content-Type: application/json
   
   Body (JSON):
   {
     "inputs": "You are a helpful support assistant. User message: {{ $json.message }}\n Assistant:",
     "parameters": {
       "max_length": 200,
       "temperature": 0.7
     }
   }
   ```
   - Get free token at https://huggingface.co/settings/tokens

---

### Option C: Simple Test Response (No API Cost)

1. **Add Node** → Search for **"Function"** → Select **"Function"**
2. **Configure Function Node**:
   ```javascript
   const message = $input.all()[0].json.message;
   const visitor = $input.all()[0].json.visitor;
   
   // Simple keyword-based responses
   const responses = {
     'hosting': '🌐 Hosting: We recommend managed WordPress hosting with SSL. What\'s your current setup?',
     'seo': '📈 SEO: Focus on keywords, backlinks, and technical SEO. Which area interests you?',
     'marketing': '📢 Digital Marketing: We offer SEO, PPC, and content marketing. What\'s your goal?',
     'domain': '🔗 Domains: We register and manage domains. Need help choosing a name?',
     'price': '💰 Pricing: Our packages start at $500/month. Custom quotes available.',
     'development': '💻 Web Development: Custom WordPress sites, e-commerce, and apps. Tell me your idea!',
   };
   
   let reply = 'Thanks for reaching out! How can we help with web development or marketing today?';
   
   for (const [keyword, response] of Object.entries(responses)) {
     if (message.toLowerCase().includes(keyword)) {
       reply = response;
       break;
     }
   }
   
   return { response: reply };
   ```

---

## Step 5: Format Response Node

1. **Add Node** → Search for **"Set"** → Select **"Set"**
2. **Configure Response Set Node**:
   - **Field Name**: response
   - **Field Value**: `{{ $json.generated_text || $json.choices?.[0]?.message?.content || $json.response }}`
   - (This handles different AI API response formats)

---

## Step 6: Add Response Node

1. **Add Node** → Search for **"Respond"** → Select **"Respond to Webhook"**
2. **Configure Response Node**:
   - Leave default settings (will return the data from previous nodes)
   - This sends the response back to WordPress chat

---

## Step 7: Activate & Test Workflow

1. **Save Workflow**: Click **"Save"** (Ctrl+S)
2. **Activate**: Click **"Activate"** toggle (top-right) → Turn ON
3. **Get Webhook URL**: 
   - Open Webhook node
   - Copy full URL (e.g., `https://n8n.yourinstance.com/webhook/abc123`)

---

## Step 8: Configure WordPress Plugin

1. Go to WordPress Admin → **NexGen Livechat** settings
2. Under **"🤖 Automatización N8N"** section:
   - ✅ **Enable N8N**: Check this box
   - **URL del Webhook N8N**: Paste your N8N webhook URL
   - **API Key (opcional)**: Leave blank (no auth configured)
   - **Timeout**: 10 seconds
   - **Keywords** (add one per line):
     ```
     hosting
     dominio
     ssl
     seo
     marketing
     precio
     desarrollo web
     ```
3. Click **Save Changes**

---

## Step 9: Test the Flow

### Test in N8N:
1. Open your N8N workflow
2. Click **"Test Workflow"** or use Webhook node's **"Test"** button
3. Send test payload:
```json
{
  "message": "¿Tienen hosting con SSL?",
  "session_id": "chat_test_abc123",
  "visitor": "Juan",
  "site": "Mi Sitio Web",
  "timestamp": "2026-02-23 10:30:00"
}
```
4. Should see response: `{"response": "..."}`

### Test in WordPress Chat:
1. Go to your website with the LiveChat widget
2. Enter visitor name (e.g., "Juan")
3. Type a message with a keyword: "¿Qué hosting recomiendan?"
4. Wait 2-3 seconds
5. Should see auto-response from N8N in chat

---

## Workflow Diagram

```
Webhook (POST) 
    ↓
[Receive JSON: message, session_id, visitor, site]
    ↓
Set Node (Extract fields)
    ↓
AI Model Node (Generate response)
│   ├─ OpenAI: gpt-3.5-turbo
│   ├─ HuggingFace: Mistral-7B
│   └─ Function: Keyword matcher
    ↓
Set Response Node (Format output)
    ↓
HTTP Response (Send back 200 + JSON)
    ↓
[WordPress chat shows auto-response]
```

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| "Webhook not firing" | Make sure N8N workflow is **Activated** (toggle ON) |
| "Connection refused" | Check N8N instance is running; test webhook URL in browser |
| "Empty response" | Add error handling in Function node; log errors to console |
| "Timeout error in chat" | Increase WordPress timeout setting or reduce AI model latency |
| "Rate limit exceeded" | Add delay node between requests, or upgrade to paid plan |
| "No keywords matching" | Check keyword list in N8N AND WordPress (must match exactly) |

---

## Advanced: Add Logging Node

To debug workflow issues:

1. **Add Node** → Search for **"Logs"** → Select **"Logs"**
2. **Configure Logs**:
   - **Prefix**: `N8N_RESPONSE`
   - **Log**: `{{ $json }}`
3. Place it before HTTP Response node
4. Check N8N Execution logs for all data passing through

---

## Next Steps

1. ✅ Create N8N workflow (this guide)
2. ✅ Test with sample message
3. → Add human handoff (send to Telegram if AI confidence < 0.7)
4. → Add conversation history (use PostgreSQL to save context)
5. → Add multilingual support (auto-detect language)

---

## Free Tier Limits

| Service | Free Tier | Cost After |
|---------|-----------|-----------|
| OpenAI | $5 credits (90 days) | $0.0005-0.002 per 1k tokens |
| HuggingFace | Unlimited | Paid tiers available |
| Replicate | $5 credits/month | $1.40 per million tokens |
| N8N Cloud | 3 workflows | $10-50/month |
| N8N Self-hosted | Unlimited | Free (self-hosted) |

**Recommendation**: Start with Function Node (Option C) for testing, then upgrade to OpenAI when ready for production.

---

## Example N8N Workflow JSON (Importable)

See `n8n-workflow-export.json` in this repo for a complete workflow export you can import directly into N8N.

Copy this into N8N → **File** → **Import** → Paste JSON

```json
{
  "nodes": [
    {
      "parameters": {
        "httpMethod": "POST"
      },
      "name": "Webhook",
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 1,
      "position": [250, 300]
    },
    {
      "parameters": {
        "functionCode": "const message = $input.all()[0].json.message;\nreturn { response: `Echo: ${message}` };"
      },
      "name": "Process Message",
      "type": "n8n-nodes-base.function",
      "typeVersion": 1,
      "position": [450, 300]
    },
    {
      "parameters": {
        "response": "noData"
      },
      "name": "Webhook Response",
      "type": "n8n-nodes-base.respondToWebhook",
      "typeVersion": 1,
      "position": [650, 300]
    }
  ],
  "connections": {
    "Webhook": {
      "main": [[ { "node": "Process Message", "branch": 0, "socket": 0 } ]]
    },
    "Process Message": {
      "main": [[ { "node": "Webhook Response", "branch": 0, "socket": 0 } ]]
    }
  }
}
```

Done! Your N8N flow is ready to provide automated responses to LiveChat messages. 🚀
