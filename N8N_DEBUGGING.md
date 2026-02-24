# N8N Debugging Guide - LiveChat Plugin

Having issues with N8N integration? Use this guide to debug and troubleshoot.

---

## 🧪 Quick Test Button (Easiest Way)

1. Go to **WordPress Admin → NexGen Livechat**
2. Scroll down to **"🧪 Prueba N8N (Automatización)"**
3. Click **"🧪 Probar Conexión N8N"**
4. You'll get an instant response showing:
   - ✅ **Success**: Your N8N workflow is reachable and responding
   - ❌ **Error**: Shows the exact error (timeout, wrong URL, network issue, etc.)

---

## 📊 Check Logs (Detailed Debugging)

### Step 1: Enable Debug Mode

Edit your WordPress config file: `wp-config.php`

Add or change these lines (usually near the bottom):

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );  // Prevent errors showing on site
```

### Step 2: View Debug Log

After testing, check the log file:

**Location:**
```
/wp-content/debug.log
```

**Via FTP/SFTP:**
- Connect to your hosting
- Navigate to `/wp-content/`
- Download `debug.log`
- Open in a text editor

**Via WordPress Admin (if you have a debug plugin):**
- Install [Debug Log Viewer](https://wordpress.org/plugins/debug-log-viewer/) plugin
- Go to **Tools → Debug Log**

---

## 🔍 What to Look For in Logs

When you test N8N or send a message, look for entries like:

### ✅ Success Flow
```
[2026-02-23 10:30:45] NexGen Chat - n8n_request_start: 
{"url":"http://localhost:5678/webhook-test/livechat","session_id":"chat_juan_a1b2c3","message":"¿Tienes hosting?","visitor":"Juan"}

[2026-02-23 10:30:46] NexGen Chat - n8n_response: 
{"code":200,"session_id":"chat_juan_a1b2c3","body_length":125}

[2026-02-23 10:30:46] NexGen Chat - n8n_success: 
{"session_id":"chat_juan_a1b2c3","has_response":true,"response_preview":"🌐 Nuestros planes de hosting..."}
```

### ❌ Connection Error
```
[2026-02-23 10:30:46] NexGen Chat - n8n_error: 
{"error":"Failed to connect to 127.0.0.1:5678: Connection refused","session_id":"chat_test_001","url":"http://localhost:5678/webhook-test/livechat"}
```

### ❌ Wrong Webhook URL
```
[2026-02-23 10:30:46] NexGen Chat - n8n_api_error: 
{"code":404,"body":"Webhook not found","url":"http://localhost:5678/webhook-wrong-path"}
```

---

## 🛠️ Manual cURL Test

Test your N8N webhook directly from command line (Mac/Linux/Windows with Git Bash):

```bash
curl -X POST "http://localhost:5678/webhook-test/livechat" \
  -H "Content-Type: application/json" \
  -d '{
  "message": "¿Tienes hosting?",
  "session_id": "chat_test_001",
  "visitor": "Juan",
  "site": "Test Site",
  "timestamp": "2026-02-23 10:00:00"
}'
```

**Expected response:**
```json
{
  "response": "🌐 Nuestros planes de hosting incluyen SSL y soporte 24/7. ¿Cuál es tu presupuesto?"
}
```

**Common cURL errors:**

| Error | Meaning | Fix |
|-------|---------|-----|
| `Connection refused` | N8N not running | Start N8N: `docker run -it -p 5678:5678 n8nio/n8n` |
| `Timeout` | N8N taking too long | Increase timeout in WordPress settings (15-20s) |
| `Cannot resolve host` | Invalid hostname | Check webhook URL spelling |
| `Unsupported protocol` | Wrong URL scheme | Use `http://` or `https://` not `ftp://` |

---

## 🔧 Troubleshooting Checklist

### Problem: "N8N not configured"
- [ ] Go to **Admin → NexGen Livechat**
- [ ] Check "✅ Habilitar N8N" is CHECKED
- [ ] Webhook URL field is NOT empty
- [ ] Click **Save Changes**

### Problem: "Connection timeout" 
- [ ] N8N is running? Try accessing http://localhost:5678 in browser
- [ ] Firewall blocking localhost:5678? (Windows Defender, UFW, etc.)
- [ ] URL is correct in WordPress settings?
- [ ] Increase timeout to 15-20 seconds in WordPress settings

### Problem: "Webhook not found" (404 error)
- [ ] Webhook path is correct? Should be `/webhook-test/livechat` for test workflow
- [ ] N8N workflow is **Activated** (toggle ON)?
- [ ] Check the webhook node "path" parameter in N8N matches your URL
- [ ] Are you using custom subdomain? Webhook URL might be different

