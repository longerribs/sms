<?php
/**
 * clayon/config/Validator.php
 * 
 * Input validation and sanitization.
 */

class Validator {
    public static $errors = [];

    /**
     * Validate phone number (basic E.164 format)
     */
    public static function isValidPhone($phone) {
        return preg_match('/^\+?[1-9]\d{1,14}$/', str_replace([' ', '-', '(', ')'], '', $phone));
    }

    /**
     * Validate sender ID (alphanumeric, 2-20 chars)
     */
    public static function isValidSenderId($senderId) {
        return preg_match('/^[a-zA-Z0-9]{2,20}$/', $senderId);
    }

    /**
     * Validate email
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validate message (not empty, reasonable length)
     */
    public static function isValidMessage($message) {
        $len = mb_strlen($message);
        return $len > 0 && $len <= 4300; // Max 3 segments in standard mode
    }

    /**
     * Calculate SMS segments
     */
    public static function calculateSegments($message) {
        $len = mb_strlen($message);
        if ($len <= Config::SMS_CHARS_PER_SEGMENT) {
            return 1;
        }
        return ceil($len / Config::SMS_CHARS_MULTIPART);
    }

    /**
     * Validate incoming JSON payload
     */
    public static function validateSmsRequest(&$data) {
        self::$errors = [];

        if (empty($data['recipient'])) {
            self::$errors['recipient'] = 'Recipient phone number is required';
        } elseif (!self::isValidPhone($data['recipient'])) {
            self::$errors['recipient'] = 'Invalid phone number format';
        }

        if (empty($data['message'])) {
            self::$errors['message'] = 'Message is required';
        } elseif (!self::isValidMessage($data['message'])) {
            self::$errors['message'] = 'Message is too long or invalid';
        }

        $senderId = trim((string)($data['sender_id'] ?? ''));
        if ($senderId === '') {
            $data['sender_id'] = 'TALKSASA';
        } elseif (!self::isValidSenderId($senderId)) {
            self::$errors['sender_id'] = 'Invalid sender ID format';
        } else {
            $data['sender_id'] = strtoupper($senderId);
        }

        return empty(self::$errors);
    }

    /**
     * Validate payment topup request
     */
    public static function validateTopupRequest($data) {
        self::$errors = [];

        if (empty($data['amount']) || !is_numeric($data['amount'])) {
            self::$errors['amount'] = 'Valid amount is required';
        } elseif ($data['amount'] < Config::MIN_TOPUP_AMOUNT) {
            self::$errors['amount'] = "Minimum topup is " . Config::MIN_TOPUP_AMOUNT;
        }

        if (empty($data['phone']) || !self::isValidPhone($data['phone'])) {
            self::$errors['phone'] = 'Valid phone number is required';
        }

        return empty(self::$errors);
    }

    /**
     * Get validation errors
     */
    public static function getErrors() {
        return self::$errors;
    }
}
