<?php
/**
 * Plugin Name: NexGen Telegram Chat
 * Description: Plugin de chat bidireccional integrado con Telegram Bot para WordPress
 * Version: 2.1.1
 * Author: Gustavo Payan
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('NEXGEN_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEXGEN_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));

class NexGenTelegramChat {

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_chat'));

        //registra hooks para ver en el menu de WordPress
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));

        add_action('wp_ajax_nexgen_send_message', array($this, 'handle_message'));
        add_action('wp_ajax_nopriv_nexgen_send_message', array($this, 'handle_message'));
        add_action('wp_ajax_nexgen_get_messages', array($this, 'get_messages'));
        add_action('wp_ajax_nopriv_nexgen_get_messages', array($this, 'get_messages'));

        // Guardar nombre y ajustar session_id
        add_action('wp_ajax_nexgen_set_chat_name', array($this, 'handle_set_chat_name'));
        add_action('wp_ajax_nopriv_nexgen_set_chat_name', array($this, 'handle_set_chat_name'));

        // Webhook de Telegram
        add_action('wp_ajax_nexgen_telegram_webhook', array($this, 'handle_telegram_webhook'));
        add_action('wp_ajax_nopriv_nexgen_telegram_webhook', array($this, 'handle_telegram_webhook'));

        // Debug y configuración
        add_action('wp_ajax_nexgen_debug_test', array($this, 'debug_test'));
        add_action('wp_ajax_nopriv_nexgen_debug_test', array($this, 'debug_test'));
        add_action('wp_ajax_nexgen_set_webhook', array($this, 'set_webhook'));

        // Crear tabla al activar
        register_activation_hook(__FILE__, array($this, 'create_tables'));
    }

    public function init() {
        // Inicialización del plugin
    }

    public function create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nexgen_chat_messages';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            message_text text NOT NULL,
            message_type varchar(20) NOT NULL,
            sender_info text,
            telegram_message_id int,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function enqueue_scripts() {
        wp_enqueue_script('nexgen-chat-js', NEXGEN_CHAT_PLUGIN_URL . 'assets/chat.js', array('jquery'), '2.1.0', true);
        wp_enqueue_style('nexgen-chat-css', NEXGEN_CHAT_PLUGIN_URL . 'assets/chatbot.css', array(), '2.1.0');

        // Localizar script con configuraciones
        wp_localize_script('nexgen-chat-js', 'nexgen_chat_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nexgen_chat_nonce'),
            'settings' => $this->get_chat_settings(),
            'session_id' => $this->get_session_id(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }

    public function get_chat_settings() {
        return array(
            'welcome_message' => get_option('nexgen_welcome_message', '¡Hola! ¿En qué puedo ayudarte?'),
            'error_message' => get_option('nexgen_error_message', 'Lo siento, ha ocurrido un error. Inténtalo de nuevo.'),
            'chat_title' => get_option('nexgen_chat_title', 'Chat de Soporte'),
            'primary_color' => get_option('nexgen_primary_color', '#007cba'),
            'secondary_color' => get_option('nexgen_secondary_color', '#00ba7c'),
            'background_color' => get_option('nexgen_background_color', '#ffffff'),
            'position' => get_option('nexgen_chat_position', 'bottom-right'),
            'auto_open_delay' => get_option('nexgen_auto_open_delay', 20),
            'poll_interval' => 3000 // Verificar nuevos mensajes cada 3 segundos
        );
    }

    private function get_session_id() {
        if (!session_id()) {
            @session_start();
        }

        if (!isset($_SESSION['nexgen_chat_session'])) {
            $_SESSION['nexgen_chat_session'] = 'chat_' . uniqid() . '_' . time();
        }

        return $_SESSION['nexgen_chat_session'];
    }

    public function render_chat() {
        $settings = $this->get_chat_settings();
        ?>
        <div class="nexgen-chatbot nexgen-<?php echo esc_attr($settings['position']); ?>" style="
            --primary-color: <?php echo esc_attr($settings['primary_color']); ?>;
            --secondary-color: <?php echo esc_attr($settings['secondary_color']); ?>;
            --background-color: <?php echo esc_attr($settings['background_color']); ?>;
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
                    <h3><?php echo esc_html($settings['chat_title']); ?></h3>
                    <div class="nexgen-status-indicator">
                        <span class="nexgen-status-dot"></span>
                        <span class="nexgen-status-text">En línea</span>
                    </div>
                    <button class="nexgen-chatbot-close" id="nexgen-chat-close">×</button>
                </div>

                <!-- Overlay para capturar nombre al iniciar chat -->
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
                            <?php echo esc_html($settings['welcome_message']); ?>
                        </div>
                        <div class="nexgen-message-time"><?php echo date('H:i'); ?></div>
                    </div>
                </div>

                <!-- Indicador de escritura -->
                <div class="nexgen-chatbot-typing" id="nexgen-typing" style="display: none;">
                    <div class="nexgen-typing-indicator">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    Escribiendo...
                </div>

                <!-- Área de input -->
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

    public function handle_message() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexgen_chat_nonce')) {
            wp_send_json_error('Nonce inválido');
        }

        // Obtener y sanitizar el mensaje
        if (!isset($_POST['message']) || empty(trim($_POST['message']))) {
            wp_send_json_error('Mensaje vacío');
        }

        $message = sanitize_text_field(trim($_POST['message']));
        $session_id = sanitize_text_field($_POST['session_id']);
        $bot_token = get_option('nexgen_bot_token');
        $chat_id = get_option('nexgen_chat_id');

        // Verificar configuración
        if (empty($bot_token) || empty($chat_id)) {
            wp_send_json_error('Bot no configurado correctamente');
        }

        // Guardar mensaje del usuario en BD
        $this->save_message($session_id, $message, 'user');

        // Enviar mensaje a Telegram
        $response = $this->send_to_telegram($bot_token, $chat_id, $message, $session_id);

        if ($response['success']) {
            wp_send_json_success(array(
                'message' => 'Mensaje enviado correctamente',
                'session_id' => $session_id
            ));
        } else {
            wp_send_json_error($response['error_message']);
        }
    }

    public function get_messages() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nexgen_chat_nonce')) {
            wp_send_json_error('Nonce inválido');
        }

        $session_id = sanitize_text_field($_POST['session_id']);
        $last_message_id = intval($_POST['last_message_id']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'nexgen_chat_messages';

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE session_id = %s AND id > %d
             ORDER BY created_at ASC",
            $session_id, $last_message_id
        ));

        $formatted_messages = array();
        foreach ($messages as $msg) {
            $formatted_messages[] = array(
                'id' => $msg->id,
                'text' => $msg->message_text,
                'type' => $msg->message_type,
                'time' => date('H:i', strtotime($msg->created_at))
            );
        }

        wp_send_json_success($formatted_messages);
    }

    private function save_message($session_id, $message, $type, $telegram_message_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nexgen_chat_messages';

        if (!session_id()) {
            @session_start();
        }

        $sender_info = array(
            'ip' => $this->get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'page' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        );

        if (isset($_SESSION['nexgen_chat_session']) && $_SESSION['nexgen_chat_session'] === $session_id && isset($_SESSION['nexgen_chat_name'])) {
            $sender_info['name'] = $_SESSION['nexgen_chat_name'];
        }

        $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'message_text' => $message,
                'message_type' => $type,
                'sender_info' => json_encode($sender_info),
                'telegram_message_id' => $telegram_message_id,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );

        return $wpdb->insert_id;
    }

    private function send_to_telegram($bot_token, $chat_id, $message, $session_id) {
        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

        if (!session_id()) {
            @session_start();
        }

        // Obtener información adicional
        $site_name = get_bloginfo('name');
        $current_page = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Página desconocida';
        $name = (isset($_SESSION['nexgen_chat_session']) && $_SESSION['nexgen_chat_session'] === $session_id && isset($_SESSION['nexgen_chat_name']))
            ? $_SESSION['nexgen_chat_name']
            : $this->extract_name_from_session($session_id);

        $formatted_message  = "💬 Nuevo mensaje desde {$site_name}\n\n";
        $formatted_message .= "👤 Nombre: {$name}\n";
        $formatted_message .= "🔗 Sesión: {$session_id}\n";
        $formatted_message .= "🌐 Página: {$current_page}\n\n";
        $formatted_message .= "📝 Mensaje: {$message}\n";
        $formatted_message .= "⏰ Hora: " . current_time('mysql') . "\n\n";
        $formatted_message .= "💡 Para responder, responde a este mensaje";

        $data = array(
            'chat_id' => $chat_id,
            'text' => $formatted_message
        );

        $args = array(
            'body' => $data,
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(),
            'cookies' => array()
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error_message' => 'Error de conexión: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            $decoded_response = json_decode($response_body, true);
            $error_description = isset($decoded_response['description']) ? $decoded_response['description'] : 'Error desconocido';

            return array(
                'success' => false,
                'error_message' => "Error de Telegram ({$response_code}): {$error_description}"
            );
        }

        $decoded_response = json_decode($response_body, true);

        if (!isset($decoded_response['ok']) || !$decoded_response['ok']) {
            return array(
                'success' => false,
                'error_message' => "Error de Telegram: " . $decoded_response['description']
            );
        }

        return array('success' => true);
    }

    public function handle_telegram_webhook() {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('NexGen Chat - Webhook recibido: ' . $input);
        }

        if (isset($update['message'])) {
            $message = $update['message'];
            $text = isset($message['text']) ? $message['text'] : '';

            // Verificar si es /reply o respuesta a un mensaje
            if (strpos($text, '/reply ') === 0) {
                $this->process_reply_command($text);
            } elseif (isset($message['reply_to_message'])) {
                $this->process_reply_to_message($message);
            }
        }

        http_response_code(200);
        echo "OK";
        wp_die();
    }

    private function process_reply_command($text) {
        // Formato: /reply session_id mensaje
        $parts = explode(' ', $text, 3);
        if (count($parts) >= 3) {
            $session_id = $parts[1];
            $reply_message = $parts[2];
            $this->save_message($session_id, $reply_message, 'bot');
        }
    }

    private function process_reply_to_message($message) {
        $reply_text = isset($message['text']) ? $message['text'] : '';
        $original_message = isset($message['reply_to_message']['text']) ? $message['reply_to_message']['text'] : '';

        // Extraer session_id del mensaje original
        if (preg_match('/🔗 Sesión: (chat_[A-Za-z0-9\-]+_[A-Fa-f0-9]{6})/', $original_message, $matches)) {
            $session_id = $matches[1];
            $this->save_message($session_id, $reply_text, 'bot');
        }
    }

    private function extract_name_from_session($session_id) {
        // session_id esperado: chat_<slug_nombre>_<hash6>
        if (preg_match('/^chat_([a-z0-9\-]+)_[a-f0-9]{6}$/i', $session_id, $m)) {
            $slug = $m[1];
            $parts = array_filter(preg_split('/-+/', $slug));
            $parts = array_map(function($p) { return mb_convert_case($p, MB_CASE_TITLE, 'UTF-8'); }, $parts);
            return implode(' ', $parts);
        }
        return 'Visitante';
    }

    public function handle_set_chat_name() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexgen_chat_nonce')) {
            wp_send_json_error('Nonce inválido');
        }

        $raw_name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        if (empty($raw_name)) {
            wp_send_json_error('Nombre requerido');
        }

        // Generar session_id legible: chat_<slug_nombre>_<hash6>
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $raw_name), '-'));
        if (empty($slug)) { $slug = 'visitante'; }
        $hash = substr(md5($raw_name . '|' . microtime(true) . '|' . wp_rand()), 0, 6);
        $new_session = 'chat_' . $slug . '_' . $hash;

        if (!session_id()) { @session_start(); }
        $_SESSION['nexgen_chat_session'] = $new_session;
        $_SESSION['nexgen_chat_name'] = $raw_name;

        wp_send_json_success(array('session_id' => $new_session));
    }

    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Desconocida';
    }

    public function debug_test() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }

        $bot_token = get_option('nexgen_bot_token');
        $chat_id = get_option('nexgen_chat_id');

        if (empty($bot_token) || empty($chat_id)) {
            wp_send_json_error('Configuración incompleta');
        }

        $test_message = "🧪 Mensaje de prueba desde " . get_bloginfo('name') . " - " . date('Y-m-d H:i:s');
        $result = $this->send_to_telegram($bot_token, $chat_id, $test_message, 'test_session');

        if ($result['success']) {
            wp_send_json_success('✅ Mensaje de prueba enviado correctamente');
        } else {
            wp_send_json_error($result['error_message']);
        }
    }

    public function set_webhook() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }

        $bot_token = get_option('nexgen_bot_token');
        if (empty($bot_token)) {
            wp_send_json_error('Token del bot no configurado');
        }

        $webhook_url = admin_url('admin-ajax.php?action=nexgen_telegram_webhook');
        $api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook";

        $response = wp_remote_post($api_url, array(
            'body' => array(
                'url' => $webhook_url,
                'drop_pending_updates' => true
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Error de conexión: ' . $response->get_error_message());
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded = json_decode($response_body, true);

        if (isset($decoded['ok']) && $decoded['ok']) {
            wp_send_json_success('✅ Webhook configurado correctamente');
        } else {
            $error = isset($decoded['description']) ? $decoded['description'] : 'Error desconocido';
            wp_send_json_error('Error: ' . $error);
        }
    }

    public function add_admin_menu() {
    add_menu_page(
        'NexGen Telegram Chat',           // Título de la página
        'NexGen Livechat',               // Texto que aparece en el menú
        'manage_options',                  // Capacidad requerida (solo admins)
        'nexgen-telegram-chat',            // Slug único de la página
        array($this, 'admin_page'),        // Función que renderiza el contenido
        'dashicons-format-chat',           // Ícono del menú (burbuja de chat)
        75                                 // Posición en el menú (75 = debajo de "Herramientas")
    );
}

    public function admin_init() {
        register_setting('nexgen_chat_settings', 'nexgen_bot_token');
        register_setting('nexgen_chat_settings', 'nexgen_chat_id');
        register_setting('nexgen_chat_settings', 'nexgen_welcome_message');
        register_setting('nexgen_chat_settings', 'nexgen_error_message');
        register_setting('nexgen_chat_settings', 'nexgen_chat_title');
        register_setting('nexgen_chat_settings', 'nexgen_primary_color');
        register_setting('nexgen_chat_settings', 'nexgen_secondary_color');
        register_setting('nexgen_chat_settings', 'nexgen_background_color');
        register_setting('nexgen_chat_settings', 'nexgen_chat_position');
        register_setting('nexgen_chat_settings', 'nexgen_auto_open_delay');
    }

    public function admin_page() {
        $bot_token = get_option('nexgen_bot_token');
        $chat_id = get_option('nexgen_chat_id');
        $is_configured = !empty($bot_token) && !empty($chat_id);
        $webhook_url = admin_url('admin-ajax.php?action=nexgen_telegram_webhook');
        ?>
        <div class="wrap">
            <h1>🚀 NexGen Telegram Chat</h1>
            <p>Este Plugin permite hacer uso de telegram con un chatbot para poder tener un chat en tiempo real desde la web con los usuarios </p>

            <?php if ($is_configured): ?>
                <div class="notice notice-success">
                    <p>✅ Bot configurado correctamente.
                    <button type="button" class="button" onclick="testTelegramConnection()">Enviar mensaje de prueba</button>
                    <button type="button" class="button button-primary" onclick="setupWebhook()">Configurar Webhook</button>
                    </p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p>⚠️ Bot no configurado. Completa los campos Token y Chat ID para activar el chat.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('nexgen_chat_settings'); ?>

                <h2>🤖 Configuración de Telegram</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Token del Bot de Telegram</th>
                        <td>
                            <input type="text" name="nexgen_bot_token" value="<?php echo esc_attr($bot_token); ?>" class="regular-text" placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz" />
                            <p class="description">Obtén tu token desde @BotFather en Telegram</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Chat ID de Telegram</th>
                        <td>
                            <input type="text" name="nexgen_chat_id" value="<?php echo esc_attr($chat_id); ?>" class="regular-text" placeholder="123456789" />
                            <p class="description">ID del chat donde recibirás los mensajes. Obtén tu ID desde @userinfobot</p>
                        </td>
                    </tr>
                </table>

                <h2>💬 Configuración del Chat</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Título del Chat</th>
                        <td>
                            <input type="text" name="nexgen_chat_title" value="<?php echo esc_attr(get_option('nexgen_chat_title', 'Chat de Soporte')); ?>" class="regular-text" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Mensaje de Bienvenida</th>
                        <td>
                            <textarea name="nexgen_welcome_message" rows="3" class="large-text"><?php echo esc_textarea(get_option('nexgen_welcome_message', '¡Hola! ¿En qué puedo ayudarte?')); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Mensaje de Error</th>
                        <td>
                            <textarea name="nexgen_error_message" rows="3" class="large-text"><?php echo esc_textarea(get_option('nexgen_error_message', 'Lo siento, ha ocurrido un error. Inténtalo de nuevo.')); ?></textarea>
                        </td>
                    </tr>
                </table>

                <h2>🎨 Personalización Visual</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Color Primario</th>
                        <td><input type="color" name="nexgen_primary_color" value="<?php echo esc_attr(get_option('nexgen_primary_color', '#007cba')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Color Secundario</th>
                        <td><input type="color" name="nexgen_secondary_color" value="<?php echo esc_attr(get_option('nexgen_secondary_color', '#00ba7c')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Color de Fondo</th>
                        <td><input type="color" name="nexgen_background_color" value="<?php echo esc_attr(get_option('nexgen_background_color', '#ffffff')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Posición del Chat</th>
                        <td>
                            <select name="nexgen_chat_position">
                                <option value="bottom-right" <?php selected(get_option('nexgen_chat_position', 'bottom-right'), 'bottom-right'); ?>>Abajo Derecha</option>
                                <option value="bottom-left" <?php selected(get_option('nexgen_chat_position', 'bottom-left'), 'bottom-left'); ?>>Abajo Izquierda</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto-apertura (segundos)</th>
                        <td>
                            <input type="number" name="nexgen_auto_open_delay" value="<?php echo esc_attr(get_option('nexgen_auto_open_delay', 20)); ?>" min="0" max="300" />
                            <p class="description">0 para desactivar la auto-apertura</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Guardar Configuración'); ?>
            </form>

            <hr>

            <h2>🔗 Configuración del Webhook (REQUERIDO para chat bidireccional)</h2>
            <div class="notice notice-info">
                <p><strong>📋 URL del Webhook:</strong></p>
                <code style="background: #f0f0f0; padding: 10px; display: block; margin: 10px 0;"><?php echo esc_html($webhook_url); ?></code>
                <p>Haz clic en "Configurar Webhook" arriba para configurarlo automáticamente.</p>
            </div>

            <h2>💡 Cómo responder desde Telegram</h2>
            <div class="notice notice-success">
                <p><strong>Tienes 2 formas de responder:</strong></p>
                <ol>
                    <li><strong>Responder al mensaje:</strong> Usa la función "Responder" de Telegram en el mensaje que recibiste</li>
                    <li><strong>Comando /reply:</strong> Escribe <code>/reply session_id tu_respuesta</code></li>
                </ol>
                <p>💡 <strong>Tip:</strong> El ID de sesión aparece en cada mensaje que recibes (🔗 Sesión: chat_xxxx)</p>
            </div>

            <h2>📋 Instrucciones Completas</h2>
            <ol>
                <li><strong>Crear bot:</strong> Habla con @BotFather en Telegram y envía <code>/newbot</code></li>
                <li><strong>Obtener token:</strong> Copia el token que te da BotFather y pégalo arriba</li>
                <li><strong>Obtener Chat ID:</strong> Envía un mensaje a @userinfobot para obtener tu ID</li>
                <li><strong>Configurar webhook:</strong> Haz clic en "Configurar Webhook" arriba</li>
                <li><strong>Probar:</strong> Envía un mensaje desde tu web y responde desde Telegram</li>
                <li><strong>Personalizar:</strong> Ajusta colores y mensajes según tu marca</li>
            </ol>
        </div>

        <script>
        function testTelegramConnection() {
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Enviando...';
            button.disabled = true;

            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=nexgen_debug_test'
            })
            .then(r => r.json())
            .then(data => alert((data.success ? '✅ ' : '❌ Error: ') + data.data))
            .catch(err => alert('❌ Error de conexión: ' + err))
            .finally(() => {
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        function setupWebhook() {
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Configurando...';
            button.disabled = true;

            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=nexgen_set_webhook'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.data + '\n\n🎉 ¡Ya puedes responder desde Telegram y se verá en el chat web!');
                } else {
                    alert('❌ Error: ' + data.data);
                }
            })
            .catch(err => alert('❌ Error de conexión: ' + err))
            .finally(() => {
                button.textContent = originalText;
                button.disabled = false;
            });
        }
        </script>
        <?php
    }
}

// Inicializar el plugin
new NexGenTelegramChat();
?>