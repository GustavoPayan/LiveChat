# Hybrid LLM + N8N Implementation Guide

## Overview

The NexGen Chat system now uses a **hybrid architecture** that intelligently routes messages:

- **80% LLM (Direct AI)** — Fast responses for FAQ/general questions (~500ms, $0.001/msg)
- **15% N8N (Leads)** — CRM integration for lead generation & business workflows (~2-3s, structured data)
- **5% Blocked** — Out-of-scope + Escalation to human Telegram support

**Cost**: $10-20/month vs $50-100/month N8N-only

---

## Architecture Components

### 1. LLM Service (`class-llm-service.php`)

Multi-provider wrapper supporting OpenAI, Claude, and Gemini with unified interface.

**Key Methods:**

```php
// Query any provider
$response = NexGen_LLM_Service::query(
    message: "What are your products?",
    system_prompt: "You are a helpful support bot..."
);
// Returns: ['success' => bool, 'response' => string, 'tokens_input' => int, 'tokens_output' => int, 'cost' => float, 'provider' => string]

// Get configured provider
$provider = NexGen_LLM_Service::get_provider(); // e.g., 'openai'

// Get cost for a query
$cost = NexGen_LLM_Service::get_estimated_cost($input_tokens, $output_tokens);
```

**Providers:**

- **OpenAI (GPT-4o-mini)** — Most capable, balanced cost ($0.00015/1K input, $0.0006/1K output)
- **Claude (3.5 Haiku)** — Best for analysis ($0.0008/1K input, $0.004/1K output)
- **Gemini (2.0 Flash)** — Free tier available, good for scale ($0 free tier, paid: $0.075/1M input)

**Configuration:**

```php
// Set in WordPress options
update_option('nexgen_llm_provider', 'openai'); // or 'claude', 'gemini'
update_option('nexgen_llm_api_key', 'sk-xxx'); // From wp-config or encrypted
update_option('nexgen_llm_enabled', true);
update_option('nexgen_llm_temperature', 0.7); // 0-1, higher = more creative
update_option('nexgen_llm_max_tokens', 500); // Max response length
```

---

### 2. Business Prompt Service (`class-business-prompt-service.php`)

Dynamically builds prompts with business context and validates message scope.

**Key Methods:**

```php
// Build system prompt with business context
$prompt = NexGen_Business_Prompt_Service::build_system_prompt();

// Check if message is about the business
$scope = NexGen_Business_Prompt_Service::validate_scope("What's your pricing?");
// Returns: ['in_scope' => bool, 'confidence' => float, 'matches' => array]

// Detect if user wants to contact (lead intent)
$is_lead = NexGen_Business_Prompt_Service::detect_lead_intent("I want to schedule a meeting");
// Returns: true

// Extract lead contact data
$lead = NexGen_Business_Prompt_Service::extract_lead_data($message, $visitor_name, $session_id);
// Returns: ['name' => str, 'email' => str|null, 'phone' => str|null, 'interest' => str]

// Get rejection message for out-of-scope
$rejection = NexGen_Business_Prompt_Service::get_rejection_message();
```

**Configuration:**

```php
// Business context settings
update_option('nexgen_business_context', "We sell SaaS project management tools for creative teams...");
update_option('nexgen_business_type', 'saas'); // or 'ecommerce', 'service', etc.

// Scope validation
update_option('nexgen_supported_topics', ['Pricing', 'Features', 'Account Support']);
update_option('nexgen_scope_keywords', "pricing\nfeatures\nsupport");

// Lead detection
update_option('nexgen_lead_keywords', "contact\nmeeting\nschedule\nI want to\nSign me up");

// Custom rejection message
update_option('nexgen_rejection_message', "That's outside our scope. Contact sales@company.com instead.");
```

---

### 3. LLM Router (`class-llm-router.php`)

Intelligent message routing engine. The core of the hybrid system.

**Key Methods:**

