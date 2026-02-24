<?php
/**
 * Plugin Name: NexGen Telegram Chat
 * Description: Plugin de chat bidireccional integrado con Telegram Bot para WordPress
 * Version: 3.0.0
 * Author: Gustavo Payan
 * 
 * @package NexGenTelegramChat
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants
define( 'NEXGEN_CHAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NEXGEN_CHAT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'NEXGEN_CHAT_MAIN_FILE', __FILE__ );

// Load service classes
require_once NEXGEN_CHAT_PLUGIN_PATH . 'includes/class-security.php';
require_once NEXGEN_CHAT_PLUGIN_PATH . 'includes/class-session-service.php';
require_once NEXGEN_CHAT_PLUGIN_PATH . 'includes/class-message-service.php';
require_once NEXGEN_CHAT_PLUGIN_PATH . 'includes/class-telegram-service.php';
require_once NEXGEN_CHAT_PLUGIN_PATH . 'includes/class-n8n-service.php';
require_once NEXGEN_CHAT_PLUGIN_PATH . 'includes/class-plugin.php';

// Initialize plugin
new NexGen_Telegram_Chat();

/**
 * Legacy class alias for backwards compatibility
 */
class NexGenTelegramChat extends NexGen_Telegram_Chat {
}