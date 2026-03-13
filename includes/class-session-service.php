<?php
/**
 * Session Service for NexGen Telegram Chat
 * Manages chat sessions, visitor names, and session state
 *
 * @package NexGenTelegramChat
 * @subpackage Session
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NexGen_Session_Service {

	const SESSION_COOKIE = 'nexgen_chat_session';
	const CHAT_NAME_COOKIE = 'nexgen_chat_name';
	const COOKIE_EXPIRY = 2592000; // 30 days

	/**
	 * Initialize session - Now uses cookies instead of PHP sessions
	 * This prevents interference with REST API and loopback requests
	 *
	 * @return void
	 */
	public static function init() {
		// No longer initializing PHP sessions to avoid REST API conflicts
		// Session data is now stored in cookies
	}

	/**
	 * Get or create session ID in proper format
	 * Session data is stored in cookies
	 *
	 * @return string Session ID in format chat_<slug>_<6-hex>
	 */
	public static function get_session_id() {
		// Try to get from cookie first
		if ( isset( $_COOKIE[ self::SESSION_COOKIE ] ) ) {
			$session_id = sanitize_text_field( $_COOKIE[ self::SESSION_COOKIE ] );
			if ( self::validate_format( $session_id ) ) {
				return $session_id;
			}
		}

		// Generate new session and store in cookie
		$new_session = self::generate_session_id();
		setcookie(
			self::SESSION_COOKIE,
			$new_session,
			time() + self::COOKIE_EXPIRY,
			'/',
			'',
			is_ssl(),
			true // httponly
		);

		return $new_session;
	}

	/**
	 * Validate session id format
	 *
	 * @param string $session_id Session ID to validate.
	 * @return bool True if valid format.
	 */
	private static function validate_format( $session_id ) {
		return (bool) preg_match( '/^chat_[a-z0-9\-]+_[a-f0-9]{6}$/i', $session_id );
	}

	/**
	 * Generate new session ID in format chat_<slug>_<6-hex>
	 *
	 * @param string $name Optional name to include in slug.
	 * @return string Generated session ID.
	 */
	public static function generate_session_id( $name = '' ) {
		$slug = 'default';

		if ( ! empty( $name ) ) {
			// Convert name to slug
			$slug = strtolower( trim( preg_replace( '/[^a-zA-Z0-9]+/', '-', $name ), '-' ) );
			if ( empty( $slug ) ) {
				$slug = 'visitor';
			}
		}

		// Generate 6-character hex hash
		$hash = substr( md5( ( $name ?? '' ) . '|' . microtime( true ) . '|' . wp_rand() ), 0, 6 );

		return 'chat_' . $slug . '_' . $hash;
	}

	/**
	 * Extract visitor name from session ID
	 *
	 * @param string $session_id Session ID.
	 * @return string Readable name.
	 */
	public static function extract_name_from_session( $session_id ) {
		// session_id format: chat_<slug_nombre>_<hash6>
		if ( preg_match( '/^chat_([a-z0-9\-]+)_[a-f0-9]{6}$/i', $session_id, $m ) ) {
			$slug = $m[1];
			// Split slug and capitalize words
			$parts = array_filter( preg_split( '/-+/', $slug ) );
			$parts = array_map(
				function( $p ) {
					return mb_convert_case( $p, MB_CASE_TITLE, 'UTF-8' );
				},
				$parts
			);
			return implode( ' ', $parts );
		}

		return 'Visitante';
	}

	/**
	 * Set visitor name and update session
	 * Stores name in cookie
	 *
	 * @param string $name Visitor name.
	 * @return string New session ID with name embedded.
	 * @throws Exception On invalid name.
	 */
	public static function set_chat_name( $name ) {
		// Validate
		$name = sanitize_text_field( trim( $name ) );
		if ( empty( $name ) ) {
			throw new Exception( 'Nombre requerido' );
		}

		// Generate new session with name
		$new_session = self::generate_session_id( $name );

		// Store session ID in cookie
		setcookie(
			self::SESSION_COOKIE,
			$new_session,
			time() + self::COOKIE_EXPIRY,
			'/',
			'',
			is_ssl(),
			true // httponly
		);

		// Store name in cookie
		setcookie(
			self::CHAT_NAME_COOKIE,
			$name,
			time() + self::COOKIE_EXPIRY,
			'/',
			'',
			is_ssl(),
			true // httponly
		);

		return $new_session;
	}

	/**
	 * Get stored visitor name from cookie
	 *
	 * @return string Visitor name or empty string.
	 */
	public static function get_chat_name() {
		return isset( $_COOKIE[ self::CHAT_NAME_COOKIE ] ) ? sanitize_text_field( $_COOKIE[ self::CHAT_NAME_COOKIE ] ) : '';
	}

	/**
	 * Get all session data from cookies
	 *
	 * @return array Session data.
	 */
	public static function get_session_data() {
		return [
			'session_id' => isset( $_COOKIE[ self::SESSION_COOKIE ] ) ? sanitize_text_field( $_COOKIE[ self::SESSION_COOKIE ] ) : '',
			'name'       => isset( $_COOKIE[ self::CHAT_NAME_COOKIE ] ) ? sanitize_text_field( $_COOKIE[ self::CHAT_NAME_COOKIE ] ) : '',
			'created_at' => current_time( 'mysql' ),
		];
	}

	/**
	 * Destroy session by clearing cookies
	 *
	 * @return void
	 */
	public static function destroy() {
		setcookie( self::SESSION_COOKIE, '', time() - 3600, '/', '', is_ssl(), true );
		setcookie( self::CHAT_NAME_COOKIE, '', time() - 3600, '/', '', is_ssl(), true );
	}

	/**
	 * Check if session has a name set in cookie
	 *
	 * @return bool True if name is set.
	 */
	public static function has_name() {
		return ! empty( $_COOKIE[ self::CHAT_NAME_COOKIE ] );
	}
}
