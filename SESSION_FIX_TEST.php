<?php
/**
 * Quick Test Script - NexGen Telegram Chat Session Fix
 * 
 * This script verifies that the session fix is working correctly.
 * Copy this file to your WordPress installation and access it via HTTP.
 * 
 * SECURITY WARNING: Remove this file after testing!
 */

// Prevent direct access outside WordPress
if ( ! defined( 'ABSPATH' ) ) {
	// If running standalone for testing
	require_once( dirname( __FILE__ ) . '/../../../../wp-load.php' );
}

// Only allow from localhost or logged-in admins
if ( ! is_localhost() && ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Acceso denegado' );
}

// Start testing
?>
<!DOCTYPE html>
<html>
<head>
	<title>NexGen Chat - Session Fix Test</title>
	<style>
		body { font-family: Arial; margin: 20px; background: #f5f5f5; }
		.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
		.test { margin: 15px 0; padding: 10px; border-left: 4px solid #ccc; }
		.pass { border-left-color: #22c55e; background: #dcfce7; }
		.fail { border-left-color: #ef4444; background: #fee2e2; }
		.warn { border-left-color: #f59e0b; background: #fef3c7; }
		h1 { color: #333; }
		code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
		.result { font-weight: bold; margin-top: 5px; }
	</style>
</head>
<body>
	<div class="container">
		<h1>🧪 NexGen Chat - Session Fix Test</h1>
		
		<?php
		
		// Test 1: Check if session_start() is being called
		$test1_pass = true;
		$sessions_before = count( get_defined_vars() );
		
		// Simulate a page load - check for active sessions
		$session_active = session_id() ? true : false;
		
		if ( $session_active ) {
			$test1_result = 'FALLO - Sesión PHP abierta. Esto significa que session_start() sigue siendo llamado.';
			$test1_pass = false;
		} else {
			$test1_result = 'OK - No hay sesión PHP abierta. El plugin no está interfiriendo.';
		}
		
		echo '<div class="test ' . ( $test1_pass ? 'pass' : 'fail' ) . '">';
		echo '<strong>Test 1: Sesión PHP abierta</strong>';
		echo '<p>' . $test1_result . '</p>';
		echo '</div>';
		
		// Test 2: Check if Cookie storage is working
		$test2_pass = false;
		$test2_result = '';
		
		// Try to load the plugin services
		if ( class_exists( 'NexGen_Session_Service' ) ) {
			$session_id = NexGen_Session_Service::get_session_id();
			
			if ( ! empty( $session_id ) && preg_match( '/^chat_[a-z0-9\-]+_[a-f0-9]{6}$/i', $session_id ) ) {
				$test2_pass = true;
				$test2_result = 'OK - Session ID generado correctamente: <code>' . esc_html( $session_id ) . '</code>';
			} else {
				$test2_result = 'FALLO - Session ID inválido o no generado.';
			}
		} else {
			$test2_result = 'ADVERTENCIA - No se pudo cargar NexGen_Session_Service. Verifica que el plugin esté activado.';
		}
		
		echo '<div class="test ' . ( $test2_pass ? 'pass' : ( strpos( $test2_result, 'ADVERTENCIA' ) !== false ? 'warn' : 'fail' ) ) . '">';
		echo '<strong>Test 2: Generación de Session ID (Cookie)</strong>';
		echo '<p>' . $test2_result . '</p>';
		echo '</div>';
		
		// Test 3: Check PHP version and extensions
		$test3_pass = true;
		$php_version = phpversion();
		$extensions = [ 'curl', 'json', 'filter' ];
		$missing = [];
		
		foreach ( $extensions as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing[] = $ext;
				$test3_pass = false;
			}
		}
		
		$test3_result = 'OK - PHP ' . esc_html( $php_version ) . ' con extensiones requeridas.';
		if ( ! empty( $missing ) ) {
			$test3_result = 'FALLO - Faltan extensiones: ' . implode( ', ', $missing );
		}
		
		echo '<div class="test ' . ( $test3_pass ? 'pass' : 'fail' ) . '">';
		echo '<strong>Test 3: Configuración PHP</strong>';
		echo '<p>' . $test3_result . '</p>';
		echo '</div>';
		
		// Test 4: Check WordPress REST API accessibility
		$test4_pass = false;
		$rest_url = rest_url( 'wp/v2/posts' );
		$response = wp_remote_get( $rest_url );
		
		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, [ 200, 401, 403 ] ) ) {
				$test4_pass = true;
				$test4_result = 'OK - REST API accesible (código: ' . $code . ')';
			} else {
				$test4_result = 'FALLO - REST API retorna código inesperado: ' . $code;
			}
		} else {
			$test4_result = 'FALLO - REST API no accesible: ' . $response->get_error_message();
		}
		
		echo '<div class="test ' . ( $test4_pass ? 'pass' : 'fail' ) . '">';
		echo '<strong>Test 4: Acceso a REST API</strong>';
		echo '<p>' . $test4_result . '</p>';
		echo '</div>';
		
		// Test 5: Environment check
		$test5_checks = [];
		
		// Check HTTPS
		if ( is_ssl() ) {
			$test5_checks[] = '✓ HTTPS activo (cookies secure)';
		} else {
			$test5_checks[] = '⚠ HTTP (cookies no-secure, considera HTTPS)';
		}
		
		// Check WordPress version
		$wp_version = get_bloginfo( 'version' );
		if ( version_compare( $wp_version, '5.0', '>=' ) ) {
			$test5_checks[] = '✓ WordPress ' . $wp_version . ' (compatible)';
		} else {
			$test5_checks[] = 'FALLO - WordPress ' . $wp_version . ' no soportado';
		}
		
		// Check WP Debug
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$test5_checks[] = '⚠ WP_DEBUG activo (desactívalo en producción)';
		} else {
			$test5_checks[] = '✓ WP_DEBUG no activo (recomendado)';
		}
		
		echo '<div class="test">';
		echo '<strong>Test 5: Ambiente & Configuración</strong>';
		echo '<ul>';
		foreach ( $test5_checks as $check ) {
			echo '<li>' . $check . '</li>';
		}
		echo '</ul>';
		echo '</div>';
		
		// Summary
		echo '<hr>';
		echo '<h2>📋 Resumen</h2>';
		
		$all_pass = $test1_pass && $test2_pass && $test3_pass && $test4_pass;
		
		if ( $all_pass ) {
			echo '<div class="test pass">';
			echo '<strong style="color: green; font-size: 16px;">✓ Todos los tests pasaron!</strong>';
			echo '<p>El fix de sesiones está funcionando correctamente.</p>';
			echo '<p><strong style="color: red;">⚠ IMPORTANTE:</strong> Elimina este archivo de prueba de tu servidor.</p>';
			echo '</div>';
		} else {
			echo '<div class="test fail">';
			echo '<strong style="color: red; font-size: 16px;">✗ Algunos tests fallaron</strong>';
			echo '<p>Revisa los resultados arriba y contacta con soporte si persiste el problema.</p>';
			echo '</div>';
		}
		
		// Helper function
		function is_localhost() {
			$whitelist = [ '127.0.0.1', '::1' ];
			return in_array( $_SERVER['REMOTE_ADDR'], $whitelist );
		}
		
		?>
		
		<hr>
		<h3>💡 Próximos Pasos</h3>
		<ol>
			<li>Verifica que la salud del sitio (Site Health) ya no muestre el error de sesiones</li>
			<li>Prueba el chat en el frontend - debe funcionar normalmente</li>
			<li>Verifica que los mensajes lleguen a Telegram</li>
			<li><strong>Elimina este archivo de prueba</strong></li>
		</ol>
		
		<hr>
		<p style="color: #666; font-size: 12px;">
			<code>SESSION_FIX_TEST.php</code> - Eliminable después de testing
		</p>
	</div>
</body>
</html>
