# Phase 3 & 4 Implementation Checklist

## Phase 3: Security Hardening (Current Priority)

### 3.1 Rate Limiting
- [x] Rate limit logic implemented in `NexGen_Security::check_rate_limit()`
- [ ] Integrated into `handle_send_message()` handler
- [ ] Test: Send 25 messages in 60 seconds, verify 401 on message #21
- [ ] Admin notification when rate limit exceeded
- [ ] Custom rate limit per user role (admin higher than visitor)

**Code Location:** `includes/class-security.php` (line 171)

```php
// Add to handle_send_message() after nonce check:
$rate_check = NexGen_Security::check_rate_limit($session_id);
if (!$rate_check['allowed']) {
    wp_send_json_error([
        'error' => 'Too many messages',
        'retry_after' => $rate_check['reset_in']
    ], 429);
}
```

### 3.2 Input Validation Enhancement
- [x] Basic validation in `NexGen_Security::validate_message()`
- [ ] Add regex patterns for common XSS attacks
- [ ] Add SQL injection pattern detection
- [ ] Test with OWASP payloads
- [ ] Sanitize before saving to DB

**Test Case:**
```bash
# These should all be rejected
curl -X POST ... -d '{"message":"<script>alert(1)</script>"}'
curl -X POST ... -d '{"message":"1; DROP TABLE users;--"}'
curl -X POST ... -d '{"message":"\" OR \"1\"=\"1"}'
```

### 3.3 Webhook Signature Validation
- [ ] Telegram webhook validation in `handle_telegram_webhook()`
- [ ] Verify X-Telegram-Bot-API-Secret-Token header
- [ ] Log failed signature attempts
- [ ] Test: Send webhook with wrong signature, verify rejection

**Code to Add:**
```php
public function handle_telegram_webhook() {
    $request_body = file_get_contents('php://input');
    $webhook_token = get_option('nexgen_telegram_webhook_token');
    
    // Verify signature
    $headers = getallheaders();
    $received_token = $headers['X-Telegram-Bot-Api-Secret-Token'] ?? null;
    
    if (!hash_equals($webhook_token, $received_token ?? '')) {
        NexGen_Security::log_event('webhook_signature_failed', [
            'expected_length' => strlen($webhook_token),
            'received_length' => strlen($received_token ?? '')
        ]);
        wp_send_json_error('Unauthorized', 401);
    }
    
    // Continue processing...
}
```

### 3.4 API Key Encryption
- [ ] Store N8N API key encrypted in options
- [ ] Add encryption/decryption methods to `NexGen_Security`
- [ ] Use WordPress built-in encryption (sodium if available)
- [ ] Test: Verify API key is not readable in plain text in DB

**Implementation Option:**
```php
// Option 1: Using WordPress nonces (simple)
wp_hash(get_option('nexgen_n8n_api_key'));

// Option 2: Using sodium (PHP 7.2+, recommended)
sodium_crypto_secretbox(
    $api_key,
    base64_decode(NEXGEN_ENCRYPTION_KEY)
);
```

### 3.5 Enhanced Logging
- [ ] Log all AJAX actions with timestamp
- [ ] Log rate limit attempts
- [ ] Log validation failures
- [ ] Log webhook signature failures
- [ ] Create log rotation (keep last 7 days)
- [ ] Review logs weekly for suspicious activity

**Locations to add logging:**
- [ ] `handle_send_message()` - log each message
- [ ] `check_rate_limit()` - log when exceeded
- [ ] `validate_message()` - log validation failures
- [ ] `handle_telegram_webhook()` - log webhook events
- [ ] `handle_test_n8n()` - log N8N test results

### 3.6 Testing & Validation
- [ ] Complete security validation tests
- [ ] Test rate limiting with bot
- [ ] Test XSS payloads (should all fail)
- [ ] Test SQL injection payloads (should all fail)
- [ ] Generate security audit report

---

## Phase 4: Context-Aware Chatbot (Gemini AI)

### 4.1 Foundation Setup
- [ ] Create `NexGen_Context_Service` class
  - [x] Already created: `includes/class-context-service.php`
  - [ ] Implement `get_services()` method
  - [ ] Implement `get_pricing()` method
  - [ ] Implement `get_faq()` method
  - [ ] Implement `extract_lead_info()` method
  - [ ] Implement `calculate_lead_quality()` method

