<?php
/**
 * LLM Service for NexGen Telegram Chat
 * Wrapper para integración con APIs de modelos de lenguaje (OpenAI, Claude, Gemini)
 *
 * @package NexGenTelegramChat
 * @subpackage LLM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NexGen_LLM_Service {

	/**
	 * Get LLM configuration
	 *
	 * @return array Configuration array with provider, API key, settings
	 */
	public static function get_config() {
		return [
			'enabled'    => get_option( 'nexgen_llm_enabled', true ),
			'provider'   => get_option( 'nexgen_llm_provider', 'openai' ),
			'api_key'    => self::get_api_key(),
			'model'      => self::get_model(),
			'temperature' => (float) get_option( 'nexgen_llm_temperature', 0.7 ),
			'max_tokens' => (int) get_option( 'nexgen_llm_max_tokens', 500 ),
			'timeout'    => (int) get_option( 'nexgen_llm_timeout', 10 ),
		];
	}

	/**
	 * Get API key from secure location
	 * Priority: wp-config constant > encrypted option
	 *
	 * @return string|null API key or null if not found
	 */
	private static function get_api_key() {
		// Check wp-config first
		if ( defined( 'NEXGEN_LLM_API_KEY' ) ) {
			return NEXGEN_LLM_API_KEY;
		}

		// Fallback to encrypted option
		$encrypted = get_option( '_nexgen_llm_api_key_encrypted' );
		if ( $encrypted ) {
			return $encrypted;
		}

		return null;
	}

	/**
	 * Get model name based on provider
	 *
	 * @return string Model identifier
	 */
	private static function get_model() {
		$config = self::get_config();

		switch ( $config['provider'] ) {
			case 'openai':
				return 'gpt-4o-mini';
			case 'claude':
				return 'claude-3-5-haiku-20241022';
			case 'gemini':
				return 'gemini-2.0-flash';
			default:
				return 'gpt-4o-mini';
		}
	}

	/**
	 * Query LLM with message
	 *
	 * @param string $message User message
	 * @param string $system_prompt System prompt for context
	 * @param array  $options Additional options
	 * @return array ['success' => bool, 'response' => string|null, 'tokens' => int, 'cost' => float, 'error' => string|null]
	 */
	public static function query( $message, $system_prompt = '', $options = [] ) {
		$config = self::get_config();

		if ( ! $config['enabled'] || empty( $config['api_key'] ) ) {
			return [
				'success'  => false,
				'response' => null,
				'tokens'   => 0,
				'cost'     => 0,
				'error'    => 'LLM not configured',
			];
		}

		// Validate input
		$validation = NexGen_Security::validate_message( $message );
		if ( ! $validation['valid'] ) {
			return [
				'success'  => false,
				'response' => null,
				'tokens'   => 0,
				'cost'     => 0,
				'error'    => $validation['error'],
			];
		}

		$message = $validation['message'];

		// Log request start
		NexGen_Security::log_event( 'llm_query_start', [
			'provider'  => $config['provider'],
			'message'   => substr( $message, 0, 100 ),
			'model'     => $config['model'],
		] );

		// Route to correct provider
		$response = match ( $config['provider'] ) {
			'openai' => self::query_openai( $message, $system_prompt, $config, $options ),
			'claude' => self::query_claude( $message, $system_prompt, $config, $options ),
			'gemini' => self::query_gemini( $message, $system_prompt, $config, $options ),
			default  => ['success' => false, 'error' => 'Unknown provider'],
		};

		// Log response
		if ( $response['success'] ) {
			NexGen_Security::log_event( 'llm_query_success', [
				'provider' => $config['provider'],
				'tokens'   => $response['tokens'] ?? 0,
				'cost'     => $response['cost'] ?? 0,
			] );
		} else {
			NexGen_Security::log_event( 'llm_query_error', [
				'provider' => $config['provider'],
				'error'    => $response['error'] ?? 'Unknown error',
			] );
		}

		return $response;
	}

	/**
	 * Query OpenAI API
	 *
	 * @param string $message User message
	 * @param string $system_prompt System prompt
	 * @param array  $config LLM config
	 * @param array  $options Additional options
	 * @return array Response array
	 */
	private static function query_openai( $message, $system_prompt, $config, $options ) {
		$endpoint = 'https://api.openai.com/v1/chat/completions';

		$payload = [
			'model'       => $config['model'],
			'messages'    => [
				[
					'role'    => 'system',
					'content' => $system_prompt ?: 'You are a helpful assistant.',
				],
				[
					'role'    => 'user',
					'content' => $message,
				],
			],
			'temperature' => $config['temperature'],
			'max_tokens'  => $config['max_tokens'],
			'top_p'       => 0.9,
		];

		$args = [
			'body'      => wp_json_encode( $payload ),
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $config['api_key'],
			],
			'timeout'   => $config['timeout'],
			'sslverify' => true,
		];

		$start_time = microtime( true );
		$response   = wp_remote_post( $endpoint, $args );
		$latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return [
				'success'  => false,
				'response' => null,
				'tokens'   => 0,
				'cost'     => 0,
				'error'    => $response->get_error_message(),
				'latency'  => $latency_ms,
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['choices'][0]['message']['content'] ) ) {
			return [
				'success'  => false,
				'response' => null,
				'tokens'   => 0,
				'cost'     => 0,
				'error'    => 'Empty response from OpenAI',
				'latency'  => $latency_ms,
			];
		}

		$input_tokens  = $body['usage']['prompt_tokens'] ?? 0;
		$output_tokens = $body['usage']['completion_tokens'] ?? 0;
		$cost          = self::calculate_cost_openai( $input_tokens, $output_tokens );

		return [
			'success'  => true,
			'response' => $body['choices'][0]['message']['content'],
			'tokens'   => $input_tokens + $output_tokens,
			'cost'     => $cost,
			'latency'  => $latency_ms,
		];
	}

	/**
	 * Query Claude API
	 *
	 * @param string $message User message
	 * @param string $system_prompt System prompt
	 * @param array  $config LLM config
	 * @param array  $options Additional options
	 * @return array Response array
	 */
	private static function query_claude( $message, $system_prompt, $config, $options ) {
		$endpoint = 'https://api.anthropic.com/v1/messages';

		$payload = [
			'model'       => $config['model'],
			'max_tokens'  => $config['max_tokens'],
			'system'      => $system_prompt ?: 'You are a helpful assistant.',
			'messages'    => [
				[
					'role'    => 'user',
					'content' => $message,
				],
			],
			'temperature' => $config['temperature'],
		];

		$args = [
			'body'      => wp_json_encode( $payload ),
			'headers'   => [
				'Content-Type'        => 'application/json',
				'x-api-key'          => $config['api_key'],
				'anthropic-version'  => '2023-06-01',
			],
			'timeout'   => $config['timeout'],
			'sslverify' => true,
		];

		$start_time = microtime( true );
		$response   = wp_remote_post( $endpoint, $args );
		$latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return [
				'success'  => false,
				'response' => null,
				'tokens'   => 0,
				'cost'     => 0,
				'error'    => $response->get_error_message(),
				'latency'  => $latency_ms,
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['content'][0]['text'] ) ) {
			return [
				'success'  => false,
				'response' => null,
				'tokens'   => 0,
				'cost'     => 0,
				'error'    => 'Empty response from Claude',
				'latency'  => $latency_ms,
			];
		}

		$input_tokens  = $body['usage']['input_tokens'] ?? 0;
		$output_tokens = $body['usage']['output_tokens'] ?? 0;
		$cost          = self::calculate_cost_claude( $input_tokens, $output_tokens );

		return [
			'success'  => true,
			'response' => $body['content'][0]['text'],
			'tokens'   => $input_tokens + $output_tokens,
			'cost'     => $cost,
			'latency'  => $latency_ms,
		];
	}

	/**
	 * Query Gemini API
	 *
	 * @param string $message User message
	 * @param string $system_prompt System prompt
	 * @param array  $config LLM config
	 * @param array  $options Additional options
	 * @return array Response array
	 */
	private static function query_gemini( $message, $system_prompt, $config, $options ) {
		$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $config['model'] . ':generateContent';

		$payload = [
			'contents' => [
				[
					'role' => 'user',
					'parts' => [
						[
							'text' => ( $system_prompt ? $system_prompt . "\n\n" : '' ) . $message,
						],
					],
				],
			],
			'generationConfig' => [
				'temperature'    => $config['temperature'],
				'maxOutputTokens' => $config['max_tokens'],
				'topP'           => 0.9,
			],
		];

		$args = [
			'body'      => wp_json_encode( $payload ),
			'headers'   => [
				'Content-Type' => 'application/json',
			],
			'timeout'   => $config['timeout'],
			'sslverify' => true,
		];

		$start_time = microtime( true );
		$response   = wp_remote_post( $endpoint . '?key=' . $config['api_key'], $args );
		$latency_ms = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return [
				'success'  => false,
				'response' => null,
				'tokens'   => 0,
				'cost'     => 0,
				'error'    => $response->get_error_message(),
				'latency'  => $latency_ms,
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return [
				'success'  => false,
				'response' => null,
				'tokens'   => 0,
				'cost'     => 0,
				'error'    => 'Empty response from Gemini',
				'latency'  => $latency_ms,
			];
		}

		$input_tokens  = $body['usageMetadata']['promptTokenCount'] ?? 0;
		$output_tokens = $body['usageMetadata']['candidatesTokenCount'] ?? 0;
		$cost          = 0; // Gemini free tier

		return [
			'success'  => true,
			'response' => $body['candidates'][0]['content']['parts'][0]['text'],
			'tokens'   => $input_tokens + $output_tokens,
			'cost'     => $cost,
			'latency'  => $latency_ms,
		];
	}

	/**
	 * Calculate cost for OpenAI
	 * GPT-4o-mini: $0.00015 input, $0.0006 output per 1K tokens
	 *
	 * @param int $input_tokens Input tokens
	 * @param int $output_tokens Output tokens
	 * @return float Cost in USD
	 */
	private static function calculate_cost_openai( $input_tokens, $output_tokens ) {
		$input_cost  = ( $input_tokens / 1000 ) * 0.00015;
		$output_cost = ( $output_tokens / 1000 ) * 0.0006;
		return round( $input_cost + $output_cost, 6 );
	}

	/**
	 * Calculate cost for Claude
	 * Claude 3.5 Haiku: $0.0008 input, $0.004 output per 1K tokens
	 *
	 * @param int $input_tokens Input tokens
	 * @param int $output_tokens Output tokens
	 * @return float Cost in USD
	 */
	private static function calculate_cost_claude( $input_tokens, $output_tokens ) {
		$input_cost  = ( $input_tokens / 1000 ) * 0.0008;
		$output_cost = ( $output_tokens / 1000 ) * 0.004;
		return round( $input_cost + $output_cost, 6 );
	}

	/**
	 * Get provider info for display
	 *
	 * @param string $provider Provider name
	 * @return array Provider info
	 */
	public static function get_provider_info( $provider = '' ) {
		if ( empty( $provider ) ) {
			$config   = self::get_config();
			$provider = $config['provider'];
		}

		return match ( $provider ) {
			'openai' => [
				'name'  => 'OpenAI',
				'model' => 'GPT-4o Mini',
				'price' => '$0.0015 per 1K input tokens',
			],
			'claude' => [
				'name'  => 'Anthropic Claude',
				'model' => 'Claude 3.5 Haiku',
				'price' => '$0.0008 per 1K input tokens',
			],
			'gemini' => [
				'name'  => 'Google Gemini',
				'model' => 'Gemini 2.0 Flash',
				'price' => 'Free tier available',
			],
			default  => [],
		};
	}
}
