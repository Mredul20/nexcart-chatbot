# NexCart Chatbot - Groq AI Configuration Guide

## 🚀 Quick Setup

### 1. Get Your Groq API Key

1. Visit [Groq Console](https://console.groq.com/)
2. Sign up or log in to your account
3. Navigate to "API Keys" section
4. Create a new API key
5. Copy your API key securely

### 2. Configure WordPress

Add your Groq API key to your `wp-config.php` file:

```php
// Add this line to your wp-config.php file
define('GROQ_API_KEY', 'your-groq-api-key-here');
```

**Example:**
```php
// NexCart Chatbot Configuration
define('GROQ_API_KEY', 'gsk_abc123xyz789...');

// That's it! The plugin will automatically detect and use this key.
```

### 3. Firebase Configuration (Optional)

If you want to store conversations in Firebase for monitoring:

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

## 🔧 Plugin Features

### Core Functionality
- **AI Model**: LLaMA 3 (8B parameters) via Groq
- **Response Time**: Lightning fast responses
- **Fallback System**: Rule-based responses if API is unavailable
- **Rate Limiting**: 10 messages per minute per user
- **Chat Logging**: WordPress database storage for admin monitoring

### Security Features
- ✅ **Secure API Key Storage**: Keys stored in wp-config.php (not in database)
- ✅ **Input Validation**: Message length and content validation
- ✅ **Rate Limiting**: Prevents spam and abuse
- ✅ **CORS Protection**: Proper headers for external API calls
- ✅ **Nonce Verification**: WordPress security nonces

### Admin Features
- 📊 **Chat Dashboard**: Monitor conversations in WordPress admin
- 📈 **Usage Statistics**: Track AI usage and popular questions
- ⚙️ **Configuration Status**: Check if API keys are properly configured
- 🛠️ **Database Logging**: All conversations stored for analysis

## 📋 API Endpoint

The plugin provides a direct JSON API endpoint for external integrations:

```bash
# Direct API Access
curl -X POST "https://yoursite.com/wp-content/plugins/nexcart-chatbot/chat-api.php?action=nexcart_api" \
     -H "Content-Type: application/json" \
     -d '{"message": "Hello, what products do you have?"}'
```

**Response:**
```json
{
  "reply": "Hello! I'd be happy to help you explore our products. We have a wide variety of items available..."
}
```

## 🎨 Customization

### Styling
The chatbot CSS is included inline and can be customized by modifying the `get_chatbot_css()` method in the main plugin file.

### AI Responses
Customize the AI's personality and knowledge by editing the `prepare_context()` method in `chat-api.php`.

### Fallback Responses
Modify rule-based responses in the `get_fallback_response()` method for when the AI is unavailable.

## 🔍 Troubleshooting

### Common Issues

1. **"Groq API Key not configured"**
   - Ensure `GROQ_API_KEY` is defined in `wp-config.php`
   - Check for typos in the constant name
   - Verify your API key is valid at Groq Console

2. **"Rate limit exceeded"**
   - Users are limited to 10 messages per minute
   - Wait 60 seconds and try again
   - Increase limit in `chat-api.php` if needed

3. **"API request failed"**
   - Check your internet connection
   - Verify Groq API status
   - Check WordPress error logs

### Debug Mode

Enable WordPress debug logging:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for detailed error messages.

## 📊 Monitoring

### Admin Dashboard
Visit **WordPress Admin → Chatbot** to:
- View recent conversations
- Check configuration status
- Monitor AI usage
- Analyze common questions

### Database Storage
Conversations are stored in the `wp_nexcart_chat_logs` table with:
- Chat ID and message content
- User information (logged in users or guest IPs)
- Timestamps for analytics
- Sender type (user or AI)

## 🚀 Performance

### Groq AI Benefits
- **Ultra-fast responses** (typically under 1 second)
- **Cost-effective** compared to OpenAI
- **High-quality LLaMA 3 model** with excellent understanding
- **Reliable uptime** and performance

### Caching
- Fallback responses are cached for performance
- Rate limiting prevents API abuse
- Firebase integration for conversation persistence

## 🔐 Security Best Practices

1. **Never expose your API key** in frontend code or public repositories
2. **Use HTTPS** for all communications
3. **Monitor usage** through the admin dashboard
4. **Set appropriate rate limits** for your use case
5. **Regularly update** the plugin for security patches

## 📞 Support

For issues with the plugin:
1. Check the WordPress admin dashboard for configuration status
2. Review error logs for detailed error messages
3. Verify your Groq API key is valid and has sufficient credits
4. Test the direct API endpoint to isolate issues

---

**Ready to go?** Just add your Groq API key to `wp-config.php` and activate the plugin! 🎉
