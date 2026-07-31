<?php
/**
 * clayon/env_loader.php
 * 
 * Specialized Environment Loader for Clayon project.
 * Loads variables from .env2 file — no Composer dependency required.
 */

$envFile = __DIR__ . '/.env2';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments and blank lines
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;

        // Must contain an '=' sign
        if (strpos($line, '=') === false) continue;

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip surrounding quotes (" or ')
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Only set if not already defined (allows env overrides)
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Retrieve an environment variable with an optional default.
 */
function clayon_env(string $key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}