### 4.2 WordPress REST Endpoints
- [ ] Add `/wp-json/nexgen/v1/context` endpoint
- [ ] Add `/wp-json/nexgen/v1/save-message` endpoint
- [ ] Add API authentication (Bearer token)
- [ ] Test endpoints with cURL

**Endpoints to Create:**
```php
// In class-plugin.php admin_init():
register_rest_route('nexgen/v1', '/context', [
    'methods' => 'GET',
    'callback' => [NexGen_Context_Service::class, 'get_context'],
    'permission_callback' => fn() => self::verify_api_key()
]);

register_rest_route('nexgen/v1', '/save-message', [
    'methods' => 'POST',
    'callback' => [NexGen_Message_Service::class, 'save_from_api'],
    'permission_callback' => fn() => self::verify_api_key()
]);
```

### 4.3 Google Gemini API Setup
- [ ] Create Google Cloud account
- [ ] Create Gemini API project
- [ ] Generate API key
- [ ] Add API key to `.env` file
- [ ] Test API key with simple curl request

**Verify API key:**
```bash
curl -X GET \
  "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=YOUR_API_KEY" \
  -H "Content-Type: application/json"
```

### 4.4 N8N Workflow Setup
- [ ] Create N8N account (n8n.cloud or self-hosted)
- [x] Workflow created: `n8n-gemini-workflow.json`
- [ ] Import workflow to N8N
- [ ] Configure environment variables
  - [ ] WORDPRESS_URL
  - [ ] WORDPRESS_API_KEY
  - [ ] GEMINI_API_URL
  - [ ] GEMINI_API_KEY
- [ ] Set up credentials
  - [ ] Gemini API credential
  - [ ] WordPress Bearer token credential
- [ ] Configure rate limiting (20 req/min)
- [ ] Test webhook connectivity

### 4.5 Admin Page Configuration
- [ ] Add "Use Gemini AI" checkbox
- [ ] Add services JSON textarea
- [ ] Add pricing tiers JSON textarea
- [ ] Add FAQ entries JSON textarea
- [ ] Add API key generation button
- [ ] Add test button for Gemini integration
- [ ] Register all new settings

**Settings to Register:**
```php
register_setting('nexgen_chat_settings', 'nexgen_use_gemini');
register_setting('nexgen_chat_settings', 'nexgen_services');
register_setting('nexgen_chat_settings', 'nexgen_pricing_tiers');
register_setting('nexgen_chat_settings', 'nexgen_faq_entries');
register_setting('nexgen_chat_settings', 'nexgen_api_key');
```

### 4.6 Message Routing Update
- [ ] Modify `handle_send_message()` to check `nexgen_use_gemini`
- [ ] Route to N8N webhook if Gemini enabled
- [ ] Include fallback to Telegram if N8N fails
- [ ] Test full flow: message → Gemini → response

### 4.7 Lead Extraction & Scoring
- [ ] Implement lead extraction in N8N
- [ ] Calculate lead quality score
- [ ] Store extracted emails/phones with score
- [ ] Create lead list in WordPress admin
- [ ] Add export to CSV functionality

**Lead Data Stored:**
```json
{
  "email": "john@example.com",
  "phone": "+1234567890",
  "service_interest": "web development",
  "urgency": "high",
  "quality_score": 85,
  "extracted_budget": "$5000"
}
```

### 4.8 Testing & Validation
- [ ] Test context fetching works
- [ ] Test Gemini API integration
- [ ] Test N8N workflow end-to-end
- [ ] Test lead extraction accuracy
- [ ] Load test: 100 concurrent messages
- [ ] Test fallback when Gemini fails
- [ ] Verify messages saved to DB correctly
- [ ] Check lead scores calculated correctly

---

## Quick Start: Next 5 Steps

### Step 1: Integrate Rate Limiting (15 min)
```php
// In handle_send_message(), after nonce verification:
$rate_check = NexGen_Security::check_rate_limit($session_id);
if (!$rate_check['allowed']) {
    wp_send_json_error('Too many requests. Try again in 60 seconds.', 429);
}
```

### Step 2: Set Up Google Gemini API (10 min)
```bash
1. Go to https://aistudio.google.com/apikey
2. Click "Get API Key"
3. Accept terms
4. Copy API key
5. Add to .env: GEMINI_API_KEY=sk-... 
```

### Step 3: Create WordPress REST Endpoints (20 min)
Copy the code from section 4.2 and add to `class-plugin.php`

