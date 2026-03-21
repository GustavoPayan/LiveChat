<?php
/**
 * Main Plugin Class for NexGen Telegram Chat
 * Handles WordPress hooks, AJAX routing, and service initialization
 *
 * @package NexGenTelegramChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NexGen_Telegram_Chat {

	/**
	 * Constructor - Register hooks
	 */
	public function __construct() {
		// Core hooks
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_footer', [ $this, 'render_chat' ] );

		// Admin menu
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );

		// AJAX - Message handling
		add_action( 'wp_ajax_nexgen_send_message', [ $this, 'handle_send_message' ] );
		add_action( 'wp_ajax_nopriv_nexgen_send_message', [ $this, 'handle_send_message' ] );

		add_action( 'wp_ajax_nexgen_get_messages', [ $this, 'handle_get_messages' ] );
		add_action( 'wp_ajax_nopriv_nexgen_get_messages', [ $this, 'handle_get_messages' ] );

		add_action( 'wp_ajax_nexgen_get_conversation', [ $this, 'handle_get_conversation' ] );
		add_action( 'wp_ajax_nopriv_nexgen_get_conversation', [ $this, 'handle_get_conversation' ] );

		// AJAX - Session name
		add_action( 'wp_ajax_nexgen_set_chat_name', [ $this, 'handle_set_chat_name' ] );
		add_action( 'wp_ajax_nopriv_nexgen_set_chat_name', [ $this, 'handle_set_chat_name' ] );

		// AJAX - Telegram webhook
		add_action( 'wp_ajax_nexgen_telegram_webhook', [ $this, 'handle_telegram_webhook' ] );
		add_action( 'wp_ajax_nopriv_nexgen_telegram_webhook', [ $this, 'handle_telegram_webhook' ] );

		// AJAX - Admin functions
		add_action( 'wp_ajax_nexgen_debug_test', [ $this, 'handle_debug_test' ] );
		add_action( 'wp_ajax_nexgen_set_webhook', [ $this, 'handle_set_webhook' ] );
		add_action( 'wp_ajax_nexgen_test_n8n', [ $this, 'handle_test_n8n' ] );
		add_action( 'wp_ajax_nexgen_test_llm', [ $this, 'handle_test_llm' ] );

		// AJAX - Security (nonce refresh)
		add_action( 'wp_ajax_nexgen_refresh_nonce', [ $this, 'handle_refresh_nonce' ] );
		add_action( 'wp_ajax_nopriv_nexgen_refresh_nonce', [ $this, 'handle_refresh_nonce' ] );

		// Activation hook
		register_activation_hook( NEXGEN_CHAT_MAIN_FILE, [ $this, 'on_activate' ] );
	}

	/**
	 * Plugin initialization
	 * Note: No longer initializing sessions here to prevent REST API conflicts
	 *
	 * @return void
	 */
	public function init() {
		// Placeholder for future initialization if needed
		// Session management is now cookie-based and doesn't require init()
	}

	/**
	 * On plugin activation
	 *
	 * @return void
	 */
	public function on_activate() {
		NexGen_Message_Service::create_table();
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$settings = $this->get_chat_settings();

		wp_enqueue_script(
			'nexgen-chat-js',
			NEXGEN_CHAT_PLUGIN_URL . 'assets/chat.js',
			[ 'jquery' ],
			'3.2.0',
			true
		);

		wp_enqueue_style(
			'nexgen-chat-css',
			NEXGEN_CHAT_PLUGIN_URL . 'assets/chatbot.css',
			[],
			'3.2.0'
		);

		// Localize script with settings
		wp_localize_script(
			'nexgen-chat-js',
			'nexgen_chat_ajax',
			[
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => NexGen_Security::create_nonce(),
				'settings'  => $settings,
				'session_id' => NexGen_Session_Service::get_session_id(),
				'debug'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
			]
		);
	}

	/**
	 * Get all chat settings
	 *
	 * @return array Settings array.
	 */
	private function get_chat_settings() {
		return [
			'welcome_message'    => get_option( 'nexgen_welcome_message', '¡Hola! ¿En qué puedo ayudarte?' ),
			'error_message'      => get_option( 'nexgen_error_message', 'Lo siento, ha ocurrido un error. Inténtalo de nuevo.' ),
			'chat_title'         => get_option( 'nexgen_chat_title', 'Chat de Soporte' ),
			'primary_color'      => get_option( 'nexgen_primary_color', '#007cba' ),
			'secondary_color'    => get_option( 'nexgen_secondary_color', '#00ba7c' ),
			'background_color'   => get_option( 'nexgen_background_color', '#ffffff' ),
			'position'           => get_option( 'nexgen_chat_position', 'bottom-right' ),
			'auto_open_delay'    => (int) get_option( 'nexgen_auto_open_delay', 20 ),
			'poll_interval'      => 3000,
		];
	}

	/**
	 * Render chat widget in footer
	 *
	 * @return void
	 */
	public function render_chat() {
		$settings = $this->get_chat_settings();
		?>
		<div class="nexgen-chatbot nexgen-<?php echo esc_attr( $settings['position'] ); ?>" style="
			--primary-color: <?php echo esc_attr( $settings['primary_color'] ); ?>;
			--secondary-color: <?php echo esc_attr( $settings['secondary_color'] ); ?>;
			--background-color: <?php echo esc_attr( $settings['background_color'] ); ?>;
		">
			<!-- Botón toggle -->
			<div class="nexgen-chatbot-toggle" id="nexgen-chat-toggle">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="chat-icon">
					<path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
				</svg>
				<span class="close-icon" style="display: none;">×</span>
				<div class="nexgen-notification-badge" id="nexgen-notification" style="display: none;">1</div>
			</div>

			<!-- Ventana del chat -->
			<div class="nexgen-chatbot-window" id="nexgen-chat-window" style="display: none;">
				<!-- Header -->
				<div class="nexgen-chatbot-header">
					<h3><?php echo esc_html( $settings['chat_title'] ); ?></h3>
					<div class="nexgen-status-indicator">
						<span class="nexgen-status-dot"></span>
						<span class="nexgen-status-text">En línea</span>
					</div>
					<button class="nexgen-chatbot-close" id="nexgen-chat-close">×</button>
				</div>

				<!-- Name overlay -->
				<div class="nexgen-name-overlay" id="nexgen-name-overlay" style="display: none;">
					<div class="nexgen-name-card">
						<div class="nexgen-name-title">¡Hola! Antes de empezar</div>
						<label for="nexgen-name-input" class="nexgen-name-label">¿Cómo te llamas?</label>
						<input type="text" id="nexgen-name-input" class="nexgen-name-input" placeholder="Escribe tu nombre" />
						<button id="nexgen-name-save" class="nexgen-name-button">Empezar chat</button>
					</div>
				</div>

				<!-- Área de mensajes -->
				<div class="nexgen-chatbot-messages" id="nexgen-chat-messages">
					<div class="nexgen-message nexgen-bot-message">
						<div class="nexgen-message-content">
							<?php echo esc_html( $settings['welcome_message'] ); ?>
						</div>
						<div class="nexgen-message-time"><?php echo date( 'H:i' ); ?></div>
					</div>
				</div>

				<!-- Typing indicator -->
				<div class="nexgen-chatbot-typing" id="nexgen-typing" style="display: none;">
					<div class="nexgen-typing-indicator">
						<span></span>
						<span></span>
						<span></span>
					</div>
					Escribiendo...
				</div>

				<!-- Input area -->
				<div class="nexgen-chatbot-input-area">
					<input type="text" id="nexgen-chat-input" placeholder="Escribe tu mensaje...">
					<button id="nexgen-chat-send">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
							<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
						</svg>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: Handle send message
	 * Hybrid routing: LLM (80%) → Leads/N8N (15%) → Blocked/Telegram (5%)
	 *
	 * @return void
	 */
	public function handle_send_message() {
		try {
			// Verify nonce
			if ( ! NexGen_Security::verify_nonce() ) {
				wp_send_json_error( 'Nonce inválido' );
			}

			// Get and validate message
			$message = isset( $_POST['message'] ) ? $_POST['message'] : '';
			$message_validation = NexGen_Security::validate_message( $message );

			if ( ! $message_validation['valid'] ) {
				wp_send_json_error( $message_validation['error'] );
			}

			$message    = $message_validation['message'];
			$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : '';

			// Validate session ID
			if ( ! NexGen_Security::validate_session_id( $session_id ) ) {
				wp_send_json_error( 'Session ID inválido' );
			}

			// Check rate limit
			$rate_limit = NexGen_Security::check_rate_limit( $session_id );
			if ( ! $rate_limit['allowed'] ) {
				wp_send_json_error( 'Demasiados mensajes. Intenta más tarde.' );
			}

			// Get visitor name from session
			$visitor_name = isset( $_SESSION['nexgen_chat_name'] ) ? $_SESSION['nexgen_chat_name'] : '';

			// Route message through hybrid LLM + N8N router
			$result = NexGen_LLM_Router::process_message(
				message: $message,
				session_id: $session_id,
				visitor_name: $visitor_name,
				visitor_email: ''
			);

			if ( ! $result['success'] ) {
				wp_send_json_error( $result['response'] );
			}

			// Success response with route type and metadata
			wp_send_json_success( [
				'message'    => $result['response'],
				'session_id' => $session_id,
				'type'       => $result['type'],
				'metadata'   => $result['metadata'] ?? [],
			] );
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'send_message_error', [ 'error' => $e->getMessage() ] );
			wp_send_json_error( 'Error interno: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX: Get messages for polling
	 *
	 * @return void
	 */
	public function handle_get_messages() {
		try {
			if ( ! NexGen_Security::verify_nonce() ) {
				wp_send_json_error( 'Nonce inválido' );
			}

			$session_id       = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : '';
			$last_message_id  = isset( $_POST['last_message_id'] ) ? (int) $_POST['last_message_id'] : 0;

			if ( ! NexGen_Security::validate_session_id( $session_id ) ) {
				wp_send_json_error( 'Session ID inválido' );
			}

			$messages = NexGen_Message_Service::get_messages( $session_id, $last_message_id );

			wp_send_json_success( $messages );
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'get_messages_error', [ 'error' => $e->getMessage() ] );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: Get conversation history (Phase 5 - optimized)
	 * Retrieves paginated conversation history for infinite scroll
	 *
	 * @return void
	 */
	public function handle_get_conversation() {
		try {
			if ( ! NexGen_Security::verify_nonce() ) {
				wp_send_json_error( 'Nonce inválido' );
			}

			$session_id = NexGen_Session_Service::get_session_id();
			if ( ! $session_id ) {
				wp_send_json_error( 'No session' );
			}

			if ( ! NexGen_Security::validate_session_id( $session_id ) ) {
				wp_send_json_error( 'Session ID inválido' );
			}

			// Get pagination params
			$offset = isset( $_GET['offset'] ) ? max( 0, (int) $_GET['offset'] ) : 0;
			$limit  = isset( $_GET['limit'] ) ? min( 20, (int) $_GET['limit'] ) : 10;  // Max 20 to prevent abuse

			// Rate limit: max 1 request per second
			$rate_check = NexGen_Security::check_rate_limit( $session_id . '_history', 1, 1 );
			if ( ! $rate_check['allowed'] ) {
				wp_send_json_error( 'Too many requests' );
			}

			$messages = NexGen_Message_Service::get_conversation_optimized( $session_id, $limit, $offset );

			wp_send_json_success( $messages );
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'get_conversation_error', [ 'error' => $e->getMessage() ] );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: Set visitor name
	 *
	 * @return void
	 */
	public function handle_set_chat_name() {
		try {
			if ( ! NexGen_Security::verify_nonce() ) {
				wp_send_json_error( 'Nonce inválido' );
			}

			$name = isset( $_POST['name'] ) ? $_POST['name'] : '';

			if ( empty( $name ) ) {
				wp_send_json_error( 'Nombre requerido' );
			}

			$new_session_id = NexGen_Session_Service::set_chat_name( $name );

			wp_send_json_success( [ 'session_id' => $new_session_id ] );
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'set_chat_name_error', [ 'error' => $e->getMessage() ] );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: Handle Telegram webhook
	 *
	 * @return void
	 */
	public function handle_telegram_webhook() {
		try {
			$result = NexGen_Telegram_Service::handle_webhook();

			http_response_code( $result['success'] ? 200 : 400 );
			echo 'OK';
			wp_die();
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'webhook_error', [ 'error' => $e->getMessage() ] );
			http_response_code( 500 );
			echo 'Error';
			wp_die();
		}
	}

	/**
	 * AJAX: Debug test message
	 *
	 * @return void
	 */
	public function handle_debug_test() {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Sin permisos' );
			}

			$result = NexGen_Telegram_Service::send_test_message();

			if ( ! $result['success'] ) {
				wp_send_json_error( $result['error'] );
			}

			wp_send_json_success( '✅ Mensaje de prueba enviado correctamente' );
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'debug_test_error', [ 'error' => $e->getMessage() ] );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: Set Telegram webhook
	 *
	 * @return void
	 */
	public function handle_set_webhook() {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Sin permisos' );
			}

			$result = NexGen_Telegram_Service::set_webhook();

			if ( ! $result['success'] ) {
				wp_send_json_error( $result['error'] );
			}

			wp_send_json_success( '✅ Webhook configurado correctamente' );
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'set_webhook_error', [ 'error' => $e->getMessage() ] );
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Test N8N webhook connection (AJAX handler)
	 *
	 * @return void
	 */
	public function handle_test_n8n() {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Sin permisos' );
			}

			$config = NexGen_N8N_Service::get_config();

			if ( ! $config['enabled'] ) {
				wp_send_json_error( 'N8N no está habilitado' );
			}

			if ( empty( $config['webhook_url'] ) ) {
				wp_send_json_error( 'URL del webhook no configurada' );
			}

			// Send test message
			$result = NexGen_N8N_Service::send_to_n8n(
				'¿Tienes hosting?',
				'chat_test_' . wp_generate_password( 6, false )
			);

			if ( ! $result['success'] ) {
				wp_send_json_error( 'Error: ' . $result['error'] );
			}

			wp_send_json_success( [
				'message'  => '✅ Conexión con N8N exitosa',
				'response' => $result['response'],
			] );
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'test_n8n_error', [ 'error' => $e->getMessage() ] );
			wp_send_json_error( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Test LLM connection (AJAX handler)
	 *
	 * @return void
	 */
	public function handle_test_llm() {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Sin permisos' );
			}

			// Check if LLM is enabled
			$llm_enabled = get_option( 'nexgen_llm_enabled', true );
			if ( ! $llm_enabled ) {
				wp_send_json_error( 'LLM no está habilitado' );
			}

			// Check API key
			$api_key = get_option( 'nexgen_llm_api_key' );
			if ( empty( $api_key ) ) {
				wp_send_json_error( 'Clave API no configurada' );
			}

			// Test LLM query
			$start_time = microtime( true );
			$result = NexGen_LLM_Service::query(
				'¿Cuál es 2+2? Responde brevemente.',
				'Eres un asistente útil. Responde en español de forma breve.'
			);
			$latency_ms = round( ( microtime( true ) - $start_time ) * 1000, 2 );

			if ( ! $result['success'] ) {
				wp_send_json_error( 'Error LLM: ' . $result['error'] );
			}

			wp_send_json_success( [
				'response'   => $result['response'],
				'latency_ms' => $latency_ms,
				'cost'       => $result['cost'] ?? 0,
				'provider'   => $result['provider'] ?? 'unknown',
			] );
		} catch ( Exception $e ) {
			NexGen_Security::log_event( 'test_llm_error', [ 'error' => $e->getMessage() ] );
			wp_send_json_error( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX: Refresh nonce (no verification needed - this endpoint provides nonces)
	 * Called when client receives "Nonce inválido" error
	 *
	 * @return void
	 */
	public function handle_refresh_nonce() {
		// Generate fresh nonce
		$new_nonce = NexGen_Security::create_nonce();

		// Return new nonce
		wp_send_json_success( [
			'nonce' => $new_nonce,
			'timestamp' => current_time( 'mysql' ),
		] );
	}

	/**
	 * Register admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_menu_page(
			'NexGen Telegram Chat',
			'NexGen Livechat',
			'manage_options',
			'nexgen-telegram-chat',
			[ $this, 'render_admin_page' ],
			'dashicons-format-chat'
		);
	}

	/**
	 * Register admin settings
	 *
	 * @return void
	 */
	public function admin_init() {
		// Telegram settings
		register_setting( 'nexgen_chat_settings', 'nexgen_bot_token', [
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_chat_id', [
			'sanitize_callback' => 'sanitize_text_field',
		] );

		// Chat UI settings
		register_setting( 'nexgen_chat_settings', 'nexgen_chat_title', [
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_welcome_message', [
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_error_message', [
			'sanitize_callback' => 'sanitize_textarea_field',
		] );

		// Appearance settings
		register_setting( 'nexgen_chat_settings', 'nexgen_primary_color', [
			'sanitize_callback' => 'sanitize_hex_color',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_secondary_color', [
			'sanitize_callback' => 'sanitize_hex_color',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_background_color', [
			'sanitize_callback' => 'sanitize_hex_color',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_chat_position', [
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_auto_open_delay', [
			'sanitize_callback' => 'absint',
		] );

		// N8N settings (Phase 2)
		register_setting( 'nexgen_chat_settings', 'nexgen_n8n_enabled', [
			'sanitize_callback' => function( $value ) {
				return ! empty( $value ) ? 1 : 0;
			},
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_n8n_webhook_url', [
			'sanitize_callback' => 'esc_url_raw',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_n8n_api_key', [
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_n8n_timeout', [
			'sanitize_callback' => 'absint',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_ai_keywords', [
			'sanitize_callback' => 'sanitize_textarea_field',
		] );

		// LLM/IA settings (Phase 5 - Hybrid Routing)
		register_setting( 'nexgen_chat_settings', 'nexgen_llm_enabled', [
			'sanitize_callback' => function( $value ) {
				return ! empty( $value ) ? 1 : 0;
			},
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_llm_provider', [
			'sanitize_callback' => function( $value ) {
				return in_array( $value, ['openai', 'claude', 'gemini'], true ) ? $value : 'openai';
			},
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_llm_api_key', [
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_business_context', [
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_business_type', [
			'sanitize_callback' => function( $value ) {
				return in_array( $value, ['general', 'saas', 'ecommerce', 'service', 'agency'], true ) ? $value : 'general';
			},
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_supported_topics', [
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_scope_keywords', [
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_lead_keywords', [
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_rejection_message', [
			'sanitize_callback' => 'sanitize_textarea_field',
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_llm_temperature', [
			'sanitize_callback' => function( $value ) {
				$temp = floatval( $value );
				return $temp >= 0 && $temp <= 1 ? $temp : 0.7;
			},
		] );
		register_setting( 'nexgen_chat_settings', 'nexgen_llm_max_tokens', [
			'sanitize_callback' => function( $value ) {
				$tokens = absint( $value );
				return $tokens >= 50 && $tokens <= 2000 ? $tokens : 500;
			},
		] );
	}

	/**
	 * Render admin settings page
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$bot_token       = get_option( 'nexgen_bot_token' );
		$chat_id         = get_option( 'nexgen_chat_id' );
		$is_configured   = ! empty( $bot_token ) && ! empty( $chat_id );
		$webhook_url     = admin_url( 'admin-ajax.php?action=nexgen_telegram_webhook' );

		include NEXGEN_CHAT_PLUGIN_PATH . 'includes/admin-page.php';
	}
}
