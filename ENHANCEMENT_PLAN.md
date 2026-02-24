# LiveChat Plugin Enhancement Plan

## Vision
Transform the plugin from a basic Telegram-only chat into a **hybrid AI + human support system** using N8N workflows for automation, with enterprise-grade security and maintainable architecture.

---

## Phase 1: Architecture Refactor (Foundation)

### 1.1 Separate the monolithic class into services
**Current state**: Single `NexGenTelegramChat` class (~750 LOC)  
**Goal**: Service-oriented architecture

**New structure**:
```
nexgen-telegram-chat.php          # Plugin bootstrap
includes/
  ├── class-plugin.php            # Main plugin class (hooks only)
  ├── class-message-service.php   # Message CRUD, DB operations
  ├── class-telegram-service.php  # Telegram Bot API wrapper
  ├── class-n8n-service.php       # N8N webhook integration (NEW)
  ├── class-session-service.php   # Session management
  └── class-security.php          # Nonce, validation, sanitization
```

**Benefits**:
- Single Responsibility Principle
- Easier testing and debugging
- Reusable components
- Clear dependencies

---

## Phase 2: N8N Integration

### 2.1 Message Routing Logic
**Flow**: 
1. User sends message → Route handler checks if it's a question (keywords/ML)
2. **If question** → Send to N8N flow → Return automated response
3. **If statement** → Send to human via Telegram → Wait for response
4. **If N8N fails** → Fallback to human queue

**Implementation**:
```php
// New AJAX action
wp_ajax_nexgen_process_message
  ├── Classify message (keyword-based or API call)
  ├── If auto-respondable: call N8N webhook
  ├── Else: route to Telegram bot
  ├── Handle response routing back to chat
```

### 2.2 N8N Configuration in Admin
**New admin settings**:
- `nexgen_n8n_enabled` (checkbox)
- `nexgen_n8n_webhook_url` (URL for automated responses)
- `nexgen_n8n_api_key` (encrypted in options table)
- `nexgen_n8n_timeout` (seconds, default 10)
- `nexgen_ai_keywords` (JSON list: "web development", "pricing", "features")

**UI**: Add new section "🤖 Automation Settings" to admin page

---


## Phase 3: Security Hardening ⚔️ (CURRENT)

### 3.1 Rate Limiting (Prevent Spam & Abuse)
**Implementation**:
```php
// In NexGen_Security class
public static function check_rate_limit($session_id, $limit = 20, $period = 60) {
  $key = "nexgen_rate_limit_{$session_id}";
  $count = get_transient($key);
  
  if ($count >= $limit) {
    return [
      'allowed' => false,
      'message' => "Too many requests. Try again in {$period}s.",
      'retry_after' => $period
    ];
  }
  
  set_transient($key, ($count ?: 0) + 1, $period);
  
  return ['allowed' => true];
}
```
**Where to use**: All message handlers before processing
**Limits**: 20 messages per minute per session

### 3.2 Input Validation & Sanitization
**Current**: Basic `sanitize_text_field()`  
**Upgrade**:
```php
// Enhanced validation
public static function validate_message($message, $max_length = 1000) {
  // 1. Type check
  if (!is_string($message)) return ['valid' => false, 'error' => 'Invalid type'];
  
  // 2. Length check
  if (strlen($message) > $max_length) return ['valid' => false, 'error' => 'Message too long'];
  
  // 3. Empty check
  $trimmed = trim($message);
  if (empty($trimmed)) return ['valid' => false, 'error' => 'Message cannot be empty'];
  
  // 4. XSS detection (block suspicious patterns)
  $xss_patterns = ['<script', 'javascript:', 'onerror=', 'onclick=', 'onload='];
  foreach ($xss_patterns as $pattern) {
    if (stripos($trimmed, $pattern) !== false) {
      return ['valid' => false, 'error' => 'Suspicious content detected'];
    }
  }
  
  return ['valid' => true, 'message' => sanitize_textarea_field($trimmed)];
}
```

### 3.3 Webhook Signature Validation (Telegram)
**Problem**: Anyone can POST to the webhook  
**Solution**: Verify Telegram's X-Telegram-Bot-API-Secret-Token header
```php
public static function validate_telegram_webhook($token, $expected_token) {
  $headers = getallheaders();
  $received_token = $headers['X-Telegram-Bot-Api-Secret-Token'] ?? null;
  
  if (!hash_equals($token, $received_token ?? '')) {
    NexGen_Security::log_event('webhook_signature_failed', ['received' => $received_token]);
    wp_send_json_error('Invalid signature', 401);
  }
  
  return true;
}
```

### 3.4 Database Security
**New columns**:
- `ip_hash` (SHA256 of visitor IP, never raw IP for GDPR)
- `is_suspicious` (flag for automated detection)
- `sender_info` (JSON metadata: user-agent, referer, etc)

