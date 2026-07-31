<?php
/**
 * clayon/setup/create-database.php
 * 
 * Create the clayon_sms database if it doesn't exist
 * Run once: php clayon/setup/create-database.php
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       CLAYON DATABASE CREATION                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    // Connect to MySQL without selecting a database
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: 3306;
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    
    echo "🔗 Connecting to MySQL...\n";
    $pdo = new PDO(
        "mysql:host=$host;port=$port",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "   ✅ Connected\n\n";
    
    // Create database
    $databaseName = 'clayon_sms';
    echo "📦 Creating database: $databaseName\n";
    
    $pdo->exec("DROP DATABASE IF EXISTS `$databaseName`");
    echo "   ✅ Dropped existing database (if any)\n";
    
    $pdo->exec("CREATE DATABASE `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "   ✅ Database created\n\n";
    
    echo "✅ Database creation complete!\n";
    echo "   Database: $databaseName\n";
    echo "   Charset: utf8mb4\n";
    echo "   Collation: utf8mb4_unicode_ci\n\n";
    
    echo "📖 Next step:\n";
    echo "   php clayon/setup/init-clayon-db.php\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
