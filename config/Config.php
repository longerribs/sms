<?php
/**
 * clayon/config/Config.php
 * 
 * Application configuration and constants.
 */

class Config {
    // Environment
    public const APP_ENV = 'development';
    public const DEBUG = true;

    // API Configuration
    public const API_BASE_URL = '';
    public const API_PREFIX = '/clayon/api';
    
    // SMS Provider (TalkSasa)
    public const TALKSASA_API_URL = 'https://api.talksasa.com/v1/sms/send';
    public const TALKSASA_TIMEOUT = 30;
    
    // Queue Configuration
    public const QUEUE_WORKER_LIMIT = 20; // Jobs to claim per cron run
    public const QUEUE_RETRY_DELAY = 5; // minutes
    public const QUEUE_MAX_ATTEMPTS = 5;
    public const QUEUE_DEAD_LETTER_POLICY = 'manual_review'; // manual_review or refund
    
    // SMS Configuration
    public const SMS_CHARS_PER_SEGMENT = 160; // Standard SMS
    public const SMS_CHARS_MULTIPART = 153; // Multi-part SMS
    
    // Rate Limiting
    public const RATE_LIMIT_PER_MINUTE = 100; // API requests
    public const RATE_LIMIT_PER_HOUR = 10000;
    
    // Pricing (can be overridden in database)
    public const DEFAULT_MARKUP_TYPE = 'percentage'; // percentage or fixed
    public const DEFAULT_MARKUP_VALUE = 25.0; // 25% markup
    public const MIN_TOPUP_AMOUNT = 100.0; // KES

    // Security
    public const API_KEY_LENGTH = 32;
    public const API_KEY_PREFIX = 'clay_';
    public const TOKEN_EXPIRY = 3600; // seconds

    // Notifications (optional)
    public const SEND_SMS_NOTIFICATIONS = false;
    public const SEND_EMAIL_NOTIFICATIONS = true;

    public static function getTalkSasaApiKey() {
        return getenv('TALKSASA_API_KEY') ?: '';
    }

    public static function getMpesaConfig() {
        return [
            'consumer_key' => getenv('MPESA_CONSUMER_KEY'),
            'consumer_secret' => getenv('MPESA_CONSUMER_SECRET'),
            'shortcode' => getenv('MPESA_SHORTCODE'),
            'till_number' => getenv('MPESA_TILL_NUMBER'),
            'passkey' => getenv('MPESA_PASSKEY'),
            'auth_url' => getenv('MPESA_AUTH_URL'),
            'stk_push_url' => getenv('MPESA_STK_PUSH_URL'),
        ];
    }
}