### Step 4: Import N8N Workflow (10 min)
```bash
1. Open n8n.cloud
2. Import n8n-gemini-workflow.json
3. Configure environment variables
4. Test webhook
```

### Step 5: Add Admin Settings (30 min)
Add to `admin-page.php`:
- Use Gemini checkbox
- Services JSON textarea
- Pricing JSON textarea
- API key field

---

## Testing Checklist

### Security Tests
- [ ] Rate limiting blocks 21st message within 60 seconds
- [ ] XSS payload `<script>alert(1)</script>` rejected
- [ ] SQL injection `'; DROP TABLE;--` rejected
- [ ] Webhook without token returns 401
- [ ] API without Bearer token returns 401

### Functionality Tests
- [ ] Message with service keyword routes to Gemini
- [ ] Response appears in chat within 3 seconds
- [ ] "Responderemos pronto" NOT shown for Gemini responses
- [ ] Lead email extracted correctly
- [ ] Lead phone extracted correctly
- [ ] Lead quality score between 0-100

### Performance Tests
- [ ] Average response time < 3 seconds
- [ ] Can handle 20 concurrent messages
- [ ] API errors < 0.1%
- [ ] Database saves complete in < 500ms

### Integration Tests
- [ ] Gemini API call succeeds with context
- [ ] N8N webhook receives message correctly
- [ ] Response saves to WordPress database
- [ ] Lead data exported to CSV correctly
- [ ] Fallback to Telegram works when Gemini fails

---

## Files Created/Modified

### New Files
- ✅ `includes/class-context-service.php` - Context fetching service
- ✅ `n8n-gemini-workflow.json` - N8N workflow with Gemini
- ✅ `GEMINI_SETUP_GUIDE.md` - Complete setup instructions
- ✅ `N8N_SECURITY_BEST_PRACTICES.md` - Security hardening guide
- ✅ `PHASE_3_4_CHECKLIST.md` - This file

### Modified Files
- [ ] `includes/class-plugin.php` - Add REST endpoints, rate limiting
- [ ] `includes/class-n8n-service.php` - Update routing to Gemini
- [ ] `includes/admin-page.php` - Add new settings for Gemini
- [ ] `assets/chat.js` - Already updated to handle `automated` flag
- [ ] `ENHANCEMENT_PLAN.md` - ✅ Updated with Phase 3 & 4 details

---

## Timeline Estimate

| Task | Time | Status |
|------|------|--------|
| Rate Limiting | 15 min | ⏳ Ready |
| Google Gemini Setup | 10 min | ⏳ Ready |
| REST Endpoints | 20 min | ⏳ Ready |
| N8N Workflow | 10 min | ⏳ Ready |
| Admin Settings | 30 min | ⏳ Ready |
| Testing | 1 hour | ⏳ Ready |
| **Total** | **~2.5 hours** | ⏳ Ready |

---

## Success Criteria

### Phase 3 Complete When:
- ✅ Rate limiting prevents 21+ messages/minute
- ✅ All XSS/SQL payloads rejected with error
- ✅ All webhook events logged with timestamp
- ✅ No sensitive data in logs
- ✅ Security tests all passing

### Phase 4 Complete When:
- ✅ Gemini generates context-aware responses
- ✅ Lead extraction works (email/phone)
- ✅ Lead quality score 0-100
- ✅ Messages saved with lead metadata
- ✅ Chat widget shows AI responses correctly
- ✅ Fallback to Telegram works on timeout
- ✅ Average response time < 3 seconds

---

## Support & Troubleshooting

**If rate limiting doesn't work:**
- Check that transient is set correctly
- Verify session_id format: `chat_<slug>_<6hex>`

**If Gemini API fails:**
- Verify API key is valid
- Check Google Cloud quota
- Review N8N error logs

**If REST endpoints return 401:**
- Verify API key is saved in WordPress
- Check Authorization header format
- Test with cURL first

**If N8N workflow times out:**
- Check N8N logs for errors
- Verify WordPress URL is accessible from N8N
- Increase timeout in workflow settings

---

## Next Phase: Phase 5 (Code Quality)

After Phase 4 is complete:
- [ ] Add PSR-12 compliance
- [ ] Add type hints to all methods
- [ ] Improve error handling with custom exceptions
- [ ] Create unit tests
- [ ] Create integration tests
- [ ] Performance optimization