```php
// Main entry point: routes to LLM, lead, blocked, or Telegram
$result = NexGen_LLM_Router::process_message(
    message: "What are your pricing plans?",
    session_id: "chat_example_a1b2c3",
    visitor_name: "John",
    visitor_email: "john@example.com"
);
// Returns: [
//   'success' => bool,
//   'response' => string, // the actual message to display
//   'type' => 'llm|lead|blocked|telegram|error',
//   'metadata' => ['latency_ms' => 245, 'cost' => 0.0015, ...]
// ]

// Get routing statistics
$stats = NexGen_LLM_Router::get_statistics('2024-01-01', '2024-01-31');
// Returns statistics per route type with average latency and total cost

// Get recent leads from database
$leads = NexGen_LLM_Router::get_recent_leads(20);
```

**Routing Logic:**

```
Input Message
    ↓
[Step 1] Validate input (not empty)
    ↓
[Step 2] Check if LLM enabled
    ├─ No → Fallback to Telegram
    └─ Yes ↓
[Step 3] Validate scope (keyword matching)
    ├─ Out of scope (high confidence) → BLOCKED (5%)
    └─ In scope or low confidence ↓
[Step 4] Detect lead intent (contact/meeting keywords)
    ├─ Lead intent detected → LEAD (15%)
    │   ├─ Extract contact data
    │   ├─ Save to wp_nexgen_leads table
    │   ├─ Send to N8N webhook
    │   └─ Return confirmation message
    └─ No lead intent → LLM (80%)
        ├─ Query OpenAI/Claude/Gemini
        ├─ Save response to conversation
        ├─ Log latency & cost metrics
        └─ Return response to user
```

---

## Integration with Existing Code

### Update `class-plugin.php`

Replace the old N8N-only handler with the new hybrid router:

**Before (Old):**

```php
// In class-plugin.php handle_send_message()
public static function handle_send_message() {
    // ... validation ...
    
    // Old: Always route to N8N on keywords
    if ( $this->contains_lead_keywords( $message ) ) {
        $result = NexGen_N8N_Service::send_webhook( $message );
    } else {
        $result = NexGen_Telegram_Service::send_message( $message, $session_id );
    }
}
```

**After (New):**

```php
// In class-plugin.php handle_send_message()
public static function handle_send_message() {
    // ... validation ...
    
    // New: Route through intelligent router
    $result = NexGen_LLM_Router::process_message(
        message: $message,
        session_id: $session_id,
        visitor_name: $_SESSION['nexgen_chat_name'] ?? '',
        visitor_email: ''
    );
    
    if ( $result['success'] ) {
        wp_send_json_success( [
            'response' => $result['response'],
            'type'     => $result['type'],
            'metadata' => $result['metadata'] ?? [],
        ] );
    } else {
        wp_send_json_error( $result['response'] );
    }
}
```

### Load New Services in Bootstrap

**In `nexgen-telegram-chat.php`:**

```php
// Add after existing service requires
require_once plugin_dir_path( __FILE__ ) . 'includes/class-llm-service.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-business-prompt-service.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-llm-router.php';
```

**In `class-plugin.php` constructor:**

```php
public function __construct() {
    // ... existing code ...
    
    // Initialize new services (auto-create tables)
    NexGen_LLM_Router::create_tables();
}
```

---

## Admin Settings Panel

Add new settings section to WordPress admin for LLM configuration:

### New Settings Fields

| Setting | Option Key | Type | Default |
|---------|-----------|------|---------|
| Enable LLM Routing | `nexgen_llm_enabled` | Checkbox | Checked |
| LLM Provider | `nexgen_llm_provider` | Select (openai\|claude\|gemini) | `openai` |
| API Key | `nexgen_llm_api_key` | Password | (empty) |
| Temperature | `nexgen_llm_temperature` | Slider (0-1) | `0.7` |
| Max Tokens | `nexgen_llm_max_tokens` | Number | `500` |
| Business Context | `nexgen_business_context` | Textarea | (empty) |
| Supported Topics | `nexgen_supported_topics` | Textarea (newline-separated) | Default list |
| Scope Keywords | `nexgen_scope_keywords` | Textarea | (empty) |
| Lead Keywords | `nexgen_lead_keywords` | Textarea | Default list |
| Rejection Message | `nexgen_rejection_message` | Textarea | Default text |

### HTML Template Example

