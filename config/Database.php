<?php
/**
 * clayon/config/Database.php
 * 
 * Database connection factory and utilities.
 */

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Load .env2 for SMS module (separate from MLM)
        $envFile = __DIR__ . '/../.env2';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }
        
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: 3306;
        $database = getenv('DB_DATABASE') ?: 'clayon_sms';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4; SET time_zone = '+03:00';",
            ]);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Initialize the database schema
     */
    public function initializeSchema() {
        $schema = file_get_contents(__DIR__ . '/../sql/schema.sql');
        $statements = array_filter(
            array_map('trim', preg_split('/;[\s\n]+/', $schema))
        );

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $this->pdo->exec($statement . ';');
                } catch (PDOException $e) {
                    error_log("Schema Error: " . $e->getMessage() . " | Statement: $statement");
                }
            }
        }
    }
}

function getSmsDb() {
    return Database::getInstance()->getConnection();
}

function getDb() {
    return getSmsDb();
}
