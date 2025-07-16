<?php
/**
 * Plugin Name: NexCart Chatbot
 * Plugin URI: https://your-website.com/nexcart-chatbot
 * Description: A secure and modular WordPress plugin that adds a floating chatbox to the frontend. It allows users to chat with an AI assistant powered by Groq AI (LLaMA 3 model), and stores conversations in Firebase Realtime Database. Messages are securely routed through a PHP backend endpoint that keeps the API key safe.
 * Version: 1.0.1
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nexcart-chatbot
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Woo: 8.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Define plugin constants
define('NEXCART_CHATBOT_VERSION', '1.0.1');
define('NEXCART_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEXCART_CHATBOT_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main NexCart Chatbot Class
 */
class NexCart_Chatbot {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'add_chatbot_html'));
        
        // AJAX hooks
        add_action('wp_ajax_nexcart_chat', array($this, 'handle_chat_request'));
        add_action('wp_ajax_nopriv_nexcart_chat', array($this, 'handle_chat_request'));
        add_action('wp_ajax_nexcart_support_message', array($this, 'handle_support_message'));
        add_action('wp_ajax_nexcart_check_support_status', array($this, 'check_support_status'));
        
        // Add admin menu for monitoring chats
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('nexcart-chatbot', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on frontend
        if (!is_admin()) {
            // Load main chatbot script first
            wp_enqueue_script(
                'nexcart-chatbot-js',
                NEXCART_CHATBOT_PLUGIN_URL . 'assets/chatbot.js',
                array('jquery'),
                NEXCART_CHATBOT_VERSION,
                true
            );
            
            // Get Firebase configuration
            $firebase_config = $this->get_firebase_config();
            
            // Localize script with AJAX URL, nonce, and Firebase config
            wp_localize_script('nexcart-chatbot-js', 'nexcart_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nexcart_chat_nonce'),
                'loading_text' => __('Typing...', 'nexcart-chatbot'),
                'firebase_config' => $firebase_config,
                'user_id' => $this->get_user_identifier(),
                'user_name' => $this->get_user_name(),
                'chat_endpoint' => NEXCART_CHATBOT_PLUGIN_URL . 'chat-api.php',
                'groq_enabled' => defined('GROQ_API_KEY') && !empty(GROQ_API_KEY),
                'support_ajax_url' => admin_url('admin-ajax.php'),
                'firebase_sdk_urls' => array(
                    'app' => 'https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js',
                    'database' => 'https://www.gstatic.com/firebasejs/9.0.0/firebase-database-compat.js'
                )
            ));
            
            // Add inline CSS
            wp_add_inline_style('wp-block-library', $this->get_chatbot_css());
        }
    }
    
    /**
     * Add chatbot HTML to footer
     */
    public function add_chatbot_html() {
        ?>
        <div id="nexcart-chatbot-container">
            <div id="nexcart-chatbot-toggle">
                <span>🤖</span>
                <div id="nexcart-groq-indicator" class="groq-active"></div>
            </div>
            <div id="nexcart-chatbot-widget" style="display: none;">
                <div id="nexcart-chatbot-header">
                    <div>
                        <h4><?php _e('NexCart Assistant', 'nexcart-chatbot'); ?></h4>
                        <div id="nexcart-chat-mode">
                            <label>
                                <input type="radio" name="chat_mode" value="ai" checked>
                                🤖 <?php _e('AI Chat', 'nexcart-chatbot'); ?>
                            </label>
                            <label>
                                <input type="radio" name="chat_mode" value="live">
                                👨‍💼 <?php _e('Live Support', 'nexcart-chatbot'); ?>
                            </label>
                        </div>
                        <div id="nexcart-mode-info">
                            <small id="nexcart-ai-info"><?php _e('Powered by Groq AI (LLaMA 3)', 'nexcart-chatbot'); ?></small>
                            <small id="nexcart-live-info" style="display: none;"><?php _e('Connect with our support team', 'nexcart-chatbot'); ?></small>
                        </div>
                    </div>
                    <button id="nexcart-chatbot-close">&times;</button>
                </div>
                <div id="nexcart-chatbot-messages"></div>
                <div id="nexcart-connection-status" style="display: none;">
                    <div id="nexcart-connecting">
                        <span class="nexcart-status-indicator">⏳</span>
                        <?php _e('Connecting to support...', 'nexcart-chatbot'); ?>
                    </div>
                    <div id="nexcart-connected" style="display: none;">
                        <span class="nexcart-status-indicator">🟢</span>
                        <?php _e('Connected to support agent', 'nexcart-chatbot'); ?>
                    </div>
                    <div id="nexcart-offline" style="display: none;">
                        <span class="nexcart-status-indicator">🔴</span>
                        <?php _e('Support is offline. Try AI chat or leave a message.', 'nexcart-chatbot'); ?>
                    </div>
                </div>
                <div id="nexcart-chatbot-input-container">
                    <input type="text" id="nexcart-chatbot-input" placeholder="<?php _e('Type your message...', 'nexcart-chatbot'); ?>">
                    <button id="nexcart-chatbot-send"><?php _e('Send', 'nexcart-chatbot'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle chat requests
     */
    public function handle_chat_request() {
        // Enhanced security: Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nexcart_chat_nonce')) {
            wp_die(__('Security check failed', 'nexcart-chatbot'), __('Security Error', 'nexcart-chatbot'), array('response' => 403));
        }
        
        // Validate required fields
        if (!isset($_POST['message']) || !isset($_POST['chat_id'])) {
            wp_send_json_error(array('message' => __('Missing required fields', 'nexcart-chatbot')));
        }
        
        $message = sanitize_text_field($_POST['message']);
        $chat_id = sanitize_text_field($_POST['chat_id']);
        
        // Additional validation
        if (empty(trim($message))) {
            wp_send_json_error(array('message' => __('Message cannot be empty', 'nexcart-chatbot')));
        }
        
        if (strlen($message) > 1000) {
            wp_send_json_error(array('message' => __('Message too long - maximum 1000 characters', 'nexcart-chatbot')));
        }
        
        // Rate limiting per user
        $rate_limit_passed = $this->check_rate_limit();
        if (!$rate_limit_passed) {
            wp_send_json_error(array('message' => __('Too many requests. Please wait a moment.', 'nexcart-chatbot')));
        }
        
        // Store message in database for admin monitoring
        $this->store_chat_message($chat_id, $message, 'user');
        
        // Include the chat API file
        require_once NEXCART_CHATBOT_PLUGIN_PATH . 'chat-api.php';
        
        // Get AI response
        $chat_api = new NexCart_Chat_API();
        $response = $chat_api->get_groq_response($message);
        
        // Store AI response in database
        $this->store_chat_message($chat_id, $response, 'ai');
        
        wp_send_json_success(array('response' => $response));
    }
    
    /**
     * Check rate limit for current user
     */
    private function check_rate_limit() {
        $user_id = $this->get_user_identifier();
        $rate_limit_key = 'nexcart_rate_limit_' . md5($user_id);
        $current_requests = get_transient($rate_limit_key) ?: 0;
        
        // 10 requests per minute per user
        if ($current_requests >= 10) {
            return false;
        }
        
        // Update rate limit counter
        set_transient($rate_limit_key, $current_requests + 1, 60);
        return true;
    }
    
    /**
     * Handle support message requests
     */
    public function handle_support_message() {
        // Enhanced security: Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nexcart_chat_nonce')) {
            wp_die(__('Security check failed', 'nexcart-chatbot'), __('Security Error', 'nexcart-chatbot'), array('response' => 403));
        }
        
        // Validate required fields
        if (!isset($_POST['message']) || !isset($_POST['chat_id'])) {
            wp_send_json_error(array('message' => __('Missing required fields', 'nexcart-chatbot')));
        }
        
        $message = sanitize_text_field($_POST['message']);
        $chat_id = sanitize_text_field($_POST['chat_id']);
        $user_id = get_current_user_id();
        
        // Only allow admins/support agents to send support messages
        if (!current_user_can('manage_options') && !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'nexcart-chatbot')));
        }
        
        // Store support message in database
        $this->store_chat_message($chat_id, $message, 'support');
        
        wp_send_json_success(array('message' => __('Message sent successfully', 'nexcart-chatbot')));
    }
    
    /**
     * Check support status
     */
    public function check_support_status() {
        // Check if any support agents are online
        $support_online = $this->is_support_online();
        
        wp_send_json_success(array(
            'online' => $support_online,
            'message' => $support_online ? __('Support is available', 'nexcart-chatbot') : __('Support is offline', 'nexcart-chatbot')
        ));
    }
    
    /**
     * Check if support is online
     */
    private function is_support_online() {
        // Check if any admin/editor users are currently active
        $current_time = current_time('timestamp');
        $online_threshold = $current_time - (15 * 60); // 15 minutes
        
        $online_users = get_users(array(
            'meta_query' => array(
                array(
                    'key' => 'last_activity',
                    'value' => $online_threshold,
                    'compare' => '>='
                )
            ),
            'role__in' => array('administrator', 'editor')
        ));
        
        return !empty($online_users);
    }
    
    /**
     * Store chat message in database for admin monitoring
     */
    private function store_chat_message($chat_id, $message, $sender) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nexcart_chat_logs';
        
        // Determine sender details
        $user_id = 0;
        $sender_name = '';
        
        if ($sender === 'user') {
            $user_id = is_user_logged_in() ? get_current_user_id() : 0;
            $sender_name = is_user_logged_in() ? wp_get_current_user()->display_name : 'Guest';
        } elseif ($sender === 'support') {
            $user_id = get_current_user_id();
            $sender_name = wp_get_current_user()->display_name ?: 'Support Agent';
        } elseif ($sender === 'ai') {
            $sender_name = 'AI Assistant';
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'chat_id' => $chat_id,
                'message' => $message,
                'sender' => $sender,
                'sender_name' => $sender_name,
                'user_id' => $user_id,
                'user_ip' => $_SERVER['REMOTE_ADDR'],
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Get Firebase configuration
     */
    private function get_firebase_config() {
        // You can set these in wp-config.php or plugin settings
        // Default values are set to your Firebase project
        return array(
            'apiKey' => get_option('nexcart_firebase_api_key', 'AIzaSyCLIFGF0KmZfLP9LhHpcWugHgk3qKFqIy0'),
            'authDomain' => get_option('nexcart_firebase_auth_domain', 'nexcart-chat.firebaseapp.com'),
            'databaseURL' => get_option('nexcart_firebase_database_url', 'https://nexcart-chat-default-rtdb.firebaseio.com'),
            'projectId' => get_option('nexcart_firebase_project_id', 'nexcart-chat'),
            'storageBucket' => get_option('nexcart_firebase_storage_bucket', 'nexcart-chat.firebasestorage.app'),
            'messagingSenderId' => get_option('nexcart_firebase_messaging_sender_id', '71577709809'),
            'appId' => get_option('nexcart_firebase_app_id', '1:71577709809:web:9498a3dcf9d8b667644d50')
        );
    }
    
    /**
     * Get unique user identifier
     */
    private function get_user_identifier() {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        // For anonymous users, create/get session-based ID
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['nexcart_user_id'])) {
            $_SESSION['nexcart_user_id'] = 'guest_' . uniqid();
        }
        
        return $_SESSION['nexcart_user_id'];
    }
    
    /**
     * Get user display name
     */
    private function get_user_name() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return $user->display_name;
        }
        
        return __('Guest', 'nexcart-chatbot');
    }
    
    /**
     * Check if admin is online (placeholder - implement your logic)
     */
    private function is_admin_online() {
        // This can be enhanced to check actual admin presence in Firebase
        return get_option('nexcart_admin_online_status', false);
    }
    
    /**
     * Get chatbot CSS
     */
    private function get_chatbot_css() {
        return '
        #nexcart-chatbot-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        #nexcart-chatbot-toggle {
            width: 60px;
            height: 60px;
            background: #0073aa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            position: relative;
        }
        
        #nexcart-chatbot-toggle:hover {
            transform: scale(1.05);
            background: #005a87;
        }
        
        #nexcart-chatbot-toggle span {
            font-size: 24px;
            color: white;
        }
        
        #nexcart-chatbot-widget {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 350px;
            height: 450px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        #nexcart-chatbot-header {
            background: #0073aa;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        #nexcart-chatbot-header h4 {
            margin: 0;
            font-size: 16px;
        }
        
        #nexcart-chatbot-close {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
        }
        
        #nexcart-chatbot-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: #f9f9f9;
        }
        
        .nexcart-message {
            margin-bottom: 15px;
            max-width: 80%;
        }
        
        .nexcart-message.user {
            margin-left: auto;
        }
        
        .nexcart-message-content {
            padding: 10px 15px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .nexcart-message.user .nexcart-message-content {
            background: #0073aa;
            color: white;
        }
        
        .nexcart-message.bot .nexcart-message-content {
            background: white;
            color: #333;
            border: 1px solid #e1e1e1;
        }
        
        #nexcart-chatbot-input-container {
            padding: 15px;
            background: white;
            border-top: 1px solid #e1e1e1;
            display: flex;
            gap: 10px;
        }
        
        #nexcart-chatbot-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
            font-size: 14px;
        }
        
        #nexcart-chatbot-input:focus {
            border-color: #0073aa;
        }
        
        #nexcart-chatbot-send {
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
        }
        
        #nexcart-chatbot-send:hover {
            background: #005a87;
        }
        
        #nexcart-groq-indicator {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid white;
            background: #00d4aa;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        #nexcart-ai-info {
            margin-top: 2px;
        }
        
        #nexcart-ai-info small {
            color: rgba(255,255,255,0.8);
            font-size: 11px;
        }
        
        .nexcart-message.ai .nexcart-message-content {
            background: #00d4aa;
            color: white;
        }
        
        .nexcart-typing {
            background: linear-gradient(90deg, #00d4aa, #0099cc);
            background-size: 200% 200%;
            animation: gradient 2s ease infinite;
            color: white;
        }
        
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        #nexcart-admin-status {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        #nexcart-admin-status.admin-online {
            background: #28a745;
        }
        
        #nexcart-admin-status.admin-offline {
            background: #dc3545;
        }
        
        #nexcart-chat-mode {
            display: flex;
            gap: 15px;
            margin-top: 8px;
            margin-bottom: 4px;
        }
        
        #nexcart-chat-mode label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            color: rgba(255,255,255,0.9);
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        #nexcart-chat-mode label:hover {
            background: rgba(255,255,255,0.1);
        }
        
        #nexcart-chat-mode label.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        #nexcart-chat-mode input[type="radio"] {
            margin: 0;
            transform: scale(0.8);
        }
        
        #nexcart-mode-info {
            margin-top: 2px;
        }
        
        #nexcart-mode-info small {
            color: rgba(255,255,255,0.8);
            font-size: 11px;
        }
        
        #nexcart-connection-status {
            background: #f0f0f0;
            padding: 8px 15px;
            border-bottom: 1px solid #e1e1e1;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nexcart-status-indicator {
            font-size: 14px;
        }
        
        #nexcart-connecting {
            color: #666;
        }
        
        #nexcart-connected {
            color: #28a745;
        }
        
        #nexcart-offline {
            color: #dc3545;
        }
        
        #nexcart-admin-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: 3px;
        }
        
        #nexcart-admin-indicator.online {
            background: #28a745;
        }
        
        #nexcart-admin-indicator.offline {
            background: #dc3545;
        }
        
        .nexcart-message.admin .nexcart-message-content {
            background: #28a745;
            color: white;
        }
        
        .nexcart-message.support .nexcart-message-content {
            background: #17a2b8;
            color: white;
        }
        
        .nexcart-message.system .nexcart-message-content {
            background: #6c757d;
            color: white;
            font-style: italic;
        }
        
        .nexcart-message-sender {
            font-size: 11px;
            color: #666;
            margin-bottom: 3px;
            font-weight: bold;
        }
        
        /* Enhanced message content styling */
        .nexcart-message-content strong {
            font-weight: bold;
        }
        
        .nexcart-message-content em {
            font-style: italic;
        }
        
        .nexcart-message-content a {
            color: #0073aa;
            text-decoration: none;
        }
        
        .nexcart-message-content a:hover {
            text-decoration: underline;
        }
        
        /* Product cards within messages */
        .nexcart-product-card {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .nexcart-product-image {
            max-width: 60px;
            height: 60px;
            border-radius: 6px;
            object-fit: cover;
            flex-shrink: 0;
        }
        
        .nexcart-product-info {
            flex: 1;
            min-width: 0;
        }
        
        .nexcart-product-name {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 14px;
            line-height: 1.3;
        }
        
        .nexcart-product-price {
            color: #00d4aa;
            font-weight: bold;
            font-size: 15px;
            margin-bottom: 6px;
        }
        
        .nexcart-product-desc {
            font-size: 12px;
            color: rgba(255,255,255,0.8);
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .nexcart-buy-btn {
            background: #00d4aa;
            color: white !important;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .nexcart-buy-btn:hover {
            background: #00b894;
            color: white !important;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,212,170,0.3);
        }
        
        /* AI message product cards styling */
        .nexcart-message.ai .nexcart-product-card {
            background: rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .nexcart-message.ai .nexcart-product-desc {
            color: rgba(0,0,0,0.6);
        }
        ';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database table for chat logs
        $this->create_chat_logs_table();
        
        // Set default options
        if (!get_option('nexcart_chatbot_settings')) {
            add_option('nexcart_chatbot_settings', array(
                'enabled' => true,
                'welcome_message' => __('Hello! 👋 I\'m your AI assistant powered by Groq AI. How can I help you today?', 'nexcart-chatbot'),
                'rate_limit' => 10 // messages per minute per user
            ));
        }
    }
    
    /**
     * Create chat logs table
     */
    private function create_chat_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nexcart_chat_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            chat_id varchar(100) NOT NULL,
            message text NOT NULL,
            sender varchar(10) NOT NULL,
            sender_name varchar(100) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_ip varchar(45) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY chat_id (chat_id),
            KEY timestamp (timestamp),
            KEY sender (sender)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('NexCart Chatbot requires WooCommerce to be installed and activated.', 'nexcart-chatbot'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Add admin menu for chat monitoring
     */
    public function add_admin_menu() {
        add_menu_page(
            __('NexCart Chatbot', 'nexcart-chatbot'),
            __('Chatbot', 'nexcart-chatbot'),
            'manage_options',
            'nexcart-chatbot',
            array($this, 'admin_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    /**
     * Admin page for chat monitoring
     */
    public function admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nexcart_chat_logs';
        
        // Get recent chats
        $chats = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 100"
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('NexCart Chatbot Dashboard', 'nexcart-chatbot'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Chat Mode Configuration', 'nexcart-chatbot'); ?></h2>
                <p>
                    <strong><?php _e('Available Modes:', 'nexcart-chatbot'); ?></strong>
                </p>
                <ul>
                    <li>🤖 <strong><?php _e('AI Chat:', 'nexcart-chatbot'); ?></strong> <?php _e('Instant responses powered by Groq AI', 'nexcart-chatbot'); ?></li>
                    <li>👨‍💼 <strong><?php _e('Live Support:', 'nexcart-chatbot'); ?></strong> <?php _e('Real-time chat with support agents', 'nexcart-chatbot'); ?></li>
                </ul>
                <p>
                    <strong><?php _e('Support Status:', 'nexcart-chatbot'); ?></strong>
                    <?php if ($this->is_support_online()): ?>
                        <span style="color: green;">🟢 <?php _e('Online', 'nexcart-chatbot'); ?></span>
                    <?php else: ?>
                        <span style="color: orange;">🟠 <?php _e('Offline', 'nexcart-chatbot'); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="card">
                <h2><?php _e('Configuration Status', 'nexcart-chatbot'); ?></h2>
                <p>
                    <strong><?php _e('Groq API:', 'nexcart-chatbot'); ?></strong>
                    <?php if (defined('GROQ_API_KEY') && !empty(GROQ_API_KEY)): ?>
                        <span style="color: green;">✓ <?php _e('Configured', 'nexcart-chatbot'); ?></span>
                    <?php else: ?>
                        <span style="color: red;">✗ <?php _e('Not configured - Add GROQ_API_KEY to wp-config.php', 'nexcart-chatbot'); ?></span>
                    <?php endif; ?>
                </p>
                <p>
                    <strong><?php _e('Firebase:', 'nexcart-chatbot'); ?></strong>
                    <?php 
                    $firebase_config = $this->get_firebase_config();
                    if (!empty($firebase_config['apiKey'])): ?>
                        <span style="color: green;">✓ <?php _e('Configured', 'nexcart-chatbot'); ?></span>
                    <?php else: ?>
                        <span style="color: orange;">⚠ <?php _e('Not fully configured', 'nexcart-chatbot'); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="card">
                <h2><?php _e('Recent Chat Messages', 'nexcart-chatbot'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'nexcart-chatbot'); ?></th>
                            <th><?php _e('Chat ID', 'nexcart-chatbot'); ?></th>
                            <th><?php _e('Sender', 'nexcart-chatbot'); ?></th>
                            <th><?php _e('Message', 'nexcart-chatbot'); ?></th>
                            <th><?php _e('User', 'nexcart-chatbot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($chats)): ?>
                            <?php foreach ($chats as $chat): ?>
                                <tr>
                                    <td><?php echo esc_html($chat->timestamp); ?></td>
                                    <td><?php echo esc_html(substr($chat->chat_id, 0, 10) . '...'); ?></td>
                                    <td>
                                        <span class="<?php echo $chat->sender === 'user' ? 'user-message' : 'ai-message'; ?>">
                                            <?php echo esc_html(ucfirst($chat->sender)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(wp_trim_words($chat->message, 10)); ?></td>
                                    <td>
                                        <?php if ($chat->user_id > 0): ?>
                                            <?php 
                                            $user = get_user_by('id', $chat->user_id);
                                            echo esc_html($user ? $user->display_name : 'Unknown');
                                            ?>
                                        <?php else: ?>
                                            <?php _e('Guest', 'nexcart-chatbot'); ?> (<?php echo esc_html($chat->user_ip); ?>)
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5"><?php _e('No chat messages yet.', 'nexcart-chatbot'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
            .user-message { color: #0073aa; font-weight: bold; }
            .ai-message { color: #28a745; font-weight: bold; }
            .card { background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; }
        </style>
        <?php
    }
}

// Initialize the plugin
new NexCart_Chatbot();
