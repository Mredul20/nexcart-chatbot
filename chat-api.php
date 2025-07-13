<?php
/**
 * NexCart Chat API - Groq AI Integration
 * Handles secure AI responses using Groq's LLaMA 3 model
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Allow direct access for external API calls
    if (isset($_GET['action']) && $_GET['action'] === 'nexcart_api') {
        // Load WordPress
        require_once('../../../wp-load.php');
    } else {
        exit;
    }
}

// Handle direct API calls (for external integrations)
if (isset($_GET['action']) && $_GET['action'] === 'nexcart_api') {
    // Set JSON header
    header('Content-Type: application/json');
    
    // Handle CORS if needed
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        }
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        }
        exit(0);
    }
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['message']) || empty(trim($input['message']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input - message is required']);
        exit;
    }
    
    // Validate message length
    if (strlen($input['message']) > 1000) {
        http_response_code(400);
        echo json_encode(['error' => 'Message too long - maximum 1000 characters']);
        exit;
    }
    
    // Rate limiting check (basic implementation)
    $user_ip = $_SERVER['REMOTE_ADDR'];
    $rate_limit_key = 'nexcart_rate_limit_' . md5($user_ip);
    $current_requests = get_transient($rate_limit_key) ?: 0;
    
    if ($current_requests >= 10) { // 10 requests per minute
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded - please wait a minute']);
        exit;
    }
    
    // Update rate limit counter
    set_transient($rate_limit_key, $current_requests + 1, 60);
    
    try {
        $chat_api = new NexCart_Chat_API();
        $response = $chat_api->get_groq_response($input['message']);
        
        echo json_encode(['reply' => $response]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        error_log('NexCart Chatbot API Error: ' . $e->getMessage());
    }
    
    exit;
}

class NexCart_Chat_API {
    
    private $api_key;
    private $api_endpoint;
    
    public function __construct() {
        // Securely load API key from wp-config.php
        $this->api_key = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
        
        // Groq API endpoint
        $this->api_endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    }
    
    /**
     * Get Groq AI response using LLaMA 3 model
     */
    public function get_groq_response($user_message) {
        // If no API key is configured, use fallback responses
        if (empty($this->api_key)) {
            error_log('NexCart Chatbot: GROQ_API_KEY not defined in wp-config.php');
            return $this->get_fallback_response($user_message);
        }
        
        try {
            // Prepare the context for the AI
            $context = $this->prepare_context();
            
            // Create the messages array for Groq API
            $messages = array(
                array(
                    'role' => 'system',
                    'content' => $context
                ),
                array(
                    'role' => 'user',
                    'content' => $user_message
                )
            );
            
            // Make API request to Groq
            $response = $this->make_groq_request($messages);
            
            if ($response && isset($response['choices'][0]['message']['content'])) {
                $ai_response = trim($response['choices'][0]['message']['content']);
                return $this->format_response_with_products($ai_response);
            }
            
        } catch (Exception $e) {
            error_log('NexCart Chatbot Groq API Error: ' . $e->getMessage());
        }
        
        // Fallback to rule-based response if API fails
        return $this->get_fallback_response($user_message);
    }
    
    /**
     * Prepare context for Groq AI
     */
    private function prepare_context() {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        
        // Get some basic store information
        $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '৳';
        $store_info = $this->get_store_info();
        
        $context = "You are an intelligent and helpful AI assistant for {$site_name}, an e-commerce store. ";
        $context .= "You are powered by Groq AI using the LLaMA 3 model. ";
        $context .= "Be friendly, professional, and helpful while maintaining a conversational tone.\n\n";
        
        $context .= "Store Information:\n";
        $context .= "- Store Name: {$site_name}\n";
        $context .= "- Website: {$site_url}\n";
        $context .= "- Currency: {$currency}\n\n";
        
        if (!empty($store_info)) {
            $context .= $store_info . "\n";
        }
        
        $context .= "Guidelines:\n";
        $context .= "- Be helpful and friendly in your responses\n";
        $context .= "- Provide accurate information about products when possible\n";
        $context .= "- If you don't know something specific, politely say so and suggest contacting customer support\n";
        $context .= "- Keep responses concise but informative (under 300 words)\n";
        $context .= "- Use emojis sparingly and appropriately\n";
        $context .= "- Focus on being helpful for e-commerce related questions\n";
        $context .= "- If asked about technical details you're unsure about, recommend contacting support\n";
        $context .= "- Always prioritize customer satisfaction and provide value\n\n";
        
        $context .= "You can help with:\n";
        $context .= "- Product information and recommendations\n";
        $context .= "- General store policies\n";
        $context .= "- Shipping and return information\n";
        $context .= "- Order guidance\n";
        $context .= "- General customer service questions\n";
        
        return $context;
    }
    
    /**
     * Get basic store information
     */
    private function get_store_info() {
        $info = "";
        
        // Get popular products
        $popular_products = $this->get_popular_products(5);
        if (!empty($popular_products)) {
            $info .= "Popular Products:\n";
            foreach ($popular_products as $product) {
                $info .= $this->format_product_card($product);
            }
            $info .= "\n";
        }
        
        // Get basic policies (you can expand this)
        $policies = $this->get_store_policies();
        if (!empty($policies)) {
            $info .= $policies;
        }
        
        return $info;
    }
    
    /**
     * Get popular products
     */
    private function get_popular_products($limit = 5) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'meta_key' => 'total_sales',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'post_status' => 'publish'
        );
        
        $products = get_posts($args);
        $result = array();
        
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if ($product) {
                $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');
                $result[] = array(
                    'name' => $product->get_name(),
                    'price' => wc_price($product->get_price()),
                    'url' => get_permalink($product->get_id()),
                    'image' => $image_url ? $image_url : wc_placeholder_img_src('thumbnail'),
                    'short_description' => wp_trim_words($product->get_short_description(), 15)
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Get store policies
     */
    private function get_store_policies() {
        $policies = "";
        
        // You can customize these based on your store's actual policies
        $policies .= "Store Policies:\n";
        $policies .= "- Free shipping on orders over ৳2000\n";
        $policies .= "- 30-day return policy\n";
        $policies .= "- Customer support available 24/7\n";
        $policies .= "- Secure payment processing\n\n";
        
        return $policies;
    }
    
    /**
     * Make API request to Groq
     */
    private function make_groq_request($messages) {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        $body = array(
            'model' => 'llama3-8b-8192', // Groq's LLaMA 3 model
            'messages' => $messages,
            'max_tokens' => 500,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'stream' => false
        );
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30,
            'method' => 'POST'
        );
        
        $response = wp_remote_post($this->api_endpoint, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('Groq API request failed: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
            throw new Exception("Groq API Error (HTTP $response_code): $error_message");
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Groq API');
        }
        
        return $data;
    }
    
    /**
     * Get fallback response using rule-based logic when Groq AI is unavailable
     */
    private function get_fallback_response($message) {
        $message_lower = strtolower($message);
        
        // Greeting responses
        if (preg_match('/\b(hi|hello|hey|good morning|good afternoon|good evening)\b/', $message_lower)) {
            return "Hello! 👋 Welcome to " . get_bloginfo('name') . "! I'm your AI assistant powered by Groq AI. How can I help you find what you're looking for today?";
        }
        
        // Product search
        if (preg_match('/\b(product|item|find|search|looking for|show me|recommend)\b/', $message_lower)) {
            return $this->handle_product_search($message);
        }
        
        // AI/Technology questions
        if (preg_match('/\b(ai|artificial intelligence|groq|llama|technology)\b/', $message_lower)) {
            return "I'm powered by Groq AI using the LLaMA 3 model! 🤖 This gives me the ability to understand your questions and provide helpful, human-like responses about our store and products. What can I help you with today?";
        }
        
        // Shipping inquiries
        if (preg_match('/\b(shipping|delivery|ship|deliver|freight|postage)\b/', $message_lower)) {
            return "Here's our shipping information:\n\n🚚 **Shipping Options:**\n• Standard shipping (5-7 business days)\n• Express shipping (2-3 business days)\n• Free shipping on orders over ৳2000\n\n📍 Shipping costs are calculated at checkout based on your location. Would you like help with anything specific about shipping?";
        }
        
        // Return/refund inquiries
        if (preg_match('/\b(return|refund|exchange|warranty|guarantee|policy)\b/', $message_lower)) {
            return "Our **return policy** is customer-friendly:\n\n✅ **Easy Returns:**\n• 30-day return window\n• Items must be in original condition\n• Refunds processed within 5-7 business days\n• Free returns for defective items\n\n📞 Need to start a return? I can guide you through the process!";
        }
        
        // Order status
        if (preg_match('/\b(order|status|track|tracking|where is my|shipment)\b/', $message_lower)) {
            return "To check your **order status**:\n\n1️⃣ Visit your account page\n2️⃣ Click on 'Order History'\n3️⃣ Find your order for tracking details\n\n📱 You can also check your email for tracking updates. If you need immediate help with a specific order, please share your order number!";
        }
        
        // Payment inquiries
        if (preg_match('/\b(payment|pay|credit card|paypal|checkout|billing)\b/', $message_lower)) {
            return "We accept multiple **secure payment methods**:\n\n💳 **Payment Options:**\n• Credit/Debit Cards (Visa, MasterCard)\n• bKash\n• Nagad\n• Rocket\n• Bank Transfer\n• Cash on Delivery\n\n🔒 All payments are secured with SSL encryption. Having trouble with checkout? Let me know!";
        }
        
        // Goodbye/thank you
        if (preg_match('/\b(bye|goodbye|thank you|thanks|thx)\b/', $message_lower)) {
            return "You're very welcome! 😊 Thank you for choosing " . get_bloginfo('name') . ". If you need any more help, just ask - I'm here 24/7 powered by Groq AI! Have a wonderful day! 🌟";
        }
        
        // Default response
        return "I'm here to help! 🤖 I'm powered by **Groq AI** and can assist you with:\n\n• 🛍️ Product information and recommendations\n• 📦 Shipping and delivery questions\n• 📊 Order status and tracking\n• 🔄 Return and refund policies\n• 💳 Payment options\n• ℹ️ General store information\n\nWhat would you like to know more about?";
    }
    
    /**
     * Handle product search
     */
    private function handle_product_search($message) {
        // Extract potential product keywords
        $keywords = $this->extract_keywords($message);
        
        if (empty($keywords)) {
            return "I'd love to help you find products! Could you tell me what specific item you're looking for? For example:\n\n• \"Show me running shoes\"\n• \"I need a blue dress\"\n• \"Looking for smartphone accessories\"\n\nWhat can I help you find today?";
        }
        
        // Search for products
        $products = $this->search_products($keywords);
        
        if (empty($products)) {
            // Offer popular products as alternative
            $popular_products = $this->get_popular_products(3);
            if (!empty($popular_products)) {
                $response = "I couldn't find any products matching '{$keywords}' right now. But here are some of our popular items:\n\n";
                foreach ($popular_products as $product) {
                    $response .= $this->format_product_card($product);
                }
                $response .= "\nYou could also try:\n• Using different keywords\n• Browsing our categories\n• Checking our featured products";
                return $response;
            }
            
            return "I couldn't find any products matching '{$keywords}' right now. You could try:\n\n• Using different keywords\n• Browsing our categories\n• Checking our featured products\n\nWould you like me to show you our popular items instead?";
        }
        
        $response = "Here are some products I found for '{$keywords}':\n\n";
        foreach ($products as $product) {
            $response .= $this->format_product_card($product);
        }
        
        $response .= "Would you like more details about any of these products?";
        
        return $response;
    }
    
    /**
     * Extract keywords from message
     */
    private function extract_keywords($message) {
        // Remove common words and extract potential product terms
        $common_words = array('i', 'am', 'looking', 'for', 'find', 'show', 'me', 'want', 'need', 'a', 'an', 'the', 'some', 'any');
        $words = str_word_count(strtolower($message), 1);
        $keywords = array_diff($words, $common_words);
        
        return implode(' ', $keywords);
    }
    
    /**
     * Search products
     */
    private function search_products($keywords, $limit = 5) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            's' => $keywords,
            'post_status' => 'publish'
        );
        
        $products = get_posts($args);
        $result = array();
        
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if ($product) {
                $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');
                $result[] = array(
                    'name' => $product->get_name(),
                    'price' => wc_price($product->get_price()),
                    'url' => get_permalink($product->get_id()),
                    'image' => $image_url ? $image_url : wc_placeholder_img_src('thumbnail'),
                    'short_description' => wp_trim_words($product->get_short_description(), 15)
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Format response with markdown-like styling and product integration
     */
    private function format_response_with_products($response) {
        // Basic markdown-to-HTML conversion
        $response = $this->convert_markdown_to_html($response);
        
        // Look for product mentions and enhance them
        $response = $this->enhance_product_mentions($response);
        
        return $response;
    }
    
    /**
     * Format a product as an enhanced card with buy button
     */
    private function format_product_card($product) {
        $card = '<div class="nexcart-product-card">';
        
        // Product image (if available)
        if (!empty($product['image'])) {
            $card .= '<img src="' . esc_url($product['image']) . '" alt="' . esc_attr($product['name']) . '" class="nexcart-product-image">';
        }
        
        $card .= '<div class="nexcart-product-info">';
        $card .= '<div class="nexcart-product-name">' . esc_html($product['name']) . '</div>';
        $card .= '<div class="nexcart-product-price">' . $product['price'] . '</div>';
        
        // Short description
        if (!empty($product['short_description'])) {
            $card .= '<div class="nexcart-product-desc">' . esc_html($product['short_description']) . '</div>';
        }
        
        // Buy button
        $card .= '<a href="' . esc_url($product['url']) . '" class="nexcart-buy-btn" target="_blank">🛒 Buy Here</a>';
        $card .= '</div>';
        $card .= '</div>';
        
        return $card;
    }
    
    /**
     * Convert basic markdown to HTML (sanitized)
     */
    private function convert_markdown_to_html($text) {
        // Bold text: **text** or __text__
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $text);
        
        // Italic text: *text* or _text_
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.*?)_/', '<em>$1</em>', $text);
        
        // Links: [text](url)
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
        
        // Line breaks
        $text = nl2br($text);
        
        // Sanitize the HTML to prevent XSS
        $allowed_tags = '<strong><em><br><a><p><ul><li><ol><div><img>';
        $text = strip_tags($text, $allowed_tags);
        
        return $text;
    }
    
    /**
     * Enhance product mentions in responses
     */
    private function enhance_product_mentions($response) {
        // This could be enhanced to automatically detect product names
        // and convert them to clickable links
        return $response;
    }
}
