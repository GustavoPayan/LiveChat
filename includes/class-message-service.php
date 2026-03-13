<?php
/**
 * Message Service for NexGen Telegram Chat
 * Handles message database operations (CRUD)
 *
 * @package NexGenTelegramChat
 * @subpackage Message
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NexGen_Message_Service {

	const TABLE_NAME_SUFFIX = 'nexgen_chat_messages';

	/**
	 * Get database table name
	 * 
	 * Constructs the full table name with WordPress prefix.
	 * Table name: wp_nexgen_chat_messages (or custom prefix).
	 *
	 * @return string Full table name with WordPress prefix.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME_SUFFIX;
	}

	/**
	 * Create messages table if it doesn't exist
	 *
	 * Called during plugin activation. Creates table with proper indexes
	 * for performance and calls upgrade_table_schema() for compatibility.
	 *
	 * Table schema:
	 * - id: auto-increment primary key
	 * - session_id: indexed (for polling queries)
	 * - created_at: indexed (for ordering)
	 * - message_text: full message content
	 * - message_type: 'user', 'bot', 'system'
	 * - sender_info: JSON with ip, user_agent, page, name
	 * - ip_hash: SHA256 hash of IP (GDPR compliant)
	 *
	 * @return bool True on success (always returns true for compatibility).
	 */
	public static function create_table(): bool {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			session_id varchar(100) NOT NULL,
			message_text text NOT NULL,
			message_type varchar(20) NOT NULL DEFAULT 'user',
			sender_info json,
			telegram_message_id bigint(20),
			ip_hash varchar(64),
			is_suspicious tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY created_at (created_at),
			KEY ip_hash (ip_hash),
			KEY message_type (message_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Upgrade existing table schema if needed
		self::upgrade_table_schema();

		return true;
	}

	/**
	 * Upgrade table schema to add missing columns
	 * This handles tables created with older schema versions
	 *
	 * @return void
	 */
	private static function upgrade_table_schema() {
		global $wpdb;

		$table_name = self::get_table_name();

		// Check and add ip_hash column if missing
		$ip_hash_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM $table_name LIKE %s",
				'ip_hash'
			)
		);

		if ( empty( $ip_hash_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				"ALTER TABLE $table_name ADD COLUMN ip_hash varchar(64) AFTER telegram_message_id"
			);
		}

		// Check and add is_suspicious column if missing
		$suspicious_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM $table_name LIKE %s",
				'is_suspicious'
			)
		);

		if ( empty( $suspicious_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				"ALTER TABLE $table_name ADD COLUMN is_suspicious tinyint(1) DEFAULT 0 AFTER ip_hash"
			);
		}

		// Check and add sender_info column if missing
		$sender_info_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM $table_name LIKE %s",
				'sender_info'
			)
		);

		if ( empty( $sender_info_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				"ALTER TABLE $table_name ADD COLUMN sender_info json AFTER message_type"
			);
		}
	}

	/**
	 * Save a message to database
	 * 
	 * Validates input, hashes IP, and persists message with sender metadata.
	 * This is the primary method for storing messages in the chat system.
	 *
	 * @param string      $session_id Session ID (format: chat_<slug>_<6-hex>).
	 * @param string      $message Message text (max 1000 chars after validation).
	 * @param string      $type Message type: 'user', 'bot', or 'system'.
	 * @param int|null    $telegram_message_id Optional Telegram message ID for linking.
	 * @param array       $sender_info Optional sender metadata (ip, user_agent, page, name).
	 * 
	 * @return int Insert ID on success.
	 * @throws Exception If session_id/message empty, invalid type, or DB insert fails.
	 * 
	 * @example
	 * try {
	 *   $msg_id = NexGen_Message_Service::save_message(
	 *     'chat_mysite_abc123',
	 *     'How much does the plan cost?',
	 *     'user',
	 *     null,
	 *     ['name' => 'John', 'ip' => '192.168.1.1']
	 *   );
	 * } catch (Exception $e) {
	 *   NexGen_Security::log_event('message_save_error', ['error' => $e->getMessage()]);
	 * }
	 */
	public static function save_message(
		string $session_id,
		string $message,
		string $type = 'user',
		?int $telegram_message_id = null,
		array $sender_info = []
	): int {
		global $wpdb;

		// Validate input
		if ( empty( $session_id ) || empty( $message ) ) {
			throw new Exception( 'Session ID and message are required' );
		}

		if ( ! in_array( $type, [ 'user', 'bot', 'system' ], true ) ) {
			throw new Exception( 'Invalid message type: ' . esc_attr( $type ) );
		}

		$table_name = self::get_table_name();

		// Prepare sender info
		if ( empty( $sender_info ) ) {
			$sender_info = self::get_default_sender_info();
		}

		// Hash IP for privacy
		if ( isset( $sender_info['ip'] ) ) {
			$sender_info['ip_hash'] = NexGen_Security::hash_ip( $sender_info['ip'] );
		}

		// Insert
		$result = $wpdb->insert(
			$table_name,
			[
				'session_id'           => $session_id,
				'message_text'         => $message,
				'message_type'         => $type,
				'sender_info'          => wp_json_encode( $sender_info ),
				'telegram_message_id'  => $telegram_message_id,
				'ip_hash'              => isset( $sender_info['ip_hash'] ) ? $sender_info['ip_hash'] : null,
				'created_at'           => current_time( 'mysql' ),
			],
			[
				'%s', // session_id
				'%s', // message_text
				'%s', // message_type
				'%s', // sender_info
				'%d', // telegram_message_id
				'%s', // ip_hash
				'%s', // created_at
			]
		);

		if ( ! $result ) {
			throw new Exception( 'Failed to save message: ' . $wpdb->last_error );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get default sender information
	 * 
	 * Collects client IP, user agent, referrer page, and chat name.
	 * Called when sender_info array is empty or incomplete.
	 *
	 * @return array Sender data with keys: ip, user_agent, page, name.
	 */
	private static function get_default_sender_info(): array {
		return [
			'ip'         => NexGen_Security::get_user_ip(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
			'page'       => isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '',
			'name'       => NexGen_Session_Service::get_chat_name(),
		];
	}

	/**
	 * Get messages for a session (NEW messages for polling)
	 *
	 * Used by frontend polling to get only new messages since last check.
	 * Returns messages in ascending order (oldest first) for chronological display.
	 *
	 * @param string $session_id Session ID (format: chat_<slug>_<6-hex>).
	 * @param int    $after_message_id Return messages after this ID (default 0).
	 * @param int    $limit Maximum number of messages to return (default 50).
	 * 
	 * @return array Array of message objects with: id, text, type, time. Empty if no messages.
	 */
	public static function get_messages( string $session_id, int $after_message_id = 0, int $limit = 50 ): array {
		global $wpdb;

		if ( empty( $session_id ) ) {
			return [];
		}

		$table_name = self::get_table_name();

		$query = $wpdb->prepare(
			"SELECT id, message_text, message_type, created_at 
			 FROM $table_name
			 WHERE session_id = %s AND id > %d
			 ORDER BY created_at ASC
			 LIMIT %d",
			$session_id,
			$after_message_id,
			$limit
		);

		$messages = $wpdb->get_results( $query );

		if ( is_null( $messages ) ) {
			return [];
		}

		// Format response
		return array_map(
			function( $msg ) {
				return [
					'id'   => (int) $msg->id,
					'text' => NexGen_Security::escape_message( $msg->message_text ),
					'type' => $msg->message_type,
					'time' => date( 'H:i', strtotime( $msg->created_at ) ),
				];
			},
			$messages
		);
	}

	/**
	 * Get single message by ID
	 *
	 * @param int $message_id Message ID.
	 * @return object|null Message object or null.
	 */
	public static function get_message( $message_id ) {
		global $wpdb;

		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$message_id
			)
		);
	}

	/**
	 * Get conversation history for a session
	 *
	 * @param string $session_id Session ID.
	 * @param int    $limit Maximum messages.
	 * @return array Array of messages.
	 */
	public static function get_conversation( $session_id, $limit = 100 ) {
		return self::get_messages( $session_id, 0, $limit );
	}

	/**
	 * Delete messages for a session (admin only)
	 *
	 * @param string $session_id Session ID.
	 * @return int Number of deleted rows.
	 */
	public static function delete_session_messages( $session_id ) {
		global $wpdb;

		$table_name = self::get_table_name();

		return $wpdb->delete(
			$table_name,
			[ 'session_id' => $session_id ],
			[ '%s' ]
		);
	}

	/**
	 * Mark message as suspicious (for admin review)
	 *
	 * @param int $message_id Message ID.
	 * @param int $is_suspicious 1 or 0.
	 * @return bool True on success.
	 */
	public static function mark_suspicious( $message_id, $is_suspicious = 1 ) {
		global $wpdb;

		$table_name = self::get_table_name();

		return $wpdb->update(
			$table_name,
			[ 'is_suspicious' => $is_suspicious ],
			[ 'id' => $message_id ],
			[ '%d' ],
			[ '%d' ]
		) !== false;
	}

	/**
	 * Get message count for a session
	 * 
	 * Returns total number of messages in a conversation.
	 * Used for statistics and UX features (e.g., "40 messages in this chat").
	 *
	 * @param string $session_id Session ID (format: chat_<slug>_<6-hex>).
	 * @return int Message count (0 if session has no messages).
	 */
	public static function get_message_count( string $session_id ): int {
		global $wpdb;

		$table_name = self::get_table_name();

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE session_id = %s",
				$session_id
			)
		);

		return (int) $count;
	}

	/**
	 * Find message by Telegram message ID
	 * 
	 * Links Telegram webhook replies back to original session.
	 * Used in webhook handler to extract session_id from Telegram message thread.
	 *
	 * @param int $telegram_message_id Telegram message ID from webhooks.
	 * @return object|null Message object if found, null otherwise.
	 */
	public static function get_by_telegram_id( int $telegram_message_id ): ?object {
		global $wpdb;

		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE telegram_message_id = %d",
				$telegram_message_id
			)
		);
	}

	/**
	 * Get conversation optimized - for Phase 5 (lightweight history)
	 * 
	 * Retrieves paginated history without loading entire chat.
	 * Used by infinite scroll and for building Gemini context.
	 * Returns messages in chronological order (oldest first).
	 *
	 * Performance: less than 50ms for 10 messages (with DB indexes).
	 *
	 * @param string $session_id Session ID (format: chat_<slug>_<6-hex>).
	 * @param int    $limit Number of messages to retrieve (default 10, max 20).
	 * @param int    $offset Pagination offset (default 0).
	 * 
	 * @return array Array of message objects with: id, text, type, time, sender.
	 */
	public static function get_conversation_optimized( string $session_id, int $limit = 10, int $offset = 0 ): array {
		global $wpdb;

		if ( empty( $session_id ) ) {
			return [];
		}

		$table_name = self::get_table_name();

		// Get latest messages (descending order)
		$query = $wpdb->prepare(
			"SELECT id, message_text, message_type, created_at, sender_info
			 FROM $table_name
			 WHERE session_id = %s
			 ORDER BY created_at DESC
			 LIMIT %d OFFSET %d",
			$session_id,
			$limit,
			$offset
		);

		$messages = $wpdb->get_results( $query );

		if ( is_null( $messages ) ) {
			return [];
		}

		// Reverse to display chronologically (oldest first)
		$messages = array_reverse( $messages );

		// Format response
		return array_map(
			function( $msg ) {
				$sender_info = ! empty( $msg->sender_info ) ? json_decode( $msg->sender_info, true ) : [];
				return [
					'id'     => (int) $msg->id,
					'text'   => NexGen_Security::escape_message( $msg->message_text ),
					'type'   => $msg->message_type,
					'time'   => date( 'H:i', strtotime( $msg->created_at ) ),
					'sender' => $sender_info['name'] ?? 'Usuario',
				];
			},
			$messages
		);
	}
}
