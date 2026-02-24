jQuery(document).ready(function($) {
    let chatOpen = false;
    let autoOpenTimer = null;
    let pollTimer = null;
    let lastMessageId = 0;
    let hasNewMessages = false;

    const settings = nexgen_chat_ajax.settings;
    let sessionId = nexgen_chat_ajax.session_id; // mutable, se actualiza tras guardar nombre

    // Elementos del DOM
    const $toggle = $('#nexgen-chat-toggle');
    const $window = $('#nexgen-chat-window');
    const $close = $('#nexgen-chat-close');
    const $input = $('#nexgen-chat-input');
    const $send = $('#nexgen-chat-send');
    const $messages = $('#nexgen-chat-messages');
    const $typing = $('#nexgen-typing');
    const $chatIcon = $toggle.find('.chat-icon');
    const $closeIcon = $toggle.find('.close-icon');
    const $notification = $('#nexgen-notification');

    // Overlay nombre
    const $nameOverlay = $('#nexgen-name-overlay');
    const $nameInput = $('#nexgen-name-input');
    const $nameSave = $('#nexgen-name-save');

    // Debug
    if (nexgen_chat_ajax.debug) {
        console.log('NexGen Chat - Configuración:', settings);
        console.log('NexGen Chat - Session ID (inicio):', sessionId);
    }

    // Auto-apertura después del tiempo configurado
    if (settings.auto_open_delay > 0) {
        autoOpenTimer = setTimeout(function() {
            if (!chatOpen) openChat();
        }, settings.auto_open_delay * 1000);
    }

    // Iniciar polling para nuevos mensajes
    startPolling();

    // Event listeners
    $toggle.on('click', function() {
        if (chatOpen) closeChat();
        else openChat();
    });

    $close.on('click', closeChat);
    $send.on('click', sendMessage);

    $input.on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    $nameSave.on('click', saveName);
    $nameInput.on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            saveName();
        }
    });

    // Funciones principales
    function openChat() {
        chatOpen = true;
        $window.show();
        $chatIcon.hide();
        $closeIcon.show();
        $input.focus();

        // Ocultar notificación
        $notification.hide();
        hasNewMessages = false;

        // Cancelar auto-apertura si está activa
        if (autoOpenTimer) {
            clearTimeout(autoOpenTimer);
            autoOpenTimer = null;
        }

        // Si no hay nombre guardado, mostrar overlay
        if (!getStoredName()) {
            showNameOverlay();
        }

        // Scroll al final
        scrollToBottom();

        // Cargar mensajes existentes
        loadExistingMessages();
    }

    function closeChat() {
        chatOpen = false;
        $window.hide();
        $chatIcon.show();
        $closeIcon.hide();
    }

    function sendMessage() {
        const message = $input.val().trim();

        if (!message) {
            $input.focus();
            return;
        }

        // Exigir nombre antes de enviar
        if (!getStoredName()) {
            showNameOverlay();
            return;
        }

        if (nexgen_chat_ajax.debug) {
            console.log('NexGen Chat - Enviando mensaje:', { message, sessionId });
        }

        // Deshabilitar input y botón
        $input.prop('disabled', true);
        $send.prop('disabled', true);

        // Agregar mensaje del usuario visualmente
        addMessage(message, 'user');

        // Limpiar input
        $input.val('');

        // Mostrar indicador de escritura
        showTyping();

        // Enviar mensaje via AJAX
        $.ajax({
            url: nexgen_chat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'nexgen_send_message',
                message: message,
                session_id: sessionId,
                nonce: nexgen_chat_ajax.nonce
            },
            timeout: 30000,
            success: function(response) {
                hideTyping();

                if (nexgen_chat_ajax.debug) {
                    console.log('NexGen Chat - Respuesta:', response);
                }

                if (response.success) {
                    // Only show "we'll respond soon" message if NOT automated (N8N response)
                    // If automated, the response will appear via polling
                    if (!response.data.automated) {
                        addSystemMessage('Mensaje enviado. Responderemos pronto.');
                    }
                } else {
                    const errorMsg = response.data || settings.error_message;
                    addMessage(errorMsg, 'bot');
                }
            },
            error: function(xhr, status, error) {
                hideTyping();
                let errorMessage = settings.error_message;

                if (status === 'timeout') errorMessage = 'Tiempo de espera agotado. Verifica tu conexión.';
                else if (xhr.status === 0) errorMessage = 'Sin conexión a internet. Verifica tu conexión.';
                else if (xhr.status >= 500) errorMessage = 'Error del servidor. Inténtalo más tarde.';

                addMessage(errorMessage, 'bot');
            },
            complete: function() {
                $input.prop('disabled', false);
                $send.prop('disabled', false);
                $input.focus();
            }
        });
    }

    function loadExistingMessages() {
        $.ajax({
            url: nexgen_chat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'nexgen_get_messages',
                session_id: sessionId,
                last_message_id: 0,
                nonce: nexgen_chat_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    // Limpiar mensajes existentes excepto el de bienvenida
                    $messages.find('.nexgen-message:not(:first)').remove();

                    response.data.forEach(function(msg) {
                        addMessage(msg.text, msg.type, msg.time, false);
                        lastMessageId = Math.max(lastMessageId, msg.id);
                    });

                    scrollToBottom();
                }
            }
        });
    }

    function startPolling() {
        pollTimer = setInterval(checkForNewMessages, settings.poll_interval);
    }

    function checkForNewMessages() {
        $.ajax({
            url: nexgen_chat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'nexgen_get_messages',
                session_id: sessionId,
                last_message_id: lastMessageId,
                nonce: nexgen_chat_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(msg) {
                        if (msg.type === 'bot') {
                            addMessage(msg.text, msg.type, msg.time);
                            lastMessageId = Math.max(lastMessageId, msg.id);

                            if (!chatOpen) showNotification();
                        }
                    });

                    if (chatOpen) scrollToBottom();
                }
            }
        });
    }

    function showNotification() {
        hasNewMessages = true;
        $notification.text('1').show();
        $toggle.addClass('has-notification');
    }

    function addMessage(text, type, time, animate = true) {
        if (!time) {
            time = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        }

        const messageClass = type === 'user' ? 'nexgen-user-message' : 'nexgen-bot-message';

        const messageHtml = `
            <div class="nexgen-message ${messageClass}" ${animate ? 'style="opacity: 0;"' : ''}>
                <div class="nexgen-message-content">${escapeHtml(text)}</div>
                <div class="nexgen-message-time">${time}</div>
            </div>
        `;

        const $newMessage = $(messageHtml);
        $messages.append($newMessage);

        if (animate) $newMessage.animate({opacity: 1}, 300);

        scrollToBottom();
    }

    function addSystemMessage(text) {
        const time = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        const messageHtml = `
            <div class="nexgen-message nexgen-system-message">
                <div class="nexgen-message-content">${escapeHtml(text)}</div>
                <div class="nexgen-message-time">${time}</div>
            </div>
        `;
        $messages.append(messageHtml);
        scrollToBottom();
    }

    function showTyping() {
        $typing.show();
        scrollToBottom();
    }

    function hideTyping() {
        $typing.hide();
    }

    function scrollToBottom() {
        $messages.scrollTop($messages[0].scrollHeight);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Nombre en sessionStorage
    function getStoredName() {
        try { return sessionStorage.getItem('nexgen_chat_name') || ''; }
        catch (e) { return ''; }
    }

    function showNameOverlay() {
        $nameOverlay.show();
        $nameInput.removeClass('nexgen-input-error');
        const stored = getStoredName();
        if (stored) $nameInput.val(stored);
        setTimeout(() => $nameInput.trigger('focus'), 50);
    }

    function hideNameOverlay() {
        $nameOverlay.hide();
    }

    function saveName() {
        const rawName = ($nameInput.val() || '').trim();
        if (!rawName) {
            $nameInput.addClass('nexgen-input-error').focus();
            return;
        }

        $nameSave.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: nexgen_chat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'nexgen_set_chat_name',
                name: rawName,
                nonce: nexgen_chat_ajax.nonce
            },
            success: function(resp) {
                if (resp.success) {
                    sessionId = resp.data.session_id;
                    try { sessionStorage.setItem('nexgen_chat_name', rawName); } catch(e) {}
                    hideNameOverlay();
                    addSystemMessage('Gracias, ' + rawName + '. Ahora puedes escribir tu mensaje.');
                    $input.focus();

                    if (nexgen_chat_ajax.debug) {
                        console.log('NexGen Chat - Nuevo Session ID:', sessionId);
                    }
                } else {
                    alert(resp.data || 'No se pudo guardar el nombre. Intenta nuevamente.');
                }
            },
            error: function() {
                alert('Error de conexión al guardar el nombre.');
            },
            complete: function() {
                $nameSave.prop('disabled', false).text('Empezar chat');
            }
        });
    }

    // Detectar si el usuario está inactivo para mostrar el chat
    let inactivityTimer = null;
    function resetInactivityTimer() {
        if (inactivityTimer) clearTimeout(inactivityTimer);

        if (!chatOpen && settings.auto_open_delay > 0) {
            inactivityTimer = setTimeout(function() {
                openChat();
            }, settings.auto_open_delay * 1000);
        }
    }

    // Eventos para detectar actividad del usuario
    $(document).on('mousemove keypress scroll', function() {
        if (!chatOpen) resetInactivityTimer();
    });

    // Limpiar timers al cerrar la página
    $(window).on('beforeunload', function() {
        if (autoOpenTimer) clearTimeout(autoOpenTimer);
        if (pollTimer) clearInterval(pollTimer);
        if (inactivityTimer) clearTimeout(inactivityTimer);
    });

    // Inicializar timer de inactividad
    resetInactivityTimer();

    // CSS adicional para notificaciones
    const additionalCSS = `
    .nexgen-chatbot-toggle.has-notification { animation: shake 0.5s ease-in-out infinite alternate; }
    @keyframes shake { 0% { transform: translateX(0); } 100% { transform: translateX(2px); } }
    `;
    $('<style>').text(additionalCSS).appendTo('head');
});