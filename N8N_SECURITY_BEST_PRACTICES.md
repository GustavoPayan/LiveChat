# N8N Security & Best Practices Guide

## Overview

This guide covers security hardening and optimization for the NexGen Gemini AI N8N workflow. Following these practices will protect your data, reduce costs, and ensure reliable automation.

---

## 1. Webhook Security

### 1.1 Bearer Token Authentication

**✅ DO:**
```json
{
  "webhook": {
    "path": "livechat",
    "authentication": "genericCredentialType",
    "credential": {
      "authorizationType": "bearerToken",
      "token": "{{ $env.WEBHOOK_SECRET_TOKEN }}"
    }
  }
}
```

**❌ DON'T:**
- Leave webhook public (no auth)
- Use simple API key in URL path
- Commit tokens to Git
- Log full requests with tokens

### 1.2 Test Webhook Security

```bash
# This should FAIL (no token)
curl -X POST https://hook.n8n.cloud/webhook/livechat \
  -H "Content-Type: application/json" \
  -d '{"message":"test"}'
# Response: 401 Unauthorized

# This should SUCCEED (with token)
curl -X POST https://hook.n8n.cloud/webhook/livechat \
  -H "Authorization: Bearer your_secret_token" \
  -H "Content-Type: application/json" \
  -d '{"message":"test"}'
```

---

## 2. API Key Management

### 2.1 Store Keys in Environment Variables

**❌ BAD:**
```javascript
const apiKey = "sk-proj-abcd1234"; // Hardcoded - NEVER!
fetch('https://api.gemini.com', {
  headers: { 'Authorization': apiKey }
});
```

**✅ GOOD:**
```javascript
// In N8N settings → Variables
const apiKey = $env.GEMINI_API_KEY; // Reads from secure storage
fetch('https://api.gemini.com', {
  headers: { 'Authorization': `Bearer ${apiKey}` }
});
```

### 2.2 N8N Credentials Management

**Setup secure credentials:**
```
1. N8N Settings → Credentials
2. Create credential type: "Google Generative AI"
3. Store API key in Vault (encrypted at rest)
4. Use in workflow: {{ $credentials.GoogleGenerativeAI.apiKey }}
```

**Key rotation:**
- Rotate Gemini API keys monthly
- Archive old keys in secure document
- Keep rotation log with dates

### 2.3 WordPress API Key Security

**Generate strong key:**
```php
// Good: 32-character random string with special chars
$api_key = wp_generate_password(32, true);

// Bad: Using MD5 hash (not secure enough)
$api_key = md5('yoursite.com');
```

**Store safely:**
```php
// DO: Store in wp_options (encrypted by WordPress)
update_option('nexgen_api_key', $secure_key);

// DON'T: Store in wp-config.php (exposed if config leaked)
// DON'T: Log in debug file
```

---

## 3. Input Validation & Sanitization

### 3.1 Webhook Input Validation

**N8N Node: "Validate Message"**

```javascript
// Validate BEFORE expensive API calls
function validateInput(json) {
  const errors = [];
  
  // 1. Type validation
  if (typeof json.message !== 'string') {
    errors.push('message must be string');
  }
  
  // 2. Length validation
  if (json.message.length === 0) {
    errors.push('message cannot be empty');
  }
  if (json.message.length > 1000) {
    errors.push('message exceeds 1000 characters');
  }
  
  // 3. Spam detection
  const urlCount = (json.message.match(/https?:\/\//g) || []).length;
  if (urlCount > 3) {
    errors.push('too many URLs detected (potential spam)');
  }
  
  // 4. Profanity check (optional)
  const forbidden = ['spam', 'casino', 'lottery', 'viagra'];
  const hasSpam = forbidden.some(word => 
    json.message.toLowerCase().includes(word)
  );
  if (hasSpam) {
    errors.push('message contains forbidden keywords');
  }
  
  return {
    valid: errors.length === 0,
    errors: errors
  };
}
```

