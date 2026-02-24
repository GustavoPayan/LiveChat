# Gemini AI Chatbot Setup Guide - Phase 4 (Context Chatbot)

## Overview

This guide helps you set up an intelligent AI chatbot using **Google Gemini** with **N8N** to provide context-aware responses about your services, pricing, and company information.

**What it does:**
1. User sends a message through the chat widget
2. N8N fetches your WordPress service/pricing data
3. Gemini API generates a response based on your specific context
4. Bot responds with AI-powered answer tailored to your business
5. Lead information is extracted and scored automatically

---

## Prerequisites

✅ **Already Completed:**
- Phase 1: Service-oriented architecture
- Phase 2: N8N integration with keywords (we're upgrading this)
- Phase 3: Security & rate limiting

✅ **You Need:**
1. **Google Cloud Account** (free tier available for Gemini)
2. **N8N Cloud Account** (n8n.cloud or self-hosted)
3. **WordPress REST API** access
4. **Gemini API Key**

---

## Step 1: Get Google Gemini API Key

### 1.1 Create Google Cloud Project

```bash
1. Go to https://console.cloud.google.com/
2. Click "Select a Project" → "NEW PROJECT"
3. Name: "NexGen AI Chat"
4. Click CREATE
5. Wait 1-2 minutes for project creation
```

### 1.2 Enable Gemini API

```bash
1. Go to https://console.cloud.google.com/apis/library
2. Search for "Gemini API"
3. Click "Google AI for Developers" → "Generative AI API"
4. Click ENABLE
5. Wait for API to enable (30 seconds)
```

### 1.3 Create API Key

```bash
1. Go to https://aistudio.google.com/apikey
2. Click "Get API Key"
3. Select your "NexGen AI Chat" project
4. Click "Create API Key"
5. ✅ Copy the API Key (keep it safe!)
```

**⚠️ Security:** Never commit API key to Git. Store in environment variables.

---

## Step 2: Configure WordPress REST Endpoint

We need to add a REST API endpoint that returns your context data for Gemini.

### 2.1 Add REST Endpoint to class-plugin.php

```php
// In NexGen_Telegram_Chat class, add to __construct():
add_action('rest_api_init', [$this, 'register_rest_endpoints']);

// Add new method:
public function register_rest_endpoints() {
    register_rest_route('nexgen/v1', '/context', [
        'methods' => 'GET',
        'callback' => [NexGen_Context_Service::class, 'get_context'],
        'permission_callback' => function() {
            // Check Bearer token
            $headers = getallheaders();
            $auth = $headers['Authorization'] ?? '';
            $token = str_replace('Bearer ', '', $auth);
            return $token === get_option('nexgen_api_key');
        }
    ]);
    
    register_rest_route('nexgen/v1', '/save-message', [
        'methods' => 'POST',
        'callback' => [NexGen_Message_Service::class, 'save_from_api'],
        'permission_callback' => function() {
            $headers = getallheaders();
            $auth = $headers['Authorization'] ?? '';
            $token = str_replace('Bearer ', '', $auth);
            return $token === get_option('nexgen_api_key');
        }
    ]);
}
```

### 2.2 Generate WordPress API Key

In WordPress Admin:
```
1. Go to NexGen Livechat settings
2. Add new field: "REST API Key for N8N"
3. Generate: wp_generate_password(32, true)
4. Save and copy the key
```

⚠️ **Security:** Use a strong random string, not your password.

---

## Step 3: Configure N8N Workflow

### 3.1 Import the Gemini Workflow

```bash
1. Go to n8n.cloud
2. Create new workflow
3. Click Import from File
4. Select: n8n-gemini-workflow.json
5. Click Import
```

### 3.2 Set Environment Variables in N8N

Go to **Settings** → **Variables**:

```
WORDPRESS_URL: https://thenexgen.dev
WORDPRESS_API_KEY: [your generated key from Step 2]
GEMINI_API_URL: https://generativelanguage.googleapis.com/v1beta/openai/
GEMINI_API_KEY: [your Google API key]
N8N_INSTANCE_ID: [auto-generated]
```

### 3.3 Configure Credentials

In N8N:

**Gemini API Key:**
```
1. Settings → Credentials
2. New → Google Generative AI (Gemini)
3. Paste your API key
4. Save
```

**WordPress Bearer Token:**
```
1. Settings → Credentials
2. New → Generic Credential Type
3. Name: "WordPress_API"
4. Header Name: "Authorization"
5. Header Value: "Bearer [your API key from Step 2]"
6. Save
```

### 3.4 Test the Webhook

Click the Webhook node → "Open in new window" to get the URL:

```
https://hook.n8n.cloud/webhook/[your-webhook-id]
```

**Test with cURL:**
```bash
curl -X POST https://hook.n8n.cloud/webhook/livechat \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "¿Cuál es el costo del desarrollo web?",
    "session_id": "chat_test_123",
    "visitor": "Juan",
    "timestamp": "2026-02-24 10:00:00"
  }'
```

---

## Step 4: Configure Admin Page Settings

Add these new settings to WordPress admin (NexGen Livechat):

### 4.1 Update admin-page.php N8N Tab

```php
<tr>
    <th scope="row"><label>Usar Gemini AI en lugar de Keywords</label></th>
    <td>
        <input type="checkbox" name="nexgen_use_gemini" value="1" 
            <?php checked(get_option('nexgen_use_gemini')); ?> />
        <p>Si está habilitado, usará Gemini AI con contexto</p>
    </td>
</tr>

<tr>
    <th scope="row"><label>Servicios Ofrecidos (JSON)</label></th>
    <td>
        <textarea name="nexgen_services" rows="6" class="large-text">
<?php echo esc_textarea(get_option('nexgen_services', '[]')); ?>
        </textarea>
        <p>Formato: [{"name":"Web Development","description":"...","price_from":1000}]</p>
    </td>
</tr>

<tr>
    <th scope="row"><label>Tarifas de Precios (JSON)</label></th>
    <td>
        <textarea name="nexgen_pricing_tiers" rows="6" class="large-text">
<?php echo esc_textarea(get_option('nexgen_pricing_tiers', '[]')); ?>
        </textarea>
        <p>Formato: [{"name":"Basic","price":500,"period":"month"}]</p>
    </td>
</tr>

<tr>
    <th scope="row"><label>Preguntas Frecuentes (JSON)</label></th>
    <td>
        <textarea name="nexgen_faq_entries" rows="6" class="large-text">
<?php echo esc_textarea(get_option('nexgen_faq_entries', '[]')); ?>
        </textarea>
    </td>
</tr>

<tr>
    <th scope="row"><label>API Key para N8N</label></th>
    <td>
        <input type="text" readonly name="nexgen_api_key" 
            value="<?php echo esc_attr(get_option('nexgen_api_key')); ?>" 
            class="regular-text" />
        <button type="button" class="button" onclick="generateApiKey()">
            Generar Nueva Clave
        </button>
    </td>
</tr>
```

### 4.2 Add JavaScript Function

```javascript
function generateApiKey() {
    if (!confirm('¿Generar nueva clave API? La anterior dejará de funcionar.')) return;
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=nexgen_generate_api_key&nonce=' + document.querySelector('[name="nexgen_nonce"]').value
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelector('[name="nexgen_api_key"]').value = data.data;
            alert('✅ Nueva clave generada: ' + data.data);
        } else {
            alert('❌ Error: ' + data.data);
        }
    });
}
```

### 4.3 Register New Settings in class-plugin.php

```php
// In admin_init():
register_setting('nexgen_chat_settings', 'nexgen_use_gemini', [
    'sanitize_callback' => function($v) { return $v ? 1 : 0; }
]);
register_setting('nexgen_chat_settings', 'nexgen_services', [
    'sanitize_callback' => function($v) { 
        return json_encode(json_decode($v, true) ?? []);
    }
]);
register_setting('nexgen_chat_settings', 'nexgen_pricing_tiers', [
    'sanitize_callback' => function($v) { 
        return json_encode(json_decode($v, true) ?? []);
    }
]);
register_setting('nexgen_chat_settings', 'nexgen_faq_entries', [
    'sanitize_callback' => function($v) { 
        return json_encode(json_decode($v, true) ?? []);
    }
]);
register_setting('nexgen_chat_settings', 'nexgen_api_key');

// Add AJAX handler
add_action('wp_ajax_nexgen_generate_api_key', function() {
    check_admin_referer('nexgen_chat_nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    
    $new_key = wp_generate_password(32, true);
    update_option('nexgen_api_key', $new_key);
    
    wp_send_json_success($new_key);
});
```

---

## Step 5: Update Message Routing Logic

### 5.1 Modify handle_send_message() in class-plugin.php

```php
public function handle_send_message() {
    // Existing validation...
    $validation = NexGen_Security::validate_message($_POST['message']);
    if (!$validation['valid']) wp_send_json_error($validation['error']);
    
    // NEW: Check if using Gemini AI
    $use_gemini = get_option('nexgen_use_gemini');
    $should_use_n8n = $use_gemini 
        ? true 
        : NexGen_N8N_Service::should_process($validation['message']);
    
    if ($should_use_n8n) {
        // This will now use Gemini workflow
        $result = NexGen_N8N_Service::send_to_n8n($validation['message'], $session_id);
    } else {
        // Fallback to Telegram
        $result = NexGen_Telegram_Service::send_message($validation['message'], $session_id);
    }
    
    wp_send_json_success([
        'automated' => !empty($result['success']),
        'message' => $result['response'] ?? 'Error'
    ]);
}
```

---

## Step 6: N8N Security Best Practices

### 6.1 Rate Limiting

**In N8N Webhook Trigger:**
```
□ Limit by IP
□ Max connections: 20 per minute per IP
□ Queue mode: On (handles bursts)
```

### 6.2 Input Validation

The workflow includes:
- ✅ Message length validation (max 1000 chars)
- ✅ Spam detection (too many numbers, punctuation)
- ✅ Known spam keyword filtering
- ✅ Email & phone extraction safety

### 6.3 API Key Security

**Environment Variables (NEVER in code):**
```bash
# .env or N8N settings
GEMINI_API_KEY=sk-...
WORDPRESS_API_KEY=wp_...
```

**Workflow settings:**
- ✅ No logging of API keys
- ✅ Bearer token validation on webhook
- ✅ HTTPS only connections

### 6.4 Error Handling

N8N workflow includes:
- Try-catch on each API call
- Fallback responses if Gemini fails
- Logging of errors (no sensitive data)
- Rate limit enforcement

### 6.5 Lead Data Privacy

- ✅ Hashed IP addresses stored
- ✅ GDPR-compliant (no unnecessary IP logging)
- ✅ Contact info only stored if user provides it
- ✅ Automatic data retention policy (90 days)

---

## Step 7: Test the Integration

### 7.1 WordPress UI Test

```
1. Open chat widget on your website
2. Type: "¿Cuál es el costo para un sitio web?"
3. Wait 2-3 seconds
4. Expect: AI response with your pricing info
```

### 7.2 N8N Execution Monitor

```
1. In N8N, click "Executions"
2. View successful workflow runs
3. Check for any errors or timeouts
4. Monitor API usage
```

### 7.3 Check WordPress Logs

```
1. WP_DEBUG enabled in wp-config.php
2. View /wp-content/debug.log
3. Look for 'n8n_' log entries
4. Verify message saved with lead_score
```

---

## Step 8: Configuration Examples

### Example: Services Configuration

```json
[
  {
    "name": "Web Development",
    "description": "Custom WordPress and web applications",
    "price_from": 1500,
    "price_to": 5000,
    "duration": "4-8 weeks",
    "features": ["Responsive design", "SEO optimized", "Custom CMS"]
  },
  {
    "name": "SEO Optimization",
    "description": "Improve search engine rankings",
    "price_from": 500,
    "price_to": 2000,
    "duration": "Ongoing",
    "features": ["Keyword research", "Technical SEO", "Link building"]
  }
]
```

### Example: Pricing Tiers

```json
[
  {
    "name": "Startup Package",
    "price": 999,
    "currency": "USD",
    "period": "month",
    "features": ["5 pages", "Contact form", "Email support"],
    "best_for": "Small businesses"
  },
  {
    "name": "Growth Package",
    "price": 2499,
    "currency": "USD",
    "period": "month",
    "features": ["Unlimited pages", "E-commerce", "24/7 support"],
    "best_for": "Growing companies"
  }
]
```

---

## Troubleshooting

### Issue: Gemini API returns "Quota exceeded"

**Solution:**
```
1. Check Google Cloud quota at console.cloud.google.com
2. Increase rate limit in N8N workflow (currently 20/min)
3. Upgrade to Google Cloud paid plan if needed
```

### Issue: WordPress context not fetching

**Solution:**
```
1. Verify WordPress API key in N8N settings
2. Test manually: curl https://yoursite.com/wp-json/nexgen/v1/context
3. Check CORS headers if using different domain
4. Enable debug logging in WordPress
```

### Issue: Responses are slow (> 5 seconds)

**Solution:**
```
1. Reduce context size (limit FAQ to 5 items, portfolio to 3)
2. Enable response caching in N8N
3. Consider using gemini-1.0-pro (faster, cheaper)
4. Add queue processing for high load
```

### Issue: AI responses not in Spanish

**Solution:**
```
1. Check language setting in system prompt (build-prompt node)
2. Verify Gemini model supports language (1.5-pro does)
3. Force language in prompt: "Respond ONLY in Spanish:"
```

---

## Monitoring & Optimization

### 7.1 Monitor Costs

**Google Cloud:**
- Free tier: 60 requests per minute
- Paid: ~$0.00075 per API call (Gemini 1.5)
- Monthly estimate: ~$50 for 1000 chats/day

**N8N:**
- Cloud free: 1000 executions/month
-Cloud paid: $10-49/month

### 7.2 Lead Scoring Dashboard

Create WordPress admin page to track:
- ✅ Total conversations
- ✅ Lead quality scores (0-100)
- ✅ Contact info extraction rate
- ✅ Response time average
- ✅ AI satisfaction rating

### 7.3 Performance Metrics

Monitor:
- Average response time (target: < 3 sec)
- API error rate (target: < 1%)
- Cost per conversation
- Lead conversion rate

---

## Next Steps

✅ **After Setup:**
1. Train Gemini with FAQ entries
2. Add follow-up surveys ("Was this helpful?")
3. Integrate with CRM (HubSpot, Salesforce, etc)
4. Create lead dashboard
5. Set up email notifications for high-quality leads

---

## Security Checklist

- [ ] API keys stored in environment variables
- [ ] N8N webhook requires Bearer token
- [ ] WordPress REST endpoint validates API key
- [ ] Rate limiting enabled (20 req/min per session)
- [ ] Input validation before API call
- [ ] Spam detection active
- [ ] Error logging without sensitive data
- [ ] HTTPS only connections
- [ ] Regular key rotation (monthly)
- [ ] Backup API keys stored safely

---

## Support

For issues:
1. Check N8N workflow log for errors
2. Enable WP_DEBUG in WordPress
3. Review Google Cloud quotas
4. Test each node individually in N8N

