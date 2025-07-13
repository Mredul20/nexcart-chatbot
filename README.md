# NexCart Chatbot Plugin - Groq AI Edition

A secure and modular WordPress chatbot plugin powered by **Groq AI** using the **LLaMA 3** model. This plugin adds a floating chatbox to your WordPress site, provides intelligent AI responses, and securely stores conversations in Firebase Realtime Database.

## ✨ Features

- 🤖 **Groq AI Integration**: Lightning-fast responses using LLaMA 3 (8B parameters)
- 🔐 **Secure API Management**: API keys stored securely in wp-config.php
- � **Responsive Design**: Beautiful chat interface that works on all devices
- 🚀 **Ultra-Fast Performance**: Groq's optimized infrastructure for instant responses
- � **Firebase Integration**: Store conversations in real-time database
- � **Admin Dashboard**: Monitor chats and usage in WordPress admin
- 🛡️ **Built-in Security**: Rate limiting, input validation, and CORS protection
- 🎯 **WooCommerce Ready**: Enhanced for e-commerce with product awareness

## 🚀 Quick Start

### 1. Installation
1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin

### 2. Get Groq API Key
1. Visit [Groq Console](https://console.groq.com/)
2. Create an account and generate an API key
3. Copy your API key

### 3. Configure WordPress
Add to your `wp-config.php` file:

```php
// NexCart Chatbot - Groq AI Configuration
define('GROQ_API_KEY', 'your-groq-api-key-here');
```

**That's it!** The chatbot will appear on your site and start providing AI-powered responses.

## 🔧 Advanced Configuration

### Firebase Setup (Optional)
For conversation storage and monitoring:

```php
// Firebase Configuration (Optional)
update_option('nexcart_firebase_api_key', 'AIzaSyCLIFGF0KmZfLP9LhHpcWugHgk3qKFqIy0');
update_option('nexcart_firebase_auth_domain', 'nexcart-chat.firebaseapp.com');
update_option('nexcart_firebase_database_url', 'https://nexcart-chat-default-rtdb.firebaseio.com');
update_option('nexcart_firebase_project_id', 'nexcart-chat');
update_option('nexcart_firebase_storage_bucket', 'nexcart-chat.firebasestorage.app');
update_option('nexcart_firebase_messaging_sender_id', '71577709809');
update_option('nexcart_firebase_app_id', '1:71577709809:web:9498a3dcf9d8b667644d50');
```

## 📊 Admin Dashboard

Visit **WordPress Admin → Chatbot** to:

- ✅ Check configuration status (Groq API and Firebase)
- 📈 View recent chat conversations
- 👥 Monitor user interactions
- 📊 Analyze common questions and responses
- 🔧 Debug connection issues

## 🛠️ Features in Detail

### AI Assistant
- **Model**: LLaMA 3 (8B parameters) via Groq
- **Speed**: Ultra-fast responses (typically < 1 second)
- **Context**: Store-aware with WooCommerce integration
- **Fallback**: Rule-based responses when API unavailable
- **Customizable**: Modify AI personality and responses

### Security & Performance
- 🔒 **Secure API Storage**: Keys never exposed in frontend
- ⚡ **Rate Limiting**: 10 messages/minute per user
- 🛡️ **Input Validation**: Message length and content checking
- 📝 **Request Logging**: All interactions logged for monitoring
- 🚀 **Optimized**: Minimal resource usage

### User Experience
- 💬 **Floating Widget**: Non-intrusive chat button
- 📱 **Mobile Responsive**: Works perfectly on all devices
- 🎨 **Modern Design**: Clean, professional interface
- ⌨️ **Keyboard Shortcuts**: Enter to send messages
- 🔄 **Auto-scroll**: Messages automatically scroll into view

## 🔌 API Integration

### Direct API Endpoint

```bash
# External API Access
curl -X POST "https://yoursite.com/wp-content/plugins/nexcart-chatbot/chat-api.php?action=nexcart_api" \
     -H "Content-Type: application/json" \
     -d '{"message": "What products do you recommend?"}'
```

**Response:**
```json
{
  "reply": "I'd be happy to help you find the perfect products! Based on our current popular items..."
}
```

### WordPress AJAX Integration

The plugin integrates seamlessly with WordPress AJAX:

```javascript
// Frontend JavaScript Integration
jQuery.post(nexcart_ajax.ajax_url, {
    action: 'nexcart_chat',
    message: 'Hello!',
    chat_id: 'unique-chat-id',
    nonce: nexcart_ajax.nonce
}, function(response) {
    console.log(response.data.response);
});
```

## File Structure

```
nexcart-chatbot/
├── nexcart-chatbot.php         ← Main plugin file
├── assets/
│   └── chatbot.js              ← Frontend JavaScript with Firebase integration
├── chat-api.php                ← AI API handler with JSON endpoint
├── admin-dashboard.html        ← Admin interface for live chat
└── README.md                   ← This file
```

## Customization

### Styling

The chatbot CSS is included inline in the main plugin file. You can customize colors, sizes, and positioning by modifying the `get_chatbot_css()` method.

### AI Responses

Modify the fallback responses in `chat-api.php` in the `get_fallback_response()` method to customize automated replies.

### Firebase Data Structure

```
firebase-project/
├── chats/
│   └── {chatId}/
│       ├── userId: "user_123"
│       ├── userName: "John Doe"
│       ├── status: "active"
│       ├── startTime: timestamp
│       └── messages/
│           └── {messageId}/
│               ├── text: "Hello"
│               ├── sender: "user|admin"
│               ├── senderName: "John Doe"
│               └── timestamp: timestamp
└── admin/
    └── status/
        ├── online: true
        └── timestamp: timestamp
```

## Troubleshooting

### Common Issues

1. **Firebase not connecting**: Check your Firebase configuration and database rules
2. **AI not responding**: Verify your API key is correct and you have credits
3. **Chat not appearing**: Ensure WooCommerce is installed and activated
4. **Styles not loading**: Check for theme conflicts and CSS overrides

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for errors.

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- Modern browser with JavaScript enabled
- Firebase project (for live chat)
- OpenAI or Grok API key (for AI responses)

## Support

For issues and feature requests, please check the plugin documentation or contact support.

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Firebase integration for live chat
- AI assistant with OpenAI/Grok support
- WooCommerce integration
- Admin dashboard for live chat management
- Responsive design and modern UI
