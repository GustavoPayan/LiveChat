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

	/**
	 * Initialize session
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! session_id() ) {
			@session_start();
		}
	}

	/**
	 * Get or create session ID in proper format
	 *
	 * @return string Session ID in format chat_<slug>_<6-hex>
	 */
	public static function get_session_id() {
		self::init();

		if ( ! isset( $_SESSION[ self::SESSION_COOKIE ] ) ) {
			// Generate new session with standard format
			$_SESSION[ self::SESSION_COOKIE ] = self::generate_session_id();
		}

		return $_SESSION[ self::SESSION_COOKIE ];
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

		self::init();

		// Generate new session with name
		$new_session = self::generate_session_id( $name );

		// Store in session and sessionStorage-accessible variables
		$_SESSION[ self::SESSION_COOKIE ] = $new_session;
		$_SESSION[ self::CHAT_NAME_COOKIE ] = $name;

		return $new_session;
	}

	/**
	 * Get stored visitor name from session
	 *
	 * @return string Visitor name or empty string.
	 */
	public static function get_chat_name() {
		self::init();

		return isset( $_SESSION[ self::CHAT_NAME_COOKIE ] ) ? $_SESSION[ self::CHAT_NAME_COOKIE ] : '';
	}

	/**
	 * Get all session data
	 *
	 * @return array Session data.
	 */
	public static function get_session_data() {
		self::init();

		return [
			'session_id' => isset( $_SESSION[ self::SESSION_COOKIE ] ) ? $_SESSION[ self::SESSION_COOKIE ] : '',
			'name'       => isset( $_SESSION[ self::CHAT_NAME_COOKIE ] ) ? $_SESSION[ self::CHAT_NAME_COOKIE ] : '',
			'created_at' => isset( $_SESSION['nexgen_created_at'] ) ? $_SESSION['nexgen_created_at'] : current_time( 'mysql' ),
		];
	}

	/**
	 * Destroy session
	 *
	 * @return void
	 */
	public static function destroy() {
		self::init();

		unset( $_SESSION[ self::SESSION_COOKIE ] );
		unset( $_SESSION[ self::CHAT_NAME_COOKIE ] );
	}

	/**
	 * Check if session has a name set
	 *
	 * @return bool True if name is set.
	 */
	public static function has_name() {
		self::init();

		return ! empty( $_SESSION[ self::CHAT_NAME_COOKIE ] );
	}
}