**Auto-upgrade** (already implemented in Phase 2):
```php
public static function upgrade_table_schema() {
  // Safely adds missing columns without data loss
}
```

### 3.5 API Key Encryption
**Current**: API keys stored in plaintext in wp_options  
**Fix**: Encrypt sensitive data
```php
public static function encrypt_api_key($key) {
  // Use WordPress's built-in encryption if available
  // Or store in wp-config.php constants instead of DB
  return base64_encode($key); // Minimal protection, upgrade to sodium later
}

public static function decrypt_api_key($encrypted) {
  return base64_decode($encrypted);
}
```

### 3.6 Enhanced Logging
**Add to all critical operations**:
```php
NexGen_Security::log_event('security_event_type', [
  'action' => 'message_received',
  'session_id' => $session_id,
  'message_length' => strlen($message),
  'ip_hash' => hash('sha256', $_SERVER['REMOTE_ADDR']),
  'timestamp' => current_time('mysql'),
  'rate_limit_status' => $rate_limit['allowed'],
  'is_n8n' => $should_go_to_n8n,
]);
```

**Locations to add logging**:
- ✅ Message received (log IP hash, content length, source)
- ✅ Rate limit exceeded (potential DDoS)
- ✅ Validation failures (XSS/SQL injection attempts)
- ✅ Webhook signature failures
- ✅ N8N errors (API issues)
- ✅ Telegram API errors

---

## Phase 4: Context-Aware Chatbot (AI with Service Knowledge) 🧠

### 4.1 Vision
Transform from **simple keyword routing** to **intelligent LLM-powered assistant** that understands your service offerings, pricing, features, and can qualify leads.

**Example Flow**:
```
User: "¿Cuál es el costo del desarrollo web?"
↓
N8N Flow:
  1. Extract intent: "pricing inquiry"
  2. Fetch context from WordPress: pricing, services, portfolio
  3. Send to Gemini API: "User asks about web dev pricing. Our services: X, Y, Z. Our pricing: A, B, C. Respond professionally in Spanish"
  4. Gemini responds with contextual answer
  5. Save conversation to database for lead scoring
↓
User sees: "Nuestros servicios de desarrollo web comienzan en $X y incluyen..."
```

### 4.2 Data Sources for Context

**Fetch from WordPress**:
```php
public static function gather_context() {
  return [
    'services' => self::get_services(),      // Custom post type or option
    'pricing' => self::get_pricing_table(),  // Option or page
    'features' => self::get_features(),      // From settings
    'company' => [
      'name' => get_bloginfo('name'),
      'description' => get_bloginfo('description'),
      'phone' => get_option('nexgen_company_phone'),
      'email' => get_option('nexgen_company_email'),
    ],
    'portfolio' => self::get_recent_projects(), // Last 3 projects
  ];
}
```

**Store in admin settings**:
- Service descriptions (textarea)
- Pricing tiers (JSON)
- Company info (name, phone, email, address)
- Portfolio project names
- Team member names/roles
- FAQ entries

### 4.3 N8N Workflow with Gemini Integration

**N8N Setup**:
```
[Chat Message Input]
    ↓
[Extract Keywords/Intent] 
    ↓
[Fetch WordPress Context] → API call to /wp-json/nexgen/context
    ↓
[Call Gemini API] 
    ├─ Model: gemini-1.5-pro
    ├─ System Prompt: "You are a professional Spanish-speaking sales assistant for [Company]. Use provided context to answer."
    ├─ Include Context: Services, Pricing, Company Info
    ├─ User Message: Original question
    └─ Temperature: 0.7 (balanced)
    ↓
[Save to Database]
    ├─ Response text
    ├─ Intent (extracted)
    ├─ Lead quality score
    └─ Contact info if extracted
    ↓
[Return Response to Chat]
```

### 4.4 N8N Security Best Practices

**Webhook Security**:
```
1. Add Authentication:
   - Use Bearer token in N8N webhook trigger
   - Configure in WordPress: wp-json with API key
   - Example: Authorization: Bearer nexgen_secret_token_xxxx

2. Rate Limiting in N8N:
   - Limit requests per IP
   - Queue processing for high load
   - Retry logic with exponential backoff

3. API Keys:
   - Store Gemini API key in N8N secrets/vault
   - Never log or expose API key
   - Rotate keys regularly
```

**N8N Node Configuration**:
```json
{
  "name": "Fetch Context from WordPress",
  "type": "httpRequest",
  "typeVersion": 4,
  "position": [250, 300],
  "parameters": {
    "url": "https://yoursite.com/wp-json/nexgen/context",
    "requestMethod": "GET",
    "authentication": "genericCredentialType",
    "genericCredentials": "WordPress_API_Key",
    "headers": {
      "Authorization": "Bearer {{ $env.WORDPRESS_API_KEY }}"
    }
  }
}
```

