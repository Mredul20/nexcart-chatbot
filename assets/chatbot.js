jQuery(document).ready(function($) {
    'use strict';
    
    // Chatbot functionality
    const NexCartChatbot = {
        
        firebase: null,
        database: null,
        chatId: null,
        messageCount: 0,
        firebaseLoaded: false,
        currentMode: 'ai', // 'ai' or 'live'
        isConnectedToSupport: false,
        supportAgentId: null,
        
        // Initialize the chatbot
        init: function() {
            this.bindEvents();
            this.generateChatId();
            this.addWelcomeMessage();
            // Firebase will be loaded lazily when chatbot opens
        },
        
        // Lazy load Firebase SDK
        loadFirebaseSDK: function() {
            return new Promise((resolve, reject) => {
                if (this.firebaseLoaded) {
                    resolve();
                    return;
                }
                
                if (!nexcart_ajax.firebase_sdk_urls) {
                    resolve(); // Skip Firebase if URLs not provided
                    return;
                }
                
                // Load Firebase App SDK
                const firebaseAppScript = document.createElement('script');
                firebaseAppScript.src = nexcart_ajax.firebase_sdk_urls.app;
                firebaseAppScript.onload = () => {
                    // Load Firebase Database SDK
                    const firebaseDatabaseScript = document.createElement('script');
                    firebaseDatabaseScript.src = nexcart_ajax.firebase_sdk_urls.database;
                    firebaseDatabaseScript.onload = () => {
                        this.firebaseLoaded = true;
                        this.initFirebase();
                        resolve();
                    };
                    firebaseDatabaseScript.onerror = () => {
                        console.warn('Failed to load Firebase Database SDK');
                        resolve(); // Continue without Firebase
                    };
                    document.head.appendChild(firebaseDatabaseScript);
                };
                firebaseAppScript.onerror = () => {
                    console.warn('Failed to load Firebase App SDK');
                    resolve(); // Continue without Firebase
                };
                document.head.appendChild(firebaseAppScript);
            });
        },
        
        // Initialize Firebase
        initFirebase: function() {
            if (typeof firebase !== 'undefined' && nexcart_ajax.firebase_config.apiKey) {
                try {
                    // Initialize Firebase
                    if (!firebase.apps.length) {
                        firebase.initializeApp(nexcart_ajax.firebase_config);
                    }
                    this.database = firebase.database();
                    console.log('Firebase initialized successfully');
                } catch (error) {
                    console.error('Firebase initialization error:', error);
                }
            } else {
                console.warn('Firebase not available or not configured');
            }
        },
        
        // Generate unique chat ID
        generateChatId: function() {
            this.chatId = nexcart_ajax.user_id + '_' + Date.now();
            console.log('Chat ID generated:', this.chatId);
        },
        
        // Bind event listeners
        bindEvents: function() {
            // Toggle chatbot widget
            $('#nexcart-chatbot-toggle').on('click', this.toggleWidget.bind(this));
            
            // Close chatbot
            $('#nexcart-chatbot-close').on('click', this.closeWidget);
            
            // Send message on button click
            $('#nexcart-chatbot-send').on('click', this.sendMessage);
            
            // Send message on Enter key press
            $('#nexcart-chatbot-input').on('keypress', function(e) {
                if (e.which === 13) {
                    NexCartChatbot.sendMessage();
                }
            });
            
            // Chat mode selection
            $('input[name="chat_mode"]').on('change', this.handleModeChange.bind(this));
            
            // Auto-resize input
            $('#nexcart-chatbot-input').on('input', this.autoResizeInput);
        },
        
        // Add welcome message
        addWelcomeMessage: function() {
            if (this.currentMode === 'ai') {
                const welcomeMessage = 'Hello! 👋 I\'m your AI assistant powered by **Groq AI** using the **LLaMA 3** model.\n\nI can help you with:\n\n• 🛍️ Product information and recommendations\n• 📦 Order status and tracking\n• 🚚 Shipping and return policies\n• 💳 Payment options and checkout help\n• ❓ General store questions\n\nWhat can I help you find today?';
                this.addMessage(welcomeMessage, 'ai', 'AI Assistant');
            } else {
                this.connectToLiveSupport();
            }
        },
        
        // Handle mode change between AI and Live chat
        handleModeChange: function(e) {
            const newMode = e.target.value;
            const oldMode = this.currentMode;
            this.currentMode = newMode;
            
            // Update visual indicators
            this.updateModeUI();
            
            // Clear messages when switching modes
            $('#nexcart-chatbot-messages').empty();
            
            if (newMode === 'ai') {
                $('#nexcart-connection-status').hide();
                this.addWelcomeMessage();
                this.updateInputPlaceholder('Ask me anything about our store...');
            } else if (newMode === 'live') {
                this.connectToLiveSupport();
                this.updateInputPlaceholder('Type your message to support...');
            }
        },
        
        // Update mode UI indicators
        updateModeUI: function() {
            // Update radio button labels
            $('#nexcart-chat-mode label').removeClass('active');
            $('#nexcart-chat-mode input:checked').parent().addClass('active');
            
            // Update info text
            if (this.currentMode === 'ai') {
                $('#nexcart-ai-info').show();
                $('#nexcart-live-info').hide();
            } else {
                $('#nexcart-ai-info').hide();
                $('#nexcart-live-info').show();
            }
        },
        
        // Connect to live support
        connectToLiveSupport: function() {
            $('#nexcart-connection-status').show();
            $('#nexcart-connecting').show();
            $('#nexcart-connected, #nexcart-offline').hide();
            
            // Simulate connection attempt
            setTimeout(() => {
                this.checkSupportAvailability();
            }, 2000);
        },
        
        // Check if support agents are available
        checkSupportAvailability: function() {
            // Check if admin is online via Firebase or WordPress option
            const isSupporOnline = this.isSupportOnline();
            
            if (isSupporOnline) {
                this.establishSupportConnection();
            } else {
                this.showSupportOffline();
            }
        },
        
        // Check if support is online
        isSupportOnline: function() {
            // This could check Firebase presence or WordPress user meta
            // For now, let's use a simple time-based check (office hours)
            const now = new Date();
            const hour = now.getHours();
            // Assume support is available 9 AM to 9 PM (adjust as needed)
            return hour >= 9 && hour <= 21;
        },
        
        // Establish connection to support
        establishSupportConnection: function() {
            this.isConnectedToSupport = true;
            this.supportAgentId = 'agent_' + Date.now();
            
            $('#nexcart-connecting').hide();
            $('#nexcart-connected').show();
            
            // Add welcome message from support
            const welcomeMsg = 'Hello! 👋 A support agent is now connected and ready to help you. How can we assist you today?';
            this.addMessage(welcomeMsg, 'support', 'Support Agent');
            
            // Listen for live messages if Firebase is available
            if (this.database) {
                this.listenForSupportMessages();
            }
        },
        
        // Show support offline status
        showSupportOffline: function() {
            $('#nexcart-connecting').hide();
            $('#nexcart-offline').show();
            
            const offlineMsg = 'Our support team is currently offline. You can:\n\n• Switch to AI Chat for instant help\n• Leave a message and we\'ll get back to you\n• Try again during business hours (9 AM - 9 PM)';
            this.addMessage(offlineMsg, 'system', 'System');
        },
        
        // Listen for support messages via Firebase
        listenForSupportMessages: function() {
            if (!this.database) return;
            
            const messagesRef = this.database.ref('chats/' + this.chatId + '/messages');
            messagesRef.on('child_added', (snapshot) => {
                const message = snapshot.val();
                if (message && message.sender === 'support' && message.timestamp > Date.now() - 1000) {
                    this.addMessage(message.text, 'support', 'Support Agent');
                }
            });
        },
        
        // Update input placeholder
        updateInputPlaceholder: function(placeholder) {
            $('#nexcart-chatbot-input').attr('placeholder', placeholder);
        },
        
        // Toggle widget visibility
        toggleWidget: function() {
            const widget = $('#nexcart-chatbot-widget');
            const toggle = $('#nexcart-chatbot-toggle');
            
            if (widget.is(':visible')) {
                widget.fadeOut(300);
                toggle.find('span').text('🤖');
            } else {
                // Load Firebase SDK when chatbot opens for the first time
                if (!this.firebaseLoaded) {
                    this.loadFirebaseSDK().then(() => {
                        this.showWidget(widget, toggle);
                    });
                } else {
                    this.showWidget(widget, toggle);
                }
            }
        },
        
        // Show widget helper function
        showWidget: function(widget, toggle) {
            widget.fadeIn(300);
            toggle.find('span').text('💬');
            $('#nexcart-chatbot-input').focus();
        },
        
        // Close widget
        closeWidget: function() {
            $('#nexcart-chatbot-widget').fadeOut(300);
            $('#nexcart-chatbot-toggle span').text('🤖');
        },
        
        // Add welcome message
        addWelcomeMessage: function() {
            const welcomeMessage = 'Hello! 👋 I\'m your NexCart assistant. I can help you with:\n\n• Product information and recommendations\n• Order status and tracking\n• Shipping and return policies\n• Store policies and FAQ\n\nHow can I assist you today?';
            this.addMessage(welcomeMessage, 'bot');
        },
        
        // Send user message
        sendMessage: function() {
            const input = $('#nexcart-chatbot-input');
            const message = input.val().trim();
            
            if (message === '') return;
            
            // Rate limiting check
            this.messageCount++;
            if (this.messageCount > 10) {
                this.addMessage('Please wait a moment before sending more messages. 🕐', 'system');
                return;
            }
            
            // Reset rate limit counter after 1 minute
            setTimeout(() => {
                this.messageCount = Math.max(0, this.messageCount - 1);
            }, 60000);
            
            // Add user message to chat
            NexCartChatbot.addMessage(message, 'user', nexcart_ajax.user_name);
            
            // Clear input
            input.val('');
            
            // Store message in Firebase
            NexCartChatbot.storeMessageInFirebase(message, 'user');
            
            // Route message based on current mode
            if (this.currentMode === 'ai') {
                // Send to Groq AI endpoint
                NexCartChatbot.sendToGroqAI(message);
            } else {
                // Send to live support
                NexCartChatbot.sendToLiveSupport(message);
            }
        },
        
        // Send message to live support
        sendToLiveSupport: function(message) {
            if (!this.isConnectedToSupport) {
                this.addMessage('Please wait while we connect you to support...', 'system', 'System');
                this.connectToLiveSupport();
                return;
            }
            
            // Store message in Firebase for support agents
            this.storeMessageInFirebase(message, 'user');
            
            // Simulate support response (in real implementation, this would come via Firebase)
            setTimeout(() => {
                // This is a placeholder - real support messages would come through Firebase
                if (Math.random() > 0.7) { // 30% chance of auto-response
                    const autoResponses = [
                        'Thank you for your message. A support agent will respond shortly.',
                        'I\'m looking into your inquiry. Please give me a moment.',
                        'Let me check that information for you.',
                        'I understand your concern. Let me help you with that.'
                    ];
                    const response = autoResponses[Math.floor(Math.random() * autoResponses.length)];
                    this.addMessage(response, 'support', 'Support Agent');
                    this.storeMessageInFirebase(response, 'support');
                }
            }, 1000 + Math.random() * 3000); // Random delay 1-4 seconds
        },
        
        // Store message in Firebase
        storeMessageInFirebase: function(message, sender) {
            if (this.database && this.chatId) {
                const messageRef = this.database.ref(`chats/${this.chatId}/messages`).push();
                messageRef.set({
                    text: message,
                    sender: sender,
                    senderName: sender === 'user' ? nexcart_ajax.user_name : 'AI Assistant',
                    timestamp: firebase.database.ServerValue.TIMESTAMP
                });
            }
        },
        
        // Send message to Groq AI
        sendToGroqAI: function(message) {
            // Show typing indicator
            NexCartChatbot.showTyping();
            
            // Send AJAX request to Groq AI endpoint
            $.ajax({
                url: nexcart_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'nexcart_chat',
                    message: message,
                    chat_id: this.chatId,
                    nonce: nexcart_ajax.nonce
                },
                success: function(response) {
                    NexCartChatbot.hideTyping();
                    
                    if (response.success) {
                        const aiResponse = response.data.response;
                        NexCartChatbot.addMessage(aiResponse, 'ai', 'AI Assistant');
                        
                        // Store AI response in Firebase
                        NexCartChatbot.storeMessageInFirebase(aiResponse, 'ai');
                    } else {
                        NexCartChatbot.addMessage('Sorry, I encountered an error. Please try again. 🔧', 'ai', 'AI Assistant');
                    }
                },
                error: function(xhr, status, error) {
                    NexCartChatbot.hideTyping();
                    console.error('Groq AI request failed:', error);
                    NexCartChatbot.addMessage('I\'m having trouble connecting to my AI brain. Please try again in a moment! 🤖', 'ai', 'AI Assistant');
                }
            });
        },
        
        // Add message to chat
        addMessage: function(message, sender, senderName) {
            const messagesContainer = $('#nexcart-chatbot-messages');
            const showSender = senderName && sender !== 'user';
            
            const messageHtml = `
                <div class="nexcart-message ${sender}">
                    ${showSender ? `<div class="nexcart-message-sender">${senderName}</div>` : ''}
                    <div class="nexcart-message-content">
                        ${this.formatMessage(message)}
                    </div>
                </div>
            `;
            
            messagesContainer.append(messageHtml);
            this.scrollToBottom();
        },
        
        // Format message (convert line breaks, handle HTML content)
        formatMessage: function(message) {
            // If message contains HTML (like product cards), return as-is
            if (message.includes('<div class="nexcart-product-card">')) {
                return message;
            }
            
            // Otherwise, format as plain text with line breaks and markdown
            return message
                .replace(/\n/g, '<br>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>');
        },
        
        // Show typing indicator
        showTyping: function() {
            const typingHtml = `
                <div class="nexcart-message ai nexcart-typing-message">
                    <div class="nexcart-message-sender">AI Assistant</div>
                    <div class="nexcart-message-content nexcart-typing">
                        🤖 ${nexcart_ajax.loading_text}
                    </div>
                </div>
            `;
            
            $('#nexcart-chatbot-messages').append(typingHtml);
            this.scrollToBottom();
        },
        
        // Hide typing indicator
        hideTyping: function() {
            $('.nexcart-typing-message').remove();
        },
        
        // Scroll to bottom of messages
        scrollToBottom: function() {
            const messagesContainer = $('#nexcart-chatbot-messages');
            messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
        },
        
        // Auto-resize input (if needed for future enhancements)
        autoResizeInput: function() {
            // This can be used for multi-line input in future versions
        },
        
        // Handle product searches
        searchProducts: function(query) {
            // This method can be expanded to search products via AJAX
            $.ajax({
                url: nexcart_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'nexcart_search_products',
                    query: query,
                    nonce: nexcart_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.products) {
                        NexCartChatbot.displayProducts(response.data.products);
                    }
                }
            });
        },
        
        // Display products in chat
        displayProducts: function(products) {
            if (products.length === 0) {
                this.addMessage('Sorry, I couldn\'t find any products matching your search.', 'bot');
                return;
            }
            
            let productMessage = 'Here are some products I found:\n\n';
            products.forEach(function(product) {
                productMessage += `• ${product.name} - $${product.price}\n`;
            });
            
            this.addMessage(productMessage, 'bot');
        }
    };
    
    // Initialize chatbot when DOM is ready
    NexCartChatbot.init();
    
    // Add some utility functions
    window.NexCartChatbot = NexCartChatbot;
});
