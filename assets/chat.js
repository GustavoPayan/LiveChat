jQuery(document).ready(function($) {
    let chatOpen = false;
    let autoOpenTimer = null;
    let pollTimer = null;
    let lastMessageId = 0;
    let hasNewMessages = false;

    // Phase 5: Conversation cache & pagination
    let conversationCache = [];  // In-memory cache for history
    let allMessagesLoaded = false;
    let isLoadingMore = false;
    let conversationOffset = 0;
    const CONVERSATION_LIMIT = 10;  // Load 10 messages at a time

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

    // **NONCE REFRESH SYSTEM** (Protection against expired nonces)
    let currentNonce = nexgen_chat_ajax.nonce;  // Mutable, se actualiza cuando expira
    const NONCE_REFRESH_INTERVAL = 12 * 60 * 60 * 1000;  // 12 hours in ms
    let nonceRefreshTimer = null;

    /**
     * Refresh nonce from server (called when nonce expires or preventively)
     * @returns {Promise<boolean>} True if refresh successful
     */
    function refreshNonce() {
        return $.ajax({
            url: nexgen_chat_ajax.ajax_url,
            type: 'POST',
            data: { action: 'nexgen_refresh_nonce' },
            timeout: 5000
        }).done(function(response) {
            if (response.success && response.data.nonce) {
                currentNonce = response.data.nonce;
                if (nexgen_chat_ajax.debug) {
                    console.log('NexGen Chat - Nonce refreshed at', response.data.timestamp);
                }
                return true;
            }
            return false;
        }).fail(function(error) {
            console.warn('NexGen Chat - Nonce refresh failed:', error);
            return false;
        });
    }

    /**
     * Make AJAX request with automatic nonce refresh on "Nonce inválido" error
     * @param {object} ajaxConfig - jQuery AJAX config
     * @param {boolean} retryOnNonceError - Should we retry if nonce is invalid (default true)
     * @returns {Promise}
     */
    function makeAjaxWithNonceRetry(ajaxConfig, retryOnNonceError = true) {
        if (!ajaxConfig.data) ajaxConfig.data = {};
        ajaxConfig.data.nonce = currentNonce;

        return $.Deferred(function(deferred) {
            const request = $.ajax(ajaxConfig);

            request.done(function(...args) {
                deferred.resolveWith(this, args);
            });

            request.fail(function(xhr, status, error) {
                if (retryOnNonceError && xhr.responseJSON && xhr.responseJSON.data === 'Nonce inválido') {
                    if (nexgen_chat_ajax.debug) {
                        console.log('NexGen Chat - Nonce expired, refreshing and retrying...');
                    }

                    refreshNonce().done(function() {
                        ajaxConfig.data.nonce = currentNonce;
                        $.ajax(ajaxConfig)
                            .done(function(...retryArgs) {
                                deferred.resolveWith(this, retryArgs);
                            })
                            .fail(function(...retryFailArgs) {
                                deferred.rejectWith(this, retryFailArgs);
                            });
                    }).fail(function() {
                        deferred.reject(xhr, status, error);
                    });
                } else {
                    deferred.reject(xhr, status, error);
                }
            });
        }).promise();
    }

    // Set up preventive nonce refresh every 12 hours
    function setupNonceRefreshTimer() {
        if (nonceRefreshTimer) clearInterval(nonceRefreshTimer);
        nonceRefreshTimer = setInterval(function() {
            refreshNonce();  // Silent refresh in background
        }, NONCE_REFRESH_INTERVAL);
    }
    setupNonceRefreshTimer();

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

    // Phase 5: Infinite scroll - Load more history when scrolling to top
    $messages.on('scroll', function() {
        if ($(this).scrollTop() === 0 && !isLoadingMore && !allMessagesLoaded) {
            conversationOffset = conversationCache.length;
            loadConversation(conversationOffset, CONVERSATION_LIMIT);
        }
    });

    // Funciones principales
    function openChat() {
        chatOpen = true;
        $window.removeClass('nexgen-exit').addClass('nexgen-enter');
        $window.css('display', 'flex'); // Ensure flex display
        
        // Animate icon transition
        $chatIcon.fadeOut(200);
        $closeIcon.fadeIn(200);
        
        // Cancelar auto-apertura si está activa
        if (autoOpenTimer) {
            clearTimeout(autoOpenTimer);
            autoOpenTimer = null;
        }

        // Ocultar notificación con transición suave
        $notification.fadeOut(200);
        hasNewMessages = false;

        // Focus input after animation
        setTimeout(() => {
            $input.focus();
        }, 150);

        // Si no hay nombre guardado, mostrar overlay
        if (!getStoredName()) {
            setTimeout(() => showNameOverlay(), 200);
        }

        // Scroll al final
        scrollToBottom();

        // Phase 5: Load conversation optimized history (if cache is empty)
        if (conversationCache.length === 0) {
            loadConversation(0, CONVERSATION_LIMIT);
        }
    }

    function closeChat() {
        chatOpen = false;
        $window.removeClass('nexgen-enter').addClass('nexgen-exit');
        
        // Animate icon transition
        $closeIcon.fadeOut(200);
        $chatIcon.fadeIn(200);
        
        // Hide window after animation completes
        setTimeout(() => {
            $window.css('display', 'none');
        }, 250);
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

        // Enviar mensaje via AJAX (with automatic nonce retry)
        makeAjaxWithNonceRetry({
            url: nexgen_chat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'nexgen_send_message',
                message: message,
                session_id: sessionId
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
        makeAjaxWithNonceRetry({
            url: nexgen_chat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'nexgen_get_messages',
                session_id: sessionId,
                last_message_id: 0
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

    function checkForNewMessages() {
        makeAjaxWithNonceRetry({
            url: nexgen_chat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'nexgen_get_messages',
                session_id: sessionId,
                last_message_id: lastMessageId
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

    /**
     * Phase 5: Load conversation history optimized
     * Fetches paginated history without overloading DB
     */
    function loadConversation(offset, limit) {
        isLoadingMore = true;

        makeAjaxWithNonceRetry({
            url: nexgen_chat_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'nexgen_get_conversation',
                offset: offset,
                limit: limit
            },
            timeout: 10000,
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    // Add to cache
                    conversationCache = response.data.concat(conversationCache);

                    // Render messages
                    response.data.forEach(function(msg) {
                        renderMessage(msg, false); // false = don't animate for history
                    });

                    // Update lastMessageId from newest message
                    if (!lastMessageId && response.data.length > 0) {
                        lastMessageId = response.data[response.data.length - 1].id;
                    }
                } else {
                    allMessagesLoaded = true; // No more messages to load
                }
            },
            error: function(xhr, status, error) {
                if (nexgen_chat_ajax.debug) {
                    console.log('NexGen Chat - Error loading conversation:', error);
                }
            },
            complete: function() {
                isLoadingMore = false;
            }
        });
    }

    function showNotification() {
        hasNewMessages = true;
        $notification.text('1');
        $notification.addClass('nexgen-notification-entering');
        $notification.show();
        $toggle.addClass('has-notification');
        
        setTimeout(() => {
            $notification.removeClass('nexgen-notification-entering');
        }, 10);
    }

    function addMessage(text, type, time, animate = true) {
        if (!time) {
            time = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        }

        const messageClass = type === 'user' ? 'nexgen-user-message' : 'nexgen-bot-message';

        const messageHtml = `
            <div class="nexgen-message ${messageClass}${animate ? ' nexgen-message-entering' : ''}">
                <div class="nexgen-message-content">${escapeHtml(text)}</div>
                <div class="nexgen-message-time">${time}</div>
            </div>
        `;

        const $newMessage = $(messageHtml);
        $messages.append($newMessage);

        if (animate) {
            // Trigger animation with slight delay for smooth rendering
            setTimeout(() => {
                $newMessage.removeClass('nexgen-message-entering');
            }, 10);
        }

        scrollToBottom();
    }

    /**
     * Phase 5: Render message from history
     * Used for both new messages and historical messages
     */
    function renderMessage(msg, animate = true) {
        const messageClass = msg.type === 'user' ? 'nexgen-user-message' : 'nexgen-bot-message';

        const messageHtml = `
            <div class="nexgen-message ${messageClass}${animate ? ' nexgen-message-entering' : ''}">
                <div class="nexgen-message-content">${escapeHtml(msg.text)}</div>
                <div class="nexgen-message-time">${msg.time}</div>
            </div>
        `;

        const $newMessage = $(messageHtml);
        
        // Insert at end for new messages, or at beginning for history
        if (animate) {
            $messages.append($newMessage);
            setTimeout(() => {
                $newMessage.removeClass('nexgen-message-entering');
            }, 10);
            scrollToBottom();
        } else {
            // For history, prepend and don't scroll
            if ($messages.find('.nexgen-message').length === 0) {
                $messages.append($newMessage);
            } else {
                $newMessage.prependTo($messages);
            }
        }
    }

    function addSystemMessage(text) {
        const time = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        const messageHtml = `
            <div class="nexgen-message nexgen-system-message nexgen-message-entering">
                <div class="nexgen-message-content">${escapeHtml(text)}</div>
                <div class="nexgen-message-time">${time}</div>
            </div>
        `;
        const $msg = $(messageHtml);
        $messages.append($msg);
        
        // Trigger animation
        setTimeout(() => {
            $msg.removeClass('nexgen-message-entering');
        }, 10);
        
        scrollToBottom();
    }

    function showTyping() {
        $typing.addClass('nexgen-typing-entering');
        setTimeout(() => {
            $typing.removeClass('nexgen-typing-entering').show();
        }, 10);
        scrollToBottom();
    }

    function hideTyping() {
        $typing.addClass('nexgen-typing-exiting');
        setTimeout(() => {
            $typing.removeClass('nexgen-typing-exiting').hide();
        }, 200);
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
        $nameOverlay.removeClass('nexgen-overlay-exiting').addClass('nexgen-overlay-entering');
        $nameOverlay.show();
        $nameInput.removeClass('nexgen-input-error');
        const stored = getStoredName();
        if (stored) $nameInput.val(stored);
        setTimeout(() => {
            $nameInput.trigger('focus');
            $nameOverlay.removeClass('nexgen-overlay-entering');
        }, 50);
    }

    function hideNameOverlay() {
        $nameOverlay.addClass('nexgen-overlay-exiting');
        setTimeout(() => {
            $nameOverlay.removeClass('nexgen-overlay-exiting').hide();
        }, 200);
    }

    function saveName() {
        const rawName = ($nameInput.val() || '').trim();
        if (!rawName) {
            $nameInput.addClass('nexgen-input-error').focus();
            return;
        }

        $nameSave.prop('disabled', true).text('Guardando...');

        makeAjaxWithNonceRetry({
            url: nexgen_chat_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'nexgen_set_chat_name',
                name: rawName
            },
            success: function(resp) {
                if (resp.success) {
                    sessionId = resp.data.session_id;
                    try { sessionStorage.setItem('nexgen_chat_name', rawName); } catch(e) {}
                    hideNameOverlay();
                    addSystemMessage('Gracias, ' + rawName + '. Ahora puedes escribir tu mensaje.');
                    loadExistingMessages();
                } else {
                    $nameInput.addClass('nexgen-input-error');
                    alert('Error: ' + (resp.data || 'No se pudo guardar el nombre'));
                }
            },
            error: function(xhr, status, error) {
                $nameInput.addClass('nexgen-input-error');
                alert('Error al guardar nombre: ' + error);
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