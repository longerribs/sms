<?php
/**
 * clayon/db.php
 * 
 * Specialized Database Connection for Clayon.
 * Sets the timezone to Africa/Nairobi for consistent scheduling.
 */

require_once __DIR__ . '/env_loader.php';

// Set PHP Timezone
date_default_timezone_set('Africa/Nairobi');

function getClayonDb() {
    static $db = null;
    if ($db === null) {
        $host = clayon_env('DB_HOST', 'localhost');
        $name = clayon_env('DB_DATABASE', 'clayon_sms');
        $user = clayon_env('DB_USERNAME', 'root');
        $pass = clayon_env('DB_PASSWORD', '');
        
        try {
            $db = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Set MySQL session timezone to match PHP
            $db->exec("SET time_zone = '+03:00'");
            
        } catch (PDOException $e) {
            die("Clayon Database Connection Failed: " . $e->getMessage());
        }
    }
    return $db;
}