### Problem: Messages not routing to N8N
- [ ] Enable WP_DEBUG in wp-config.php
- [ ] Check logs for "n8n_request_start" entries
- [ ] Message contains a keyword? (hosting, seo, dominio, etc.)
- [ ] Keywords match exactly (case-insensitive match)
- [ ] Test with exact keyword: type "hosting" not "hosting is good"

### Problem: N8N doesn't respond in chat
- [ ] N8N workflow is activated
- [ ] Test button shows success? → problem is in workflow logic
- [ ] Check N8N workflow has "Respond to Webhook" node at end
- [ ] Response node returns `{"response": "..."}`

---

## 📋 Example Debug Session

**Scenario:** User types "¿Tienes hosting?" but doesn't get a response

**Steps:**

1. **Verify N8N is running:**
   ```bash
   curl http://localhost:5678  # Should show "Upgrade to newer version..." or similar
   ```

2. **Test webhook directly:**
   ```bash
   curl -X POST "http://localhost:5678/webhook-test/livechat" \
     -H "Content-Type: application/json" \
     -d '{"message":"¿Tienes hosting?","session_id":"test_123","visitor":"Test","site":"Test","timestamp":"2026-02-23 10:00:00"}'
   ```
   - ✅ Got response? → Go to step 4
   - ❌ Connection error? → N8N not running (restart it)
   - ❌ No response? → Check N8N workflow configuration

3. **Test via WordPress admin:**
   - Go to **Admin → NexGen Livechat** 
   - Click **"🧪 Probar Conexión N8N"**
   - Check error message
   - Look at debug.log for n8n_* entries

4. **Test in actual chat:**
   - Open your website
   - Type "¿Tienes hosting?" in chat
   - If no response:
     - Check for "n8n_request_start" in logs → URL issue?
     - Check for "n8n_error" in logs → Connection issue?
     - Check for "n8n_success" in logs → N8N returned empty response?

5. **Check N8N workflow:**
   - Open N8N web interface (http://localhost:5678)
   - Open your "LiveChat Auto-Response" workflow
   - Check execution history for errors
   - Test the workflow manually by clicking "Test" on the Webhook node

---

## 🚀 Advanced: Add More Logging

Want more details? Edit [includes/class-n8n-service.php](includes/class-n8n-service.php) and add logging after any important line:

```php
NexGen_Security::log_event( 'debug_point', [ 'variable' => $variable_value ] );
```

This will appear in debug.log with the exact value.

---

## 📞 Still Having Issues?

1. **Check WordPress error log:**
   ```
   /wp-content/debug.log
   ```
   Search for `n8n_` to see all N8N related events

2. **Check N8N execution logs:**
   - Open N8N web interface
   - Open workflow → **Executions**
   - Look for failed executions with error messages

3. **Test N8N independently:**
   - Create a simple test workflow with just Webhook + "Echo" function
   - Make sure it responds before using with LiveChat

4. **Check WordPress settings:**
   - Admin → NexGen Livechat
   - Verify all N8N settings are saved
   - Verify webhook URL is exactly correct

---

## 🎯 The Complete Flow (for debugging)

```
User types "¿Tienes hosting?" in chat
         ↓
[assets/chat.js]
Sends AJAX request to wp-ajax
         ↓
[includes/class-plugin.php::handle_send_message()]
✅ Verify nonce
✅ Validate input
✅ Check rate limit
↓
[includes/class-n8n-service.php::should_process()]
❌ Does message contain keyword?
   YES → Log "n8n_request_start"
   NO → Skip to Telegram
         ↓
[Log Check Point] Look for: n8n_request_start
         ↓
[includes/class-n8n-service.php::send_to_n8n()]
↓ Send HTTP POST to N8N
↓
[Log Check Point] Look for: n8n_error or n8n_response
         ↓
[N8N Webhook Receives Request]
↓ Process in N8N workflow
↓
[N8N Sends Back JSON Response]
         ↓
[Log Check Point] Look for: n8n_success
         ↓
[includes/class-plugin.php::handle_send_message()]
✅ Save bot message to DB
✅ Return to frontend
         ↓
[assets/chat.js]
Display auto-response in chat
```

Each step has logging. If you get stuck, find which log entry is missing and you know which step failed.

---

## 💡 Pro Tips

- **Local N8N + Remote WordPress:** Use ngrok to expose local N8N publicly
  ```bash
  ngrok http 5678  # Get URL like https://abc123.ngrok.io
  # Use that URL in WordPress settings
  ```

- **Test without N8N:** Set keywords to random strings (like "xyz_test_123") and N8N won't trigger
  - Messages will always go to Telegram
  - Test the plugin works before debugging N8N

- **Docker N8N:** Check logs with:
  ```bash
  docker logs <container_name>
  ```

- **n8n.cloud:** Check webhook URL format - may need full domain

---

**You got this! 🚀 Once you see the success logs, you know it's working!**
