<?php
/**
 * clayon/src/Auth.php
 * 
 * Authentication and API Key management for Clayon.
 */

class Auth {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Generate a new API Key for a client
     * Format: sk_live_[random_hex]
     */
    public function generateKey($clientId) {
        $prefix = 'sk_live_';
        $random = bin2hex(random_bytes(16));
        $plainKey = $prefix . $random;
        $hash = password_hash($plainKey, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->db->prepare("INSERT INTO client_api_keys (client_id, key_hash, key_prefix, plain_api_key) VALUES (?, ?, ?, ?)");
            $stmt->execute([$clientId, $hash, substr($plainKey, 0, 12), $plainKey]);
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Unknown column') !== false || stripos($e->getMessage(), 'doesn\'t exist') !== false) {
                $stmt = $this->db->prepare("INSERT INTO client_api_keys (client_id, key_hash, key_prefix) VALUES (?, ?, ?)");
                $stmt->execute([$clientId, $hash, substr($plainKey, 0, 12)]);
            } else {
                throw $e;
            }
        }
        
        return $plainKey;
    }

    /**
     * Robustly extract Bearer token from headers
     */
    public static function getBearerToken() {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } else if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            } elseif (isset($requestHeaders['authorization'])) {
                $headers = trim($requestHeaders['authorization']);
            }
        }
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Validate an API Key
     */
    public function validate($plainKey) {
        if (!$plainKey) return false;

        $prefix = substr($plainKey, 0, 12);
        
        $stmt = $this->db->prepare("SELECT * FROM client_api_keys WHERE key_prefix = ? AND status = 'active'");
        $stmt->execute([$prefix]);
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($keys as $keyData) {
            if (password_verify($plainKey, $keyData['key_hash'])) {
                $this->db->prepare("UPDATE client_api_keys SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?")
                         ->execute([$keyData['id']]);
                return $keyData['client_id'];
            }

            if (!empty($keyData['plain_api_key']) && $keyData['plain_api_key'] === $plainKey) {
                $newHash = password_hash($plainKey, PASSWORD_DEFAULT);
                $this->db->prepare("UPDATE client_api_keys SET key_hash = ?, last_used_at = CURRENT_TIMESTAMP WHERE id = ?")
                         ->execute([$newHash, $keyData['id']]);
                return $keyData['client_id'];
            }
        }
        
        return false;
    }
}