### 4.5 Lead Qualification & CRM Integration

**Score leads based on**:
- Service interest (e.g., "development" = high interest)
- Urgency signals ("need it this week" = high)
- Contact info provided (phone/email = higher quality)

```php
public static function calculate_lead_quality($message, $response, $extracted_info) {
  $score = 0;
  
  // Interest signals
  if (isset($extracted_info['service_interest'])) $score += 30;
  if (isset($extracted_info['budget_mentioned'])) $score += 20;
  if (isset($extracted_info['timeline_mentioned'])) $score += 15;
  if (isset($extracted_info['contact_info'])) $score += 35;
  
  return min($score, 100); // 0-100 score
}

// Store in database for CRM integration
NexGen_Message_Service::save_message([
  'session_id' => $session_id,
  'message_text' => $message,
  'sender_info' => json_encode([
    'lead_score' => $lead_quality,
    'intent' => $intent,
    'extracted_contact' => $contact_info,
    'interested_service' => $service,
  ]),
]);
```

### 4.6 WordPress REST API Endpoint for Context

**New endpoint** `/wp-json/nexgen/context`:
```php
// Register in class-plugin.php admin_init()
register_rest_route('nexgen/v1', '/context', [
  'methods' => 'GET',
  'callback' => [NexGen_Context_Service::class, 'get_context'],
  'permission_callback' => function() {
    return current_user_can('read') || wp_verify_nonce($_GET['nonce'], 'nexgen_context');
  }
]);
```

### 4.7 Conversation History for Better Context

**If user asks follow-up questions**, include conversation history:
```
Gemini System Prompt:
"You are a sales assistant. Here's the conversation history:

User: ¿Cuál es el costo?
Assistant: Our pricing starts at...

User: ¿Incluye hosting?
Assistant: [NEW] Yes, all packages include..."
```

**Database schema**:
```
wp_nexgen_chat_messages
├── conversation_id (group related messages)
├── message_order (sequence in conversation)
├── llm_context_used (what data was sent to LLM)
└── lead_data (extracted info)
```

---

## Phase 5: Code Quality & Best Practices

### 5.1 Type Hints & PHPDoc
All methods should have:
```php
/**
 * Process and send message with AI enhancement
 * 
 * @param string $message User message (max 1000 chars)
 * @param string $session_id Unique session ID (chat_*_hexcode)
 * @return array Success response with ['response' => string, 'lead_score' => int]
 * @throws MessageException On validation failure
 */
public static function process_with_ai(string $message, string $session_id): array
```

### 5.2 Error Handling
```php
try {
  $validated = NexGen_Security::validate_message($message);
  if (!$validated['valid']) throw new Exception($validated['error']);
  
  $response = NexGen_N8N_Service::send_to_n8n($message, $session_id);
  if (!$response['success']) throw new Exception($response['error']);
  
  return ['success' => true, 'response' => $response['response']];
} catch (Exception $e) {
  NexGen_Security::log_event('message_process_error', ['error' => $e->getMessage()]);
  return ['success' => false, 'error' => 'Failed to process message'];
}
```

---

## Phase 6: Frontend Improvements & Testing

### 6.1 Modern JavaScript Architecture
- Migrate from jQuery to Vanilla JS ES6 modules
- Component-based: `ChatWidget`, `MessageRenderer`, `FormHandler`
- Event-driven messaging

### 6.2 Testing
- Unit tests for validation functions
- Integration tests for N8N flow
- Security tests (XSS, SQL injection, rate limiting)

---

## Implementation Priority

**Current (Phase 3-4)**:
1. ✅ Implement rate limiting
2. ✅ Add input validation
3. ✅ Webhook signature validation
4. 🔜 Build Gemini integration in N8N
5. 🔜 Add WordPress context endpoint
6. 🔜 Lead scoring system

**Next (Phase 5-6)**:
7. Code quality improvements
8. Testing suite
9. Frontend modernization
10. Documentation

---

## File Structure After Phase 4

```
includes/
  ├── class-plugin.php              # Main plugin (hooks)
  ├── class-security.php            # Validation, rate limit, logging
  ├── class-message-service.php     # DB operations
  ├── class-telegram-service.php    # Telegram API
  ├── class-n8n-service.php         # N8N routing
  ├── class-context-service.php     # Context fetching for LLM (NEW)
  ├── class-lead-service.php        # Lead scoring (NEW)
  └── admin-page.php                # Settings UI
```

---

## Success Metrics for Phase 4

✅ **Chatbot Quality**:
- Response relevance score > 85%
- Average response time < 3 seconds
- User satisfaction score > 4/5

✅ **Lead Quality**:
- Lead qualification accuracy > 80%
- Contact capture rate > 30%
- Average lead score > 60

✅ **System Health**:
- Uptime > 99.5%
- Error rate < 0.1%
- API cost optimization (batching, caching)