```html
<div class="wrap">
    <h1>NexGen Chat - LLM Settings</h1>
    
    <form method="post">
        <table class="form-table">
            <tr>
                <th>Enable LLM Routing</th>
                <td>
                    <input type="checkbox" name="nexgen_llm_enabled" value="1" 
                        <?php checked(get_option('nexgen_llm_enabled'), 1); ?>>
                    Use intelligent routing (LLM + N8N)
                </td>
            </tr>
            
            <tr>
                <th>LLM Provider</th>
                <td>
                    <select name="nexgen_llm_provider">
                        <option value="openai" <?php selected(get_option('nexgen_llm_provider'), 'openai'); ?>>
                            OpenAI (GPT-4o-mini) - $0.15/1M input
                        </option>
                        <option value="claude" <?php selected(get_option('nexgen_llm_provider'), 'claude'); ?>>
                            Claude 3.5 Haiku - $0.80/1M input
                        </option>
                        <option value="gemini" <?php selected(get_option('nexgen_llm_provider'), 'gemini'); ?>>
                            Gemini 2.0 Flash - Free tier available
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th>API Key</th>
                <td>
                    <input type="password" name="nexgen_llm_api_key" 
                        value="<?php echo esc_attr(get_option('nexgen_llm_api_key')); ?>" 
                        style="width: 300px;">
                    <p class="description">Get from platform dashboard</p>
                </td>
            </tr>
            
            <tr>
                <th>Temperature</th>
                <td>
                    <input type="range" name="nexgen_llm_temperature" min="0" max="1" step="0.1" 
                        value="<?php echo esc_attr(get_option('nexgen_llm_temperature', 0.7)); ?>">
                    <span id="temp_display">0.7</span> (0=precise, 1=creative)
                </td>
            </tr>
            
            <tr>
                <th>Business Context</th>
                <td>
                    <textarea name="nexgen_business_context" rows="5" style="width: 100%;"><?php 
                        echo esc_textarea(get_option('nexgen_business_context')); 
                    ?></textarea>
                    <p class="description">Describe your business. Used in every LLM prompt.</p>
                </td>
            </tr>
            
            <tr>
                <th>Lead Keywords</th>
                <td>
                    <textarea name="nexgen_lead_keywords" rows="5" style="width: 100%;"><?php 
                        echo esc_textarea(get_option('nexgen_lead_keywords')); 
                    ?></textarea>
                    <p class="description">One per line. Messages with these trigger lead workflow.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary">Save Settings</button>
        </p>
    </form>
</div>
```

---

## Database Tables

### `wp_nexgen_metrics` (Auto-Created)

Tracks all message routing decisions and costs.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT | Primary key |
| `session_id` | VARCHAR(255) | Reference to chat session |
| `route_type` | VARCHAR(50) | 'llm', 'lead', 'blocked', 'telegram' |
| `metadata` | LONGTEXT | JSON with latency_ms, cost, tokens, etc. |
| `created_at` | DATETIME | Timestamp |

**Sample Query:**

```sql
-- Cost by provider this month
SELECT 
    JSON_EXTRACT(metadata, '$.provider') as provider,
    COUNT(*) as count,
    ROUND(SUM(CAST(JSON_EXTRACT(metadata, '$.cost') AS DECIMAL)), 4) as total_cost,
    ROUND(AVG(CAST(JSON_EXTRACT(metadata, '$.latency_ms') AS DECIMAL)), 0) as avg_latency_ms
FROM wp_nexgen_metrics
WHERE route_type = 'llm'
  AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY provider;
```

### `wp_nexgen_leads` (Auto-Created)

Stores lead data extracted from conversations.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT | Primary key |
| `session_id` | VARCHAR(255) | Chat session reference |
| `name` | VARCHAR(255) | Visitor name |
| `email` | VARCHAR(255) | Email (extracted or provided) |
| `phone` | VARCHAR(20) | Phone (extracted or provided) |
| `interest` | LONGTEXT | Message summary or full message |
| `n8n_sent` | TINYINT | 1 if sent to N8N webhook |
| `created_at` | DATETIME | When lead was captured |

---

## Cost Calculation Examples

### Scenario 1: 100 messages/day, 80% LLM, 20% other

