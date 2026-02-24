<?php
/**
 * Admin Page Template for NexGen Telegram Chat
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
			<button type="button" class="button" onclick="testTelegramConnection()">Enviar mensaje de prueba</button>
			<button type="button" class="button button-primary" onclick="setupWebhook()">Configurar Webhook</button>
		</div>
	<?php else : ?>
		<div class="notice notice-warning">
			<p>⚠️ Bot no configurado. Completa los campos Token y Chat ID para activar el chat.</p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'nexgen_chat_settings' ); ?>

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
						<option value="bottom-right" <?php selected( get_option( 'nexgen_chat_position', 'bottom-right' ), 'bottom-right' ); ?>>Abajo Derecha</option>
						<option value="bottom-left" <?php selected( get_option( 'nexgen_chat_position', 'bottom-left' ), 'bottom-left' ); ?>>Abajo Izquierda</option>
					</select>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="nexgen_auto_open_delay">Auto-apertura (segundos)</label></th>
				<td>
					<input type="number" name="nexgen_auto_open_delay" id="nexgen_auto_open_delay" value="<?php echo esc_attr( get_option( 'nexgen_auto_open_delay', 20 ) ); ?>" min="0" max="300" />
					<p class="description">0 para desactivar la auto-apertura</p>
				</td>
			</tr>
		</table>

		<hr>

		<h2>🤖 Automatización N8N</h2>
		<p><strong>Configura N8N para automatizar respuestas a preguntas comunes sobre desarrollo web y marketing digital.</strong></p>
		
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
				<th scope="row"><label for="nexgen_ai_keywords">Palabras clave para automatización</label></th>
				<td>
					<textarea name="nexgen_ai_keywords" id="nexgen_ai_keywords" rows="5" class="large-text" placeholder="hosting&#10;dominio&#10;ssl&#10;seo&#10;marketing digital&#10;estrategia&#10;precio&#10;desarrollo web"><?php 
						$keywords = json_decode( get_option( 'nexgen_ai_keywords', '["hosting","dominio","ssl","seo","marketing digital","estrategia","precio","desarrollo web"]' ), true );
						if ( is_array( $keywords ) ) {
							echo esc_textarea( implode( "\n", $keywords ) );
						}
					?></textarea>
					<p class="description">Una palabra clave por línea. Los mensajes que contienen estas palabras se enviarán a N8N automáticamente.</p>
				</td>
			</tr>
		</table>

		<div class="notice notice-info" style="margin: 20px 0;">
			<p><strong>📚 Cómo funciona:</strong></p>
			<ol>
				<li>Si el mensaje contiene alguna de las palabras clave → se envía a N8N para respuesta automática</li>
				<li>Si N8N responde exitosamente → el usuario recibirá la respuesta automática</li>
				<li>Si N8N falla o es lenta → se enrutará a Telegram para respuesta humana</li>
				<li>Si no hay palabras clave → se enrutará directamente a Telegram</li>
			</ol>
		</div>

		<?php submit_button( 'Guardar Configuración' ); ?>
	</form>

	<hr>

	<h2>📖 Estructura de N8N Webhook</h2>
	<div class="notice notice-info">
		<p><strong>Tu endpoint N8N recibirá JSON con esta estructura:</strong></p>
		<pre style="background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto;">
{
  "message": "¿Cuál es el mejor hosting?",
  "session_id": "chat_juan_a1b2c3",
  "visitor": "Juan",
  "site": "Mi Sitio Web",
  "timestamp": "2026-02-23 14:30:00"
}
		</pre>
		<p><strong>Tu N8N debe responder con JSON en este formato:</strong></p>
		<pre style="background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto;">
{
  "response": "El mejor hosting es... [tu respuesta]"
}
		</pre>
	</div>

	<h2>🔗 Configuración del Webhook (REQUERIDO para chat bidireccional)</h2>
	<div class="notice notice-info">
		<p><strong>📋 URL del Webhook:</strong></p>
		<code style="background: #f0f0f0; padding: 10px; display: block; margin: 10px 0; word-break: break-all;"><?php echo esc_html( $webhook_url ); ?></code>
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
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
