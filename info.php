<?php
// File ini cuma buat debug, HAPUS setelah work!
echo "<h1>Environment Variables Check</h1>";
echo "<pre>";

// Cek via getenv()
echo "=== getenv() ===\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: ' NOT SET') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: ' NOT SET') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: ' NOT SET') . "\n";
echo "DB_PASS: " . (getenv('DB_PASS') ? ' SET' : ' NOT SET') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: ' NOT SET') . "\n";

// Cek via $_ENV (kadang beda di PHP-FPM)
echo "\n=== \$_ENV ===\n";
print_r($_ENV);

// Cek via $_SERVER (kadang env vars masuk sini)
echo "\n=== \$_SERVER (filter DB_) ===\n";
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'DB_') === 0) {
        echo "$key: $value\n";
    }
}
echo "</pre>";
?>
