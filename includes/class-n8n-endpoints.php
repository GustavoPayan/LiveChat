<?php
/**
 * N8N Integration Endpoints for NexGen Telegram Chat
 * Add this to class-plugin.php AJAX handlers or include separately
 * 
 * Endpoints:
 * - POST /wp-json/nexgen/v1/n8n-message - Recibir mensajes desde n8n
 * - GET  /wp-json/nexgen/v1/context - Obtener contexto (servicios, precios, FAQ)
 * - POST /wp-json/nexgen/v1/save-message - Guardar respuesta de n8n
 * - POST /wp-json/nexgen/v1/webhook-response - Enviar respuesta al chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register N8N REST endpoints
 */
add_action( 'rest_api_init', function() {
	// Endpoint para recibir mensajes y enviar a n8n
	register_rest_route( 'nexgen/v1', '/n8n-message', [
		'methods'             => 'POST',
		'callback'            => 'nexgen_handle_n8n_message',
		'permission_callback' => function( $request ) {
			return nexgen_validate_api_request( $request );
		},
	] );

	// Endpoint para obtener contexto (servicios, precios, FAQ, etc)
	register_rest_route( 'nexgen/v1', '/context', [
		'methods'             => 'GET',
		'callback'            => 'nexgen_get_context',
		'permission_callback' => function( $request ) {
			return nexgen_validate_api_request( $request );
		},
	] );

	// Endpoint para guardar mensajes de bot
	register_rest_route( 'nexgen/v1', '/save-message', [
		'methods'             => 'POST',
		'callback'            => 'nexgen_save_bot_message',
		'permission_callback' => function( $request ) {
			return nexgen_validate_api_request( $request );
		},
	] );

	// Endpoint para enviar respuesta al chat
	register_rest_route( 'nexgen/v1', '/webhook-response', [
		'methods'             => 'POST',
		'callback'            => 'nexgen_send_webhook_response',
		'permission_callback' => function( $request ) {
			return nexgen_validate_api_request( $request );
		},
	] );
});

/**
 * Validate API request from N8N
 * Checks Bearer token matches WORDPRESS_API_KEY
 * 
 * @param WP_REST_Request $request Request object.
 * @return bool Whether request is valid.
 */
function nexgen_validate_api_request( $request ) {
	// Get Authorization header
	$auth_header = $request->get_header( 'Authorization' );
	
	if ( empty( $auth_header ) ) {
		return false;
	}

	// Extract Bearer token
	if ( strpos( $auth_header, 'Bearer ' ) !== 0 ) {
		return false;
	}

	$token = substr( $auth_header, 7 );
	$expected_token = getenv( 'WORDPRESS_API_KEY' );

	// Fallback to option if env var not set
	if ( empty( $expected_token ) ) {
		$expected_token = get_option( 'nexgen_api_key' );
	}

	return ! empty( $token ) && ! empty( $expected_token ) && hash_equals( $token, $expected_token );
}

/**
 * Handle message from N8N webhook
 * Sends message to Telegram and stores in database
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function nexgen_handle_n8n_message( $request ) {
	try {
		$params = $request->get_json_params();

		if ( empty( $params['message'] ) || empty( $params['session_id'] ) ) {
			return new WP_REST_Response(
				[ 'error' => 'Missing required fields: message, session_id' ],
				400
			);
		}

		$message = sanitize_text_field( $params['message'] );
		$session_id = sanitize_text_field( $params['session_id'] );
		$visitor = ! empty( $params['visitor'] ) ? sanitize_text_field( $params['visitor'] ) : 'Visitante';
		$timestamp = ! empty( $params['timestamp'] ) ? sanitize_text_field( $params['timestamp'] ) : current_time( 'mysql' );

		// Validate session ID
		if ( ! NexGen_Security::validate_session_id( $session_id ) ) {
			return new WP_REST_Response(
				[ 'error' => 'Invalid session_id format' ],
				400
			);
		}

		// Validate message
		$validation = NexGen_Security::validate_message( $message );
		if ( ! $validation['valid'] ) {
			return new WP_REST_Response(
				[ 'error' => $validation['error'] ],
				400
			);
		}

		// Save user message
		NexGen_Message_Service::save_message( $session_id, $message, 'user' );

		NexGen_Security::log_event( 'n8n_message_received', [
			'session_id' => $session_id,
			'visitor'    => $visitor,
			'length'     => strlen( $message ),
		] );

		return new WP_REST_Response(
			[
				'success'    => true,
				'message_id' => $session_id,
				'timestamp'  => current_time( 'mysql' ),
			],
			202
		);
	} catch ( Exception $e ) {
		NexGen_Security::log_event( 'n8n_message_error', [ 'error' => $e->getMessage() ] );

		return new WP_REST_Response(
			[ 'error' => $e->getMessage() ],
			500
		);
	}
}

/**
 * Get context for N8N (company info, services, pricing, FAQ)
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object with context.
 */
