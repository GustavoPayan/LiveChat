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
	 * @param string $nonce The nonce to verify.
	 * @return bool True if valid, false otherwise.
	 */
	public static function verify_nonce( $nonce = '' ) {
		if ( empty( $nonce ) ) {
			$nonce = isset( $_POST['nonce'] ) ? $_POST['nonce'] : '';
		}
		return wp_verify_nonce( $nonce, self::NONCE_ACTION ) !== false;
	}

	/**
	 * Get clean nonce for localization
	 *
	 * @return string The nonce value.
	 */
	public static function create_nonce() {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Validate and sanitize a message
	 *
	 * @param string $message The message to validate.
	 * @param int    $max_length Maximum message length.
	 * @return array ['valid' => bool, 'message' => string, 'error' => string|null]
	 */
	public static function validate_message( $message = '', $max_length = self::MAX_MESSAGE_LENGTH ) {
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
	 * @param string $session_id The session ID to validate.
	 * @return bool True if valid format.
	 */
	public static function validate_session_id( $session_id = '' ) {
		if ( empty( $session_id ) ) {
			return false;
		}
		// Format: chat_<slug>_<6-hex>
		return preg_match( '/^chat_[a-z0-9\-]+_[a-f0-9]{6}$/i', $session_id ) === 1;
	}

	/**
	 * Check for XSS attack patterns
	 *
	 * @param string $text Text to check.
	 * @return bool True if potential XSS payload detected.
	 */
	private static function has_xss_payload( $text ) {
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
	 * @return string The user's IP address.
	 */
	public static function get_user_ip() {
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
						return $ip;
					}
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
	}

	/**
	 * Hash IP address for privacy
	 *
	 * @param string $ip The IP address to hash.
	 * @return string Hashed IP.
	 */
	public static function hash_ip( $ip ) {
		return hash( 'sha256', $ip . get_option( 'siteurl' ) );
	}

	/**
	 * Check rate limit for a session
	 *
	 * @param string $session_id The session ID.
	 * @param int    $limit Number of allowed messages per minute.
	 * @return array ['allowed' => bool, 'remaining' => int, 'reset_in' => int]
	 */
	public static function check_rate_limit( $session_id, $limit = self::MAX_MESSAGES_PER_MINUTE ) {
		$transient_key = self::RATE_LIMIT_TRANSIENT . $session_id;
		$current_count  = (int) get_transient( $transient_key );

		if ( $current_count >= $limit ) {
			return [
				'allowed'   => false,
				'remaining' => 0,
				'reset_in'  => 60,
			];
		}

		// Increment count
		set_transient( $transient_key, $current_count + 1, 60 );

		return [
			'allowed'   => true,
			'remaining' => $limit - $current_count - 1,
			'reset_in'  => 60,
		];
	}

	/**
	 * Validate Telegram webhook signature (optional)
	 *
	 * @param array  $update The webhook payload.
	 * @param string $bot_token The Telegram bot token.
	 * @return bool True if signature is valid or empty payload.
	 */
	public static function validate_telegram_signature( $update, $bot_token ) {
		if ( empty( $update ) || ! is_array( $update ) ) {
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
	 * @param string $text The text to escape.
	 * @return string The escaped text.
	 */
	public static function escape_message( $text ) {
		return esc_html( $text );
	}

	/**
	 * Log security event
	 *
	 * @param string $event Event name.
	 * @param array  $data Event data.
	 * @return void
	 */
	public static function log_event( $event, $data = [] ) {
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
