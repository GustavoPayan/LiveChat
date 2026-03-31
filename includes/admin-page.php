<?php
/**
 * Admin Page Template for NexGen Telegram Chat - TABBED VERSION
 *
 * @package NexGenTelegramChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Variables from render_admin_page():
// $bot_token, $chat_id, $is_configured, $webhook_url
?>

<div class="wrap">
	<h1>🚀 NexGen Telegram Chat</h1>
	<p>Este Plugin permite usar Telegram como sistema de chat en vivo integrado con tu sitio WordPress. Responde mensajes directamente desde Telegram o usa automatizaciones N8N.</p>

	<?php if ( $is_configured ) : ?>
		<div class="notice notice-success">
			<p>✅ Bot configurado correctamente.</p>
			<button type="button" class="button" onclick="testTelegramConnection(event)">Enviar mensaje de prueba</button>
			<button type="button" class="button button-primary" onclick="setupWebhook(event)">Configurar Webhook</button>
		</div>
	<?php else : ?>
		<div class="notice notice-warning">
			<p>⚠️ Bot no configurado. Completa los campos Token y Chat ID para activar el chat.</p>
		</div>
	<?php endif; ?>

	<!-- Tabs Navigation -->
	<div class="nexgen-tabs">
		<button class="nexgen-tab-button active" onclick="switchTab(event, 'tab-telegram')">🤖 Telegram</button>
		<button class="nexgen-tab-button" onclick="switchTab(event, 'tab-chat')">💬 Chat UI</button>
		<button class="nexgen-tab-button" onclick="switchTab(event, 'tab-appearance')">🎨 Apariencia</button>
		<button class="nexgen-tab-button" onclick="switchTab(event, 'tab-llm')">⚡ IA/LLM</button>
		<button class="nexgen-tab-button" onclick="switchTab(event, 'tab-n8n')">🧠 N8N</button>
		<button class="nexgen-tab-button" onclick="switchTab(event, 'tab-webhook')">🔗 Webhook</button>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'nexgen_chat_settings' ); ?>

		<!-- TAB 1: Telegram Configuration -->
		<div id="tab-telegram" class="nexgen-tab-content active">
			<h2>🤖 Configuración de Telegram</h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="nexgen_bot_token">Token del Bot de Telegram</label></th>
				<td>
					<input type="text" name="nexgen_bot_token" id="nexgen_bot_token" value="<?php echo esc_attr( $bot_token ); ?>" class="regular-text" placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz" />
					<p class="description">Obtén tu token desde @BotFather en Telegram</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_chat_id">Chat ID de Telegram</label></th>
				<td>
					<input type="text" name="nexgen_chat_id" id="nexgen_chat_id" value="<?php echo esc_attr( $chat_id ); ?>" class="regular-text" placeholder="123456789" />
					<p class="description">ID del chat donde recibirás los mensajes. Obtén tu ID desde @userinfobot</p>
				</td>
			</tr>
		</table>
		</div>

		<!-- TAB 2: Chat UI Configuration -->
		<div id="tab-chat" class="nexgen-tab-content">
			<h2>💬 Configuración del Chat</h2>
			<table class="form-table">
			<tr>
				<th scope="row"><label for="nexgen_chat_title">Título del Chat</label></th>
				<td>
					<input type="text" name="nexgen_chat_title" id="nexgen_chat_title" value="<?php echo esc_attr( get_option( 'nexgen_chat_title', 'Chat de Soporte' ) ); ?>" class="regular-text" />
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_welcome_message">Mensaje de Bienvenida</label></th>
				<td>
					<textarea name="nexgen_welcome_message" id="nexgen_welcome_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'nexgen_welcome_message', '¡Hola! ¿En qué puedo ayudarte?' ) ); ?></textarea>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_error_message">Mensaje de Error</label></th>
				<td>
					<textarea name="nexgen_error_message" id="nexgen_error_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'nexgen_error_message', 'Lo siento, ha ocurrido un error. Inténtalo de nuevo.' ) ); ?></textarea>
				</td>
			</tr>
		</table>
		</div>

		<!-- TAB 3: Appearance -->
		<div id="tab-appearance" class="nexgen-tab-content">
			<h2>🎨 Personalización Visual</h2>
			<table class="form-table">
			<tr>
				<th scope="row"><label for="nexgen_primary_color">Color Primario</label></th>
				<td>
					<input type="color" name="nexgen_primary_color" id="nexgen_primary_color" value="<?php echo esc_attr( get_option( 'nexgen_primary_color', '#007cba' ) ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_secondary_color">Color Secundario</label></th>
				<td>
					<input type="color" name="nexgen_secondary_color" id="nexgen_secondary_color" value="<?php echo esc_attr( get_option( 'nexgen_secondary_color', '#00ba7c' ) ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_background_color">Color de Fondo</label></th>
				<td>
					<input type="color" name="nexgen_background_color" id="nexgen_background_color" value="<?php echo esc_attr( get_option( 'nexgen_background_color', '#ffffff' ) ); ?>" />
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_chat_position">Posición del Chat</label></th>
				<td>
					<select name="nexgen_chat_position" id="nexgen_chat_position">
						<option value="bottom-right" <?php selected( get_option( 'nexgen_chat_position', 'bottom-right' ), 'bottom-right' ); ?>>Abajo a la derecha</option>
						<option value="bottom-left" <?php selected( get_option( 'nexgen_chat_position', 'bottom-right' ), 'bottom-left' ); ?>>Abajo a la izquierda</option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_auto_open_delay">Abrir automáticamente después de (segundos)</label></th>
				<td>
					<input type="number" name="nexgen_auto_open_delay" id="nexgen_auto_open_delay" value="<?php echo esc_attr( get_option( 'nexgen_auto_open_delay', 0 ) ); ?>" min="0" max="60" />
					<p class="description">0 = no abrir automáticamente</p>
				</td>
			</tr>
		</table>
		</div>

		<!-- TAB 4: LLM/IA Hybrid Routing -->
		<div id="tab-llm" class="nexgen-tab-content">
			<h2>⚡ Configuración de IA/LLM (Hybrid Routing)</h2>
			<p style="background: #e8f5ff; padding: 15px; border-left: 4px solid #0078d4; border-radius: 4px; margin-bottom: 20px;">
				<strong>🚀 Sistema Híbrido:</strong> 80% LLM directo (rápido), 15% N8N (leads), 5% Soporte humano
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="nexgen_llm_enabled">Habilitar Routing LLM Híbrido</label></th>
					<td>
						<input type="checkbox" name="nexgen_llm_enabled" id="nexgen_llm_enabled" value="1" <?php checked( get_option( 'nexgen_llm_enabled', 1 ) ); ?> />
						<p class="description">✅ Activa el nuevo sistema inteligente de enrutamiento</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="nexgen_llm_provider">Proveedor de IA</label></th>
					<td>
						<select name="nexgen_llm_provider" id="nexgen_llm_provider">
							<option value="openai" <?php selected( get_option( 'nexgen_llm_provider', 'openai' ), 'openai' ); ?>>OpenAI (GPT-4o-mini) - Recomendado</option>
							<option value="claude" <?php selected( get_option( 'nexgen_llm_provider', 'openai' ), 'claude' ); ?>>Claude 3.5 Haiku - Análisis avanzado</option>
							<option value="gemini" <?php selected( get_option( 'nexgen_llm_provider', 'openai' ), 'gemini' ); ?>>Google Gemini 2.0 - Más rápido</option>
						</select>
						<p class="description">Elige el proveedor de IA. OpenAI es recomendado.</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="nexgen_llm_api_key">Clave API del Proveedor IA</label></th>
					<td>
						<input type="password" name="nexgen_llm_api_key" id="nexgen_llm_api_key" value="<?php echo esc_attr( get_option( 'nexgen_llm_api_key' ) ); ?>" class="regular-text" placeholder="sk-... (OpenAI) o api-key (Claude/Gemini)" />
						<p class="description">🔐 Clave API segura. O define NEXGEN_LLM_API_KEY en wp-config.php</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="nexgen_business_context">Contexto de Tu Negocio</label></th>
					<td>
						<textarea name="nexgen_business_context" id="nexgen_business_context" rows="6" class="large-text" placeholder="Describe tu negocio, productos, servicios..."><?php echo esc_textarea( get_option( 'nexgen_business_context' ) ); ?></textarea>
						<p class="description">📝 Se incluye en cada prompt de IA</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="nexgen_business_type">Tipo de Negocio</label></th>
					<td>
						<select name="nexgen_business_type" id="nexgen_business_type">
							<option value="general" <?php selected( get_option( 'nexgen_business_type', 'general' ), 'general' ); ?>>General</option>
							<option value="saas" <?php selected( get_option( 'nexgen_business_type', 'general' ), 'saas' ); ?>>SaaS / Software</option>
							<option value="ecommerce" <?php selected( get_option( 'nexgen_business_type', 'general' ), 'ecommerce' ); ?>>E-commerce</option>
							<option value="service" <?php selected( get_option( 'nexgen_business_type', 'general' ), 'service' ); ?>>Servicios</option>
						</select>
						<p class="description">Optimiza respuestas de IA según tu tipo de negocio</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="nexgen_supported_topics">Temas Soportados</label></th>
					<td>
						<textarea name="nexgen_supported_topics" id="nexgen_supported_topics" rows="5" class="large-text" placeholder="Información de productos&#10;Precios&#10;Características&#10;Soporte técnico&#10;Cuenta"><?php echo esc_textarea( implode( "\n", (array) get_option( 'nexgen_supported_topics', ['Información del producto', 'Precios', 'Características'] ) ) ); ?></textarea>
						<p class="description">📋 Uno por línea. Temas que tu IA puede responder.</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="nexgen_scope_keywords">Palabras Clave de Validación</label></th>
					<td>
						<textarea name="nexgen_scope_keywords" id="nexgen_scope_keywords" rows="4" class="large-text" placeholder="precio&#10;características&#10;soporte&#10;producto"><?php echo esc_textarea( get_option( 'nexgen_scope_keywords' ) ); ?></textarea>
						<p class="description">🔍 Palabras clave para detectar si el mensaje es relevante</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="nexgen_lead_keywords">Palabras Clave para Leads</label></th>
					<td>
						<textarea name="nexgen_lead_keywords" id="nexgen_lead_keywords" rows="4" class="large-text" placeholder="contacto&#10;reunión&#10;agendar&#10;demo"><?php echo esc_textarea( get_option( 'nexgen_lead_keywords' ) ); ?></textarea>
						<p class="description">🎯 Detecta cuando el usuario quiere contactar</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="nexgen_rejection_message">Mensaje de Rechazo (Out-of-Scope)</label></th>
					<td>
						<textarea name="nexgen_rejection_message" id="nexgen_rejection_message" rows="3" class="large-text" placeholder="Aprecio tu pregunta, pero ese tema está fuera..."><?php echo esc_textarea( get_option( 'nexgen_rejection_message' ) ); ?></textarea>
						<p class="description">💬 Respuesta cuando la pregunta está fuera de alcance</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="nexgen_llm_temperature">Temperatura (Creatividad)</label></th>
					<td>
						<input type="range" name="nexgen_llm_temperature" id="nexgen_llm_temperature" min="0" max="1" step="0.1" value="<?php echo esc_attr( get_option( 'nexgen_llm_temperature', 0.7 ) ); ?>" style="width: 200px;" />
						<span id="temp-display" style="margin-left: 10px; font-weight: bold;"><?php echo esc_attr( get_option( 'nexgen_llm_temperature', 0.7 ) ); ?></span>
						<p class="description">🎯 0=preciso, 1=creativo. Recomendado: 0.7</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="nexgen_llm_max_tokens">Tokens Máximos</label></th>
					<td>
						<input type="number" name="nexgen_llm_max_tokens" id="nexgen_llm_max_tokens" value="<?php echo esc_attr( get_option( 'nexgen_llm_max_tokens', 500 ) ); ?>" min="50" max="2000" />
						<p class="description">📏 Límite de palabras por respuesta (default 500)</p>
					</td>
				</tr>
			</table>

			<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 20px 0;">
				<h4>🧪 Prueba de Conexión LLM</h4>
				<button type="button" class="button button-primary" onclick="testLLMConnection(event)">🧪 Probar Conexión LLM</button>
				<p class="description" style="margin-top: 10px;">Envía un mensaje de prueba a tu proveedor IA.</p>
			</div>

			<div class="notice notice-info" style="margin: 20px 0;">
				<p><strong>💡 Sistema Híbrido:</strong> 80% LLM (rápido), 15% N8N, 5% Humano | <strong>90% más barato</strong> vs N8N-only</p>
			</div>
		</div>

		<!-- TAB 5: N8N Automation -->
		<div id="tab-n8n" class="nexgen-tab-content">
			<h2>🤖 Automatización N8N</h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="nexgen_n8n_enabled">Habilitar N8N</label></th>
				<td>
					<input type="checkbox" name="nexgen_n8n_enabled" id="nexgen_n8n_enabled" value="1" <?php checked( get_option( 'nexgen_n8n_enabled' ) ); ?> />
					<p class="description">Activar automatización de respuestas mediante N8N</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_n8n_webhook_url">URL del Webhook N8N</label></th>
				<td>
					<input type="url" name="nexgen_n8n_webhook_url" id="nexgen_n8n_webhook_url" value="<?php echo esc_url( get_option( 'nexgen_n8n_webhook_url' ) ); ?>" class="regular-text" placeholder="https://n8n.example.com/webhook/chat" />
					<p class="description">URL completa del webhook N8N donde se procesarán las preguntas</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_n8n_api_key">Clave API N8N (Opcional)</label></th>
				<td>
					<input type="password" name="nexgen_n8n_api_key" id="nexgen_n8n_api_key" value="<?php echo esc_attr( get_option( 'nexgen_n8n_api_key' ) ); ?>" class="regular-text" placeholder="Tu clave API (se almacena encriptada)" />
					<p class="description">Clave API para autenticación en N8N (se almacena de forma segura)</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_n8n_timeout">Tiempo máximo de espera (segundos)</label></th>
				<td>
					<input type="number" name="nexgen_n8n_timeout" id="nexgen_n8n_timeout" value="<?php echo esc_attr( get_option( 'nexgen_n8n_timeout', 10 ) ); ?>" min="1" max="60" />
					<p class="description">Máximo tiempo a esperar respuesta de N8N (default: 10s)</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_ai_keywords">Palabras clave para IA</label></th>
				<td>
					<textarea name="nexgen_ai_keywords" id="nexgen_ai_keywords" rows="6" class="large-text" placeholder="hosting&#10;seo&#10;dominio&#10;precio&#10;desarrollo"><?php echo esc_textarea( get_option( 'nexgen_ai_keywords' ) ); ?></textarea>
					<p class="description">Una palabra clave por línea. Los mensajes que contengan estas palabras se envían a N8N en lugar de Telegram.</p>
				</td>
			</tr>
		</table>

		<div class="notice notice-info">
			<p><strong>⚙️ Cómo funciona:</strong></p>
			<p>Si un mensaje contiene una de las palabras clave y N8N está habilitado, se envía a N8N en lugar de a Telegram. Si N8N no responde en el tiempo límite, se redirige a Telegram automáticamente.</p>
		</div>

		<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 20px 0;">
			<h4>🧪 Prueba de Conexión N8N</h4>
			<button type="button" class="button button-primary" onclick="testN8NConnection(event)">🧪 Probar Conexión N8N</button>
			<p class="description" style="margin-top: 10px;">Envía un mensaje de prueba a tu webhook N8N para verificar que está funcionando.</p>
		</div>

		<hr style="margin: 30px 0;">

		<h4>📋 Estructura del Webhook N8N</h4>
		<p><strong>Petición (Request):</strong></p>
		<pre style="background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto;">
{
  "message": "¿Tienes hosting?",
  "session_id": "chat_juan_a1b2c3",
  "visitor": "Juan",
  "site": "Mi Sitio Web",
  "timestamp": "2026-02-24 10:30:00"
}
		</pre>

		<p><strong>Respuesta (Response - REQUERIDA):</strong></p>
		<pre style="background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto;">
{
  "response": "🌐 Ofrecemos hosting con SSL y soporte 24/7. ¿Cuál es tu presupuesto?"
}
		</pre>
		</div>

		<!-- TAB 5: Webhook Settings -->
		<div id="tab-webhook" class="nexgen-tab-content">
			<h2>🔗 Configuración del Webhook</h2>
			
			<div class="notice notice-info">
				<p><strong>📋 URL del Webhook de Telegram:</strong></p>
				<code style="background: #f0f0f0; padding: 10px; display: block; margin: 10px 0; word-break: break-all;"><?php echo esc_html( $webhook_url ); ?></code>
				<p>Haz clic en "Configurar Webhook" en la sección de configuración del bot para registrar esta URL en Telegram.</p>
			</div>

			<h3>💡 Cómo responder desde Telegram</h3>
			<div class="notice notice-success">
				<p><strong>Tienes 2 formas de responder:</strong></p>
				<ol>
					<li><strong>Responder al mensaje:</strong> Usa la función "Responder" de Telegram en el mensaje que recibiste</li>
					<li><strong>Comando /reply:</strong> Escribe <code>/reply session_id tu_respuesta</code></li>
				</ol>
				<p>💡 <strong>Tip:</strong> El ID de sesión aparece en cada mensaje que recibes (🔗 Sesión: chat_xxxx)</p>
			</div>

			<h3>📖 Instrucciones Completas</h3>
			<ol>
				<li><strong>Crear bot:</strong> Habla con @BotFather en Telegram y envía <code>/newbot</code></li>
				<li><strong>Obtener token:</strong> Copia el token que te da BotFather y pégalo en la pestaña "Telegram"</li>
				<li><strong>Obtener Chat ID:</strong> Envía un mensaje a @userinfobot para obtener tu ID</li>
				<li><strong>Configurar webhook:</strong> Haz clic en "Configurar Webhook" arriba</li>
				<li><strong>Probar:</strong> Envía un mensaje desde tu web y responde desde Telegram</li>
				<li><strong>Personalizar:</strong> Ajusta los colores y mensajes en la pestaña "Apariencia"</li>
			</ol>
		</div>

		<?php submit_button( 'Guardar cambios', 'primary', 'submit' ); ?>
	</form>
</div>

<style>
	.nexgen-tabs {
		display: flex;
		gap: 10px;
		margin: 20px 0;
		border-bottom: 2px solid #ccc;
		flex-wrap: wrap;
	}

	.nexgen-tab-button {
		padding: 12px 20px;
		background: #f0f0f0;
		border: 2px solid transparent;
		border-bottom: 3px solid transparent;
		cursor: pointer;
		font-size: 14px;
		font-weight: 500;
		transition: all 0.3s ease;
		color: #333;
	}

	.nexgen-tab-button:hover {
		background: #e0e0e0;
	}

	.nexgen-tab-button.active {
		background: #fff;
		border-bottom-color: #007cba;
		color: #007cba;
	}

	.nexgen-tab-content {
		display: none;
		padding: 20px 0;
		animation: fadeIn 0.3s ease;
	}

	.nexgen-tab-content.active {
		display: block;
	}

	@keyframes fadeIn {
		from {
			opacity: 0;
		}
		to {
			opacity: 1;
		}
	}
</style>

<script>
function switchTab(event, tabName) {
	event.preventDefault();

	// Hide all tabs
	const tabs = document.querySelectorAll('.nexgen-tab-content');
	tabs.forEach(tab => {
		tab.classList.remove('active');
	});

	// Remove active class from all buttons
	const buttons = document.querySelectorAll('.nexgen-tab-button');
	buttons.forEach(btn => {
		btn.classList.remove('active');
	});

	// Show selected tab
	document.getElementById(tabName).classList.add('active');

	// Mark button as active
	event.target.classList.add('active');

	// Save tab preference to localStorage
	localStorage.setItem('nexgen_active_tab', tabName);
}

// Restore last active tab on page load
document.addEventListener('DOMContentLoaded', function() {
	const lastTab = localStorage.getItem('nexgen_active_tab') || 'tab-telegram';
	const tabButton = document.querySelector(`button[onclick="switchTab(event, '${lastTab}')"]`);
	if (tabButton) {
		tabButton.click();
	}
});

function getAdminButton(event, defaultText) {
	const button = event?.target || document.activeElement;
	return {
		button: button && button.tagName === 'BUTTON' ? button : null,
		originalText: button && button.tagName === 'BUTTON' ? button.textContent : defaultText || ''
	};
}

async function parseJsonOrText(response) {
	const text = await response.text();
	try {
		return JSON.parse(text);
	} catch (err) {
		const cleaned = text.replace(/\s+/g, ' ').trim();
		throw new Error('Invalid JSON response from server: ' + cleaned);
	}
}

function testTelegramConnection(event) {
	const { button, originalText } = getAdminButton(event, 'Enviar...');
	if (button) {
		button.textContent = 'Enviando...';
		button.disabled = true;
	}

	fetch(ajaxurl, {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'action=nexgen_debug_test'
	})
	.then(parseJsonOrText)
	.then(data => alert((data.success ? '✅ ' : '❌ Error: ') + data.data))
	.catch(err => alert('❌ Error de conexión: ' + err.message))
	.finally(() => {
		if (button) {
			button.textContent = originalText;
			button.disabled = false;
		}
	});
}

function setupWebhook(event) {
	const { button, originalText } = getAdminButton(event, 'Configurando...');
	if (button) {
		button.textContent = 'Configurando...';
		button.disabled = true;
	}

	fetch(ajaxurl, {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'action=nexgen_set_webhook'
	})
	.then(parseJsonOrText)
	.then(data => {
		if (data.success) {
			alert('✅ ' + data.data + '\n\n🎉 ¡Ya puedes responder desde Telegram y se verá en el chat web!');
		} else {
			alert('❌ Error: ' + data.data);
		}
	})
	.catch(err => alert('❌ Error de conexión: ' + err.message))
	.finally(() => {
		if (button) {
			button.textContent = originalText;
			button.disabled = false;
		}
	});
}

function testN8NConnection(event) {
	const { button, originalText } = getAdminButton(event, 'Probando...');
	if (button) {
		button.textContent = 'Probando...';
		button.disabled = true;
	}

	fetch(ajaxurl, {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'action=nexgen_test_n8n'
	})
	.then(parseJsonOrText)
	.then(data => {
		if (data.success) {
			const response = data.data.response ? '\n\n📝 Respuesta de N8N:\n' + data.data.response : '';
			alert('✅ ' + data.data.message + response);
		} else {
			alert('❌ ' + data.data);
		}
	})
	.catch(err => alert('❌ Error de conexión: ' + err.message))
	.finally(() => {
		if (button) {
			button.textContent = originalText;
			button.disabled = false;
		}
	});
}

function testLLMConnection(event) {
	const { button, originalText } = getAdminButton(event, 'Probando...');
	if (button) {
		button.textContent = 'Probando...';
		button.disabled = true;
	}

	fetch(ajaxurl, {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'action=nexgen_test_llm'
	})
	.then(parseJsonOrText)
	.then(data => {
		if (data.success) {
			alert('✅ Conexión LLM exitosa!\n\n📝 Respuesta:\n' + data.data.response + '\n\n⏱️ Latencia: ' + data.data.latency_ms + 'ms\n💰 Costo: $' + data.data.cost.toFixed(4));
		} else {
			alert('❌ Error: ' + data.data);
		}
	})
	.catch(err => alert('❌ Error de conexión: ' + err.message))
	.finally(() => {
		if (button) {
			button.textContent = originalText;
			button.disabled = false;
		}
	});
}

// Update temperature display when slider changes
document.addEventListener('DOMContentLoaded', function() {
	const tempSlider = document.getElementById('nexgen_llm_temperature');
	if (tempSlider) {
		tempSlider.addEventListener('input', function() {
			document.getElementById('temp-display').textContent = this.value;
		});
	}
});
</script>