### 3.2 Output Sanitization

**Before sending response to frontend:**

```javascript
// DO: Escape HTML special characters
const response = geminiOutput.replace(/[<>&"']/g, (char) => {
  const escapeMap = { '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&#x27;' };
  return escapeMap[char];
});

// DON'T: Return raw LLM output to browser
// DON'T: Trust user input or LLM output
```

---

## 4. Rate Limiting & DDoS Protection

### 4.1 N8N Rate Limiting Configuration

```json
{
  "webhook": {
    "rateLimiting": {
      "enabled": true,
      "maxRequestsPerMinute": 20,
      "maxRequestsPerHour": 1000,
      "windowMs": 60000,
      "storageType": "memory"
    }
  }
}
```

### 4.2 IP-based Rate Limiting

**Add to N8N webhook node:**

```javascript
// Extract client IP
const clientIP = $json.headers['x-forwarded-for'] || 
                 $json.headers['x-real-ip'] ||
                 $json.remoteAddress;

// Hash IP (don't store raw IP for GDPR)
const ipHash = require('crypto')
  .createHash('sha256')
  .update(clientIP)
  .digest('hex');

return {
  ...json,
  client_ip_hash: ipHash,
  request_id: $execution.id
};
```

### 4.3 Exponential Backoff for Retries

```javascript
// If Gemini API fails, retry with increasing delay
{
  "retryCount": 3,
  "retryInterval": {
    "0": 1000,    // 1 second
    "1": 5000,    // 5 seconds
    "2": 15000    // 15 seconds
  }
}
```

---

## 5. Error Handling & Logging

### 5.1 Safe Logging (No Sensitive Data)

**✅ DO:**
```javascript
// Log only necessary info
NexGen_Security::log_event('n8n_response', [
  'latency_ms' => (microtime(true) - $start) * 1000,
  'tokens_used' => $response['usage']['completion_tokens'],
  'session_id' => $session_id,
  'status' => $response['status'],
  'error_type' => $error ? get_class($error) : null,
]);
```

**❌ DON'T:**
```javascript
// NEVER log sensitive data
NexGen_Security::log_event('n8n_response', [
  'api_key' => $api_key,              // ❌ EXPOSED!
  'full_message' => $user_message,    // ❌ Privacy issue
  'api_response' => $full_response,   // ❌ Could contain PII
]);
```

### 5.2 Error Handling in N8N

```javascript
// Safe error handling - don't expose internal details
try {
  response = await callGeminiAPI(message);
} catch (error) {
  console.log({
    error_type: error.constructor.name,
    error_code: error.code,
    // DON'T log: error.message (might contain API details)
  });
  
  // Return user-friendly message
  return {
    success: false,
    error: 'Unable to process your message. Please try again.',
    request_id: $execution.id  // For debugging without exposing details
  };
}
```

### 5.3 Log Retention Policy

```
- Delete user IPs after 30 days (GDPR)
- Delete conversation content after 90 days
- Keep aggregated metrics forever
- Encrypt logs at rest
- Archive old logs to cold storage
```

---

## 6. Database Security

### 6.1 Protected Message Storage

```php
// WordPress message storage with encryption
class NexGen_Message_Service {
  public static function save_message($session_id, $text, $type, $metadata = []) {
    global $wpdb;
    
    // Hash IP for privacy
    $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR']);
    
    // Sanitize text
    $safe_text = wp_kses_post($text); // Allows safe HTML
    
    // Encrypt sensitive metadata
    $encrypted_meta = self::encrypt_data(json_encode($metadata));
    
    return $wpdb->insert(
      $wpdb->prefix . 'nexgen_chat_messages',
      [
        'session_id' => $session_id,
        'message_text' => $safe_text,
        'message_type' => $type, // 'user', 'bot', 'system'
        'sender_info' => $encrypted_meta,
        'ip_hash' => $ip_hash, // Never store raw IP
        'created_at' => current_time('mysql'),
      ],
      ['%s', '%s', '%s', '%s', '%s', '%s']
    );
  }
  
  // Encrypt lead data (email, phone)
  private static function encrypt_data($data) {
    // Use WordPress table prefix as salt
    $key = hash('sha256', ABSPATH);
    // Simple encryption - upgrade to sodium for production
    return base64_encode($data . $key);
  }
}
```

