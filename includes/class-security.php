<?php
/**
 * Security Service for NexGen Telegram Chat
 * Handles nonce verification, input sanitization, validation, and rate limiting
 *
 * @package NexGenTelegramChat
 * @subpackage Security
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NexGen_Security {

	const NONCE_ACTION = 'nexgen_chat_nonce';
	const RATE_LIMIT_TRANSIENT = 'nexgen_rate_limit_';
	const MAX_MESSAGES_PER_MINUTE = 20;
	const MAX_MESSAGE_LENGTH = 1000;

	/**
	 * Verify AJAX nonce
	 * 
	 * Validates nonce from POST request to prevent CSRF attacks.
	 * Nonce is valid for 24 hours by default in WordPress.
	 *
	 * @param string $nonce The nonce to verify (if empty, checks $_POST['nonce']).
	 * @return bool True if valid nonce, false otherwise.
	 */
	public static function verify_nonce( string $nonce = '' ): bool {
		if ( empty( $nonce ) ) {
			$nonce = isset( $_POST['nonce'] ) ? (string) $_POST['nonce'] : '';
		}
		return wp_verify_nonce( $nonce, self::NONCE_ACTION ) !== false;
	}

	/**
	 * Get clean nonce for localization
	 * 
	 * Creates fresh nonce for JavaScript to use in AJAX calls.
	 * Called in wp_localize_script() for frontend.
	 *
	 * @return string The generated nonce value.
	 */
	public static function create_nonce(): string {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Validate and sanitize a message
	 * 
	 * Performs multi-layer validation:
	 * 1. Check if empty
	 * 2. Check length
	 * 3. Sanitize HTML/special chars
	 * 4. Check for XSS payloads
	 *
	 * @param string $message The message to validate.
	 * @param int    $max_length Maximum message length (default 1000 chars).
	 * 
	 * @return array ['valid' => bool, 'message' => string, 'error' => string|null]
	 */
	public static function validate_message( string $message = '', int $max_length = self::MAX_MESSAGE_LENGTH ): array {
		// Check if empty
		$message = isset( $message ) ? $message : '';
		$message = trim( $message );

		if ( empty( $message ) ) {
			return [
				'valid'   => false,
				'message' => '',
				'error'   => 'Mensaje vacío',
			];
		}

		// Check length
		if ( strlen( $message ) > $max_length ) {
			return [
				'valid'   => false,
				'message' => '',
				'error'   => sprintf( 'Mensaje demasiado largo (máximo %d caracteres)', $max_length ),
			];
		}

		// Sanitize
		$clean_message = sanitize_text_field( $message );

		// Check for common XSS patterns
		if ( self::has_xss_payload( $clean_message ) ) {
			return [
				'valid'   => false,
				'message' => '',
				'error'   => 'Mensaje contiene caracteres no permitidos',
			];
		}

		return [
			'valid'   => true,
			'message' => $clean_message,
			'error'   => null,
		];
	}

	/**
	 * Validate session ID format
	 * 
	 * Session IDs must match format: chat_<slug>_<6-hex>
	 * Example: chat_mysite_a1b2c3
	 * This prevents injection attacks via session verification.
	 *
	 * @param string $session_id The session ID to validate.
	 * @return bool True if valid format, false otherwise.
	 */
	public static function validate_session_id( string $session_id = '' ): bool {
		if ( empty( $session_id ) ) {
			return false;
		}
		// Format: chat_<slug>_<6-hex>
		return preg_match( '/^chat_[a-z0-9\-]+_[a-f0-9]{6}$/i', $session_id ) === 1;
	}

	/**
	 * Check for XSS attack patterns
	 * 
	 * Detects common JavaScript injection payloads.
	 * Checked after sanitization as additional defense layer.
	 *
	 * @param string $text Text to check for payloads.
	 * @return bool True if potential XSS payload detected.
	 */
	private static function has_xss_payload( string $text ): bool {
		$patterns = [
			'/<script[^>]*>.*?<\/script>/is',
			'/javascript:/i',
			'/on\w+\s*=/i',
			'/<iframe[^>]*>/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get user's real IP address
	 * 
	 * Checks common IP headers to handle proxies and load balancers.
	 * Hashed later for GDPR compliance (not logged in plain form).
	 *
	 * @return string The user's IP address (or 'unknown' if unable to determine).
	 */
	public static function get_user_ip(): string {
		$ip_keys = [
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		];

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return (string) $ip;
					}
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	}

	/**
	 * Hash IP address for privacy
	 * 
	 * Uses SHA256 to irreversibly hash IP for GDPR compliance.
	 * Cannot be reversed, but allows duplicate detection.
	 *
	 * @param string $ip The IP address to hash.
	 * @return string SHA256 hash of IP (64 chars).
	 */
	public static function hash_ip( string $ip ): string {
		return hash( 'sha256', $ip . get_option( 'siteurl' ) );
	}

	/**
	 * Check rate limit for a session
	 * 
	 * Limits messages per session to prevent spam/abuse.
	 * Uses WordPress transients (expires after 60 seconds).
	 * Default: 20 messages per minute per session.
	 *
	 * @param string $session_id The session ID to rate-limit.
	 * @param int    $limit Number of allowed messages per period (default 20).
	 * @param int    $period Time period in seconds (default 60).
	 * 
	 * @return array ['allowed' => bool, 'remaining' => int, 'reset_in' => int]
	 */
	public static function check_rate_limit( string $session_id, int $limit = self::MAX_MESSAGES_PER_MINUTE, int $period = 60 ): array {
		$transient_key = self::RATE_LIMIT_TRANSIENT . $session_id;
		$current_count  = (int) get_transient( $transient_key );

		if ( $current_count >= $limit ) {
			return [
				'allowed'   => false,
				'remaining' => 0,
				'reset_in'  => $period,
			];
		}

		// Increment count
		set_transient( $transient_key, $current_count + 1, $period );

		return [
			'allowed'   => true,
			'remaining' => $limit - $current_count - 1,
			'reset_in'  => $period,
		];
	}

	/**
	 * Validate Telegram webhook signature (optional)
	 * 
	 * Basic validation of webhook payload structure.
	 * Checks that required fields exist in update object.
	 *
	 * @param array  $update The webhook payload from Telegram.
	 * @param string $bot_token The Telegram bot token (for future HMAC validation).
	 * @return bool True if signature/structure is valid.
	 */
	public static function validate_telegram_signature( array $update, string $bot_token ): bool {
		if ( empty( $update ) ) {
			return false;
		}

		// For now, basic payload structure check
		if ( ! isset( $update['message'] ) && ! isset( $update['callback_query'] ) && ! isset( $update['update_id'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Escape HTML in message content for safe display
	 * 
	 * Prevents XSS by converting HTML special characters to entities.
	 * Safe for display in HTML context.
	 *
	 * @param string $text The text to escape.
	 * @return string The escaped text (safe for HTML output).
	 */
	public static function escape_message( string $text ): string {
		return esc_html( $text );
	}

	/**
	 * Log security event
	 * 
	 * Records security events to debug.log when WP_DEBUG is enabled.
	 * Never logs sensitive data (passwords, API keys).
	 * Safe for production (disabled by default without WP_DEBUG).
	 *
	 * @param string $event Event name (e.g., 'rate_limit_exceeded').
	 * @param array  $data Event data (uses wp_json_encode for safety).
	 * @return void
	 */
	public static function log_event( string $event, array $data = [] ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}

		$log_entry = sprintf(
			"[%s] NexGen Chat - %s: %s\n",
			current_time( 'mysql' ),
			$event,
			wp_json_encode( $data )
		);

		error_log( $log_entry );
	}
}
