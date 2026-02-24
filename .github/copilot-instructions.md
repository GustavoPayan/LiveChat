# Copilot / AI Agent Instructions — LiveChat (NexGen Telegram Chat)

Purpose: quickly orient an AI coding agent to be productive in this WordPress plugin repository.

Key context
- Project type: WordPress plugin (PHP backend + frontend JS/CSS). See README for intent and install notes.
- Main entry: [nexgen-telegram-chat.php](nexgen-telegram-chat.php#L1-L40) — contains the `NexGenTelegramChat` class and most behavior.
- Frontend: [assets/chat.js](assets/chat.js#L1-L60) — UI logic, polling, AJAX calls, sessionStorage usage.

Big-picture architecture
- Single-plugin PHP class registers WordPress hooks (init, wp_enqueue_scripts, wp_footer, admin_menu, admin_init).
- Frontend <-> backend flow:
  - `assets/chat.js` calls `admin-ajax.php` actions (AJAX) using the localized `nexgen_chat_ajax` object.
  - PHP handlers live in `nexgen-telegram-chat.php` and respond to actions: `nexgen_send_message`, `nexgen_get_messages`, `nexgen_set_chat_name`, `nexgen_telegram_webhook`, `nexgen_debug_test`, `nexgen_set_webhook`.
  - Messages are persisted in a custom DB table: `$wpdb->prefix . 'nexgen_chat_messages'` (created via `create_tables()` using `dbDelta`).
  - Outbound to Telegram uses `send_to_telegram()` (Telegram Bot API `sendMessage`). Inbound responses come through webhook routed to `admin-ajax.php?action=nexgen_telegram_webhook`.

Important conventions & patterns (project-specific)
- Session ID format: `chat_<slug>_<6-hex>` (see `extract_name_from_session()` and `handle_set_chat_name()` in [nexgen-telegram-chat.php](nexgen-telegram-chat.php#L430-L520)). Agents should preserve this format when generating or validating session IDs.
- Message parsing for replies: bot replies are associated by extracting the session id from the text line that contains `🔗 Sesión: chat_...` (regex in `process_reply_to_message()`). Do not change that literal token unless updating both send and receive code paths.
- AJAX nonce name: `nexgen_chat_nonce` — every AJAX handler expects and verifies this nonce. Use `wp_localize_script` variable `nexgen_chat_ajax.nonce` in JS tests.
- Settings keys in WP options: e.g. `nexgen_bot_token`, `nexgen_chat_id`, `nexgen_welcome_message`, `nexgen_primary_color`, etc. See `admin_init()` registration.
- Polling: frontend polls with `poll_interval` configured in `get_chat_settings()` (default 3000ms). Prefer non-invasive changes to polling unless performance testing justifies it.
- Use of sessions + sessionStorage: PHP session (`$_SESSION`) stores `nexgen_chat_session` and `nexgen_chat_name`; JS stores name in `sessionStorage`. Keep both in sync when modifying name flows.

Security & validation patterns
- Handlers validate nonces with `wp_verify_nonce()` and sanitize inputs using `sanitize_text_field()`.
- Admin-only operations guard with `current_user_can('manage_options')` (e.g., `debug_test`, `set_webhook`).
- DB writes use `$wpdb->insert()`; keep the same column names/types when modifying message storage.

Developer workflows & quick commands
- There is no standalone test harness — run and debug inside a local WP environment (LocalWP / Docker / XAMPP). Steps to test key flows:
  1. Install the plugin ZIP or copy these files into `wp-content/plugins/nexgen-telegram-chat`.
  2. Activate plugin in WP admin.
  3. Configure `nexgen_bot_token` and `nexgen_chat_id` in the plugin admin page rendered by `admin_page()`.
  4. Use the admin UI buttons to `Enviar mensaje de prueba` (calls `nexgen_debug_test`) and `Configurar Webhook` (calls `nexgen_set_webhook`).
- To simulate inbound Telegram replies locally, POST JSON to `admin-ajax.php?action=nexgen_telegram_webhook` with the same structure Telegram sends; the plugin logs webhook input when `WP_DEBUG` is true.

Files to inspect when making changes
- [nexgen-telegram-chat.php](nexgen-telegram-chat.php#L1-L60) — plugin bootstrap, hooks and most handlers.
- [nexgen-telegram-chat.php](nexgen-telegram-chat.php#L200-L320) — `send_to_telegram()` and message formatting (important for reply parsing).
- [assets/chat.js](assets/chat.js#L1-L120) — chat UX, polling, AJAX payloads and error handling.
- [assets/chatbot.css](assets/chatbot.css#L1-L40) — visual variables (`--primary-color`) used in `render_chat()` (keep style names and CSS variables consistent if renaming classes).

What to avoid changing without coordinated edits
- The literal message format that includes `🔗 Sesión:` and the session id — it's used to tie Telegram replies back to session state. If you change the token/string, update both `send_to_telegram()` and `process_reply_to_message()` simultaneously.
- The AJAX `action` names and nonce behavior — JS and PHP must match exactly.

Examples (copy-paste friendly)
- AJAX send (from `assets/chat.js`):

```js
// payload used when sending message
{
  action: 'nexgen_send_message',
  message: 'Hello',
  session_id: sessionId,
  nonce: nexgen_chat_ajax.nonce
}
```

- Reply extraction regex (do not change lightly):

```php
// in process_reply_to_message()
preg_match('/🔗 Sesión: (chat_[A-Za-z0-9\\-]+_[A-Fa-f0-9]{6})/', $original_message, $matches)
```

Patch & PR guidelines for agents
- Make minimal, focused edits. If altering the message format or session semantics, include coordinated changes to both PHP and JS, and update this instructions file.
- Preserve user-facing strings (Spanish) or keep translations consistent.

If anything here is unclear or missing, tell me which area you want expanded (webhook testing, message schema, or admin flows) and I'll update this file.