### 6.2 SQL Injection Prevention

**✅ Prepared Statements:**
```php
$wpdb->prepare(
  "SELECT * FROM {$wpdb->prefix}nexgen_chat_messages WHERE session_id = %s AND created_at > %s",
  $session_id,
  date('Y-m-d H:i:s', strtotime('-30 days'))
);
```

**❌ String Concatenation:**
```php
// NEVER do this!
"SELECT * FROM messages WHERE session_id = '{$session_id}'"
```

---

## 7. CORS & Cross-Origin Security

### 7.1 Configure CORS Headers

**WordPress REST API:**
```php
// Add to functions.php or plugin
add_filter('rest_pre_serve_request', function($served) {
  header('Access-Control-Allow-Origin: https://yourdomain.com');
  header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
  header('Access-Control-Allow-Headers: Authorization, Content-Type');
  header('Access-Control-Max-Age: 3600');
  return $served;
});
```

### 7.2 N8N CORS Configuration

```json
{
  "corsProxy": {
    "enabled": true,
    "allowedOrigins": ["https://yourdomain.com"],
    "allowedMethods": ["POST", "OPTIONS"],
    "allowedHeaders": ["Content-Type", "Authorization"]
  }
}
```

---

## 8. Performance Optimization

### 8.1 Caching Strategy

**Cache WordPress context:**
```javascript
// Don't fetch context every message - cache for 1 hour
const cacheKey = 'nexgen_context_cache';
let context = $json.storage.get(cacheKey);

if (!context) {
  context = await fetchWordPressContext();
  $json.storage.set(cacheKey, context, 3600); // 3600 seconds = 1 hour
}

return {...$json, context};
```

### 8.2 Reduce API Calls

**Batch requests:**
```javascript
// Instead of fetching each thing separately
// ❌ 3 API calls
context.services = fetchServices();
context.pricing = fetchPricing();
context.faq = fetchFAQ();

// ✅ 1 API call returning all
context = await fetch('/wp-json/nexgen/v1/full-context');
```

### 8.3 Limit Context Size

```javascript
// Reduce token usage (= lower costs)
{
  "services": services.slice(0, 5),      // Max 5 services
  "faq": faq.slice(0, 10),               // Max 10 FAQ entries
  "portfolio": portfolio.slice(0, 3)     // Max 3 projects
  // Result: ~2K tokens vs ~10K tokens = 5x cost savings!
}
```

---

## 9. Monitoring & Alerting

### 9.1 Key Metrics to Monitor

```
1. API Error Rate
   Target: < 0.1%
   Alert if: > 1%

2. Response Time
   Target: < 3 seconds
   Alert if: > 5 seconds

3. Cost per Request
   Target: $0.001-0.005
   Alert if: > $0.01

4. Uptime
   Target: 99.9%
   Alert if: < 99%
```

### 9.2 N8N Monitoring Setup

```javascript
// Add to workflow to track metrics
{
  "metrics": {
    "execution_time": $execution.time,
    "tokens_used": $json.usage.completion_tokens,
    "api_calls": $json.api_call_count,
    "errors": $json.errors.length,
    "lead_score": $json.lead_quality_score
  }
}
```

### 9.3 Error Alerts

**Set up notifications if:**
- Gemini API fails 3+ times in a row
- Response time exceeds 10 seconds
- Rate limit exceeded
- Database connection fails
- N8N workflow crashes

---

## 10. Compliance & Privacy

### 10.1 GDPR Compliance