function nexgen_get_context( $request ) {
	try {
		// Get company info
		$company = [
			'name'  => get_bloginfo( 'name' ),
			'url'   => get_bloginfo( 'url' ),
			'email' => get_option( 'admin_email' ),
			'phone' => get_option( 'nexgen_company_phone', '' ),
		];

		// Get services from options
		$services = [];
		$service_ids = get_option( 'nexgen_services', [] );
		if ( is_array( $service_ids ) ) {
			foreach ( $service_ids as $service ) {
				$services[] = [
					'name'        => $service['name'] ?? 'Servicio',
					'description' => $service['description'] ?? '',
					'price'       => $service['price'] ?? 0,
				];
			}
		}

		// Get pricing tiers
		$pricing = [];
		$pricing_data = get_option( 'nexgen_pricing_tiers', [] );
		if ( is_array( $pricing_data ) ) {
			foreach ( $pricing_data as $tier ) {
				$pricing[] = [
					'name'   => $tier['name'] ?? 'Plan',
					'price'  => $tier['price'] ?? 0,
					'period' => $tier['period'] ?? 'month',
					'features' => $tier['features'] ?? [],
				];
			}
		}

		// Get FAQ
		$faq = [];
		$faq_data = get_option( 'nexgen_faq', [] );
		if ( is_array( $faq_data ) ) {
			foreach ( array_slice( $faq_data, 0, 10 ) as $item ) {
				$faq[] = [
					'question' => $item['q'] ?? '',
					'answer'   => $item['a'] ?? '',
				];
			}
		}

		$context = [
			'company'  => $company,
			'services' => $services,
			'pricing'  => $pricing,
			'faq'      => $faq,
		];

		NexGen_Security::log_event( 'context_retrieved', [ 'items' => count( $services ) + count( $pricing ) + count( $faq ) ] );

		return new WP_REST_Response( $context, 200 );
	} catch ( Exception $e ) {
		NexGen_Security::log_event( 'context_error', [ 'error' => $e->getMessage() ] );

		return new WP_REST_Response(
			[ 'error' => $e->getMessage() ],
			500
		);
	}
}

/**
 * Save bot message from N8N
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function nexgen_save_bot_message( $request ) {
	try {
		$params = $request->get_json_params();

		if ( empty( $params['session_id'] ) || empty( $params['message_text'] ) ) {
			return new WP_REST_Response(
				[ 'error' => 'Missing required fields: session_id, message_text' ],
				400
			);
		}

		$session_id = sanitize_text_field( $params['session_id'] );
		$message = sanitize_text_field( $params['message_text'] );
		$sender_info = ! empty( $params['sender_info'] ) ? $params['sender_info'] : [];

		// Validate session ID
		if ( ! NexGen_Security::validate_session_id( $session_id ) ) {
			return new WP_REST_Response(
				[ 'error' => 'Invalid session_id' ],
				400
			);
		}

		// Save message
		$message_id = NexGen_Message_Service::save_message(
			$session_id,
			$message,
			'bot',
			$sender_info
		);

		if ( ! $message_id ) {
			return new WP_REST_Response(
				[ 'error' => 'Failed to save message' ],
				500
			);
		}

		NexGen_Security::log_event( 'bot_message_saved', [
			'session_id'  => $session_id,
			'message_id'  => $message_id,
			'model'       => $sender_info['ai_model'] ?? 'unknown',
			'lead_score'  => $sender_info['lead_score'] ?? 0,
		] );

		return new WP_REST_Response(
			[
				'success'    => true,
				'message_id' => $message_id,
			],
			200
		);
	} catch ( Exception $e ) {
		NexGen_Security::log_event( 'save_message_error', [ 'error' => $e->getMessage() ] );

		return new WP_REST_Response(
			[ 'error' => $e->getMessage() ],
			500
		);
	}
}

/**
 * Send webhook response back to chat widget
 * 
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function nexgen_send_webhook_response( $request ) {
	try {
		$params = $request->get_json_params();

		if ( empty( $params['session_id'] ) || empty( $params['response'] ) ) {
			return new WP_REST_Response(
				[ 'error' => 'Missing required fields: session_id, response' ],
				400
			);
		}

		$session_id = sanitize_text_field( $params['session_id'] );
		$response = sanitize_text_field( $params['response'] );
		$lead_score = ! empty( $params['lead_score'] ) ? intval( $params['lead_score'] ) : 0;
		$lead_email = ! empty( $params['extracted_email'] ) ? sanitize_email( $params['extracted_email'] ) : null;
		$lead_phone = ! empty( $params['extracted_phone'] ) ? sanitize_text_field( $params['extracted_phone'] ) : null;

		// Validate
		if ( ! NexGen_Security::validate_session_id( $session_id ) ) {
			return new WP_REST_Response(
				[ 'error' => 'Invalid session_id' ],
				400
			);
		}

		// Store lead info if extracted
		if ( $lead_email || $lead_phone ) {
			$lead_data = [
				'session_id' => $session_id,
				'email'      => $lead_email,
				'phone'      => $lead_phone,
				'score'      => $lead_score,
				'timestamp'  => current_time( 'mysql' ),
			];

			// Store in options or custom table
			$leads = get_option( 'nexgen_leads', [] );
			$leads[ $session_id ] = $lead_data;
			update_option( 'nexgen_leads', $leads );

			NexGen_Security::log_event( 'lead_captured', [
				'session_id' => $session_id,
				'email'      => $lead_email,
				'score'      => $lead_score,
			] );
		}

		return new WP_REST_Response(
			[
				'success'      => true,
				'session_id'   => $session_id,
				'lead_score'   => $lead_score,
				'timestamp'    => current_time( 'mysql' ),
			],
			200
		);
	} catch ( Exception $e ) {
		NexGen_Security::log_event( 'webhook_response_error', [ 'error' => $e->getMessage() ] );

		return new WP_REST_Response(
			[ 'error' => $e->getMessage() ],
			500
		);
	}
}