**LLM (80 messages/day @ $0.001 avg per message):**
- OpenAI GPT-4o-mini: ~80 × $0.001 = $0.08/day = $2.40/month
- Claude 3.5 Haiku: ~80 × $0.004 = $0.32/day = $9.60/month
- Gemini 2.0: ~80 × $0 = $0/month (free tier)

**N8N (20 leads/day):**
- Execution cost: ~2-5¢ per execution = $0.04-0.10/day = $1.20-3/month
- Execution limit: 1000/month = $10

**Monthly Total:**
- **With OpenAI: $13.60-16.60/month**
- **With Claude: $20.80-23.80/month**
- **With Gemini: $11.20-13/month**

Compare vs N8N-only (50¢+ per execution × 100 messages) = $50-150/month

---

## Performance & SLA

### Expected Latency

| Route | Latency | Notes |
|-------|---------|-------|
| LLM (OpenAI) | 400-800ms | Streaming disabled for latency metrics |
| LLM (Claude) | 300-600ms | Generally faster than OpenAI |
| LLM (Gemini) | 200-500ms | Fastest response time |
| Lead (N8N) | 2000-3000ms | Network + N8N processing |
| Blocked | 50-150ms | Local validation only |
| Telegram | 100-300ms | Direct HTTP POST to Telegram |

### Scalability

- **Concurrent users:** Up to 10K concurrent chats (depends on WordPress hosting)
- **Messages/day:** 10K+ with LLM at marginal cost increase
- **Rate limiting:** 20 messages per session per minute (configurable)

---

## Testing & Validation

### Test LLM Connection

```php
// In WordPress admin console
$test = NexGen_LLM_Service::query(
    "What's 2+2?",
    "You are a helpful math tutor."
);

echo wp_json_encode($test, JSON_PRETTY_PRINT);
```

### Test Scope Validation

```php
$scope = NexGen_Business_Prompt_Service::validate_scope(
    "I want to know about your pricing"
);

echo $scope['in_scope'] ? 'IN SCOPE' : 'OUT OF SCOPE';
echo ' (confidence: ' . $scope['confidence'] . ')';
```

### Test Full Router

```php
$result = NexGen_LLM_Router::process_message(
    "How much do you charge?",
    "chat_example_abc123",
    "Test User"
);

echo "Route: " . $result['type'] . "\n";
echo "Response: " . $result['response'] . "\n";
echo "Latency: " . $result['metadata']['latency_ms'] . "ms\n";
```

---

## Troubleshooting

### LLM returns empty response

**Cause:** API timeout or invalid credentials

**Solution:**
1. Check API key in settings (Settings → NexGen Chat → LLM Settings)
2. Verify API account has credits/paid plan active
3. Check `wp_nexgen_metrics` table for error logs
4. Enable `WP_DEBUG` and check debug.log

### Routes always go to Telegram

**Cause:** `nexgen_llm_enabled` is false or fallback triggered

**Solution:**
1. Check admin settings: LLM should be enabled
2. Review error logs: `wp_nexgen_metrics` should show route_type='telegram'
3. Test LLM connection separately

### High latency on responses

**Cause:** Slow LLM provider or rate limits

**Solution:**
1. Switch provider: Gemini fastest, Claude balanced, OpenAI most capable
2. Reduce `nexgen_llm_max_tokens` (500 → 250)
3. Increase `nexgen_llm_temperature` to 1.0 (less thinking)

---

## Future Enhancements

- [ ] **Caching:** Store responses for repeated questions (5-10% traffic volume)
- [ ] **Fine-tuning:** Custom models trained on business documents
- [ ] **Analytics Dashboard:** Real-time metrics, cost tracker, satisfaction ratings
- [ ] **A/B Testing:** Compare LLM providers or prompts automatically
- [ ] **Fallback Chains:** LLM → Cache → Telegram → Manual review
- [ ] **Multi-language:** Automatic language detection and response translation

---

## Support & Documentation

- **OpenAI Docs:** https://platform.openai.com/docs
- **Claude Docs:** https://docs.anthropic.com/claude/docs
- **Gemini Docs:** https://ai.google.dev/docs
- **N8N:** https://docs.n8n.io/
- **WordPress Plugins:** https://developer.wordpress.org/plugins/
