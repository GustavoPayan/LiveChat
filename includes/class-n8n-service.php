<?php
/**
 * N8N Service for NexGen Telegram Chat
 * Handles integration with N8N automation workflows
 *
 * @package NexGenTelegramChat
 * @subpackage N8N
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NexGen_N8N_Service {

	/**
	 * Get N8N configuration
	 *
	 * @return array ['enabled' => bool, 'webhook_url' => string, 'timeout' => int]
	 */
	public static function get_config() {
		return [
			'enabled'      => get_option( 'nexgen_n8n_enabled' ),
			'webhook_url'  => get_option( 'nexgen_n8n_webhook_url' ),
			'api_key'      => get_option( 'nexgen_n8n_api_key' ),
			'timeout'      => (int) get_option( 'nexgen_n8n_timeout', 10 ),
			'keywords'     => self::get_keywords(),
		];
	}

	/**
	 * Get list of keywords that trigger N8N automation
	 * Keywords are stored as newline-separated text in admin form
	 *
	 * @return array List of keywords.
	 */
	public static function get_keywords() {
		$keywords_text = get_option( 'nexgen_ai_keywords', '' );
		
		if ( empty( $keywords_text ) ) {
			return [];
		}

		// Split by newline, trim whitespace, filter empty strings
		$keywords = array_filter( 
			array_map( 'trim', explode( "\n", $keywords_text ) ),
			function( $keyword ) {
				return ! empty( $keyword );
			}
		);

		return array_values( $keywords ); // Re-index array
	}

	/**
	 * Check if message should be processed by N8N
	 *
	 * @param string $message The message text.
	 * @return bool True if message should go to N8N.
	 */
	public static function should_process( $message ) {
		$config = self::get_config();

		// Log configuration for debugging
		NexGen_Security::log_event( 'n8n_check_process', [
			'enabled'    => $config['enabled'],
			'message'    => $message,
			'keywords'   => $config['keywords'],
			'keyword_count' => count( $config['keywords'] ),
		] );

		if ( ! $config['enabled'] ) {
			NexGen_Security::log_event( 'n8n_disabled', [ 'reason' => 'N8N not enabled' ] );
			return false;
		}

		$keywords = $config['keywords'];
		$message  = strtolower( $message );

		if ( empty( $keywords ) ) {
			NexGen_Security::log_event( 'n8n_no_keywords', [ 'message' => $message ] );
			return false;
		}

		foreach ( $keywords as $keyword ) {
			$keyword_lower = strtolower( trim( $keyword ) );
			if ( stripos( $message, $keyword_lower ) !== false ) {
				NexGen_Security::log_event( 'n8n_keyword_matched', [
					'message'   => $message,
					'keyword'   => $keyword_lower,
				] );
				return true;
			}
		}

		NexGen_Security::log_event( 'n8n_no_match', [
			'message'  => $message,
			'keywords' => $keywords,
		] );

		return false;
	}

	/**
	 * Send message to N8N webhook for processing
	 *
	 * @param string $message Message text.
	 * @param string $session_id Session ID.
	 * @return array ['success' => bool, 'response' => string|null, 'error' => string|null]
	 */
	public static function send_to_n8n( $message, $session_id ) {
		$config = self::get_config();

		if ( ! $config['enabled'] || empty( $config['webhook_url'] ) ) {
			return [
				'success'  => false,
				'response' => null,
				'error'    => 'N8N not configured',
			];
		}

		$payload = [
			'message'    => $message,
			'session_id' => $session_id,
			'visitor'    => NexGen_Session_Service::extract_name_from_session( $session_id ),
			'site'       => get_bloginfo( 'name' ),
			'timestamp'  => current_time( 'mysql' ),
		];

		// Log outgoing request
		NexGen_Security::log_event( 'n8n_request_start', [
			'url'        => $config['webhook_url'],
			'session_id' => $session_id,
			'message'    => $message,
			'visitor'    => $payload['visitor'],
		] );

		$args = [
			'body'      => wp_json_encode( $payload ),
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $config['api_key'],
			],
			'timeout'   => $config['timeout'],
			'sslverify' => true,
		];

		$response = wp_remote_post( $config['webhook_url'], $args );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			NexGen_Security::log_event( 'n8n_error', [
				'error'      => $error_msg,
				'session_id' => $session_id,
				'url'        => $config['webhook_url'],
			] );

			return [
				'success'  => false,
				'response' => null,
				'error'    => 'N8N connection error: ' . $error_msg,
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		// Log response code
		NexGen_Security::log_event( 'n8n_response', [
			'code'       => $response_code,
			'session_id' => $session_id,
			'body_length' => strlen( $response_body ),
		] );

		if ( $response_code < 200 || $response_code >= 300 ) {
			NexGen_Security::log_event( 'n8n_api_error', [
				'code'  => $response_code,
				'body'  => $response_body,
				'url'   => $config['webhook_url'],
			] );

			return [
				'success'  => false,
				'response' => null,
				'error'    => "N8N API error ({$response_code})",
			];
		}

		// Try to parse response as JSON
		$decoded = json_decode( $response_body, true );
		$response_text = isset( $decoded['response'] ) ? $decoded['response'] : $response_body;

		NexGen_Security::log_event( 'n8n_success', [
			'session_id'  => $session_id,
			'has_response' => ! empty( $response_text ),
			'response_preview' => substr( $response_text, 0, 100 ),
		] );

		return [
			'success'  => true,
			'response' => $response_text,
			'error'    => null,
		];
	}

	/**
	 * Test N8N webhook connection
	 *
	 * @return array ['success' => bool, 'error' => string|null]
	 */
	public static function test_connection() {
		$result = self::send_to_n8n( 'Test message', 'chat_test_000000' );

		return [
			'success' => $result['success'],
			'error'   => $result['error'],
		];
	}

	/**
	 * Classify message (basic keyword match, can be extended)
	 *
	 * @param string $message Message text.
	 * @return array ['type' => string, 'category' => string|null]
	 */
	public static function classify_message( $message ) {
		$lowercase = strtolower( $message );

		// Web development keywords
		$webdev_keywords = [ 'hosting', 'domain', 'ssl', 'server', 'website', 'wordpress', 'desarrollo web', 'hosting', 'dominio' ];
		foreach ( $webdev_keywords as $kw ) {
			if ( stripos( $lowercase, $kw ) !== false ) {
				return [
					'type'     => 'auto',
					'category' => 'web_development',
				];
			}
		}

		// Digital marketing keywords
		$marketing_keywords = [ 'seo', 'marketing', 'publicidad', 'analytics', 'estrategia', 'marketing digital', 'campana' ];
		foreach ( $marketing_keywords as $kw ) {
			if ( stripos( $lowercase, $kw ) !== false ) {
				return [
					'type'     => 'auto',
					'category' => 'digital_marketing',
				];
			}
		}

		// Default: human response
		return [
			'type'     => 'human',
			'category' => null,
		];
	}
}
