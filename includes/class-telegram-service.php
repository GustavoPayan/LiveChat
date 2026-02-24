<?php
/**
 * Telegram Service for NexGen Telegram Chat
 * Handles Telegram Bot API integration and webhook processing
 *
 * @package NexGenTelegramChat
 * @subpackage Telegram
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NexGen_Telegram_Service {

	const API_BASE_URL = 'https://api.telegram.org/bot';
	const SESSION_TOKEN = '🔗 Sesión: ';

	/**
	 * Get Telegram bot configuration
	 *
	 * @return array ['token' => string, 'chat_id' => string, 'active' => bool]
	 */
	public static function get_config() {
		$token   = get_option( 'nexgen_bot_token' );
		$chat_id = get_option( 'nexgen_chat_id' );

		return [
			'token'  => $token,
			'chat_id' => $chat_id,
			'active' => ! empty( $token ) && ! empty( $chat_id ),
		];
	}

	/**
	 * Send message to Telegram
	 *
	 * @param string $message Message text.
	 * @param string $session_id Session ID.
	 * @return array ['success' => bool, 'message_id' => int|null, 'error' => string|null]
	 */
	public static function send_message( $message, $session_id ) {
		$config = self::get_config();

		if ( ! $config['active'] ) {
			return [
				'success'    => false,
				'message_id' => null,
				'error'      => 'Bot no configurado',
			];
		}

		// Get visitor name
		$name = NexGen_Session_Service::extract_name_from_session( $session_id );

		// Format message with session info
		$site_name       = get_bloginfo( 'name' );
		$current_page    = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : 'Página desconocida';
		$formatted_msg   = "💬 Nuevo mensaje desde {$site_name}\n\n";
		$formatted_msg  .= "👤 Nombre: {$name}\n";
		$formatted_msg  .= self::SESSION_TOKEN . "{$session_id}\n";
		$formatted_msg  .= "🌐 Página: {$current_page}\n\n";
		$formatted_msg  .= "📝 Mensaje: {$message}\n";
		$formatted_msg  .= "⏰ Hora: " . current_time( 'mysql' ) . "\n\n";
		$formatted_msg  .= "💡 Para responder, responde a este mensaje";

		return self::call_api( 'sendMessage', [
			'chat_id' => $config['chat_id'],
			'text'    => $formatted_msg,
		] );
	}

	/**
	 * Send test message to verify bot connection
	 *
	 * @return array ['success' => bool, 'error' => string|null]
	 */
	public static function send_test_message() {
		$config = self::get_config();

		if ( ! $config['active'] ) {
			return [
				'success' => false,
				'error'   => 'Bot no configurado',
			];
		}

		$test_msg = '🧪 Mensaje de prueba desde ' . get_bloginfo( 'name' ) . ' - ' . date( 'Y-m-d H:i:s' );

		$response = self::call_api( 'sendMessage', [
			'chat_id' => $config['chat_id'],
			'text'    => $test_msg,
		] );

		return [
			'success' => $response['success'],
			'error'   => $response['error'],
		];
	}

	/**
	 * Configure webhook for Telegram updates
	 *
	 * @return array ['success' => bool, 'error' => string|null]
	 */
	public static function set_webhook() {
		$config = self::get_config();

		if ( ! $config['active'] ) {
			return [
				'success' => false,
				'error'   => 'Bot no configurado',
			];
		}

		$webhook_url = admin_url( 'admin-ajax.php?action=nexgen_telegram_webhook' );

		$response = self::call_api( 'setWebhook', [
			'url'                  => $webhook_url,
			'drop_pending_updates' => true,
		] );

		NexGen_Security::log_event( 'webhook_config', [
			'success'  => $response['success'],
			'url'      => $webhook_url,
			'error'    => $response['error'],
		] );

		return $response;
	}

	/**
	 * Handle incoming webhook from Telegram
	 *
	 * @return array ['success' => bool, 'processed' => bool, 'error' => string|null]
	 */
	public static function handle_webhook() {
		$input  = file_get_contents( 'php://input' );
		$update = json_decode( $input, true );

		NexGen_Security::log_event( 'webhook_received', [ 'has_message' => isset( $update['message'] ) ] );

		if ( ! $update || ! isset( $update['message'] ) ) {
			return [
				'success'   => false,
				'processed' => false,
				'error'     => 'Invalid payload',
			];
		}

		$message = $update['message'];
		$text    = isset( $message['text'] ) ? $message['text'] : '';

		try {
			// Check for /reply command
			if ( strpos( $text, '/reply ' ) === 0 ) {
				self::process_reply_command( $text );
				return [
					'success'   => true,
					'processed' => true,
					'error'     => null,
				];
			}

			// Check for reply to existing message
			if ( isset( $message['reply_to_message'] ) ) {
				self::process_reply_to_message( $message );
				return [
					'success'   => true,
					'processed' => true,
					'error'     => null,
				];
			}

			return [
				'success'   => true,
				'processed' => false,
				'error'     => 'No reply detected',
			];
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'webhook_error', [
				'error'   => $e->getMessage(),
				'message' => $text,
			] );

			return [
				'success'   => false,
				'processed' => false,
				'error'     => $e->getMessage(),
			];
		}
	}

	/**
	 * Process /reply command format
	 *
	 * @param string $text Command text like "/reply session_id message".
	 * @return void
	 * @throws Exception On error.
	 */
	private static function process_reply_command( $text ) {
		$parts = explode( ' ', $text, 3 );

		if ( count( $parts ) < 3 ) {
			throw new Exception( 'Invalid /reply format' );
		}

		$session_id   = sanitize_text_field( $parts[1] );
		$reply_text   = sanitize_text_field( $parts[2] );

		if ( ! NexGen_Security::validate_session_id( $session_id ) ) {
			throw new Exception( 'Invalid session ID format' );
		}

		NexGen_Message_Service::save_message( $session_id, $reply_text, 'bot' );

		NexGen_Security::log_event( 'reply_command_processed', [
			'session_id' => $session_id,
		] );
	}

	/**
	 * Process reply to a message (Telegram reply feature)
	 *
	 * @param array $message Telegram message array.
	 * @return void
	 * @throws Exception On error.
	 */
	private static function process_reply_to_message( $message ) {
		$reply_text     = isset( $message['text'] ) ? sanitize_text_field( $message['text'] ) : '';
		$original_msg   = isset( $message['reply_to_message']['text'] ) ? $message['reply_to_message']['text'] : '';

		if ( empty( $reply_text ) || empty( $original_msg ) ) {
			throw new Exception( 'Missing reply text or original message' );
		}

		// Extract session_id from original message
		// Format: 🔗 Sesión: chat_xxx_yyyyyy
		if ( ! preg_match( '/🔗 Sesión: (chat_[A-Za-z0-9\-]+_[A-Fa-f0-9]{6})/', $original_msg, $matches ) ) {
			throw new Exception( 'Session ID not found in original message' );
		}

		$session_id = $matches[1];

		if ( ! NexGen_Security::validate_session_id( $session_id ) ) {
			throw new Exception( 'Invalid session ID in message' );
		}

		NexGen_Message_Service::save_message( $session_id, $reply_text, 'bot' );

		NexGen_Security::log_event( 'reply_processed', [
			'session_id' => $session_id,
		] );
	}

	/**
	 * Call Telegram Bot API
	 *
	 * @param string $method API method name.
	 * @param array  $params Parameters to send.
	 * @return array ['success' => bool, 'message_id' => int|null, 'error' => string|null]
	 */
	private static function call_api( $method, $params = [] ) {
		$config = self::get_config();

		if ( ! $config['active'] ) {
			return [
				'success'    => false,
				'message_id' => null,
				'error'      => 'Bot not configured',
			];
		}

		$url  = self::API_BASE_URL . $config['token'] . '/' . $method;
		$args = [
			'body'      => $params,
			'timeout'   => 30,
			'sslverify' => true,
		];

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'success'    => false,
				'message_id' => null,
				'error'      => 'Connection error: ' . $response->get_error_message(),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$decoded       = json_decode( $response_body, true );

		if ( $response_code !== 200 || ! isset( $decoded['ok'] ) || ! $decoded['ok'] ) {
			$error_msg = isset( $decoded['description'] ) ? $decoded['description'] : 'Unknown error';

			return [
				'success'    => false,
				'message_id' => null,
				'error'      => "Telegram API ({$response_code}): {$error_msg}",
			];
		}

		$message_id = isset( $decoded['result']['message_id'] ) ? $decoded['result']['message_id'] : null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'error'      => null,
		];
	}
}
