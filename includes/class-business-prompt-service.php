<?php
/**
 * Business Prompt Service for NexGen Telegram Chat
 * Construye prompts dinámicos con contexto del negocio
 *
 * @package NexGenTelegramChat
 * @subpackage Prompts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NexGen_Business_Prompt_Service {

	/**
	 * Build system prompt with business context
	 *
	 * @param array $options Additional options
	 * @return string System prompt for LLM
	 */
	public static function build_system_prompt( $options = [] ) {
		$business_name    = get_option( 'blogname', 'Our Business' );
		$business_desc    = get_option( 'blogdescription', '' );
		$custom_context   = get_option( 'nexgen_business_context', '' );
		$business_type    = get_option( 'nexgen_business_type', 'general' );
		$supported_topics = self::get_supported_topics();

		$prompt = "You are a professional support chatbot for {$business_name}.";
		$prompt .= "\n\nBusiness Description: {$business_desc}";

		if ( ! empty( $custom_context ) ) {
			$prompt .= "\n\nAdditional Context:\n{$custom_context}";
		}

		$prompt .= "\n\nYou ONLY answer questions related to: " . implode( ', ', $supported_topics ) . ".";
		$prompt .= "\nIf a question is outside these topics, politely decline and suggest contacting support.";
		$prompt .= "\n\nBe helpful, professional, and concise. Keep responses under 3 sentences.";
		$prompt .= "\nUse the user's language for responses.";

		/**
		 * Filter: Allow customization of system prompt
		 *
		 * @param string $prompt The system prompt
		 * @param array  $options Options passed to builder
		 */
		return apply_filters( 'nexgen_system_prompt', $prompt, $options );
	}

	/**
	 * Build prompt for specific topic
	 *
	 * @param string $topic Topic name (pricing, features, support, etc.)
	 * @param array  $options Additional options
	 * @return string System prompt
	 */
	public static function build_topic_prompt( $topic, $options = [] ) {
		$base_prompt = self::build_system_prompt( $options );

		$topic_contexts = [
			'pricing'  => "You are an expert in pricing and billing. Provide clear pricing information. If asked about discounts, mention contacting sales.",
			'features' => "You are knowledgeable about all product features. Explain features clearly with examples. Suggest relevant features based on user needs.",
			'support'  => "You are a technical support specialist. Help troubleshoot issues. If unsolvable, offer to escalate to our team.",
			'legal'    => "You provide information about terms, privacy, and legal policies. Be precise and reference specific clauses.",
		];

		if ( isset( $topic_contexts[ $topic ] ) ) {
			$base_prompt .= "\n\nFocus: " . $topic_contexts[ $topic ];
		}

		return $base_prompt;
	}

	/**
	 * Get supported topics for this business
	 *
	 * @return array Topics
	 */
	public static function get_supported_topics() {
		$topics = get_option( 'nexgen_supported_topics', [] );

		if ( empty( $topics ) ) {
			// Default topics
			$topics = [
				'Product information',
				'Pricing and billing',
				'Features',
				'General support',
				'Account management',
			];
		}

		return apply_filters( 'nexgen_supported_topics', $topics );
	}

	/**
	 * Check if message topic is within supported scope
	 *
	 * @param string $message User message
	 * @return array ['in_scope' => bool, 'confidence' => float (0-1), 'topic' => string|null]
	 */
	public static function validate_scope( $message ) {
		$topics          = self::get_supported_topics();
		$custom_keywords = get_option( 'nexgen_scope_keywords', '' );

		// Parse custom keywords
		$keywords = array_filter(
			array_map( 'trim', explode( "\n", $custom_keywords ) )
		);

		$all_scope_triggers = array_merge( $topics, $keywords );

		// Simple keyword matching (can be enhanced with ML later)
		$message_lower = strtolower( $message );
		$matches       = [];

		foreach ( $all_scope_triggers as $trigger ) {
			$trigger_lower = strtolower( $trigger );
			if ( stripos( $message_lower, $trigger_lower ) !== false ) {
				$matches[] = $trigger;
			}
		}

		$in_scope   = ! empty( $matches );
		$confidence = min( count( $matches ) * 0.3, 1.0 ); // Each match adds 30% confidence

		return [
			'in_scope'   => $in_scope,
			'confidence' => $confidence,
			'matches'    => $matches,
		];
	}

	/**
	 * Get rejection message for out-of-scope questions
	 *
	 * @return string Rejection message
	 */
	public static function get_rejection_message() {
		$business_name = get_option( 'blogname', 'Our Business' );
		$custom_rejection = get_option( 'nexgen_rejection_message', '' );

		if ( ! empty( $custom_rejection ) ) {
			return $custom_rejection;
		}

		return "I appreciate your question, but I'm specifically designed to help with questions about {$business_name}. " .
		       "Please feel free to ask about our products, pricing, features, or account support. " .
		       "For other inquiries, please contact our team directly.";
	}

	/**
	 * Build prompt for lead detection
	 *
	 * @return string Prompt for lead detection
	 */
	public static function build_lead_detection_prompt() {
		return "If the user expresses interest in contacting support, scheduling a meeting, or acquiring a lead form, classify as LEAD_INTENT. " .
		       "Otherwise classify as GENERAL. Return only: LEAD_INTENT or GENERAL.";
	}

	/**
	 * Extract lead data from message using LLM
	 *
	 * @param string $message User message
	 * @param string $visitor_name Visitor name
	 * @param string $session_id Session ID
	 * @return array ['name' => string, 'email' => string|null, 'phone' => string|null, 'interest' => string]
	 */
	public static function extract_lead_data( $message, $visitor_name = '', $session_id = '' ) {
		$extraction_prompt = "Extract lead information from this message. Return JSON with: " .
		                     "{\"name\": \"str\", \"email\": \"str|null\", \"phone\": \"str|null\", \"interest\": \"str\"}. " .
		                     "Message: {$message}";

		$response = NexGen_LLM_Service::query(
			message: $extraction_prompt,
			system_prompt: "You are a data extraction specialist. Extract contact information accurately."
		);

		if ( ! $response['success'] ) {
			// Fallback to basic extraction
			return [
				'name'     => $visitor_name,
				'email'    => self::extract_email( $message ),
				'phone'    => self::extract_phone( $message ),
				'interest' => substr( $message, 0, 200 ),
			];
		}

		try {
			$data = json_decode( $response['response'], true );
			return [
				'name'     => $data['name'] ?? $visitor_name,
				'email'    => $data['email'] ?? self::extract_email( $message ),
				'phone'    => $data['phone'] ?? self::extract_phone( $message ),
				'interest' => $data['interest'] ?? substr( $message, 0, 200 ),
			];
		} catch ( Exception $e ) {
			// Fallback
			return [
				'name'     => $visitor_name,
				'email'    => self::extract_email( $message ),
				'phone'    => self::extract_phone( $message ),
				'interest' => substr( $message, 0, 200 ),
			];
		}
	}

	/**
	 * Simple email extraction regex
	 *
	 * @param string $text Text to search
	 * @return string|null Email or null
	 */
	private static function extract_email( $text ) {
		if ( preg_match( '/([a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $matches ) ) {
			return $matches[1];
		}
		return null;
	}

	/**
	 * Simple phone extraction regex
	 *
	 * @param string $text Text to search
	 * @return string|null Phone or null
	 */
	private static function extract_phone( $text ) {
		// Matches various phone formats: +1234567890, (123) 456-7890, 123-456-7890, etc.
		if ( preg_match( '/(\+?1?\s?[-.\s]?\(?[0-9]{3}\)?[-.\s]?[0-9]{3}[-.\s]?[0-9]{4})/', $text, $matches ) ) {
			return $matches[1];
		}
		return null;
	}

	/**
	 * Check if message matches lead keywords
	 *
	 * @param string $message User message
	 * @return bool True if message indicates lead intent
	 */
	public static function detect_lead_intent( $message ) {
		$lead_keywords = get_option( 'nexgen_lead_keywords', "contact\nmeeting\nschedule\nagendar\nquiero contactar\nquiero hablar" );

		$keywords = array_filter(
			array_map( 'trim', explode( "\n", $lead_keywords ) )
		);

		$message_lower = strtolower( $message );

		foreach ( $keywords as $keyword ) {
			if ( stripos( $message_lower, strtolower( trim( $keyword ) ) ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
