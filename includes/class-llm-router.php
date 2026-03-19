<?php
/**
 * LLM Router Service for NexGen Telegram Chat
 * Intelligent message routing: LLM (80%) vs N8N leads (15%) vs Blocked (5%)
 *
 * @package NexGenTelegramChat
 * @subpackage Routing
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NexGen_LLM_Router {

	/**
	 * Process user message and route to appropriate handler
	 *
	 * @param string $message User message
	 * @param string $session_id Chat session ID
	 * @param string $visitor_name Visitor name
	 * @param string $visitor_email Optional visitor email
	 * @return array ['success' => bool, 'response' => string, 'type' => 'llm|lead|blocked|telegram', 'metadata' => array]
	 */
	public static function process_message( $message, $session_id, $visitor_name = '', $visitor_email = '' ) {
		try {
			// Step 1: Validate input
			if ( empty( trim( $message ) ) ) {
				return [
					'success'  => false,
					'response' => 'Message cannot be empty',
					'type'     => 'error',
					'metadata' => [],
				];
			}

			// Step 2: Check if LLM routing is enabled
			$llm_enabled = get_option( 'nexgen_llm_enabled', true );
			if ( ! $llm_enabled ) {
				// Fallback to Telegram if LLM disabled
				return self::process_telegram( $message, $session_id, $visitor_name );
			}

			// Step 3: Validate message scope
			$scope_check = NexGen_Business_Prompt_Service::validate_scope( $message );
			if ( ! $scope_check['in_scope'] ) {
				// Out of scope: rejected or escalate to Telegram
				$block_probability = 1 - $scope_check['confidence'];
				if ( $block_probability > 0.7 ) {
					return self::process_blocked( $message, $session_id, $visitor_name );
				}
				// Low confidence: let LLM decide
			}

			// Step 4: Detect lead intent
			$is_lead = NexGen_Business_Prompt_Service::detect_lead_intent( $message );

			// Step 5: Route based on intent
			if ( $is_lead ) {
				// 15% flow: Lead generation
				return self::process_lead( $message, $session_id, $visitor_name, $visitor_email );
			} else {
				// 80% flow: Direct LLM
				return self::process_llm( $message, $session_id, $visitor_name );
			}
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'router_error', [ 'error' => $e->getMessage() ] );
			return [
				'success'  => false,
				'response' => 'An error occurred. Please try again.',
				'type'     => 'error',
				'metadata' => [ 'error' => $e->getMessage() ],
			];
		}
	}

	/**
	 * Process message via LLM (80% of traffic)
	 *
	 * @param string $message User message
	 * @param string $session_id Session ID
	 * @param string $visitor_name Visitor name
	 * @return array Response with metadata
	 */
	private static function process_llm( $message, $session_id, $visitor_name = '' ) {
		$start_time = microtime( true );

		try {
			// Build prompt with business context
			$system_prompt = NexGen_Business_Prompt_Service::build_system_prompt();

			// Query LLM
			$llm_response = NexGen_LLM_Service::query( $message, $system_prompt );

			$latency = round( ( microtime( true ) - $start_time ) * 1000, 2 );

			if ( ! $llm_response['success'] ) {
				// LLM failed: escalate to Telegram
				NexGen_Security::log_event( 'llm_failed', [ 'error' => $llm_response['error'] ] );
				return self::process_telegram( $message, $session_id, $visitor_name );
			}

			$response_text = $llm_response['response'];

			// Save user message to conversation
			NexGen_Message_Service::save_message( $session_id, $message, 'user' );

			// Save LLM response to conversation
			NexGen_Message_Service::save_message( $session_id, $response_text, 'bot' );

			// Record metrics
			self::save_metric( $session_id, 'llm', [
				'latency_ms'     => $latency,
				'tokens_input'   => $llm_response['tokens_input'] ?? 0,
				'tokens_output'  => $llm_response['tokens_output'] ?? 0,
				'cost'           => $llm_response['cost'] ?? 0,
				'provider'       => $llm_response['provider'] ?? 'unknown',
				'input_length'   => strlen( $message ),
				'output_length'  => strlen( $response_text ),
			] );

			// Log successful LLM response
			NexGen_Security::log_event( 'message_processed_llm', [
				'session_id' => $session_id,
				'provider'   => $llm_response['provider'] ?? 'unknown',
				'latency_ms' => $latency,
			] );

			return [
				'success'  => true,
				'response' => $response_text,
				'type'     => 'llm',
				'metadata' => [
					'latency_ms'    => $latency,
					'cost'          => $llm_response['cost'] ?? 0,
					'provider'      => $llm_response['provider'] ?? 'unknown',
					'tokens_input'  => $llm_response['tokens_input'] ?? 0,
					'tokens_output' => $llm_response['tokens_output'] ?? 0,
				],
			];
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'llm_process_error', [ 'error' => $e->getMessage() ] );
			// Fallback to Telegram
			return self::process_telegram( $message, $session_id, $visitor_name );
		}
	}

	/**
	 * Process lead (15% of traffic)
	 *
	 * @param string $message User message
	 * @param string $session_id Session ID
	 * @param string $visitor_name Visitor name
	 * @param string $visitor_email Optional email
	 * @return array Response
	 */
	private static function process_lead( $message, $session_id, $visitor_name = '', $visitor_email = '' ) {
		$start_time = microtime( true );

		try {
			// Extract lead data
			$lead_data = NexGen_Business_Prompt_Service::extract_lead_data(
				$message,
				$visitor_name,
				$session_id
			);

			// Save lead to database
			$lead_id = self::save_lead( $session_id, $lead_data );

			// Send to N8N if webhook configured
			$n8n_result = [ 'success' => true ];
			$n8n_configured = get_option( 'nexgen_n8n_webhook_url', '' );

			if ( ! empty( $n8n_configured ) ) {
				$n8n_result = NexGen_N8N_Service::send_lead( $lead_data );
			}

			$latency = round( ( microtime( true ) - $start_time ) * 1000, 2 );

			// Save messages
			NexGen_Message_Service::save_message( $session_id, $message, 'user' );

			$response_text = "Thank you for your interest! We'll get back to you shortly. " .
			                "A member of our team will contact you soon.";

			NexGen_Message_Service::save_message( $session_id, $response_text, 'bot' );

			// Record metrics
			self::save_metric( $session_id, 'lead', [
				'latency_ms'  => $latency,
				'lead_id'     => $lead_id,
				'n8n_sent'    => $n8n_result['success'] ? 1 : 0,
				'lead_data'   => json_encode( $lead_data ),
			] );

			NexGen_Security::log_event( 'lead_processed', [
				'session_id' => $session_id,
				'lead_id'    => $lead_id,
				'n8n_sent'   => $n8n_result['success'],
			] );

			return [
				'success'  => true,
				'response' => $response_text,
				'type'     => 'lead',
				'metadata' => [
					'latency_ms' => $latency,
					'lead_id'    => $lead_id,
					'n8n_sent'   => $n8n_result['success'],
				],
			];
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'lead_process_error', [ 'error' => $e->getMessage() ] );
			return self::process_telegram( $message, $session_id, $visitor_name );
		}
	}

	/**
	 * Process blocked/rejected message (5% of traffic)
	 *
	 * @param string $message User message
	 * @param string $session_id Session ID
	 * @param string $visitor_name Visitor name
	 * @return array Response
	 */
	private static function process_blocked( $message, $session_id, $visitor_name = '' ) {
		try {
			// Save user message
			NexGen_Message_Service::save_message( $session_id, $message, 'user' );

			// Get rejection message
			$response_text = NexGen_Business_Prompt_Service::get_rejection_message();

			// Save rejection response
			NexGen_Message_Service::save_message( $session_id, $response_text, 'bot' );

			// Record metric
			self::save_metric( $session_id, 'blocked', [
				'reason' => 'out_of_scope',
			] );

			NexGen_Security::log_event( 'message_blocked', [
				'session_id' => $session_id,
				'message'    => substr( $message, 0, 100 ),
			] );

			return [
				'success'  => true,
				'response' => $response_text,
				'type'     => 'blocked',
				'metadata' => [ 'reason' => 'out_of_scope' ],
			];
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'blocked_process_error', [ 'error' => $e->getMessage() ] );
			return [
				'success'  => false,
				'response' => 'Unable to process your message.',
				'type'     => 'error',
			];
		}
	}

	/**
	 * Fallback: Escalate to Telegram human support
	 *
	 * @param string $message User message
	 * @param string $session_id Session ID
	 * @param string $visitor_name Visitor name
	 * @return array Response
	 */
	private static function process_telegram( $message, $session_id, $visitor_name = '' ) {
		try {
			// Save message
			NexGen_Message_Service::save_message( $session_id, $message, 'user' );

			// Send to Telegram
			$telegram_result = NexGen_Telegram_Service::send_message(
				$message,
				$session_id,
				$visitor_name
			);

			if ( ! $telegram_result['success'] ) {
				return [
					'success'  => false,
					'response' => 'Unable to reach support. Please try again.',
					'type'     => 'error',
				];
			}

			// Record metric
			self::save_metric( $session_id, 'telegram', [
				'escalated' => 1,
				'visitor_name' => $visitor_name,
			] );

			NexGen_Security::log_event( 'message_escalated_telegram', [
				'session_id'   => $session_id,
				'visitor_name' => $visitor_name,
			] );

			$response_text = "Your message has been sent to our support team. " .
			                "A representative will assist you shortly.";

			return [
				'success'  => true,
				'response' => $response_text,
				'type'     => 'telegram',
				'metadata' => [ 'escalated' => true ],
			];
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'telegram_process_error', [ 'error' => $e->getMessage() ] );
			return [
				'success'  => false,
				'response' => 'An error occurred. Please contact support directly.',
				'type'     => 'error',
			];
		}
	}

	/**
	 * Save lead to database
	 *
	 * @param string $session_id Session ID
	 * @param array  $lead_data Lead data
	 * @return int Lead ID
	 */
	private static function save_lead( $session_id, $lead_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nexgen_leads';

		// Ensure table exists
		self::create_leads_table();

		$data = [
			'session_id' => $session_id,
			'name'       => $lead_data['name'] ?? '',
			'email'      => $lead_data['email'] ?? '',
			'phone'      => $lead_data['phone'] ?? '',
			'interest'   => $lead_data['interest'] ?? '',
			'created_at' => current_time( 'mysql' ),
		];

		$formats = [ '%s', '%s', '%s', '%s', '%s', '%s' ];

		$wpdb->insert( $table, $data, $formats );

		return $wpdb->insert_id;
	}

	/**
	 * Save routing metric/analytic
	 *
	 * @param string $session_id Session ID
	 * @param string $route_type Route type (llm, lead, blocked, telegram)
	 * @param array  $metadata Additional data
	 * @return int Metric ID
	 */
	private static function save_metric( $session_id, $route_type, $metadata = [] ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nexgen_metrics';

		// Ensure table exists
		self::create_metrics_table();

		$data = [
			'session_id'  => $session_id,
			'route_type'  => $route_type,
			'metadata'    => wp_json_encode( $metadata ),
			'created_at'  => current_time( 'mysql' ),
		];

		$formats = [ '%s', '%s', '%s', '%s' ];

		$wpdb->insert( $table, $data, $formats );

		return $wpdb->insert_id;
	}

	/**
	 * Create leads table if not exists
	 */
	private static function create_leads_table() {
		global $wpdb;

		$table      = $wpdb->prefix . 'nexgen_leads';
		$charset    = $wpdb->get_charset_collate();
		$table_name = $table;

		// Check if table exists
		$existing = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( ! empty( $existing ) ) {
			return;
		}

		$sql = "CREATE TABLE $table_name (
			id INT AUTO_INCREMENT PRIMARY KEY,
			session_id VARCHAR(255) NOT NULL,
			name VARCHAR(255),
			email VARCHAR(255),
			phone VARCHAR(20),
			interest LONGTEXT,
			n8n_sent TINYINT(1) DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			KEY session_id (session_id),
			KEY email (email)
		) $charset;";

		// Silence output temporarily
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create metrics table if not exists
	 */
	private static function create_metrics_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'nexgen_metrics';
		$charset = $wpdb->get_charset_collate();

		// Check if table exists
		$existing = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( ! empty( $existing ) ) {
			return;
		}

		$sql = "CREATE TABLE $table (
			id INT AUTO_INCREMENT PRIMARY KEY,
			session_id VARCHAR(255) NOT NULL,
			route_type VARCHAR(50),
			metadata LONGTEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			KEY session_id (session_id),
			KEY route_type (route_type),
			KEY created_at (created_at)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get routing statistics for dashboard
	 *
	 * @param string $date_from Optional date from (YYYY-MM-DD)
	 * @param string $date_to Optional date to (YYYY-MM-DD)
	 * @return array Statistics
	 */
	public static function get_statistics( $date_from = '', $date_to = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nexgen_metrics';

		$where = '1=1';
		if ( ! empty( $date_from ) ) {
			$where .= $wpdb->prepare( ' AND DATE(created_at) >= %s', $date_from );
		}
		if ( ! empty( $date_to ) ) {
			$where .= $wpdb->prepare( ' AND DATE(created_at) <= %s', $date_to );
		}

		$stats = $wpdb->get_results(
			"SELECT 
				route_type,
				COUNT(*) as count,
				ROUND(AVG(CAST(JSON_EXTRACT(metadata, '$.latency_ms') AS DECIMAL)), 2) as avg_latency,
				ROUND(SUM(CAST(JSON_EXTRACT(metadata, '$.cost') AS DECIMAL)), 4) as total_cost
			FROM $table
			WHERE $where
			GROUP BY route_type"
		);

		return $stats ?: [];
	}

	/**
	 * Get recent leads
	 *
	 * @param int $limit Number of leads to retrieve
	 * @return array Leads
	 */
	public static function get_recent_leads( $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'nexgen_leads';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		) ?: [];
	}
}
