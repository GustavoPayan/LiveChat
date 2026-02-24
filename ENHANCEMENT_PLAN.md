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

## Phase 3: Security Hardening

### 3.1 Input Validation & Sanitization
**Current gaps**:
- `sanitize_text_field()` used, but limited
- No rate limiting
- Webhook data not fully validated

**Improvements**:
```php
// New validation class
class Security {
  public static function validate_message($msg, $max_length = 1000) {
    // 1. Trim & empty check
    // 2. Length validation
    // 3. SQL injection test
    // 4. XSS payload detection
    // 5. Rate limit check (IP + session)
  }
  
  public static function validate_telegram_update($payload) {
    // Verify webhook signature if enabled
    // Validate message structure
  }
}
```

### 3.2 Rate Limiting
**Implementation**:
- Store rate limit data in transients: `nexgen_rate_limit_<sessionid>`
- Limit: 20 messages per minute per session
- Return 429 on exceed

### 3.3 Webhook Security
**Current issue**: Webhook accepts any POST, no signature validation  
**Fix**:
- Optional Telegram webhook signature validation
- Required API key for N8N webhooks (encrypted)
- Log all webhook attempts (success/failure)

### 3.4 Database Security
**Current**: Using `$wpdb->insert()` (good)  
**Add**:
- Column `ip_hash` (hashed IP, not raw IP for privacy)
- Column `is_suspicious` flag
- Audit log for admin actions
- SQL injection test in unit tests

### 3.5 CSRF Protection
**Current**: Using `wp_verify_nonce()` (good)  
**Ensure**:
- All AJAX handlers check nonce (audit all actions)
- Nonce refresh on long polling sessions

---

## Phase 4: Code Quality & Best Practices

### 4.1 PSR-12 Compliance
- Add `.phpcs.xml` for PHP CodeSniffer
- Use type hints: `function handle_message(string $msg, string $session_id): bool`
- Add PHPDoc blocks

### 4.2 Error Handling
**Current**: `wp_send_json_error()` / `wp_send_json_success()`  
**Improve**:
```php
try {
  // Logic
} catch (MessageException $e) {
  error_log("NexGen Chat: " . $e->getMessage());
  wp_send_json_error([
    'code' => $e->getCode(),
    'message' => $e->getMessage(),
    'timestamp' => current_time('mysql')
  ]);
}
```

### 4.3 Logging System
- Add `error_log()` calls for important events
- Create debug mode in admin: `NEXGEN_CHAT_DEBUG = true`
- Log file: `wp-content/plugins/nexgen-telegram-chat/logs/debug.log`
- Include timestamp, action, user, result

### 4.4 Type Hints & PHPDoc
```php
/**
 * Save a message to the database
 * @param string $session_id  Unique session identifier
 * @param string $message     Message text (sanitized)
 * @param string $type        Message type: 'user', 'bot', 'system'
 * @param int|null $tg_msg_id Telegram message ID (optional)
 * @return int                Database insert ID
 * @throws MessageException   On database error
 */
public function save_message(
  string $session_id,
  string $message,
  string $type,
  ?int $tg_msg_id = null
): int
```

---

## Phase 5: Frontend Improvements (Optional but Recommended)

### 5.1 Consider Modern Framework
**Current**: jQuery (2012 standard)  
**Options**:
- **Keep jQuery** (less refactor, but outdated)
- **Migrate to Vanilla JS** (modern, smaller bundle)
- **Migrate to Vue 3** (reactive, maintainable, larger bundle ~30KB)

**Recommendation**: Vanilla JS (vanilla-ajax + event listeners) = best balance

### 5.2 Component Structure
If staying with native JS:
```javascript
// ES6 modules
export class ChatWidget {
  constructor(options) { }
  open() { }
  close() { }
  send() { }
}

export class MessageHandler {
  static format(msg, type) { }
  static validate(msg) { }
}
```

### 5.3 Better Error UX
- Show error type: "⚠️ Network error" vs "❌ Bot says: X"
- Keyboard shortcuts (Ctrl+Enter to send)
- Emoji picker for rich replies
- Typing indicators

---

## Phase 6: Documentation & Testing

### 6.1 Documentation
- Update `README.md` with architecture diagram
- Add `ARCHITECTURE.md` (services, data flow)
- Add `SECURITY.md` (encryption, validation, rate limits)
- Add `DEVELOPER.md` (setup, testing, debugging)
- Update `.github/copilot-instructions.md` with new services

### 6.2 Basic Testing
- Unit tests for `Message Service` (save, retrieve, delete)
- Integration tests for Telegram webhook
- Security tests (rate limit, XSS payloads, SQL injection)
- Use PHPUnit

---

## Implementation Roadmap

### Sprint 1 (Week 1-2): Security + Architecture
1. ✅ Create service classes (Message, Telegram, Session)
2. ✅ Add input validation & sanitization library
3. ✅ Implement rate limiting (transients)
4. ✅ Add Webhook signature validation
5. ✅ Update DB schema (add `ip_hash`)

### Sprint 2 (Week 3-4): N8N Integration
1. ✅ Create N8N service class
2. ✅ Add admin settings for N8N
3. ✅ Implement message classification (keyword-based)
4. ✅ Route messages (bot → N8N or Telegram)
5. ✅ Add fallback logic

### Sprint 3 (Week 5-6): Code Quality
1. ✅ Add logging system
2. ✅ Type hints for all methods
3. ✅ PHPDoc blocks
4. ✅ Error handling refactor
5. ✅ Add `.phpcs.xml`

### Sprint 4 (Week 7): Frontend & Polish
1. ✅ Optional: Migrate jQuery → Vanilla JS
2. ✅ Improve error messages
3. ✅ Add typing indicators UI
4. ✅ Test on multiple browsers

### Sprint 5 (Week 8): Testing & Docs
1. ✅ Write unit tests
2. ✅ Write documentation
3. ✅ Create architecture diagrams
4. ✅ Release v3.0.0

---

## File Size Estimates

| File | Current LOC | After Refactor | Change |
|------|-------------|-----------------|--------|
| nexgen-telegram-chat.php | 730 | 150 | -80% ✓ |
| (new) class-plugin.php | - | 120 | - |
| (new) class-message-service.php | - | 180 | - |
| (new) class-telegram-service.php | - | 150 | - |
| (new) class-n8n-service.php | - | 120 | - |
| (new) class-security.php | - | 100 | - |
| assets/chat.js | 380 | 350 | -8% |
| assets/chatbot.css | 339 | 380 | +12% |
| **Total** | **1449** | **1550** | **+7%** |

---

## Security Checklist

- [ ] All inputs sanitized with type validation
- [ ] Rate limiting implemented (20 msg/min per session)
- [ ] CSRF nonces on all AJAX endpoints
- [ ] Webhook signature validation (opt-in)
- [ ] IP-based DDoS protection via rate limits
- [ ] Sensitive data (API keys) hashed/encrypted
- [ ] SQL injection tests passing
- [ ] XSS payload tests passing
- [ ] Log all security events
- [ ] Admin-only functions guarded with `current_user_can()`

---

## Next Steps

1. **Do you want me to start with Phase 1 (Architecture Refactor)?**
2. **Priority on N8N integration or security hardening first?**
3. **Any specific N8N workflows you already have?** (e.g., FAQ bot, lead capture)
4. **Timeline constraints?**