```
✅ To-Do:
- [x] Collect only necessary data (message, email if provided)
- [x] Hash IP addresses (not raw IPs)
- [x] Delete messages after 90 days
- [x] Allow users to request their data
- [x] Implement data export function
- [x] Clear cookies when conversation ends
- [x] Display privacy policy in chat widget
```

### 10.2 Data Retention Policy

```php
// Auto-delete old messages (90 days)
wp_schedule_event(time(), 'daily', 'nexgen_cleanup_old_messages');

add_action('nexgen_cleanup_old_messages', function() {
  global $wpdb;
  
  $cutoff = date('Y-m-d', strtotime('-90 days'));
  
  $wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->prefix}nexgen_chat_messages 
     WHERE created_at < %s AND message_type = 'user'",
    $cutoff
  ));
});
```

### 10.3 User Consent

```html
<!-- Add to chat widget -->
<div class="nexgen-consent">
  <p>💬 This chat uses AI to provide faster responses.</p>
  <p>Your message and email (if provided) may be stored for 90 days.</p>
  <a href="/privacy-policy/">Privacy Policy</a>
</div>
```

---

## 11. Disaster Recovery

### 11.1 Backup N8N Workflow

```bash
# Export workflow regularly
1. In N8N, click workflow → Export
2. Save JSON to Git (without secrets)
3. Version control: workflows/gemini-chatbot-v1.0.json
4. Keep last 5 versions for rollback
```

### 11.2 Fallback Strategy

**If Gemini API fails:**
```javascript
try {
  response = await callGeminiAPI();
} catch (error) {
  // Fallback 1: Return FAQ entry
  if (matchesFAQ(message)) {
    return faqAnswer;
  }
  
  // Fallback 2: Simple keyword match
  if (matchesKeyword(message)) {
    return templateResponse;
  }
  
  // Fallback 3: Route to Telegram human
  return routeToTelegram(message, session_id);
}
```

### 11.3 Test Disaster Recovery

```
Weekly:
- Simulate API failure
- Test fallback responses
- Verify routing to Telegram works
```

---

## 12. Security Checklist

- [ ] All API keys in environment variables
- [ ] N8N webhook requires Bearer token
- [ ] WordPress REST endpoint validates API key
- [ ] Input validation before API call
- [ ] Output sanitization before sending to frontend
- [ ] Rate limiting enabled (20 req/min per session)
- [ ] Logging doesn't contain sensitive data
- [ ] IP addresses hashed (not stored raw)
- [ ] HTTPS only (no HTTP connections)
- [ ] CORS properly configured
- [ ] Database uses prepared statements
- [ ] Error messages don't expose internal details
- [ ] Regular key rotation (monthly)
- [ ] Data retention policy enforced (90 days)
- [ ] Backup/rollback strategy tested
- [ ] Monitoring & alerting configured
- [ ] Privacy policy updated
- [ ] User consent notice displayed

---

## Quick Reference: Common Mistakes

| Mistake | Impact | Fix |
|---------|--------|-----|
| API key in code | 🔴 CRITICAL leak | Use env variables |
| No input validation | 🔴 CRITICAL injection attacks | Validate all input |
| Logging PII | 🟠 MEDIUM privacy breach | Remove sensitive data |
| No rate limiting |  🟠 MEDIUM DDoS vulnerability | Limit 20 req/min |
| Raw IPs stored | 🟡 LOW GDPR issue | Hash with SHA256 |
| SQL string concat | 🔴 CRITICAL SQL injection | Use prepared statements |
| Error messages expose details | 🟡 LOW info disclosure | Generic error messages |

---

## Additional Resources

- [OWASP Top 10 Security Risks](https://owasp.org/www-project-top-ten/)
- [N8N Security Best Practices](https://docs.n8n.io/security/)
- [Google AI Safety Guidelines](https://ai.google/responsibility/)
- [WordPress Plugin Security](https://developer.wordpress.org/plugins/security/)

