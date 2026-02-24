<?php
/**
 * Context Service for NexGen Telegram Chat
 * Gathers WordPress data (services, pricing, company info) to provide context to LLM
 *
 * @package NexGenTelegramChat
 * @subpackage Context
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NexGen_Context_Service {

	/**
	 * Get complete context for LLM (Gemini, GPT, etc)
	 *
	 * @return array Context data for AI assistant.
	 */
	public static function get_context() {
		return [
			'company'    => self::get_company_info(),
			'services'   => self::get_services(),
			'pricing'    => self::get_pricing(),
			'portfolio'  => self::get_portfolio(),
			'faq'        => self::get_faq(),
			'contact'    => self::get_contact_info(),
			'timestamp'  => current_time( 'mysql' ),
		];
	}

	/**
	 * Get company information
	 *
	 * @return array Company data.
	 */
	public static function get_company_info() {
		return [
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => get_bloginfo( 'url' ),
			'language'    => get_bloginfo( 'language' ),
			'phone'       => get_option( 'nexgen_company_phone', '' ),
			'email'       => get_bloginfo( 'admin_email' ),
			'address'     => get_option( 'nexgen_company_address', '' ),
			'timezone'    => wp_timezone_string(),
		];
	}

	/**
	 * Get services offered
	 *
	 * @return array List of services.
	 */
	public static function get_services() {
		$services_json = get_option( 'nexgen_services', '[]' );
		$services      = json_decode( $services_json, true );

		if ( ! is_array( $services ) ) {
			return [];
		}

		return array_map(
			function( $service ) {
				return [
					'name'        => $service['name'] ?? '',
					'description' => $service['description'] ?? '',
					'price_from'  => $service['price_from'] ?? null,
					'price_to'    => $service['price_to'] ?? null,
					'duration'    => $service['duration'] ?? '',
					'features'    => $service['features'] ?? [],
				];
			},
			$services
		);
	}

	/**
	 * Get pricing information
	 *
	 * @return array Pricing tiers.
	 */
	public static function get_pricing() {
		$pricing_json = get_option( 'nexgen_pricing_tiers', '[]' );
		$pricing      = json_decode( $pricing_json, true );

		if ( ! is_array( $pricing ) ) {
			return [];
		}

		return array_map(
			function( $tier ) {
				return [
					'name'      => $tier['name'] ?? '',
					'price'     => $tier['price'] ?? 0,
					'currency'  => $tier['currency'] ?? 'USD',
					'period'    => $tier['period'] ?? 'month',
					'features'  => $tier['features'] ?? [],
					'best_for'  => $tier['best_for'] ?? '',
				];
			},
			$pricing
		);
	}

	/**
	 * Get portfolio/recent projects
	 *
	 * @return array Recent projects.
	 */
	public static function get_portfolio() {
		$args = [
			'post_type'      => 'post',
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [
				[
					'key'     => 'nexgen_featured_project',
					'value'   => '1',
					'compare' => '=',
				],
			],
		];

		$posts    = get_posts( $args );
		$portfolio = [];

		foreach ( $posts as $post ) {
			$portfolio[] = [
				'title'       => $post->post_title,
				'description' => wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ),
				'url'         => get_permalink( $post->ID ),
				'date'        => get_the_date( 'Y-m-d', $post->ID ),
				'client'      => get_post_meta( $post->ID, 'nexgen_project_client', true ),
				'result'      => get_post_meta( $post->ID, 'nexgen_project_result', true ),
			];
		}

		return $portfolio;
	}

	/**
	 * Get FAQ entries
	 *
	 * @return array FAQ entries.
	 */
	public static function get_faq() {
		$faq_json = get_option( 'nexgen_faq_entries', '[]' );
		$faq      = json_decode( $faq_json, true );

		if ( ! is_array( $faq ) ) {
			return [];
		}

		return array_map(
			function( $entry ) {
				return [
					'question' => $entry['question'] ?? '',
					'answer'   => $entry['answer'] ?? '',
					'category' => $entry['category'] ?? 'General',
				];
			},
			array_slice( $faq, 0, 10 ) // Limit to 10 FAQ entries to avoid token overflow
		);
	}

	/**
	 * Get contact information
	 *
	 * @return array Contact details.
	 */
	public static function get_contact_info() {
		return [
			'email'       => get_bloginfo( 'admin_email' ),
			'phone'       => get_option( 'nexgen_company_phone', '' ),
			'address'     => get_option( 'nexgen_company_address', '' ),
			'business_hours' => get_option( 'nexgen_business_hours', 'Monday-Friday 9AM-6PM' ),
			'support_url' => get_option( 'nexgen_support_url', '' ),
		];
	}

	/**
	 * Extract lead information from conversation
	 *
	 * @param string $message User message to analyze.
	 * @return array Extracted lead data.
	 */
	public static function extract_lead_info( $message ) {
		$lead_data = [
			'contact_email'      => null,
			'contact_phone'      => null,
			'interest_service'   => null,
			'interest_budget'    => null,
			'urgency_level'      => 'normal',
			'extracted_keywords' => [],
		];

		// Extract email
		if ( preg_match( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $message, $matches ) ) {
			$lead_data['contact_email'] = $matches[0];
		}

		// Extract phone (international format)
		if ( preg_match( '/[\+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,9}/', $message, $matches ) ) {
			$lead_data['contact_phone'] = $matches[0];
		}

		// Extract budget mentions
		if ( preg_match( '/(\$|\€|USD|EUR|CNY|MXN)?\s*([0-9,]+)/i', $message, $matches ) ) {
			$lead_data['interest_budget'] = $matches[0];
		}

		// Extract urgency signals
		$urgency_keywords = [ 'asap', 'urgent', 'hoy', 'ahora', 'inmediato', 'rápido' ];
		foreach ( $urgency_keywords as $keyword ) {
			if ( stripos( $message, $keyword ) !== false ) {
				$lead_data['urgency_level'] = 'high';
				break;
			}
		}

		return $lead_data;
	}

	/**
	 * Calculate lead quality score based on extracted data and conversation
	 *
	 * @param array $lead_data Extracted lead information.
	 * @param string $message User message.
	 * @return int Score from 0-100.
	 */
	public static function calculate_lead_quality( $lead_data, $message ) {
		$score = 0;

		// Contact info (35 points)
		if ( ! empty( $lead_data['contact_email'] ) ) {
			$score += 20;
		}
		if ( ! empty( $lead_data['contact_phone'] ) ) {
			$score += 15;
		}

		// Interest signals (30 points)
		if ( ! empty( $lead_data['interest_service'] ) ) {
			$score += 20;
		}
		if ( ! empty( $lead_data['interest_budget'] ) ) {
			$score += 10;
		}

		// Urgency (20 points)
		if ( 'high' === $lead_data['urgency_level'] ) {
			$score += 20;
		}

		// Message length (quality of inquiry)
		if ( strlen( $message ) > 50 ) {
			$score += 15;
		}

		return min( $score, 100 );
	}

	/**
	 * Build system prompt for Gemini with context
	 *
	 * @param array $context The context data.
	 * @param string $language Response language (default 'es' for Spanish).
	 * @return string System prompt for LLM.
	 */
	public static function build_system_prompt( $context, $language = 'es' ) {
		$company = $context['company'];
		$services = $context['services'];
		$pricing = $context['pricing'];

		// Build service descriptions
		$services_text = '';
		foreach ( $services as $service ) {
			$services_text .= "- {$service['name']}: {$service['description']}\n";
		}

		// Build pricing info
		$pricing_text = '';
		foreach ( $pricing as $tier ) {
			$pricing_text .= "- {$tier['name']}: {$tier['currency']} {$tier['price']}/{$tier['period']}\n";
		}

		$lang_text = ( 'es' === $language ) ? 'Spanish' : 'English';

		$prompt = <<<PROMPT
You are a professional sales assistant for {$company['name']}.

**Company Information:**
- Website: {$company['url']}
- Email: {$company['email']}
- Phone: {$company['phone']}
- Address: {$company['address']}

**Services Offered:**
$services_text

**Pricing:**
$pricing_text

**Guidelines:**
1. Respond in $lang_text - Be professional and friendly
2. Always address customer needs based on their questions
3. Recommend appropriate services and pricing tiers
4. If unable to answer, suggest scheduling a consultation
5. Collect contact information naturally in conversation
6. Recommend follow-up only when truly relevant

**Tone:**
- Conversational but professional
- Focused on solving customer problems
- Honest about capabilities and timeline

When the customer provides contact info (email/phone), acknowledge it and ask if they'd like to schedule a demo or consultation.
PROMPT;

		return $prompt;
	}
}
