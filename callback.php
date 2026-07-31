<?php
/**
 * sms/callback.php
 * 
 * Public entry point for M-Pesa callbacks.
 * Proxies request to the actual logic in src/PaymentCallback.php
 */

require_once __DIR__ . '/src/PaymentCallback.php';
