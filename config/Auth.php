<?php
/**
 * sms/config/Auth.php
 * 
 * API authentication and authorization.
 */

class Auth {
    private static $client = null;
    private static $apiKey = null;

    /**
     * Verify and extract Bearer token from request
     */
    public static function verifyApiKey() {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($auth) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (empty($auth) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (!preg_match('/Bearer\s+(.+)/', $auth, $matches)) {
            Response::unauthorized('Missing or invalid Authorization header');
        }

        $token = $matches[1];
        $client = self::validateToken($token);

        if (!$client) {
            Response::unauthorized('Invalid or revoked API key');
        }

        // Check if client is active
        if ($client['status'] !== 'active') {
            Response::forbidden('Client account is suspended or inactive');
        }

        self::$client = $client;
        self::$apiKey = $token;

        return $client;
    }

    /**
     * Validate token against client_api_keys table using Bcrypt
     */
    private static function validateToken($token) {
        try {
            if (!$token) return null;

            // Extract prefix (12 characters, e.g. sk_live_f335)
            $prefix = substr($token, 0, 12);
            $db = getSmsDb();

            $stmt = $db->prepare("
                SELECT ck.id, ck.key_hash, ck.plain_api_key, c.id AS client_id, c.name, c.email, c.plan_id, c.status 
                FROM client_api_keys ck
                JOIN clients c ON ck.client_id = c.id
                WHERE ck.key_prefix = ? AND ck.status = 'active'
            ");
            $stmt->execute([$prefix]);
            $keys = $stmt->fetchAll();

            foreach ($keys as $keyData) {
                if (password_verify($token, $keyData['key_hash'])) {
                    $updateStmt = $db->prepare("UPDATE client_api_keys SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $updateStmt->execute([$keyData['id']]);

                    return [
                        'id' => $keyData['client_id'],
                        'name' => $keyData['name'],
                        'email' => $keyData['email'],
                        'plan_id' => $keyData['plan_id'],
                        'status' => $keyData['status']
                    ];
                }

                if (!empty($keyData['plain_api_key']) && $keyData['plain_api_key'] === $token) {
                    $newHash = password_hash($token, PASSWORD_DEFAULT);
                    $updateStmt = $db->prepare("UPDATE client_api_keys SET key_hash = ?, last_used_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $updateStmt->execute([$newHash, $keyData['id']]);

                    return [
                        'id' => $keyData['client_id'],
                        'name' => $keyData['name'],
                        'email' => $keyData['email'],
                        'plan_id' => $keyData['plan_id'],
                        'status' => $keyData['status']
                    ];
                }
            }

            return null;
        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return null;
        }
    }

    public static function getCurrentClient() {
        return self::$client;
    }

    public static function getCurrentClientId() {
        return self::$client['id'] ?? null;
    }

    /**
     * Generate new API key for client (Bcrypt compatible)
     */
    public static function generateApiKey($clientId) {
        try {
            $prefix = 'sk_live_';
            $random = bin2hex(random_bytes(16));
            $plainKey = $prefix . $random;
            $hash = password_hash($plainKey, PASSWORD_DEFAULT);
            $keyPrefix = substr($plainKey, 0, 12);

            $db = getSmsDb();
            try {
                $stmt = $db->prepare("
                    INSERT INTO client_api_keys (client_id, key_hash, key_prefix, plain_api_key, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $stmt->execute([$clientId, $hash, $keyPrefix, $plainKey]);
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Unknown column') !== false || stripos($e->getMessage(), 'doesn\'t exist') !== false) {
                    $stmt = $db->prepare("
                        INSERT INTO client_api_keys (client_id, key_hash, key_prefix, status)
                        VALUES (?, ?, ?, 'active')
                    ");
                    $stmt->execute([$clientId, $hash, $keyPrefix]);
                } else {
                    throw $e;
                }
            }

            return [
                'id' => $db->lastInsertId(),
                'key' => $plainKey, // Only returned on creation!
                'key_prefix' => $keyPrefix,
                'created_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("API Key generation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Revoke API key
     */
    public static function revokeApiKey($keyId, $clientId) {
        try {
            $db = getSmsDb();
            $stmt = $db->prepare("
                UPDATE client_api_keys 
                SET status = 'revoked' 
                WHERE id = ? AND client_id = ?
            ");
            return $stmt->execute([$keyId, $clientId]);
        } catch (Exception $e) {
            error_log("API Key revocation error: " . $e->getMessage());
            return false;
        }
    }
}
